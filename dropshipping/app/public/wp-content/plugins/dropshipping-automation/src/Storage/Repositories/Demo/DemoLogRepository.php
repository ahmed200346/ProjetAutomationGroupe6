<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\LogRepository;

defined( 'ABSPATH' ) || exit;

final class DemoLogRepository implements LogRepository {
	public function all(): array {
		return DemoDataset::table( 'logs' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $log ) {
			if ( $id === $log['id'] ) {
				return $log;
			}
		}

		return null;
	}

	public function for_run( int $run_id ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $run_id ) { return $run_id === $row['run_id']; } ) );
	}
}