<?php
/**
 * Audit Log Repository
 *
 * Database access layer for the API audit log.
 *
 * @package WPMind\Modules\ApiGateway\Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Admin;

/**
 * Class AuditLogRepository
 *
 * Read-only queries for the wpmind_api_audit_log table.
 */
class AuditLogRepository {

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpmind_api_audit_log';
	}

	/**
	 * List audit log entries with pagination and filters.
	 *
	 * @param array $filters  Optional filters: key_id, event_type, date_from, date_to.
	 * @param int   $page     Page number (1-based).
	 * @param int   $per_page Items per page.
	 * @return array Array of row arrays.
	 */
	public static function list_logs( array $filters = [], int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$where  = [];
		$values = [];

		if ( ! empty( $filters['key_id'] ) ) {
			$where[]  = 'key_id = %s';
			$values[] = sanitize_text_field( $filters['key_id'] );
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_text_field( $filters['event_type'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$offset    = max( 0, ( $page - 1 ) * $per_page );

		$table = self::table();
		$sql   = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$values ),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Count total audit log entries matching filters.
	 *
	 * @param array $filters Optional filters: key_id, event_type, date_from, date_to.
	 * @return int Total count.
	 */
	public static function count_logs( array $filters = [] ): int {
		global $wpdb;

		$where  = [];
		$values = [];

		if ( ! empty( $filters['key_id'] ) ) {
			$where[]  = 'key_id = %s';
			$values[] = sanitize_text_field( $filters['key_id'] );
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_text_field( $filters['event_type'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$table     = self::table();
		$sql       = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}
}
