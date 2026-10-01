<?php

namespace DSA\Storage\Repositories;

defined( 'ABSPATH' ) || exit;

interface TrendSignalRepository {
	public function all(): array;

	public function find( int $id ): ?array;

	public function for_platform( string $platform ): array;
}