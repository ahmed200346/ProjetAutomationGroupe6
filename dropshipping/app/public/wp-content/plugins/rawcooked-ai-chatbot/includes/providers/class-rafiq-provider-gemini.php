<?php
/**
 * Google Gemini adapter — generateContent + batchEmbedContents.
 *
 * The free tier makes this the default provider: a site can run the bot
 * (chat + embeddings) without paying anything.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Provider_Gemini extends Rafiq_Provider {

	const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

	public function chat( $system, array $messages, array $tools, array $opts ) {
		$key = (string) Rafiq_Settings::get( 'gemini_api_key' );
		$this->require_key( $key, 'Gemini' );
		$model = rawurlencode( (string) Rafiq_Settings::get( 'gemini_model' ) );

		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $system ) ),
			),
			'contents'          => $this->to_api_contents( $messages ),
			'generationConfig'  => array(
				'temperature'     => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.4,
				'maxOutputTokens' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 1024,
			),
		);

		if ( $tools ) {
			$declarations = array();
			foreach ( $tools as $tool ) {
				$declaration = array(
					'name'        => $tool['name'],
					'description' => $tool['description'],
				);
				// Gemini rejects an empty properties map: omit parameters instead.
				$props = isset( $tool['parameters']['properties'] ) ? $tool['parameters']['properties'] : null;
				if ( is_array( $props ) && ! empty( $props ) ) {
					$declaration['parameters'] = $tool['parameters'];
				}
				$declarations[] = $declaration;
			}
			$body['tools'] = array( array( 'functionDeclarations' => $declarations ) );
		}

		$data = $this->post_json(
			self::BASE_URL . $model . ':generateContent',
			array( 'x-goog-api-key' => $key ),
			$body
		);

		$text  = '';
		$calls = array();
		$parts = isset( $data['candidates'][0]['content']['parts'] ) ? $data['candidates'][0]['content']['parts'] : array();
		foreach ( $parts as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= $part['text'];
			} elseif ( isset( $part['functionCall']['name'] ) ) {
				$calls[] = array(
					'id'        => uniqid( 'gm_' ), // Gemini has no call ids; ours pairs call and result.
					'name'      => $part['functionCall']['name'],
					'arguments' => isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] )
						? $part['functionCall']['args'] : array(),
				);
			}
		}

		return array(
			'text'       => $text,
			'tool_calls' => $calls,
		);
	}

	private function to_api_contents( array $messages ) {
		$contents = array();

		foreach ( $messages as $message ) {
			if ( 'tool' === $message['role'] ) {
				$part = array(
					'functionResponse' => array(
						'name'     => $message['name'],
						'response' => array( 'content' => (string) $message['content'] ),
					),
				);
				$last = count( $contents ) - 1;
				if ( $last >= 0 && 'user' === $contents[ $last ]['role'] && isset( $contents[ $last ]['parts'][0]['functionResponse'] ) ) {
					$contents[ $last ]['parts'][] = $part;
				} else {
					$contents[] = array(
						'role'  => 'user',
						'parts' => array( $part ),
					);
				}
				continue;
			}

			if ( 'assistant' === $message['role'] && ! empty( $message['tool_calls'] ) ) {
				$parts = array();
				if ( ! empty( $message['content'] ) ) {
					$parts[] = array( 'text' => (string) $message['content'] );
				}
				foreach ( $message['tool_calls'] as $call ) {
					$parts[] = array(
						'functionCall' => array(
							'name' => $call['name'],
							'args' => empty( $call['arguments'] ) ? new stdClass() : $call['arguments'],
						),
					);
				}
				$contents[] = array(
					'role'  => 'model',
					'parts' => $parts,
				);
				continue;
			}

			$contents[] = array(
				'role'  => ( 'assistant' === $message['role'] ) ? 'model' : 'user',
				'parts' => array( array( 'text' => (string) $message['content'] ) ),
			);
		}

		return $contents;
	}

	public function embed( array $texts ) {
		$key = (string) Rafiq_Settings::get( 'gemini_api_key' );
		$this->require_key( $key, 'Gemini' );
		$model = (string) Rafiq_Settings::get( 'gemini_embed_model' );

		$vectors = array();
		foreach ( array_chunk( $texts, 50 ) as $batch ) {
			$requests = array();
			foreach ( $batch as $text ) {
				$requests[] = array(
					'model'   => 'models/' . $model,
					'content' => array( 'parts' => array( array( 'text' => $text ) ) ),
					// gemini-embedding-001 defaults to 3072 dims; 768 keeps
					// storage and cosine cost sane with near-identical quality.
					'outputDimensionality' => 768,
				);
			}
			$data = $this->post_json(
				self::BASE_URL . rawurlencode( $model ) . ':batchEmbedContents',
				array( 'x-goog-api-key' => $key ),
				array( 'requests' => $requests ),
				120
			);
			if ( empty( $data['embeddings'] ) || ! is_array( $data['embeddings'] ) ) {
				throw new Exception( 'Embeddings response malformed.' );
			}
			foreach ( $data['embeddings'] as $item ) {
				$vectors[] = isset( $item['values'] ) ? $item['values'] : array();
			}
		}
		return $vectors;
	}
}
