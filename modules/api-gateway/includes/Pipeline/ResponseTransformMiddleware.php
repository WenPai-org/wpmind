<?php
/**
 * Response Transform Middleware
 *
 * Pipeline stage that transforms WPMind results into OpenAI-compatible responses.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\Transform\ResponseTransformer;

/**
 * Class ResponseTransformMiddleware
 *
 * Reads the internal result from the context, transforms it
 * into an OpenAI-compatible format, and sets the REST response.
 */
final class ResponseTransformMiddleware implements GatewayStageInterface {

	private ResponseTransformer $transformer;

	public function __construct() {
		$this->transformer = new ResponseTransformer();
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

		$result = $context->get_internal_result();

		if ( $result === null ) {
			$context->set_error(
				new \WP_Error(
					'empty_upstream_result',
					'No result from upstream provider.',
					[ 'status' => 502 ]
				)
			);
			return;
		}

		$payload   = $context->get_internal_payload() ?? [];
		$operation = $context->operation();

		$original_model = $payload['original_model'] ?? $payload['model'] ?? '';

		$data = match ( $operation ) {
			'chat.completions' => $this->transformer->transform_chat(
				$result,
				$original_model,
				$context->request_id()
			),
			'embeddings' => $this->transformer->transform_embedding(
				$result,
				$original_model,
				$context->request_id()
			),
			'models' => $result,
			'responses' => $this->transformer->transform_chat(
				$result,
				$original_model,
				$context->request_id()
			),
			default => $result,
		};

		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'Content-Type', 'application/json' );

		$context->set_rest_response( $response );
	}
}
