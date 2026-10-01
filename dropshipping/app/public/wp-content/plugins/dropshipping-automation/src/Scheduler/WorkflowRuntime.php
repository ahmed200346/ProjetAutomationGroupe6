<?php

namespace DSA\Scheduler;

defined( 'ABSPATH' ) || exit;

final class WorkflowRuntime {
	const OPTION = 'dsa_workflow_schedules';
	const HISTORY_OPTION = 'dsa_workflow_runs';
	const HISTORY_TRANSIENT = 'dsa_workflow_demo_runs';
	const GROUP = 'dsa-workflows';
	const WORKFLOWS = array( 'products', 'trends' );

	public function register() {
		add_action( 'dsa_workflow_schedule', array( $this, 'start_scheduled' ), 10, 1 );
		add_action( 'dsa_workflow_simulate', array( $this, 'simulate' ), 10, 2 );
		( new \DSA\Integrations\N8nWorkflowRunner() )->register();
	}

	public function schedules() {
		$saved = get_option( self::OPTION, array() );
		if ( is_string( $saved ) ) {
			$decoded = json_decode( $saved, true );
			$saved = is_array( $decoded ) ? $decoded : array();
		}
		$saved = is_array( $saved ) ? $saved : array();
		$defaults = array(
			'products' => array( 'mode' => 'daily', 'time' => '02:00', 'enabled' => false ),
			'trends'   => array( 'mode' => 'daily', 'time' => '02:00', 'enabled' => false ),
		);
		$schedules = array();
		foreach ( self::WORKFLOWS as $workflow ) {
			$input = isset( $saved[ $workflow ] ) && is_array( $saved[ $workflow ] ) ? $saved[ $workflow ] : $defaults[ $workflow ];
			try {
				$schedules[ $workflow ] = CronSchedule::normalize( $input, wp_timezone_string() ?: 'UTC' );
			} catch ( \InvalidArgumentException $error ) {
				$schedules[ $workflow ] = CronSchedule::normalize( $defaults[ $workflow ], wp_timezone_string() ?: 'UTC' );
			}
		}
		return $schedules;
	}

	public function save_schedule( $workflow, $configuration ) {
		$schedules = $this->schedules();
		$schedules[ $workflow ] = $configuration;
		update_option( self::OPTION, wp_json_encode( $schedules, JSON_UNESCAPED_SLASHES ), false );
		$this->reconcile( $workflow, $configuration );
		return $configuration;
	}

	public function reconcile( $workflow, $configuration ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'dsa_workflow_schedule', array( $workflow ), self::GROUP );
		}
		if ( empty( $configuration['enabled'] ) || 'manual' === $configuration['mode'] || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		$occurrences = CronSchedule::next_occurrences( $configuration, new \DateTimeImmutable( 'now', new \DateTimeZone( $configuration['timezone'] ) ), 1 );
		if ( $occurrences ) {
			as_schedule_single_action( $occurrences[0]->getTimestamp(), 'dsa_workflow_schedule', array( $workflow ), self::GROUP, true );
		}
	}

	public function start_scheduled( $workflow ) {
		if ( ! in_array( $workflow, self::WORKFLOWS, true ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$configuration = $this->schedules()[ $workflow ];
		if ( empty( $configuration['enabled'] ) || 'manual' === $configuration['mode'] ) {
			return;
		}
		if ( 'products' === $workflow ) {
			( new \DSA\Integrations\N8nWorkflowRunner() )->enqueue( 'scheduled' );
		} else {
			as_enqueue_async_action( 'dsa_workflow_simulate', array( $workflow, 'scheduled' ), self::GROUP );
		}
		$this->reconcile( $workflow, $configuration );
	}

	public function enqueue_manual( $workflow ) {
		if ( 'products' === $workflow ) {
			return ( new \DSA\Integrations\N8nWorkflowRunner() )->enqueue( 'manual' );
		}
		if ( ! in_array( $workflow, self::WORKFLOWS, true ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}
		return as_enqueue_async_action( 'dsa_workflow_simulate', array( $workflow, 'manual' ), self::GROUP );
	}

	public function simulate( $workflow, $source = 'manual' ) {
		if ( ! in_array( $workflow, self::WORKFLOWS, true ) ) {
			return;
		}
		$labels = array(
			'products' => __( 'Scraping produits', 'dsa' ),
			'trends'   => __( 'Scraping tendances', 'dsa' ),
		);
		$history = $this->history();
		$run_id = wp_generate_uuid4();
		$failed = 3 === ( count( $history ) % 4 );
		$created_at = current_time( 'mysql', true );
		$result = $failed
			? __( 'Exécution simulée : une étape a échoué.', 'dsa' )
			: __( 'Exécution simulée terminée.', 'dsa' );
		array_unshift(
			$history,
			array(
				'id'         => $run_id,
				'workflow'   => $workflow,
				'label'      => $labels[ $workflow ],
				'source'     => 'scheduled' === $source ? 'scheduled' : 'manual',
				'status'     => $failed ? 'failed' : 'simulated',
				'result'     => $result,
				'created_at' => $created_at,
			)
		);
		set_transient( self::HISTORY_TRANSIENT, array_slice( $history, 0, 50 ), 30 * DAY_IN_SECONDS );
		$event = array(
			'id' => $run_id,
			'workflow' => $workflow,
			'status' => $failed ? 'failed' : 'completed',
			'started_at' => $created_at,
			'finished_at' => $created_at,
			'counts' => array( 'found' => 0, 'scored' => 0, 'created' => 0, 'duplicates' => 0, 'errors' => $failed ? 1 : 0 ),
			'demo' => true,
		);
		do_action( $failed ? 'dsa_run_failed' : 'dsa_run_completed', $event );
	}

	public function history() {
		$demo_history = get_transient( self::HISTORY_TRANSIENT );
		$history = is_array( $demo_history ) ? $demo_history : get_option( self::HISTORY_OPTION, array() );
		$history = is_array( $history ) ? $history : array();
		$history = array_merge( $history, ( new \DSA\Integrations\N8nRunStore() )->latest() );
		usort( $history, static function ( $left, $right ) { return strcmp( $right['created_at'] ?? '', $left['created_at'] ?? '' ); } );
		return array_slice( $history, 0, 50 );
	}

	public function cron_health() {
		return array(
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'action_scheduler' => function_exists( 'as_schedule_single_action' ) && function_exists( 'as_enqueue_async_action' ),
			'n8n_webhook'       => ( new \DSA\Integrations\N8nWebhookClient() )->is_configured(),
			'command'          => 'wp action-scheduler run --quiet',
		);
	}

	public static function unschedule() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		foreach ( self::WORKFLOWS as $workflow ) {
			as_unschedule_all_actions( 'dsa_workflow_schedule', array( $workflow ), self::GROUP );
		}
	}
}