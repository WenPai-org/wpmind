<?php
/**
 * Rate Limiter
 *
 * Orchestrates RPM and TPM rate limiting with primary/fallback stores.
 *
 * @package WPMind\Modules\ApiGateway\RateLimit
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\RateLimit;

/**
 * Class RateLimiter
 *
 * Checks both requests-per-minute and tokens-per-minute limits,
 * falling back from Redis to transients on failure.
 */
final class RateLimiter {

	private RateStoreInterface $primary;
	private RateStoreInterface $fallback;

	/**
	 * @param RateStoreInterface $primary  Primary rate store (e.g. Redis).
	 * @param RateStoreInterface $fallback Fallback rate store (e.g. Transient).
	 */
	public function __construct( RateStoreInterface $primary, RateStoreInterface $fallback ) {
		$this->primary  = $primary;
		$this->fallback = $fallback;
	}

	/**
	 * Create a RateLimiter with Redis primary and Transient fallback.
	 *
	 * @return self
	 */
	public static function create(): self {
		try {
			$primary = new RedisRateStore();
		} catch ( \RuntimeException $e ) {
			$primary = new TransientRateStore();
		}

		return new self( $primary, new TransientRateStore() );
	}

	/**
	 * Check and consume rate-limit capacity for a request.
	 *
	 * Checks RPM first, then TPM. Returns the more restrictive result.
	 *
	 * @param string $key_id           API key identifier.
	 * @param string $request_id       Unique request ID.
	 * @param int    $rpm_limit        Requests per minute limit (0 = unlimited).
	 * @param int    $tpm_limit        Tokens per minute limit (0 = unlimited).
	 * @param int    $estimated_tokens Estimated token count for this request.
	 * @return RateStoreResult
	 */
	public function check_and_consume( string $key_id, string $request_id, int $rpm_limit, int $tpm_limit, int $estimated_tokens ): RateStoreResult {
		$now = time();

		$rpm_result = null;
		$tpm_result = null;

		if ( $rpm_limit > 0 ) {
			$rpm_key    = "wpmind:rl:rpm:{$key_id}";
			$rpm_result = $this->consume_with_fallback( $rpm_key, 60, 1, $rpm_limit, $request_id, $now );

			if ( ! $rpm_result->allowed ) {
				return $rpm_result;
			}
		}

		if ( $tpm_limit > 0 && $estimated_tokens > 0 ) {
			$tpm_key    = "wpmind:rl:tpm:{$key_id}";
			$tpm_result = $this->consume_with_fallback( $tpm_key, 60, $estimated_tokens, $tpm_limit, $request_id, $now );

			if ( ! $tpm_result->allowed ) {
				// Roll back the RPM entry since TPM was denied.
				if ( $rpm_limit > 0 ) {
					$this->rollback_with_fallback( "wpmind:rl:rpm:{$key_id}", $request_id );
				}

				return $tpm_result;
			}
		}

		return $this->most_restrictive( $rpm_result, $tpm_result, $now );
	}

	/**
	 * Consume from primary store, falling back on exception.
	 *
	 * @param string $key        Bucket key.
	 * @param int    $window_sec Window size.
	 * @param int    $cost       Cost units.
	 * @param int    $limit      Maximum units.
	 * @param string $rid        Request ID.
	 * @param int    $now        Current timestamp.
	 * @return RateStoreResult
	 */
	private function consume_with_fallback( string $key, int $window_sec, int $cost, int $limit, string $rid, int $now ): RateStoreResult {
		try {
			return $this->primary->consume( $key, $window_sec, $cost, $limit, $rid, $now );
		} catch ( \Exception $e ) {
			error_log( '[WPMind] RateLimiter primary store failed: ' . $e->getMessage() );
			return $this->fallback->consume( $key, $window_sec, $cost, $limit, $rid, $now );
		}
	}

	/**
	 * Rollback from primary store, falling back on exception.
	 *
	 * @param string $key Bucket key.
	 * @param string $rid Request ID.
	 */
	private function rollback_with_fallback( string $key, string $rid ): void {
		try {
			$this->primary->rollback( $key, $rid ); } catch ( \Throwable $e ) {
			/* ignore */ }
			try {
				$this->fallback->rollback( $key, $rid ); } catch ( \Throwable $e ) {
						/* ignore */ }
	}

	/**
	 * Return the more restrictive of two results.
	 *
	 * @param RateStoreResult|null $rpm RPM result.
	 * @param RateStoreResult|null $tpm TPM result.
	 * @param int                  $now Current timestamp.
	 * @return RateStoreResult
	 */
	private function most_restrictive( ?RateStoreResult $rpm, ?RateStoreResult $tpm, int $now ): RateStoreResult {
		if ( $rpm === null && $tpm === null ) {
			return new RateStoreResult( true, PHP_INT_MAX, $now + 60 );
		}

		if ( $rpm === null ) {
			return $tpm;
		}

		if ( $tpm === null ) {
			return $rpm;
		}

		// Both exist and both allowed: return the one with fewer remaining.
		if ( $rpm->remaining <= $tpm->remaining ) {
			return $rpm;
		}

		return $tpm;
	}
}
