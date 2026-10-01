<?php

namespace DSA\Integrations;

defined( 'ABSPATH' ) || exit;

final class N8nWebhookClient {
	const TOKEN_HEADER = 'X-DSA-Webhook-Token';

	private $url;
	private $token;
	private $callbackUrl;
	private $transport;
	private $enabled;

	public function __construct( $url = null, $token = null, $transport = null, $enabled = null, $callback_url = null ) {
		$this->url = null === $url && defined( 'DSA_N8N_WEBHOOK_URL' ) ? DSA_N8N_WEBHOOK_URL : $url;
		$this->token = null === $token && defined( 'DSA_N8N_WEBHOOK_TOKEN' ) ? DSA_N8N_WEBHOOK_TOKEN : $token;
		$this->callbackUrl = null === $callback_url && defined( 'DSA_N8N_CALLBACK_URL' ) ? DSA_N8N_CALLBACK_URL : $callback_url;
		$this->transport = is_callable( $transport ) ? $transport : 'wp_remote_post';
		$this->enabled = null === $enabled ? ( defined( 'DSA_N8N_LIVE_ENABLED' ) && true === DSA_N8N_LIVE_ENABLED ) : ( true === $enabled );
	}

	public function is_configured() {
		if ( ! $this->enabled || ! is_string( $this->token ) || strlen( $this->token ) < 32 ) {
			return false;
		}
		return $this->is_safe_endpoint( $this->url, false ) && $this->is_safe_endpoint( $this->callbackUrl, true );
	}

	public function dispatch( array $payload ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'dsa_n8n_not_configured', __( 'Le webhook n8n doit être configuré et activé explicitement dans wp-config.php.', 'dsa' ), array( 'status' => 503 ) );
		}

		$payload = $this->sanitize_payload( $payload );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$payload['callback_url'] = $this->callbackUrl;

		try {
			$response = call_user_func(
				$this->transport,
				$this->url,
				array(
					'timeout' => 8,
					'redirection' => 0,
					'sslverify' => true,
					'headers' => array(
						'Content-Type' => 'application/json',
						self::TOKEN_HEADER => $this->token,
					),
					'body' => wp_json_encode( $payload ),
				)
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'dsa_n8n_transport_failed', __( 'La transmission au webhook n8n a échoué.', 'dsa' ), array( 'status' => 502 ) );
		}

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'dsa_n8n_unreachable', __( 'n8n ne répond pas. Le lancement n’a pas été transmis.', 'dsa' ), array( 'status' => 502 ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'dsa_n8n_rejected', __( 'n8n a refusé le lancement. Vérifiez le webhook et son authentification.', 'dsa' ), array( 'status' => 502 ) );
		}

		return array( 'accepted' => true, 'http_status' => $status, 'run_id' => $payload['run_id'] );
	}

	private function sanitize_payload( array $payload ) {
		$run_id = isset( $payload['run_id'] ) && is_string( $payload['run_id'] ) ? sanitize_text_field( $payload['run_id'] ) : '';
		$query = isset( $payload['query'] ) && is_string( $payload['query'] ) ? sanitize_text_field( $payload['query'] ) : '';
		$marketplace = isset( $payload['marketplace'] ) && is_string( $payload['marketplace'] ) ? sanitize_key( $payload['marketplace'] ) : '';
		$limit = isset( $payload['limit'] ) && is_scalar( $payload['limit'] ) ? absint( $payload['limit'] ) : 0;

		if ( '' === $run_id || '' === $query || ! in_array( $marketplace, N8nWorkflowSettings::MARKETPLACES, true ) || $limit < 1 || $limit > 20 ) {
			return new \WP_Error( 'dsa_n8n_invalid_payload', __( 'Recherche, marketplace, limite ou run_id invalide.', 'dsa' ), array( 'status' => 400 ) );
		}

		return array(
			'run_id' => $run_id,
			'query' => function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 120 ) : substr( $query, 0, 120 ),
			'marketplace' => $marketplace,
			'limit' => $limit,
		);
	}

	private function is_safe_endpoint( $url, $allow_docker_host ) {
		if ( ! is_string( $url ) ) {
			return false;
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host = strtolower( trim( $parts['host'], '[]' ) );
		$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
		if ( $allow_docker_host && 'host.docker.internal' === $host ) {
			$is_local = true;
		}
		return 'https' === $scheme || ( 'http' === $scheme && $is_local );
	}
}