<?php
/**
 * Optional remote model catalog fetched from the WPMind update host.
 *
 * The bundled models.json ships with the plugin and only refreshes on
 * release. RemoteCatalog pulls a newer catalog (same schema, plus
 * per-model capabilities) into a transient so ModelCatalog can merge
 * it without any per-request network cost.
 *
 * @package WPMind\Provider\Catalog
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Catalog;

/**
 * Fetches and caches the remote model catalog.
 *
 * Refresh runs on admin_init at most once per TTL; data() only reads
 * the transient so frontend requests never hit the network.
 *
 * @since 0.14.0
 */
final class RemoteCatalog {

	private const TRANSIENT = 'wpmind_catalog_remote';
	private const TTL       = 86400;
	private const URL       = 'https://updates.wenpai.net/wpmind/models.json';

	/**
	 * Whether maybe_refresh() has already attempted a refresh during this request.
	 *
	 * @var bool
	 */
	private static bool $checked_this_request = false;

	/**
	 * Remote catalog endpoint. Filter to disable ('' ) or point at a mirror.
	 *
	 * @return string
	 */
	public static function url(): string {
		return (string) apply_filters( 'wpmind_remote_catalog_url', self::URL );
	}

	/**
	 * Register the lazy refresh hook.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_init', [ self::class, 'maybe_refresh' ] );
	}

	/**
	 * Refresh the cached catalog when stale. Admin pages only.
	 *
	 * Runs at most once per request; resets ModelCatalog singleton on refresh
	 * so subsequent reads in the same request see the new table.
	 *
	 * @return void
	 */
	public static function maybe_refresh(): void {
		if ( self::$checked_this_request ) {
			return;
		}
		self::$checked_this_request = true;

		$raw        = get_transient( self::TRANSIENT );
		$fetched_at = is_array( $raw ) ? (int) ( $raw['fetched_at'] ?? 0 ) : 0;
		if ( $fetched_at > 0 && ( time() - $fetched_at ) < self::TTL ) {
			return;
		}
		if ( ( new self() )->refresh() ) {
			ModelCatalog::reset_instance();
		}
	}

	/**
	 * Reset per-request refresh guard (tests only).
	 *
	 * @internal
	 */
	public static function reset_checked_flag(): void {
		self::$checked_this_request = false;
	}
	/**
	 * GET the remote catalog and store it. Manual refresh entry point.
	 *
	 * @return bool True when a valid payload was cached.
	 */
	public function refresh(): bool {
		$url = self::url();
		if ( '' === $url ) {
			return false;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'               => 5,
				'_wpmind_skip_tracking' => true,
			]
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return false;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['providers'] ) || ! is_array( $decoded['providers'] ) ) {
			return false;
		}

		$decoded['fetched_at'] = time();
		set_transient( self::TRANSIENT, $decoded, self::TTL );
		return true;
	}

	/**
	 * Cached remote catalog payload (never triggers a fetch).
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$raw = get_transient( self::TRANSIENT );
		return is_array( $raw ) && isset( $raw['providers'] ) && is_array( $raw['providers'] ) ? $raw : [];
	}

	/**
	 * Drop the cached catalog (tests / manual invalidation).
	 *
	 * @return void
	 */
	public function invalidate(): void {
		delete_transient( self::TRANSIENT );
	}
}
