<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class JournalMaintenance {
	const ACTION = 'dsa_journal_purge';
	const GROUP = 'dsa-maintenance';

	public function register() {
		add_action( 'init', array( $this, 'ensure_schedule' ) );
		add_action( self::ACTION, array( $this, 'purge_expired' ) );
	}

	public function ensure_schedule() {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$exists = function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( self::ACTION, array(), self::GROUP );
			if ( ! $exists ) {
				as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::ACTION, array(), self::GROUP, true );
			}
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::ACTION ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::ACTION );
		}
	}

	public function purge_expired() {
		return ( new NotificationCenter() )->purge( NotificationSettings::get()['retention_days'] );
	}

	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION, null, self::GROUP );
		}
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::ACTION );
		}
	}
}