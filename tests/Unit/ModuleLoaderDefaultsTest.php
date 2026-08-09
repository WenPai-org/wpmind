<?php
/**
 * Module default behavior tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Core\ModuleLoader;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleInterface.php';
require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleLoader.php';

final class ModuleLoaderDefaultsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpmind_test_options'] = [];
	}

	public function test_optional_module_is_disabled_without_saved_choice(): void {
		$loader = ModuleLoader::instance();
		self::assertFalse( $loader->is_module_enabled( 'api-gateway', false ) );
	}

	public function test_core_module_can_default_to_enabled(): void {
		$loader = ModuleLoader::instance();
		self::assertTrue( $loader->is_module_enabled( 'cost-control', true ) );
	}

	public function test_saved_choice_overrides_manifest_default(): void {
		$GLOBALS['wpmind_test_options']['wpmind_module_geo_enabled'] = '1';
		$loader = ModuleLoader::instance();
		self::assertTrue( $loader->is_module_enabled( 'geo', false ) );
	}
}
