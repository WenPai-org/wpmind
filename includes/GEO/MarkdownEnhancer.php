<?php
/**
 * Markdown Enhancer
 *
 * Enhances official AI Experiments Markdown Feeds output with GEO signals.
 *
 * @package WPMind\GEO
 * @since 3.0.0
 */

namespace WPMind\GEO;

/**
 * Class MarkdownEnhancer
 *
 * Mode A: Enhances official AI Experiments plugin Markdown output via filters.
 * Only activates when the official plugin is installed and Markdown Feeds enabled.
 */
class MarkdownEnhancer {

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
		if ( ! get_option( 'wpmind_geo_enabled', true ) ) {
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
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Enhanced sections.
	 */
	private function enhance_sections( array $sections, \WP_Post $post ): array {
		// 1. Chinese content optimization (if enabled).
		if ( get_option( 'wpmind_chinese_optimize', true ) ) {
			$sections = $this->chinese_optimizer->optimize( $sections );
		}

		// 2. Inject GEO signals (if enabled).
		if ( get_option( 'wpmind_geo_signals', true ) ) {
			$sections = $this->geo_injector->inject( $sections, $post );
		}

		// 3. Add metadata section.
		$sections = $this->add_metadata_section( $sections, $post );

		/**
		 * Filter the enhanced sections.
		 *
		 * @param array    $sections The enhanced sections.
		 * @param \WP_Post $post     The post object.
		 */
		return apply_filters( 'wpmind_geo_enhanced_sections', $sections, $post );
	}

	/**
	 * Add metadata section.
	 *
	 * This method was missing in the original design (Codex review fix).
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Sections with metadata.
	 */
	private function add_metadata_section( array $sections, \WP_Post $post ): array {
		// Get custom fields if ACF is available.
		$custom_fields = array();

		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID );
			if ( is_array( $acf_fields ) ) {
				$custom_fields = $acf_fields;
			}
		}

		// Get standard post meta.
		$excerpt = get_the_excerpt( $post );

		// Build metadata section.
		$metadata = '';

		if ( ! empty( $excerpt ) ) {
			$metadata .= "\n\n**摘要**: " . esc_html( $excerpt ) . "\n";
		}

		// Add reading time estimate.
		$word_count   = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$reading_time = max( 1, ceil( $word_count / 200 ) );
		$metadata    .= sprintf( "\n**阅读时间**: 约 %d 分钟\n", $reading_time );

		// Add custom fields summary (if any).
		if ( ! empty( $custom_fields ) ) {
			$metadata .= "\n**自定义字段**: " . count( $custom_fields ) . " 个\n";
		}

		if ( ! empty( $metadata ) ) {
			$sections['wpmind_metadata'] = $metadata;
		}

		return $sections;
	}
}
