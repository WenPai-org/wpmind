<?php
/**
 * ModelCatalog unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Catalog\ModelCatalog;
use WPMind\Usage\Pricing;

/**
 * @covers \WPMind\Provider\Catalog\ModelCatalog
 */
final class ModelCatalogTest extends TestCase {

	protected function setUp(): void {
		ModelCatalog::reset_instance();
	}

	protected function tearDown(): void {
		ModelCatalog::reset_instance();
	}

	public function test_default_model_skips_retired(): void {
		$catalog = ModelCatalog::instance();
		$this->assertSame( 'deepseek-chat', $catalog->default_model( 'deepseek' ) );
		$this->assertSame( 'gpt-4o', $catalog->default_model( 'openai' ) );
		$this->assertNotContains( 'deepseek-coder', $catalog->recommended_ids( 'deepseek' ) );
		$this->assertNotContains( 'gpt-3.5-turbo', $catalog->recommended_ids( 'openai' ) );
		$this->assertNotContains( 'abab5.5-chat', $catalog->recommended_ids( 'minimax' ) );
	}

	public function test_resolve_retired_with_replacement(): void {
		$res = ModelCatalog::instance()->resolve( 'deepseek', 'deepseek-coder' );
		$this->assertSame( 'deepseek-chat', $res->model );
		$this->assertSame( 'deepseek-coder', $res->remapped_from );
		$this->assertTrue( $res->was_remapped() );
		$this->assertContains( $res->reason, [ 'retired_replaced', 'alias' ] );
	}

	public function test_resolve_alias(): void {
		$res = ModelCatalog::instance()->resolve( 'openai', 'gpt-3.5-turbo' );
		$this->assertSame( 'gpt-4o-mini', $res->model );
		$this->assertTrue( $res->was_remapped() );
	}

	public function test_resolve_unknown_passthrough(): void {
		$res = ModelCatalog::instance()->resolve( 'deepseek', 'my-custom-endpoint-id' );
		$this->assertSame( 'my-custom-endpoint-id', $res->model );
		$this->assertFalse( $res->was_remapped() );
		$this->assertSame( 'unknown_passthrough', $res->reason );
	}

	public function test_resolve_auto_uses_default(): void {
		$res = ModelCatalog::instance()->resolve( 'qwen', 'auto' );
		$this->assertSame( 'qwen-plus', $res->model );
		$this->assertSame( 'user_default', $res->reason );
	}

	public function test_for_provider_capability_filter(): void {
		$catalog = ModelCatalog::instance();
		$vision  = $catalog->for_provider( 'google', 'vision', true );
		$this->assertArrayHasKey( 'gemini-2.0-flash', $vision );
		$text = $catalog->for_provider( 'deepseek', 'text' );
		$this->assertArrayHasKey( 'deepseek-chat', $text );
		$this->assertArrayNotHasKey( 'deepseek-coder', $text );
	}

	public function test_for_provider_map_excludes_retired(): void {
		$map = ModelCatalog::instance()->for_provider_map( 'minimax' );
		$this->assertArrayHasKey( 'abab6.5s-chat', $map );
		$this->assertArrayNotHasKey( 'abab5.5-chat', $map );
	}

	public function test_pricing_from_catalog_then_fallback(): void {
		$catalog = ModelCatalog::instance();
		$price   = $catalog->pricing( 'deepseek', 'deepseek-chat' );
		$this->assertIsArray( $price );
		$this->assertSame( 1.0, $price['input'] );

		// Null catalog price falls back to Pricing::DATA via calculate_cost.
		$cost = Pricing::calculate_cost( 'deepseek', 'deepseek-chat', 1_000_000, 1_000_000 );
		$this->assertEquals( 3.0, $cost );
	}

	public function test_hardcoded_retired_ids_absent_from_php_sources(): void {
		$root  = dirname( __DIR__, 2 );
		$paths = [
			$root . '/wpmind.php',
			$root . '/includes',
			$root . '/modules',
		];
		$patterns = [
			'gpt-3\.5-turbo',
			'claude-3-opus-20240229',
			'gemini-1\.5-pro',
			'deepseek-coder',
			'abab5\.5-chat',
		];
		$allow = [
			'includes/Provider/Catalog/models.json',
			'includes/Provider/ProviderDefinitions.php', // historical defaults
			'includes/Usage/Pricing.php', // legacy DATA fallback
			'modules/api-gateway/includes/Transform/ModelMapper.php', // D10 legacy aliases
			'tests/Unit/ModelCatalogTest.php',
			'tests/Unit/ModelCatalogSchemaTest.php',
			'tests/Unit/MigrationEndpointsV2Test.php',
			'tests/Unit/EndpointSchemaWiringTest.php',
			'tests/Unit/EndpointRepositoryTest.php',
			'tests/Unit/PricingTest.php',
		];
		$hits = [];
		foreach ( $paths as $base ) {
			$iterator = is_dir( $base )
				? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base ) )
				: [ new SplFileInfo( $base ) ];
			foreach ( $iterator as $file ) {
				if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
					continue;
				}
				$ext = $file->getExtension();
				if ( ! in_array( $ext, [ 'php', 'js' ], true ) ) {
					continue;
				}
				$rel = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
				foreach ( $allow as $a ) {
					if ( str_ends_with( $rel, $a ) || $rel === $a ) {
						continue 2;
					}
				}
				$content = file_get_contents( $file->getPathname() );
				if ( false === $content ) {
					continue;
				}
				foreach ( $patterns as $pat ) {
					if ( preg_match( '/' . $pat . '/', $content ) ) {
						$hits[] = $rel . ' ~ ' . $pat;
					}
				}
			}
		}
		$this->assertSame( [], $hits, 'Retired model ids must not be hardcoded outside allowlist: ' . implode( ', ', $hits ) );
	}
}
