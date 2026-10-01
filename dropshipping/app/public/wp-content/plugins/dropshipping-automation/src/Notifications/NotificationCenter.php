<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class NotificationCenter {
	const OPTION = 'dsa_notifications';
	const MAX_ITEMS = 200;

	public function add( $type, $title, $message, $level = 'info' ) {
		$type = in_array( $type, NotificationSettings::EVENTS, true ) ? $type : 'run_failed';
		$level = in_array( $level, array( 'info', 'warning', 'error', 'success' ), true ) ? $level : 'info';
		$items = $this->items();
		$notification = array(
			'id'         => substr( hash( 'sha256', uniqid( 'dsa-', true ) . wp_salt( 'auth' ) ), 0, 24 ),
			'type'       => $type,
			'level'      => $level,
			'title'      => substr( sanitize_text_field( (string) $title ), 0, 120 ),
			'message'    => substr( sanitize_text_field( (string) $message ), 0, 240 ),
			'created_at' => current_time( 'mysql', true ),
			'read_at'    => '',
		);
		array_unshift( $items, $notification );
		update_option( self::OPTION, array_slice( $items, 0, self::MAX_ITEMS ), false );
		return $notification;
	}

	public function latest( $limit = 10 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );
		return array_slice( $this->items(), 0, $limit );
	}

	public function unread_count() {
		return count( array_filter( $this->items(), static function ( $item ) {
			return empty( $item['read_at'] );
		} ) );
	}

	public function mark_read( $id ) {
		$id = sanitize_text_field( (string) $id );
		$items = $this->items();
		$found = false;
		foreach ( $items as &$item ) {
			if ( hash_equals( (string) $item['id'], $id ) ) {
				$item['read_at'] = current_time( 'mysql', true );
				$found = true;
				break;
			}
		}
		unset( $item );
		if ( $found ) {
			update_option( self::OPTION, $items, false );
		}
		return $found;
	}

	public function mark_all_read() {
		$items = $this->items();
		$now = current_time( 'mysql', true );
		foreach ( $items as &$item ) {
			if ( empty( $item['read_at'] ) ) {
				$item['read_at'] = $now;
			}
		}
		unset( $item );
		update_option( self::OPTION, $items, false );
	}

	public function update_delivery_status( $id, $status ) {
		$allowed = array( 'sent', 'failed', 'disabled', 'not_selected', 'digest_mode', 'demo_suppressed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		$items = $this->items();
		$updated = false;
		foreach ( $items as &$item ) {
			if ( isset( $item['id'] ) && hash_equals( (string) $item['id'], (string) $id ) ) {
				$item['delivery_status'] = $status;
				$updated = true;
				break;
			}
		}
		unset( $item );
		if ( $updated ) {
			update_option( self::OPTION, $items, false );
		}
		return $updated;
	}

	public function purge( $retention_days = null ) {
		$settings = NotificationSettings::get();
		$retention_days = null === $retention_days ? $settings['retention_days'] : max( 1, min( 365, absint( $retention_days ) ) );
		$cutoff = time() - ( $retention_days * DAY_IN_SECONDS );
		$items = $this->items();
		$kept = array_values( array_filter( $items, static function ( $item ) use ( $cutoff ) {
			$timestamp = strtotime( ( $item['created_at'] ?? '' ) . ' UTC' );
			return false !== $timestamp && $timestamp >= $cutoff;
		} ) );
		update_option( self::OPTION, $kept, false );
		return count( $items ) - count( $kept );
	}

	private function items() {
		$items = get_option( self::OPTION, array() );
		return is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
	}
}