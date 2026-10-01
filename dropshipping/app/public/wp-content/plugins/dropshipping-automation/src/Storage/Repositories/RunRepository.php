<?php

namespace DSA\Storage\Repositories;

defined( 'ABSPATH' ) || exit;

interface RunRepository {
	public function all(): array;

	public function find( int $id ): ?array;
}