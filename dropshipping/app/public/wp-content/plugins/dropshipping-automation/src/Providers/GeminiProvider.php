<?php

namespace DSA\Providers;

defined( 'ABSPATH' ) || exit;

final class GeminiProvider implements Provider {
	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = is_string( $api_key ) ? trim( $api_key ) : '';
	}

	public function list_models(): array {
		if ( '' === $this->api_key ) {
			throw new \RuntimeException( __( 'La clé API Gemini est absente.', 'dsa' ) );
		}

		$models = array();
		$page_token = '';
		for ( $page = 0; $page < 10; $page++ ) {
			$url = add_query_arg( 'pageSize', 1000, 'https://generativelanguage.googleapis.com/v1beta/models' );
			if ( '' !== $page_token ) {
				$url = add_query_arg( 'pageToken', $page_token, $url );
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 2,
					'sslverify'   => true,
					'httpversion' => '1.1',
					'headers'     => array(
						'Accept'        => 'application/json',
						'x-goog-api-key' => $this->api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$transport_error = sanitize_text_field( $response->get_error_message() );
				$transport_error = str_replace( $this->api_key, '[clé masquée]', $transport_error );
				throw new \RuntimeException(
					$transport_error
					? sprintf( __( 'Gemini est injoignable depuis le serveur WordPress. Détail réseau : %s', 'dsa' ), $transport_error )
					: __( 'Gemini est injoignable depuis le serveur WordPress. Vérifiez la sortie HTTPS, le DNS et le certificat SSL du serveur.', 'dsa' )
				);
			}

			$status = wp_remote_retrieve_response_code( $response );
			if ( 400 === $status || 401 === $status || 403 === $status ) {
				$message = self::get_error_message( $response );
				throw new \RuntimeException( $message ? sprintf( __( 'Gemini a refusé la requête : %s', 'dsa' ), $message ) : __( 'La clé API Gemini est invalide, non autorisée ou l’API Generative Language n’est pas activée.', 'dsa' ) );
			}
			if ( 429 === $status ) {
				throw new \RuntimeException( __( 'Le quota Gemini est atteint. Réessayez plus tard.', 'dsa' ) );
			}
			if ( 200 !== $status ) {
				throw new \RuntimeException( __( 'Gemini a renvoyé une erreur lors du chargement des modèles.', 'dsa' ) );
			}

			$payload = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $payload ) ) {
				throw new \RuntimeException( __( 'La réponse Gemini est invalide.', 'dsa' ) );
			}

			$models = array_merge( $models, self::parse_models( $payload ) );
			$page_token = isset( $payload['nextPageToken'] ) && is_string( $payload['nextPageToken'] ) ? $payload['nextPageToken'] : '';
			if ( '' === $page_token ) {
				break;
			}
		}

		return $models;
	}

	public function get_status(): array {
		return array( 'configured' => '' !== $this->api_key );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse_models( array $payload ): array {
		$models = array();
		foreach ( isset( $payload['models'] ) && is_array( $payload['models'] ) ? $payload['models'] : array() as $model ) {
			if ( ! is_array( $model ) || empty( $model['name'] ) || ! is_array( $model['supportedGenerationMethods'] ?? null ) || ! in_array( 'generateContent', $model['supportedGenerationMethods'], true ) ) {
				continue;
			}
			$models[] = array(
				'id'          => sanitize_text_field( (string) $model['name'] ),
				'name'        => sanitize_text_field( (string) ( $model['displayName'] ?? $model['name'] ) ),
				'description' => sanitize_text_field( (string) ( $model['description'] ?? '' ) ),
			);
		}
		return $models;
	}

	private static function get_error_message( array $response ): string {
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = is_array( $payload ) && is_array( $payload['error'] ?? null ) ? ( $payload['error']['message'] ?? '' ) : '';
		return is_string( $message ) ? sanitize_text_field( $message ) : '';
	}
}
