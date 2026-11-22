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

use WPMind\Provider\Catalog\ModelCatalog;
use WPMind\Provider\EndpointRepository;

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
	public const VISION_PROVIDERS = [ 'openai', 'anthropic', 'google', 'qwen', 'zhipu', 'wenpai_cloud' ];

	/**
	 * Fallback vision model per provider when catalog has no explicit vision-tagged model.
	 *
	 * @var array<string, string>
	 */
	public const VISION_MODELS = [
		'openai'       => 'gpt-4o',
		'anthropic'    => 'claude-3-5-sonnet-20241022',
		'google'       => 'gemini-2.0-flash',
		'qwen'         => 'qwen-vl-max',
		'zhipu'        => 'glm-4v',
		'wenpai_cloud' => 'auto',
	];

	/**
	 * Build multimodal vision messages for the chat API.
	 *
	 * Local/intranet URLs or local readable files are automatically converted to
	 * base64 data URIs when possible so remote Vision APIs can read them.
	 *
	 * @param string $image_url     Image URL, local file path, or base64 data URI.
	 * @param string $prompt        User prompt describing what to do with the image.
	 * @param string $system        Optional system prompt.
	 * @param int    $attachment_id Optional WordPress attachment ID.
	 * @return array Messages array compatible with wpmind_chat().
	 */
	public static function build_vision_messages( string $image_url, string $prompt, string $system = '', int $attachment_id = 0 ): array {
		$resolved_image_url = self::normalize_image_url( $image_url, $attachment_id );
		$messages           = [];

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
					'image_url' => [ 'url' => $resolved_image_url ],
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
	 * Normalize an image URL or local file path for Vision API consumption.
	 *
	 * Local/intranet targets (localhost, 127.0.0.1, .local, .test, private IPs,
	 * or readable local files) prefer attachment_to_data_uri() / data URI encoding.
	 *
	 * @param string $image_url     Image URL, local file path, or data URI.
	 * @param int    $attachment_id Optional WordPress attachment ID.
	 * @return string Normalized URL or data URI.
	 */
	public static function normalize_image_url( string $image_url, int $attachment_id = 0 ): string {
		$image_url = trim( $image_url );
		if ( '' === $image_url || str_starts_with( $image_url, 'data:' ) ) {
			return $image_url;
		}

		// Local readable file path.
		if ( ! preg_match( '#^https?://#i', $image_url ) && is_file( $image_url ) && is_readable( $image_url ) ) {
			$data_uri = self::file_to_data_uri( $image_url );
			if ( false !== $data_uri ) {
				return $data_uri;
			}
		}

		// Local or intranet HTTP(S) URL.
		if ( self::is_local_or_intranet_url( $image_url ) ) {
			if ( $attachment_id > 0 ) {
				$data_uri = self::attachment_to_data_uri( $attachment_id );
				if ( false !== $data_uri ) {
					return $data_uri;
				}
			}

			if ( function_exists( 'attachment_url_to_postid' ) ) {
				$found_id = (int) attachment_url_to_postid( $image_url );
				if ( $found_id > 0 ) {
					$data_uri = self::attachment_to_data_uri( $found_id );
					if ( false !== $data_uri ) {
						return $data_uri;
					}
				}
			}

			$local_file = self::map_upload_url_to_file( $image_url );
			if ( null !== $local_file ) {
				$data_uri = self::file_to_data_uri( $local_file );
				if ( false !== $data_uri ) {
					return $data_uri;
				}
			}
		}

		return $image_url;
	}

	/**
	 * Determine whether a URL points to localhost or an intranet address.
	 *
	 * @param string $url URL to inspect.
	 * @return bool
	 */
	public static function is_local_or_intranet_url( string $url ): bool {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host = strtolower( trim( $host, '[]' ) );

		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1', '0.0.0.0' ], true ) ) {
			return true;
		}

		if ( str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' ) || str_ends_with( $host, '.localhost' ) ) {
			return true;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return false;
	}

	/**
	 * Convert a WordPress attachment to a base64 data URI.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return string|false Data URI string or false on failure.
	 */
	public static function attachment_to_data_uri( int $attachment_id ): string|false {
		if ( ! function_exists( 'get_attached_file' ) ) {
			return false;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			return false;
		}

		$mime = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $attachment_id ) : '';
		return self::file_to_data_uri( $file, $mime );
	}

	/**
	 * Convert a local image file to a base64 data URI.
	 *
	 * @param string $file Local file path.
	 * @param string $mime Optional MIME type override.
	 * @return string|false Data URI string or false on failure.
	 */
	public static function file_to_data_uri( string $file, string $mime = '' ): string|false {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		if ( '' === $mime ) {
			$mime = self::detect_image_mime( $file );
		}

		if ( '' === $mime || ! str_starts_with( $mime, 'image/' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local image file for data URI encoding.
		$data = file_get_contents( $file );
		if ( false === $data ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by multimodal data URI spec.
		return 'data:' . $mime . ';base64,' . base64_encode( $data );
	}

	/**
	 * Select a vision-capable provider from configured endpoints via EndpointRepository.
	 *
	 * Falls back to 'openai' if no vision provider is configured.
	 *
	 * @return string Provider slug.
	 */
	public static function get_vision_provider(): string {
		$configured = self::get_configured_vision_providers();
		if ( [] === $configured ) {
			return 'openai';
		}

		// Prefer the default provider if it supports vision and is configured.
		$default = (string) get_option( 'wpmind_default_provider', '' );
		if ( '' !== $default && in_array( $default, $configured, true ) ) {
			return $default;
		}

		return $configured[0];
	}

	/**
	 * Get the vision model for a provider via ModelCatalog (never returns retired models).
	 *
	 * @param string $provider Provider slug.
	 * @return string Model identifier.
	 */
	public static function get_vision_model( string $provider ): string {
		$catalog       = ModelCatalog::instance();
		$vision_models = $catalog->for_provider( $provider, 'vision', false );

		if ( [] !== $vision_models ) {
			$default = $catalog->default_model( $provider );
			if ( '' !== $default && isset( $vision_models[ $default ] ) ) {
				return $default;
			}
			return (string) array_key_first( $vision_models );
		}

		$fallback = self::VISION_MODELS[ $provider ] ?? $catalog->default_model( $provider );
		if ( '' === $fallback ) {
			return 'gpt-4o';
		}

		return $catalog->resolve( $provider, $fallback )->model;
	}

	/**
	 * Get all configured vision-capable providers (enabled + has API key via EndpointRepository).
	 *
	 * Used to constrain the failover chain so non-vision providers
	 * are never tried with multimodal image messages.
	 *
	 * @return string[] Array of provider slugs.
	 */
	public static function get_configured_vision_providers(): array {
		$repo    = new EndpointRepository();
		$catalog = ModelCatalog::instance();
		$enabled = $repo->enabled();

		$configured = [];
		foreach ( array_keys( $enabled ) as $provider ) {
			$has_catalog_vision = [] !== $catalog->for_provider( $provider, 'vision', false );
			if ( $has_catalog_vision || in_array( $provider, self::VISION_PROVIDERS, true ) ) {
				$configured[] = $provider;
			}
		}

		return $configured;
	}

	/**
	 * Map a WordPress uploads URL back to a local readable path if present.
	 *
	 * @param string $url Image URL.
	 * @return string|null Local path or null.
	 */
	private static function map_upload_url_to_file( string $url ): ?string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return null;
		}

		$uploads = wp_upload_dir();
		$baseurl = rtrim( $uploads['baseurl'], '/' );
		$basedir = rtrim( $uploads['basedir'], '/' );

		if ( '' === $baseurl || '' === $basedir || ! str_starts_with( $url, $baseurl . '/' ) ) {
			return null;
		}

		$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
		$path     = $basedir . '/' . $relative;
		$real     = realpath( $path );
		$realbase = realpath( $basedir );

		if ( false === $real || false === $realbase || ! str_starts_with( $real, $realbase ) ) {
			return null;
		}

		return is_readable( $real ) ? $real : null;
	}

	/**
	 * Detect image MIME type from a local file path.
	 *
	 * @param string $file Local file path.
	 * @return string MIME type or empty string.
	 */
	private static function detect_image_mime( string $file ): string {
		if ( function_exists( 'wp_check_filetype' ) ) {
			$checked = wp_check_filetype( $file );
			if ( is_string( $checked['type'] ) && '' !== $checked['type'] ) {
				return $checked['type'];
			}
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$map = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'bmp'  => 'image/bmp',
			'avif' => 'image/avif',
		];

		if ( isset( $map[ $ext ] ) ) {
			return $map[ $ext ];
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$detected = mime_content_type( $file );
			if ( is_string( $detected ) ) {
				return $detected;
			}
		}

		return '';
	}
}
