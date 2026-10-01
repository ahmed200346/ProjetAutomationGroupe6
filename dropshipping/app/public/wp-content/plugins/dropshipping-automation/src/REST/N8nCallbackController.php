<?php

namespace DSA\REST;

use DSA\Integrations\N8nRunStore;
use DSA\Integrations\N8nResultsStore;

defined( 'ABSPATH' ) || exit;

final class N8nCallbackController {
	private $runs;
	private $results;

	public function __construct( N8nRunStore $runs = null, N8nResultsStore $results = null ) {
		$this->runs = null === $runs ? new N8nRunStore() : $runs;
		$this->results = null === $results ? new N8nResultsStore() : $results;
	}

	public function register() {
		$this->results->maybe_install();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'dsa/v1',
			'/workflows/callback',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'receive' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/workflows/results',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'receive_result' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission( $request ) {
		$expected = defined( 'DSA_N8N_CALLBACK_TOKEN' ) && is_string( DSA_N8N_CALLBACK_TOKEN ) ? DSA_N8N_CALLBACK_TOKEN : '';
		$provided = $request instanceof \WP_REST_Request ? $request->get_header( 'X-DSA-Callback-Token' ) : '';
		return strlen( $expected ) >= 32 && is_string( $provided ) && hash_equals( $expected, $provided );
	}

	public function receive( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		$run_id = isset( $input['run_id'] ) && is_string( $input['run_id'] ) ? sanitize_text_field( $input['run_id'] ) : '';
		$status = isset( $input['status'] ) && is_string( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
		if ( '' === $run_id || ! in_array( $status, array( 'completed', 'failed' ), true ) ) {
			return new \WP_Error( 'dsa_n8n_callback_invalid', __( 'Le callback n8n ne respecte pas le contrat attendu.', 'dsa' ), array( 'status' => 400 ) );
		}
		$existing = $this->runs->find( $run_id );
		if ( ! $existing ) {
			return new \WP_Error( 'dsa_n8n_run_not_found', __( 'Le run associé à ce callback est introuvable.', 'dsa' ), array( 'status' => 404 ) );
		}
		if ( in_array( $existing['status'], array( 'completed', 'failed' ), true ) ) {
			return new \WP_REST_Response( array( 'accepted' => true, 'duplicate' => true ), 200 );
		}

		$input_counts = isset( $input['counts'] ) && is_array( $input['counts'] ) ? $input['counts'] : array();
		$counts = array();
		foreach ( array( 'found', 'scored', 'created', 'duplicates', 'errors' ) as $counter ) {
			$value = $input_counts[ $counter ] ?? 0;
			$counts[ $counter ] = is_scalar( $value ) ? min( 1000000, absint( $value ) ) : 0;
		}
		$summary = 'completed' === $status
			? __( 'Workflow n8n terminé.', 'dsa' )
			: __( 'Le workflow n8n a échoué. Consultez son exécution dans n8n.', 'dsa' );
		$updated = $this->runs->update(
			$run_id,
			array(
				'status' => $status,
				'result' => $summary,
				'finished_at' => current_time( 'mysql', true ),
				'counts' => $counts,
			)
		);
		if ( ! $updated ) {
			return new \WP_Error( 'dsa_n8n_run_update_failed', __( 'Le résultat du run n’a pas pu être enregistré.', 'dsa' ), array( 'status' => 500 ) );
		}
		do_action(
			'completed' === $status ? 'dsa_run_completed' : 'dsa_run_failed',
			array(
				'id' => $run_id,
				'workflow' => 'products',
				'status' => $status,
				'counts' => $counts,
				'demo' => false,
			)
		);
		return new \WP_REST_Response( array( 'accepted' => true, 'run_id' => $run_id ), 200 );
	}

	public function receive_result( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		$product_result = isset( $input['product_result'] ) && is_array( $input['product_result'] )
			? $input['product_result']
			: ( isset( $input['body'] ) && is_array( $input['body'] ) ? $input['body'] : $input );

		if (
			! isset( $product_result['rank'], $product_result['product']['title'] ) ||
			! is_numeric( $product_result['rank'] ) ||
			absint( $product_result['rank'] ) < 1 ||
			! is_string( $product_result['product']['title'] ) ||
			'' === trim( $product_result['product']['title'] )
		) {
			return new \WP_Error( 'dsa_n8n_result_invalid', __( 'Le résultat produit ne respecte pas le contrat attendu.', 'dsa' ), array( 'status' => 400 ) );
		}

		$run_id = isset( $input['run_id'] ) && is_string( $input['run_id'] ) ? $input['run_id'] : '';
		$result_id = $this->results->insert( $run_id, $product_result );
		if ( false === $result_id ) {
			return new \WP_Error( 'dsa_n8n_result_save_failed', __( 'Le résultat produit n’a pas pu être enregistré.', 'dsa' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'accepted' => true,
				'result_id' => $result_id,
				'run_id' => sanitize_text_field( $run_id ),
				'rank' => absint( $product_result['rank'] ),
			),
			201
		);
	}
}