<?php
/**
 * Rate Store Result DTO
 *
 * Immutable result from a rate-limit consume operation.
 *
 * @package WPMind\Modules\ApiGateway\RateLimit
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\RateLimit;

/**
 * Class RateStoreResult
 *
 * Read-only value object returned by rate store operations.
 */
final class RateStoreResult {

	/**
	 * Whether the request is allowed under the rate limit.
	 *
	 * @var bool
	 */
	public readonly bool $allowed;

	/**
	 * Number of remaining requests in the current window.
	 *
	 * @var int
	 */
	public readonly int $remaining;

	/**
	 * Unix epoch timestamp when the current window resets.
	 *
	 * @var int
	 */
	public readonly int $reset_epoch;

	/**
	 * @param bool $allowed     Whether the request is allowed.
	 * @param int  $remaining   Remaining capacity in the window.
	 * @param int  $reset_epoch Unix timestamp when the window resets.
	 */
	public function __construct( bool $allowed, int $remaining, int $reset_epoch ) {
		$this->allowed     = $allowed;
		$this->remaining   = $remaining;
		$this->reset_epoch = $reset_epoch;
	}
}
