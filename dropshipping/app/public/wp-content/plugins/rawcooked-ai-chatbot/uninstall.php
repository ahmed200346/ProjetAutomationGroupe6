<?php
/**
 * Uninstall: remove the plugin tables, settings and transients.
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$rafiq_chunks_table = esc_sql( $wpdb->prefix . 'rafiq_chunks' );
$wpdb->query( "DROP TABLE IF EXISTS `{$rafiq_chunks_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- uninstall cleanup of the plugin's own table.

$rafiq_conversations_table = esc_sql( $wpdb->prefix . 'rafiq_conversations' );
$wpdb->query( "DROP TABLE IF EXISTS `{$rafiq_conversations_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- uninstall cleanup of the plugin's own table.

delete_option( 'rafiq_ai_settings' );
delete_option( 'rafiq_db_version' );

// Rate-limit transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '\\_transient\\_rafiq\\_rl\\_%'
	OR option_name LIKE '\\_transient\\_timeout\\_rafiq\\_rl\\_%'"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall cleanup.
