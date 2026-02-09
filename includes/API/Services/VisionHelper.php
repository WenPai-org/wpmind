<?php
/**
 * Vision Helper
 *
 * Static utility class for constructing multimodal vision messages.
 * Reuses the existing chat API for vision capabilities.
 *
 * @package WPMind\API\Services
 * @since 4.3.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

/**
 * Class VisionHelper
 *
 * Builds multimodal messages and resolves vision-capable providers.
 */
class VisionHelper {

	/**
	 * Providers that support vision (multimodal image input).
	 *
	 * @var string[]
	 */
	public const VISION_PROVIDERS = [ 'openai', 'anthropic', 'google', 'qwen', 'zhipu' ];

	/**
	 * Default vision model per provider.
	 *
	 * @var array<string, string>
	 */
	public const VISION_MODELS = [
		'openai'    => 'gpt-4o',
		'anthropic' => 'claude-3-5-sonnet-20241022',
		'google'    => 'gemini-2.0-flash-exp',
		'qwen'      => 'qwen-vl-max',
		'zhipu'     => 'glm-4v',
	];

	/**
	 * Build multimodal vision messages for the chat API.
	 *
	 * @param string $image_url Image URL or base64 data URI.
	 * @param string $prompt    User prompt describing what to do with the image.
	 * @param string $system    Optional system prompt.
	 * @return array Messages array compatible with wpmind_chat().
	 */
	public static function build_vision_messages( string $image_url, string $prompt, string $system = '' ): array {
		$messages = [];

		if ( '' !== $system ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $system,
			];
		}

		$messages[] = [
			'role'    => 'user',
			'content' => [
				[
					'type'      => 'image_url',
					'image_url' => [ 'url' => $image_url ],
				],
				[
					'type' => 'text',
					'text' => $prompt,
				],
			],
		];

		return $messages;
	}

	/**
	 * Convert a WordPress attachment to a base64 data URI.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return string|false Data URI string or false on failure.
	 */
	public static function attachment_to_data_uri( int $attachment_id ): string|false {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! $mime || ! str_starts_with( $mime, 'image/' ) ) {
			return false;
		}

		$data = file_get_contents( $file );
		if ( false === $data ) {
			return false;
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $data );
	}

	/**
	 * Select a vision-capable provider from configured endpoints.
	 *
	 * Falls back to 'openai' if no vision provider is configured.
	 *
	 * @return string Provider slug.
	 */
	public static function get_vision_provider(): string {
		$endpoints = get_option( 'wpmind_custom_endpoints', [] );

		if ( ! is_array( $endpoints ) ) {
			return 'openai';
		}

		// Prefer the default provider if it supports vision.
		$default = get_option( 'wpmind_default_provider', 'openai' );
		if ( in_array( $default, self::VISION_PROVIDERS, true ) ) {
			$ep = $endpoints[ $default ] ?? [];
			if ( ! empty( $ep['enabled'] ) && ! empty( $ep['api_key'] ) ) {
				return $default;
			}
		}

		// Otherwise pick the first enabled vision-capable provider.
		foreach ( self::VISION_PROVIDERS as $provider ) {
			$ep = $endpoints[ $provider ] ?? [];
			if ( ! empty( $ep['enabled'] ) && ! empty( $ep['api_key'] ) ) {
				return $provider;
			}
		}

		return 'openai';
	}

	/**
	 * Get the default vision model for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string Model identifier.
	 */
	public static function get_vision_model( string $provider ): string {
		return self::VISION_MODELS[ $provider ] ?? 'gpt-4o';
	}

	/**
	 * Get all configured vision-capable providers (enabled + has API key).
	 *
	 * Used to constrain the failover chain so non-vision providers
	 * are never tried with multimodal image messages.
	 *
	 * @return string[] Array of provider slugs.
	 */
	public static function get_configured_vision_providers(): array {
		$endpoints = get_option( 'wpmind_custom_endpoints', [] );

		if ( ! is_array( $endpoints ) ) {
			return [];
		}

		$configured = [];
		foreach ( self::VISION_PROVIDERS as $provider ) {
			$ep = $endpoints[ $provider ] ?? [];
			if ( ! empty( $ep['enabled'] ) && ! empty( $ep['api_key'] ) ) {
				$configured[] = $provider;
			}
		}

		return $configured;
	}
}
