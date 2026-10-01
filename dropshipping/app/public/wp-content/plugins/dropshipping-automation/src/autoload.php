<?php

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'DSA\\';
		$prefix_length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class_name, $prefix_length ) ) {
			return;
		}

		$relative_class = substr( $class_name, $prefix_length );
		$file           = DSA_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);