<?php
/**
 * Cache AJAX Controller
 *
 * Handles AJAX requests for the Exact Cache module.
 *
 * @package WPMind\Modules\ExactCache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

/**
 * Class CacheAjaxController
 *
 * Provides 4 AJAX handlers for cache management.
 */
final class CacheAjaxController {

	/**
	 * Register AJAX hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpmind_save_cache_settings', [ $this, 'ajax_save_cache_settings' ] );
		add_action( 'wp_ajax_wpmind_flush_cache', [ $this, 'ajax_flush_cache' ] );
		add_action( 'wp_ajax_wpmind_reset_cache_stats', [ $this, 'ajax_reset_cache_stats' ] );
		add_action( 'wp_ajax_wpmind_get_cache_stats', [ $this, 'ajax_get_cache_stats' ] );
	}

	/**
	 * Verify request security (shared by all handlers).
	 *
	 * Uses wpmind_ajax nonce (already issued globally by AdminAssets).
	 *
	 * @param bool $require_post Whether to enforce POST method.
	 */
	private function verify_request( bool $require_post = false ): void {
		if ( $require_post && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json_error( [ 'message' => 'Method not allowed' ], 405 );
		}
		check_ajax_referer( 'wpmind_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}
	}

	/**
	 * Save cache settings (POST only).
	 */
	public function ajax_save_cache_settings(): void {
		$this->verify_request( true );

		// Whitelist fields + boundary validation + wp_unslash.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify_request() above.
		$raw         = wp_unslash( $_POST );
		$enabled     = in_array( $raw['enabled'] ?? '', [ '1', '0' ], true ) ? $raw['enabled'] : '1';
		$default_ttl = max( 0, min( 86400, (int) ( $raw['default_ttl'] ?? 900 ) ) );
		$max_entries = max( 10, min( 5000, (int) ( $raw['max_entries'] ?? 500 ) ) );
		$scope_mode  = in_array( $raw['scope_mode'] ?? '', [ 'role', 'user', 'none' ], true )
			? $raw['scope_mode'] : 'role';

		update_option( 'wpmind_exact_cache_enabled', $enabled, false );
		update_option( 'wpmind_exact_cache_default_ttl', $default_ttl, false );
		update_option( 'wpmind_exact_cache_max_entries', $max_entries, false );
		update_option( 'wpmind_exact_cache_scope_mode', $scope_mode, false );

		wp_send_json_success();
	}

	/**
	 * Flush all cache entries (POST only, destructive).
	 */
	public function ajax_flush_cache(): void {
		$this->verify_request( true );

		\WPMind\Cache\ExactCache::instance()->flush();
		wp_send_json_success();
	}

	/**
	 * Reset cache statistics (POST only, destructive).
	 */
	public function ajax_reset_cache_stats(): void {
		$this->verify_request( true );

		// Reset core stats.
		update_option(
			'wpmind_exact_cache_stats',
			[
				'hits'          => 0,
				'misses'        => 0,
				'writes'        => 0,
				'last_hit_at'   => 0,
				'last_miss_at'  => 0,
				'last_write_at' => 0,
				'last_key'      => '',
			],
			false
		);
		// Reset daily stats.
		DailyStats::reset();
		wp_send_json_success();
	}

	/**
	 * Get cache statistics (GET allowed, read-only).
	 */
	public function ajax_get_cache_stats(): void {
		$this->verify_request( false );

		wp_send_json_success(
			[
				'stats'   => \WPMind\Cache\ExactCache::instance()->get_stats(),
				'daily'   => DailyStats::get_daily_data(),
				'savings' => CostEstimator::get_estimated_savings(),
			]
		);
	}
}
