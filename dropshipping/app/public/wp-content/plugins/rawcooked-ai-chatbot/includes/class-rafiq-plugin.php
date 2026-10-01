<?php
/**
 * Plugin orchestrator: hooks, assets, widget output, shortcode, cron.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Plugin {

	/**
	 * @var Rafiq_Plugin|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( 'Rafiq_Rest', 'register_routes' ) );

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'wp_footer', array( $this, 'render_widget_mount' ) );
		add_shortcode( 'rafiq_chatbot', array( $this, 'shortcode' ) );

		// Admin.
		if ( is_admin() ) {
			new Rafiq_Admin();
		}

		// Indexing hooks.
		add_action( 'save_post', array( 'Rafiq_Indexer', 'schedule_post_reindex' ), 20, 2 );
		add_action( 'rafiq_reindex_single', array( 'Rafiq_Indexer', 'reindex_single' ), 10, 2 );
		add_action( 'rafiq_nightly_index', array( 'Rafiq_Indexer', 'cron_full_index' ) );
		add_action( 'rafiq_nightly_index', array( 'Rafiq_Conversations', 'cron_purge' ) );

		// Plugin updates don't re-run activation: upgrade schema when needed.
		if ( get_option( 'rafiq_db_version' ) !== RAFIQ_AI_VERSION ) {
			Rafiq_Vector_Store::install();
			Rafiq_Conversations::install();

			// Google retired text-embedding-004 (mid-2026): migrate saved
			// settings to its replacement so embeddings keep working.
			if ( 'text-embedding-004' === Rafiq_Settings::get( 'gemini_embed_model' ) ) {
				Rafiq_Settings::update( array( 'gemini_embed_model' => 'gemini-embedding-001' ) );
			}

			update_option( 'rafiq_db_version', RAFIQ_AI_VERSION, false );
		}
	}

	public static function activate() {
		Rafiq_Vector_Store::install();
		Rafiq_Conversations::install();
		update_option( 'rafiq_db_version', RAFIQ_AI_VERSION, false );
		if ( ! wp_next_scheduled( 'rafiq_nightly_index' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'rafiq_nightly_index' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'rafiq_nightly_index' );
		wp_clear_scheduled_hook( 'rafiq_reindex_single' );
	}

	// -------------------------------------------------------------------
	// Frontend
	// -------------------------------------------------------------------

	private function widget_enabled() {
		return 'none' !== Rafiq_Settings::get( 'display_mode' ) && ! $this->is_excluded();
	}

	/**
	 * "No AI here": the floating widget can be disabled on specific pages,
	 * by post ID or URL path (one per line or comma-separated).
	 */
	private function is_excluded() {
		$raw = trim( (string) Rafiq_Settings::get( 'exclude_pages' ) );
		if ( '' === $raw ) {
			return false;
		}
		$entries = preg_split( '/[\n,]+/', $raw );
		$post_id = (int) get_queried_object_id();
		$path    = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed, compared, never output.
		}
		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( ctype_digit( $entry ) && (int) $entry === $post_id && $post_id > 0 ) {
				return true;
			}
			if ( '' !== $path && untrailingslashit( $path ) === untrailingslashit( '/' . ltrim( $entry, '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	public function enqueue_frontend() {
		global $post;
		$has_shortcode = $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'rafiq_chatbot' );
		if ( ! $this->widget_enabled() && ! $has_shortcode ) {
			return;
		}

		wp_enqueue_style( 'rafiq-widget', RAFIQ_AI_URL . 'assets/css/rafiq-widget.css', array(), RAFIQ_AI_VERSION );
		wp_enqueue_script( 'rafiq-widget', RAFIQ_AI_URL . 'assets/js/rafiq-widget.js', array(), RAFIQ_AI_VERSION, true );

		$settings = Rafiq_Settings::get_all();

		$quick_replies = array_values(
			array_filter(
				array_map( 'trim', explode( "\n", (string) $settings['quick_replies'] ) )
			)
		);

		/**
		 * Filters the configuration handed to the front-end widget.
		 *
		 * @param array $config Widget configuration.
		 */
		wp_localize_script(
			'rafiq-widget',
			'RafiqConfig',
			apply_filters( 'rafiq_widget_config', array(
				'restUrl'      => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/chat' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'botName'      => $settings['bot_name'],
				'welcome'      => $settings['welcome_message'],
				'mode'         => $settings['display_mode'],
				'position'     => $settings['position'],
				'shape'        => $settings['shape'],
				'theme'        => $settings['theme'],
				'animation'    => $settings['animation'],
				'mobileLayout' => $settings['mobile_layout'],
				'color'        => $settings['primary_color'],
				'avatar'       => $settings['avatar_emoji'],
				'avatarImage'  => $settings['avatar_image'],
				'quickReplies' => array_slice( $quick_replies, 0, 4 ),
				'autoOpen'     => (int) $settings['auto_open_delay'],
				'teaser'       => array(
					'text'  => $settings['teaser_text'],
					'delay' => (int) $settings['teaser_delay'],
				),
				'leadCapture'  => $settings['lead_capture'],
				'i18n'         => array(
					'placeholder' => __( 'Write a message…', 'rawcooked-ai-chatbot' ),
					'send'        => __( 'Send', 'rawcooked-ai-chatbot' ),
					'error'       => __( 'Something went wrong. Please try again.', 'rawcooked-ai-chatbot' ),
					'sources'     => __( 'Read more:', 'rawcooked-ai-chatbot' ),
					'open'        => __( 'Open chat', 'rawcooked-ai-chatbot' ),
					'close'       => __( 'Close chat', 'rawcooked-ai-chatbot' ),
					'leadTitle'   => __( 'Before we start — how can we reach you?', 'rawcooked-ai-chatbot' ),
					'leadName'    => __( 'Your name (optional)', 'rawcooked-ai-chatbot' ),
					'leadEmail'   => __( 'Your email', 'rawcooked-ai-chatbot' ),
					'leadStart'   => __( 'Start chatting', 'rawcooked-ai-chatbot' ),
					'leadSkip'    => __( 'Skip', 'rawcooked-ai-chatbot' ),
				),
			) )
		);

		$css = sprintf(
			':root{--rafiq-primary:%1$s;--rafiq-grad2:%2$s;--rafiq-radius:%3$s;--rafiq-glass-blur:%4$dpx;}',
			$settings['primary_color'],
			$settings['gradient_color'],
			'round' === $settings['shape'] ? '22px' : ( 'soft' === $settings['shape'] ? '10px' : '0px' ),
			(int) $settings['glass_blur']
		);
		wp_add_inline_style( 'rafiq-widget', $css );
	}

	/**
	 * Floating widget root element (bubble or sidebar mode).
	 */
	public function render_widget_mount() {
		if ( ! $this->widget_enabled() || is_admin() ) {
			return;
		}
		echo '<div id="rafiq-root" aria-live="polite"></div>';
	}

	/**
	 * [rafiq_chatbot] — inline, full-width chat (dedicated page mode).
	 */
	public function shortcode() {
		return '<div class="rafiq-inline" id="rafiq-inline"></div>';
	}
}
