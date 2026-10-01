<?php

namespace DSA\Storage\Repositories;

use DSA\Storage\Repositories\Demo\DemoCategoryRepository;
use DSA\Storage\Repositories\Demo\DemoOrderRepository;
use DSA\Storage\Repositories\Demo\DemoPriceSnapshotRepository;
use DSA\Storage\Repositories\Demo\DemoProductRepository;
use DSA\Storage\Repositories\Demo\DemoRunRepository;
use DSA\Storage\Repositories\Demo\DemoTrendSignalRepository;

defined( 'ABSPATH' ) || exit;

final class RepositoryFactory {
	public function products(): ProductRepository {
		return new DemoProductRepository();
	}

	public function price_snapshots(): PriceSnapshotRepository {
		return new DemoPriceSnapshotRepository();
	}

	public function categories(): CategoryRepository {
		return new DemoCategoryRepository();
	}

	public function orders(): OrderRepository {
		return new DemoOrderRepository();
	}

	public function runs(): RunRepository {
		return new DemoRunRepository();
	}

	public function trend_signals(): TrendSignalRepository {
		return new DemoTrendSignalRepository();
	}
}