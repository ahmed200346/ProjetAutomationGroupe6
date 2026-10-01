<?php

namespace DSA\Storage\Repositories;

defined( 'ABSPATH' ) || exit;

interface ProductRepository {
	public function all(): array;

	public function find( int $id ): ?array;

	public function filter( array $filters = array() ): array;
}