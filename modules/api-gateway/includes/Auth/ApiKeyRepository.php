<?php
/**
 * API Key Repository
 *
 * Database access layer for API keys.
 *
 * @package WPMind\Modules\ApiGateway\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Auth;

/**
 * Class ApiKeyRepository
 *
 * CRUD operations for the wpmind_api_keys table.
 */
class ApiKeyRepository {

	/**
	 * Cache group for API key metadata.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'wpmind_api_keys';

	/**
	 * Cache TTL in seconds.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 60;

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpmind_api_keys';
	}

	/**
	 * Invalidate the cache for a given key_id.
	 *
	 * @param string $key_id The 12-character key identifier.
	 */
	private static function invalidate_cache( string $key_id ): void {
		wp_cache_delete( $key_id, self::CACHE_GROUP );
	}

	/**
	 * Insert a new API key row.
	 *
	 * @param array $data Column => value pairs.
	 * @return int Inserted row ID.
	 */
	public static function insert_key( array $data ): int {
		global $wpdb;

		$wpdb->insert( self::table(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a key row by its unique key_id.
	 *
	 * @param string $key_id The 12-character key identifier.
	 * @return array|null Row as associative array, or null if not found.
	 */
	public static function find_by_key_id( string $key_id ): ?array {
		// Check cache first.
		$cached = wp_cache_get( $key_id, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE key_id = %s LIMIT 1",
				self::table(),
				$key_id
			),
			ARRAY_A
		);

		if ( is_array( $row ) ) {
			wp_cache_set( $key_id, $row, self::CACHE_GROUP, self::CACHE_TTL );
			return $row;
		}

		return null;
	}

	/**
	 * Update the last_used_at timestamp for a key.
	 *
	 * @param string $key_id The 12-character key identifier.
	 */
	public static function update_last_used( string $key_id ): void {
		global $wpdb;

		$wpdb->update(
			self::table(),
			[
				'last_used_at' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			],
			[ 'key_id' => $key_id ],
			[ '%s', '%s' ],
			[ '%s' ]
		);

		self::invalidate_cache( $key_id );
	}

	/**
	 * Revoke an API key.
	 *
	 * @param string $key_id        The 12-character key identifier.
	 * @param int    $actor_user_id The user performing the revocation.
	 * @param string $reason        Reason for revocation.
	 */
	public static function revoke_key( string $key_id, int $actor_user_id, string $reason ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$wpdb->update(
			self::table(),
			[
				'status'     => 'revoked',
				'revoked_at' => $now,
				'updated_at' => $now,
			],
			[ 'key_id' => $key_id ],
			[ '%s', '%s', '%s' ],
			[ '%s' ]
		);

		self::invalidate_cache( $key_id );
	}

	/**
	 * List API keys with pagination.
	 *
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Items per page.
	 * @return array Array of row arrays.
	 */
	public static function list_keys( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $per_page );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d",
				self::table(),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Count total API keys.
	 *
	 * @return int Total number of keys.
	 */
	public static function count_keys(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i", self::table() )
		);
	}
}
