<?php

namespace DSA\REST;

use DSA\Storage\ProviderSettings;
use DSA\Storage\Repositories\Demo\DemoDataset;

defined( 'ABSPATH' ) || exit;

final class BusinessDemoController {
	const STATE_KEY = 'dsa_business_demo_state';
	const STATE_TTL = 30 * DAY_IN_SECONDS;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'dsa/v1',
			'/demo',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_state' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'apply_action' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	public function check_permission( $request ): bool {
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return current_user_can( 'manage_options' ) && is_string( $nonce ) && (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	public function get_state(): \WP_REST_Response {
		return new \WP_REST_Response( $this->state(), 200 );
	}

	public function apply_action( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		$area = isset( $input['area'] ) ? sanitize_key( $input['area'] ) : '';
		$action = isset( $input['action'] ) ? sanitize_key( $input['action'] ) : '';
		$state = $this->state();

		switch ( $area ) {
			case 'workflow':
				$workflow = isset( $input['workflow'] ) ? sanitize_key( $input['workflow'] ) : '';
				if ( ! in_array( $workflow, array( 'products', 'trends' ), true ) || 'save' !== $action ) {
					return $this->invalid_action();
				}
				$configuration = $this->sanitize_workflow_configuration( $input );
				if ( is_wp_error( $configuration ) ) {
					return $configuration;
				}
				$state['workflows'][ $workflow ] = $configuration;
				break;

			case 'product':
				$status = array( 'approve' => 'published', 'reject' => 'rejected', 'rescore' => 'shortlist' )[ $action ] ?? '';
				$ids = $this->valid_ids( $input['ids'] ?? array(), array_column( DemoDataset::table( 'products' ), 'id' ) );
				if ( '' === $status || ! $ids ) {
					return $this->invalid_action();
				}
				foreach ( $ids as $id ) {
					$state['products'][ $id ] = $status;
				}
				break;

			case 'supplier':
				$supplier = isset( $input['supplier'] ) ? sanitize_key( $input['supplier'] ) : '';
				if ( ! in_array( $supplier, array( 'amazon', 'alibaba', 'aliexpress' ), true ) || ! in_array( $action, array( 'connect', 'disconnect' ), true ) ) {
					return $this->invalid_action();
				}
				$state['suppliers'][ $supplier ] = 'connect' === $action;
				break;

			case 'order':
				$order_id = absint( $input['id'] ?? 0 );
				$order = $this->find_row( DemoDataset::table( 'orders' ), $order_id );
				if ( ! $order || ! in_array( $action, array( 'transmit', 'ship', 'deliver' ), true ) ) {
					return $this->invalid_action();
				}
				$state['orders'][ $order_id ] = array( 'transmit' => 'processing', 'ship' => 'shipped', 'deliver' => 'delivered' )[ $action ];
				break;

			case 'support':
				$review_id = absint( $input['id'] ?? 0 );
				if ( ! $this->find_row( DemoDataset::table( 'reviews' ), $review_id ) || ! in_array( $action, array( 'reply', 'refund', 'faq' ), true ) ) {
					return $this->invalid_action();
				}
				$state['support'][ $review_id ] = $action;
				break;

			default:
				return $this->invalid_action();
		}

		set_transient( self::STATE_KEY, $state, self::STATE_TTL );
		return new \WP_REST_Response( array( 'state' => $state, 'simulated' => true ), 200 );
	}

	private function sanitize_workflow_configuration( array $input ) {
		$platforms = array( 'x', 'facebook', 'instagram' );
		$suppliers = array( 'amazon', 'alibaba', 'aliexpress' );
		$category_ids = array_map( 'absint', array_column( DemoDataset::table( 'categories' ), 'id' ) );
		$enabled_models = array();
		$provider_settings = new ProviderSettings();
		foreach ( array( 'gemini', 'ollama', 'openrouter', 'nvidia' ) as $provider ) {
			$config = $provider_settings->get( $provider );
			foreach ( $config['models'] as $model ) {
				if ( in_array( $model['id'], $config['enabled'], true ) ) {
					$enabled_models[] = $model['id'];
				}
			}
		}

		$model = isset( $input['model'] ) && is_string( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		if ( '' !== $model && ! in_array( $model, $enabled_models, true ) ) {
			return new \WP_Error( 'dsa_invalid_demo_model', __( 'Choisissez un modèle IA activé dans les réglages.', 'dsa' ), array( 'status' => 400 ) );
		}

		return array(
			'platforms'        => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['platforms'] ?? array() ) ), $platforms ) ),
			'categories'       => array_values( array_intersect( array_map( 'absint', (array) ( $input['categories'] ?? array() ) ), $category_ids ) ),
			'suppliers'        => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['suppliers'] ?? array() ) ), $suppliers ) ),
			'products_per_run' => max( 1, min( 100, absint( $input['products_per_run'] ?? 10 ) ) ),
			'minimum_score'    => max( 0, min( 100, absint( $input['minimum_score'] ?? 60 ) ) ),
			'model'            => $model,
		);
	}

	private function valid_ids( $ids, array $allowed ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$ids = array_slice( array_unique( array_map( 'absint', $ids ) ), 0, 80 );
		return array_values( array_intersect( $ids, $allowed ) );
	}

	private function find_row( array $rows, int $id ) {
		foreach ( $rows as $row ) {
			if ( $id === (int) $row['id'] ) {
				return $row;
			}
		}
		return null;
	}

	private function state(): array {
		$state = get_transient( self::STATE_KEY );
		return is_array( $state ) ? $state : array(
			'workflows' => array(),
			'products'  => array(),
			'suppliers' => array(),
			'orders'    => array(),
			'support'   => array(),
		);
	}

	private function invalid_action(): \WP_Error {
		return new \WP_Error( 'dsa_invalid_demo_action', __( 'Cette action de démonstration n’est pas autorisée.', 'dsa' ), array( 'status' => 400 ) );
	}
}