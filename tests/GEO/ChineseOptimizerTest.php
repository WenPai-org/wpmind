<?php
/**
 * Tests for ChineseOptimizer
 *
 * @package WPMind\Tests\GEO
 */

namespace WPMind\Tests\GEO;

use WPMind\GEO\ChineseOptimizer;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ChineseOptimizer.
 */
class ChineseOptimizerTest extends TestCase {

	/**
	 * Test Chinese-English spacing.
	 */
	public function test_adds_space_between_chinese_and_english(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array( 'content' => '这是WordPress插件' );
		$result    = $optimizer->optimize( $sections );

		$this->assertEquals( '这是 WordPress 插件', $result['content'] );
	}

	/**
	 * Test Chinese-number spacing.
	 */
	public function test_adds_space_between_chinese_and_numbers(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array( 'content' => '版本号是2.0' );
		$result    = $optimizer->optimize( $sections );

		$this->assertEquals( '版本号是 2.0', $result['content'] );
	}

	/**
	 * Test recursive array processing.
	 */
	public function test_handles_nested_arrays(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array(
			'title'   => '标题Title',
			'nested'  => array(
				'content' => '内容Content',
				'deep'    => array(
					'text' => '深层Deep',
				),
			),
		);

		$result = $optimizer->optimize( $sections );

		$this->assertEquals( '标题 Title', $result['title'] );
		$this->assertEquals( '内容 Content', $result['nested']['content'] );
		$this->assertEquals( '深层 Deep', $result['nested']['deep']['text'] );
	}

	/**
	 * Test non-string values are preserved.
	 */
	public function test_preserves_non_string_values(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array(
			'count'   => 42,
			'enabled' => true,
			'content' => '测试Test',
		);

		$result = $optimizer->optimize( $sections );

		$this->assertEquals( 42, $result['count'] );
		$this->assertTrue( $result['enabled'] );
		$this->assertEquals( '测试 Test', $result['content'] );
	}

	/**
	 * Test code content is not modified.
	 */
	public function test_skips_code_content(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array(
			'code' => '```php
function test() {
    $var = "value";
}
```',
		);

		$result = $optimizer->optimize( $sections );

		// Code should not be modified.
		$this->assertStringContainsString( '$var', $result['code'] );
	}

	/**
	 * Test punctuation normalization when enabled.
	 */
	public function test_punctuation_normalization(): void {
		$optimizer = new ChineseOptimizer( true ); // Enable punctuation normalization.
		$sections  = array( 'content' => '你好，世界！' );
		$result    = $optimizer->optimize( $sections );

		$this->assertEquals( '你好, 世界! ', $result['content'] );
	}

	/**
	 * Test empty string handling.
	 */
	public function test_handles_empty_strings(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array( 'content' => '' );
		$result    = $optimizer->optimize( $sections );

		$this->assertEquals( '', $result['content'] );
	}

	/**
	 * Test pure English text is not modified.
	 */
	public function test_pure_english_unchanged(): void {
		$optimizer = new ChineseOptimizer();
		$sections  = array( 'content' => 'Hello World 123' );
		$result    = $optimizer->optimize( $sections );

		$this->assertEquals( 'Hello World 123', $result['content'] );
	}
}
