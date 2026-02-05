<?php
/**
 * Markdown Enhancer
 *
 * Enhances official AI Experiments Markdown Feeds output with GEO signals.
 *
 * @package WPMind\Modules\Geo
 * @since 3.0.0
 */

namespace WPMind\Modules\Geo;

/**
 * Class MarkdownEnhancer
 *
 * Mode A: Enhances official AI Experiments plugin Markdown output via filters.
 * Only activates when the official plugin is installed and Markdown Feeds enabled.
 *
 * @since 3.1.0 Refactored to use unified MarkdownProcessor.
 */
class MarkdownEnhancer {

	/**
	 * Unified Markdown processor.
	 *
	 * @var MarkdownProcessor
	 */
	private MarkdownProcessor $processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->processor = new MarkdownProcessor();

		// Initialize on plugins_loaded to ensure official plugin is loaded.
		add_action( 'plugins_loaded', array( $this, 'maybe_init' ), 20 );
	}

	/**
	 * Initialize if official plugin is active.
	 */
	public function maybe_init(): void {
		if ( ! $this->is_official_markdown_feeds_active() ) {
			return;
		}

		// Check if GEO enhancement is enabled.
		// Support both legacy boolean and new string format.
		$geo_enabled = get_option( 'wpmind_geo_enabled', '1' );
		if ( $geo_enabled !== '1' && $geo_enabled !== true && $geo_enabled !== 1 ) {
			return;
		}

		// Enhance Feed output.
		add_filter(
			'ai_experiments_markdown_feed_post_sections',
			array( $this, 'enhance_feed_sections' ),
			10,
			2
		);

		// Enhance singular post output.
		add_filter(
			'ai_experiments_markdown_singular_post_sections',
			array( $this, 'enhance_singular_sections' ),
			10,
			2
		);
	}

	/**
	 * Check if official Markdown Feeds is active.
	 *
	 * @return bool True if official plugin is active with Markdown Feeds enabled.
	 */
	private function is_official_markdown_feeds_active(): bool {
		// Check for the official class.
		if ( ! class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' ) ) {
			return false;
		}

		// Check if the experiment is enabled.
		// Note: Option name may vary, check both possible names.
		$enabled = get_option( 'ai_experiments_markdown_feeds_enabled', false )
			|| get_option( 'ai_experiments_markdown_feed_enabled', false );

		return (bool) $enabled;
	}

	/**
	 * Enhance feed sections.
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Enhanced sections.
	 */
	public function enhance_feed_sections( array $sections, \WP_Post $post ): array {
		return $this->enhance_sections( $sections, $post );
	}

	/**
	 * Enhance singular post sections.
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Enhanced sections.
	 */
	public function enhance_singular_sections( array $sections, \WP_Post $post ): array {
		return $this->enhance_sections( $sections, $post );
	}

	/**
	 * Enhance sections with GEO signals.
	 *
	 * Uses unified MarkdownProcessor for consistent output.
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Enhanced sections.
	 */
	private function enhance_sections( array $sections, \WP_Post $post ): array {
		// Use unified processor for GEO enhancements.
		$sections = $this->processor->process( $sections, $post );

		/**
		 * Filter the enhanced sections.
		 *
		 * @param array    $sections The enhanced sections.
		 * @param \WP_Post $post     The post object.
		 */
		return apply_filters( 'wpmind_geo_enhanced_sections', $sections, $post );
	}
}
