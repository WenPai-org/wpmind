<?php
/**
 * Request Transform Middleware
 *
 * Pipeline stage that transforms OpenAI-format requests into WPMind payloads.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\Transform\RequestTransformer;

/**
 * Class RequestTransformMiddleware
 *
 * Delegates to RequestTransformer and stores the resulting
 * internal payload on the pipeline context.
 */
final class RequestTransformMiddleware implements GatewayStageInterface {

	private RequestTransformer $transformer;

	public function __construct() {
		$this->transformer = new RequestTransformer();
	}

	/**
	 * Process the gateway request context.
	 *
	 * @param GatewayRequestContext $context Shared request context.
	 */
	public function process( GatewayRequestContext $context ): void {
		if ( $context->has_error() ) {
			return;
		}

		$result = $this->transformer->transform(
			$context->operation(),
			$context->rest_request()
		);

		if ( isset( $result['error'] ) ) {
			$context->set_error( $result['error'] );
			return;
		}

		$context->set_internal_payload( $result['payload'] );
	}
}
