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
use WPMind\Modules\ApiGateway\Stream\SseStreamController;
use WPMind\Modules\ApiGateway\SchemaManager;

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
			'responses'        => $this->handle_chat( $context, $payload ),
			'models'           => $this->handle_models( $context ),
			'model_detail'     => $this->handle_model_detail( $context ),
			'status'           => $this->handle_status( $context ),
			default            => $context->set_error(
				new \WP_Error(
					'unsupported_operation',
					sprintf( 'Unsupported operation: %s', sanitize_text_field( $operation ) ),
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

		$options = $this->apply_provider_policy( $context, $options );
		if ( null === $options ) {
			return;
		}

		// Attach gateway caller context so site-wide usage ledger attributes this call without double-counting.
		$options['context'] = $this->build_gateway_context( $context );

		// Persist normalized options back onto payload (used by SseStreamController and LogMiddleware).
		$payload['options'] = $options;
		$context->set_internal_payload( $payload );

		if ( $stream ) {
			// Phase 6: SSE streaming via SseStreamController.
			// If slot acquisition fails, the controller sets an error
			// on the context and returns, allowing the pipeline to
			// produce a normal JSON error response.
			$controller = new SseStreamController();
			$controller->serve_chat_stream( $context );

			// If we reach here, slot acquisition failed and the error
			// is already set on the context. The pipeline continues.
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

		$options = $this->apply_provider_policy( $context, $options );
		if ( null === $options ) {
			return;
		}

		$options['context'] = $this->build_gateway_context( $context );
		$payload['options'] = $options;
		$context->set_internal_payload( $payload );

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
	 * Enforce per-key allowed_providers whitelist and normalize auto provider options.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 * @param array<string, mixed>  $options Provider options.
	 * @return array<string, mixed>|null Normalized options, or null when request is rejected with error.
	 */
	private function apply_provider_policy( GatewayRequestContext $context, array $options ): ?array {
		$auth_result       = $context->auth_result();
		$allowed_providers = [];

		if ( is_object( $auth_result ) && isset( $auth_result->allowed_providers ) && is_array( $auth_result->allowed_providers ) ) {
			$allowed_providers = array_values(
				array_filter(
					array_map( 'strval', $auth_result->allowed_providers ),
					static fn ( string $p ): bool => '' !== $p
				)
			);
		}

		$requested_provider = isset( $options['provider'] ) ? (string) $options['provider'] : 'auto';
		$is_auto            = ( '' === $requested_provider || 'auto' === $requested_provider );

		if ( ! empty( $allowed_providers ) ) {
			if ( ! $is_auto && ! in_array( $requested_provider, $allowed_providers, true ) ) {
				$context->set_error(
					new \WP_Error(
						'provider_not_allowed',
						sprintf(
							'Provider "%s" is not allowed for this API key.',
							sanitize_text_field( $requested_provider )
						),
						[ 'status' => 403 ]
					)
				);
				return null;
			}

			$options['failover_providers'] = $allowed_providers;
		}

		// For 'auto' provider, omit provider/model so WPMind routes dynamically.
		if ( $is_auto ) {
			unset( $options['provider'], $options['model'] );
		}

		return $options;
	}

	/**
	 * Build the UsageTracker context identifier for a gateway request.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 * @return string Context tag (e.g. api_gateway:sk_abcd1).
	 */
	private function build_gateway_context( GatewayRequestContext $context ): string {
		$auth_result = $context->auth_result();
		$prefix      = '';
		if ( is_object( $auth_result ) && isset( $auth_result->key_prefix ) && '' !== (string) $auth_result->key_prefix ) {
			$prefix = (string) $auth_result->key_prefix;
		} elseif ( null !== $context->key_id() ) {
			$prefix = substr( $context->key_id(), 0, 8 );
		} else {
			$prefix = 'anonymous';
		}

		return 'api_gateway:' . $prefix;
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

	/**
	 * Handle a single model detail request.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 */
	private function handle_model_detail( GatewayRequestContext $context ): void {
		$model_id = $context->rest_request()->get_param( 'model_id' );

		if ( $model_id === null || $model_id === '' ) {
			$context->set_error(
				new \WP_Error(
					'model_not_found',
					'Model ID is required.',
					[ 'status' => 400 ]
				)
			);
			return;
		}

		$resolved = ModelMapper::resolve( (string) $model_id );

		if ( $resolved === null ) {
			$context->set_error(
				new \WP_Error(
					'model_not_found',
					sprintf( 'Model "%s" is not available.', sanitize_text_field( (string) $model_id ) ),
					[ 'status' => 404 ]
				)
			);
			return;
		}

		$context->set_internal_result(
			[
				'id'       => (string) $model_id,
				'object'   => 'model',
				'created'  => 0,
				'owned_by' => 'wpmind',
			]
		);
	}

	/**
	 * Handle a gateway status request.
	 *
	 * Returns basic gateway health and configuration info.
	 *
	 * @param GatewayRequestContext $context Pipeline context.
	 */
	private function handle_status( GatewayRequestContext $context ): void {
		$context->set_internal_result(
			[
				'status'         => 'ok',
				'version'        => '1.0.0',
				'endpoints'      => [
					'/mind/v1/chat/completions',
					'/mind/v1/embeddings',
					'/mind/v1/responses',
					'/mind/v1/models',
					'/mind/v1/models/{model_id}',
					'/mind/v1/status',
				],
				'schema_version' => SchemaManager::get_schema_version(),
			]
		);
	}
}
