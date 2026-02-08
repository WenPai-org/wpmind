<?php
/**
 * Exact Cache Module
 *
 * AI request exact-match caching module for WPMind.
 *
 * @package WPMind\Modules\ExactCache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/CostEstimator.php';
require_once __DIR__ . '/includes/DailyStats.php';
require_once __DIR__ . '/includes/CacheAjaxController.php';

/**
 * Class ExactCacheModule
 *
 * Main entry point for the Exact Cache module.
 */
final class ExactCacheModule implements ModuleInterface {

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'exact-cache';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( '精确缓存', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'AI 请求精确缓存 - 降低 API 成本、加速重复请求', 'wpmind' );
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
		return true; // No external dependencies.
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'exact-cache';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		// 1. Register settings tab (vestigial filter, actual tab rendered statically by settings-page.php).
		add_filter( 'wpmind_settings_tabs', [ $this, 'register_settings_tab' ] );

		// 2. Hook scope_mode option into ExactCache core filter.
		//    When module is disabled this filter won't fire, core falls back to default 'role'.
		add_filter( 'wpmind_exact_cache_scope_mode', function (): string {
			return get_option( 'wpmind_exact_cache_scope_mode', 'role' );
		} );

		// 3. Register AJAX handlers.
		$ajax = new CacheAjaxController();
		$ajax->register_hooks();

		// 4. Register cache event hooks for DailyStats.
		add_action( 'wpmind_exact_cache_hit', [ DailyStats::class, 'record_hit' ] );
		add_action( 'wpmind_exact_cache_miss', [ DailyStats::class, 'record_miss' ] );
		add_action( 'wpmind_exact_cache_store', [ DailyStats::class, 'record_write' ] );

		// 5. Admin assets (only on WPMind settings page).
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['exact-cache'] = [
			'title'    => __( '精确缓存', 'wpmind' ),
			'icon'     => 'ri-database-2-line',
			'template' => __DIR__ . '/templates/settings.php',
			'priority' => 20,
		];
		return $tabs;
	}

	/**
	 * Enqueue admin assets for the Exact Cache tab.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_wpmind' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script(
			'wpmind-admin-exact-cache',
			WPMIND_PLUGIN_URL . 'assets/js/admin-exact-cache.js',
			[ 'jquery', 'chartjs', 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);
	}
}