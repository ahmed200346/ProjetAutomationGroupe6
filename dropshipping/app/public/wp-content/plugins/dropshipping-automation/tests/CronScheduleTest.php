<?php

use DSA\Scheduler\CronSchedule;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR );
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

require_once dirname( __DIR__ ) . '/src/Scheduler/CronSchedule.php';

final class CronScheduleTest extends TestCase {
	public function test_modes_create_their_expected_internal_expressions(): void {
		$cases = array(
			array( array( 'mode' => 'manual' ), '@manual' ),
			array( array( 'mode' => 'interval', 'amount' => 15, 'unit' => 'minutes' ), '@every:15:minutes' ),
			array( array( 'mode' => 'interval', 'amount' => 2, 'unit' => 'days' ), '@every:2:days' ),
			array( array( 'mode' => 'daily', 'time' => '08:30' ), '30 8 * * *' ),
			array( array( 'mode' => 'once', 'once_at' => '2026-09-29T08:30' ), '@once:2026-09-29:08:30' ),
			array( array( 'mode' => 'advanced', 'cron' => '30 8 * * 1,4' ), '30 8 * * 1,4' ),
		);

		foreach ( $cases as $case ) {
			$config = CronSchedule::normalize( $case[0], 'UTC' );
			$this->assertSame( $case[1], $config['expression'] );
		}
	}

	public function test_weekly_mode_creates_a_five_field_cron_expression(): void {
		$config = CronSchedule::normalize(
			array( 'mode' => 'weekly', 'weekdays' => array( 1, 4 ), 'time' => '08:30', 'timezone' => 'Europe/Paris' ),
			'UTC'
		);

		$this->assertSame( '30 8 * * 1,4', $config['expression'] );
		$from = new DateTimeImmutable( '2026-09-29 00:00:00', new DateTimeZone( 'Europe/Paris' ) );
		$occurrences = CronSchedule::next_occurrences( $config, $from, 1 );
		$this->assertSame( '2026-10-01 08:30', $occurrences[0]->format( 'Y-m-d H:i' ) );
	}

	public function test_monthly_modes_encode_last_day_and_ordinal_weekday(): void {
		$last_day = CronSchedule::normalize( array( 'mode' => 'monthly', 'time' => '03:00', 'monthly_type' => 'day', 'last_day' => true ), 'UTC' );
		$first_monday = CronSchedule::normalize( array( 'mode' => 'monthly', 'time' => '03:00', 'monthly_type' => 'weekday', 'ordinal' => 1, 'weekday' => 1 ), 'UTC' );

		$this->assertSame( '0 3 L * *', $last_day['expression'] );
		$this->assertSame( '0 3 * * 1#1', $first_monday['expression'] );
	}

	public function test_advanced_cron_accepts_standard_fields_and_rejects_extensions(): void {
		$config = CronSchedule::normalize( array( 'mode' => 'advanced', 'cron' => '30 8 * * 1,4' ), 'UTC' );

		$this->assertSame( '30 8 * * 1,4', $config['expression'] );
		$this->expectException( InvalidArgumentException::class );
		CronSchedule::normalize( array( 'mode' => 'advanced', 'cron' => '0 3 * * 1#1' ), 'UTC' );
	}

	public function test_daily_occurrences_skip_a_nonexistent_dst_local_time(): void {
		$config = CronSchedule::normalize( array( 'mode' => 'daily', 'time' => '02:30', 'timezone' => 'Europe/Paris' ), 'UTC' );
		$from = new DateTimeImmutable( '2026-03-28 00:00:00', new DateTimeZone( 'Europe/Paris' ) );
		$occurrences = CronSchedule::next_occurrences( $config, $from );

		$this->assertSame(
			array( '2026-03-28 02:30', '2026-03-30 02:30', '2026-03-31 02:30', '2026-04-01 02:30', '2026-04-02 02:30' ),
			array_map( static function ( $date ) { return $date->format( 'Y-m-d H:i' ); }, $occurrences )
		);
	}

	public function test_monthly_day_31_skips_short_months(): void {
		$config = CronSchedule::normalize( array( 'mode' => 'monthly', 'time' => '03:00', 'monthly_type' => 'day', 'day' => 31 ), 'UTC' );
		$from = new DateTimeImmutable( '2026-01-01 00:00:00', new DateTimeZone( 'UTC' ) );
		$occurrences = CronSchedule::next_occurrences( $config, $from );

		$this->assertSame(
			array( '2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31', '2026-08-31' ),
			array_map( static function ( $date ) { return $date->format( 'Y-m-d' ); }, $occurrences )
		);
	}

	public function test_last_day_of_month_is_calculated_for_short_months(): void {
		$config = CronSchedule::normalize( array( 'mode' => 'monthly', 'time' => '03:00', 'monthly_type' => 'day', 'last_day' => true ), 'UTC' );
		$from = new DateTimeImmutable( '2026-02-01 00:00:00', new DateTimeZone( 'UTC' ) );
		$occurrences = CronSchedule::next_occurrences( $config, $from, 3 );

		$this->assertSame(
			array( '2026-02-28', '2026-03-31', '2026-04-30' ),
			array_map( static function ( $date ) { return $date->format( 'Y-m-d' ); }, $occurrences )
		);
	}

	public function test_repeated_dst_time_runs_once_at_its_first_occurrence(): void {
		$config = CronSchedule::normalize( array( 'mode' => 'daily', 'time' => '02:30', 'timezone' => 'Europe/Paris' ), 'UTC' );
		$from = new DateTimeImmutable( '2026-10-24 00:00:00', new DateTimeZone( 'Europe/Paris' ) );
		$occurrences = CronSchedule::next_occurrences( $config, $from );

		$this->assertSame( '2026-10-25 02:30 +02:00', $occurrences[1]->format( 'Y-m-d H:i P' ) );
	}
}