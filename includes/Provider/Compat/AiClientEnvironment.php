<?php
/**
 * Detect WordPress AI Client SDK environment without issuing network requests.
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Compat;

/**
 * Runtime environment probe for the AI Client compatibility matrix.
 */
final class AiClientEnvironment {

	/**
	 * Detect current environment.
	 *
	 * @return array{
	 *   wp_version: string,
	 *   sdk_source: 'none'|'core'|'plugin',
	 *   sdk_version: ?string,
	 *   has_connectors: bool,
	 *   has_prompt_fn: bool,
	 *   supports_ai: bool
	 * }
	 */
	public static function detect(): array {
		$wp_version = defined( 'ABSPATH' ) && function_exists( 'get_bloginfo' )
			? (string) get_bloginfo( 'version' )
			: ( isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '' );

		$has_core_strategy   = class_exists( 'WP_AI_Client_Discovery_Strategy' );
		$has_plugin_strategy = class_exists( 'WordPress\\AI_Client\\HTTP\\WP_AI_Client_Discovery_Strategy' );
		$has_ai_client       = class_exists( 'WordPress\\AiClient\\AiClient' );

		$sdk_source = 'none';
		if ( $has_core_strategy ) {
			$sdk_source = 'core';
		} elseif ( $has_plugin_strategy || $has_ai_client ) {
			$sdk_source = 'plugin';
		}

		$sdk_version = null;
		if ( $has_ai_client && defined( 'WordPress\\AiClient\\AiClient::VERSION' ) ) {
			$sdk_version = (string) constant( 'WordPress\\AiClient\\AiClient::VERSION' );
		} elseif ( $has_ai_client ) {
			try {
				$ref = new \ReflectionClass( 'WordPress\\AiClient\\AiClient' );
				if ( $ref->hasConstant( 'VERSION' ) ) {
					$sdk_version = (string) $ref->getConstant( 'VERSION' );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Ignore reflection failures; leave version null.
			}
		}

		$supports_ai = true;
		if ( function_exists( 'wp_supports_ai' ) ) {
			$supports_ai = (bool) wp_supports_ai();
		}

		return array(
			'wp_version'     => $wp_version,
			'sdk_source'     => $sdk_source,
			'sdk_version'    => $sdk_version,
			'has_connectors' => class_exists( 'WP_Connector_Registry' ),
			'has_prompt_fn'  => function_exists( 'wp_ai_client_prompt' ),
			'supports_ai'    => $supports_ai,
		);
	}
}
