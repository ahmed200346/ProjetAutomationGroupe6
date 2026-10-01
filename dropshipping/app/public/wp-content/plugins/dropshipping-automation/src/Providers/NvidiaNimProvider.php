<?php

namespace DSA\Providers;

defined( 'ABSPATH' ) || exit;

final class NvidiaNimProvider implements Provider {
	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = is_string( $api_key ) ? trim( $api_key ) : '';
	}

	public function list_models(): array {
		if ( '' === $this->api_key ) {
			throw new \RuntimeException( __( 'La clé API NVIDIA NIM est absente.', 'dsa' ) );
		}
		$response = wp_remote_get( 'https://integrate.api.nvidia.com/v1/models', array( 'timeout' => 10, 'redirection' => 2, 'sslverify' => true, 'headers' => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->api_key ) ) );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( __( 'NVIDIA NIM est injoignable depuis le serveur WordPress.', 'dsa' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 401 === $status || 403 === $status ) {
			throw new \RuntimeException( __( 'La clé API NVIDIA NIM est invalide ou refusée.', 'dsa' ) );
		}
		if ( 200 !== $status ) {
			throw new \RuntimeException( __( 'NVIDIA NIM a renvoyé une erreur lors du chargement des modèles.', 'dsa' ) );
		}
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) ) {
			throw new \RuntimeException( __( 'La réponse NVIDIA NIM est invalide.', 'dsa' ) );
		}
		return self::parse_models( $payload );
	}

	public function get_status(): array { return array( 'configured' => '' !== $this->api_key ); }

	public static function parse_models( array $payload ): array {
		$models = array();
		foreach ( is_array( $payload['data'] ?? null ) ? $payload['data'] : array() as $model ) {
			if ( ! is_array( $model ) || empty( $model['id'] ) ) { continue; }
			$models[] = array( 'id' => sanitize_text_field( (string) $model['id'] ), 'name' => sanitize_text_field( (string) ( $model['id'] ) ), 'description' => sanitize_text_field( (string) ( $model['owned_by'] ?? '' ) ) );
		}
		return $models;
	}
}
