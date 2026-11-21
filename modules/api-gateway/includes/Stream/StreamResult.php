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
	 * Upstream provider ID used for the stream.
	 *
	 * @var string
	 */
	public readonly string $provider;

	/**
	 * Upstream model ID used for the stream.
	 *
	 * @var string
	 */
	public readonly string $model;

	/**
	 * Estimated or reported input tokens.
	 *
	 * @var int
	 */
	public readonly int $input_tokens;

	/**
	 * Estimated or reported output tokens.
	 *
	 * @var int
	 */
	public readonly int $output_tokens;

	/**
	 * Total stream duration in milliseconds.
	 *
	 * @var int
	 */
	public readonly int $latency_ms;

	/**
	 * Constructor.
	 *
	 * @param int         $tokens_used   Total tokens consumed.
	 * @param string      $finish_reason Reason the stream finished.
	 * @param string|null $error         Error message or null.
	 * @param string      $provider      Upstream provider ID.
	 * @param string      $model         Upstream model ID.
	 * @param int         $input_tokens  Input token count.
	 * @param int         $output_tokens Output token count (defaults to $tokens_used when 0).
	 * @param int         $latency_ms    Stream latency in milliseconds.
	 */
	public function __construct(
		int $tokens_used,
		string $finish_reason,
		?string $error = null,
		string $provider = '',
		string $model = '',
		int $input_tokens = 0,
		int $output_tokens = 0,
		int $latency_ms = 0
	) {
		$this->tokens_used   = $tokens_used;
		$this->finish_reason = $finish_reason;
		$this->error         = $error;
		$this->provider      = $provider;
		$this->model         = $model;
		$this->input_tokens  = max( 0, $input_tokens );
		$this->output_tokens = $output_tokens > 0 ? $output_tokens : max( 0, $tokens_used );
		$this->latency_ms    = max( 0, $latency_ms );
	}
}
