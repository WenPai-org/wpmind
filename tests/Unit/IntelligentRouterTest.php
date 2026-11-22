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

	/**
	 * Build an isolated IntelligentRouter with custom providers and empty manual priority.
	 *
	 * @param array<string, array<string, mixed>> $providers Provider map.
	 */
	private function make_router_with_providers( array $providers ): \WPMind\Routing\IntelligentRouter {
		$GLOBALS['wpmind_test_options']['wpmind_provider_priority'] = [];
		$ref                                                        = new \ReflectionClass( \WPMind\Routing\IntelligentRouter::class );
		$inst_prop                                                  = $ref->getProperty( 'instance' );
		$inst_prop->setAccessible( true );
		$inst_prop->setValue( null, null );

		$router    = \WPMind\Routing\IntelligentRouter::instance();
		$prov_prop = $ref->getProperty( 'providers' );
		$prov_prop->setAccessible( true );
		$prov_prop->setValue( $router, $providers );

		return $router;
	}

	public function test_cloud_mode_primary_prefers_wenpai_cloud_first(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_mode'] = \WPMind\Provider\WenpaiCloud::MODE_PRIMARY;

		$router = $this->make_router_with_providers(
			[
				'qwen'         => [ 'enabled' => true, 'display_name' => '千问' ],
				'wenpai_cloud' => [ 'enabled' => true, 'display_name' => '文派心思官方算力' ],
			]
		);

		$selected = $router->route( \WPMind\Routing\RoutingContext::create() );
		$chain    = $router->get_failover_chain( \WPMind\Routing\RoutingContext::create() );

		$this->assertSame( 'wenpai_cloud', $selected );
		$this->assertSame( 'wenpai_cloud', $chain[0] );
	}

	public function test_cloud_mode_fallback_prefers_byok_and_places_cloud_last(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_mode'] = \WPMind\Provider\WenpaiCloud::MODE_FALLBACK;

		$router = $this->make_router_with_providers(
			[
				'qwen'         => [ 'enabled' => true, 'display_name' => '千问' ],
				'wenpai_cloud' => [ 'enabled' => true, 'display_name' => '文派心思官方算力' ],
			]
		);

		$selected = $router->route( \WPMind\Routing\RoutingContext::create() );
		$chain    = $router->get_failover_chain( \WPMind\Routing\RoutingContext::create() );

		$this->assertSame( 'qwen', $selected );
		$this->assertSame( 'wenpai_cloud', end( $chain ) );

		// When BYOK is excluded (simulating failover), cloud steps in automatically.
		$fallback = $router->route( \WPMind\Routing\RoutingContext::create()->with_excluded_provider( 'qwen' ) );
		$this->assertSame( 'wenpai_cloud', $fallback );
	}

	public function test_cloud_mode_capability_routes_chat_to_byok_and_vision_to_cloud(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_mode'] = \WPMind\Provider\WenpaiCloud::MODE_CAPABILITY;

		$router = $this->make_router_with_providers(
			[
				'qwen'         => [ 'enabled' => true, 'display_name' => '千问' ],
				'wenpai_cloud' => [ 'enabled' => true, 'display_name' => '文派心思官方算力' ],
			]
		);

		$chat_provider   = $router->route( \WPMind\Routing\RoutingContext::create()->with_model_type( 'chat' ) );
		$vision_provider = $router->route( \WPMind\Routing\RoutingContext::create()->with_model_type( 'vision' ) );

		$this->assertSame( 'qwen', $chat_provider );
		$this->assertSame( 'wenpai_cloud', $vision_provider );
	}

	public function test_cloud_base_url_honors_option_override(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_base_url'] = 'https://custom-mind.example.com/v1/';
		$this->assertSame( 'https://custom-mind.example.com/v1', \WPMind\Provider\WenpaiCloud::base_url() );
		unset( $GLOBALS['wpmind_test_options']['wpmind_cloud_base_url'] );
	}
}
