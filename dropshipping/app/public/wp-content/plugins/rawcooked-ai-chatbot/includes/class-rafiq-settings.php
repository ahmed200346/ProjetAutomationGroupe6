<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Settings {

	const OPTION_KEY = 'rafiq_ai_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	public static function defaults() {
		return array(
			// Provider.
			'provider'          => 'gemini',
			'gemini_api_key'    => '',
			'gemini_model'      => 'gemini-2.5-flash',
			'anthropic_api_key' => '',
			'anthropic_model'   => 'claude-haiku-4-5-20251001',
			'openai_api_key'    => '',
			'openai_model'      => 'gpt-4o-mini',
			'ollama_url'        => 'http://localhost:11434',
			'ollama_model'      => 'llama3.1',

			// Embeddings (RAG). "auto" picks the best available provider.
			'embedding_provider'     => 'auto',
			'gemini_embed_model'     => 'gemini-embedding-001',
			'openai_embed_model'     => 'text-embedding-3-small',
			'ollama_embed_model'     => 'nomic-embed-text',

			// Knowledge sources.
			'index_products' => 1,
			'index_pages'    => 1,
			'index_posts'    => 1,

			// Tools.
			'tool_product_search' => 1,
			'tool_orders'         => 1,

			// Behavior.
			'bot_name'        => 'Assistant',
			'welcome_message' => __( 'Hi! How can I help you today?', 'rawcooked-ai-chatbot' ),
			'system_prompt'   => '',
			'temperature'     => '0.4',
			'max_tokens'      => 1024,
			'history_limit'   => 12,
			'rate_limit'      => 10,
			'rag_results'     => 6,

			// Appearance.
			'display_mode'   => 'bubble', // bubble | sidebar | none (shortcode only).
			'position'       => 'right',  // right | left.
			'shape'          => 'round',  // round | soft | square.
			'theme'          => 'classic', // classic | midnight | glass | gradient | minimal.
			'primary_color'  => '#c8402f',
			'gradient_color' => '#7c3aed',
			'avatar_emoji'   => "\u{1F355}",
			'avatar_image'   => '',
			'show_sources'   => 1,
			'animation'      => 'pop', // pop | slide | fade | bounce.
			'glass_blur'     => 18,
			'mobile_layout'  => 'full', // full | half.
			'exclude_pages'  => '',

			// Engagement.
			'quick_replies'   => '',
			'auto_open_delay' => 0,
			'teaser_text'     => '',
			'teaser_delay'    => 0,
			'tone'            => 'friendly', // friendly | professional | playful.
			'lead_capture'    => 'off',      // off | optional | required.

			// Conversations.
			'log_conversations' => 1,
			'retention_days'    => 30,
		);
	}

	public static function get_all() {
		if ( null === self::$cache ) {
			$saved = get_option( self::OPTION_KEY, array() );
			/**
			 * Filters the settings defaults, letting add-ons register their own.
			 *
			 * @param array $defaults Default settings.
			 */
			$defaults    = apply_filters( 'rafiq_settings_defaults', self::defaults() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $new ) {
		$clean = self::sanitize( $new );
		$all   = array_merge( self::get_all(), $clean );
		update_option( self::OPTION_KEY, $all, false );
		self::$cache = $all;
		return $all;
	}

	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$clean    = array();

		$text_keys = array(
			'gemini_api_key', 'gemini_model', 'anthropic_api_key', 'anthropic_model',
			'openai_api_key', 'openai_model', 'ollama_model',
			'gemini_embed_model', 'openai_embed_model', 'ollama_embed_model',
			'bot_name', 'avatar_emoji', 'teaser_text',
		);
		foreach ( $text_keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		if ( array_key_exists( 'ollama_url', $input ) ) {
			$clean['ollama_url'] = esc_url_raw( trim( (string) $input['ollama_url'] ), array( 'http', 'https' ) );
		}
		if ( array_key_exists( 'avatar_image', $input ) ) {
			$clean['avatar_image'] = esc_url_raw( trim( (string) $input['avatar_image'] ), array( 'http', 'https' ) );
		}
		if ( array_key_exists( 'exclude_pages', $input ) ) {
			$clean['exclude_pages'] = sanitize_textarea_field( (string) $input['exclude_pages'] );
		}

		if ( array_key_exists( 'welcome_message', $input ) ) {
			$clean['welcome_message'] = sanitize_textarea_field( (string) $input['welcome_message'] );
		}
		if ( array_key_exists( 'quick_replies', $input ) ) {
			$clean['quick_replies'] = sanitize_textarea_field( (string) $input['quick_replies'] );
		}
		if ( array_key_exists( 'system_prompt', $input ) ) {
			$clean['system_prompt'] = sanitize_textarea_field( (string) $input['system_prompt'] );
		}

		if ( array_key_exists( 'provider', $input ) ) {
			$clean['provider'] = in_array( $input['provider'], array( 'gemini', 'anthropic', 'openai', 'ollama' ), true )
				? $input['provider'] : $defaults['provider'];
		}
		if ( array_key_exists( 'embedding_provider', $input ) ) {
			$clean['embedding_provider'] = in_array( $input['embedding_provider'], array( 'auto', 'gemini', 'openai', 'ollama', 'none' ), true )
				? $input['embedding_provider'] : 'auto';
		}
		if ( array_key_exists( 'display_mode', $input ) ) {
			$clean['display_mode'] = in_array( $input['display_mode'], array( 'bubble', 'sidebar', 'none' ), true )
				? $input['display_mode'] : $defaults['display_mode'];
		}
		if ( array_key_exists( 'position', $input ) ) {
			$clean['position'] = in_array( $input['position'], array( 'right', 'left' ), true ) ? $input['position'] : 'right';
		}
		if ( array_key_exists( 'shape', $input ) ) {
			$clean['shape'] = in_array( $input['shape'], array( 'round', 'soft', 'square' ), true ) ? $input['shape'] : 'round';
		}
		if ( array_key_exists( 'theme', $input ) ) {
			$clean['theme'] = in_array( $input['theme'], array( 'classic', 'midnight', 'glass', 'gradient', 'minimal' ), true )
				? $input['theme'] : 'classic';
		}
		if ( array_key_exists( 'animation', $input ) ) {
			$clean['animation'] = in_array( $input['animation'], array( 'pop', 'slide', 'fade', 'bounce' ), true )
				? $input['animation'] : 'pop';
		}
		if ( array_key_exists( 'mobile_layout', $input ) ) {
			$clean['mobile_layout'] = in_array( $input['mobile_layout'], array( 'full', 'half' ), true )
				? $input['mobile_layout'] : 'full';
		}
		if ( array_key_exists( 'tone', $input ) ) {
			$clean['tone'] = in_array( $input['tone'], array( 'friendly', 'professional', 'playful' ), true )
				? $input['tone'] : 'friendly';
		}
		if ( array_key_exists( 'lead_capture', $input ) ) {
			$clean['lead_capture'] = in_array( $input['lead_capture'], array( 'off', 'optional', 'required' ), true )
				? $input['lead_capture'] : 'off';
		}
		if ( array_key_exists( 'primary_color', $input ) ) {
			$color                  = sanitize_hex_color( (string) $input['primary_color'] );
			$clean['primary_color'] = $color ? $color : $defaults['primary_color'];
		}
		if ( array_key_exists( 'gradient_color', $input ) ) {
			$color                   = sanitize_hex_color( (string) $input['gradient_color'] );
			$clean['gradient_color'] = $color ? $color : $defaults['gradient_color'];
		}

		$bool_keys = array( 'index_products', 'index_pages', 'index_posts', 'tool_product_search', 'tool_orders', 'show_sources', 'log_conversations' );
		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
			}
		}

		if ( array_key_exists( 'temperature', $input ) ) {
			$clean['temperature'] = (string) min( 2, max( 0, (float) $input['temperature'] ) );
		}
		$int_keys = array(
			'max_tokens'      => array( 64, 8192 ),
			'history_limit'   => array( 2, 40 ),
			'rate_limit'      => array( 1, 120 ),
			'rag_results'     => array( 1, 20 ),
			'auto_open_delay' => array( 0, 120 ),
			'teaser_delay'    => array( 0, 300 ),
			'retention_days'  => array( 1, 365 ),
			'glass_blur'      => array( 0, 40 ),
		);
		foreach ( $int_keys as $key => $range ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = min( $range[1], max( $range[0], (int) $input[ $key ] ) );
			}
		}

		/**
		 * Filters the sanitized settings, letting add-ons sanitize their own keys.
		 *
		 * @param array $clean Sanitized values.
		 * @param array $input Raw submitted values.
		 */
		return apply_filters( 'rafiq_settings_sanitize', $clean, $input );
	}

	/**
	 * Resolve which provider handles embeddings, honoring the "auto" mode.
	 * Anthropic has no embeddings API, so auto falls back to the first
	 * configured alternative. Returns provider id or empty string (keyword-only RAG).
	 */
	public static function resolve_embedding_provider() {
		$choice = self::get( 'embedding_provider' );
		if ( 'none' === $choice ) {
			return '';
		}
		if ( 'auto' !== $choice ) {
			return self::embedding_provider_ready( $choice ) ? $choice : '';
		}

		// Ollama's URL has a default value, so "configured" cannot be inferred
		// from it: in auto mode Ollama is only a candidate when it is the chat
		// provider (an explicit embedding_provider=ollama choice still works).
		$chat = self::get( 'provider' );
		$candidates = array( $chat, 'gemini', 'openai' );
		foreach ( $candidates as $candidate ) {
			if ( self::embedding_provider_ready( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	private static function embedding_provider_ready( $provider ) {
		switch ( $provider ) {
			case 'gemini':
				return '' !== self::get( 'gemini_api_key' );
			case 'openai':
				return '' !== self::get( 'openai_api_key' );
			case 'ollama':
				return '' !== self::get( 'ollama_url' );
			default:
				return false; // Anthropic: no embeddings endpoint.
		}
	}
}
