<?php
/**
 * WP 7.x Connectors 凭证读取。
 *
 * 只读 connectors_ai_* option：不写、不删、不注册 connector。
 * connector option 由 WP core Connectors 系统管理（Settings > Connectors），
 * wpmind 把它作为 wpmind 自有凭证的回落来源，实现单一凭证两处可用。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

/**
 * Connector credential reader.
 */
final class ConnectorCredentials {

	/**
	 * Connector setting option name for a provider id.
	 *
	 * Matches the pattern used by WP core Connectors for AI providers:
	 * connectors_ai_<sanitized_id>_api_key.
	 *
	 * @param string $provider_id Provider id (e.g. deepseek).
	 * @return string Option name.
	 */
	public static function option_name( string $provider_id ): string {
		return 'connectors_ai_' . str_replace( '-', '_', sanitize_key( $provider_id ) ) . '_api_key';
	}

	/**
	 * Read the connector-stored API key for a provider.
	 *
	 * Returns an empty string when the Connectors API is absent (WP < 7.0)
	 * or when no key has been stored through the Connectors UI.
	 *
	 * @param string $provider_id Provider id.
	 * @return string API key or empty string.
	 */
	public static function key_for( string $provider_id ): string {
		if ( ! class_exists( 'WP_Connector_Registry' ) ) {
			return '';
		}

		$key = get_option( self::option_name( $provider_id ), '' );

		return is_string( $key ) ? trim( $key ) : '';
	}
}
