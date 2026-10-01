<?php
/**
 * Plugin Name: Dropshipping Automation
 * Description: Tableau de bord d'administration pour le pipeline de dropshipping.
 * Version: 0.7.0
 * Text Domain: dsa
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'DSA_PLUGIN_FILE', __FILE__ );
define( 'DSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DSA_VERSION', '0.7.0' );

require_once DSA_PLUGIN_DIR . 'src/autoload.php';

register_deactivation_hook( __FILE__, array( DSA\Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'dsa', false, dirname( plugin_basename( DSA_PLUGIN_FILE ) ) . '/languages' );
		DSA\Plugin::instance()->boot();
	}
);