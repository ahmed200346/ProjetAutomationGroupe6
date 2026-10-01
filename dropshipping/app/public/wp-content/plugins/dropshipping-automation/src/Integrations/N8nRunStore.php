<?php

namespace DSA\Integrations;

defined( 'ABSPATH' ) || exit;

final class N8nRunStore {
	const OPTION = 'dsa_n8n_workflow_runs';
	const MAX_RUNS = 100;

	public function create( $run_id, array $settings, $source ) {
		$runs = $this->runs();
		$run = array(
			'id' => sanitize_text_field( (string) $run_id ),
			'workflow' => 'products',
			'label' => __( 'Recherche produits', 'dsa' ),
			'source' => 'scheduled' === $source ? 'scheduled' : 'manual',
			'status' => 'queued',
			'result' => __( 'Run ajouté à Action Scheduler.', 'dsa' ),
			'created_at' => current_time( 'mysql', true ),
			'finished_at' => '',
			'query' => sanitize_text_field( $settings['query'] ),
			'marketplace' => sanitize_key( $settings['marketplace'] ),
			'limit' => absint( $settings['limit'] ),
			'counts' => array( 'found' => 0, 'scored' => 0, 'created' => 0, 'duplicates' => 0, 'errors' => 0 ),
		);
		$runs[ $run['id'] ] = $run;
		$this->save_runs( $runs );
		return $run;
	}

	public function find( $run_id ) {
		$runs = $this->runs();
		$key = sanitize_text_field( (string) $run_id );
		return isset( $runs[ $key ] ) ? $runs[ $key ] : null;
	}

	public function update( $run_id, array $changes ) {
		$runs = $this->runs();
		$key = sanitize_text_field( (string) $run_id );
		if ( ! isset( $runs[ $key ] ) ) {
			return false;
		}
		$runs[ $key ] = array_merge( $runs[ $key ], $changes );
		$this->save_runs( $runs );
		return true;
	}

	public function latest() {
		$runs = array_values( $this->runs() );
		usort( $runs, static function ( $left, $right ) { return strcmp( $right['created_at'], $left['created_at'] ); } );
		return array_slice( $runs, 0, 50 );
	}

	private function runs() {
		$runs = get_option( self::OPTION, array() );
		return is_array( $runs ) ? $runs : array();
	}

	private function save_runs( array $runs ) {
		uasort( $runs, static function ( $left, $right ) { return strcmp( $right['created_at'], $left['created_at'] ); } );
		update_option( self::OPTION, array_slice( $runs, 0, self::MAX_RUNS, true ), false );
	}
}