<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class DigestMailer {
	private $builder;
	private $sender;

	public function __construct( DigestBuilder $builder = null, $sender = null ) {
		$this->builder = null === $builder ? new DigestBuilder() : $builder;
		$this->sender = is_callable( $sender ) ? $sender : 'wp_mail';
	}

	public function send( array $run, array $settings = null ) {
		$recipients = NotificationSettings::recipients( $settings );
		if ( ! $recipients ) {
			return false;
		}
		$subject = sprintf( __( 'Dropshipping · résumé du workflow %s', 'dsa' ), sanitize_text_field( (string) ( $run['id'] ?? '' ) ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent = true;
		foreach ( $recipients as $recipient ) {
			$sent = call_user_func( $this->sender, $recipient, $subject, $this->builder->render_html( $run ), $headers ) && $sent;
		}
		return $sent;
	}

	public function send_alert( $subject, $message, array $settings = null ) {
		$recipients = NotificationSettings::recipients( $settings );
		if ( ! $recipients ) {
			return false;
		}
		$html = '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;color:#14212b"><table role="presentation" cellspacing="0" cellpadding="0" style="max-width:620px;border-collapse:collapse"><tr><td style="padding:18px;background:#183b3b;color:#fff"><strong>' . esc_html( __( 'Dropshipping Automation', 'dsa' ) ) . '</strong></td></tr><tr><td style="padding:20px">' . esc_html( $message ) . '</td></tr></table></body></html>';
		$sent = true;
		foreach ( $recipients as $recipient ) {
			$sent = call_user_func( $this->sender, $recipient, sanitize_text_field( $subject ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) ) && $sent;
		}
		return $sent;
	}

	public function send_test( array $settings = null ) {
		$run = array(
			'id' => __( 'aperçu de test', 'dsa' ),
			'workflow' => 'products',
			'status' => 'completed',
			'counts' => array( 'found' => 12, 'scored' => 8, 'created' => 3, 'duplicates' => 2, 'errors' => 0, 'average_score' => 74 ),
			'stages' => array(),
		);
		return $this->send( $run, $settings );
	}
}