<?php
/**
 * Harnais de tests autonome pour le plugin Dropshipping Automation.
 *
 * Exécute les suites du format PHPUnit sans installation de PHPUnit :
 *
 *   php tests/standalone-runner.php                    # tous les tests
 *   php tests/standalone-runner.php DigestTest.php     # un seul fichier
 *
 * Si PHPUnit est disponible, il est utilisé directement et ce harnais
 * n'est pas nécessaire (les fichiers de test restent compatibles).
 */

namespace DSA_Tests;

if ( class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
	// PHPUnit réel déjà chargé : le harnais autonome n'a rien à faire.
	return;
}

require_once __DIR__ . '/Standalone/WpStubs.php';
require_once __DIR__ . '/Standalone/StandaloneTestCase.php';
require_once __DIR__ . '/Standalone/StandaloneRunner.php';

class_alias( 'DSA_Tests\Standalone\TestCase', 'PHPUnit\Framework\TestCase' );
class_alias( 'DSA_Tests\Standalone\AssertionFailedError', 'PHPUnit\Framework\AssertionFailedError' );

$dsa_test_plugin_dir = dirname( __DIR__ );
require $dsa_test_plugin_dir . '/src/autoload.php';

$dsa_test_runner = new \DSA_Tests\Standalone\StandaloneRunner( $dsa_test_plugin_dir . '/tests' );
exit( $dsa_test_runner->run( array_slice( $argv, 1 ) ) );
