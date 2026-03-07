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
	 * Build WHERE clause and parameter values from filters.
	 *
	 * @param array $filters Optional filters: key_id, event_type, date_from, date_to.
	 * @return array{string, array} Tuple of [ where_sql, values ].
	 */
	private static function build_where_clause( array $filters ): array {
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

		return [ $where_sql, $values ];
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

		[ $where_sql, $filter_values ] = self::build_where_clause( $filters );

		$offset = max( 0, ( $page - 1 ) * $per_page );

		$sql    = "SELECT * FROM %i {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params = array_merge( [ self::table() ], $filter_values, [ $per_page, $offset ] );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql built from build_where_clause() contains only hardcoded columns and %s placeholders.
		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$params ),
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

		[ $where_sql, $filter_values ] = self::build_where_clause( $filters );

		$sql    = "SELECT COUNT(*) FROM %i {$where_sql}";
		$params = array_merge( [ self::table() ], $filter_values );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql built from build_where_clause() contains only hardcoded columns and %s placeholders.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}
}
