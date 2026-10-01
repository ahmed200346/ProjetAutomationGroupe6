<?php

namespace DSA\REST;

use DSA\Notifications\DigestBuilder;
use DSA\Notifications\DigestMailer;
use DSA\Notifications\NotificationCenter;
use DSA\Notifications\NotificationSettings;
use DSA\Storage\Repositories\Demo\DemoDataset;

defined( 'ABSPATH' ) || exit;

final class NotificationsController {
	private $center;
	private $mailer;

	public function __construct( NotificationCenter $center = null, DigestMailer $mailer = null ) {
		$this->center = null === $center ? new NotificationCenter() : $center;
		$this->mailer = null === $mailer ? new DigestMailer() : $mailer;
	}

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$this->route( '/notifications/settings', 'GET', 'get_settings' );
		$this->route( '/notifications/settings', 'POST', 'save_settings' );
		$this->route( '/notifications', 'GET', 'get_notifications' );
		$this->route( '/notifications/read-all', 'POST', 'mark_all_read' );
		$this->route( '/notifications/(?P<id>[a-f0-9]{24})/read', 'POST', 'mark_read' );
		$this->route( '/notifications/test-email', 'POST', 'send_test_email' );
		$this->route( '/notifications/preview', 'GET', 'preview_digest' );
		$this->route( '/logs', 'GET', 'get_logs' );
		$this->route( '/logs/(?P<id>[0-9]+)', 'GET', 'get_log_detail' );
		$this->route( '/logs/purge', 'POST', 'purge_logs' );
		$this->route( '/logs/export', 'GET', 'export_logs' );
	}

	public function check_permission( $request ) {
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return current_user_can( 'manage_options' ) && is_string( $nonce ) && (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	public function get_settings() {
		$settings = NotificationSettings::get();
		$configured = array_map( 'boolval', array_map( 'strlen', $settings['webhooks'] ) );
		$settings['webhooks'] = array( 'slack' => '', 'telegram' => '' );
		$settings['webhooks_configured'] = $configured;
		return new \WP_REST_Response( $settings, 200 );
	}

	public function save_settings( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		$old = NotificationSettings::get();
		$webhooks = isset( $input['webhooks'] ) && is_array( $input['webhooks'] ) ? $input['webhooks'] : array();
		$clear = isset( $input['clear_webhooks'] ) && is_array( $input['clear_webhooks'] ) ? $input['clear_webhooks'] : array();
		foreach ( NotificationSettings::WEBHOOKS as $provider ) {
			if ( empty( $clear[ $provider ] ) && empty( $webhooks[ $provider ] ) ) {
				$webhooks[ $provider ] = $old['webhooks'][ $provider ];
			}
		}
		$input['webhooks'] = $webhooks;
		$settings = NotificationSettings::sanitize( $input );
		update_option( NotificationSettings::OPTION, $settings, false );
		return $this->get_settings();
	}

	public function get_notifications( \WP_REST_Request $request ) {
		$limit = $this->integer_param( $request, 'limit', 10 );
		return new \WP_REST_Response(
			array( 'items' => $this->center->latest( $limit ), 'unread' => $this->center->unread_count() ),
			200
		);
	}

	public function mark_read( \WP_REST_Request $request ) {
		$marked = $this->center->mark_read( sanitize_text_field( $request['id'] ) );
		return new \WP_REST_Response( array( 'marked' => $marked, 'unread' => $this->center->unread_count() ), $marked ? 200 : 404 );
	}

	public function mark_all_read() {
		$this->center->mark_all_read();
		return new \WP_REST_Response( array( 'unread' => 0 ), 200 );
	}

	public function send_test_email() {
		if ( ! NotificationSettings::recipients() ) {
			return new \WP_Error( 'dsa_email_not_configured', __( 'Activez les e-mails et vérifiez l’adresse de destination avant le test.', 'dsa' ), array( 'status' => 400 ) );
		}
		$sent = $this->mailer->send_test();
		return $sent
			? new \WP_REST_Response( array( 'sent' => true ), 200 )
			: new \WP_Error( 'dsa_email_failed', __( 'WordPress n’a pas pu envoyer le message de test.', 'dsa' ), array( 'status' => 502 ) );
	}

	public function preview_digest() {
		$builder = new DigestBuilder();
		return new \WP_REST_Response( array( 'html' => $builder->render_html( $this->preview_run() ) ), 200 );
	}

	public function get_logs( \WP_REST_Request $request ) {
		$all = $this->filtered_runs( $request );
		$page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 5, min( 25, absint( $request->get_param( 'per_page' ) ?: 10 ) ) );
		$total = count( $all );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page = min( $page, $pages );
		return new \WP_REST_Response(
			array(
				'items' => array_slice( $all, ( $page - 1 ) * $per_page, $per_page ),
				'page' => $page,
				'per_page' => $per_page,
				'total' => $total,
				'pages' => $pages,
				'demo' => true,
			),
			200
		);
	}

	public function get_log_detail( \WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		foreach ( DemoDataset::table( 'runs' ) as $run ) {
			if ( $id === (int) $run['id'] ) {
				return new \WP_REST_Response( $this->format_run( $run, true ), 200 );
			}
		}
		return new \WP_Error( 'dsa_log_not_found', __( 'Cette exécution est introuvable.', 'dsa' ), array( 'status' => 404 ) );
	}

	public function purge_logs() {
		$removed = $this->center->purge( NotificationSettings::get()['retention_days'] );
		return new \WP_REST_Response( array( 'removed' => $removed, 'message' => __( 'Les notifications arrivées à expiration ont été supprimées.', 'dsa' ) ), 200 );
	}

	public function export_logs( \WP_REST_Request $request ) {
		$rows = $this->filtered_runs( $request );
		$stream = fopen( 'php://temp', 'r+' );
		fputcsv( $stream, array( 'ID', 'Workflow', 'Début UTC', 'Fin UTC', 'Statut', 'Niveau', 'Module', 'Trouvés', 'Ajoutés', 'Erreurs' ) );
		foreach ( $rows as $row ) {
			fputcsv( $stream, array( $row['id'], $row['workflow'], $row['started_at'], $row['finished_at'], $row['status'], $row['level'], $row['module'], $row['counts']['found'], $row['counts']['created'], $row['counts']['errors'] ) );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream );
		return new \WP_REST_Response( array( 'filename' => 'dsa-journaux.csv', 'csv' => "\xEF\xBB\xBF" . $csv ), 200 );
	}

	private function route( $route, $method, $callback ) {
		register_rest_route(
			'dsa/v1',
			$route,
			array(
				'methods' => $method,
				'callback' => array( $this, $callback ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	private function filtered_runs( \WP_REST_Request $request ) {
		$level = $this->string_param( $request, 'level' );
		$module = $this->string_param( $request, 'module' );
		$workflow = $this->string_param( $request, 'workflow' );
		$days = $this->integer_param( $request, 'days', 30 );
		$cutoff = time() - ( min( 365, max( 1, $days ) ) * DAY_IN_SECONDS );
		$runs = DemoDataset::table( 'runs' );
		$logs = DemoDataset::table( 'logs' );
		$filtered = array();
		foreach ( $runs as $run ) {
			$formatted = $this->format_run( $run, false );
			if ( '' !== $workflow && $workflow !== $formatted['workflow_key'] ) {
				continue;
			}
			if ( strtotime( $formatted['started_at'] . ' UTC' ) < $cutoff ) {
				continue;
			}
			$run_logs = array_values( array_filter( $logs, static function ( $log ) use ( $run ) { return (int) $log['run_id'] === (int) $run['id']; } ) );
			$modules = array_values( array_unique( array_column( $run_logs, 'module' ) ) );
			$log_levels = array_column( $run_logs, 'niveau' );
			$formatted['level'] = 'failed' === $run['statut'] || in_array( 'error', $log_levels, true )
				? 'error'
				: ( in_array( 'warning', $log_levels, true ) ? 'warning' : 'info' );
			if ( '' !== $level && $level !== $formatted['level'] ) {
				continue;
			}
			if ( '' !== $module && ! in_array( $module, $modules, true ) ) {
				continue;
			}
			$formatted['module'] = $modules ? implode( ', ', $modules ) : __( 'Workflow', 'dsa' );
			$formatted['log_count'] = count( $run_logs );
			$filtered[] = $formatted;
		}
		usort( $filtered, static function ( $left, $right ) { return strcmp( $right['started_at'], $left['started_at'] ); } );
		return $filtered;
	}

	private function format_run( array $run, $include_logs ) {
		$stages = array_values( array_filter( DemoDataset::table( 'run_stages' ), static function ( $stage ) use ( $run ) { return (int) $stage['run_id'] === (int) $run['id']; } ) );
		$result = array(
			'id' => (int) $run['id'],
			'workflow' => $this->workflow_label( $run['workflow'] ),
			'workflow_key' => $run['workflow'],
			'started_at' => $run['debut'],
			'finished_at' => $run['fin'],
			'status' => $run['statut'],
			'level' => 'failed' === $run['statut'] ? 'error' : 'info',
			'module' => '',
			'counts' => array( 'found' => (int) $run['produits_trouves'], 'scored' => (int) $run['produits_trouves'], 'created' => (int) $run['produits_ajoutes'], 'duplicates' => 0, 'errors' => (int) $run['erreurs'], 'average_score' => (int) ( $run['score_moyen'] ?? 0 ) ),
			'stages' => $stages,
		);
		if ( $include_logs ) {
			$result['logs'] = array_values( array_filter( DemoDataset::table( 'logs' ), static function ( $log ) use ( $run ) { return (int) $log['run_id'] === (int) $run['id']; } ) );
		}
		return $result;
	}

	private function workflow_label( $workflow ) {
		$labels = array( 'social_scan' => __( 'Collecte sociale', 'dsa' ), 'product_scoring' => __( 'Évaluation produits', 'dsa' ), 'catalog_sync' => __( 'Synchronisation catalogue', 'dsa' ) );
		return $labels[ $workflow ] ?? __( 'Workflow', 'dsa' );
	}

	private function preview_run() {
		return array(
			'id' => __( 'aperçu', 'dsa' ),
			'workflow' => 'social_scan',
			'status' => 'completed',
			'counts' => array( 'found' => 12, 'scored' => 8, 'created' => 3, 'duplicates' => 2, 'errors' => 1, 'average_score' => 74 ),
			'stages' => array( array( 'stage' => 'scraping', 'status' => 'failed' ) ),
		);
	}

	private function string_param( \WP_REST_Request $request, $key ) {
		$value = $request->get_param( $key );
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	private function integer_param( \WP_REST_Request $request, $key, $default ) {
		$value = $request->get_param( $key );
		return is_scalar( $value ) && '' !== (string) $value ? absint( $value ) : absint( $default );
	}
}