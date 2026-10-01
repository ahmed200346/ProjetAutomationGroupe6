<?php

use DSA\Notifications\DigestBuilder;
use DSA\Notifications\DigestMailer;
use DSA\Notifications\EventDispatcher;
use DSA\Notifications\NotificationCenter;
use DSA\Notifications\NotificationSettings;
use DSA\Integrations\N8nWebhookClient;
use DSA\Integrations\N8nWorkflowSettings;
use DSA\Storage\ProviderSettings;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'DSA_PLUGIN_DIR' ) ) {
	define( 'DSA_PLUGIN_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

require_once DSA_PLUGIN_DIR . 'src/autoload.php';

final class DigestTest extends TestCase {
	public function test_notification_settings_allow_only_known_values_and_https_webhooks(): void {
		$settings = NotificationSettings::sanitize(
			array(
				'email_enabled' => '1',
				'recipient_mode' => 'unknown',
				'email' => 'owner@example.test',
				'webhooks' => array( 'slack' => 'http://hooks.example.test/a', 'telegram' => 'https://api.example.test/hook' ),
				'events' => array( 'run_completed', 'arbitrary_hook' ),
				'frequency' => 'instant',
				'retention_days' => 900,
			)
		);

		$this->assertTrue( $settings['email_enabled'] );
		$this->assertSame( 'admin', $settings['recipient_mode'] );
		$this->assertSame( '', $settings['webhooks']['slack'] );
		$this->assertSame( 'https://api.example.test/hook', $settings['webhooks']['telegram'] );
		$this->assertSame( array( 'run_completed' ), $settings['events'] );
		$this->assertSame( 'immediate', $settings['frequency'] );
		$this->assertSame( 365, $settings['retention_days'] );
	}

	public function test_n8n_workflow_settings_allow_only_supported_search_inputs(): void {
		$settings = N8nWorkflowSettings::sanitize(
			array(
				'query' => '<b>Wireless headphones</b>',
				'marketplace' => 'unsupported',
				'limit' => 500,
			)
		);

		$this->assertSame( 'Wireless headphones', $settings['query'] );
		$this->assertSame( 'aliexpress', $settings['marketplace'] );
		$this->assertSame( 20, $settings['limit'] );
	}

	public function test_n8n_client_requires_secure_url_and_long_token(): void {
		$callback = 'https://store.example.test/wp-json/dsa/v1/workflows/callback';
		$this->assertTrue( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/abc', str_repeat( 'x', 40 ), null, true, $callback ) )->is_configured() );
		$this->assertTrue( ( new N8nWebhookClient( 'http://localhost:5678/webhook/abc', str_repeat( 'x', 40 ), null, true, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'http://n8n.example.test/webhook/abc', str_repeat( 'x', 40 ), null, true, $callback ) )->is_configured() );
		$this->assertFalse( ( new N8nWebhookClient( 'https://n8n.example.test/webhook/abc', 'short', null, true, $callback ) )->is_configured() );
	}

	public function test_n8n_client_posts_allowlisted_payload_with_header_auth(): void {
		$captured = array();
		$client = new N8nWebhookClient(
			'https://n8n.example.test/webhook/abc',
			str_repeat( 's', 40 ),
			static function ( $url, $args ) use ( &$captured ) {
				$captured = array( 'url' => $url, 'args' => $args );
				return array( 'response' => array( 'code' => 200 ) );
			},
			true,
			'https://store.example.test/wp-json/dsa/v1/workflows/callback'
		);
		$result = $client->dispatch( array( 'run_id' => 'run-123', 'query' => 'wireless headphones', 'marketplace' => 'aliexpress', 'limit' => 10 ) );
		$sent = json_decode( $captured['args']['body'], true );

		$this->assertSame( true, $result['accepted'] );
		$this->assertSame( 'run-123', $sent['run_id'] );
		$this->assertSame( 'aliexpress', $sent['marketplace'] );
		$this->assertSame( 'https://store.example.test/wp-json/dsa/v1/workflows/callback', $sent['callback_url'] );
		$this->assertSame( str_repeat( 's', 40 ), $captured['args']['headers'][ N8nWebhookClient::TOKEN_HEADER ] );
		$this->assertSame( 0, $captured['args']['redirection'] );
	}

	public function test_notification_webhooks_reject_local_https_hosts(): void {
		$settings = NotificationSettings::sanitize(
			array(
				'webhooks' => array(
					'slack' => 'https://127.0.0.1/hooks/test',
					'telegram' => 'https://[::1]/bot/test',
				),
			)
		);

		$this->assertSame( '', $settings['webhooks']['slack'] );
		$this->assertSame( '', $settings['webhooks']['telegram'] );
	}

	public function test_provider_settings_ignore_nested_option_values_without_php_warnings(): void {
		update_option(
			ProviderSettings::OPTION,
			array(
				'openrouter' => array(
					'host' => array( 'unexpected' ),
					'models' => array( array( 'id' => array( 'nested' ), 'name' => 'Invalid' ), array( 'id' => 'openai/model', 'name' => array( 'nested' ) ) ),
					'enabled' => array( array( 'nested' ), 'openai/model' ),
					'default_model' => array( 'nested' ),
					'api_key' => array( 'not-a-secret-string' ),
				),
			)
		);

		$config = ( new ProviderSettings() )->get( 'openrouter' );

		$this->assertSame( 'http://localhost:11434', $config['host'] );
		$this->assertSame( array( array( 'id' => 'openai/model' ) ), $config['models'] );
		$this->assertSame( array( 'openai/model' ), $config['enabled'] );
		$this->assertSame( '', $config['default_model'] );
		$this->assertFalse( $config['has_key'] );
	}

	public function test_digest_builder_normalizes_counts_and_does_not_copy_raw_errors(): void {
		$digest = ( new DigestBuilder() )->build(
			array(
				'id' => 'run-12',
				'workflow' => 'social_scan',
				'counts' => array( 'found' => '8', 'average_score' => 140 ),
				'stages' => array( array( 'stage' => 'scraping', 'status' => 'failed', 'error' => 'secret token value' ) ),
			)
		);

		$this->assertSame( 8, $digest['counts']['found'] );
		$this->assertSame( 100, $digest['counts']['average_score'] );
		$this->assertSame( 1, $digest['counts']['errors'] );
		$this->assertSame( 'Collecte sociale', $digest['errors'][0]['label'] );
		$this->assertArrayNotHasKey( 'error', $digest['errors'][0] );
	}

	public function test_html_digest_escapes_untrusted_run_identifiers_and_uses_tables(): void {
		$html = ( new DigestBuilder() )->render_html( array( 'id' => '<script>secret</script>', 'workflow' => 'products' ) );

		$this->assertStringContainsString( '<table role="presentation"', $html );
		$this->assertStringContainsString( 'secret', $html );
		$this->assertStringNotContainsString( '<script>secret</script>', $html );
	}

	public function test_notification_center_tracks_unread_and_marks_an_item_read(): void {
		delete_option( NotificationCenter::OPTION );
		$center = new NotificationCenter();
		$notification = $center->add( 'run_failed', '<b>Échec</b>', 'Une étape a échoué.', 'error' );

		$this->assertSame( 1, $center->unread_count() );
		$this->assertTrue( $center->mark_read( $notification['id'] ) );
		$this->assertSame( 0, $center->unread_count() );
		$this->assertSame( 'Échec', $center->latest()[0]['title'] );
	}

	public function test_notification_center_purges_expired_items_and_keeps_recent_ones(): void {
		update_option(
			NotificationCenter::OPTION,
			array(
				array( 'id' => 'old', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 40 * DAY_IN_SECONDS ) ) ),
				array( 'id' => 'recent', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ),
			)
		);

		$this->assertSame( 1, ( new NotificationCenter() )->purge( 30 ) );
		$this->assertSame( 'recent', ( new NotificationCenter() )->latest()[0]['id'] );
	}

	public function test_test_digest_mail_uses_wp_mail_and_html_content_type(): void {
		update_option( NotificationSettings::OPTION, array(
			'email_enabled' => true,
			'recipient_mode' => 'custom',
			'email' => 'owner@example.test',
		) );
		$mails = array();
		$mailer = new DigestMailer( null, static function ( $to, $subject, $message, $headers ) use ( &$mails ) {
			$mails[] = compact( 'to', 'subject', 'message', 'headers' );
			return true;
		} );

		$this->assertTrue( $mailer->send_test() );
		$this->assertSame( 'owner@example.test', $mails[0]['to'] );
		$this->assertStringContainsString( 'text/html', $mails[0]['headers'][0] );
	}

	public function test_event_dispatcher_deduplicates_and_suppresses_email_for_demo_runs(): void {
		update_option( NotificationSettings::OPTION, array(
			'email_enabled' => true,
			'recipient_mode' => 'custom',
			'email' => 'owner@example.test',
			'frequency' => 'immediate',
		) );
		delete_option( NotificationCenter::OPTION );
		delete_option( EventDispatcher::SENT_OPTION );
		$mails = array();
		$mailer = new DigestMailer( null, static function ( $to, $subject, $message, $headers ) use ( &$mails ) { $mails[] = compact( 'to', 'subject', 'message', 'headers' ); return true; } );
		$dispatcher = new EventDispatcher( new NotificationCenter(), $mailer );
		$run = array( 'id' => 'demo-run-1', 'workflow' => 'products', 'demo' => true );

		$dispatcher->run_completed( $run );
		$dispatcher->run_completed( $run );

		$this->assertSame( 1, ( new NotificationCenter() )->unread_count() );
		$this->assertCount( 0, $mails );
		$this->assertSame( 'demo_suppressed', ( new NotificationCenter() )->latest()[0]['delivery_status'] );
	}

	public function test_digest_frequency_sends_one_run_digest_for_a_real_event(): void {
		update_option( NotificationSettings::OPTION, array(
			'email_enabled' => true,
			'recipient_mode' => 'custom',
			'email' => 'owner@example.test',
			'frequency' => 'digest',
		) );
		delete_option( EventDispatcher::SENT_OPTION );
		$mails = array();
		$mailer = new DigestMailer( null, static function ( $to, $subject, $message, $headers ) use ( &$mails ) { $mails[] = compact( 'to', 'subject', 'message', 'headers' ); return true; } );

		( new EventDispatcher( new NotificationCenter(), $mailer ) )->run_completed( array( 'id' => 'real-run-1', 'workflow' => 'products', 'counts' => array( 'found' => 4 ) ) );

		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'Produits trouvés', $mails[0]['message'] );
		$this->assertSame( 'sent', ( new NotificationCenter() )->latest()[0]['delivery_status'] );
	}

	public function test_digest_frequency_still_sends_immediate_product_alerts(): void {
		update_option( NotificationSettings::OPTION, array(
			'email_enabled' => true,
			'recipient_mode' => 'custom',
			'email' => 'owner@example.test',
			'frequency' => 'digest',
			'events' => array( 'product_pending' ),
		) );
		delete_option( NotificationCenter::OPTION );
		delete_option( EventDispatcher::SENT_OPTION );
		$mails = array();
		$mailer = new DigestMailer( null, static function ( $to, $subject, $message, $headers ) use ( &$mails ) { $mails[] = compact( 'to', 'subject', 'message', 'headers' ); return true; } );

		( new EventDispatcher( new NotificationCenter(), $mailer ) )->product_pending( array( 'product_id' => 3 ) );

		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'nouveau produit attend', $mails[0]['message'] );
		$this->assertSame( 'sent', ( new NotificationCenter() )->latest()[0]['delivery_status'] );
	}

	public function test_provider_alerts_are_rate_limited_without_a_run_identifier(): void {
		delete_option( NotificationSettings::OPTION );
		delete_option( NotificationCenter::OPTION );
		delete_option( EventDispatcher::SENT_OPTION );
		$dispatcher = new EventDispatcher();
		$dispatcher->provider_unavailable( array( 'provider' => 'gemini' ) );
		$dispatcher->provider_unavailable( array( 'provider' => 'gemini' ) );

		$this->assertSame( 1, ( new NotificationCenter() )->unread_count() );
	}
}