<?php
/**
 * Admin: settings screen (tabs), save handling, admin assets.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Admin {

	const PAGE_SLUG = 'rawcooked-ai-chatbot';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'RawCooked AI Chatbot', 'rawcooked-ai-chatbot' ),
			__( 'RawCooked AI', 'rawcooked-ai-chatbot' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public function enqueue( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'rafiq-admin', RAFIQ_AI_URL . 'assets/css/rafiq-admin.css', array(), RAFIQ_AI_VERSION );
		wp_enqueue_script( 'rafiq-admin', RAFIQ_AI_URL . 'assets/js/rafiq-admin.js', array(), RAFIQ_AI_VERSION, true );
		wp_localize_script(
			'rafiq-admin',
			'RafiqAdmin',
			array(
				'indexUrl'  => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/index' ) ),
				'statusUrl' => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/index-status' ) ),
				'testUrl'   => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/test-provider' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'convUrl'  => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/conversations' ) ),
				'leadsUrl' => esc_url_raw( rest_url( Rafiq_Rest::NAMESPACE_URI . '/leads' ) ),
				'i18n'      => array(
					'indexing'    => __( 'Indexing…', 'rawcooked-ai-chatbot' ),
					'done'        => __( 'Indexing complete.', 'rawcooked-ai-chatbot' ),
					'failed'      => __( 'Indexing failed:', 'rawcooked-ai-chatbot' ),
					'testing'     => __( 'Testing…', 'rawcooked-ai-chatbot' ),
					'test_ok'     => __( 'Connection OK — model replied.', 'rawcooked-ai-chatbot' ),
					'confirm_del' => __( 'Delete ALL conversations? This cannot be undone.', 'rawcooked-ai-chatbot' ),
					'no_convs'    => __( 'No conversations yet.', 'rawcooked-ai-chatbot' ),
					'anonymous'   => __( 'Visitor', 'rawcooked-ai-chatbot' ),
					'view'        => __( 'View', 'rawcooked-ai-chatbot' ),
				),
			)
		);
	}

	public function maybe_save() {
		if ( ! isset( $_POST['rafiq_save_settings'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'rawcooked-ai-chatbot' ) );
		}
		check_admin_referer( 'rafiq_save_settings', 'rafiq_nonce' );

		$input = isset( $_POST['rafiq'] ) && is_array( $_POST['rafiq'] ) ? wp_unslash( $_POST['rafiq'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in Rafiq_Settings::sanitize().

		// Unchecked checkboxes are absent from POST: normalize them to 0.
		foreach ( array( 'index_products', 'index_pages', 'index_posts', 'tool_product_search', 'tool_orders', 'show_sources', 'log_conversations' ) as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				$input[ $key ] = 0;
			}
		}

		Rafiq_Settings::update( $input );
		add_settings_error( 'rafiq', 'saved', __( 'Settings saved.', 'rawcooked-ai-chatbot' ), 'success' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = Rafiq_Settings::get_all();
		settings_errors( 'rafiq' );
		?>
		<div class="wrap rafiq-admin">
			<h1><span class="rafiq-logo"><?php echo esc_html( $s['avatar_emoji'] ); ?></span> <?php esc_html_e( 'RawCooked AI Chatbot', 'rawcooked-ai-chatbot' ); ?></h1>

			<nav class="rafiq-tabs">
				<button type="button" class="rafiq-tab is-active" data-tab="provider"><?php esc_html_e( 'AI Provider', 'rawcooked-ai-chatbot' ); ?></button>
				<button type="button" class="rafiq-tab" data-tab="knowledge"><?php esc_html_e( 'Knowledge (RAG)', 'rawcooked-ai-chatbot' ); ?></button>
				<button type="button" class="rafiq-tab" data-tab="appearance"><?php esc_html_e( 'Appearance', 'rawcooked-ai-chatbot' ); ?></button>
				<button type="button" class="rafiq-tab" data-tab="behavior"><?php esc_html_e( 'Behavior & Privacy', 'rawcooked-ai-chatbot' ); ?></button>
				<button type="button" class="rafiq-tab" data-tab="conversations"><?php esc_html_e( 'Conversations', 'rawcooked-ai-chatbot' ); ?></button>
				<?php
				/**
				 * Fires inside the settings tab bar so add-ons can register tabs.
				 * Echo: <button type="button" class="rafiq-tab" data-tab="your-slug">Label</button>
				 */
				do_action( 'rafiq_admin_tabs', $s );
				?>
			</nav>

			<form method="post" action="">
				<?php wp_nonce_field( 'rafiq_save_settings', 'rafiq_nonce' ); ?>
				<input type="hidden" name="rafiq_save_settings" value="1" />

				<!-- ============ Provider ============ -->
				<section class="rafiq-panel is-active" data-panel="provider">
					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Chat model', 'rawcooked-ai-chatbot' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Bring your own API key. Google Gemini has a free tier — perfect to start. Ollama runs models like Llama 3 locally, for free, with no key at all.', 'rawcooked-ai-chatbot' ); ?></p>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="rafiq-provider"><?php esc_html_e( 'Provider', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select id="rafiq-provider" name="rafiq[provider]">
										<option value="gemini" <?php selected( $s['provider'], 'gemini' ); ?>><?php esc_html_e( 'Google Gemini (free tier available)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="anthropic" <?php selected( $s['provider'], 'anthropic' ); ?>>Anthropic Claude</option>
										<option value="openai" <?php selected( $s['provider'], 'openai' ); ?>>OpenAI</option>
										<option value="ollama" <?php selected( $s['provider'], 'ollama' ); ?>><?php esc_html_e( 'Ollama (local, e.g. Llama 3)', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
									<button type="button" class="button" id="rafiq-test-provider"><?php esc_html_e( 'Test connection', 'rawcooked-ai-chatbot' ); ?></button>
									<span id="rafiq-test-result"></span>
									<p class="description"><?php esc_html_e( 'Save your settings before testing.', 'rawcooked-ai-chatbot' ); ?></p>
								</td>
							</tr>
						</table>

						<div class="rafiq-provider-box" data-provider="gemini">
							<h3>Google Gemini</h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label><?php esc_html_e( 'API key', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="password" class="regular-text" name="rafiq[gemini_api_key]" value="<?php echo esc_attr( $s['gemini_api_key'] ); ?>" autocomplete="off" />
									<p class="description"><?php esc_html_e( 'Free key at aistudio.google.com', 'rawcooked-ai-chatbot' ); ?></p></td>
								</tr>
								<tr>
									<th scope="row"><label><?php esc_html_e( 'Model', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="text" class="regular-text" name="rafiq[gemini_model]" value="<?php echo esc_attr( $s['gemini_model'] ); ?>" /></td>
								</tr>
							</table>
						</div>

						<div class="rafiq-provider-box" data-provider="anthropic">
							<h3>Anthropic Claude</h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label><?php esc_html_e( 'API key', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="password" class="regular-text" name="rafiq[anthropic_api_key]" value="<?php echo esc_attr( $s['anthropic_api_key'] ); ?>" autocomplete="off" /></td>
								</tr>
								<tr>
									<th scope="row"><label><?php esc_html_e( 'Model', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="text" class="regular-text" name="rafiq[anthropic_model]" value="<?php echo esc_attr( $s['anthropic_model'] ); ?>" /></td>
								</tr>
							</table>
						</div>

						<div class="rafiq-provider-box" data-provider="openai">
							<h3>OpenAI</h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label><?php esc_html_e( 'API key', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="password" class="regular-text" name="rafiq[openai_api_key]" value="<?php echo esc_attr( $s['openai_api_key'] ); ?>" autocomplete="off" /></td>
								</tr>
								<tr>
									<th scope="row"><label><?php esc_html_e( 'Model', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="text" class="regular-text" name="rafiq[openai_model]" value="<?php echo esc_attr( $s['openai_model'] ); ?>" /></td>
								</tr>
							</table>
						</div>

						<div class="rafiq-provider-box" data-provider="ollama">
							<h3>Ollama</h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label><?php esc_html_e( 'Server URL', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="url" class="regular-text" name="rafiq[ollama_url]" value="<?php echo esc_attr( $s['ollama_url'] ); ?>" placeholder="http://localhost:11434" />
									<p class="description"><?php esc_html_e( 'The Ollama server must be reachable from this WordPress server.', 'rawcooked-ai-chatbot' ); ?></p></td>
								</tr>
								<tr>
									<th scope="row"><label><?php esc_html_e( 'Model', 'rawcooked-ai-chatbot' ); ?></label></th>
									<td><input type="text" class="regular-text" name="rafiq[ollama_model]" value="<?php echo esc_attr( $s['ollama_model'] ); ?>" placeholder="llama3.1" /></td>
								</tr>
							</table>
						</div>
					</div>

					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Embeddings (for semantic search)', 'rawcooked-ai-chatbot' ); ?></h2>
						<p class="description"><?php esc_html_e( '"Auto" picks the best available option. Claude has no embeddings API, so with Claude as chat model the embeddings fall back to Gemini, OpenAI or Ollama. With no embedding provider at all, the bot still works using keyword search.', 'rawcooked-ai-chatbot' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Embedding provider', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[embedding_provider]">
										<option value="auto" <?php selected( $s['embedding_provider'], 'auto' ); ?>><?php esc_html_e( 'Auto (recommended)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="gemini" <?php selected( $s['embedding_provider'], 'gemini' ); ?>>Gemini</option>
										<option value="openai" <?php selected( $s['embedding_provider'], 'openai' ); ?>>OpenAI</option>
										<option value="ollama" <?php selected( $s['embedding_provider'], 'ollama' ); ?>>Ollama</option>
										<option value="none" <?php selected( $s['embedding_provider'], 'none' ); ?>><?php esc_html_e( 'None (keyword search only)', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</section>

				<!-- ============ Knowledge ============ -->
				<section class="rafiq-panel" data-panel="knowledge">
					<div class="rafiq-card">
						<h2><?php esc_html_e( 'What should the bot know?', 'rawcooked-ai-chatbot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Sources', 'rawcooked-ai-chatbot' ); ?></th>
								<td>
									<label><input type="checkbox" name="rafiq[index_products]" value="1" <?php checked( $s['index_products'] ); ?> /> <?php esc_html_e( 'WooCommerce products (name, price, stock, description)', 'rawcooked-ai-chatbot' ); ?></label><br/>
									<label><input type="checkbox" name="rafiq[index_pages]" value="1" <?php checked( $s['index_pages'] ); ?> /> <?php esc_html_e( 'Pages', 'rawcooked-ai-chatbot' ); ?></label><br/>
									<label><input type="checkbox" name="rafiq[index_posts]" value="1" <?php checked( $s['index_posts'] ); ?> /> <?php esc_html_e( 'Blog posts', 'rawcooked-ai-chatbot' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Index', 'rawcooked-ai-chatbot' ); ?></h2>
						<p><span id="rafiq-index-stats"></span></p>
						<div class="rafiq-progress" id="rafiq-progress" hidden><div class="rafiq-progress-bar" id="rafiq-progress-bar"></div></div>
						<p id="rafiq-index-message"></p>
						<button type="button" class="button button-primary" id="rafiq-index-run"><?php esc_html_e( 'Index now', 'rawcooked-ai-chatbot' ); ?></button>
						<button type="button" class="button" id="rafiq-index-rebuild"><?php esc_html_e( 'Rebuild from scratch', 'rawcooked-ai-chatbot' ); ?></button>
						<p class="description"><?php esc_html_e( 'Content is also re-indexed automatically when you edit it, and every night.', 'rawcooked-ai-chatbot' ); ?></p>
					</div>
				</section>

				<!-- ============ Appearance ============ -->
				<section class="rafiq-panel" data-panel="appearance">
					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Widget', 'rawcooked-ai-chatbot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Display mode', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[display_mode]">
										<option value="bubble" <?php selected( $s['display_mode'], 'bubble' ); ?>><?php esc_html_e( 'Floating bubble (corner)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="sidebar" <?php selected( $s['display_mode'], 'sidebar' ); ?>><?php esc_html_e( 'Side panel (edge tab)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="none" <?php selected( $s['display_mode'], 'none' ); ?>><?php esc_html_e( 'Nothing floating — shortcode only', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
									<p class="description"><?php echo wp_kses_post( __( 'For a dedicated chat page, put the shortcode <code>[rafiq_chatbot]</code> on any page.', 'rawcooked-ai-chatbot' ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Position', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[position]">
										<option value="right" <?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="left" <?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Theme', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<div class="rafiq-themes">
										<?php
										$themes = array(
											'classic'  => __( 'Classic', 'rawcooked-ai-chatbot' ),
											'midnight' => __( 'Midnight (dark)', 'rawcooked-ai-chatbot' ),
											'glass'    => __( 'Glass', 'rawcooked-ai-chatbot' ),
											'gradient' => __( 'Gradient', 'rawcooked-ai-chatbot' ),
											'minimal'  => __( 'Minimal', 'rawcooked-ai-chatbot' ),
										);
										foreach ( $themes as $theme_id => $theme_label ) :
											?>
											<label class="rafiq-theme-card rafiq-theme-card--<?php echo esc_attr( $theme_id ); ?>">
												<input type="radio" name="rafiq[theme]" value="<?php echo esc_attr( $theme_id ); ?>" <?php checked( $s['theme'], $theme_id ); ?> />
												<span class="rafiq-theme-card__preview" aria-hidden="true">
													<span class="rafiq-theme-card__bar"></span>
													<span class="rafiq-theme-card__msg rafiq-theme-card__msg--a"></span>
													<span class="rafiq-theme-card__msg rafiq-theme-card__msg--u"></span>
												</span>
												<span class="rafiq-theme-card__name"><?php echo esc_html( $theme_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Shape', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[shape]">
										<option value="round" <?php selected( $s['shape'], 'round' ); ?>><?php esc_html_e( 'Round', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="soft" <?php selected( $s['shape'], 'soft' ); ?>><?php esc_html_e( 'Soft corners', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="square" <?php selected( $s['shape'], 'square' ); ?>><?php esc_html_e( 'Square', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Open animation', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[animation]">
										<option value="pop" <?php selected( $s['animation'], 'pop' ); ?>><?php esc_html_e( 'Pop (scale from corner)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="slide" <?php selected( $s['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide up', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="fade" <?php selected( $s['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="bounce" <?php selected( $s['animation'], 'bounce' ); ?>><?php esc_html_e( 'Bounce', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Glass blur intensity', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<input type="range" min="0" max="40" name="rafiq[glass_blur]" value="<?php echo esc_attr( $s['glass_blur'] ); ?>" oninput="this.nextElementSibling.textContent=this.value+'px'" />
									<span><?php echo esc_html( $s['glass_blur'] . 'px' ); ?></span>
									<p class="description"><?php esc_html_e( 'Only used by the Glass theme.', 'rawcooked-ai-chatbot' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Mobile layout', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[mobile_layout]">
										<option value="full" <?php selected( $s['mobile_layout'], 'full' ); ?>><?php esc_html_e( 'Full screen', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="half" <?php selected( $s['mobile_layout'], 'half' ); ?>><?php esc_html_e( 'Bottom half (site stays visible on top)', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Main color', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<input type="color" name="rafiq[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" />
									<label style="margin-left:12px;"><?php esc_html_e( 'Gradient second color:', 'rawcooked-ai-chatbot' ); ?>
										<input type="color" name="rafiq[gradient_color]" value="<?php echo esc_attr( $s['gradient_color'] ); ?>" /></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Suggested questions', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><textarea class="large-text" rows="3" name="rafiq[quick_replies]" placeholder="<?php esc_attr_e( "What do you sell?\nWhat are your opening hours?", 'rawcooked-ai-chatbot' ); ?>"><?php echo esc_textarea( $s['quick_replies'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One per line (max 4). Shown as clickable chips when the chat opens.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Auto-open after (seconds)', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="number" min="0" max="120" class="small-text" name="rafiq[auto_open_delay]" value="<?php echo esc_attr( $s['auto_open_delay'] ); ?>" />
								<p class="description"><?php esc_html_e( '0 = disabled. Opens the chat proactively once per visit.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Teaser bubble', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<input type="text" class="regular-text" name="rafiq[teaser_text]" value="<?php echo esc_attr( $s['teaser_text'] ); ?>" placeholder="<?php esc_attr_e( 'A question? I answer in seconds 👋', 'rawcooked-ai-chatbot' ); ?>" />
									<label style="margin-left:8px"><?php esc_html_e( 'after', 'rawcooked-ai-chatbot' ); ?>
										<input type="number" min="0" max="300" class="small-text" name="rafiq[teaser_delay]" value="<?php echo esc_attr( $s['teaser_delay'] ); ?>" /> s</label>
									<p class="description"><?php esc_html_e( 'A small speech bubble next to the launcher — less intrusive than auto-open. Dismissible, once per visit. 0 s = disabled.', 'rawcooked-ai-chatbot' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Bot name', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="text" class="regular-text" name="rafiq[bot_name]" value="<?php echo esc_attr( $s['bot_name'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Avatar emoji', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="text" class="small-text" name="rafiq[avatar_emoji]" value="<?php echo esc_attr( $s['avatar_emoji'] ); ?>" maxlength="4" /></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Avatar image (optional)', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="url" class="regular-text" name="rafiq[avatar_image]" value="<?php echo esc_attr( $s['avatar_image'] ); ?>" placeholder="https://…/mascotte.png" />
								<p class="description"><?php esc_html_e( 'Overrides the emoji. Upload an image in Media Library and paste its URL (square, ≥76px). It hops when a reply arrives.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Welcome message', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="rafiq[welcome_message]"><?php echo esc_textarea( $s['welcome_message'] ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Sources', 'rawcooked-ai-chatbot' ); ?></th>
								<td><label><input type="checkbox" name="rafiq[show_sources]" value="1" <?php checked( $s['show_sources'] ); ?> /> <?php esc_html_e( 'Show "Read more" links under answers', 'rawcooked-ai-chatbot' ); ?></label></td>
							</tr>
						</table>
					</div>
				</section>

				<!-- ============ Behavior ============ -->
				<section class="rafiq-panel" data-panel="behavior">
					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Personality & rules', 'rawcooked-ai-chatbot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Tone', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[tone]">
										<option value="friendly" <?php selected( $s['tone'], 'friendly' ); ?>><?php esc_html_e( 'Friendly & warm', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="professional" <?php selected( $s['tone'], 'professional' ); ?>><?php esc_html_e( 'Professional & formal', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="playful" <?php selected( $s['tone'], 'playful' ); ?>><?php esc_html_e( 'Playful & upbeat', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Lead capture', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td>
									<select name="rafiq[lead_capture]">
										<option value="off" <?php selected( $s['lead_capture'], 'off' ); ?>><?php esc_html_e( 'Off', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="optional" <?php selected( $s['lead_capture'], 'optional' ); ?>><?php esc_html_e( 'Ask for email (skippable)', 'rawcooked-ai-chatbot' ); ?></option>
										<option value="required" <?php selected( $s['lead_capture'], 'required' ); ?>><?php esc_html_e( 'Require email before chatting', 'rawcooked-ai-chatbot' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Collected emails appear in the Conversations tab.', 'rawcooked-ai-chatbot' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Conversation log', 'rawcooked-ai-chatbot' ); ?></th>
								<td>
									<label><input type="checkbox" name="rafiq[log_conversations]" value="1" <?php checked( $s['log_conversations'] ); ?> /> <?php esc_html_e( 'Store transcripts for the Conversations tab', 'rawcooked-ai-chatbot' ); ?></label><br/>
									<label><?php esc_html_e( 'Auto-delete after', 'rawcooked-ai-chatbot' ); ?>
										<input type="number" min="1" max="365" class="small-text" name="rafiq[retention_days]" value="<?php echo esc_attr( $s['retention_days'] ); ?>" />
										<?php esc_html_e( 'days (GDPR retention)', 'rawcooked-ai-chatbot' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Extra instructions', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><textarea class="large-text" rows="5" name="rafiq[system_prompt]" placeholder="<?php esc_attr_e( 'e.g. We deliver within 5km. Opening hours: 11:00–23:00. Always suggest the daily special.', 'rawcooked-ai-chatbot' ); ?>"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Added to the bot\'s instructions. Privacy rules are built in and cannot be overridden.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WooCommerce tools', 'rawcooked-ai-chatbot' ); ?></th>
								<td>
									<label><input type="checkbox" name="rafiq[tool_product_search]" value="1" <?php checked( $s['tool_product_search'] ); ?> /> <?php esc_html_e( 'Live product search (price & stock in real time)', 'rawcooked-ai-chatbot' ); ?></label><br/>
									<label><input type="checkbox" name="rafiq[tool_orders]" value="1" <?php checked( $s['tool_orders'] ); ?> /> <?php esc_html_e( 'Order status — only for the logged-in customer\'s own orders', 'rawcooked-ai-chatbot' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Temperature', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="number" step="0.1" min="0" max="2" class="small-text" name="rafiq[temperature]" value="<?php echo esc_attr( $s['temperature'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Max response tokens', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="number" min="64" max="8192" class="small-text" name="rafiq[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Disable on these pages', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><textarea class="large-text" rows="2" name="rafiq[exclude_pages]" placeholder="<?php esc_attr_e( "/checkout\n/legal\n42", 'rawcooked-ai-chatbot' ); ?>"><?php echo esc_textarea( $s['exclude_pages'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One per line: a URL path (/checkout) or a page ID. The floating widget will not load there.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Messages per visitor per minute', 'rawcooked-ai-chatbot' ); ?></label></th>
								<td><input type="number" min="1" max="120" class="small-text" name="rafiq[rate_limit]" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Protects your API bill from abuse.', 'rawcooked-ai-chatbot' ); ?></p></td>
							</tr>
						</table>
					</div>
				</section>

				<!-- ============ Conversations ============ -->
				<section class="rafiq-panel" data-panel="conversations">
					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Last 7 days', 'rawcooked-ai-chatbot' ); ?></h2>
						<div class="rafiq-stats" id="rafiq-stats">
							<div class="rafiq-stat"><span class="rafiq-stat__num" data-stat="week_conversations">–</span><span class="rafiq-stat__label"><?php esc_html_e( 'Conversations', 'rawcooked-ai-chatbot' ); ?></span></div>
							<div class="rafiq-stat"><span class="rafiq-stat__num" data-stat="week_messages">–</span><span class="rafiq-stat__label"><?php esc_html_e( 'Messages', 'rawcooked-ai-chatbot' ); ?></span></div>
							<div class="rafiq-stat"><span class="rafiq-stat__num" data-stat="week_no_context">–</span><span class="rafiq-stat__label"><?php esc_html_e( '% unanswered by site content', 'rawcooked-ai-chatbot' ); ?></span></div>
							<div class="rafiq-stat"><span class="rafiq-stat__num" data-stat="total_leads">–</span><span class="rafiq-stat__label"><?php esc_html_e( 'Leads collected (total)', 'rawcooked-ai-chatbot' ); ?></span></div>
						</div>
						<p class="description"><?php esc_html_e( 'A high "unanswered" rate means visitors ask things your site does not cover — content ideas, for free.', 'rawcooked-ai-chatbot' ); ?></p>
					</div>

					<div class="rafiq-card">
						<h2><?php esc_html_e( 'Recent conversations', 'rawcooked-ai-chatbot' ); ?></h2>
						<table class="widefat striped" id="rafiq-conv-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Started', 'rawcooked-ai-chatbot' ); ?></th>
									<th><?php esc_html_e( 'Visitor', 'rawcooked-ai-chatbot' ); ?></th>
									<th><?php esc_html_e( 'First message', 'rawcooked-ai-chatbot' ); ?></th>
									<th><?php esc_html_e( 'Msgs', 'rawcooked-ai-chatbot' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="rafiq-conv-body"></tbody>
						</table>
						<div id="rafiq-transcript" class="rafiq-transcript" hidden></div>
						<p style="margin-top:12px;">
							<button type="button" class="button" id="rafiq-export-leads"><?php esc_html_e( 'Export leads (CSV)', 'rawcooked-ai-chatbot' ); ?></button>
							<button type="button" class="button rafiq-danger" id="rafiq-delete-convs"><?php esc_html_e( 'Delete all conversations', 'rawcooked-ai-chatbot' ); ?></button>
						</p>
					</div>
				</section>

				<?php
				/**
				 * Fires after the built-in panels so add-ons can render their own.
				 * Echo: <section class="rafiq-panel" data-panel="your-slug">…</section>
				 */
				do_action( 'rafiq_admin_panels', $s );
				?>

				<p class="submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'rawcooked-ai-chatbot' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
