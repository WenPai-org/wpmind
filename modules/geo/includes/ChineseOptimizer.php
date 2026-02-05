<?php
/**
 * Chinese Content Optimizer
 *
 * Optimizes Chinese content for better readability and AI parsing.
 *
 * @package WPMind\Modules\Geo
 * @since 3.0.0
 */

namespace WPMind\Modules\Geo;

/**
 * Class ChineseOptimizer
 *
 * Handles Chinese content optimization including:
 * - Adding spaces between Chinese and English/numbers
 * - Normalizing punctuation (optional)
 */
class ChineseOptimizer {

	/**
	 * Whether to normalize punctuation.
	 *
	 * @var bool
	 */
	private bool $normalize_punctuation;

	/**
	 * Constructor.
	 *
	 * @param bool $normalize_punctuation Whether to normalize Chinese punctuation.
	 */
	public function __construct( bool $normalize_punctuation = false ) {
		$this->normalize_punctuation = $normalize_punctuation;
	}

	/**
	 * Optimize sections array.
	 *
	 * Recursively processes array structures, only optimizing string values.
	 * This fixes the Codex review issue about assuming pure strings.
	 *
	 * @param array $sections The sections to optimize.
	 * @return array Optimized sections.
	 */
	public function optimize( array $sections ): array {
		foreach ( $sections as $key => $content ) {
			if ( is_string( $content ) ) {
				$sections[ $key ] = $this->optimize_text( $content );
			} elseif ( is_array( $content ) ) {
				// Recursively handle nested arrays.
				$sections[ $key ] = $this->optimize( $content );
			}
			// Skip non-string, non-array values (int, bool, etc.)
		}
		return $sections;
	}

	/**
	 * Optimize a single text string.
	 *
	 * Changed from private to protected for testability (Codex review fix).
	 *
	 * @param string $text The text to optimize.
	 * @return string Optimized text.
	 */
	protected function optimize_text( string $text ): string {
		// Skip if text appears to be code (contains common code patterns).
		if ( $this->is_code_content( $text ) ) {
			return $text;
		}

		// Add space between Chinese and English/numbers.
		$text = $this->add_cjk_spacing( $text );

		// Optionally normalize punctuation.
		if ( $this->normalize_punctuation ) {
			$text = $this->normalize_chinese_punctuation( $text );
		}

		return $text;
	}

	/**
	 * Add spacing between CJK characters and Latin characters/numbers.
	 *
	 * @param string $text The text to process.
	 * @return string Text with proper spacing.
	 */
	private function add_cjk_spacing( string $text ): string {
		// CJK Unified Ideographs range.
		$cjk_pattern = '[\x{4e00}-\x{9fa5}\x{3400}-\x{4dbf}]';

		// Add space: Chinese followed by English/number.
		$text = preg_replace(
			'/(' . $cjk_pattern . ')([a-zA-Z0-9])/u',
			'$1 $2',
			$text
		);

		// Add space: English/number followed by Chinese.
		$text = preg_replace(
			'/([a-zA-Z0-9])(' . $cjk_pattern . ')/u',
			'$1 $2',
			$text
		);

		return $text;
	}

	/**
	 * Normalize Chinese punctuation to English equivalents.
	 *
	 * Note: This is optional and disabled by default as it may not be
	 * desirable for all use cases.
	 *
	 * @param string $text The text to process.
	 * @return string Text with normalized punctuation.
	 */
	private function normalize_chinese_punctuation( string $text ): string {
		$replacements = array(
			'，' => ', ',
			'。' => '. ',
			'！' => '! ',
			'？' => '? ',
			'：' => ': ',
			'；' => '; ',
			'"'  => '"',
			'"'  => '"',
			"\xe2\x80\x98" => "'", // Left single quotation mark (')
			"\xe2\x80\x99" => "'", // Right single quotation mark (')
			'（' => '(',
			'）' => ')',
			'【' => '[',
			'】' => ']',
		);

		return str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$text
		);
	}

	/**
	 * Check if content appears to be code.
	 *
	 * @param string $text The text to check.
	 * @return bool True if content appears to be code.
	 */
	private function is_code_content( string $text ): bool {
		// Common code patterns.
		$code_patterns = array(
			'/^```/',           // Markdown code block.
			'/^\s*<\?php/',     // PHP opening tag.
			'/function\s+\w+/', // Function definition.
			'/class\s+\w+/',    // Class definition.
			'/\$\w+\s*=/',      // Variable assignment.
			'/=>\s*\[/',        // Array syntax.
		);

		foreach ( $code_patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}

		return false;
	}
}
