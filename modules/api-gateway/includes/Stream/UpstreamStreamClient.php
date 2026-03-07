<?php
/**
 * Upstream Stream Client
 *
 * Wraps the existing PublicAPI::stream() method for SSE proxying,
 * adding cancellation support and token tracking.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

/**
 * Class UpstreamStreamClient
 *
 * Bridges the gateway SSE layer with WPMind's internal
 * streaming API, translating chunks into gateway callbacks
 * while respecting cancellation tokens.
 */
final class UpstreamStreamClient {

	/**
	 * Stream a chat completion from the upstream provider.
	 *
	 * Calls PublicAPI::stream() and forwards each chunk to the
	 * provided callback. Checks the cancellation token between
	 * chunks and aborts early if cancelled.
	 *
	 * @param array             $messages  Chat messages array.
	 * @param array             $options   Provider/model options.
	 * @param callable          $on_chunk  Callback receiving (string $text, array $raw_chunk).
	 * @param CancellationToken $token     Cancellation token.
	 * @return StreamResult Completion result with token count and finish reason.
	 */
	public function stream_chat(
		array $messages,
		array $options,
		callable $on_chunk,
		CancellationToken $token
	): StreamResult {
		$tokens_used   = 0;
		$finish_reason = 'stop';
		$error_msg     = null;
		$cancelled     = false;

		/**
		 * Internal callback passed to PublicAPI::stream().
		 *
		 * @param string $delta    Text delta from the provider.
		 * @param array  $raw_json Raw JSON chunk from the provider.
		 */
		$callback = function ( string $delta, array $raw_json ) use ( $on_chunk, $token, &$tokens_used, &$cancelled ): void {
			if ( $token->is_cancelled() ) {
				$cancelled = true;
				throw new \RuntimeException( 'Stream cancelled: ' . esc_html( $token->get_reason() ) );
			}

			// Estimate tokens from chunk text (rough: 1 token per 3 chars for mixed CJK/Latin).
			$tokens_used += max( 1, (int) ceil( mb_strlen( $delta, 'UTF-8' ) / 3 ) );

			$on_chunk( $delta, $raw_json );
		};

		$api = \WPMind\API\PublicAPI::instance();

		try {
			/** @var bool|\WP_Error $result */
			$result = $api->stream( $messages, $callback, $options );

			if ( is_wp_error( $result ) ) {
				$finish_reason = 'error';
				$error_msg     = $result->get_error_message();
			}
		} catch ( \Throwable $e ) {
			if ( $cancelled ) {
				$finish_reason = 'cancelled';
				$error_msg     = $token->get_reason();
			} else {
				$finish_reason = 'error';
				$error_msg     = $e->getMessage();
			}
		}

		return new StreamResult( $tokens_used, $finish_reason, $error_msg );
	}
}
