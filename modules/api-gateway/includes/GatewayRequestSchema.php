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
				'type'     => 'array',
				'required' => true,
			],
			'temperature'       => [
				'type'    => 'number',
				'default' => 1.0,
			],
			'max_tokens'        => [
				'type'    => 'integer',
				'default' => null,
			],
			'top_p'             => [
				'type'    => 'number',
				'default' => 1.0,
			],
			'frequency_penalty' => [
				'type'    => 'number',
				'default' => 0,
			],
			'presence_penalty'  => [
				'type'    => 'number',
				'default' => 0,
			],
			'stream'            => [
				'type'    => 'boolean',
				'default' => false,
			],
			'stop'              => [
				'type'    => [ 'string', 'array' ],
				'default' => null,
			],
			'n'                 => [
				'type'    => 'integer',
				'default' => 1,
			],
			'tools'             => [
				'type'    => 'array',
				'default' => null,
			],
			'tool_choice'       => [
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
				'type'     => [ 'string', 'array' ],
				'required' => true,
			],
			'encoding_format' => [
				'type'    => 'string',
				'default' => 'float',
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
			'model'       => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'input'       => [
				'type'     => [ 'string', 'array' ],
				'required' => true,
			],
			'instructions' => [
				'type'    => 'string',
				'default' => null,
			],
			'temperature' => [
				'type'    => 'number',
				'default' => 1.0,
			],
			'max_tokens'  => [
				'type'    => 'integer',
				'default' => null,
			],
			'tools'       => [
				'type'    => 'array',
				'default' => null,
			],
			'stream'      => [
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
}
