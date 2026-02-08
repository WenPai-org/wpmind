<?php
/**
 * Rate Store Interface
 *
 * Contract for sliding-window rate limit backends.
 *
 * @package WPMind\Modules\ApiGateway\RateLimit
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\RateLimit;

/**
 * Interface RateStoreInterface
 *
 * Implementations provide atomic consume/rollback for rate limiting.
 */
interface RateStoreInterface {

	/**
	 * Attempt to consume capacity from a rate-limit bucket.
	 *
	 * @param string $key        Bucket identifier (e.g. "rpm:{key_id}").
	 * @param int    $window_sec Sliding window size in seconds.
	 * @param int    $cost       Units to consume (1 for RPM, token count for TPM).
	 * @param int    $limit      Maximum allowed units per window.
	 * @param string $rid        Unique request ID for rollback support.
	 * @param int    $now        Current Unix timestamp.
	 * @return RateStoreResult
	 */
	public function consume( string $key, int $window_sec, int $cost, int $limit, string $rid, int $now ): RateStoreResult;

	/**
	 * Roll back a previously consumed entry by request ID.
	 *
	 * @param string $key Bucket identifier.
	 * @param string $rid Request ID to remove.
	 */
	public function rollback( string $key, string $rid ): void;
}
