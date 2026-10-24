<?php
/**
 * Core AI Client isolation contract tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShouldPreventPromptTest extends TestCase {

	public function test_plugin_does_not_register_a_global_prompt_blocker(): void {
		$source = file_get_contents( WPMIND_PLUGIN_DIR . 'wpmind.php' );

		self::assertIsString( $source );
		self::assertStringNotContainsString( 'wp_ai_client_prevent_prompt', $source );
		self::assertStringNotContainsString( 'function should_prevent_prompt', $source );
	}

	public function test_internal_failover_hooks_remain_available(): void {
		$source = file_get_contents( WPMIND_PLUGIN_DIR . 'wpmind.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( 'init_routing_hooks', $source );
		self::assertStringContainsString( 'Failover\\FailoverManager::instance()', $source );
	}
}
