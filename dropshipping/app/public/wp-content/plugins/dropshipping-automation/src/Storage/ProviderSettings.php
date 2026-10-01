<?php

namespace DSA\Storage;

defined( 'ABSPATH' ) || exit;

final class ProviderSettings {
	const OPTION = 'dsa_provider_settings';
	const CACHE_TTL = 900;

	public function get( $provider ): array {
		$all = get_option( self::OPTION, array() );
		$value = is_array( $all ) && is_array( $all[ $provider ] ?? null ) ? $all[ $provider ] : array();
		$value['host'] = is_string( $value['host'] ?? null ) ? $value['host'] : 'http://localhost:11434';
		$value['models'] = $this->sanitize_models( is_array( $value['models'] ?? null ) ? $value['models'] : array() );
		$enabled = is_array( $value['enabled'] ?? null ) ? array_filter( $value['enabled'], 'is_scalar' ) : array();
		$model_ids = array_column( $value['models'], 'id' );
		$value['enabled'] = array_values( array_intersect( array_map( static function ( $model_id ) { return sanitize_text_field( (string) $model_id ); }, $enabled ), $model_ids ) );
		$value['default_model'] = is_string( $value['default_model'] ?? null ) && in_array( $value['default_model'], $value['enabled'], true ) ? sanitize_text_field( $value['default_model'] ) : '';
		$constant_key = 'gemini' === $provider && defined( 'DSA_GEMINI_API_KEY' ) && is_string( DSA_GEMINI_API_KEY ) && '' !== trim( DSA_GEMINI_API_KEY );
		$stored_key = is_string( $value['api_key'] ?? null ) && '' !== $value['api_key'];
		$value['has_key'] = in_array( $provider, array( 'gemini', 'openrouter', 'nvidia' ), true ) && ( $constant_key || $stored_key );
		return $value;
	}

	public function get_api_key(): string {
		return $this->get_secret( 'gemini' );
	}

	public function get_secret( $provider ): string {
		if ( 'gemini' === $provider && defined( 'DSA_GEMINI_API_KEY' ) && is_string( DSA_GEMINI_API_KEY ) && '' !== trim( DSA_GEMINI_API_KEY ) ) {
			return trim( DSA_GEMINI_API_KEY );
		}
		$all = get_option( self::OPTION, array() );
		$encrypted = is_array( $all ) && is_array( $all[ $provider ] ?? null ) ? ( $all[ $provider ]['api_key'] ?? '' ) : '';
		return $this->decrypt( $encrypted );
	}

	public function save( $provider, array $input ): array {
		$allowed = array( 'gemini', 'ollama', 'openrouter', 'nvidia' );
		if ( ! in_array( $provider, $allowed, true ) ) {
			return array();
		}
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$current = is_array( $all[ $provider ] ?? null ) ? $all[ $provider ] : array();
		$current_models = $this->sanitize_models( is_array( $current['models'] ?? null ) ? $current['models'] : array() );
		$models = is_array( $input['models'] ?? null ) ? $this->sanitize_models( $input['models'] ) : $current_models;
		$ids = array_values( array_filter( array_map( function ( $model ) { return $model['id']; }, $models ) ) );
		$enabled_input = is_array( $input['enabled'] ?? null ) ? array_filter( $input['enabled'], 'is_scalar' ) : null;
		$current_enabled = is_array( $current['enabled'] ?? null ) ? array_filter( $current['enabled'], 'is_scalar' ) : array();
		$current_enabled = array_values( array_intersect( array_map( static function ( $value ) { return sanitize_text_field( (string) $value ); }, $current_enabled ), array_column( $current_models, 'id' ) ) );
		$enabled = is_array( $enabled_input ) ? array_values( array_intersect( array_map( static function ( $value ) { return sanitize_text_field( (string) $value ); }, $enabled_input ), $ids ) ) : $current_enabled;
		$default = isset( $input['default_model'] ) && is_scalar( $input['default_model'] ) ? sanitize_text_field( (string) $input['default_model'] ) : ( $current['default_model'] ?? '' );
		$all[ $provider ] = array(
			'host'         => 'ollama' === $provider ? ( new \DSA\Providers\OllamaProvider( $input['host'] ?? $current['host'] ?? '' ) )->get_status()['host'] : '',
			'models'       => $models,
			'enabled'      => array_values( array_intersect( $enabled, $ids ) ),
			'default_model'=> in_array( $default, $enabled, true ) ? $default : '',
		);
		if ( in_array( $provider, array( 'gemini', 'openrouter', 'nvidia' ), true ) ) {
			$constant_key = 'gemini' === $provider && defined( 'DSA_GEMINI_API_KEY' ) && is_string( DSA_GEMINI_API_KEY ) && '' !== trim( DSA_GEMINI_API_KEY );
			if ( ! empty( $input['remove_key'] ) && ! $constant_key ) {
				$all[ $provider ]['api_key'] = '';
			} elseif ( isset( $input['api_key'] ) && is_string( $input['api_key'] ) && '' !== trim( $input['api_key'] ) && ! $constant_key ) {
				$all[ $provider ]['api_key'] = $this->encrypt( trim( $input['api_key'] ) );
			} elseif ( isset( $current['api_key'] ) ) {
				$all[ $provider ]['api_key'] = $current['api_key'];
			}
		}
		update_option( self::OPTION, $all, false );
		$this->clear_cache( $provider );
		return $this->get( $provider );
	}

	public function get_cached_models( $provider, $cache_key = '' ) {
		$value = get_transient( $this->cache_name( $provider, $cache_key ) );
		return is_array( $value ) ? $value : false;
	}

	public function cache_models( $provider, array $models, $cache_key = '' ): void {
		set_transient( $this->cache_name( $provider, $cache_key ), $models, self::CACHE_TTL );
	}

	public function clear_cache( $provider ): void {
		delete_transient( 'dsa_models_' . sanitize_key( $provider ) );
	}

	private function cache_name( $provider, $cache_key = '' ): string {
		$suffix = is_string( $cache_key ) && '' !== $cache_key ? '_' . substr( sanitize_key( $cache_key ), 0, 32 ) : '';
		return 'dsa_models_' . sanitize_key( $provider ) . $suffix;
	}

	private function encrypt( $value ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$iv = random_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'aes-256-cbc', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv );
		return false === $cipher ? '' : base64_encode( $iv . $cipher );
	}

	private function decrypt( $value ): string {
		if ( ! is_string( $value ) || '' === $value || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$decoded = base64_decode( $value, true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $decoded, 16 ), 'aes-256-cbc', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $decoded, 0, 16 ) );
		return is_string( $plain ) ? $plain : '';
	}

	private function sanitize_models( array $models ): array {
		$clean = array();
		foreach ( $models as $model ) {
			if ( ! is_array( $model ) || ! isset( $model['id'] ) || ! is_scalar( $model['id'] ) || '' === (string) $model['id'] ) {
				continue;
			}
			$fields = array_intersect_key( $model, array_flip( array( 'id', 'name', 'description', 'size', 'family', 'quantization', 'modified_at' ) ) );
			$fields = array_filter( $fields, 'is_scalar' );
			$clean[] = array_map( static function ( $value ) { return sanitize_text_field( (string) $value ); }, $fields );
		}
		return $clean;
	}
}
