<?php
/**
 * Stream Result DTO
 *
 * Immutable data transfer object for stream completion results.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

/**
 * Class StreamResult
 *
 * Read-only value object returned after a streaming operation
 * completes, carrying token usage and finish metadata.
 */
final class StreamResult {

	/**
	 * Total tokens consumed during the stream.
	 *
	 * @var int
	 */
	public readonly int $tokens_used;

	/**
	 * Reason the stream finished (e.g. 'stop', 'length', 'error').
	 *
	 * @var string
	 */
	public readonly string $finish_reason;

	/**
	 * Error message if the stream ended abnormally, null otherwise.
	 *
	 * @var string|null
	 */
	public readonly ?string $error;

	/**
	 * Constructor.
	 *
	 * @param int         $tokens_used   Total tokens consumed.
	 * @param string      $finish_reason Reason the stream finished.
	 * @param string|null $error         Error message or null.
	 */
	public function __construct( int $tokens_used, string $finish_reason, ?string $error = null ) {
		$this->tokens_used   = $tokens_used;
		$this->finish_reason = $finish_reason;
		$this->error         = $error;
	}
}
