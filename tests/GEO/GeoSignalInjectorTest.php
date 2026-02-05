<?php
/**
 * Tests for GeoSignalInjector
 *
 * @package WPMind\Tests\GEO
 */

namespace WPMind\Tests\GEO;

use WPMind\Modules\Geo\GeoSignalInjector;
use PHPUnit\Framework\TestCase;

/**
 * Test class for GeoSignalInjector.
 *
 * Note: These tests require WordPress test framework for full functionality.
 * Basic structure tests can run without WordPress.
 */
class GeoSignalInjectorTest extends TestCase {

	/**
	 * Test inject preserves associative array keys.
	 */
	public function test_inject_preserves_array_keys(): void {
		// Skip if WordPress functions not available.
		if ( ! function_exists( 'get_the_author_meta' ) ) {
			$this->markTestSkipped( 'WordPress functions not available.' );
		}

		$injector = new GeoSignalInjector();
		$sections = array(
			'title'   => '# Test Title',
			'content' => 'Test content here.',
		);

		// Create mock post.
		$post = $this->createMock( \WP_Post::class );
		$post->ID          = 1;
		$post->post_author = 1;

		$result = $injector->inject( $sections, $post );

		// Check that original keys are preserved.
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'content', $result );

		// Check that new keys are added.
		$this->assertArrayHasKey( 'wpmind_authority', $result );
		$this->assertArrayHasKey( 'wpmind_citation', $result );
	}

	/**
	 * Test authority signal format.
	 */
	public function test_authority_signal_contains_required_fields(): void {
		if ( ! function_exists( 'get_the_author_meta' ) ) {
			$this->markTestSkipped( 'WordPress functions not available.' );
		}

		$injector = new GeoSignalInjector();
		$sections = array( 'content' => 'Test' );

		$post = $this->createMock( \WP_Post::class );
		$post->ID          = 1;
		$post->post_author = 1;

		$result = $injector->inject( $sections, $post );

		// Authority signal should contain YAML front matter.
		$this->assertStringContainsString( '---', $result['wpmind_authority'] );
		$this->assertStringContainsString( '作者:', $result['wpmind_authority'] );
		$this->assertStringContainsString( '发布日期:', $result['wpmind_authority'] );
		$this->assertStringContainsString( '最后更新:', $result['wpmind_authority'] );
	}

	/**
	 * Test citation format.
	 */
	public function test_citation_contains_required_elements(): void {
		if ( ! function_exists( 'get_the_title' ) ) {
			$this->markTestSkipped( 'WordPress functions not available.' );
		}

		$injector = new GeoSignalInjector();
		$sections = array( 'content' => 'Test' );

		$post = $this->createMock( \WP_Post::class );
		$post->ID          = 1;
		$post->post_author = 1;

		$result = $injector->inject( $sections, $post );

		// Citation should contain required elements.
		$this->assertStringContainsString( '引用本文', $result['wpmind_citation'] );
		$this->assertStringContainsString( 'APA', $result['wpmind_citation'] );
	}

	/**
	 * Test structured data generation.
	 */
	public function test_get_structured_data_returns_valid_schema(): void {
		if ( ! function_exists( 'get_the_title' ) ) {
			$this->markTestSkipped( 'WordPress functions not available.' );
		}

		$injector = new GeoSignalInjector();

		$post = $this->createMock( \WP_Post::class );
		$post->ID          = 1;
		$post->post_author = 1;

		$data = $injector->get_structured_data( $post );

		// Check required Schema.org fields.
		$this->assertEquals( 'Article', $data['@type'] );
		$this->assertArrayHasKey( 'headline', $data );
		$this->assertArrayHasKey( 'author', $data );
		$this->assertArrayHasKey( 'datePublished', $data );
		$this->assertArrayHasKey( 'publisher', $data );
	}
}
