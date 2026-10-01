<?php

use DSA\Storage\Repositories\Demo\DemoCategoryRepository;
use DSA\Storage\Repositories\Demo\DemoDataset;
use DSA\Storage\Repositories\Demo\DemoProductRepository;
use DSA\Storage\DemoMode;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'DSA_PLUGIN_DIR' ) ) {
	define( 'DSA_PLUGIN_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

require_once DSA_PLUGIN_DIR . 'src/autoload.php';

final class DemoDataTest extends TestCase {
	public function test_demo_mode_accepts_wordpress_option_scalar_values(): void {
		$this->assertTrue( DemoMode::sanitize( true ) );
		$this->assertTrue( DemoMode::sanitize( 1 ) );
		$this->assertTrue( DemoMode::sanitize( '1' ) );
		$this->assertFalse( DemoMode::sanitize( '0' ) );
		$this->assertFalse( DemoMode::sanitize( 'yes' ) );
	}

	public function test_demo_dataset_has_stable_expected_counts(): void {
		$this->assertCount( 8, ( new DemoCategoryRepository() )->all() );
		$this->assertCount( 80, ( new DemoProductRepository() )->all() );
		$this->assertCount( 720, DemoDataset::table( 'price_snapshots' ) );
		$this->assertCount( 60, DemoDataset::table( 'run_stages' ) );
		$this->assertSame( range( 1, 60 ), array_column( DemoDataset::table( 'run_stages' ), 'id' ) );
		$this->assertSame( DemoDataset::table( 'products' ), ( new DemoProductRepository() )->all() );
	}

	public function test_product_margins_and_scores_are_consistent(): void {
		foreach ( ( new DemoProductRepository() )->all() as $product ) {
			$expected_margin = ( ( $product['prix_vente'] - $product['prix_achat'] ) / $product['prix_vente'] ) * 100;
			$this->assertEqualsWithDelta( $expected_margin, $product['marge_pct'], 0.01 );

			foreach ( array( 'score_demande', 'score_tendance', 'score_concurrence', 'score_global' ) as $score ) {
				$this->assertGreaterThanOrEqual( 0, $product[ $score ] );
				$this->assertLessThanOrEqual( 100, $product[ $score ] );
			}
		}
	}

	public function test_generated_dates_are_valid_and_price_history_spans_ninety_days(): void {
		$snapshots = DemoDataset::table( 'price_snapshots' );
		foreach ( $snapshots as $snapshot ) {
			$this->assert_valid_date( $snapshot['date'], 'Y-m-d' );
		}

		foreach ( DemoDataset::table( 'products' ) as $product ) {
			$this->assert_valid_date( $product['created_at'], 'Y-m-d H:i:s' );
		}

		foreach ( DemoDataset::table( 'trend_signals' ) as $signal ) {
			$this->assert_valid_date( $signal['date'], 'Y-m-d' );
		}
		foreach ( DemoDataset::table( 'runs' ) as $run ) {
			$this->assert_valid_date( $run['debut'], 'Y-m-d H:i:s' );
			$this->assert_valid_date( $run['fin'], 'Y-m-d H:i:s' );
		}
		foreach ( DemoDataset::table( 'run_stages' ) as $stage ) {
			$this->assert_valid_date( $stage['start_at'], 'Y-m-d H:i:s' );
			$this->assert_valid_date( $stage['end_at'], 'Y-m-d H:i:s' );
			$this->assertGreaterThanOrEqual( 0, $stage['duration_seconds'] );
		}
		foreach ( DemoDataset::table( 'orders' ) as $order ) {
			$this->assert_valid_date( $order['date'], 'Y-m-d' );
		}
		foreach ( DemoDataset::table( 'reviews' ) as $review ) {
			$this->assert_valid_date( $review['date'], 'Y-m-d' );
		}
		foreach ( DemoDataset::table( 'logs' ) as $log ) {
			$this->assert_valid_date( $log['date'], 'Y-m-d H:i:s' );
		}

		foreach ( DemoDataset::table( 'categories' ) as $category ) {
			$dates = array_map(
				static function ( $row ) { return $row['date']; },
				array_filter( $snapshots, static function ( $row ) use ( $category ) { return $category['id'] === $row['category_id']; } )
			);
			$this->assertCount( 90, array_unique( $dates ) );
		}
	}

	private function assert_valid_date( string $value, string $format ): void {
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $value );
		$this->assertInstanceOf( DateTimeImmutable::class, $date );
		$this->assertSame( $value, $date->format( $format ) );
	}
}