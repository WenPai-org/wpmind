<?php
/**
 * M013_ConnectorMirrorCleanup tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Migrations\M013_ConnectorMirrorCleanup;
use WPMind\Provider\Migrations\Migrator;

final class ConnectorMirrorCleanupTest extends TestCase {

	private const FAKE_WPMIND = 'sk-fake-wpmind-aaaaaaaa';
	private const FAKE_CORE   = 'sk-fake-core-bbbbbbbbbb';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']  = array();
		$GLOBALS['wpmind_test_autoload'] = array();
	}

	public function test_deletes_mirror_when_equal_to_wpmind_key(): void {
		$GLOBALS['wpmind_test_options']['wpmind_custom_endpoints'] = array(
			'deepseek' => array( 'api_key' => self::FAKE_WPMIND ),
		);
		$GLOBALS['wpmind_test_options']['connectors_ai_deepseek_api_key'] = self::FAKE_WPMIND;

		$migration = new M013_ConnectorMirrorCleanup();
		self::assertTrue( $migration->is_needed() );

		$outcome = $migration->run();
		self::assertSame( 'ok', $outcome['result'] );
		self::assertArrayNotHasKey( 'connectors_ai_deepseek_api_key', $GLOBALS['wpmind_test_options'] );
	}

	public function test_keeps_mirror_when_value_differs(): void {
		$GLOBALS['wpmind_test_options']['wpmind_custom_endpoints'] = array(
			'deepseek' => array( 'api_key' => self::FAKE_WPMIND ),
		);
		$GLOBALS['wpmind_test_options']['connectors_ai_deepseek_api_key'] = self::FAKE_CORE;

		$migration = new M013_ConnectorMirrorCleanup();
		self::assertFalse( $migration->is_needed() );

		$outcome = $migration->run();
		self::assertSame( 'noop', $outcome['result'] );
		self::assertSame(
			self::FAKE_CORE,
			$GLOBALS['wpmind_test_options']['connectors_ai_deepseek_api_key']
		);
	}

	public function test_noop_when_mirror_absent(): void {
		$GLOBALS['wpmind_test_options']['wpmind_custom_endpoints'] = array(
			'deepseek' => array( 'api_key' => self::FAKE_WPMIND ),
		);

		$migration = new M013_ConnectorMirrorCleanup();
		self::assertFalse( $migration->is_needed() );
		$outcome = $migration->run();
		self::assertSame( 'noop', $outcome['result'] );
	}

	public function test_migrator_includes_cleanup_at_1301(): void {
		self::assertSame( 1301, Migrator::CURRENT_DB_VERSION );
		$migration = new M013_ConnectorMirrorCleanup();
		self::assertSame( 1301, $migration->version() );
	}
}
