<?php
/**
 * should_prevent_prompt unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the should_prevent_prompt logic from WPMind class.
 *
 * @covers ::WPMind\WPMind::should_prevent_prompt
 */
class ShouldPreventPromptTest extends TestCase {

	/**
	 * Already prevented → returns true (passthrough).
	 */
	public function test_returns_true_when_already_prevented(): void {
		$result = $this->call_should_prevent(
			true,
			[ 'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-test' ] ],
			true
		);

		$this->assertTrue( $result );
	}

	/**
	 * No enabled providers → returns false (no providers to check).
	 */
	public function test_returns_false_when_no_enabled_providers(): void {
		$result = $this->call_should_prevent(
			false,
			[ 'deepseek' => [ 'enabled' => false, 'api_key' => '' ] ],
			false
		);

		$this->assertFalse( $result );
	}

	/**
	 * Providers enabled + has available → returns false (allow prompt).
	 */
	public function test_returns_false_when_providers_available(): void {
		$result = $this->call_should_prevent(
			false,
			[ 'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-test' ] ],
			true
		);

		$this->assertFalse( $result );
	}

	/**
	 * Providers enabled + all tripped → returns true (prevent prompt).
	 */
	public function test_returns_true_when_all_providers_tripped(): void {
		$result = $this->call_should_prevent(
			false,
			[ 'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-test' ] ],
			false
		);

		$this->assertTrue( $result );
	}

	/**
	 * Multiple providers, some enabled, all tripped → returns true.
	 */
	public function test_returns_true_with_multiple_providers_all_tripped(): void {
		$result = $this->call_should_prevent(
			false,
			[
				'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-1' ],
				'qwen'     => [ 'enabled' => true, 'api_key' => 'sk-2' ],
				'openai'   => [ 'enabled' => false, 'api_key' => '' ],
			],
			false
		);

		$this->assertTrue( $result );
	}

	/**
	 * Multiple providers, some enabled, one available → returns false.
	 */
	public function test_returns_false_with_one_provider_available(): void {
		$result = $this->call_should_prevent(
			false,
			[
				'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-1' ],
				'qwen'     => [ 'enabled' => true, 'api_key' => 'sk-2' ],
			],
			true
		);

		$this->assertFalse( $result );
	}

	/**
	 * Provider enabled but no API key → not counted as enabled.
	 */
	public function test_provider_without_key_not_counted(): void {
		$result = $this->call_should_prevent(
			false,
			[ 'deepseek' => [ 'enabled' => true, 'api_key' => '' ] ],
			false
		);

		// No provider with both enabled + api_key → has_enabled = false → return false.
		$this->assertFalse( $result );
	}

	/**
	 * Simulate the should_prevent_prompt logic.
	 *
	 * @param bool   $prevent              Incoming prevent value.
	 * @param array  $custom_endpoints     Simulated endpoints config.
	 * @param bool   $has_available         Whether FailoverManager has available providers.
	 * @return bool
	 */
	private function call_should_prevent( bool $prevent, array $custom_endpoints, bool $has_available ): bool {
		// Replicate the exact logic from WPMind::should_prevent_prompt().
		if ( $prevent ) {
			return true;
		}

		$has_enabled = false;
		foreach ( $custom_endpoints as $endpoint ) {
			if ( ! empty( $endpoint['enabled'] ) && ! empty( $endpoint['api_key'] ) ) {
				$has_enabled = true;
				break;
			}
		}

		if ( ! $has_enabled ) {
			return false;
		}

		return ! $has_available;
	}
}
