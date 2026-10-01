<?php

namespace DSA\REST;

use DSA\Scheduler\CronSchedule;
use DSA\Scheduler\WorkflowRuntime;
use DSA\Storage\ProviderSettings;
use DSA\Storage\Repositories\RepositoryFactory;

defined( 'ABSPATH' ) || exit;

final class DashboardController {
	private $runtime;
	private $repositories;

	public function __construct( WorkflowRuntime $runtime, RepositoryFactory $repositories ) {
		$this->runtime      = $runtime;
		$this->repositories = $repositories;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'dsa/v1',
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'period' => array(
						'default'           => '30',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return in_array( (string) $value, array( '7', '30', '90' ), true );
						},
					),
				),
			)
		);
		register_rest_route(
			'dsa/v1',
			'/dashboard/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_now' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission( $request ): bool {
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return current_user_can( 'manage_options' ) && is_string( $nonce ) && (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	public function get_dashboard( \WP_REST_Request $request ): \WP_REST_Response {
		$period = absint( $request->get_param( 'period' ) );
		if ( ! in_array( $period, array( 7, 30, 90 ), true ) ) {
			$period = 30;
		}

		$now       = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$start     = $now->modify( '-' . ( $period - 1 ) . ' days' )->setTime( 0, 0 );
		$previous  = $start->modify( '-' . $period . ' days' );
		$products  = $this->repositories->products()->all();
		$signals   = $this->repositories->trend_signals()->all();
		$orders    = $this->repositories->orders()->all();
		$runs      = $this->repositories->runs()->all();
		$metrics   = $this->calculate_metrics( $products, $signals, $orders, $runs, $start, $now, $previous );
		$runtime_runs = $this->runtime->history();
		$schedule_data = $this->scheduled_runs();
		$providers = new ProviderSettings();
		$provider_ready = $this->has_configured_provider( $providers );
		$cron_health = $this->runtime->cron_health();

		return new \WP_REST_Response(
			array(
				'period'    => $period,
				'metrics'   => $metrics,
				'chart'     => $this->chart_data( $signals, $start, $period ),
				'pipeline'  => $this->pipeline( $metrics, $provider_ready, $cron_health ),
				'scheduled' => $schedule_data,
				'runs'      => $this->recent_runs( $runs, $runtime_runs ),
				'alerts'    => $this->alerts( $metrics, $provider_ready, $cron_health ),
			),
			200
		);
	}

	public function run_now() {
		$action_id = $this->runtime->enqueue_manual( 'products' );
		if ( is_wp_error( $action_id ) ) {
			return $action_id;
		}
		if ( ! $action_id ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Action Scheduler est indisponible. Le lancement n’a pas été mis en file.', 'dsa' ) ),
				503
			);
		}
		return new \WP_REST_Response( array( 'queued' => true, 'action_id' => absint( $action_id['action_id'] ), 'run_id' => $action_id['run_id'] ), 202 );
	}

	private function calculate_metrics( array $products, array $signals, array $orders, array $runs, \DateTimeImmutable $start, \DateTimeImmutable $now, \DateTimeImmutable $previous ): array {
		$current = $this->period_values( $products, $signals, $orders, $runs, $start, $now );
		$prior   = $this->period_values( $products, $signals, $orders, $runs, $previous, $start );
		return array(
			'detected' => $this->metric( $current['detected'], $prior['detected'], '' ),
			'shortlist' => $this->metric( $current['shortlist'], $prior['shortlist'], '' ),
			'published' => $this->metric( $current['published'], $prior['published'], '' ),
			'margin' => $this->metric( $current['margin'], $prior['margin'], '%' ),
			'orders' => $this->metric( $current['orders'], $prior['orders'], '' ),
			'errors' => $this->metric( $current['errors'], $prior['errors'], '%' ),
			'pending' => $current['pending'],
		);
	}

	private function period_values( array $products, array $signals, array $orders, array $runs, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		$selected_products = $this->filter_rows( $products, 'created_at', $start, $end );
		$selected_signals  = $this->filter_rows( $signals, 'date', $start, $end );
		$selected_orders   = $this->filter_rows( $orders, 'date', $start, $end );
		$selected_runs     = $this->filter_rows( $runs, 'debut', $start, $end );
		$shortlist = 0;
		$published = 0;
		$pending   = 0;
		$margin_sum = 0.0;
		$margin_count = 0;
		$open_orders = 0;
		$errors = 0;
		foreach ( $selected_products as $product ) {
			if ( 'shortlist' === $product['statut'] ) { ++$shortlist; }
			if ( 'published' === $product['statut'] ) { ++$published; }
			if ( 'pending' === $product['statut'] ) { ++$pending; }
			if ( isset( $product['marge_pct'] ) ) { $margin_sum += (float) $product['marge_pct']; ++$margin_count; }
		}
		foreach ( $selected_orders as $order ) {
			if ( in_array( $order['statut'], array( 'processing', 'pending', 'shipped' ), true ) ) { ++$open_orders; }
		}
		foreach ( $selected_runs as $run ) {
			if ( 'failed' === $run['statut'] ) { ++$errors; }
		}
		$run_count = count( $selected_runs );
		return array(
			'detected' => count( $selected_signals ),
			'shortlist' => $shortlist,
			'published' => $published,
			'margin' => $margin_count ? round( $margin_sum / $margin_count, 1 ) : 0,
			'orders' => $open_orders,
			'errors' => $run_count ? round( ( $errors / $run_count ) * 100, 1 ) : 0,
			'pending' => count( array_filter( $products, static function ( $product ) { return 'pending' === $product['statut']; } ) ),
		);
	}

	private function filter_rows( array $rows, string $date_key, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		return array_values( array_filter( $rows, static function ( $row ) use ( $date_key, $start, $end ) {
			if ( empty( $row[ $date_key ] ) ) { return false; }
			try { $date = new \DateTimeImmutable( $row[ $date_key ], new \DateTimeZone( 'UTC' ) ); } catch ( \Exception $error ) { return false; }
			return $date >= $start && $date < $end;
		} ) );
	}

	private function metric( float $value, float $previous, string $suffix ): array {
		$delta = 0.0 === $previous ? ( 0.0 === $value ? 0 : null ) : round( ( ( $value - $previous ) / abs( $previous ) ) * 100, 1 );
		return array( 'value' => $value, 'suffix' => $suffix, 'delta' => $delta );
	}

	private function chart_data( array $signals, \DateTimeImmutable $start, int $period ): array {
		$series = array();
		for ( $index = 0; $index < 7; ++$index ) {
			$bucket_start = $start->modify( '+' . (int) floor( $index * $period / 7 ) . ' days' );
			$bucket_end = $start->modify( '+' . (int) floor( ( $index + 1 ) * $period / 7 ) . ' days' );
			$count = 0;
			foreach ( $signals as $signal ) {
				if ( empty( $signal['date'] ) ) { continue; }
				$date = new \DateTimeImmutable( $signal['date'], new \DateTimeZone( 'UTC' ) );
				if ( $date >= $bucket_start && $date < $bucket_end ) { ++$count; }
			}
			$series[] = array( 'label' => $bucket_start->format( 'j M' ), 'value' => $count );
		}
		return $series;
	}

	private function scheduled_runs(): array {
		$upcoming = array();
		foreach ( $this->runtime->schedules() as $workflow => $configuration ) {
			if ( empty( $configuration['enabled'] ) || 'manual' === $configuration['mode'] ) { continue; }
			$occurrences = CronSchedule::next_occurrences( $configuration, new \DateTimeImmutable( 'now', new \DateTimeZone( $configuration['timezone'] ) ), 1 );
			if ( $occurrences ) {
				$upcoming[] = array(
					'workflow' => 'products' === $workflow ? __( 'Scraping produits', 'dsa' ) : __( 'Scraping tendances', 'dsa' ),
					'date' => wp_date( 'D j M Y · H:i', $occurrences[0]->getTimestamp(), $occurrences[0]->getTimezone() ),
				);
			}
		}
		return $upcoming;
	}

	private function recent_runs( array $runs, array $runtime_runs ): array {
		foreach ( $runtime_runs as $run ) {
			$runs[] = array(
				'workflow' => isset( $run['label'] ) ? $run['label'] : __( 'Workflow', 'dsa' ),
				'debut' => isset( $run['created_at'] ) ? $run['created_at'] : '',
				'fin' => isset( $run['created_at'] ) ? $run['created_at'] : '',
				'statut' => isset( $run['status'] ) ? $run['status'] : 'simulated',
				'produits_trouves' => 0,
				'produits_ajoutes' => 0,
			);
		}
		usort( $runs, static function ( $left, $right ) { return strcmp( $right['debut'], $left['debut'] ); } );
		return array_slice( $runs, 0, 6 );
	}

	private function has_configured_provider( ProviderSettings $settings ): bool {
		foreach ( array( 'gemini', 'ollama', 'openrouter', 'nvidia' ) as $provider ) {
			$config = $settings->get( $provider );
			if ( ! empty( $config['default_model'] ) && ( ! empty( $config['has_key'] ) || ! empty( $config['host'] ) ) ) { return true; }
		}
		return false;
	}

	private function pipeline( array $metrics, bool $provider_ready, array $cron_health ): array {
		$cron_status = empty( $cron_health['action_scheduler'] ) ? 'error' : ( ! empty( $cron_health['wp_cron_disabled'] ) ? 'warning' : 'ok' );
		return array(
			array( 'label' => __( 'Découvrir', 'dsa' ), 'page' => 'dsa-trends', 'status' => $metrics['detected']['value'] ? 'ok' : 'inactive' ),
			array( 'label' => __( 'Décider', 'dsa' ), 'page' => 'dsa-settings', 'status' => $provider_ready ? 'ok' : 'warning' ),
			array( 'label' => __( 'Promouvoir', 'dsa' ), 'page' => 'dsa-products', 'status' => $metrics['shortlist']['value'] ? 'ok' : 'inactive' ),
			array( 'label' => __( 'Publier', 'dsa' ), 'page' => 'dsa-products', 'status' => $metrics['published']['value'] ? 'ok' : 'inactive' ),
			array( 'label' => __( 'Vendre', 'dsa' ), 'page' => 'dsa-orders', 'status' => $metrics['orders']['value'] ? 'ok' : 'inactive' ),
			array( 'label' => __( 'Soutenir', 'dsa' ), 'page' => 'dsa-support', 'status' => 'inactive' ),
			array( 'label' => __( 'Contrôler', 'dsa' ), 'page' => 'dsa-logs', 'status' => $cron_status ),
		);
	}

	private function alerts( array $metrics, bool $provider_ready, array $cron_health ): array {
		$alerts = array();
		if ( $metrics['pending'] > 0 ) { $alerts[] = array( 'type' => 'warning', 'text' => sprintf( __( '%d produit(s) attendent votre validation.', 'dsa' ), $metrics['pending'] ), 'page' => 'dsa-products' ); }
		if ( ! $provider_ready ) { $alerts[] = array( 'type' => 'warning', 'text' => __( 'Aucun provider IA prêt avec un modèle par défaut.', 'dsa' ), 'page' => 'dsa-settings' ); }
		if ( empty( $cron_health['action_scheduler'] ) || ! empty( $cron_health['wp_cron_disabled'] ) ) { $alerts[] = array( 'type' => 'error', 'text' => __( 'Vérifiez le déclenchement du cron et du runner Action Scheduler.', 'dsa' ), 'page' => 'dsa-workflows' ); }
		return $alerts;
	}
}