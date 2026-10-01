<?php
/**
 * Plugin Name: WPGetAPI
 * Plugin URI:  https://wordpress.org/plugins/wpgetapi/
 * Description: Connect to external API's and display the API data.
 * Author: WPGetAPI
 * Author URI:  https://wpgetapi.com/
 * Version: 2.25.5
 * Text Domain: wpgetapi
 * License: GPL2 or later
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Class.
 *
 * @since 1.0.0
 */
final class WP_Get_API {

	/**
	 * @var The one true instance
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	public $version = '2.25.5';

	/**
	 * Main Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wpgetapi' ), esc_html( $this->version ) );
	}

	/**
	 * Disable unserializing of the class.
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wpgetapi' ), esc_html( $this->version ) );
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->define_constants();
		$this->hooks();
		$this->includes();

		do_action( 'wpgetapi_loaded' );
	}

	/**
	 * Define Constants.
	 * @since  1.0.0
	 */
	private function define_constants() {
		$this->define( 'WPGETAPIDIR', plugin_dir_path( __FILE__ ) );
		$this->define( 'WPGETAPIURL', plugin_dir_url( __FILE__ ) );
		$this->define( 'WPGETAPIBASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'WPGETAPIVERSION', $this->version );
		$this->define( 'WPGETAPILICENSEPAGE', 'wpgetapi_plugin_licenses' );
		$this->define( 'WPGETAPISTOREURL', 'https://wpgetapi.com' );
	}

	/**
	 * Define hooks.
	 * @since  1.4.2
	 */
	private function hooks() {
		$plugin_file = WPGETAPIBASENAME;
		add_filter( "plugin_action_links_{$plugin_file}", array( $this, 'plugin_action_links' ), 10, 4 );
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 4 );

		// Load translations at the init time
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Define constant if not already set.
	 * @since  1.0.0
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}


	/**
	 * Include required files.
	 * @since  1.0.0
	 */
	public function includes() {

		if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wpgetapi_' ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are not processing form submission.
			require_once WPGETAPIDIR . '/lib/cmb2/init.php';
		}

		require_once WPGETAPIDIR . '/includes/block-editor/block-editor.php';

		include_once WPGETAPIDIR . 'includes/class-wpgetapi-encryption.php';
		include_once WPGETAPIDIR . 'includes/class-wpgetapi-admin-options.php';

		include_once WPGETAPIDIR . 'includes/functions.php';
		include_once WPGETAPIDIR . 'includes/class-wpgetapi-api-enqueues.php';
		include_once WPGETAPIDIR . 'includes/class-wpgetapi-api.php';

		include_once WPGETAPIDIR . 'frontend/functions.php';
		include_once WPGETAPIDIR . 'includes/class-wpgetapi-license-handler.php';

		include_once WPGETAPIDIR . 'includes/class-wpgetapi-notices.php';
	}

	/**
	 * Filters the array of row meta for each plugin in the Plugins list table.
	 *
	 * @param array<int,string> $plugin_meta An array of the plugin row's meta data.
	 * @param string            $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return array<int,string> An array of the plugin row's meta data.
	 */
	public function filter_plugin_row_meta( array $plugin_meta, $plugin_file ) {
		if ( 'wpgetapi/wpgetapi.php' !== $plugin_file ) {
			return $plugin_meta;
		}

		$plugin_meta[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			'https://wpgetapi.com/docs/?utm_campaign=Docs&utm_medium=plugin&utm_source=external',
			esc_html( 'Docs', 'wpgetapi' )
		);

		return $plugin_meta;
	}

	/**
	 * Loading translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wpgetapi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Adds items to the plugin's action links on the Plugins listing screen.
	 *
	 * @param array<string,string> $actions     Array of action links.
	 * @param string               $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param mixed[]              $plugin_data An array of plugin data.
	 * @param string               $context     The plugin context.
	 * @return array<string,string> Array of action links.
	 */
	public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
		$new = array(
			'setup' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wpgetapi_setup' ) ),
				esc_html__( 'API Setup', 'wpgetapi' )
			),
		);

		return array_merge( $new, $actions );
	}

	/**
	 * Whether the current user can manage the WPGetAPI plugin admin
	 *
	 * @return Boolean True if the current user can manage the WPGetAPI plugin admin otheriwise false
	 */
	public function current_user_can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user can manage the WPGetAPI plugin admin
	 *
	 * @return bool
	 */
	public function is_wpgetapi_admin_page() {
		$is_wpgetapi_admin_page = false;
		if ( $this->current_user_can_manage() && ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wpgetapi_' ) !== false ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are not processing form submission.
			$is_wpgetapi_admin_page = true;
		}

		return $is_wpgetapi_admin_page;
	}

	/**
	 * Check whether any premium addon plugin is active or not.
	 *
	 * @return Boolean True if any premium addon plugin is active otherwise false.
	 */
	public function is_any_premium_addon_plugin_active() {
		return is_plugin_active( 'wpgetapi-extras/wpgetapi-extras.php' ) || is_plugin_active( 'wpgetapi-api-to-posts/wpgetapi-api-to-posts.php' ) || is_plugin_active( 'wpgetapi-oauth/wpgetapi-oauth.php' );
	}
}

/**
 * Run the plugin.
 */
function wp_get_api() {
	return WP_Get_API::instance();
}
wp_get_api();
