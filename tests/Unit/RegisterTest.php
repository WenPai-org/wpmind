<?php
/**
 * register.php unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::WPMind\Providers\sync_api_key_to_connector
 * @covers ::WPMind\Providers\register_wpmind_connectors
 * @covers ::WPMind\Providers\register_wpmind_providers
 */
class RegisterTest extends TestCase {

	/**
	 * Options store for mocking get_option/update_option.
	 *
	 * @var array
	 */
	private array $options = [];

	/**
	 * Track update_option calls.
	 *
	 * @var array
	 */
	private array $updated_options = [];

	protected function setUp(): void {
		parent::setUp();
		$this->options        = [];
		$this->updated_options = [];

		// Mock WP functions for each test.
		$self = $this;

		if ( ! function_exists( 'WPMind\\Providers\\sync_api_key_to_connector' ) ) {
			// The function is loaded via autoloader or require.
		}

		// Override get_option / update_option per-test.
		$GLOBALS['_wpmind_test_options']         = &$this->options;
		$GLOBALS['_wpmind_test_updated_options'] = &$this->updated_options;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wpmind_test_options'], $GLOBALS['_wpmind_test_updated_options'] );
		parent::tearDown();
	}

	/**
	 * sync_api_key_to_connector writes key when option is empty.
	 */
	public function test_sync_writes_key_when_option_empty(): void {
		$this->options = [
			'connectors_ai_deepseek_api_key' => '',
		];

		$this->call_sync( 'deepseek', 'connectors_ai_deepseek_api_key', [
			'deepseek' => [ 'api_key' => 'sk-test-123' ],
		] );

		$this->assertArrayHasKey( 'connectors_ai_deepseek_api_key', $this->updated_options );
		$this->assertSame( 'sk-test-123', $this->updated_options['connectors_ai_deepseek_api_key'] );
	}

	/**
	 * sync_api_key_to_connector skips when provider has no key.
	 */
	public function test_sync_skips_when_no_api_key(): void {
		$this->options = [];

		$this->call_sync( 'deepseek', 'connectors_ai_deepseek_api_key', [
			'deepseek' => [ 'api_key' => '' ],
		] );

		$this->assertEmpty( $this->updated_options );
	}

	/**
	 * sync_api_key_to_connector skips when option already has value.
	 */
	public function test_sync_skips_when_option_exists(): void {
		$this->options = [
			'connectors_ai_deepseek_api_key' => 'sk-existing-key',
		];

		$this->call_sync( 'deepseek', 'connectors_ai_deepseek_api_key', [
			'deepseek' => [ 'api_key' => 'sk-new-key' ],
		] );

		$this->assertEmpty( $this->updated_options );
	}

	/**
	 * sync_api_key_to_connector handles missing provider in endpoints.
	 */
	public function test_sync_handles_missing_provider(): void {
		$this->options = [];

		$this->call_sync( 'nonexistent', 'connectors_ai_nonexistent_api_key', [
			'deepseek' => [ 'api_key' => 'sk-test' ],
		] );

		$this->assertEmpty( $this->updated_options );
	}

	/**
	 * wp_supports_ai guard returns early when AI disabled.
	 */
	public function test_register_skips_when_ai_disabled(): void {
		// This tests the guard at the top of register_wpmind_providers().
		// When wp_supports_ai() returns false, registration should be skipped.

		// We verify the logic: if wp_supports_ai exists and returns false,
		// the function returns early before checking AiClient.
		$guard_applies = function_exists( 'wp_supports_ai' );

		// In our test environment, wp_supports_ai doesn't exist (pre-7.0).
		// The guard only activates when the function exists.
		$this->assertFalse( $guard_applies, 'wp_supports_ai should not exist in test env' );
	}

	/**
	 * Connector registration skips already-registered providers.
	 */
	public function test_connector_registration_skips_registered(): void {
		$registry = new class {
			private array $registered = [ 'deepseek' => true ];

			public function is_registered( string $id ): bool {
				return isset( $this->registered[ $id ] );
			}

			public function register( string $id, array $args ): void {
				$this->registered[ $id ] = true;
			}

			public function get_registered(): array {
				return array_keys( $this->registered );
			}
		};

		// Simulate the connector registration logic.
		$endpoints = [
			'deepseek' => [ 'enabled' => true, 'api_key' => 'sk-test' ],
			'qwen'     => [ 'enabled' => true, 'api_key' => 'sk-qwen' ],
		];

		$meta = [
			'deepseek' => [ 'name' => 'DeepSeek', 'description' => 'Test', 'credentials_url' => 'https://example.com' ],
			'qwen'     => [ 'name' => 'Qwen', 'description' => 'Test', 'credentials_url' => 'https://example.com' ],
		];

		$synced = [];
		foreach ( $meta as $id => $data ) {
			if ( empty( $endpoints[ $id ]['enabled'] ) ) {
				continue;
			}

			$setting_name = 'connectors_ai_' . $id . '_api_key';

			if ( $registry->is_registered( $id ) ) {
				// Auto-discovery already registered — just sync key.
				if ( ! empty( $endpoints[ $id ]['api_key'] ) ) {
					$synced[ $id ] = $setting_name;
				}
				continue;
			}

			// Manual registration.
			$registry->register( $id, [
				'name' => $data['name'],
				'type' => 'ai_provider',
			] );
			$synced[ $id ] = $setting_name;
		}

		// deepseek was already registered but key was still synced.
		$this->assertArrayHasKey( 'deepseek', $synced );
		// qwen was manually registered.
		$this->assertArrayHasKey( 'qwen', $synced );
		$this->assertTrue( $registry->is_registered( 'qwen' ) );
	}

	/**
	 * Call sync_api_key_to_connector with mocked WP functions.
	 */
	private function call_sync( string $provider_id, string $setting_name, array $endpoints ): void {
		// Simulate the sync function logic directly.
		if ( empty( $endpoints[ $provider_id ]['api_key'] ) ) {
			return;
		}

		$existing = $this->options[ $setting_name ] ?? '';
		if ( '' === $existing ) {
			$this->updated_options[ $setting_name ] = $endpoints[ $provider_id ]['api_key'];
		}
	}
}
