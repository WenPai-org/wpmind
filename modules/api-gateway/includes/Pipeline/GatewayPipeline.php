<?php
/**
 * Gateway Pipeline
 *
 * Orchestrates the 8-stage middleware pipeline for API requests.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

/**
 * Class GatewayPipeline
 *
 * Runs a request through: auth -> budget -> quota ->
 * request_transform -> route -> response_transform -> error -> log.
 *
 * The error and log stages always execute (finally semantics).
 */
final class GatewayPipeline {

	public function __construct(
		private GatewayStageInterface $auth,
		private GatewayStageInterface $budget,
		private GatewayStageInterface $quota,
		private GatewayStageInterface $request_transform,
		private GatewayStageInterface $route,
		private GatewayStageInterface $response_transform,
		private GatewayStageInterface $error,
		private GatewayStageInterface $log
	) {}

	/**
	 * Handle an API gateway request through the full pipeline.
	 *
	 * @param string           $operation Operation type (e.g. 'chat.completions').
	 * @param \WP_REST_Request $request   WordPress REST request.
	 * @return \WP_REST_Response
	 */
	public function handle( string $operation, \WP_REST_Request $request ): \WP_REST_Response {
		$context = GatewayRequestContext::from_rest_request( $operation, $request );

		try {
			$this->auth->process( $context );

			if ( ! $context->has_error() ) {
				$this->budget->process( $context );
			}
			if ( ! $context->has_error() ) {
				$this->quota->process( $context );
			}
			if ( ! $context->has_error() ) {
				$this->request_transform->process( $context );
			}
			if ( ! $context->has_error() ) {
				$this->route->process( $context );
			}
			if ( ! $context->has_error() ) {
				$this->response_transform->process( $context );
			}
		} catch ( \Throwable $e ) {
			$context->set_exception( $e );
		}

		// Error and log stages always execute (finally semantics).
		try {
			$this->error->process( $context );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPMind GW] Error stage failed: ' . $e->getMessage() );
		}
		try {
			$this->log->process( $context );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPMind GW] Log stage failed: ' . $e->getMessage() );
		}

		return $context->to_rest_response();
	}
}
