<?php
/**
 * AuthenticatedProviderAvailability contract tests (no network).
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthenticatedProviderAvailabilityTest extends TestCase {

	public function test_is_configured_source_does_not_probe_network(): void {
		$source = (string) file_get_contents(
			WPMIND_PLUGIN_DIR . 'includes/Providers/AuthenticatedProviderAvailability.php'
		);

		self::assertStringNotContainsString( 'generateTextResult', $source );
		self::assertStringNotContainsString( 'KEY_MAX_TOKENS', $source );
		self::assertStringNotContainsString( "MessagePart( 'a' )", $source );
		self::assertStringContainsString( 'getRequestAuthentication', $source );
		self::assertStringContainsString( '\\Throwable', $source );
	}
}
