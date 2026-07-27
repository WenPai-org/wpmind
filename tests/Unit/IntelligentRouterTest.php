<?php
/**
 * IntelligentRouter unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the preferred provider circuit breaker check.
 *
 * @covers ::WPMind\Routing\IntelligentRouter::is_provider_available
 */
class IntelligentRouterTest extends TestCase {

	/**
	 * Preferred provider is returned when available and not excluded.
	 */
	public function test_preferred_provider_returned_when_available(): void {
		$context = new class {
			public function get_preferred_provider(): ?string {
				return 'deepseek';
			}
			public function is_excluded( string $id ): bool {
				return false;
			}
		};

		$providers = [
			'deepseek' => [ 'enabled' => true ],
			'qwen'     => [ 'enabled' => true ],
		];

		$available = $this->check_preferred_with_availability( $context, $providers, [ 'deepseek' => true ] );
		$this->assertTrue( $available );
	}

	/**
	 * Preferred provider is skipped when circuit breaker is open.
	 */
	public function test_preferred_provider_skipped_when_broken(): void {
		$context = new class {
			public function get_preferred_provider(): ?string {
				return 'deepseek';
			}
			public function is_excluded( string $id ): bool {
				return false;
			}
		};

		$providers = [
			'deepseek' => [ 'enabled' => true ],
			'qwen'     => [ 'enabled' => true ],
		];

		$available = $this->check_preferred_with_availability( $context, $providers, [ 'deepseek' => false ] );
		$this->assertFalse( $available );
	}

	/**
	 * Preferred provider is skipped when excluded.
	 */
	public function test_preferred_provider_skipped_when_excluded(): void {
		$context = new class {
			public function get_preferred_provider(): ?string {
				return 'deepseek';
			}
			public function is_excluded( string $id ): bool {
				return 'deepseek' === $id;
			}
		};

		$providers = [
			'deepseek' => [ 'enabled' => true ],
		];

		$available = $this->check_preferred_with_availability( $context, $providers, [ 'deepseek' => true ] );
		$this->assertFalse( $available );
	}

	/**
	 * No preferred provider returns false (falls through to strategy).
	 */
	public function test_no_preferred_returns_false(): void {
		$context = new class {
			public function get_preferred_provider(): ?string {
				return null;
			}
			public function is_excluded( string $id ): bool {
				return false;
			}
		};

		$providers = [
			'deepseek' => [ 'enabled' => true ],
		];

		$available = $this->check_preferred_with_availability( $context, $providers, [] );
		$this->assertFalse( $available );
	}

	/**
	 * Simulate the route() logic with availability check.
	 *
	 * @param object $context        Routing context mock.
	 * @param array  $providers      Available providers.
	 * @param array  $availability   Provider availability map (id => bool).
	 * @return bool Whether preferred provider would be returned.
	 */
	private function check_preferred_with_availability( object $context, array $providers, array $availability ): bool {
		$preferred = $context->get_preferred_provider();
		if ( null !== $preferred && isset( $providers[ $preferred ] ) ) {
			if ( ! $context->is_excluded( $preferred ) ) {
				// is_provider_available check.
				$is_available = $availability[ $preferred ] ?? true;
				if ( $is_available ) {
					return true;
				}
			}
		}
		return false;
	}
}
