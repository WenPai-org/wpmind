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
		$start_time    = microtime( true );
		$input_tokens  = $this->estimate_messages_tokens( $messages );
		$tokens_used   = 0;
		$finish_reason = 'stop';
		$error_msg     = null;
		$cancelled     = false;
		$provider      = (string) ( $options['provider'] ?? '' );
		$model         = (string) ( $options['model'] ?? '' );

		/**
		 * Internal callback passed to PublicAPI::stream().
		 *
		 * @param string $delta    Text delta from the provider.
		 * @param array  $raw_json Raw JSON chunk from the provider.
		 */
		$callback = function ( string $delta, array $raw_json ) use ( $on_chunk, $token, &$tokens_used, &$cancelled, &$model ): void {
			if ( $token->is_cancelled() ) {
				$cancelled = true;
				throw new \RuntimeException( 'Stream cancelled: ' . esc_html( $token->get_reason() ) );
			}

			if ( '' === $model && isset( $raw_json['model'] ) && is_string( $raw_json['model'] ) ) {
				$model = $raw_json['model'];
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

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		return new StreamResult(
			$tokens_used,
			$finish_reason,
			$error_msg,
			$provider,
			$model,
			$input_tokens,
			$tokens_used,
			$latency_ms
		);
	}

	/**
	 * Estimate input token count from chat messages array.
	 *
	 * @param array $messages Chat messages.
	 * @return int Estimated input tokens.
	 */
	private function estimate_messages_tokens( array $messages ): int {
		$chars = 0;
		foreach ( $messages as $msg ) {
			if ( is_array( $msg ) && isset( $msg['content'] ) ) {
				if ( is_string( $msg['content'] ) ) {
					$chars += mb_strlen( $msg['content'], 'UTF-8' );
				} elseif ( is_array( $msg['content'] ) ) {
					foreach ( $msg['content'] as $part ) {
						if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
							$chars += mb_strlen( $part['text'], 'UTF-8' );
						}
					}
				}
			}
		}

		return $chars > 0 ? max( 1, (int) ceil( $chars / 3 ) ) : 0;
	}
}
