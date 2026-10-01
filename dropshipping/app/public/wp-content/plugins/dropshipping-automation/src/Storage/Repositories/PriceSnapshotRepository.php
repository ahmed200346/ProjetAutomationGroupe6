<?php

namespace DSA\Storage\Repositories;

defined( 'ABSPATH' ) || exit;

interface PriceSnapshotRepository {
	public function all(): array;

	public function find( int $id ): ?array;

	public function for_product( int $product_id ): array;

	public function for_category( int $category_id ): array;
}