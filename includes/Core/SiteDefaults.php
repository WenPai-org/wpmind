<?php
/**
 * Per-site install/upgrade defaults.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a site is a fresh install or a pre-0.12 upgrade.
 *
 * @since 0.12.0
 */
final class SiteDefaults {

	public const VERSION_OPTION = 'wpmind_installed_version';

	/**
	 * Modules that were implicitly enabled before 0.12.
	 *
	 * @var string[]
	 */
	public const LEGACY_MODULES = [
		'analytics',
		'api-gateway',
		'auto-meta',
		'cost-control',
		'exact-cache',
		'geo',
		'media-intelligence',
	];

	/**
	 * Register hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'plugins_loaded', [ self::class, 'maybe_upgrade_module_defaults' ], 1 );
		// After core (priority 10) has created the new site's tables.
		add_action( 'wp_initialize_site', [ self::class, 'on_initialize_site' ], 100 );
	}

	/**
	 * Preserve legacy module behavior once when upgrading from pre-0.12 releases.
	 * New installations receive explicit defaults from each module manifest.
	 */
	public static function maybe_upgrade_module_defaults(): void {
		if ( false !== get_option( self::VERSION_OPTION, false ) ) {
			return;
		}

		foreach ( self::LEGACY_MODULES as $module_id ) {
			$option = "wpmind_module_{$module_id}_enabled";
			if ( false === get_option( $option, false ) ) {
				add_option( $option, '1', '', false );
			}
		}

		add_option( self::VERSION_OPTION, WPMIND_VERSION, '', false );
	}

	/**
	 * A site created while WPMind is network-active is a fresh install:
	 * stamp the version so the legacy upgrade never runs on it.
	 *
	 * @param object $site New WP_Site.
	 */
	public static function on_initialize_site( $site ): void {
		if ( ! is_multisite() || ! isset( $site->blog_id ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( WPMIND_PLUGIN_FILE ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		add_option( self::VERSION_OPTION, WPMIND_VERSION, '', false );
		restore_current_blog();
	}
}
