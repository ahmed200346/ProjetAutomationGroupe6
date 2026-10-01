<?php
/**
 * Freemius integration for the free plugin.
 *
 * The free plugin itself has NO paid plans and NO gated code: Freemius is
 * used here only for opt-in analytics and to expose the "RawCooked AI Pro"
 * add-on inside the WordPress admin. Premium features live entirely in the
 * separate add-on plugin, which is what keeps this plugin compliant with
 * WordPress.org guideline 5 (no trialware / no artificial limitations).
 *
 * The SDK is loaded only if present, so the plugin keeps working normally
 * when it is not bundled (e.g. during development).
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Freemius product identifiers.
 */
define( 'RAFIQ_FS_ID', '37606' );
define( 'RAFIQ_FS_PUBLIC_KEY', 'pk_9cf26d2f69619d202242734f91c91' );
define( 'RAFIQ_FS_ADDON_ID', '37607' );
define( 'RAFIQ_FS_ADDON_PUBLIC_KEY', 'pk_5440ee674c0834a862c976dfaa71d' );

if ( ! function_exists( 'rafiq_fs' ) ) {

	/**
	 * Freemius SDK instance, or null when the SDK is not bundled.
	 *
	 * @return Freemius|null
	 */
	function rafiq_fs() {
		global $rafiq_fs;

		if ( isset( $rafiq_fs ) ) {
			return $rafiq_fs;
		}

		$sdk = RAFIQ_AI_DIR . 'freemius/start.php';
		if ( ! file_exists( $sdk ) ) {
			$rafiq_fs = null;
			return null;
		}

		require_once $sdk;

		$rafiq_fs = fs_dynamic_init(
			array(
				'id'             => RAFIQ_FS_ID,
				'slug'           => 'rawcooked-ai-chatbot',
				'type'           => 'plugin',
				'public_key'     => RAFIQ_FS_PUBLIC_KEY,
				'is_premium'     => false,
				'has_addons'     => true,
				'has_paid_plans' => false,
				'menu'           => array(
					'slug'    => Rafiq_Admin::PAGE_SLUG,
					'account' => false,
					'contact' => false,
					'support' => false,
				),
			)
		);

		return $rafiq_fs;
	}
}

/**
 * Whether the Pro add-on holds a valid license on this site.
 *
 * Add-ons call this through the `rafiq_pro_is_active` filter rather than
 * touching the SDK directly.
 *
 * @return bool
 */
function rafiq_pro_is_active() {
	return (bool) apply_filters( 'rafiq_pro_is_active', false );
}
