<?php
/**
 * Error Middleware
 *
 * Pipeline stage that converts errors and exceptions into
 * OpenAI-compatible JSON error responses.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\Error\ErrorMapper;

/**
 * Class ErrorMiddleware
 *
 * Always executes (finally semantics). Converts WP_Error or
 * uncaught exceptions into OpenAI-formatted REST responses.
 */
final class ErrorMiddleware implements GatewayStageInterface {

	/**
	 * {@inheritDoc}
	 */
	public function process( GatewayRequestContext $context ): void {
		$this->handle_exception( $context );
		$this->handle_error( $context );
	}

	/**
	 * Convert an uncaught exception to a WP_Error if no error is set yet.
	 *
	 * Never exposes exception details to the client. The actual
	 * exception message is logged server-side for debugging.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function handle_exception( GatewayRequestContext $context ): void {
		if ( ! $context->has_exception() ) {
			return;
		}

		// Log the real exception for server-side debugging.
		$exception = $context->exception();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[WPMind API Gateway] Uncaught exception in request %s: %s in %s:%d',
				$context->request_id(),
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			)
		);

		// Only set a generic error if no specific error was already set.
		if ( ! $context->has_error() ) {
			$context->set_error(
				new \WP_Error(
					'internal_error',
					'An internal error occurred.',
					[ 'status' => 500 ]
				)
			);
		}
	}

	/**
	 * Convert a WP_Error to an OpenAI-formatted REST response.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function handle_error( GatewayRequestContext $context ): void {
		if ( ! $context->has_error() ) {
			return;
		}

		$error      = $context->error();
		$error_code = $error->get_error_code();
		$mapping    = ErrorMapper::map( $error_code );

		$body = ErrorMapper::format_openai_error(
			$error->get_error_message(),
			$mapping['type'],
			$mapping['code']
		);

		$response = new \WP_REST_Response( $body, $mapping['status'] );
		$response->header( 'Content-Type', 'application/json' );

		// Add Retry-After header for rate-limited responses.
		if ( $context->retry_after_sec() > 0 ) {
			$response->header( 'Retry-After', (string) $context->retry_after_sec() );
		}

		$context->set_rest_response( $response );
	}
}
