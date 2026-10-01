<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class EventDispatcher {
	const SENT_OPTION = 'dsa_notification_sent_events';

	private $center;
	private $mailer;

	public function __construct( NotificationCenter $center = null, DigestMailer $mailer = null ) {
		$this->center = null === $center ? new NotificationCenter() : $center;
		$this->mailer = null === $mailer ? new DigestMailer() : $mailer;
	}

	public function register() {
		add_action( 'dsa_run_completed', array( $this, 'run_completed' ), 10, 1 );
		add_action( 'dsa_run_failed', array( $this, 'run_failed' ), 10, 1 );
		add_action( 'dsa_product_pending', array( $this, 'product_pending' ), 10, 1 );
		add_action( 'dsa_provider_unavailable', array( $this, 'provider_unavailable' ), 10, 1 );
	}

	public function run_completed( $run ) {
		$this->dispatch( 'run_completed', is_array( $run ) ? $run : array(), __( 'Exécution terminée', 'dsa' ), __( 'Le workflow a terminé son exécution.', 'dsa' ), 'success' );
	}

	public function run_failed( $run ) {
		$this->dispatch( 'run_failed', is_array( $run ) ? $run : array(), __( 'Échec du workflow', 'dsa' ), __( 'Une étape du workflow a rencontré une erreur.', 'dsa' ), 'error' );
	}

	public function product_pending( $data ) {
		$this->dispatch( 'product_pending', is_array( $data ) ? $data : array(), __( 'Produit à valider', 'dsa' ), __( 'Un nouveau produit attend votre validation.', 'dsa' ), 'warning' );
	}

	public function provider_unavailable( $data ) {
		$this->dispatch( 'provider_unavailable', is_array( $data ) ? $data : array(), __( 'Provider IA indisponible', 'dsa' ), __( 'Un provider IA n’est pas disponible pour le moment.', 'dsa' ), 'warning' );
	}

	private function dispatch( $type, array $data, $title, $message, $level ) {
		$settings = NotificationSettings::get();
		$identity = '';
		$identity_field = '';
		foreach ( array( 'id', 'candidate_id', 'product_id', 'provider' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_scalar( $data[ $field ] ) && '' !== trim( (string) $data[ $field ] ) ) {
				$identity = sanitize_text_field( (string) $data[ $field ] );
				$identity_field = $field;
				break;
			}
		}
		$key = $type . ':' . ( '' !== $identity ? $identity : 'event' );
		$is_run_event = in_array( $type, array( 'run_completed', 'run_failed' ), true ) && 'id' === $identity_field;
		$is_product_event = 'product_pending' === $type && '' !== $identity;
		if ( ! $is_run_event && ! $is_product_event ) {
			$key .= ':' . (string) (int) floor( time() / 900 );
		}
		$sent = get_option( self::SENT_OPTION, array() );
		$sent = is_array( $sent ) ? $sent : array();
		if ( isset( $sent[ $key ] ) ) {
			return;
		}
		$sent[ $key ] = time();
		if ( count( $sent ) > 200 ) {
			$sent = array_slice( $sent, -200, null, true );
		}
		update_option( self::SENT_OPTION, $sent, false );
		$notification = $this->center->add( $type, $title, $message, $level );

		if ( ! empty( $data['demo'] ) ) {
			$this->center->update_delivery_status( $notification['id'], 'demo_suppressed' );
			return;
		}
		if ( ! in_array( $type, $settings['events'], true ) ) {
			$this->center->update_delivery_status( $notification['id'], 'not_selected' );
			return;
		}
		if ( ! $settings['email_enabled'] ) {
			$this->center->update_delivery_status( $notification['id'], 'disabled' );
			return;
		}

		if ( 'digest' === $settings['frequency'] && in_array( $type, array( 'run_completed', 'run_failed' ), true ) ) {
			$sent_successfully = $this->mailer->send( $data, $settings );
		} else {
			$sent_successfully = $this->mailer->send_alert( $title, $message, $settings );
		}
		$status = $sent_successfully ? 'sent' : 'failed';
		$this->center->update_delivery_status( $notification['id'], $status );
	}
}