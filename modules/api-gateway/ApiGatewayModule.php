<?php
/**
 * API Gateway Module
 *
 * OpenAI-compatible AI API gateway module for WPMind.
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/SchemaManager.php';
require_once __DIR__ . '/includes/Auth/ApiKeyHasher.php';
require_once __DIR__ . '/includes/Auth/ApiKeyRepository.php';
require_once __DIR__ . '/includes/Auth/ApiKeyAuthResult.php';
require_once __DIR__ . '/includes/Auth/ApiKeyManager.php';
require_once __DIR__ . '/includes/GatewayRequestSchema.php';
require_once __DIR__ . '/includes/Pipeline/GatewayStageInterface.php';
require_once __DIR__ . '/includes/Pipeline/GatewayRequestContext.php';
require_once __DIR__ . '/includes/Pipeline/GatewayPipeline.php';
require_once __DIR__ . '/includes/RateLimit/RateStoreResult.php';
require_once __DIR__ . '/includes/RateLimit/RateStoreInterface.php';
require_once __DIR__ . '/includes/RateLimit/RedisRateStore.php';
require_once __DIR__ . '/includes/RateLimit/TransientRateStore.php';
require_once __DIR__ . '/includes/RateLimit/RateLimiter.php';
require_once __DIR__ . '/includes/Pipeline/AuthMiddleware.php';
require_once __DIR__ . '/includes/Pipeline/BudgetMiddleware.php';
require_once __DIR__ . '/includes/Pipeline/QuotaMiddleware.php';
require_once __DIR__ . '/includes/Transform/ModelMapper.php';
require_once __DIR__ . '/includes/Transform/RequestTransformer.php';
require_once __DIR__ . '/includes/Transform/ResponseTransformer.php';
require_once __DIR__ . '/includes/Pipeline/RequestTransformMiddleware.php';
require_once __DIR__ . '/includes/Pipeline/ResponseTransformMiddleware.php';
require_once __DIR__ . '/includes/Pipeline/RouteMiddleware.php';
require_once __DIR__ . '/includes/Stream/CancellationToken.php';
require_once __DIR__ . '/includes/Stream/SseSlot.php';
require_once __DIR__ . '/includes/Stream/StreamResult.php';
require_once __DIR__ . '/includes/Stream/SseConcurrencyGuard.php';
require_once __DIR__ . '/includes/Stream/UpstreamStreamClient.php';
require_once __DIR__ . '/includes/Stream/SseStreamController.php';
require_once __DIR__ . '/includes/Error/ErrorMapper.php';
require_once __DIR__ . '/includes/Pipeline/ErrorMiddleware.php';
require_once __DIR__ . '/includes/Pipeline/LogMiddleware.php';
require_once __DIR__ . '/includes/RestController.php';
require_once __DIR__ . '/includes/Admin/AuditLogRepository.php';
require_once __DIR__ . '/includes/Admin/GatewayAjaxController.php';

/**
 * Class ApiGatewayModule
 *
 * Main entry point for the API Gateway module.
 */
class ApiGatewayModule implements ModuleInterface {

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'api-gateway';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'API Gateway', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'OpenAI 兼容的 AI API 网关 — 将 WordPress 变为自托管 AI 代理', 'wpmind' );
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Check dependencies.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		return version_compare( PHP_VERSION, '8.1', '>=' );
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'api-gateway';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		// Register settings even while the gateway runtime is disabled.
		// This lets administrators opt in without exposing public routes first.

		// Register admin AJAX handlers.
		if ( is_admin() ) {
			$ajax_controller = new Admin\GatewayAjaxController();
			$ajax_controller->register_hooks();

			// Admin assets (only on WPMind settings page).
		}

		if ( ! $this->is_gateway_enabled() ) {
			return;
		}

		// Runtime-only setup starts after the explicit gateway opt-in.
		SchemaManager::maybe_upgrade();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wpmind_gateway_sse_complete', array( $this, 'log_sse_completion' ), 10, 4 );

		/**
		 * Fires when API Gateway module is initialized.
		 *
		 * @param ApiGatewayModule $this Module instance.
		 */
		do_action( 'wpmind_api_gateway_init', $this );
	}

	/**
	 * Register REST API routes.
	 *
	 * Instantiates the RestController and registers all gateway endpoints.
	 */
	public function register_rest_routes(): void {
		if ( ! $this->is_gateway_enabled() ) {
			return;
		}

		$controller = new RestController();
		$controller->register_routes();
	}

	/**
	 * Whether the public gateway runtime has been explicitly enabled.
	 */
	private function is_gateway_enabled(): bool {
		return '1' === get_option( 'wpmind_gateway_enabled', '0' );
	}

	/**
	 * Log SSE stream completion for audit and usage tracking.
	 *
	 * SSE streams exit() before pipeline finalization, so this
	 * hook ensures audit logs and usage counters are still updated.
	 *
	 * @param string                              $request_id Request ID.
	 * @param string                              $key_id     API key ID.
	 * @param Stream\StreamResult                 $result     Stream result.
	 * @param Pipeline\GatewayRequestContext|null $context    Optional request context.
	 */
	public function log_sse_completion(
		string $request_id,
		string $key_id,
		Stream\StreamResult $result,
		?Pipeline\GatewayRequestContext $context = null
	): void {
		global $wpdb;

		$payload  = $context?->get_internal_payload() ?? [];
		$provider = '' !== $result->provider ? $result->provider : (string) ( $payload['provider'] ?? '' );
		$model    = '' !== $result->model ? $result->model : (string) ( $payload['model'] ?? '' );

		$input_tokens  = $result->input_tokens;
		$output_tokens = $result->output_tokens > 0 ? $result->output_tokens : $result->tokens_used;
		$total_tokens  = $input_tokens + $output_tokens;
		$latency_ms    = $result->latency_ms > 0 ? $result->latency_ms : ( $context?->elapsed_ms() ?? 0 );
		$cost_usd      = '' !== $provider
			? Pipeline\LogMiddleware::calculate_cost_usd( $provider, $model, $input_tokens, $output_tokens )
			: 0.0;

		// Audit log.
		$audit_table = $wpdb->prefix . 'wpmind_api_audit_log';
		$wpdb->insert(
			$audit_table,
			[
				'event_type'    => 'api_stream_request',
				'key_id'        => $key_id,
				'actor_user_id' => 0,
				'request_id'    => $request_id,
				'ip_hash'       => hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ),
				'user_agent'    => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
				'detail_json'   => wp_json_encode(
					[
						'provider'      => $provider,
						'model'         => $model,
						'input_tokens'  => $input_tokens,
						'output_tokens' => $output_tokens,
						'tokens_used'   => $total_tokens,
						'cost_usd'      => $cost_usd,
						'finish_reason' => $result->finish_reason,
						'stream'        => true,
					]
				),
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		// SQLite- and MySQL-compatible monthly usage upsert.
		$window_month = gmdate( 'Y-m' );
		$now          = current_time( 'mysql', true );
		Pipeline\LogMiddleware::upsert_key_usage(
			$key_id,
			$window_month,
			$input_tokens,
			$output_tokens,
			$total_tokens,
			$cost_usd,
			$now
		);

		// Emit site-wide usage event for SSE completion.
		$auth_result = $context?->auth_result();
		$key_prefix  = is_object( $auth_result ) && isset( $auth_result->key_prefix ) && '' !== (string) $auth_result->key_prefix
			? (string) $auth_result->key_prefix
			: substr( $key_id, 0, 8 );

		if ( $total_tokens > 0 || '' !== $provider ) {
			do_action(
				'wpmind_usage_record',
				$provider,
				$model,
				$input_tokens,
				$output_tokens,
				$latency_ms,
				'api_gateway:' . $key_prefix,
				'byok'
			);
		}
	}
}
