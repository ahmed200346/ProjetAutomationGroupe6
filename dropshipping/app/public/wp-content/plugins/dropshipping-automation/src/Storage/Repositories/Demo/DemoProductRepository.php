<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\ProductRepository;

defined( 'ABSPATH' ) || exit;

final class DemoProductRepository implements ProductRepository {
	public function all(): array {
		return DemoDataset::table( 'products' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $product ) {
			if ( $id === $product['id'] ) {
				return $product;
			}
		}

		return null;
	}

	public function filter( array $filters = array() ): array {
		$products = array_filter(
			$this->all(),
			static function ( $product ) use ( $filters ) {
				if ( isset( $filters['status'] ) && $product['statut'] !== $filters['status'] ) {
					return false;
				}
				if ( ! empty( $filters['category_id'] ) && (int) $product['category_id'] !== (int) $filters['category_id'] ) {
					return false;
				}
				if ( ! empty( $filters['search'] ) && false === stripos( $product['titre'], (string) $filters['search'] ) ) {
					return false;
				}
				if ( ! empty( $filters['max_price'] ) && (float) $product['prix_vente'] > (float) $filters['max_price'] ) {
					return false;
				}
				if ( ! empty( $filters['minimum_rating'] ) && (float) $product['note_moyenne'] < (float) $filters['minimum_rating'] ) {
					return false;
				}

				return true;
			}
		);

		return array_values( $products );
	}
}