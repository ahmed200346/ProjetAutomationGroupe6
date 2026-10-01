<?php

namespace DSA_Tests\Standalone;

final class StandaloneRunner {
	private $directory;

	public function __construct( string $directory ) {
		$this->directory = $directory;
	}

	public function run( array $only_files ): int {
		$files = array();
		foreach ( glob( $this->directory . '/*Test.php' ) as $path ) {
			$name = basename( $path );
			if ( ! $only_files || in_array( $name, $only_files, true ) ) {
				$files[] = $path;
			}
		}
		if ( ! $files ) {
			echo "Aucun fichier *Test.php à exécuter.\n";
			return 1;
		}

		$total_tests      = 0;
		$total_assertions = 0;
		$failures         = array();

		foreach ( $files as $path ) {
			$class = basename( $path, '.php' );
			require_once $path;
			if ( ! class_exists( $class ) ) {
				$failures[] = $class . ' : classe introuvable dans ' . basename( $path );
				continue;
			}
			$instance = new $class();
			$methods  = array_filter(
				get_class_methods( $instance ),
				static function ( $method ) {
					return 0 === strpos( $method, 'test' );
				}
			);
			foreach ( $methods as $method ) {
				++$total_tests;
				try {
					$total_assertions += $instance->run_bare_for( $method );
				} catch ( \Throwable $error ) {
					$failures[] = $class . '::' . $method . ' — ' . $error->getMessage();
				}
			}
		}

		echo "\n";
		if ( $failures ) {
			echo 'ÉCHECS (' . count( $failures ) . ") :\n";
			foreach ( $failures as $failure ) {
				echo '  - ' . $failure . "\n";
			}
			echo "\nTests : {$total_tests} · Assertions validées avant échec : {$total_assertions}\n";
			return 1;
		}
		echo "OK ({$total_tests} tests, {$total_assertions} assertions)\n";
		return 0;
	}
}
