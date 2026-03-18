<?php
/**
 * Error Mapper
 *
 * Maps WPMind WP_Error codes to OpenAI-compatible error responses.
 *
 * @package WPMind\Modules\ApiGateway\Error
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Error;

/**
 * Class ErrorMapper
 *
 * Static utility for converting internal error codes to
 * OpenAI-compatible error format with correct HTTP status codes.
 */
final class ErrorMapper {

	/**
	 * WP_Error code to OpenAI error mapping.
	 *
	 * Each entry maps to: [type, code, HTTP status].
	 *
	 * @var array<string, array{type: string, code: string, status: int}>
	 */
	private const MAP = [
		'missing_auth_header'      => [
			'type'   => 'invalid_request_error',
			'code'   => 'missing_auth_header',
			'status' => 401,
		],
		'invalid_auth_header'      => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 401,
		],
		'invalid_api_key_format'   => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 401,
		],
		'api_key_not_found'        => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 401,
		],
		'api_key_invalid_secret'   => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 401,
		],
		'api_key_inactive'         => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 403,
		],
		'api_key_expired'          => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 403,
		],
		'api_key_ip_denied'        => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 403,
		],
		'insufficient_quota'       => [
			'type'   => 'insufficient_quota',
			'code'   => 'insufficient_quota',
			'status' => 429,
		],
		'rate_limit_exceeded'      => [
			'type'   => 'rate_limit_exceeded',
			'code'   => 'rate_limit_exceeded',
			'status' => 429,
		],
		'model_not_found'          => [
			'type'   => 'invalid_request_error',
			'code'   => 'model_not_found',
			'status' => 400,
		],
		'request_too_large'        => [
			'type'   => 'invalid_request_error',
			'code'   => 'request_too_large',
			'status' => 413,
		],
		'not_authenticated'        => [
			'type'   => 'invalid_request_error',
			'code'   => 'invalid_api_key',
			'status' => 401,
		],
		'forbidden'                => [
			'type'   => 'invalid_request_error',
			'code'   => 'insufficient_permissions',
			'status' => 403,
		],
		'sse_concurrency_exceeded' => [
			'type'   => 'rate_limit_exceeded',
			'code'   => 'rate_limit_exceeded',
			'status' => 429,
		],
		'sse_lock_timeout'         => [
			'type'   => 'server_error',
			'code'   => 'server_error',
			'status' => 503,
		],
		'unsupported_operation'    => [
			'type'   => 'invalid_request_error',
			'code'   => 'unsupported_operation',
			'status' => 400,
		],
	];

	/**
	 * Default mapping for unknown error codes.
	 *
	 * @var array{type: string, code: string, status: int}
	 */
	private const DEFAULT_MAP = [
		'type'   => 'server_error',
		'code'   => 'internal_error',
		'status' => 500,
	];

	/**
	 * Map a WP_Error code to OpenAI error metadata.
	 *
	 * @param string $wp_error_code WP_Error code.
	 * @return array{type: string, code: string, status: int}
	 */
	public static function map( string $wp_error_code ): array {
		return self::MAP[ $wp_error_code ] ?? self::DEFAULT_MAP;
	}

	/**
	 * Build an OpenAI-compatible error response body.
	 *
	 * @param string $message Human-readable error message.
	 * @param string $type    OpenAI error type.
	 * @param string $code    OpenAI error code.
	 * @return array{error: array{message: string, type: string, param: null, code: string}}
	 */
	public static function format_openai_error( string $message, string $type, string $code ): array {
		return [
			'error' => [
				'message' => $message,
				'type'    => $type,
				'param'   => null,
				'code'    => $code,
			],
		];
	}
}
