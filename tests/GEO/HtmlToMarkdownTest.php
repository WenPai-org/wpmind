<?php
/**
 * Tests for HtmlToMarkdown
 *
 * @package WPMind\Tests\GEO
 */

namespace WPMind\Tests\GEO;

use WPMind\GEO\HtmlToMarkdown;
use PHPUnit\Framework\TestCase;

/**
 * Test class for HtmlToMarkdown converter.
 */
class HtmlToMarkdownTest extends TestCase {

	/**
	 * @var HtmlToMarkdown
	 */
	private HtmlToMarkdown $converter;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		$this->converter = new HtmlToMarkdown();
	}

	/**
	 * Test heading conversion.
	 */
	public function test_converts_headings(): void {
		$html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '# Title', $md );
		$this->assertStringContainsString( '## Subtitle', $md );
		$this->assertStringContainsString( '### Section', $md );
	}

	/**
	 * Test link conversion.
	 */
	public function test_converts_links(): void {
		$html = '<a href="https://example.com">Example</a>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '[Example](https://example.com)', $md );
	}

	/**
	 * Test image conversion.
	 */
	public function test_converts_images(): void {
		$html = '<img src="image.jpg" alt="Test Image">';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '![Test Image](image.jpg)', $md );
	}

	/**
	 * Test image without alt text.
	 */
	public function test_converts_images_without_alt(): void {
		$html = '<img src="image.jpg">';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '![](image.jpg)', $md );
	}

	/**
	 * Test unordered list conversion.
	 */
	public function test_converts_unordered_lists(): void {
		$html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '- Item 1', $md );
		$this->assertStringContainsString( '- Item 2', $md );
	}

	/**
	 * Test code block conversion.
	 */
	public function test_converts_code_blocks(): void {
		$html = '<pre><code class="language-php">echo "Hello";</code></pre>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '```php', $md );
		$this->assertStringContainsString( 'echo "Hello";', $md );
		$this->assertStringContainsString( '```', $md );
	}

	/**
	 * Test inline code conversion.
	 */
	public function test_converts_inline_code(): void {
		$html = 'Use <code>$variable</code> here.';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '`$variable`', $md );
	}

	/**
	 * Test blockquote conversion.
	 */
	public function test_converts_blockquotes(): void {
		$html = '<blockquote>This is a quote.</blockquote>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '> This is a quote.', $md );
	}

	/**
	 * Test bold text conversion.
	 */
	public function test_converts_bold(): void {
		$html = '<strong>Bold text</strong>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '**Bold text**', $md );
	}

	/**
	 * Test italic text conversion.
	 */
	public function test_converts_italic(): void {
		$html = '<em>Italic text</em>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '*Italic text*', $md );
	}

	/**
	 * Test strikethrough conversion.
	 */
	public function test_converts_strikethrough(): void {
		$html = '<del>Deleted text</del>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '~~Deleted text~~', $md );
	}

	/**
	 * Test paragraph conversion.
	 */
	public function test_converts_paragraphs(): void {
		$html = '<p>First paragraph.</p><p>Second paragraph.</p>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( 'First paragraph.', $md );
		$this->assertStringContainsString( 'Second paragraph.', $md );
	}

	/**
	 * Test WordPress block comment removal.
	 */
	public function test_removes_wordpress_block_comments(): void {
		$html = '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->';
		$md   = $this->converter->convert( $html );

		$this->assertStringNotContainsString( 'wp:paragraph', $md );
		$this->assertStringContainsString( 'Content', $md );
	}

	/**
	 * Test HTML entity decoding.
	 */
	public function test_decodes_html_entities(): void {
		$html = '<p>&amp; &lt; &gt; &quot;</p>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '&', $md );
		$this->assertStringContainsString( '<', $md );
		$this->assertStringContainsString( '>', $md );
	}

	/**
	 * Test empty input.
	 */
	public function test_handles_empty_input(): void {
		$md = $this->converter->convert( '' );
		$this->assertEquals( '', $md );
	}

	/**
	 * Test complex nested HTML.
	 */
	public function test_handles_nested_html(): void {
		$html = '<p>Text with <strong>bold and <em>italic</em></strong> content.</p>';
		$md   = $this->converter->convert( $html );

		$this->assertStringContainsString( '**bold and *italic***', $md );
	}
}
