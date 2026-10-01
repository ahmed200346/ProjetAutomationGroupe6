<?php

namespace DSA\Admin;

use DSA\Storage\DemoMode;

defined( 'ABSPATH' ) || exit;

final class DemoModeSettings {
	public function register(): void {
		add_action( 'init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			'dsa_general_settings',
			DemoMode::OPTION,
			array(
				'type' => 'boolean',
				'sanitize_callback' => array( DemoMode::class, 'sanitize' ),
				'default' => false,
			)
		);
	}
}