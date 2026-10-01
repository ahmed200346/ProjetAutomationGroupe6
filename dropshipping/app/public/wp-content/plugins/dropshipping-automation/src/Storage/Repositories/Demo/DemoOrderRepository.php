<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\OrderRepository;

defined( 'ABSPATH' ) || exit;

final class DemoOrderRepository implements OrderRepository {
	public function all(): array {
		return DemoDataset::table( 'orders' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $order ) {
			if ( $id === $order['id'] ) {
				return $order;
			}
		}

		return null;
	}

	public function for_product( int $product_id ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $product_id ) { return $product_id === $row['product_id']; } ) );
	}
}