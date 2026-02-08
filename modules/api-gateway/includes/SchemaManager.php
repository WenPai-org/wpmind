<?php
/**
 * API Gateway Schema Manager
 *
 * Manages database table creation and upgrades for the API Gateway module.
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway;

/**
 * Class SchemaManager
 *
 * Handles database schema creation and version upgrades.
 */
class SchemaManager {

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option key for storing schema version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'wpmind_api_gateway_schema_version';

	/**
	 * Get the current stored schema version.
	 *
	 * @return string Schema version or empty string if not set.
	 */
	public static function get_schema_version(): string {
		return (string) get_option( self::VERSION_OPTION, '' );
	}

	/**
	 * Check if schema upgrade is needed and run it.
	 */
	public static function maybe_upgrade(): void {
		$current = self::get_schema_version();

		if ( $current === self::SCHEMA_VERSION ) {
			return;
		}

		self::create_tables();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, true );
	}

	/**
	 * Create or update database tables using dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = self::get_api_keys_sql( $wpdb->prefix, $charset_collate )
			. self::get_api_key_usage_sql( $wpdb->prefix, $charset_collate )
			. self::get_audit_log_sql( $wpdb->prefix, $charset_collate );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get SQL for the api_keys table.
	 *
	 * @param string $prefix           Table prefix.
	 * @param string $charset_collate  Charset collation.
	 * @return string SQL statement.
	 */
	private static function get_api_keys_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}wpmind_api_keys (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			key_prefix CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			secret_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			secret_salt CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			name VARCHAR(120) NOT NULL DEFAULT '',
			owner_user_id BIGINT UNSIGNED NULL,
			allowed_providers LONGTEXT NULL,
			rpm_limit INT UNSIGNED NOT NULL DEFAULT 60,
			tpm_limit INT UNSIGNED NOT NULL DEFAULT 100000,
			concurrency_limit SMALLINT UNSIGNED NOT NULL DEFAULT 2,
			monthly_budget_usd DECIMAL(12,6) NOT NULL DEFAULT 0.000000,
			ip_whitelist LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			last_used_at DATETIME NULL,
			expires_at DATETIME NULL,
			revoked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_key_id (key_id),
			KEY idx_status_expires (status, expires_at),
			KEY idx_owner (owner_user_id)
		) $charset_collate;\n";
	}

	/**
	 * Get SQL for the api_key_usage table.
	 *
	 * @param string $prefix           Table prefix.
	 * @param string $charset_collate  Charset collation.
	 * @return string SQL statement.
	 */
	private static function get_api_key_usage_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}wpmind_api_key_usage (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			window_month CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
			output_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
			total_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
			total_cost_usd DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_key_month (key_id, window_month),
			KEY idx_window_month (window_month)
		) $charset_collate;\n";
	}

	/**
	 * Get SQL for the audit_log table.
	 *
	 * @param string $prefix           Table prefix.
	 * @param string $charset_collate  Charset collation.
	 * @return string SQL statement.
	 */
	private static function get_audit_log_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}wpmind_api_audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(40) NOT NULL,
			key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
			ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
			user_agent VARCHAR(255) NULL,
			detail_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_event_time (event_type, created_at),
			KEY idx_key_time (key_id, created_at),
			KEY idx_request_id (request_id)
		) $charset_collate;\n";
	}
}
