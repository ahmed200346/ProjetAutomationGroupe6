<?php

namespace DSA\Integrations;

defined( 'ABSPATH' ) || exit;

final class N8nWorkflowRunner {
	const ACTION = 'dsa_n8n_dispatch_run';
	const GROUP = 'dsa-n8n-workflows';

	private $client;
	private $runs;

	public function __construct( N8nWebhookClient $client = null, N8nRunStore $runs = null ) {
		$this->client = null === $client ? new N8nWebhookClient() : $client;
		$this->runs = null === $runs ? new N8nRunStore() : $runs;
	}

	public function register() {
		add_action( self::ACTION, array( $this, 'dispatch' ), 10, 3 );
	}

	public function enqueue( $source = 'manual' ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return new \WP_Error( 'dsa_action_scheduler_unavailable', __( 'Action Scheduler est indisponible; le run n’a pas été lancé.', 'dsa' ), array( 'status' => 503 ) );
		}
		if ( ! $this->client->is_configured() ) {
			return new \WP_Error( 'dsa_n8n_not_configured', __( 'Configurez le webhook n8n et ses tokens dans wp-config.php avant de lancer une recherche.', 'dsa' ), array( 'status' => 503 ) );
		}

		$settings = N8nWorkflowSettings::get();
		if ( '' === $settings['query'] ) {
			return new \WP_Error( 'dsa_n8n_query_missing', __( 'Saisissez une recherche produit avant de lancer le workflow.', 'dsa' ), array( 'status' => 400 ) );
		}

		$run_id = wp_generate_uuid4();
		$run = $this->runs->create( $run_id, $settings, $source );
		$action_id = as_enqueue_async_action( self::ACTION, array( $run_id, 'products', $run['source'] ), self::GROUP, true );
		if ( ! $action_id ) {
			$this->runs->update( $run_id, array( 'status' => 'failed', 'result' => __( 'Action Scheduler n’a pas accepté le run.', 'dsa' ), 'finished_at' => current_time( 'mysql', true ), 'counts' => array( 'found' => 0, 'scored' => 0, 'created' => 0, 'duplicates' => 0, 'errors' => 1 ) ) );
			return new \WP_Error( 'dsa_n8n_enqueue_failed', __( 'Action Scheduler n’a pas accepté le run.', 'dsa' ), array( 'status' => 503 ) );
		}

		return array( 'run_id' => $run_id, 'action_id' => absint( $action_id ) );
	}

	public function dispatch( $run_id, $workflow, $source ) {
		if ( 'products' !== $workflow ) {
			return;
		}
		$run = $this->runs->find( $run_id );
		if ( ! $run || in_array( $run['status'], array( 'running', 'completed', 'failed' ), true ) ) {
			return;
		}
		$this->runs->update( $run_id, array( 'status' => 'dispatching', 'result' => __( 'Transmission vers n8n en cours.', 'dsa' ) ) );
		$response = $this->client->dispatch(
			array(
				'run_id' => $run['id'],
				'query' => $run['query'],
				'marketplace' => $run['marketplace'],
				'limit' => $run['limit'],
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->runs->update( $run_id, array( 'status' => 'dispatch_failed', 'result' => $response->get_error_message(), 'finished_at' => current_time( 'mysql', true ), 'counts' => array( 'found' => 0, 'scored' => 0, 'created' => 0, 'duplicates' => 0, 'errors' => 1 ) ) );
			return;
		}
		$this->runs->update( $run_id, array( 'status' => 'running', 'result' => __( 'Accepté par n8n; en attente du résultat du workflow.', 'dsa' ) ) );
	}
}