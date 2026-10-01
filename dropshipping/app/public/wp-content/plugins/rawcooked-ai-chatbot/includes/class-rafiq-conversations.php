<?php
/**
 * Conversation log: stores transcripts, powers the admin analytics tab,
 * captures leads and enforces retention (GDPR-friendly auto-purge).
 *
 * @package RawCooked_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rafiq_Conversations {

	const TABLE = 'rafiq_conversations';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_key VARCHAR(64) NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				lead_name VARCHAR(100) NOT NULL DEFAULT '',
				lead_email VARCHAR(190) NOT NULL DEFAULT '',
				first_message VARCHAR(255) NOT NULL DEFAULT '',
				messages LONGTEXT NOT NULL,
				msg_count INT UNSIGNED NOT NULL DEFAULT 0,
				no_context_count INT UNSIGNED NOT NULL DEFAULT 0,
				started_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY session (session_key),
				KEY updated (updated_at)
			) {$charset};"
		);
	}

	/**
	 * Append one exchange (user message + bot reply) to a session's transcript.
	 *
	 * @param string $session_key Client-generated session id.
	 * @param int    $user_id     WP user id (0 = anonymous).
	 * @param string $user_msg    Visitor message.
	 * @param string $reply       Bot reply.
	 * @param bool   $no_context  True when RAG found nothing for this question.
	 * @param array  $lead        Optional [ 'name' => ..., 'email' => ... ].
	 */
	public static function log_turn( $session_key, $user_id, $user_msg, $reply, $no_context, array $lead = array() ) {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql', true );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, messages, msg_count, no_context_count, lead_name, lead_email FROM {$table} WHERE session_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_key
			),
			ARRAY_A
		);

		$turns = array(
			array(
				'r' => 'u',
				'c' => $user_msg,
				't' => time(),
			),
			array(
				'r' => 'a',
				'c' => $reply,
				'x' => $no_context ? 1 : 0,
			),
		);

		if ( $row ) {
			$messages = json_decode( $row['messages'], true );
			$messages = is_array( $messages ) ? $messages : array();
			$messages = array_merge( $messages, $turns );
			// Cap transcript size defensively.
			$messages = array_slice( $messages, -200 );

			$update = array(
				'messages'         => wp_json_encode( $messages ),
				'msg_count'        => (int) $row['msg_count'] + 2,
				'no_context_count' => (int) $row['no_context_count'] + ( $no_context ? 1 : 0 ),
				'updated_at'       => $now,
			);
			if ( ! empty( $lead['email'] ) && '' === $row['lead_email'] ) {
				$update['lead_email'] = $lead['email'];
				$update['lead_name']  = isset( $lead['name'] ) ? $lead['name'] : '';
			}
			$wpdb->update( $table, $update, array( 'id' => $row['id'] ) );
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'session_key'      => $session_key,
				'user_id'          => $user_id,
				'lead_name'        => isset( $lead['name'] ) ? $lead['name'] : '',
				'lead_email'       => isset( $lead['email'] ) ? $lead['email'] : '',
				'first_message'    => mb_substr( $user_msg, 0, 255 ),
				'messages'         => wp_json_encode( $turns ),
				'msg_count'        => 2,
				'no_context_count' => $no_context ? 1 : 0,
				'started_at'       => $now,
				'updated_at'       => $now,
			)
		);
	}

	/**
	 * Dashboard stats for the last 7 days + totals.
	 */
	public static function stats() {
		global $wpdb;
		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$week = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS conversations,
						COALESCE(SUM(msg_count), 0) AS messages,
						COALESCE(SUM(no_context_count), 0) AS no_context
				FROM {$table} WHERE updated_at >= %s",
				$since
			),
			ARRAY_A
		);
		$totals = $wpdb->get_row(
			"SELECT COUNT(*) AS conversations,
					SUM(CASE WHEN lead_email != '' THEN 1 ELSE 0 END) AS leads
			FROM {$table}",
			ARRAY_A
		);
		// phpcs:enable

		$week_msgs    = (int) $week['messages'];
		$week_answers = max( 1, (int) ( $week_msgs / 2 ) );

		return array(
			'week_conversations' => (int) $week['conversations'],
			'week_messages'      => $week_msgs,
			'week_no_context'    => round( 100 * (int) $week['no_context'] / $week_answers ),
			'total_conversations' => (int) $totals['conversations'],
			'total_leads'         => (int) $totals['leads'],
		);
	}

	public static function recent( $limit = 25 ) {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, lead_name, lead_email, first_message, msg_count, no_context_count, started_at
				FROM {$table} ORDER BY updated_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_transcript( $id ) {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, messages, lead_name, lead_email, started_at FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['messages'] = json_decode( $row['messages'], true );
		$row['messages'] = is_array( $row['messages'] ) ? $row['messages'] : array();
		return $row;
	}

	public static function leads() {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			"SELECT lead_name, lead_email, first_message, started_at FROM {$table}
			WHERE lead_email != '' ORDER BY started_at DESC LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function delete_all() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function drop() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Nightly retention purge.
	 */
	public static function cron_purge() {
		global $wpdb;
		$days  = (int) Rafiq_Settings::get( 'retention_days' );
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}
}
