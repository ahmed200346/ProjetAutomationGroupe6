<?php

namespace DSA\Providers;

defined( 'ABSPATH' ) || exit;

final class OllamaProvider implements Provider {
	private $host;

	public function __construct( $host ) {
		$this->host = self::normalize_host( $host );
	}

	public function list_models(): array {
		if ( '' === $this->host ) {
			throw new \RuntimeException( __( 'L’URL Ollama doit utiliser http ou https et contenir un hôte valide.', 'dsa' ) );
		}

		$response = wp_remote_get(
			$this->host . '/api/tags',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( __( 'Ollama est injoignable depuis le serveur WordPress.', 'dsa' ) );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new \RuntimeException( __( 'Ollama a refusé la connexion ou l’URL est incorrecte.', 'dsa' ) );
		}
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) ) {
			throw new \RuntimeException( __( 'La réponse Ollama est invalide.', 'dsa' ) );
		}
		return self::parse_models( $payload );
	}

	public function get_status(): array {
		return array( 'configured' => '' !== $this->host, 'host' => $this->host );
	}

	public static function normalize_host( $host ): string {
		$host = is_string( $host ) ? untrailingslashit( trim( $host ) ) : '';
		$parts = wp_parse_url( $host );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || isset( $parts['user'], $parts['pass'] ) || isset( $parts['query'], $parts['fragment'] ) ) {
			return '';
		}
		return $host;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse_models( array $payload ): array {
		$models = array();
		foreach ( isset( $payload['models'] ) && is_array( $payload['models'] ) ? $payload['models'] : array() as $model ) {
			if ( ! is_array( $model ) || empty( $model['name'] ) ) {
				continue;
			}
			$details = is_array( $model['details'] ?? null ) ? $model['details'] : array();
			$models[] = array(
				'id'           => sanitize_text_field( (string) $model['name'] ),
				'name'         => sanitize_text_field( (string) $model['name'] ),
				'size'         => isset( $model['size'] ) ? absint( $model['size'] ) : 0,
				'family'       => sanitize_text_field( (string) ( $details['family'] ?? '' ) ),
				'quantization' => sanitize_text_field( (string) ( $details['quantization_level'] ?? '' ) ),
				'modified_at'  => sanitize_text_field( (string) ( $model['modified_at'] ?? '' ) ),
			);
		}
		return $models;
	}
}
