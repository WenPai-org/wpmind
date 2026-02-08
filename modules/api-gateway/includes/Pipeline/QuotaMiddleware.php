<?php
/**
 * Quota Middleware
 *
 * Pipeline stage that enforces RPM and TPM rate limits per API key.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\RateLimit\RateLimiter;

/**
 * Class QuotaMiddleware
 *
 * Checks requests-per-minute and tokens-per-minute limits using
 * the RateLimiter. Sets appropriate rate-limit response headers.
 */
final class QuotaMiddleware implements GatewayStageInterface {

	/**
	 * {@inheritDoc}
	 */
	public function process( GatewayRequestContext $context ): void {
		if ( $context->is_management_route() ) {
			return;
		}

		if ( $context->has_error() ) {
			return;
		}

		$auth_result = $context->auth_result();

		if ( $auth_result === null ) {
			return;
		}

		$estimated_tokens = $this->estimate_tokens( $context->raw_body() );
		$limiter          = RateLimiter::create();

		$result = $limiter->check_and_consume(
			$auth_result->key_id,
			$context->request_id(),
			$auth_result->rpm_limit,
			$auth_result->tpm_limit,
			$estimated_tokens
		);

		$reset_seconds = max( 0, $result->reset_epoch - time() );

		$context->set_response_header( 'x-ratelimit-limit-requests', (string) $auth_result->rpm_limit );
		$context->set_response_header( 'x-ratelimit-remaining-requests', (string) max( 0, $result->remaining ) );
		$context->set_response_header( 'x-ratelimit-reset-requests', $reset_seconds . 's' );

		if ( ! $result->allowed ) {
			$retry_after = max( 1, $reset_seconds );

			$context->set_response_header( 'Retry-After', (string) $retry_after );
			$context->set_retry_after( $retry_after );

			$context->set_error(
				new \WP_Error(
					'rate_limit_exceeded',
					'Rate limit exceeded. Please retry after ' . $retry_after . ' seconds.',
					[ 'status' => 429 ]
				)
			);
		}
	}

	/**
	 * Estimate token count from the raw request body.
	 *
	 * Uses a simple heuristic of ~4 characters per token.
	 *
	 * @param string $body Raw request body.
	 * @return int Estimated token count.
	 */
	private function estimate_tokens( string $body ): int {
		$length = mb_strlen( $body, 'UTF-8' );

		return max( 1, (int) ( $length / 3 ) );
	}
}