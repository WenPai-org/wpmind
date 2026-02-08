<?php
/**
 * Tests for ModelMapper
 *
 * @package WPMind\Tests\ApiGateway\Transform
 */

declare(strict_types=1);

// Stub WordPress get_option in the ModelMapper namespace for unit testing.
namespace WPMind\Modules\ApiGateway\Transform {
	if ( ! function_exists( 'WPMind\Modules\ApiGateway\Transform\get_option' ) ) {
		function get_option( string $key, $default = false ) {
			global $wpmind_test_options;
			return $wpmind_test_options[ $key ] ?? $default;
		}
	}
}

namespace WPMind\Tests\ApiGateway\Transform {

	require_once __DIR__ . '/../../../modules/api-gateway/includes/Transform/ModelMapper.php';

	use WPMind\Modules\ApiGateway\Transform\ModelMapper;
	use PHPUnit\Framework\TestCase;

	/**
	 * Test class for ModelMapper model resolution.
	 */
	class ModelMapperTest extends TestCase {

		/**
		 * Reset global test options before each test.
		 */
		protected function setUp(): void {
			global $wpmind_test_options;
			$wpmind_test_options = [];
		}

		/**
		 * Test resolve returns correct provider/model for a known model.
		 */
		public function test_resolve_known_model(): void {
			$result = ModelMapper::resolve( 'gpt-4o' );

			$this->assertNotNull( $result );
			$this->assertSame( 'openai', $result['provider'] );
			$this->assertSame( 'gpt-4o', $result['model'] );
		}

		/**
		 * Test resolve returns null for an unknown model.
		 */
		public function test_resolve_unknown_model_returns_null(): void {
			$result = ModelMapper::resolve( 'nonexistent-model' );

			$this->assertNull( $result );
		}

		/**
		 * Test resolve returns auto provider/model for 'auto'.
		 */
		public function test_resolve_auto_returns_auto(): void {
			$result = ModelMapper::resolve( 'auto' );

			$this->assertNotNull( $result );
			$this->assertSame( 'auto', $result['provider'] );
			$this->assertSame( 'auto', $result['model'] );
		}

		/**
		 * Test resolve uses alias over default mapping.
		 */
		public function test_resolve_uses_alias_over_default(): void {
			global $wpmind_test_options;
			$wpmind_test_options['wpmind_gateway_model_aliases'] = [
				'gpt-4o' => [
					'provider' => 'custom-provider',
					'model'    => 'custom-model',
				],
			];

			$result = ModelMapper::resolve( 'gpt-4o' );

			$this->assertNotNull( $result );
			$this->assertSame( 'custom-provider', $result['provider'] );
			$this->assertSame( 'custom-model', $result['model'] );
		}

		/**
		 * Test get_available_models includes 'auto'.
		 */
		public function test_get_available_models_includes_auto(): void {
			$models = ModelMapper::get_available_models();

			$this->assertContains( 'auto', $models );
		}

		/**
		 * Test get_available_models includes default models.
		 */
		public function test_get_available_models_includes_defaults(): void {
			$models = ModelMapper::get_available_models();

			$this->assertContains( 'gpt-4o', $models );
			$this->assertContains( 'deepseek-chat', $models );
			$this->assertContains( 'qwen-max', $models );
		}
	}
}