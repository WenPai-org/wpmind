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

		$format = [];
		foreach ( $data as $col => $val ) {
			if ( is_int( $val ) ) {
				$format[] = '%d';
			} elseif ( is_float( $val ) ) {
				$format[] = '%f';
			} else {
				$format[] = '%s';
			}
		}

		$result = $wpdb->insert( self::table(), $data, $format );

		if ( false === $result ) {
			return 0;
		}

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
				'SELECT * FROM %i WHERE key_id = %s LIMIT 1',
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

		$audit_table = $wpdb->prefix . 'wpmind_api_audit_log';
		$wpdb->insert(
			$audit_table,
			[
				'event_type'    => 'key_revoked',
				'key_id'        => $key_id,
				'actor_user_id' => $actor_user_id,
				'detail_json'   => wp_json_encode( [ 'reason' => $reason ] ),
				'created_at'    => $now,
			],
			[ '%s', '%s', '%d', '%s', '%s' ]
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
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
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
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() )
		);
	}

	/**
	 * Count active API keys.
	 *
	 * @return int Number of active keys.
	 */
	public static function count_active_keys(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE status = %s',
				self::table(),
				'active'
			)
		);
	}

	/**
	 * Get total request count for the current month across all keys.
	 *
	 * @return int Total requests this month.
	 */
	public static function get_month_total_requests(): int {
		global $wpdb;

		$usage_table  = $wpdb->prefix . 'wpmind_api_key_usage';
		$window_month = gmdate( 'Y-m' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(request_count), 0) FROM %i WHERE window_month = %s',
				$usage_table,
				$window_month
			)
		);
	}

	/**
	 * Update editable fields for an API key.
	 *
	 * @param string $key_id The 12-character key identifier.
	 * @param array  $data   Column => value pairs (whitelisted).
	 * @return bool True on success, false on failure.
	 */
	public static function update_key( string $key_id, array $data ): bool {
		global $wpdb;

		$allowed = [
			'name',
			'rpm_limit',
			'tpm_limit',
			'concurrency_limit',
			'monthly_budget_usd',
			'ip_whitelist',
			'expires_at',
		];

		$update = [];
		$format = [];

		foreach ( $data as $col => $val ) {
			if ( ! in_array( $col, $allowed, true ) ) {
				continue;
			}

			switch ( $col ) {
				case 'name':
					$update[ $col ] = sanitize_text_field( (string) $val );
					$format[]       = '%s';
					break;

				case 'rpm_limit':
				case 'tpm_limit':
				case 'concurrency_limit':
					$update[ $col ] = absint( $val );
					$format[]       = '%d';
					break;

				case 'monthly_budget_usd':
					$update[ $col ] = max( 0.0, (float) $val );
					$format[]       = '%f';
					break;

				case 'ip_whitelist':
					$update[ $col ] = is_array( $val )
						? wp_json_encode( $val )
						: sanitize_text_field( (string) $val );
					$format[]       = '%s';
					break;

				case 'expires_at':
					$update[ $col ] = empty( $val ) ? null : sanitize_text_field( (string) $val );
					$format[]       = '%s';
					break;
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		$update['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';

		$result = $wpdb->update(
			self::table(),
			$update,
			[ 'key_id' => $key_id ],
			$format,
			[ '%s' ]
		);

		self::invalidate_cache( $key_id );

		return $result !== false;
	}

	/**
	 * List all keys with current month usage, excluding secret columns.
	 *
	 * @return array Array of key rows with usage data.
	 */
	public static function list_all_with_usage(): array {
		global $wpdb;

		$keys_table   = self::table();
		$usage_table  = $wpdb->prefix . 'wpmind_api_key_usage';
		$window_month = gmdate( 'Y-m' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT k.id, k.key_id, k.key_prefix, k.name, k.owner_user_id,
						k.rpm_limit, k.tpm_limit, k.concurrency_limit,
						k.monthly_budget_usd, k.ip_whitelist, k.status,
						k.last_used_at, k.expires_at, k.revoked_at,
						k.created_at, k.updated_at,
						COALESCE(u.request_count, 0) AS usage_request_count,
						COALESCE(u.total_tokens, 0) AS usage_total_tokens,
						COALESCE(u.total_cost_usd, 0) AS usage_total_cost_usd
				FROM %i AS k
				LEFT JOIN %i AS u ON k.key_id = u.key_id AND u.window_month = %s
				ORDER BY k.created_at DESC',
				$keys_table,
				$usage_table,
				$window_month
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}
}
