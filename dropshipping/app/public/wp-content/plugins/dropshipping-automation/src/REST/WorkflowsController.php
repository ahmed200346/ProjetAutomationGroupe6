<?php

namespace DSA\REST;

use DSA\Scheduler\CronSchedule;

defined( 'ABSPATH' ) || exit;

final class WorkflowsController {
	private $runtime;

	public function __construct( \DSA\Scheduler\WorkflowRuntime $runtime ) {
		$this->runtime = $runtime;
	}

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'dsa/v1',
			'/workflows',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_workflows' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/workflows/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/workflows/product-settings',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_product_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'save_product_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/workflows/(?P<workflow>products|trends)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_workflow' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/workflows/(?P<workflow>products|trends)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_now' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission( $request ) {
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return current_user_can( 'manage_options' ) && is_string( $nonce ) && (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	public function get_workflows() {
		$schedules = $this->runtime->schedules();
		$history = $this->runtime->history();
		$status = array();
		foreach ( $schedules as $workflow => $configuration ) {
			$latest = null;
			foreach ( $history as $run ) {
				if ( isset( $run['workflow'] ) && $workflow === $run['workflow'] ) {
					$latest = $run;
					break;
				}
			}
			$status[ $workflow ] = array(
				'last'       => $latest,
				'upcoming'   => $this->format_occurrences( CronSchedule::next_occurrences( $configuration ) ),
				'summary'    => $this->summary( $configuration ),
				'expression' => $configuration['expression'],
			);
		}
		return new \WP_REST_Response(
			array( 'schedules' => $schedules, 'status' => $status, 'history' => $history, 'health' => $this->runtime->cron_health() ),
			200
		);
	}

	public function get_product_settings() {
		return new \WP_REST_Response( \DSA\Integrations\N8nWorkflowSettings::get(), 200 );
	}

	public function save_product_settings( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$settings = \DSA\Integrations\N8nWorkflowSettings::save( is_array( $input ) ? $input : array() );
		return new \WP_REST_Response( $settings, 200 );
	}

	public function preview( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		try {
			$configuration = CronSchedule::normalize( is_array( $input ) ? $input : array(), wp_timezone_string() ?: 'UTC' );
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'dsa_invalid_schedule', $error->getMessage(), array( 'status' => 400 ) );
		}
		return new \WP_REST_Response(
			array(
				'expression'  => $configuration['expression'],
				'explanation' => $this->summary( $configuration ),
				'occurrences' => $this->format_occurrences( CronSchedule::next_occurrences( $configuration ) ),
			),
			200
		);
	}

	public function save_workflow( \WP_REST_Request $request ) {
		$workflow = sanitize_key( $request['workflow'] );
		$input = $request->get_json_params();
		try {
			$configuration = CronSchedule::normalize( is_array( $input ) ? $input : array(), wp_timezone_string() ?: 'UTC' );
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'dsa_invalid_schedule', $error->getMessage(), array( 'status' => 400 ) );
		}
		$saved = $this->runtime->save_schedule( $workflow, $configuration );
		return new \WP_REST_Response( array( 'schedule' => $saved, 'status' => $this->get_workflows()->get_data()['status'][ $workflow ] ), 200 );
	}

	public function run_now( \WP_REST_Request $request ) {
		$workflow = sanitize_key( $request['workflow'] );
		if ( ! in_array( $workflow, \DSA\Scheduler\WorkflowRuntime::WORKFLOWS, true ) ) {
			return new \WP_Error( 'dsa_invalid_workflow', __( 'Ce workflow n’est pas autorisé.', 'dsa' ), array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			if ( 'products' === $workflow ) {
				return new \WP_Error( 'dsa_action_scheduler_unavailable', __( 'Action Scheduler est indisponible; aucun run réel n’a été lancé.', 'dsa' ), array( 'status' => 503 ) );
			}
			$this->runtime->simulate( $workflow, 'manual' );
			return new \WP_REST_Response( array( 'queued' => false, 'simulated' => true ), 202 );
		}
		$action_id = $this->runtime->enqueue_manual( $workflow );
		if ( is_wp_error( $action_id ) ) {
			return $action_id;
		}
		if ( ! $action_id ) {
			return new \WP_Error( 'dsa_enqueue_failed', __( 'Le lancement n’a pas pu être mis en file.', 'dsa' ), array( 'status' => 503 ) );
		}
		return new \WP_REST_Response( array( 'queued' => true, 'action_id' => absint( is_array( $action_id ) ? $action_id['action_id'] : $action_id ), 'run_id' => is_array( $action_id ) ? $action_id['run_id'] : '' ), 202 );
	}

	private function summary( $configuration ) {
		$time = isset( $configuration['time'] ) ? $configuration['time'] : '';
		switch ( $configuration['mode'] ) {
			case 'manual':
				return __( 'Exécution uniquement à la demande.', 'dsa' );
			case 'interval':
				$units = array( 'minutes' => __( 'minute(s)', 'dsa' ), 'hours' => __( 'heure(s)', 'dsa' ), 'days' => __( 'jour(s)', 'dsa' ) );
				return sprintf( __( 'Toutes les %1$d %2$s.', 'dsa' ), $configuration['amount'], $units[ $configuration['unit'] ] );
			case 'daily':
				return sprintf( __( 'Chaque jour à %s.', 'dsa' ), $time );
			case 'weekly':
				$labels = array( 1 => __( 'lundi', 'dsa' ), 2 => __( 'mardi', 'dsa' ), 3 => __( 'mercredi', 'dsa' ), 4 => __( 'jeudi', 'dsa' ), 5 => __( 'vendredi', 'dsa' ), 6 => __( 'samedi', 'dsa' ), 0 => __( 'dimanche', 'dsa' ) );
				$days = array_map( static function ( $day ) use ( $labels ) { return $labels[ $day ]; }, $configuration['weekdays'] );
				return sprintf( __( 'Chaque %1$s à %2$s.', 'dsa' ), implode( ' et ', $days ), $time );
			case 'monthly':
				if ( 'weekday' === $configuration['monthly_type'] ) {
					$ordinals = array( 1 => __( 'premier', 'dsa' ), 2 => __( 'deuxième', 'dsa' ), 3 => __( 'troisième', 'dsa' ), 4 => __( 'quatrième', 'dsa' ), 5 => __( 'cinquième', 'dsa' ) );
					$days = array( 1 => __( 'lundi', 'dsa' ), 2 => __( 'mardi', 'dsa' ), 3 => __( 'mercredi', 'dsa' ), 4 => __( 'jeudi', 'dsa' ), 5 => __( 'vendredi', 'dsa' ), 6 => __( 'samedi', 'dsa' ), 0 => __( 'dimanche', 'dsa' ) );
					return sprintf( __( 'Le %1$s %2$s de chaque mois à %3$s.', 'dsa' ), $ordinals[ $configuration['ordinal'] ], $days[ $configuration['weekday'] ], $time );
				}
				$day = 'last' === $configuration['day'] ? __( 'dernier jour', 'dsa' ) : sprintf( __( '%d de chaque mois', 'dsa' ), $configuration['day'] );
				return sprintf( __( 'Le %1$s à %2$s.', 'dsa' ), $day, $time );
			case 'once':
				return sprintf( __( 'Une seule fois, le %s.', 'dsa' ), $configuration['once_at'] );
			case 'advanced':
				return CronSchedule::cron_explanation( $configuration['cron'] );
		}
		return '';
	}

	private function format_occurrences( $occurrences ) {
		$formatted = array();
		foreach ( $occurrences as $occurrence ) {
			$formatted[] = array(
				'iso'   => $occurrence->format( DATE_ATOM ),
				'label' => wp_date( 'D j M Y · H:i', $occurrence->getTimestamp(), $occurrence->getTimezone() ),
			);
		}
		return $formatted;
	}
}