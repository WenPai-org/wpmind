<?php
/**
 * Gateway Request Schema
 *
 * Defines WordPress REST API argument schemas for each endpoint.
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway;

/**
 * Class GatewayRequestSchema
 *
 * Static methods returning WP REST API args arrays
 * for request validation and sanitization.
 */
final class GatewayRequestSchema {

	/**
	 * Schema for chat completions endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function chat_completions(): array {
		return [
			'model'             => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'messages'          => [
				'type'              => 'array',
				'required'          => true,
				'maxItems'          => 256,
				'items'             => [
					'type'       => 'object',
					'properties' => [
						'role'    => [
							'type' => 'string',
							'enum' => [ 'system', 'user', 'assistant', 'tool' ],
						],
						'content' => [
							'type' => [ 'string', 'array' ],
						],
					],
				],
				'validate_callback' => [ __CLASS__, 'validate_messages' ],
			],
			'temperature'       => [
				'type'    => 'number',
				'default' => 1.0,
				'minimum' => 0,
				'maximum' => 2,
			],
			'max_tokens'        => [
				'type'    => 'integer',
				'default' => null,
				'minimum' => 1,
			],
			'top_p'             => [
				'type'    => 'number',
				'default' => 1.0,
				'minimum' => 0,
				'maximum' => 1,
			],
			'frequency_penalty' => [
				'type'    => 'number',
				'default' => 0,
				'minimum' => -2,
				'maximum' => 2,
			],
			'presence_penalty'  => [
				'type'    => 'number',
				'default' => 0,
				'minimum' => -2,
				'maximum' => 2,
			],
			'stream'            => [
				'type'    => 'boolean',
				'default' => false,
			],
			'stop'              => [
				'type'              => [ 'string', 'array' ],
				'default'           => null,
				'validate_callback' => [ __CLASS__, 'validate_stop' ],
			],
			'n'                 => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => [ __CLASS__, 'validate_n' ],
			],
			'tools'             => [
				'type'              => 'array',
				'default'           => null,
				'validate_callback' => [ __CLASS__, 'validate_tools' ],
			],
			'tool_choice'       => [
				'type'    => [ 'string', 'object' ],
				'default' => null,
			],
		];
	}

	/**
	 * Schema for embeddings endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function embeddings(): array {
		return [
			'model'           => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'input'           => [
				'type'              => [ 'string', 'array' ],
				'required'          => true,
				'validate_callback' => [ __CLASS__, 'validate_input' ],
			],
			'encoding_format' => [
				'type'    => 'string',
				'default' => 'float',
				'enum'    => [ 'float', 'base64' ],
			],
		];
	}

	/**
	 * Schema for responses endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function responses(): array {
		return [
			'model'        => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'input'        => [
				'type'     => [ 'string', 'array' ],
				'required' => true,
			],
			'instructions' => [
				'type'              => 'string',
				'default'           => null,
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'temperature'  => [
				'type'    => 'number',
				'default' => 1.0,
				'minimum' => 0,
				'maximum' => 2,
			],
			'max_tokens'   => [
				'type'    => 'integer',
				'default' => null,
				'minimum' => 1,
			],
			'tools'        => [
				'type'              => 'array',
				'default'           => null,
				'validate_callback' => [ __CLASS__, 'validate_tools' ],
			],
			'stream'       => [
				'type'    => 'boolean',
				'default' => false,
			],
		];
	}

	/**
	 * Schema for models endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function models(): array {
		return [];
	}

	/**
	 * Validate messages array.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_messages( $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rest_invalid_param', 'messages must be an array.', [ 'status' => 400 ] );
		}

		if ( count( $value ) === 0 ) {
			return new \WP_Error( 'rest_invalid_param', 'messages must not be empty.', [ 'status' => 400 ] );
		}

		if ( count( $value ) > 256 ) {
			return new \WP_Error( 'rest_invalid_param', 'messages must not exceed 256 items.', [ 'status' => 400 ] );
		}

		$valid_roles = [ 'system', 'user', 'assistant', 'tool' ];

		foreach ( $value as $i => $msg ) {
			if ( ! is_array( $msg ) || ! isset( $msg['role'], $msg['content'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf( 'messages[%d] must have role and content.', $i ),
					[ 'status' => 400 ]
				);
			}

			if ( ! in_array( $msg['role'], $valid_roles, true ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf( 'messages[%d].role must be one of: %s.', $i, implode( ', ', $valid_roles ) ),
					[ 'status' => 400 ]
				);
			}

			if ( ! is_string( $msg['content'] ) && ! is_array( $msg['content'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf( 'messages[%d].content must be a string or array.', $i ),
					[ 'status' => 400 ]
				);
			}
		}

		return true;
	}

	/**
	 * Validate stop parameter.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_stop( $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		if ( $value === null ) {
			return true;
		}

		if ( is_string( $value ) ) {
			if ( $value === '' ) {
				return new \WP_Error( 'rest_invalid_param', 'stop string must not be empty.', [ 'status' => 400 ] );
			}
			return true;
		}

		if ( is_array( $value ) ) {
			if ( count( $value ) > 4 ) {
				return new \WP_Error( 'rest_invalid_param', 'stop array must not exceed 4 items.', [ 'status' => 400 ] );
			}
			foreach ( $value as $item ) {
				if ( ! is_string( $item ) ) {
					return new \WP_Error( 'rest_invalid_param', 'Each stop item must be a string.', [ 'status' => 400 ] );
				}
			}
			return true;
		}

		return new \WP_Error( 'rest_invalid_param', 'stop must be a string or array.', [ 'status' => 400 ] );
	}

	/**
	 * Validate n parameter (must be 1).
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_n( $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		if ( (int) $value !== 1 ) {
			return new \WP_Error(
				'rest_invalid_param',
				'Only n=1 is supported. Multiple completions (n>1) are not available.',
				[ 'status' => 400 ]
			);
		}
		return true;
	}

	/**
	 * Validate tools array.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_tools( $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		if ( $value === null ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rest_invalid_param', 'tools must be an array.', [ 'status' => 400 ] );
		}

		foreach ( $value as $i => $tool ) {
			if ( ! is_array( $tool ) || ! isset( $tool['type'], $tool['function'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf( 'tools[%d] must have type and function keys.', $i ),
					[ 'status' => 400 ]
				);
			}
		}

		return true;
	}

	/**
	 * Validate input parameter for embeddings.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_input( $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		if ( is_string( $value ) ) {
			if ( $value === '' ) {
				return new \WP_Error( 'rest_invalid_param', 'input string must not be empty.', [ 'status' => 400 ] );
			}
			return true;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! is_string( $item ) ) {
					return new \WP_Error( 'rest_invalid_param', 'Each input item must be a string.', [ 'status' => 400 ] );
				}
			}
			return true;
		}

		return new \WP_Error( 'rest_invalid_param', 'input must be a string or array of strings.', [ 'status' => 400 ] );
	}
}
