<?php

namespace DSA\Integrations;

defined( 'ABSPATH' ) || exit;

final class N8nResultsStore {
	const DB_VERSION = '1';
	const DB_VERSION_OPTION = 'dsa_n8n_results_db_version';

	public function maybe_install() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . 'dsa_workflow_results';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id varchar(191) NOT NULL DEFAULT '',
			product_rank int(10) unsigned NOT NULL,
			product_title varchar(255) NOT NULL,
			source_platform varchar(32) NOT NULL DEFAULT '',
			search_query text NOT NULL,
			payload_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public function insert( $run_id, array $product_result ) {
		global $wpdb;

		$payload = wp_json_encode( $product_result );
		if ( ! is_string( $payload ) || strlen( $payload ) > 1000000 ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'dsa_workflow_results',
			array(
				'run_id' => sanitize_text_field( (string) $run_id ),
				'product_rank' => absint( $product_result['rank'] ),
				'product_title' => sanitize_text_field( $product_result['product']['title'] ),
				'source_platform' => sanitize_key( $product_result['source']['platform'] ?? '' ),
				'search_query' => sanitize_text_field( $product_result['source']['search_query'] ?? '' ),
				'payload_json' => $payload,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}
}