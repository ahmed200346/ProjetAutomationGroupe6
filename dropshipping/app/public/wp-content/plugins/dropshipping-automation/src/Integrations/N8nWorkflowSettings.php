<?php

namespace DSA\Integrations;

defined( 'ABSPATH' ) || exit;

final class N8nWorkflowSettings {
	const OPTION = 'dsa_n8n_workflow_settings';
	const MARKETPLACES = array( 'aliexpress', 'amazon' );

	public static function defaults() {
		return array(
			'query' => '',
			'marketplace' => 'aliexpress',
			'limit' => 10,
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? $saved : array() );
	}

	public static function save( $input ) {
		$settings = self::sanitize( $input );
		update_option( self::OPTION, $settings, false );
		return $settings;
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$query = isset( $input['query'] ) && is_string( $input['query'] )
			? sanitize_text_field( $input['query'] )
			: '';
		$query = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 120 ) : substr( $query, 0, 120 );
		$marketplace = isset( $input['marketplace'] ) && is_string( $input['marketplace'] )
			? sanitize_key( $input['marketplace'] )
			: '';
		$limit = isset( $input['limit'] ) && is_scalar( $input['limit'] ) ? absint( $input['limit'] ) : 10;

		return array(
			'query' => $query,
			'marketplace' => in_array( $marketplace, self::MARKETPLACES, true ) ? $marketplace : 'aliexpress',
			'limit' => max( 1, min( 20, $limit ) ),
		);
	}
}