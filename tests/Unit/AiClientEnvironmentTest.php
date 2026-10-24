<?php
/**
 * AiClientEnvironment unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Compat\AiClientEnvironment;

final class AiClientEnvironmentTest extends TestCase {

	public function test_detect_returns_expected_shape_without_sdk(): void {
		$env = AiClientEnvironment::detect();

		self::assertArrayHasKey( 'wp_version', $env );
		self::assertArrayHasKey( 'sdk_source', $env );
		self::assertArrayHasKey( 'sdk_version', $env );
		self::assertArrayHasKey( 'has_connectors', $env );
		self::assertArrayHasKey( 'has_prompt_fn', $env );
		self::assertArrayHasKey( 'supports_ai', $env );

		self::assertSame( 'none', $env['sdk_source'] );
		self::assertNull( $env['sdk_version'] );
		self::assertFalse( $env['has_connectors'] );
		self::assertFalse( $env['has_prompt_fn'] );
		self::assertTrue( $env['supports_ai'] );
	}
}
