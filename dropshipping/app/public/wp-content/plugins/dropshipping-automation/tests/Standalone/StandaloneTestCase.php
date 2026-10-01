<?php

namespace DSA_Tests\Standalone;

if ( class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
	// PHPUnit réel disponible : rien à définir ici, les tests l'utilisent directement.
	class_alias( 'PHPUnit\\Framework\\TestCase', 'DSA_Tests\\Standalone\\FallbackTestCase' );
} else {

	class AssertionFailedError extends \Exception {
	}

	/**
	 * Base de repli imitant l'API PHPUnit réellement utilisée par les suites du plugin.
	 */
	abstract class TestCase {
		private $expectation_count   = 0;
		private $expected_exception  = null;
		private $exception_was_thrown = false;

		public function run_bare_for( string $method ): int {
			$this->expectation_count    = 0;
			$this->expected_exception   = null;
			$this->exception_was_thrown = false;
			try {
				$this->{$method}();
			} catch ( AssertionFailedError $error ) {
				throw $error;
			} catch ( \Throwable $error ) {
				if ( null === $this->expected_exception ) {
					throw $error;
				}
				if ( ! $error instanceof $this->expected_exception ) {
					throw new AssertionFailedError(
						sprintf( 'Exception attendue %1$s, reçue %2$s : %3$s', $this->expected_exception, get_class( $error ), $error->getMessage() )
					);
				}
				$this->exception_was_thrown = true;
			}
			if ( null !== $this->expected_exception && ! $this->exception_was_thrown ) {
				throw new AssertionFailedError( 'Exception attendue non levée : ' . $this->expected_exception );
			}
			return $this->expectation_count;
		}

		protected function record(): void {
			++$this->expectation_count;
		}

		protected function fail( string $message ): void {
			throw new AssertionFailedError( $message );
		}

		protected function expectException( string $class ): void {
			$this->record();
			$this->expected_exception = $class;
		}

		protected function assertSame( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $expected !== $actual ) {
				$this->fail( $message ?: sprintf( "assertSame : attendu %s, obtenu %s", var_export( $expected, true ), var_export( $actual, true ) ) );
			}
		}

		protected function assertNotSame( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $expected === $actual ) {
				$this->fail( $message ?: 'assertNotSame a échoué.' );
			}
		}

		protected function assertEquals( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $expected != $actual ) {
				$this->fail( $message ?: sprintf( "assertEquals : attendu %s, obtenu %s", var_export( $expected, true ), var_export( $actual, true ) ) );
			}
		}

		protected function assertEqualsWithDelta( $expected, $actual, float $delta, string $message = '' ): void {
			$this->record();
			if ( abs( (float) $expected - (float) $actual ) > $delta ) {
				$this->fail( $message ?: sprintf( 'assertEqualsWithDelta : attendu %s ±%s, obtenu %s', $expected, $delta, $actual ) );
			}
		}

		protected function assertTrue( $condition, string $message = '' ): void {
			$this->record();
			if ( true !== $condition ) {
				$this->fail( $message ?: 'assertTrue a échoué.' );
			}
		}

		protected function assertFalse( $condition, string $message = '' ): void {
			$this->record();
			if ( false !== $condition ) {
				$this->fail( $message ?: 'assertFalse a échoué.' );
			}
		}

		protected function assertNull( $value, string $message = '' ): void {
			$this->record();
			if ( null !== $value ) {
				$this->fail( $message ?: 'assertNull a échoué.' );
			}
		}

		protected function assertNotNull( $value, string $message = '' ): void {
			$this->record();
			if ( null === $value ) {
				$this->fail( $message ?: 'assertNotNull a échoué.' );
			}
		}

		protected function assertEmpty( $value, string $message = '' ): void {
			$this->record();
			if ( ! empty( $value ) ) {
				$this->fail( $message ?: 'assertEmpty a échoué.' );
			}
		}

		protected function assertNotEmpty( $value, string $message = '' ): void {
			$this->record();
			if ( empty( $value ) ) {
				$this->fail( $message ?: 'assertNotEmpty a échoué.' );
			}
		}

		protected function assertCount( int $expected, $haystack, string $message = '' ): void {
			$this->record();
			$actual = is_countable( $haystack ) ? count( $haystack ) : -1;
			if ( $expected !== $actual ) {
				$this->fail( $message ?: sprintf( 'assertCount : attendu %d, obtenu %d', $expected, $actual ) );
			}
		}

		protected function assertArrayHasKey( $key, $array, string $message = '' ): void {
			$this->record();
			if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
				$this->fail( $message ?: sprintf( 'assertArrayHasKey : clé %s absente.', var_export( $key, true ) ) );
			}
		}

		protected function assertArrayNotHasKey( $key, $array, string $message = '' ): void {
			$this->record();
			if ( is_array( $array ) && array_key_exists( $key, $array ) ) {
				$this->fail( $message ?: sprintf( 'assertArrayNotHasKey : clé %s présente.', var_export( $key, true ) ) );
			}
		}

		protected function assertGreaterThan( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $actual <= $expected ) {
				$this->fail( $message ?: sprintf( 'assertGreaterThan : %s <= %s', $actual, $expected ) );
			}
		}

		protected function assertGreaterThanOrEqual( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $actual < $expected ) {
				$this->fail( $message ?: sprintf( 'assertGreaterThanOrEqual : %s < %s', $actual, $expected ) );
			}
		}

		protected function assertLessThanOrEqual( $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( $actual > $expected ) {
				$this->fail( $message ?: sprintf( 'assertLessThanOrEqual : %s > %s', $actual, $expected ) );
			}
		}

		protected function assertStringContainsString( string $needle, string $haystack, string $message = '' ): void {
			$this->record();
			if ( false === strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: sprintf( 'assertStringContainsString : « %s » introuvable.', $needle ) );
			}
		}

		protected function assertStringNotContainsString( string $needle, string $haystack, string $message = '' ): void {
			$this->record();
			if ( false !== strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: sprintf( 'assertStringNotContainsString : « %s » trouvé alors qu’il ne doit pas l’être.', $needle ) );
			}
		}

		protected function assertMatchesRegularExpression( string $pattern, string $subject, string $message = '' ): void {
			$this->record();
			if ( ! preg_match( $pattern, $subject ) ) {
				$this->fail( $message ?: sprintf( 'assertMatchesRegularExpression : %s ne correspond pas à %s.', $subject, $pattern ) );
			}
		}

		protected function assertIsArray( $value, string $message = '' ): void {
			$this->record();
			if ( ! is_array( $value ) ) {
				$this->fail( $message ?: 'assertIsArray a échoué.' );
			}
		}

		protected function assertIsString( $value, string $message = '' ): void {
			$this->record();
			if ( ! is_string( $value ) ) {
				$this->fail( $message ?: 'assertIsString a échoué.' );
			}
		}

		protected function assertIsInt( $value, string $message = '' ): void {
			$this->record();
			if ( ! is_int( $value ) ) {
				$this->fail( $message ?: 'assertIsInt a échoué.' );
			}
		}

		protected function assertInstanceOf( string $expected, $actual, string $message = '' ): void {
			$this->record();
			if ( ! $actual instanceof $expected ) {
				$this->fail( $message ?: sprintf( 'assertInstanceOf : attendu %s, obtenu %s.', $expected, is_object( $actual ) ? get_class( $actual ) : gettype( $actual ) ) );
			}
		}

		protected function assertIsBool( $value, string $message = '' ): void {
			$this->record();
			if ( ! is_bool( $value ) ) {
				$this->fail( $message ?: 'assertIsBool a échoué.' );
			}
		}
	}
}
