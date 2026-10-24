<?php
/**
 * Schema validation for bundled models.json.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Catalog\ModelCatalog;
use WPMind\Provider\ProviderDefinitions;

/**
 * @covers \WPMind\Provider\Catalog\ModelCatalog
 */
final class ModelCatalogSchemaTest extends TestCase {

	protected function setUp(): void {
		ModelCatalog::reset_instance();
	}

	public function test_catalog_file_readable_and_versioned(): void {
		$path = ModelCatalog::catalog_path();
		$this->assertFileExists( $path );
		$raw = ModelCatalog::instance()->raw();
		$this->assertNotSame( '', $raw['catalog_version'] ?? '' );
		$this->assertArrayHasKey( 'providers', $raw );
	}

	public function test_every_known_provider_has_default_and_models(): void {
		$catalog = ModelCatalog::instance();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$block = $catalog->provider( $id );
			$this->assertNotNull( $block, "missing provider $id" );
			$this->assertNotSame( '', $catalog->default_model( $id ), "empty default for $id" );
			$this->assertNotEmpty( $catalog->recommended_ids( $id ), "no recommended models for $id" );
			$default = (string) ( $block['default_model'] ?? '' );
			$this->assertArrayHasKey( $default, $block['models'] ?? [], "default_model missing in models for $id" );
		}
	}

	public function test_model_entries_have_required_fields(): void {
		$raw = ModelCatalog::instance()->raw();
		foreach ( (array) ( $raw['providers'] ?? [] ) as $pid => $block ) {
			foreach ( (array) ( $block['models'] ?? [] ) as $mid => $meta ) {
				$this->assertIsArray( $meta, "$pid/$mid" );
				$this->assertArrayHasKey( 'name', $meta );
				$this->assertArrayHasKey( 'capabilities', $meta );
				$this->assertIsArray( $meta['capabilities'] );
				$this->assertContains( $meta['status'] ?? null, [ 'active', 'deprecated', 'retired' ], "$pid/$mid status" );
				$this->assertArrayHasKey( 'replaced_by', $meta );
				$this->assertArrayHasKey( 'pricing', $meta );
				$pricing = $meta['pricing'];
				if ( null === $pricing ) {
					continue;
				}
				$this->assertIsArray( $pricing, "$pid/$mid pricing" );
				$this->assertArrayHasKey( 'as_of', $pricing );
				$this->assertArrayHasKey( 'source', $pricing );
				$this->assertTrue(
					( null === $pricing['input'] && null === $pricing['output'] )
					|| ( is_numeric( $pricing['input'] ) && is_numeric( $pricing['output'] ) ),
					"$pid/$mid pricing input/output"
				);
			}
		}
	}

	public function test_replaced_by_has_no_cycles(): void {
		$catalog = ModelCatalog::instance();
		$raw     = $catalog->raw();
		foreach ( (array) ( $raw['providers'] ?? [] ) as $pid => $block ) {
			$models = (array) ( $block['models'] ?? [] );
			foreach ( $models as $mid => $meta ) {
				$seen = [];
				$cur  = $mid;
				for ( $i = 0; $i < 10; $i++ ) {
					if ( isset( $seen[ $cur ] ) ) {
						$this->fail( "cycle in $pid starting at $mid" );
					}
					$seen[ $cur ] = true;
					$repl         = $models[ $cur ]['replaced_by'] ?? null;
					if ( ! is_string( $repl ) || '' === $repl ) {
						break;
					}
					$this->assertArrayHasKey( $repl, $models, "$pid $cur replaced_by missing" );
					$cur = $repl;
				}
			}
		}
	}

	public function test_default_model_is_not_retired(): void {
		$catalog = ModelCatalog::instance();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$default = $catalog->default_model( $id );
			$meta    = $catalog->model_meta( $id, $default );
			if ( null === $meta ) {
				continue;
			}
			$this->assertNotSame( 'retired', $meta['status'] ?? '', "$id default is retired" );
		}
	}
}
