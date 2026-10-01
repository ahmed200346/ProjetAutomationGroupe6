<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\CategoryRepository;

defined( 'ABSPATH' ) || exit;

final class DemoCategoryRepository implements CategoryRepository {
	public function all(): array {
		return DemoDataset::table( 'categories' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $category ) {
			if ( $id === $category['id'] ) {
				return $category;
			}
		}

		return null;
	}
}