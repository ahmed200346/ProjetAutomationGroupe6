<?php

namespace DSA\Storage;

defined( 'ABSPATH' ) || exit;

final class DemoMode {
	const OPTION = 'dsa_demo_mode';

	public static function enabled(): bool {
		return self::sanitize( get_option( self::OPTION, false ) );
	}

	public static function sanitize( $value ): bool {
		return true === $value || 1 === $value || '1' === $value;
	}
}