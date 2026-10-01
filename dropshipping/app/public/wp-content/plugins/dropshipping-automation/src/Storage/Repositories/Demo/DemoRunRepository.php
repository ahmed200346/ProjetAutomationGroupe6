<?php

namespace DSA\Storage\Repositories\Demo;

use DSA\Storage\Repositories\RunRepository;

defined( 'ABSPATH' ) || exit;

final class DemoRunRepository implements RunRepository {
	public function all(): array {
		return DemoDataset::table( 'runs' );
	}

	public function find( int $id ): ?array {
		foreach ( $this->all() as $run ) {
			if ( $id === $run['id'] ) {
				return $run;
			}
		}

		return null;
	}
}