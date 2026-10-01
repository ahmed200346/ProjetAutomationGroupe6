<?php

namespace DSA\REST;

defined( 'ABSPATH' ) || exit;

final class ProvidersController {
	private $settings;

	public function __construct() {
		$this->settings = new \DSA\Storage\ProviderSettings();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'dsa/v1',
			'/providers/(?P<provider>gemini|ollama|openrouter|nvidia)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_provider' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_provider' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/providers/(?P<provider>gemini|ollama|openrouter|nvidia)/discover',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'discover_models' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission(): bool {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		return current_user_can( 'manage_options' ) && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public function get_provider( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_key( $request['provider'] );
		return new \WP_REST_Response( $this->public_config( $provider ), 200 );
	}

	public function discover_models( \WP_REST_Request $request ) {
		$provider = sanitize_key( $request['provider'] );
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		try {
			$cache_key = '';
			if ( in_array( $provider, array( 'gemini', 'openrouter', 'nvidia' ), true ) ) {
				$stored_secret = 'gemini' === $provider ? $this->settings->get_api_key() : $this->settings->get_secret( $provider );
				$cache_key = hash( 'sha256', isset( $params['api_key'] ) && is_string( $params['api_key'] ) && '' !== trim( $params['api_key'] ) ? trim( $params['api_key'] ) : $stored_secret );
			}
			$cached = ! empty( $params['refresh'] ) ? false : $this->settings->get_cached_models( $provider, $cache_key );
			if ( false !== $cached ) {
				return new \WP_REST_Response( array( 'models' => $cached, 'cached' => true ), 200 );
			}
			if ( 'gemini' === $provider ) {
				$key = isset( $params['api_key'] ) && is_string( $params['api_key'] ) && '' !== trim( $params['api_key'] ) ? trim( $params['api_key'] ) : $this->settings->get_api_key();
				$models = ( new \DSA\Providers\GeminiProvider( $key ) )->list_models();
			} elseif ( 'openrouter' === $provider ) {
				$key = isset( $params['api_key'] ) && is_string( $params['api_key'] ) && '' !== trim( $params['api_key'] ) ? trim( $params['api_key'] ) : $this->settings->get_secret( 'openrouter' );
				$models = ( new \DSA\Providers\OpenRouterProvider( $key ) )->list_models();
			} elseif ( 'nvidia' === $provider ) {
				$key = isset( $params['api_key'] ) && is_string( $params['api_key'] ) && '' !== trim( $params['api_key'] ) ? trim( $params['api_key'] ) : $this->settings->get_secret( 'nvidia' );
				$models = ( new \DSA\Providers\NvidiaNimProvider( $key ) )->list_models();
			} else {
				$host = isset( $params['host'] ) ? $params['host'] : $this->settings->get( 'ollama' )['host'];
				$models = ( new \DSA\Providers\OllamaProvider( $host ) )->list_models();
			}
			$this->settings->cache_models( $provider, $models, $cache_key );
			return new \WP_REST_Response( array( 'models' => $models, 'cached' => false ), 200 );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'dsa_provider_error', __( 'Le provider IA est indisponible ou a refusé la requête. Vérifiez sa configuration et réessayez.', 'dsa' ), array( 'status' => 502 ) );
		}
	}

	public function save_provider( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_key( $request['provider'] );
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		return new \WP_REST_Response( $this->public_config( $provider, $this->settings->save( $provider, $params ) ), 200 );
	}

	private function public_config( $provider, $saved = null ): array {
		$config = is_array( $saved ) ? $saved : $this->settings->get( $provider );
		$key_provider = in_array( $provider, array( 'gemini', 'openrouter', 'nvidia' ), true );
		return array(
			'provider'      => $provider,
			'host'          => 'ollama' === $provider ? ( $config['host'] ?? 'http://localhost:11434' ) : '',
			'configured'    => $key_provider ? ! empty( $config['has_key'] ) : ! empty( $config['host'] ),
			'key_configured'=> $key_provider && ! empty( $config['has_key'] ),
			'key_mask'      => $key_provider && ! empty( $config['has_key'] ) ? '•••• (enregistrée)' : '',
			'models'        => $config['models'] ?? array(),
			'enabled'       => $config['enabled'] ?? array(),
			'default_model' => $config['default_model'] ?? '',
			'cache_ttl'     => \DSA\Storage\ProviderSettings::CACHE_TTL,
		);
	}
}
