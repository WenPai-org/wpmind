<?php
/**
 * GEO Module
 *
 * AI Search Engine Optimization module for WPMind.
 *
 * @package WPMind\Modules\Geo
 * @since 1.0.0
 */

declare(strict_types=1);

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
require_once __DIR__ . '/includes/AiIndexingManager.php';
require_once __DIR__ . '/includes/AiSitemapGenerator.php';
require_once __DIR__ . '/includes/RobotsTxtManager.php';
require_once __DIR__ . '/includes/AiSummaryManager.php';
require_once __DIR__ . '/includes/EntityLinker.php';
require_once __DIR__ . '/includes/BrandEntity.php';

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
		// Support both legacy boolean and new string format.
		$geo_enabled = get_option( 'wpmind_geo_enabled', '1' );
		if ( $geo_enabled !== '1' && $geo_enabled !== true && $geo_enabled !== 1 ) {
			return;
		}

		// Initialize components.
		$this->init_components();

		// Register settings tab.
		add_filter( 'wpmind_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_wpmind_save_geo_settings', array( $this, 'ajax_save_settings' ) );

		// Deferred rewrite flush (set by AJAX save, executed on next page load).
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );

		// Admin assets (only on WPMind settings page).
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

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
		// Helper function to check if option is enabled.
		$is_enabled = function ( $option, $default = '1' ) {
			$value = get_option( $option, $default );
			return $value === '1' || $value === true || $value === 1;
		};

		// Markdown Feed.
		if ( $is_enabled( 'wpmind_standalone_markdown_feed', '0' ) ) {
			$this->components['markdown_feed'] = new MarkdownFeed();
		}

		// Markdown Enhancer (for official AI plugin).
		$this->components['markdown_enhancer'] = new MarkdownEnhancer();

		// llms.txt Generator.
		if ( $is_enabled( 'wpmind_llms_txt_enabled', '1' ) ) {
			$this->components['llms_txt'] = new LlmsTxtGenerator();
		}

		// Schema.org Generator.
		if ( $is_enabled( 'wpmind_schema_enabled', '1' ) ) {
			$this->components['schema'] = new SchemaGenerator();
		}

		// Crawler Tracker.
		$this->components['crawler_tracker'] = new CrawlerTracker();

		// AI Indexing Manager.
		if ( $is_enabled( 'wpmind_ai_indexing_enabled', '0' ) ) {
			$this->components['ai_indexing'] = new AiIndexingManager();
		}

		// AI Sitemap.
		if ( $is_enabled( 'wpmind_ai_sitemap_enabled', '0' ) ) {
			$this->components['ai_sitemap'] = new AiSitemapGenerator();
		}

		// robots.txt AI Crawler Management.
		if ( $is_enabled( 'wpmind_robots_ai_enabled', '0' ) ) {
			$this->components['robots_txt'] = new RobotsTxtManager();
		}

		// AI Summary.
		if ( $is_enabled( 'wpmind_ai_summary_enabled', '0' ) ) {
			$this->components['ai_summary'] = new AiSummaryManager();
		}

		// Entity Linker.
		if ( $is_enabled( 'wpmind_entity_linker_enabled', '0' ) ) {
			$this->components['entity_linker'] = new EntityLinker();
		}

		// Brand Entity.
		if ( $is_enabled( 'wpmind_brand_entity_enabled', '0' ) ) {
			$this->components['brand_entity'] = new BrandEntity();
		}
	}

	/**
	 * Enqueue admin assets for the GEO tab.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_wpmind' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'wpmind-geo',
			WPMIND_PLUGIN_URL . 'assets/css/pages/geo.css',
			[ 'wpmind-admin' ],
			WPMIND_VERSION
		);
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
	 * Flush rewrite rules if flagged by AJAX save.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'wpmind_flush_rewrite_rules' ) ) {
			delete_transient( 'wpmind_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	/**
	 * AJAX handler for saving GEO settings.
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		// Sanitize and save settings.
		// Use string '1'/'0' instead of boolean for reliable storage.
		// Compare by value (not isset) because JS always sends 0/1 for checkboxes.
		$to_bool = function ( $key ) use ( $settings ): string {
			return ( (string) ( $settings[ $key ] ?? '0' ) ) === '1' ? '1' : '0';
		};

		$options = array(
			'wpmind_geo_enabled'              => $to_bool( 'wpmind_geo_enabled' ),
			'wpmind_standalone_markdown_feed' => $to_bool( 'wpmind_standalone_markdown_feed' ),
			'wpmind_chinese_optimize'         => $to_bool( 'wpmind_chinese_optimize' ),
			'wpmind_geo_signals'              => $to_bool( 'wpmind_geo_signals' ),
			'wpmind_crawler_tracking'         => $to_bool( 'wpmind_crawler_tracking' ),
			'wpmind_llms_txt_enabled'         => $to_bool( 'wpmind_llms_txt_enabled' ),
			'wpmind_schema_enabled'           => $to_bool( 'wpmind_schema_enabled' ),
			'wpmind_schema_mode'              => sanitize_key( $settings['wpmind_schema_mode'] ?? 'auto' ),
			'wpmind_ai_indexing_enabled'      => $to_bool( 'wpmind_ai_indexing_enabled' ),
			'wpmind_ai_sitemap_enabled'       => $to_bool( 'wpmind_ai_sitemap_enabled' ),
			'wpmind_robots_ai_enabled'        => $to_bool( 'wpmind_robots_ai_enabled' ),
			'wpmind_ai_summary_enabled'       => $to_bool( 'wpmind_ai_summary_enabled' ),
			'wpmind_entity_linker_enabled'    => $to_bool( 'wpmind_entity_linker_enabled' ),
			'wpmind_brand_entity_enabled'     => $to_bool( 'wpmind_brand_entity_enabled' ),
		);

		foreach ( $options as $key => $value ) {
			update_option( $key, $value, false );
		}

		// Save AI indexing sub-settings only when enabled (avoid overwriting with defaults).
		if ( $options['wpmind_ai_indexing_enabled'] === '1' ) {
			$declaration = in_array(
				$settings['wpmind_ai_default_declaration'] ?? 'original',
				[ 'original', 'ai-assisted', 'ai-generated' ],
				true
			) ? sanitize_key( $settings['wpmind_ai_default_declaration'] ) : 'original';
			update_option( 'wpmind_ai_default_declaration', $declaration, false );

			$excluded_types = [];
			if ( isset( $settings['wpmind_ai_excluded_post_types'] ) && is_array( $settings['wpmind_ai_excluded_post_types'] ) ) {
				$excluded_types = array_map( 'sanitize_key', $settings['wpmind_ai_excluded_post_types'] );
			}
			update_option( 'wpmind_ai_excluded_post_types', $excluded_types, false );
		}

		// Save AI sitemap sub-settings only when enabled.
		if ( $options['wpmind_ai_sitemap_enabled'] === '1' ) {
			$max_entries = max( 10, min( 5000, absint( $settings['wpmind_ai_sitemap_max_entries'] ?? 500 ) ) );
			update_option( 'wpmind_ai_sitemap_max_entries', $max_entries, false );
		}

		// Save robots.txt AI rules only when enabled.
		if ( $options['wpmind_robots_ai_enabled'] === '1' ) {
			$raw_rules = $settings['wpmind_robots_ai_rules'] ?? [];
			if ( is_array( $raw_rules ) ) {
				$manager = new RobotsTxtManager();
				$manager->save_rules( $raw_rules );
			}
		}

		// Save AI summary sub-settings only when enabled.
		if ( $options['wpmind_ai_summary_enabled'] === '1' ) {
			$fallback = in_array(
				$settings['wpmind_ai_summary_fallback'] ?? 'excerpt',
				[ 'excerpt', 'none' ],
				true
			) ? sanitize_key( $settings['wpmind_ai_summary_fallback'] ) : 'excerpt';
			update_option( 'wpmind_ai_summary_fallback', $fallback, false );
		}

		// Save brand entity sub-settings only when enabled.
		if ( $options['wpmind_brand_entity_enabled'] === '1' ) {
			$brand_text_fields = [
				'wpmind_brand_name',
				'wpmind_brand_description',
				'wpmind_brand_founding_date',
			];
			foreach ( $brand_text_fields as $field ) {
				$val = isset( $settings[ $field ] ) ? sanitize_text_field( wp_unslash( $settings[ $field ] ) ) : '';
				update_option( $field, $val, false );
			}

			$brand_url_fields = [
				'wpmind_brand_url',
				'wpmind_brand_social_facebook',
				'wpmind_brand_social_twitter',
				'wpmind_brand_social_linkedin',
				'wpmind_brand_social_youtube',
				'wpmind_brand_social_github',
				'wpmind_brand_social_weibo',
				'wpmind_brand_social_zhihu',
				'wpmind_brand_social_wechat',
				'wpmind_brand_wikidata_url',
				'wpmind_brand_wikipedia_url',
			];
			foreach ( $brand_url_fields as $field ) {
				$val = isset( $settings[ $field ] ) ? esc_url_raw( wp_unslash( $settings[ $field ] ) ) : '';
				update_option( $field, $val, false );
			}

			// Contact email (URL-like but use esc_url_raw for mailto compatibility).
			$email = isset( $settings['wpmind_brand_contact_email'] )
				? sanitize_email( wp_unslash( $settings['wpmind_brand_contact_email'] ) ) : '';
			update_option( 'wpmind_brand_contact_email', $email, false );

			// Phone (not URL).
			$phone = isset( $settings['wpmind_brand_contact_phone'] )
				? sanitize_text_field( wp_unslash( $settings['wpmind_brand_contact_phone'] ) ) : '';
			update_option( 'wpmind_brand_contact_phone', $phone, false );

			// Org type (whitelist).
			$allowed_types = [ 'Organization', 'Corporation', 'LocalBusiness', 'OnlineBusiness', 'NewsMediaOrganization' ];
			$org_type      = isset( $settings['wpmind_brand_org_type'] )
				? sanitize_text_field( $settings['wpmind_brand_org_type'] ) : 'Organization';
			if ( ! in_array( $org_type, $allowed_types, true ) ) {
				$org_type = 'Organization';
			}
			update_option( 'wpmind_brand_org_type', $org_type, false );
		}

		// Flush rewrite rules if markdown feed or AI sitemap setting changed.
		if ( isset( $settings['wpmind_standalone_markdown_feed'] ) || isset( $settings['wpmind_ai_sitemap_enabled'] ) ) {
			// Schedule flush for next page load to ensure routes are registered.
			set_transient( 'wpmind_flush_rewrite_rules', '1', 60 );
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
