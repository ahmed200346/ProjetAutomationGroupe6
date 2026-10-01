<?php

namespace DSA\Providers;

defined( 'ABSPATH' ) || exit;

interface Provider {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_models(): array;

	/**
	 * @return array<string, mixed>
	 */
	public function get_status(): array;
}
