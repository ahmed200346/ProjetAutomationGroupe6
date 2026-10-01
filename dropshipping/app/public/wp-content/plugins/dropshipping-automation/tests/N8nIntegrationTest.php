<?php

use DSA\Integrations\N8nRunStore;
use DSA\Integrations\N8nWebhookClient;
use DSA\Integrations\N8nWorkflowSettings;
use DSA\Integrations\N8nWorkflowRunner;
use DSA\REST\N8nCallbackController;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'DSA_PLUGIN_DIR' ) ) {
	define( 'DSA_PLUGIN_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

require_once DSA_PLUGIN_DIR . 'src/autoload.php';

if ( ! defined( 'DSA_N8N_CALLBACK_TOKEN' ) ) {
	define( 'DSA_N8N_CALLBACK_TOKEN', str_repeat( 'c', 40 ) );
}

final class N8nIntegrationTest extends TestCase {
	public function test_product_settings_are_allowlisted_and_bounded(): void {
		$settings = N8nWorkflowSettings::sanitize(
			array(
				'query' => '<b> Wireless headphones </b>',
				'marketplace' => 'unknown-store',
				'limit' => 500,
			)
		);

		$this->assertSame( 'Wireless headphones', $settings['query'] );
		$this->assertSame( 'aliexpress', $settings['marketplace'] );
		$this->assertSame( 20, $settings['limit'] );
	}

	public function test_n8n_client_requires_https_except_for_local_development(): void {
		$token = str_repeat( 't', 40 );
		$callback = 'http://host.docker.internal:8080/wp-json/dsa/v1/workflows/callback';
		$this->assertTrue( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/test', $token, null, true, $callback ) )->is_configured() );
		$this->assertTrue( ( new N8nWebhookClient( 'http://localhost:5678/webhook/test', $token, null, true, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'http://n8n.example.test/webhook/test', $token, null, true, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/test', 'short', null, true, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/test', $token, null, false, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/test', $token, null, true, 'http://public.example.test/callback' ) )->is_configured() );
	}

	public function test_n8n_client_posts_safe_payload_and_secret_header_using_mock_transport(): void {
		$captured = array();
		$client = new N8nWebhookClient(
			'https://n8n.example.test/webhook/test',
			str_repeat( 's', 40 ),
			static function ( $url, $args ) use ( &$captured ) {
				$captured = array( 'url' => $url, 'args' => $args );
				return array( 'response' => array( 'code' => 200 ) );
			},
			true,
			'http://host.docker.internal:8080/wp-json/dsa/v1/workflows/callback'
		);

		$result = $client->dispatch( array( 'run_id' => 'run-123', 'query' => 'cable usb-c', 'marketplace' => 'aliexpress', 'limit' => 5 ) );
		$body = json_decode( $captured['args']['body'], true );

		$this->assertSame( true, $result['accepted'] );
		$this->assertSame( 'run-123', $body['run_id'] );
		$this->assertSame( 'cable usb-c', $body['query'] );
		$this->assertSame( 'aliexpress', $body['marketplace'] );
		$this->assertSame( 5, $body['limit'] );
		$this->assertSame( 'http://host.docker.internal:8080/wp-json/dsa/v1/workflows/callback', $body['callback_url'] );
		$this->assertSame( str_repeat( 's', 40 ), $captured['args']['headers'][ N8nWebhookClient::TOKEN_HEADER ] );
		$this->assertSame( 0, $captured['args']['redirection'] );
	}

	public function test_n8n_run_store_keeps_state_and_limits_history(): void {
		delete_option( N8nRunStore::OPTION );
		$store = new N8nRunStore();
		$store->create( 'run-123', array( 'query' => 'lamp', 'marketplace' => 'aliexpress', 'limit' => 4 ), 'manual' );
		$store->update( 'run-123', array( 'status' => 'running' ) );

		$this->assertSame( 'running', $store->find( 'run-123' )['status'] );
		$this->assertSame( 'lamp', $store->find( 'run-123' )['query'] );
		$this->assertSame( 1, count( $store->latest() ) );
	}

	public function test_n8n_runner_marks_run_running_after_mocked_webhook_acceptance(): void {
		delete_option( N8nRunStore::OPTION );
		$store = new N8nRunStore();
		$store->create( 'run-accepted', array( 'query' => 'lamp', 'marketplace' => 'aliexpress', 'limit' => 4 ), 'manual' );
		$client = new N8nWebhookClient( 'https://n8n.example.test/webhook/test', str_repeat( 'x', 40 ), static function () { return array( 'response' => array( 'code' => 200 ) ); }, true, 'https://store.example.test/wp-json/dsa/v1/workflows/callback' );

		( new N8nWorkflowRunner( $client, $store ) )->dispatch( 'run-accepted', 'products', 'manual' );

		$this->assertSame( 'running', $store->find( 'run-accepted' )['status'] );
	}

	public function test_n8n_runner_records_failed_dispatch_without_exposing_transport_details(): void {
		delete_option( N8nRunStore::OPTION );
		$store = new N8nRunStore();
		$store->create( 'run-rejected', array( 'query' => 'lamp', 'marketplace' => 'aliexpress', 'limit' => 4 ), 'manual' );
		$client = new N8nWebhookClient( 'https://n8n.example.test/webhook/test', str_repeat( 'x', 40 ), static function () { return array( 'response' => array( 'code' => 401 ) ); }, true, 'https://store.example.test/wp-json/dsa/v1/workflows/callback' );

		( new N8nWorkflowRunner( $client, $store ) )->dispatch( 'run-rejected', 'products', 'manual' );

		$this->assertSame( 'dispatch_failed', $store->find( 'run-rejected' )['status'] );
		$this->assertStringNotContainsString( 'token', $store->find( 'run-rejected' )['result'] );
	}

	public function test_late_callback_can_reconcile_an_uncertain_dispatch_failure(): void {
		delete_option( N8nRunStore::OPTION );
		$store = new N8nRunStore();
		$store->create( 'run-late-callback', array( 'query' => 'lamp', 'marketplace' => 'aliexpress', 'limit' => 4 ), 'manual' );
		$transport_error = new WP_Error( 'timeout', 'private transport detail' );
		$client = new N8nWebhookClient( 'https://n8n.example.test/webhook/test', str_repeat( 'x', 40 ), static function () use ( $transport_error ) { return $transport_error; }, true, 'https://store.example.test/wp-json/dsa/v1/workflows/callback' );
		$runner = new N8nWorkflowRunner( $client, $store );
		$runner->dispatch( 'run-late-callback', 'products', 'manual' );
		$this->assertSame( 'dispatch_failed', $store->find( 'run-late-callback' )['status'] );

		$controller = new N8nCallbackController( $store );
		$this->assertFalse( $controller->check_permission( new WP_REST_Request( 'POST', '/dsa/v1/workflows/callback' ) ) );
		$request = new WP_REST_Request( 'POST', '/dsa/v1/workflows/callback' );
		$request->set_header( 'X-DSA-Callback-Token', DSA_N8N_CALLBACK_TOKEN );
		$request->set_body( wp_json_encode( array( 'run_id' => 'run-late-callback', 'status' => 'completed', 'counts' => array( 'found' => 5, 'scored' => 2 ) ) ) );
		$response = $controller->receive( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'completed', $store->find( 'run-late-callback' )['status'] );
	}

	public function test_callback_requires_token_and_records_only_aggregated_outcome(): void {
		delete_option( N8nRunStore::OPTION );
		$store = new N8nRunStore();
		$store->create( 'run-callback', array( 'query' => 'lamp', 'marketplace' => 'aliexpress', 'limit' => 4 ), 'manual' );
		$controller = new N8nCallbackController( $store );
		$request = new WP_REST_Request( 'POST', '/dsa/v1/workflows/callback' );
		$request->set_header( 'X-DSA-Callback-Token', DSA_N8N_CALLBACK_TOKEN );
		$request->set_body( wp_json_encode( array( 'run_id' => 'run-callback', 'status' => 'completed', 'counts' => array( 'found' => 7, 'scored' => 3, 'created' => 0 ) ) ) );

		$response = $controller->receive( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'completed', $store->find( 'run-callback' )['status'] );
		$this->assertSame( 7, $store->find( 'run-callback' )['counts']['found'] );
		$this->assertArrayNotHasKey( 'products', $store->find( 'run-callback' ) );
	}
}
