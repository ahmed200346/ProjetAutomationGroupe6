<?php
/**
 * Provider abstraction.
 *
 * All adapters speak one internal dialect so the chat orchestrator never
 * cares which API is behind it.
 *
 * Internal message format:
 *   [ 'role' => 'user',      'content' => string ]
 *   [ 'role' => 'assistant', 'content' => string|null,
 *     'tool_calls' => [ [ 'id' => string, 'name' => string, 'arguments' => array ], ... ] ]
 *   [ 'role' => 'tool', 'tool_call_id' => string, 'name' => string, 'content' => string ]
 *
 * Tool definition format:
 *   [ 'name' => string, 'description' => string, 'parameters' => array (JSON schema) ]
 *
 * chat() returns:
 *   [ 'text' => string, 'tool_calls' => array (same shape as assistant tool_calls) ]
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Rafiq_Provider {

	/**
	 * Run one chat completion.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Internal-format messages.
	 * @param array  $tools    Tool definitions (may be empty).
	 * @param array  $opts     temperature, max_tokens.
	 * @return array [ 'text' => string, 'tool_calls' => array ]
	 * @throws Exception On API error.
	 */
	abstract public function chat( $system, array $messages, array $tools, array $opts );

	/**
	 * Embed a list of texts.
	 *
	 * @param string[] $texts Input texts.
	 * @return float[][] One vector per input.
	 * @throws Exception On API error or when unsupported.
	 */
	abstract public function embed( array $texts );

	/**
	 * Factory.
	 *
	 * @param string $id gemini|anthropic|openai|ollama.
	 * @return Rafiq_Provider
	 * @throws Exception For unknown ids.
	 */
	public static function make( $id ) {
		switch ( $id ) {
			case 'gemini':
				return new Rafiq_Provider_Gemini();
			case 'anthropic':
				return new Rafiq_Provider_Anthropic();
			case 'openai':
				return new Rafiq_Provider_OpenAI();
			case 'ollama':
				return new Rafiq_Provider_Ollama();
		}
		throw new Exception( 'Unknown provider: ' . esc_html( $id ) );
	}

	/**
	 * POST JSON, return decoded JSON, throw on HTTP or API errors.
	 *
	 * @throws Exception
	 */
	protected function post_json( $url, array $headers, array $body, $timeout = 60 ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'HTTP error: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$detail = '';
			if ( is_array( $data ) ) {
				if ( isset( $data['error']['message'] ) ) {
					$detail = $data['error']['message'];
				} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
					$detail = $data['error'];
				}
			}
			throw new Exception( esc_html( sprintf( 'API error (HTTP %d): %s', $code, $detail ? $detail : 'unknown' ) ) );
		}

		if ( ! is_array( $data ) ) {
			throw new Exception( 'Invalid JSON response from provider.' );
		}
		return $data;
	}

	protected function require_key( $key, $provider_label ) {
		if ( '' === trim( (string) $key ) ) {
			throw new Exception( esc_html( sprintf( '%s API key is not configured.', $provider_label ) ) );
		}
	}
}
