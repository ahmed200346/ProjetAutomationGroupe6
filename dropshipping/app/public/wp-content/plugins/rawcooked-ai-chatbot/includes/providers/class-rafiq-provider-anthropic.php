<?php
/**
 * Anthropic (Claude) adapter — Messages API with tool use.
 *
 * Anthropic has no embeddings endpoint; embeddings are routed to another
 * provider by Rafiq_Settings::resolve_embedding_provider().
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Provider_Anthropic extends Rafiq_Provider {

	const API_URL = 'https://api.anthropic.com/v1/messages';

	public function chat( $system, array $messages, array $tools, array $opts ) {
		$key = (string) Rafiq_Settings::get( 'anthropic_api_key' );
		$this->require_key( $key, 'Anthropic' );

		$body = array(
			'model'       => (string) Rafiq_Settings::get( 'anthropic_model' ),
			'system'      => $system,
			'messages'    => $this->to_api_messages( $messages ),
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.4,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 1024,
		);

		if ( $tools ) {
			$body['tools'] = array_map(
				static function ( $tool ) {
					return array(
						'name'         => $tool['name'],
						'description'  => $tool['description'],
						'input_schema' => $tool['parameters'],
					);
				},
				$tools
			);
		}

		$data = $this->post_json(
			self::API_URL,
			array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			$body
		);

		$text  = '';
		$calls = array();
		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				} elseif ( isset( $block['type'] ) && 'tool_use' === $block['type'] ) {
					$calls[] = array(
						'id'        => $block['id'],
						'name'      => $block['name'],
						'arguments' => is_array( $block['input'] ) ? $block['input'] : array(),
					);
				}
			}
		}

		return array(
			'text'       => $text,
			'tool_calls' => $calls,
		);
	}

	/**
	 * Convert internal messages to Anthropic's content-block format.
	 * Consecutive tool results are grouped into one user turn.
	 */
	private function to_api_messages( array $messages ) {
		$api = array();

		foreach ( $messages as $message ) {
			if ( 'tool' === $message['role'] ) {
				$block = array(
					'type'        => 'tool_result',
					'tool_use_id' => $message['tool_call_id'],
					'content'     => (string) $message['content'],
				);
				$last = count( $api ) - 1;
				if ( $last >= 0 && 'user' === $api[ $last ]['role'] && is_array( $api[ $last ]['content'] )
					&& isset( $api[ $last ]['content'][0]['type'] ) && 'tool_result' === $api[ $last ]['content'][0]['type'] ) {
					$api[ $last ]['content'][] = $block;
				} else {
					$api[] = array(
						'role'    => 'user',
						'content' => array( $block ),
					);
				}
				continue;
			}

			if ( 'assistant' === $message['role'] && ! empty( $message['tool_calls'] ) ) {
				$content = array();
				if ( ! empty( $message['content'] ) ) {
					$content[] = array(
						'type' => 'text',
						'text' => (string) $message['content'],
					);
				}
				foreach ( $message['tool_calls'] as $call ) {
					$content[] = array(
						'type'  => 'tool_use',
						'id'    => $call['id'],
						'name'  => $call['name'],
						'input' => empty( $call['arguments'] ) ? new stdClass() : $call['arguments'],
					);
				}
				$api[] = array(
					'role'    => 'assistant',
					'content' => $content,
				);
				continue;
			}

			$api[] = array(
				'role'    => $message['role'],
				'content' => (string) $message['content'],
			);
		}

		return $api;
	}

	public function embed( array $texts ) {
		throw new Exception( 'Anthropic does not provide an embeddings API. Configure Gemini, OpenAI or Ollama for embeddings.' );
	}
}
