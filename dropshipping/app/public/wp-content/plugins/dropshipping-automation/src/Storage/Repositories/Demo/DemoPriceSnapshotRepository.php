<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\PriceSnapshotRepository;

defined( 'ABSPATH' ) || exit;

final class DemoPriceSnapshotRepository implements PriceSnapshotRepository {
	public function all(): array {
		return DemoDataset::table( 'price_snapshots' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $snapshot ) {
			if ( $id === $snapshot['id'] ) {
				return $snapshot;
			}
		}

		return null;
	}

	public function for_product( int $product_id ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $product_id ) { return $product_id === $row['product_id']; } ) );
	}

	public function for_category( int $category_id ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $category_id ) { return $category_id === $row['category_id']; } ) );
	}
}