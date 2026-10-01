<?php
/**
 * Plugin Name:       RawCooked AI Chatbot
 * Description:       AI chatbot for WordPress & WooCommerce with RAG on your content and products. Bring your own API key: Google Gemini (free tier), Anthropic Claude, OpenAI, or a local Ollama (Llama 3).
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            rawcooked
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rawcooked-ai-chatbot
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAFIQ_AI_VERSION', '1.5.0' );
define( 'RAFIQ_AI_FILE', __FILE__ );
define( 'RAFIQ_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAFIQ_AI_URL', plugin_dir_url( __FILE__ ) );

require_once RAFIQ_AI_DIR . 'includes/class-rafiq-admin.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-freemius.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-settings.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-vector-store.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-conversations.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-indexer.php';
require_once RAFIQ_AI_DIR . 'includes/providers/class-rafiq-provider.php';
require_once RAFIQ_AI_DIR . 'includes/providers/class-rafiq-provider-openai.php';
require_once RAFIQ_AI_DIR . 'includes/providers/class-rafiq-provider-anthropic.php';
require_once RAFIQ_AI_DIR . 'includes/providers/class-rafiq-provider-gemini.php';
require_once RAFIQ_AI_DIR . 'includes/providers/class-rafiq-provider-ollama.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-tools.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-chat.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-rest.php';
require_once RAFIQ_AI_DIR . 'includes/class-rafiq-plugin.php';

// Boot the SDK (no-op when it is not bundled) and let add-ons hook in.
rafiq_fs();
do_action( 'rafiq_fs_loaded' );

register_activation_hook( __FILE__, array( 'Rafiq_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Rafiq_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Rafiq_Plugin', 'instance' ) );
