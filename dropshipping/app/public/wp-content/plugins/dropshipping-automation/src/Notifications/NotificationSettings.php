<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class NotificationSettings {
	const OPTION = 'dsa_notification_settings';
	const EVENTS = array( 'run_completed', 'run_failed', 'product_pending', 'provider_unavailable' );
	const WEBHOOKS = array( 'slack', 'telegram' );

	public static function defaults() {
		return array(
			'email_enabled'  => false,
			'recipient_mode' => 'admin',
			'email'          => '',
			'webhooks'       => array( 'slack' => '', 'telegram' => '' ),
			'events'         => array( 'run_completed', 'run_failed' ),
			'frequency'      => 'immediate',
			'retention_days' => 30,
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $saved ) ? $saved : array() );
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$events = isset( $input['events'] ) && is_array( $input['events'] ) ? $input['events'] : $defaults['events'];
		$events = array_values( array_unique( array_filter( $events, static function ( $event ) {
			return is_string( $event ) && in_array( $event, self::EVENTS, true );
		} ) ) );
		$webhooks = isset( $input['webhooks'] ) && is_array( $input['webhooks'] ) ? $input['webhooks'] : array();
		$clean_webhooks = array();
		foreach ( self::WEBHOOKS as $provider ) {
			$clean_webhooks[ $provider ] = self::https_url( $webhooks[ $provider ] ?? '' );
		}
		$recipient_mode = isset( $input['recipient_mode'] ) && 'custom' === $input['recipient_mode'] ? 'custom' : 'admin';
		$email = isset( $input['email'] ) && is_string( $input['email'] ) ? trim( $input['email'] ) : '';
		$email = filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
		$frequency = isset( $input['frequency'] ) && in_array( $input['frequency'], array( 'immediate', 'digest' ), true )
			? $input['frequency']
			: $defaults['frequency'];
		$retention = isset( $input['retention_days'] ) && is_numeric( $input['retention_days'] )
			? (int) $input['retention_days']
			: $defaults['retention_days'];

		return array(
			'email_enabled'  => self::is_truthy( $input['email_enabled'] ?? false ),
			'recipient_mode' => $recipient_mode,
			'email'          => $email,
			'webhooks'       => $clean_webhooks,
			'events'         => $events,
			'frequency'      => $frequency,
			'retention_days' => max( 1, min( 365, $retention ) ),
		);
	}

	public static function recipients( array $settings = null ) {
		$settings = null === $settings ? self::get() : self::sanitize( $settings );
		if ( empty( $settings['email_enabled'] ) ) {
			return array();
		}
		$email = 'custom' === $settings['recipient_mode']
			? $settings['email']
			: (string) get_option( 'admin_email', '' );
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? array( $email ) : array();
	}

	private static function is_truthy( $value ) {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}

	private static function https_url( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		$url = trim( $value );
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return '';
		}
		$host = strtolower( rtrim( trim( $parts['host'], '[]' ), '.' ) );
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) || 'localhost' === $host || preg_match( '/\.(?:localhost|local|internal)$/', $host ) ) {
			return '';
		}
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}
}