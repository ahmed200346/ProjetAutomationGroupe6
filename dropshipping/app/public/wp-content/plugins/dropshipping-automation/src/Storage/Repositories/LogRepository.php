<?php

namespace DSA\Storage\Repositories;

defined( 'ABSPATH' ) || exit;

interface LogRepository {
	public function all(): array;

	public function find( int $id ): ?array;

	public function for_run( int $run_id ): array;
}