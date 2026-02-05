<?php
/**
 * Markdown Processor
 *
 * Unified core pipeline for Markdown processing.
 *
 * @package WPMind\GEO
 * @since 3.1.0
 */

namespace WPMind\GEO;

/**
 * Class MarkdownProcessor
 *
 * Unified processing pipeline for both MarkdownFeed and MarkdownEnhancer.
 * Ensures consistent output regardless of mode (standalone or enhancement).
 */
class MarkdownProcessor {

	/**
	 * Processed marker to prevent duplicate processing.
	 */
	private const PROCESSED_MARKER = '<!-- wpmind-geo-processed -->';

	/**
	 * Chinese optimizer instance.
	 *
	 * @var ChineseOptimizer
	 */
	private ChineseOptimizer $chinese_optimizer;

	/**
	 * GEO signal injector instance.
	 *
	 * @var GeoSignalInjector
	 */
	private GeoSignalInjector $geo_injector;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->chinese_optimizer = new ChineseOptimizer();
		$this->geo_injector      = new GeoSignalInjector();
	}

	/**
	 * Process Markdown sections with GEO enhancements.
	 *
	 * Input contract: $sections must be an associative array with keys like:
	 * - 'title': The post title in Markdown format
	 * - 'content': The main content in Markdown format
	 * - Other optional sections
	 *
	 * @param array               $sections The content sections (already converted to Markdown).
	 * @param \WP_Post            $post     The post object.
	 * @param ProcessOptions|null $options  Processing options (null = use settings).
	 * @return array Processed sections.
	 */
	public function process( array $sections, \WP_Post $post, ?ProcessOptions $options = null ): array {
		// Use settings-based options if not provided.
		if ( null === $options ) {
			$options = ProcessOptions::from_settings();
		}

		// Idempotency check: if already processed, return as-is.
		if ( $this->is_already_processed( $sections ) ) {
			return $sections;
		}

		// Check if GEO enhancement is globally enabled.
		if ( ! get_option( 'wpmind_geo_enabled', true ) ) {
			return $this->mark_as_processed( $sections );
		}

		// 1. Chinese content optimization (if enabled).
		if ( ! $options->skip_language_opt && $options->allow_rewrite ) {
			$sections = $this->chinese_optimizer->optimize( $sections );
		}

		// 2. GEO signal injection (if enabled).
		if ( ! $options->skip_geo_signals ) {
			$sections = $this->geo_injector->inject( $sections, $post );
		}

		// 3. Add metadata section (if enabled).
		if ( $options->add_metadata ) {
			$sections = $this->add_metadata_section( $sections, $post );
		}

		// 4. Mark as processed to prevent duplicate processing.
		$sections = $this->mark_as_processed( $sections );

		/**
		 * Filter the processed sections.
		 *
		 * @param array               $sections The processed sections.
		 * @param \WP_Post            $post     The post object.
		 * @param ProcessOptions      $options  The processing options.
		 */
		return apply_filters( 'wpmind_geo_processed_sections', $sections, $post, $options );
	}

	/**
	 * Check if sections have already been processed.
	 *
	 * @param array $sections The content sections.
	 * @return bool True if already processed.
	 */
	private function is_already_processed( array $sections ): bool {
		// Check for marker in any section.
		foreach ( $sections as $content ) {
			if ( is_string( $content ) && false !== strpos( $content, self::PROCESSED_MARKER ) ) {
				return true;
			}
		}

		// Check for marker key.
		return isset( $sections['_wpmind_processed'] ) && true === $sections['_wpmind_processed'];
	}

	/**
	 * Mark sections as processed.
	 *
	 * @param array $sections The content sections.
	 * @return array Sections with processed marker.
	 */
	private function mark_as_processed( array $sections ): array {
		// Add internal marker (not visible in output).
		$sections['_wpmind_processed'] = true;

		return $sections;
	}

	/**
	 * Add metadata section to content.
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Sections with metadata.
	 */
	private function add_metadata_section( array $sections, \WP_Post $post ): array {
		$metadata = '';

		// Get excerpt.
		$excerpt = get_the_excerpt( $post );
		if ( ! empty( $excerpt ) ) {
			$metadata .= "\n\n**摘要**: " . esc_html( $excerpt ) . "\n";
		}

		// Calculate reading time.
		// Chinese: ~400 characters/minute, English: ~200 words/minute.
		$content      = isset( $sections['content'] ) ? $sections['content'] : '';
		$word_counts  = $this->count_words( $content );
		$reading_time = max( 1, ceil( $word_counts['chinese'] / 400 + $word_counts['english'] / 200 ) );
		$metadata    .= sprintf( "\n**阅读时间**: 约 %d 分钟\n", $reading_time );

		// Get custom fields count (if ACF is available).
		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID );
			if ( is_array( $acf_fields ) && ! empty( $acf_fields ) ) {
				$metadata .= sprintf( "\n**自定义字段**: %d 个\n", count( $acf_fields ) );
			}
		}

		if ( ! empty( $metadata ) ) {
			$sections['wpmind_metadata'] = $metadata;
		}

		return $sections;
	}

	/**
	 * Count words in content (supports Chinese).
	 *
	 * @param string $content The content to count.
	 * @return array Word counts with 'chinese' and 'english' keys.
	 */
	private function count_words( string $content ): array {
		// Strip Markdown formatting.
		$text = wp_strip_all_tags( $content );

		// Count Chinese characters.
		$chinese_count = preg_match_all( '/[\x{4e00}-\x{9fff}]/u', $text, $matches );

		// Count English words.
		$english_text  = preg_replace( '/[\x{4e00}-\x{9fff}]/u', ' ', $text );
		$english_count = str_word_count( $english_text );

		return array(
			'chinese' => $chinese_count,
			'english' => $english_count,
		);
	}

	/**
	 * Get the Chinese optimizer instance.
	 *
	 * @return ChineseOptimizer
	 */
	public function get_chinese_optimizer(): ChineseOptimizer {
		return $this->chinese_optimizer;
	}

	/**
	 * Get the GEO signal injector instance.
	 *
	 * @return GeoSignalInjector
	 */
	public function get_geo_injector(): GeoSignalInjector {
		return $this->geo_injector;
	}
}
