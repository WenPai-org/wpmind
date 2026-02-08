<?php
/**
 * Request Transformer
 *
 * Converts OpenAI-compatible REST requests into WPMind internal payloads.
 *
 * @package WPMind\Modules\ApiGateway\Transform
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Transform;

/**
 * Class RequestTransformer
 *
 * Transforms incoming OpenAI-format REST requests into the internal
 * payload structure consumed by WPMind's PublicAPI.
 */
final class RequestTransformer {

	/**
	 * Transform a REST request into an internal payload.
	 *
	 * @param string           $operation Operation type (e.g. 'chat.completions').
	 * @param \WP_REST_Request $request   WordPress REST request.
	 * @return array{payload?: array, error?: \WP_Error} Result with payload or error.
	 */
	public function transform( string $operation, \WP_REST_Request $request ): array {
		$body_check = $this->check_body_size( $request );

		if ( is_wp_error( $body_check ) ) {
			return [ 'error' => $body_check ];
		}

		return match ( $operation ) {
			'chat.completions' => $this->transform_chat_completions( $request ),
			'embeddings'       => $this->transform_embeddings( $request ),
			'responses'        => $this->transform_responses( $request ),
			'models'           => [ 'payload' => [ 'operation' => 'models' ] ],
			'model_detail'     => [ 'payload' => [ 'operation' => 'model_detail' ] ],
			'status'           => [ 'payload' => [ 'operation' => 'status' ] ],
			default            => [
				'error' => new \WP_Error(
					'unsupported_operation',
					sprintf( 'Unsupported operation: %s', sanitize_text_field( $operation ) ),
					[ 'status' => 400 ]
				),
			],
		};
	}

	/**
	 * Check request body size against configured limit.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error True if within limit, WP_Error if exceeded.
	 */
	private function check_body_size( \WP_REST_Request $request ): true|\WP_Error {
		$max_bytes = (int) get_option( 'wpmind_gateway_max_body_bytes', 10485760 );
		$body_size = strlen( $request->get_body() );

		if ( $body_size > $max_bytes ) {
			return new \WP_Error(
				'request_too_large',
				sprintf(
					'Request body size %d bytes exceeds maximum %d bytes.',
					$body_size,
					$max_bytes
				),
				[ 'status' => 413 ]
			);
		}

		return true;
	}

	/**
	 * Enforce max_tokens cap from site configuration.
	 *
	 * @param int|null $max_tokens Requested max_tokens value.
	 * @return int|null Capped value or null.
	 */
	private function cap_max_tokens( ?int $max_tokens ): ?int {
		if ( $max_tokens === null ) {
			return null;
		}

		$cap = (int) get_option( 'wpmind_gateway_max_tokens_cap', 16384 );

		return min( $max_tokens, $cap );
	}

	/**
	 * Transform a chat completions request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array{payload?: array, error?: \WP_Error}
	 */
	private function transform_chat_completions( \WP_REST_Request $request ): array {
		$model    = $request->get_param( 'model' );
		$resolved = ModelMapper::resolve( (string) $model );

		if ( $resolved === null ) {
			return [
				'error' => new \WP_Error(
					'model_not_found',
					sprintf( 'Model "%s" is not available.', sanitize_text_field( (string) $model ) ),
					[ 'status' => 400 ]
				),
			];
		}

		$messages   = $request->get_param( 'messages' );
		$max_tokens = $request->get_param( 'max_tokens' );
		$stream     = (bool) $request->get_param( 'stream' );

		$options = [
			'provider' => $resolved['provider'],
			'model'    => $resolved['model'],
		];

		$temperature = $request->get_param( 'temperature' );
		if ( $temperature !== null ) {
			$options['temperature'] = (float) $temperature;
		}

		$capped_max_tokens = $this->cap_max_tokens(
			$max_tokens !== null ? (int) $max_tokens : null
		);
		if ( $capped_max_tokens !== null ) {
			$options['max_tokens'] = $capped_max_tokens;
		}

		$top_p = $request->get_param( 'top_p' );
		if ( $top_p !== null ) {
			$options['top_p'] = (float) $top_p;
		}

		$frequency_penalty = $request->get_param( 'frequency_penalty' );
		if ( $frequency_penalty !== null ) {
			$options['frequency_penalty'] = (float) $frequency_penalty;
		}

		$presence_penalty = $request->get_param( 'presence_penalty' );
		if ( $presence_penalty !== null ) {
			$options['presence_penalty'] = (float) $presence_penalty;
		}

		$stop = $request->get_param( 'stop' );
		if ( $stop !== null ) {
			$options['stop'] = $stop;
		}

		$tools = $request->get_param( 'tools' );
		if ( $tools !== null ) {
			$options['tools'] = $tools;
		}

		$tool_choice = $request->get_param( 'tool_choice' );
		if ( $tool_choice !== null ) {
			$options['tool_choice'] = $tool_choice;
		}

		return [
			'payload' => [
				'operation'      => 'chat.completions',
				'provider'       => $resolved['provider'],
				'model'          => $resolved['model'],
				'original_model' => (string) $model,
				'messages'       => $messages,
				'options'        => $options,
				'stream'         => $stream,
			],
		];
	}

	/**
	 * Transform an embeddings request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array{payload?: array, error?: \WP_Error}
	 */
	private function transform_embeddings( \WP_REST_Request $request ): array {
		$model    = $request->get_param( 'model' );
		$resolved = ModelMapper::resolve( (string) $model );

		if ( $resolved === null ) {
			return [
				'error' => new \WP_Error(
					'model_not_found',
					sprintf( 'Model "%s" is not available.', sanitize_text_field( (string) $model ) ),
					[ 'status' => 400 ]
				),
			];
		}

		$input           = $request->get_param( 'input' );
		$encoding_format = $request->get_param( 'encoding_format' );

		$options = [
			'provider' => $resolved['provider'],
			'model'    => $resolved['model'],
		];

		if ( $encoding_format !== null ) {
			$options['encoding_format'] = (string) $encoding_format;
		}

		return [
			'payload' => [
				'operation'      => 'embeddings',
				'provider'       => $resolved['provider'],
				'model'          => $resolved['model'],
				'original_model' => (string) $model,
				'input'          => $input,
				'options'        => $options,
			],
		];
	}

	/**
	 * Transform a Responses API request into chat format.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array{payload?: array, error?: \WP_Error}
	 */
	private function transform_responses( \WP_REST_Request $request ): array {
		$model    = $request->get_param( 'model' );
		$resolved = ModelMapper::resolve( (string) $model );

		if ( $resolved === null ) {
			return [
				'error' => new \WP_Error(
					'model_not_found',
					sprintf( 'Model "%s" is not available.', sanitize_text_field( (string) $model ) ),
					[ 'status' => 400 ]
				),
			];
		}

		$input        = $request->get_param( 'input' );
		$instructions = $request->get_param( 'instructions' );
		$max_tokens   = $request->get_param( 'max_tokens' );
		$stream       = (bool) $request->get_param( 'stream' );

		$messages = $this->convert_responses_input_to_messages( $input, $instructions );

		$options = [
			'provider' => $resolved['provider'],
			'model'    => $resolved['model'],
		];

		$temperature = $request->get_param( 'temperature' );
		if ( $temperature !== null ) {
			$options['temperature'] = (float) $temperature;
		}

		$capped_max_tokens = $this->cap_max_tokens(
			$max_tokens !== null ? (int) $max_tokens : null
		);
		if ( $capped_max_tokens !== null ) {
			$options['max_tokens'] = $capped_max_tokens;
		}

		$tools = $request->get_param( 'tools' );
		if ( $tools !== null ) {
			$options['tools'] = $tools;
		}

		return [
			'payload' => [
				'operation'      => 'chat.completions',
				'provider'       => $resolved['provider'],
				'model'          => $resolved['model'],
				'original_model' => (string) $model,
				'messages'       => $messages,
				'options'        => $options,
				'stream'         => $stream,
				'source_format'  => 'responses',
			],
		];
	}

	/**
	 * Convert Responses API input to chat messages format.
	 *
	 * @param string|array $input        The input field from Responses API.
	 * @param string|null  $instructions Optional system instructions.
	 * @return array Chat messages array.
	 */
	private function convert_responses_input_to_messages( string|array $input, ?string $instructions ): array {
		$messages = [];

		if ( $instructions !== null && $instructions !== '' ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $instructions,
			];
		}

		if ( is_string( $input ) ) {
			$messages[] = [
				'role'    => 'user',
				'content' => $input,
			];
		} elseif ( is_array( $input ) ) {
			foreach ( $input as $item ) {
				if ( is_string( $item ) ) {
					$messages[] = [
						'role'    => 'user',
						'content' => $item,
					];
				} elseif ( is_array( $item ) && isset( $item['role'], $item['content'] ) ) {
					$messages[] = [
						'role'    => (string) $item['role'],
						'content' => $item['content'],
					];
				}
			}
		}

		return $messages;
	}
}
