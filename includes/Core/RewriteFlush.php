<?php
/**
 * Deferred rewrite rules flush and per-site db_version upgrades.
 *
 * Rewrite rules are only complete after every enabled module has run its
 * `init` callbacks, so WPMind never flushes in the request that changes a
 * route-related setting. It records a per-site flag instead and the next
 * request consumes the flag on `wp_loaded` with a soft flush.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Core;

/**
 * Class RewriteFlush
 */
final class RewriteFlush {

	/**
	 * Per-site option holding the "flush on next request" flag.
	 */
	public const FLAG_OPTION = 'wpmind_flush_rewrite_rules';

	/**
	 * Per-site option holding the stored database schema version.
	 */
	public const DB_VERSION_OPTION = 'wpmind_db_version';

	/**
	 * Options whose change adds, removes or alters a WPMind rewrite route.
	 *
	 * @var string[]
	 */
	public const ROUTE_OPTIONS = [
		'wpmind_module_geo_enabled',
		'wpmind_geo_enabled',
		'wpmind_llms_txt_enabled',
		'wpmind_ai_sitemap_enabled',
		'wpmind_standalone_markdown_feed',
	];

	/**
	 * Register the option listeners and the single flag consumer.
	 */
	public static function register_hooks(): void {
		foreach ( self::ROUTE_OPTIONS as $option ) {
			add_action( "add_option_{$option}", [ self::class, 'request_flush' ] );
			add_action( "update_option_{$option}", [ self::class, 'request_flush' ] );
			add_action( "delete_option_{$option}", [ self::class, 'request_flush' ] );
		}

		add_action( 'wp_loaded', [ self::class, 'maybe_flush' ] );
	}

	/**
	 * Ask for a rewrite flush on the next request of the current site.
	 *
	 * Also used as the option hook callback; PHP ignores the extra hook arguments.
	 */
	public static function request_flush(): void {
		update_option( self::FLAG_OPTION, '1', true );
	}

	/**
	 * Consume the flag: delete it first, then soft flush (no .htaccess write).
	 *
	 * Deleting first guarantees a failing flush is not retried on every request.
	 */
	public static function maybe_flush(): void {
		if ( '1' !== get_option( self::FLAG_OPTION ) ) {
			return;
		}

		delete_option( self::FLAG_OPTION );
		flush_rewrite_rules( false );
	}

	/**
	 * Run per-site upgrade steps when the stored db_version is older.
	 *
	 * A missing option counts as version 1 (every release before 0.12.1).
	 *
	 * @param int $target Current WPMIND_DB_VERSION.
	 * @return bool True when the stored version was upgraded.
	 */
	public static function maybe_upgrade_db( int $target ): bool {
		$stored = (int) get_option( self::DB_VERSION_OPTION, 1 );
		if ( $stored >= $target ) {
			return false;
		}

		// Write the new version first so a failing step never loops.
		update_option( self::DB_VERSION_OPTION, $target, true );

		if ( $stored < 2 ) {
			// Step 2: rebuild rewrite rules once (B22: llms.txt / ai-sitemap.xml).
			self::request_flush();
		}

		return true;
	}

	/**
	 * Plugin deactivation: drop the stored rules instead of flushing.
	 *
	 * Flushing here would write the still-registered WPMind routes back. With
	 * the option gone, core rebuilds it on the next request without them.
	 */
	public static function on_deactivate(): void {
		delete_option( 'rewrite_rules' );
	}
}
