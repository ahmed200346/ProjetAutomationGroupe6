<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\TrendSignalRepository;

defined( 'ABSPATH' ) || exit;

final class DemoTrendSignalRepository implements TrendSignalRepository {
	public function all(): array {
		return DemoDataset::table( 'trend_signals' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $signal ) {
			if ( $id === $signal['id'] ) {
				return $signal;
			}
		}

		return null;
	}

	public function for_platform( string $platform ): array {
		return array_values( array_filter( $this->all(), static function ( $row ) use ( $platform ) { return $platform === $row['plateforme']; } ) );
	}
}