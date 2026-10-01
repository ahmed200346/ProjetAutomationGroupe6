<?php
/**
 * OpenAI adapter (Chat Completions + Embeddings).
 *
 * Also serves as the base for any OpenAI-compatible endpoint (Ollama).
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Provider_OpenAI extends Rafiq_Provider {

	protected function base_url() {
		return 'https://api.openai.com/v1';
	}

	protected function api_key() {
		return (string) Rafiq_Settings::get( 'openai_api_key' );
	}

	protected function chat_model() {
		return (string) Rafiq_Settings::get( 'openai_model' );
	}

	protected function embed_model() {
		return (string) Rafiq_Settings::get( 'openai_embed_model' );
	}

	protected function label() {
		return 'OpenAI';
	}

	protected function headers() {
		$headers = array();
		$key     = $this->api_key();
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		return $headers;
	}

	public function chat( $system, array $messages, array $tools, array $opts ) {
		$this->require_key( $this->api_key(), $this->label() );

		$api_messages = array( array( 'role' => 'system', 'content' => $system ) );
		foreach ( $messages as $message ) {
			$api_messages[] = $this->to_api_message( $message );
		}

		$body = array(
			'model'       => $this->chat_model(),
			'messages'    => $api_messages,
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.4,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 1024,
		);

		if ( $tools ) {
			$body['tools'] = array_map(
				static function ( $tool ) {
					return array(
						'type'     => 'function',
						'function' => array(
							'name'        => $tool['name'],
							'description' => $tool['description'],
							'parameters'  => $tool['parameters'],
						),
					);
				},
				$tools
			);
		}

		$data    = $this->post_json( $this->base_url() . '/chat/completions', $this->headers(), $body );
		$choice  = isset( $data['choices'][0]['message'] ) ? $data['choices'][0]['message'] : array();
		$text    = isset( $choice['content'] ) ? (string) $choice['content'] : '';
		$calls   = array();

		if ( ! empty( $choice['tool_calls'] ) && is_array( $choice['tool_calls'] ) ) {
			foreach ( $choice['tool_calls'] as $call ) {
				if ( ! isset( $call['function']['name'] ) ) {
					continue;
				}
				$arguments = json_decode( isset( $call['function']['arguments'] ) ? $call['function']['arguments'] : '{}', true );
				$calls[]   = array(
					'id'        => isset( $call['id'] ) ? $call['id'] : uniqid( 'call_' ),
					'name'      => $call['function']['name'],
					'arguments' => is_array( $arguments ) ? $arguments : array(),
				);
			}
		}

		return array(
			'text'       => $text,
			'tool_calls' => $calls,
		);
	}

	private function to_api_message( array $message ) {
		if ( 'tool' === $message['role'] ) {
			return array(
				'role'         => 'tool',
				'tool_call_id' => $message['tool_call_id'],
				'content'      => (string) $message['content'],
			);
		}

		if ( 'assistant' === $message['role'] && ! empty( $message['tool_calls'] ) ) {
			return array(
				'role'       => 'assistant',
				'content'    => isset( $message['content'] ) ? $message['content'] : null,
				'tool_calls' => array_map(
					static function ( $call ) {
						return array(
							'id'       => $call['id'],
							'type'     => 'function',
							'function' => array(
								'name'      => $call['name'],
								'arguments' => wp_json_encode( $call['arguments'] ),
							),
						);
					},
					$message['tool_calls']
				),
			);
		}

		return array(
			'role'    => $message['role'],
			'content' => (string) $message['content'],
		);
	}

	public function embed( array $texts ) {
		$this->require_key( $this->api_key(), $this->label() );

		$vectors = array();
		foreach ( array_chunk( $texts, 64 ) as $batch ) {
			$data = $this->post_json(
				$this->base_url() . '/embeddings',
				$this->headers(),
				array(
					'model' => $this->embed_model(),
					'input' => array_values( $batch ),
				),
				120
			);
			if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
				throw new Exception( 'Embeddings response malformed.' );
			}
			foreach ( $data['data'] as $item ) {
				$vectors[] = isset( $item['embedding'] ) ? $item['embedding'] : array();
			}
		}
		return $vectors;
	}
}
