<?php
/**
 * SSE Stream Controller
 *
 * Main controller that orchestrates Server-Sent Events streaming
 * for the API Gateway chat completions endpoint.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

use WPMind\Modules\ApiGateway\Pipeline\GatewayRequestContext;

/**
 * Class SseStreamController
 *
 * Orchestrates the full SSE lifecycle: slot acquisition,
 * header emission, chunk proxying, and cleanup. This controller
 * takes over the HTTP response entirely when streaming is active.
 */
final class SseStreamController {

	/**
	 * Concurrency guard instance.
	 *
	 * @var SseConcurrencyGuard
	 */
	private SseConcurrencyGuard $guard;

	/**
	 * Upstream stream client instance.
	 *
	 * @var UpstreamStreamClient
	 */
	private UpstreamStreamClient $client;

	/**
	 * Constructor.
	 *
	 * @param SseConcurrencyGuard|null  $guard  Optional guard instance.
	 * @param UpstreamStreamClient|null $client Optional client instance.
	 */
	public function __construct( ?SseConcurrencyGuard $guard = null, ?UpstreamStreamClient $client = null ) {
		$this->guard  = $guard ?? new SseConcurrencyGuard();
		$this->client = $client ?? new UpstreamStreamClient();
	}

	/**
	 * Serve a chat completion as an SSE stream.
	 *
	 * Takes over the HTTP response entirely. On success, sends SSE
	 * events and calls exit(). On slot acquisition failure, sets an
	 * error on the context and returns so the pipeline can handle it.
	 *
	 * @param GatewayRequestContext $context Pipeline request context.
	 */
	public function serve_chat_stream( GatewayRequestContext $context ): void {
		$payload    = $context->get_internal_payload();
		$messages   = $payload['messages'] ?? [];
		$options    = $payload['options'] ?? [];
		$model      = $options['model'] ?? 'auto';
		$key_id     = $context->key_id() ?? 'anonymous';
		$request_id = $context->request_id();

		// Resolve concurrency limit from auth result.
		$auth_result    = $context->auth_result();
		$per_key_limit  = $auth_result->concurrency_limit ?? 2;

		// Step 1: Acquire SSE slot.
		$slot = $this->guard->acquire_slot( $key_id, $request_id, $per_key_limit );

		if ( is_wp_error( $slot ) ) {
			$context->set_error( $slot );
			return;
		}

		// Step 2: Create cancellation token.
		$token = new CancellationToken();

		// Step 3: Ensure cleanup runs even if client disconnects.
		ignore_user_abort( true );

		// Step 4: Clean output buffers to prevent buffering interference.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Step 5: Send SSE headers.
		$this->send_sse_headers( $request_id, $context );

		// Step 6: Heartbeat counter for periodic slot refresh.
		$chunk_count = 0;

		// Step 7: Define chunk callback.
		$on_chunk = function ( string $text, array $raw_chunk ) use ( $request_id, $model, $token, $slot, &$chunk_count ): void {
			// Check for client disconnect.
			if ( connection_aborted() ) {
				$token->cancel( 'client_disconnected' );
				return;
			}

			// Build OpenAI-compatible streaming chunk.
			$chunk_data = [
				'id'      => 'wpmind-' . $request_id,
				'object'  => 'chat.completion.chunk',
				'created' => time(),
				'model'   => $model,
				'choices' => [
					[
						'index'         => 0,
						'delta'         => [ 'content' => $text ],
						'finish_reason' => null,
					],
				],
			];

			$this->send_sse_event( wp_json_encode( $chunk_data ) );

			// Heartbeat every 20 chunks to keep slot alive.
			++$chunk_count;
			if ( $chunk_count % 20 === 0 ) {
				$this->guard->heartbeat_slot( $slot );
			}
		};

		try {
			// Step 8: Stream from upstream provider.
			$result = $this->client->stream_chat( $messages, $options, $on_chunk, $token );

			// Step 9: Send final chunk with finish_reason.
			if ( ! $token->is_cancelled() ) {
				$final_chunk = [
					'id'      => 'wpmind-' . $request_id,
					'object'  => 'chat.completion.chunk',
					'created' => time(),
					'model'   => $model,
					'choices' => [
						[
							'index'         => 0,
							'delta'         => (object) [],
							'finish_reason' => $result->finish_reason === 'error' ? 'stop' : $result->finish_reason,
						],
					],
				];

				$this->send_sse_event( wp_json_encode( $final_chunk ) );
			}

			// Step 10: Send [DONE] marker.
			$this->send_sse_event( '[DONE]' );

			/**
			 * Fires after an SSE stream completes successfully.
			 *
			 * @param string       $request_id Request ID.
			 * @param string       $key_id     API key ID.
			 * @param StreamResult $result     Stream result.
			 */
			do_action( 'wpmind_gateway_sse_complete', $request_id, $key_id, $result );
		} finally {
			// Step 11: Always release the SSE slot.
			$this->guard->release_slot( $slot );
		}

		// Step 12: Terminate to prevent WordPress from adding output.
		exit();
	}

	/**
	 * Send SSE response headers.
	 *
	 * @param string                 $request_id Request ID for X-Request-Id header.
	 * @param GatewayRequestContext  $context    Context for additional headers.
	 */
	private function send_sse_headers( string $request_id, GatewayRequestContext $context ): void {
		// Core SSE headers.
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );
		header( 'X-Request-Id: ' . $request_id );

		// Apply any headers set by earlier pipeline stages.
		foreach ( $context->get_response_headers() as $name => $value ) {
			if ( strtolower( $name ) === 'x-wpmind-stream' ) {
				continue; // Skip the pending marker.
			}
			header( $name . ': ' . $value );
		}

		// Flush headers immediately.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// Don't call fastcgi_finish_request here — we need the connection open.
			// Just flush what we have.
		}
		flush();
	}

	/**
	 * Send a single SSE event.
	 *
	 * Formats the data according to the SSE specification and
	 * flushes the output buffer immediately.
	 *
	 * @param string $data Event data payload.
	 */
	private function send_sse_event( string $data ): void {
		echo 'data: ' . $data . "\n\n";

		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}
}
