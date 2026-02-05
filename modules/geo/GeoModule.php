<?php
/**
 * GEO Module
 *
 * AI Search Engine Optimization module for WPMind.
 *
 * @package WPMind\Modules\Geo
 * @since 1.0.0
 */

namespace WPMind\Modules\Geo;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/ChineseOptimizer.php';
require_once __DIR__ . '/includes/GeoSignalInjector.php';
require_once __DIR__ . '/includes/HtmlToMarkdown.php';
require_once __DIR__ . '/includes/ProcessOptions.php';
require_once __DIR__ . '/includes/MarkdownProcessor.php';
require_once __DIR__ . '/includes/MarkdownEnhancer.php';
require_once __DIR__ . '/includes/MarkdownFeed.php';
require_once __DIR__ . '/includes/LlmsTxtGenerator.php';
require_once __DIR__ . '/includes/SchemaGenerator.php';
require_once __DIR__ . '/includes/CrawlerTracker.php';

/**
 * Class GeoModule
 *
 * Main entry point for the GEO optimization module.
 */
class GeoModule implements ModuleInterface {

	/**
	 * Module instances.
	 *
	 * @var array
	 */
	private array $components = [];

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'geo';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'GEO 优化', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'AI 搜索引擎优化 - Markdown Feeds, llms.txt, Schema.org', 'wpmind' );
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Check dependencies.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		// GEO module has no external dependencies.
		return true;
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'geo';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		// Check if GEO is globally enabled.
		if ( ! get_option( 'wpmind_geo_enabled', true ) ) {
			return;
		}

		// Initialize components.
		$this->init_components();

		// Register settings tab.
		add_filter( 'wpmind_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_wpmind_save_geo_settings', array( $this, 'ajax_save_settings' ) );

		/**
		 * Fires when GEO module is initialized.
		 *
		 * @param GeoModule $this Module instance.
		 */
		do_action( 'wpmind_geo_init', $this );
	}

	/**
	 * Initialize module components.
	 */
	private function init_components(): void {
		// Markdown Feed.
		if ( get_option( 'wpmind_standalone_markdown_feed', false ) ) {
			$this->components['markdown_feed'] = new MarkdownFeed();
		}

		// Markdown Enhancer (for official AI plugin).
		$this->components['markdown_enhancer'] = new MarkdownEnhancer();

		// llms.txt Generator.
		if ( get_option( 'wpmind_llms_txt_enabled', true ) ) {
			$this->components['llms_txt'] = new LlmsTxtGenerator();
		}

		// Schema.org Generator.
		if ( get_option( 'wpmind_schema_enabled', true ) ) {
			$this->components['schema'] = new SchemaGenerator();
		}

		// Crawler Tracker.
		$this->components['crawler_tracker'] = new CrawlerTracker();
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['geo'] = array(
			'title'    => __( 'GEO', 'wpmind' ),
			'icon'     => 'ri-search-eye-line',
			'template' => WPMIND_PATH . 'modules/geo/templates/settings.php',
			'priority' => 30,
		);
		return $tabs;
	}

	/**
	 * AJAX handler for saving GEO settings.
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		$settings = $_POST['settings'] ?? array();

		// Sanitize and save settings.
		$options = array(
			'wpmind_geo_enabled'             => isset( $settings['wpmind_geo_enabled'] ),
			'wpmind_standalone_markdown_feed' => isset( $settings['wpmind_standalone_markdown_feed'] ),
			'wpmind_chinese_optimize'        => isset( $settings['wpmind_chinese_optimize'] ),
			'wpmind_geo_signals'             => isset( $settings['wpmind_geo_signals'] ),
			'wpmind_crawler_tracking'        => isset( $settings['wpmind_crawler_tracking'] ),
			'wpmind_llms_txt_enabled'        => isset( $settings['wpmind_llms_txt_enabled'] ),
			'wpmind_schema_enabled'          => isset( $settings['wpmind_schema_enabled'] ),
			'wpmind_schema_mode'             => sanitize_key( $settings['wpmind_schema_mode'] ?? 'auto' ),
		);

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}

		// Flush rewrite rules if markdown feed setting changed.
		if ( isset( $settings['wpmind_standalone_markdown_feed'] ) ) {
			flush_rewrite_rules();
		}

		wp_send_json_success( array( 'message' => __( '设置已保存', 'wpmind' ) ) );
	}

	/**
	 * Get a component instance.
	 *
	 * @param string $name Component name.
	 * @return object|null Component instance or null.
	 */
	public function get_component( string $name ): ?object {
		return $this->components[ $name ] ?? null;
	}

	/**
	 * Get all components.
	 *
	 * @return array Components array.
	 */
	public function get_components(): array {
		return $this->components;
	}
}
