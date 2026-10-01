<?php
/**
 * Ollama adapter — local, free models (Llama 3.x, Mistral, Qwen, ...).
 *
 * Uses Ollama's OpenAI-compatible API (/v1), so it inherits the OpenAI
 * adapter wholesale. No API key involved.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Provider_Ollama extends Rafiq_Provider_OpenAI {

	protected function base_url() {
		$url = untrailingslashit( (string) Rafiq_Settings::get( 'ollama_url' ) );
		if ( '' === $url ) {
			$url = 'http://localhost:11434';
		}
		return $url . '/v1';
	}

	protected function api_key() {
		return 'ollama'; // Ollama ignores the key but the header must exist.
	}

	protected function chat_model() {
		return (string) Rafiq_Settings::get( 'ollama_model' );
	}

	protected function embed_model() {
		return (string) Rafiq_Settings::get( 'ollama_embed_model' );
	}

	protected function label() {
		return 'Ollama';
	}
}
