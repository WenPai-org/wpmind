<?php
/**
 * Route Middleware
 *
 * Pipeline stage that dispatches requests to WPMind's PublicAPI.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\Transform\ModelMapper;
use WPMind\Modules\ApiGateway\Transform\ResponseTransformer;

/**
 * Class RouteMiddleware
 *
 * Reads the internal payload from the context and calls the
 * appropriate PublicAPI method based on the operation type.
 */
final class RouteMiddleware implements GatewayStageInterface {

	/**
	 * Process the gateway request context.
	 *
	 * @param GatewayRequestContext $context Shared request context.
	 */
	public function process( GatewayRequestContext $context ): void {
		if ( $context->has_error() ) {
			return;
		}

		$payload   = $context->get_internal_payload();
		$operation = $payload['operation'] ?? $context->operation();

		match ( $operation ) {
			'chat.completions' => $this->handle_chat( $context, $payload ),
			'embeddings'       => $this->handle_embeddings( $context, $payload ),
			'models'           => $this->handle_models( $context ),
			default            => $context->set_error(
				new \WP_Error(
					'unsupported_operation',
					sprintf( 'Unsupported operation: %s', $operation ),
					[ 'status' => 400 ]
				)
			),
		};
	}

	/**
	 * Handle a chat completions request.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 * @param array                 $payload Internal payload.
	 */
	private function handle_chat( GatewayRequestContext $context, array $payload ): void {
		$messages = $payload['messages'] ?? [];
		$options  = $payload['options'] ?? [];
		$stream   = $payload['stream'] ?? false;

		// For 'auto' provider, omit provider from options to let WPMind route.
		if ( ( $options['provider'] ?? '' ) === 'auto' ) {
			unset( $options['provider'], $options['model'] );
		}

		if ( $stream ) {
			// Streaming will be fully implemented in Phase 6.
			// For now, set a flag so the response layer knows.
			$context->set_response_header( 'X-WPMind-Stream', 'pending' );
			$context->set_internal_result( [
				'stream'  => true,
				'pending' => true,
			] );
			return;
		}

		$api = \WPMind\API\PublicAPI::instance();

		/** @var array|\WP_Error $result */
		$result = $api->chat( $messages, $options );

		if ( is_wp_error( $result ) ) {
			$context->set_error( $result );
			return;
		}

		$context->set_internal_result( $result );
	}

	/**
	 * Handle an embeddings request.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 * @param array                 $payload Internal payload.
	 */
	private function handle_embeddings( GatewayRequestContext $context, array $payload ): void {
		$input   = $payload['input'] ?? '';
		$options = $payload['options'] ?? [];

		// For 'auto' provider, omit provider from options.
		if ( ( $options['provider'] ?? '' ) === 'auto' ) {
			unset( $options['provider'], $options['model'] );
		}

		$api = \WPMind\API\PublicAPI::instance();

		/** @var array|\WP_Error $result */
		$result = $api->embed( $input, $options );

		if ( is_wp_error( $result ) ) {
			$context->set_error( $result );
			return;
		}

		$context->set_internal_result( $result );
	}

	/**
	 * Handle a models list request.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 */
	private function handle_models( GatewayRequestContext $context ): void {
		$models      = ModelMapper::get_available_models();
		$transformer = new ResponseTransformer();
		$data        = $transformer->transform_models( $models );

		$context->set_internal_result( $data );
	}
}
