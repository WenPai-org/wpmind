<?php
/**
 * HTML to Markdown Converter
 *
 * Converts HTML content to Markdown format.
 *
 * @package WPMind\GEO
 * @since 3.0.0
 */

namespace WPMind\GEO;

/**
 * Class HtmlToMarkdown
 *
 * Converts HTML to Markdown using regex-based approach.
 * For complex HTML, consider using a library like league/html-to-markdown.
 */
class HtmlToMarkdown {

	/**
	 * Convert HTML to Markdown.
	 *
	 * @param string $html The HTML content to convert.
	 * @return string Markdown content.
	 */
	public function convert( string $html ): string {
		// Process blocks first.
		$html = $this->process_blocks( $html );

		// Convert headings.
		$html = $this->convert_headings( $html );

		// Convert links.
		$html = $this->convert_links( $html );

		// Convert images.
		$html = $this->convert_images( $html );

		// Convert lists.
		$html = $this->convert_lists( $html );

		// Convert code blocks.
		$html = $this->convert_code( $html );

		// Convert blockquotes.
		$html = $this->convert_blockquotes( $html );

		// Convert emphasis.
		$html = $this->convert_emphasis( $html );

		// Convert paragraphs.
		$html = $this->convert_paragraphs( $html );

		// Clean up.
		$html = $this->cleanup( $html );

		return $html;
	}

	/**
	 * Process WordPress blocks (Gutenberg).
	 *
	 * @param string $html The HTML content.
	 * @return string Processed content.
	 */
	private function process_blocks( string $html ): string {
		// Remove block comments.
		$html = preg_replace( '/<!-- \/?wp:[^>]+ -->/', '', $html );

		// Handle figure/figcaption.
		$html = preg_replace(
			'/<figure[^>]*>(.*?)<figcaption[^>]*>(.*?)<\/figcaption><\/figure>/is',
			"$1\n*$2*\n",
			$html
		);

		$html = preg_replace( '/<\/?figure[^>]*>/', '', $html );

		return $html;
	}

	/**
	 * Convert headings.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown headings.
	 */
	private function convert_headings( string $html ): string {
		for ( $i = 6; $i >= 1; $i-- ) {
			$prefix = str_repeat( '#', $i );
			$html   = preg_replace(
				"/<h{$i}[^>]*>(.*?)<\/h{$i}>/is",
				"\n\n{$prefix} $1\n\n",
				$html
			);
		}
		return $html;
	}

	/**
	 * Convert links.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown links.
	 */
	private function convert_links( string $html ): string {
		return preg_replace(
			'/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			'[$2]($1)',
			$html
		);
	}

	/**
	 * Convert images.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown images.
	 */
	private function convert_images( string $html ): string {
		// With alt text.
		$html = preg_replace(
			'/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']([^"\']*)["\'][^>]*\/?>/is',
			'![$2]($1)',
			$html
		);

		// Without alt text.
		$html = preg_replace(
			'/<img[^>]+src=["\']([^"\']+)["\'][^>]*\/?>/is',
			'![]($1)',
			$html
		);

		return $html;
	}

	/**
	 * Convert lists.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown lists.
	 */
	private function convert_lists( string $html ): string {
		// Unordered lists.
		$html = preg_replace( '/<ul[^>]*>/i', "\n", $html );
		$html = preg_replace( '/<\/ul>/i', "\n", $html );

		// Ordered lists.
		$html = preg_replace( '/<ol[^>]*>/i', "\n", $html );
		$html = preg_replace( '/<\/ol>/i', "\n", $html );

		// List items - unordered.
		$html = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $html );

		return $html;
	}

	/**
	 * Convert code blocks.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown code.
	 */
	private function convert_code( string $html ): string {
		// Code blocks (pre > code).
		$html = preg_replace_callback(
			'/<pre[^>]*><code[^>]*(?:class=["\'][^"\']*language-([^"\'\s]+)[^"\']*["\'])?[^>]*>(.*?)<\/code><\/pre>/is',
			function ( $matches ) {
				$lang = $matches[1] ?? '';
				$code = html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' );
				return "\n\n```{$lang}\n{$code}\n```\n\n";
			},
			$html
		);

		// Standalone pre.
		$html = preg_replace_callback(
			'/<pre[^>]*>(.*?)<\/pre>/is',
			function ( $matches ) {
				$code = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
				return "\n\n```\n{$code}\n```\n\n";
			},
			$html
		);

		// Inline code.
		$html = preg_replace(
			'/<code[^>]*>(.*?)<\/code>/is',
			'`$1`',
			$html
		);

		return $html;
	}

	/**
	 * Convert blockquotes.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown blockquotes.
	 */
	private function convert_blockquotes( string $html ): string {
		return preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/is',
			function ( $matches ) {
				$content = trim( strip_tags( $matches[1] ) );
				$lines   = explode( "\n", $content );
				$quoted  = array_map(
					function ( $line ) {
						return '> ' . trim( $line );
					},
					$lines
				);
				return "\n\n" . implode( "\n", $quoted ) . "\n\n";
			},
			$html
		);
	}

	/**
	 * Convert emphasis (bold, italic).
	 *
	 * @param string $html The HTML content.
	 * @return string Content with Markdown emphasis.
	 */
	private function convert_emphasis( string $html ): string {
		// Bold.
		$html = preg_replace( '/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $html );

		// Italic.
		$html = preg_replace( '/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $html );

		// Strikethrough.
		$html = preg_replace( '/<(del|s|strike)[^>]*>(.*?)<\/\1>/is', '~~$2~~', $html );

		return $html;
	}

	/**
	 * Convert paragraphs.
	 *
	 * @param string $html The HTML content.
	 * @return string Content with proper paragraph spacing.
	 */
	private function convert_paragraphs( string $html ): string {
		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "\n\n$1\n\n", $html );
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		return $html;
	}

	/**
	 * Clean up the converted content.
	 *
	 * @param string $html The content to clean.
	 * @return string Cleaned content.
	 */
	private function cleanup( string $html ): string {
		// Remove remaining HTML tags.
		$html = strip_tags( $html );

		// Decode HTML entities.
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );

		// Normalize line breaks.
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		// Trim whitespace.
		$html = trim( $html );

		return $html;
	}
}
