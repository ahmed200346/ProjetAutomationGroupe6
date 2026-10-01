<?php

namespace DSA\REST;

use DSA\Storage\Repositories\RepositoryFactory;

defined( 'ABSPATH' ) || exit;

final class TrendsController {
	private $repositories;

	public function __construct( RepositoryFactory $repositories ) {
		$this->repositories = $repositories;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'dsa/v1',
			'/trends',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_trends' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'period'     => array( 'default' => 30, 'sanitize_callback' => 'absint' ),
					'platform'   => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
					'source'     => array( 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
					'categories' => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public function check_permission( $request ): bool {
		$nonce = $request instanceof \WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return current_user_can( 'manage_options' ) && is_string( $nonce ) && (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	public function get_trends( \WP_REST_Request $request ): \WP_REST_Response {
		$period = absint( $request->get_param( 'period' ) );
		$period = in_array( $period, array( 7, 30, 90 ), true ) ? $period : 30;
		$platform_param = $request->get_param( 'platform' );
		$platform = is_scalar( $platform_param ) ? sanitize_key( (string) $platform_param ) : '';
		$platform = in_array( $platform, array( '', 'x', 'facebook', 'instagram' ), true ) ? $platform : '';
		$source_param = $request->get_param( 'source' );
		$source = is_scalar( $source_param ) ? sanitize_key( (string) $source_param ) : '';
		$categories_param = $request->get_param( 'categories' );
		$categories_param = is_scalar( $categories_param ) ? (string) $categories_param : '';
		$requested_categories = array_filter( array_map( 'absint', explode( ',', $categories_param ) ) );

		$products = $this->repositories->products()->all();
		$categories = $this->repositories->categories()->all();
		$snapshots = $this->repositories->price_snapshots()->all();
		$signals = $this->repositories->trend_signals()->all();
		$runs = $this->repositories->runs()->all();

		$category_names = array();
		foreach ( $categories as $category ) {
			$category_names[ absint( $category['id'] ) ] = (string) $category['nom'];
		}
		$products_by_id = array();
		$products_by_name = array();
		$sources = array();
		foreach ( $products as $product ) {
			$product_id = absint( $product['id'] );
			$products_by_id[ $product_id ] = $product;
			$key = $this->product_key( $product['titre'], $product['category_id'] );
			if ( ! isset( $products_by_name[ $key ] ) ) {
				$products_by_name[ $key ] = $product;
			}
			if ( ! empty( $product['source'] ) ) {
				$sources[ sanitize_key( $product['source'] ) ] = sanitize_key( $product['source'] );
			}
		}
		if ( ! isset( $sources[ $source ] ) ) {
			$source = '';
		}

		$selected_categories = array_values( array_intersect( array_keys( $category_names ), $requested_categories ) );
		if ( empty( $selected_categories ) && empty( $requested_categories ) ) {
			$selected_categories = array_keys( $category_names );
		}
		$selected_category_map = array_fill_keys( $selected_categories, true );
		$end = new \DateTimeImmutable( 'tomorrow', new \DateTimeZone( 'UTC' ) );
		$start = $end->modify( '-' . $period . ' days' );
		$previous_start = $start->modify( '-' . $period . ' days' );
		$demand_start = $end->modify( '-30 days' );
		$signal_totals = array();
		$category_totals = array();
		$platform_totals = array( 'x' => 0, 'facebook' => 0, 'instagram' => 0 );
		$daily_product_mentions = array();
		$demanded = array();

		foreach ( $signals as $signal ) {
			$signal_date = $this->parse_date( $signal['date'] ?? '' );
			if ( ! $signal_date || $signal_date < $previous_start || $signal_date >= $end ) {
				continue;
			}
			$signal_platform = sanitize_key( $signal['plateforme'] ?? '' );
			if ( '' !== $platform && $platform !== $signal_platform ) {
				continue;
			}
			$product_name = sanitize_text_field( $signal['produit_detecte'] ?? '' );
			$product_key = $this->product_key_for_name( $product_name, $products_by_name );
			$product = $products_by_name[ $product_key ] ?? null;
			if ( ! $product || ( '' !== $source && $source !== sanitize_key( $product['source'] ?? '' ) ) ) {
				continue;
			}
			$category_id = absint( $product['category_id'] );
			if ( ! isset( $selected_category_map[ $category_id ] ) ) {
				continue;
			}
			$signal_count = absint( $signal['nb_mentions'] ?? 1 );
			$key = $this->product_key( $product_name, $category_id );
			$day = $signal_date->format( 'Y-m-d' );
			$signal_totals[ $key ] = $signal_totals[ $key ] ?? array( 'mentions' => 0, 'current' => 0, 'previous' => 0 );
			$signal_totals[ $key ]['mentions'] += $signal_count;
			if ( $signal_date >= $demand_start ) {
				$demand_key = $category_id . ':' . $product_name;
				$demanded[ $demand_key ] = $demanded[ $demand_key ] ?? array( 'category' => $category_names[ $category_id ] ?? '', 'product' => $product_name, 'signals' => 0, 'mentions' => 0 );
				++$demanded[ $demand_key ]['signals'];
				$demanded[ $demand_key ]['mentions'] += $signal_count;
			}
			if ( $signal_date >= $start ) {
				$signal_totals[ $key ]['current'] += $signal_count;
				$category_totals[ $category_id ] = ( $category_totals[ $category_id ] ?? 0 ) + 1;
				$platform_totals[ $signal_platform ] = ( $platform_totals[ $signal_platform ] ?? 0 ) + 1;
				$daily_product_mentions[ $key ][ $day ] = ( $daily_product_mentions[ $key ][ $day ] ?? 0 ) + $signal_count;
			} elseif ( $signal_date < $start ) {
				$signal_totals[ $key ]['previous'] += $signal_count;
			}
		}

		$price_buckets = array();
		$product_price_totals = array();
		foreach ( $snapshots as $snapshot ) {
			$snapshot_date = $this->parse_date( $snapshot['date'] ?? '' );
			$product_id = absint( $snapshot['product_id'] ?? 0 );
			$product = $products_by_id[ $product_id ] ?? null;
			$category_id = absint( $snapshot['category_id'] ?? 0 );
			if ( ! $snapshot_date || $snapshot_date < $start || $snapshot_date >= $end || ! $product || ! isset( $selected_category_map[ $category_id ] ) ) {
				continue;
			}
			if ( '' !== $source && $source !== sanitize_key( $product['source'] ?? '' ) ) {
				continue;
			}
			$day = $snapshot_date->format( 'Y-m-d' );
			$price = (float) ( $snapshot['prix'] ?? 0 );
			$price_buckets[ $category_id ][ $day ][] = $price;
			$product_price_totals[ $product_id ][] = $price;
		}

		$labels = array();
		$days = array();
		for ( $offset = 0; $offset < $period; ++$offset ) {
			$day = $start->modify( '+' . $offset . ' days' )->format( 'Y-m-d' );
			$days[] = $day;
			$labels[] = wp_date( 'j M', strtotime( $day . ' 00:00:00 UTC' ) );
		}
		$price_series = array();
		$price_changes = array();
		foreach ( $selected_categories as $category_id ) {
			$values = array();
			$known_prices = array();
			foreach ( $days as $day ) {
				$daily_prices = $price_buckets[ $category_id ][ $day ] ?? array();
				$value = $daily_prices ? round( array_sum( $daily_prices ) / count( $daily_prices ), 2 ) : null;
				$values[] = $value;
				if ( null !== $value ) {
					$known_prices[] = $value;
				}
			}
			$first = $known_prices[0] ?? 0;
			$last = $known_prices ? $known_prices[ count( $known_prices ) - 1 ] : 0;
			$price_changes[] = array(
				'category' => $category_names[ $category_id ] ?? '',
				'change'   => $first > 0 ? round( ( ( $last - $first ) / $first ) * 100, 1 ) : null,
			);
			$price_series[] = array( 'label' => $category_names[ $category_id ] ?? '', 'data' => $values );
		}

		$ranking = array();
		foreach ( $products_by_name as $key => $product ) {
			$category_id = absint( $product['category_id'] );
			if ( ! isset( $selected_category_map[ $category_id ] ) || ( '' !== $source && $source !== sanitize_key( $product['source'] ?? '' ) ) ) {
				continue;
			}
			$stats = $signal_totals[ $key ] ?? array( 'mentions' => 0, 'current' => 0, 'previous' => 0 );
			$previous = $stats['previous'];
			$growth = $previous > 0 ? round( ( ( $stats['current'] - $previous ) / $previous ) * 100, 1 ) : ( $stats['current'] > 0 ? 100 : 0 );
			$product_id = absint( $product['id'] );
			$prices = $product_price_totals[ $product_id ] ?? array();
			$sparkline = array();
			for ( $bucket = 0; $bucket < 7; ++$bucket ) {
				$bucket_start = (int) floor( $bucket * $period / 7 );
				$bucket_end = (int) floor( ( $bucket + 1 ) * $period / 7 );
				$bucket_total = 0;
				foreach ( $daily_product_mentions[ $key ] ?? array() as $day => $mentions ) {
					$day_offset = (int) $start->diff( new \DateTimeImmutable( $day, new \DateTimeZone( 'UTC' ) ) )->days;
					if ( $day_offset >= $bucket_start && $day_offset < $bucket_end ) {
						$bucket_total += $mentions;
					}
				}
				$sparkline[] = $bucket_total;
			}
			$ranking[] = array(
				'product' => (string) $product['titre'],
				'category' => $category_names[ $category_id ] ?? '',
				'mentions' => absint( $stats['current'] ),
				'growth' => $growth,
				'average_price' => $prices ? round( array_sum( $prices ) / count( $prices ), 2 ) : (float) ( $product['prix_vente'] ?? 0 ),
				'margin' => (float) ( $product['marge_pct'] ?? 0 ),
				'score' => absint( $product['score_global'] ?? 0 ),
				'sparkline' => $sparkline,
			);
		}
		usort( $ranking, static function ( $left, $right ) { return $right['mentions'] <=> $left['mentions']; } );
		$ranking = array_slice( $ranking, 0, 20 );
		usort( $demanded, static function ( $left, $right ) { return $right['signals'] <=> $left['signals']; } );
		$demanded = array_slice( array_values( $demanded ), 0, 30 );
		$top_categories = array();
		arsort( $category_totals );
		foreach ( array_slice( $category_totals, 0, 8, true ) as $category_id => $count ) {
			$top_categories[] = array( 'label' => $category_names[ $category_id ] ?? '', 'value' => $count );
		}
		arsort( $platform_totals );
		$last_run = $this->latest_run( $runs );

		return new \WP_REST_Response(
			array(
				'period' => $period,
				'filters' => array(
					'categories' => array_map( static function ( $category ) { return array( 'id' => absint( $category['id'] ), 'name' => (string) $category['nom'] ); }, $categories ),
					'sources' => array_values( $sources ),
				),
				'price' => array( 'labels' => $labels, 'series' => $price_series, 'changes' => $price_changes ),
				'categories' => array( 'labels' => array_column( $top_categories, 'label' ), 'values' => array_column( $top_categories, 'value' ) ),
				'platforms' => array( 'labels' => array( 'X', 'Facebook', 'Instagram' ), 'values' => array( absint( $platform_totals['x'] ?? 0 ), absint( $platform_totals['facebook'] ?? 0 ), absint( $platform_totals['instagram'] ?? 0 ) ) ),
				'products' => $ranking,
				'demanded' => $demanded,
				'last_run' => $last_run,
			),
			200
		);
	}

	private function latest_run( array $runs ): array {
		$runs = array_values( array_filter( $runs, static function ( $run ) {
			return in_array( $run['workflow'] ?? '', array( 'social_scan', 'trend_scraping', 'scrape_trends', 'trends' ), true );
		} ) );
		usort( $runs, static function ( $left, $right ) { return strcmp( $right['debut'] ?? '', $left['debut'] ?? '' ); } );
		$run = $runs[0] ?? array();
		return array(
			'date' => sanitize_text_field( $run['debut'] ?? '' ),
			'status' => sanitize_key( $run['statut'] ?? 'unknown' ),
			'label' => sanitize_text_field( $run['workflow'] ?? '' ),
		);
	}

	private function parse_date( $value ): ?\DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $error ) {
			return null;
		}
	}

	private function product_key( $name, $category_id ): string {
		return absint( $category_id ) . ':' . strtolower( trim( sanitize_text_field( $name ) ) );
	}

	private function product_key_for_name( string $name, array $products_by_name ): string {
		foreach ( $products_by_name as $key => $product ) {
			if ( (string) $product['titre'] === $name ) {
				return $key;
			}
		}
		return '';
	}
}