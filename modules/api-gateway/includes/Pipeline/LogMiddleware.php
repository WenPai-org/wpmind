<?php
/**
 * Log Middleware
 *
 * Pipeline stage that writes audit log entries and updates
 * API key usage counters for every gateway request.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

/**
 * Class LogMiddleware
 *
 * Always executes (finally semantics). Writes to the audit log
 * table and increments usage counters. Failures are silently
 * logged -- logging must never break the API response.
 */
final class LogMiddleware implements GatewayStageInterface {

	/**
	 * {@inheritDoc}
	 */
	public function process( GatewayRequestContext $context ): void {
		try {
			$this->write_audit_log( $context );
			$this->update_key_usage( $context );
		} catch ( \Throwable $e ) {
			// Logging must never cause the request to fail.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[WPMind API Gateway] Log middleware error for request %s: %s',
					$context->request_id(),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Write an entry to the audit log table.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function write_audit_log( GatewayRequestContext $context ): void {
		global $wpdb;

		$has_error  = $context->has_error();
		$event_type = $has_error ? 'api_error' : 'api_request';

		// Build detail JSON.
		$detail = $this->build_detail( $context );

		// Determine actor_user_id (0 for anonymous).
		$user_id       = get_current_user_id();
		$actor_user_id = $user_id > 0 ? $user_id : 0;

		// Privacy-preserving IP hash.
		$ip_hash = $this->hash_client_ip( $context );

		// User-Agent, truncated to 255 chars.
		$user_agent = $context->rest_request()->get_header( 'user-agent' );
		if ( is_string( $user_agent ) && mb_strlen( $user_agent ) > 255 ) {
			$user_agent = mb_substr( $user_agent, 0, 255 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'wpmind_api_audit_log',
			[
				'event_type'    => $event_type,
				'key_id'        => $context->key_id(),
				'actor_user_id' => $actor_user_id,
				'request_id'    => $context->request_id(),
				'ip_hash'       => $ip_hash,
				'user_agent'    => $user_agent,
				'detail_json'   => wp_json_encode( $detail ),
				'created_at'    => current_time( 'mysql', true ),
			],
			[
				'%s', // event_type
				'%s', // key_id
				'%d', // actor_user_id
				'%s', // request_id
				'%s', // ip_hash
				'%s', // user_agent
				'%s', // detail_json
				'%s', // created_at
			]
		);

		if ( $inserted === false ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[WPMind API Gateway] Failed to insert audit log for request %s: %s',
					$context->request_id(),
					$wpdb->last_error
				)
			);
		}
	}

	/**
	 * Update the API key usage counters (atomic upsert).
	 *
	 * Only runs for successful requests with a valid key_id.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function update_key_usage( GatewayRequestContext $context ): void {
		// Only for successful requests with a key.
		if ( $context->has_error() ) {
			return;
		}

		$key_id = $context->key_id();
		if ( $key_id === null ) {
			return;
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'wpmind_api_key_usage';
		$window_month = gmdate( 'Y-m' );
		$now          = current_time( 'mysql', true );

		// Extract token counts from internal result if available.
		$result        = $context->get_internal_result();
		$input_tokens  = 0;
		$output_tokens = 0;
		$total_tokens  = 0;
		$cost_usd      = 0.0;

		if ( is_array( $result ) ) {
			$usage = $result['usage'] ?? [];
			if ( is_array( $usage ) ) {
				$input_tokens  = (int) ( $usage['prompt_tokens'] ?? 0 );
				$output_tokens = (int) ( $usage['completion_tokens'] ?? 0 );
				$total_tokens  = (int) ( $usage['total_tokens'] ?? 0 );
			}
			$cost_usd = (float) ( $result['cost_usd'] ?? 0.0 );
		}

		// Atomic upsert: INSERT ... ON DUPLICATE KEY UPDATE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$query_result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
					( key_id, window_month, request_count, input_tokens, output_tokens, total_tokens, total_cost_usd, updated_at )
				VALUES ( %s, %s, 1, %d, %d, %d, %f, %s )
				ON DUPLICATE KEY UPDATE
					request_count  = request_count  + 1,
					input_tokens   = input_tokens   + VALUES(input_tokens),
					output_tokens  = output_tokens  + VALUES(output_tokens),
					total_tokens   = total_tokens   + VALUES(total_tokens),
					total_cost_usd = total_cost_usd + VALUES(total_cost_usd),
					updated_at     = VALUES(updated_at)",
				$key_id,
				$window_month,
				$input_tokens,
				$output_tokens,
				$total_tokens,
				$cost_usd,
				$now
			)
		);

		if ( $query_result === false ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[WPMind API Gateway] Failed to update key usage for %s: %s',
					$key_id,
					$wpdb->last_error
				)
			);
		}
	}

	/**
	 * Build the detail JSON object for the audit log entry.
	 *
	 * @param GatewayRequestContext $context Request context.
	 * @return array<string, mixed>
	 */
	private function build_detail( GatewayRequestContext $context ): array {
		$detail = [
			'operation'  => $context->operation(),
			'status'     => $context->has_error() ? $this->get_error_status( $context ) : 200,
			'elapsed_ms' => $context->elapsed_ms(),
		];

		// Include error code if present.
		if ( $context->has_error() ) {
			$detail['error_code'] = $context->error()->get_error_code();
		}

		// Include model and provider from internal payload if available.
		$payload = $context->get_internal_payload();
		if ( is_array( $payload ) ) {
			if ( isset( $payload['model'] ) ) {
				$detail['model'] = $payload['model'];
			}
			if ( isset( $payload['provider'] ) ) {
				$detail['provider'] = $payload['provider'];
			}
		}

		return $detail;
	}

	/**
	 * Extract the HTTP status code from a WP_Error.
	 *
	 * @param GatewayRequestContext $context Request context.
	 * @return int HTTP status code.
	 */
	private function get_error_status( GatewayRequestContext $context ): int {
		$error = $context->error();
		$data  = $error->get_error_data();

		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return (int) $data['status'];
		}

		return 500;
	}

	/**
	 * Generate an HMAC-SHA256 hash of the client IP for privacy.
	 *
	 * Uses the IP resolved by AuthMiddleware via the context.
	 *
	 * @param GatewayRequestContext $context Request context.
	 * @return string 64-character hex hash.
	 */
	private function hash_client_ip( GatewayRequestContext $context ): string {
		$ip = $context->client_ip() ?? '127.0.0.1';

		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
