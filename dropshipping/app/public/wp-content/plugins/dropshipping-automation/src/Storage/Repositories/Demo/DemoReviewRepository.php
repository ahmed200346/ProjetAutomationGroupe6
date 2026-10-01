<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\ReviewRepository;

defined( 'ABSPATH' ) || exit;

final class DemoReviewRepository implements ReviewRepository {
	public function all(): array {
		return DemoDataset::table( 'reviews' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $review ) {
			if ( $id === $review['id'] ) {
				return $review;
			}
		}

		return null;
	}

	public function for_product( int $product_id ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $product_id ) { return $product_id === $row['product_id']; } ) );
	}
}