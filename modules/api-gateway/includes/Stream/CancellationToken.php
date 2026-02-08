<?php
/**
 * Cancellation Token
 *
 * Simple thread-safe cancellation mechanism for SSE streams.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

/**
 * Class CancellationToken
 *
 * Provides a cooperative cancellation signal that can be checked
 * by long-running stream operations to abort gracefully.
 */
final class CancellationToken {

	/**
	 * Whether cancellation has been requested.
	 *
	 * @var bool
	 */
	private bool $cancelled = false;

	/**
	 * Reason for cancellation.
	 *
	 * @var string
	 */
	private string $reason = '';

	/**
	 * Request cancellation with an optional reason.
	 *
	 * @param string $reason Human-readable cancellation reason.
	 */
	public function cancel( string $reason = '' ): void {
		$this->cancelled = true;
		$this->reason    = $reason;
	}

	/**
	 * Check whether cancellation has been requested.
	 *
	 * @return bool True if cancelled.
	 */
	public function is_cancelled(): bool {
		return $this->cancelled;
	}

	/**
	 * Get the cancellation reason.
	 *
	 * @return string Reason string, empty if not cancelled.
	 */
	public function get_reason(): string {
		return $this->reason;
	}
}
