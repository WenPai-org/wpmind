<?php
/**
 * Provider 注册入口
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 调试日志
 */
function debug_log( string $message ): void {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[WPMind] ' . $message );
    }
}

/**
 * 注册 WPMind Providers 到 WP AI Client
 */
function register_wpmind_providers(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    // 检查 WP AI Client 是否可用
    if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
        debug_log( 'AiClient class not found' );
        return;
    }

    // 检查 WPMind 是否已初始化
    if ( ! function_exists( 'WPMind\\wpmind' ) ) {
        debug_log( 'WPMind not initialized' );
        return;
    }

    // 检查 HTTP 发现策略是否可用（WP 7.0 核心 或 旧 AI 插件）
    $has_core_strategy  = class_exists( 'WP_AI_Client_Discovery_Strategy' );
    $has_plugin_strategy = class_exists( 'WordPress\\AI_Client\\HTTP\\WP_AI_Client_Discovery_Strategy' );

    if ( ! $has_core_strategy && ! $has_plugin_strategy ) {
        debug_log( 'WP_AI_Client_Discovery_Strategy not found (neither core nor plugin)' );
        return;
    }

    $registered = true;

    // WP 7.0 核心已在 wp-settings.php 调用 init()，仅旧 AI 插件需要手动初始化
    if ( ! $has_core_strategy && $has_plugin_strategy ) {
        \WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy::init();
    }

    $plugin = \WPMind\wpmind();
    $endpoints = $plugin->get_custom_endpoints();

    debug_log( 'Registering providers. Endpoints: ' . print_r( array_keys( $endpoints ), true ) );

    // 获取 ProviderRegistry 实例
    $registry = \WordPress\AiClient\AiClient::defaultRegistry();

    // 使用 ProviderRegistrar 注册所有已启用的 Provider
    ProviderRegistrar::registerProviders( $registry, $endpoints );

    // 调试：列出已注册的 Provider
    $registeredIds = $registry->getRegisteredProviderIds();
    debug_log( 'Registered provider IDs: ' . implode( ', ', $registeredIds ) );

}

// 在 init 钩子以优先级 5 注册 Provider
add_action( 'init', __NAMESPACE__ . '\\register_wpmind_providers', 5 );

// 备用：REST API 请求时也需要注册
add_action( 'rest_api_init', function(): void {
    register_wpmind_providers();
}, 5 );

/**
 * 获取 WPMind 配置的 API Key 凭据
 */
function get_wpmind_credentials(): array {
    static $cache = null;
    
    if ( $cache !== null ) {
        return $cache;
    }
    
    $cache = [];
    
    if ( ! function_exists( 'WPMind\\wpmind' ) ) {
        return $cache;
    }

    $plugin = \WPMind\wpmind();
    $endpoints = $plugin->get_custom_endpoints();

    foreach ( $endpoints as $provider_id => $endpoint ) {
        if ( ! empty( $endpoint['enabled'] ) && ! empty( $endpoint['api_key'] ) ) {
            $cache[ $provider_id ] = $endpoint['api_key'];
        }
    }

    return $cache;
}

/**
 * 使用 pre_option 过滤器短路返回合并后的凭据
 */
function pre_option_credentials( $pre_option ) {
    static $in_filter = false;
    if ( $in_filter ) {
        return $pre_option;
    }
    
    $wpmind_creds = get_wpmind_credentials();
    
    if ( empty( $wpmind_creds ) ) {
        return $pre_option;
    }
    
    $in_filter = true;
    
    remove_filter( 'pre_option_wp_ai_client_provider_credentials', __NAMESPACE__ . '\\pre_option_credentials' );
    $original = get_option( 'wp_ai_client_provider_credentials', [] );
    add_filter( 'pre_option_wp_ai_client_provider_credentials', __NAMESPACE__ . '\\pre_option_credentials' );
    
    $in_filter = false;
    
    if ( ! is_array( $original ) ) {
        $original = [];
    }
    
    return array_merge( $original, $wpmind_creds );
}

add_filter( 'pre_option_wp_ai_client_provider_credentials', __NAMESPACE__ . '\\pre_option_credentials', 1 );

/**
 * 在 WP 7.0+ Connectors 系统中注册 WPMind 的国产 AI Providers
 *
 * @since 3.8.0
 */
function register_wpmind_connectors( \WP_Connector_Registry $registry ): void {
    if ( ! function_exists( 'WPMind\\wpmind' ) ) {
        return;
    }

    $plugin    = \WPMind\wpmind();
    $endpoints = $plugin->get_custom_endpoints();
    $meta      = ProviderRegistrar::getConnectorMeta();

    foreach ( $meta as $provider_id => $connector_data ) {
        // 仅注册已启用的 provider
        if ( empty( $endpoints[ $provider_id ]['enabled'] ) ) {
            continue;
        }

        // 跳过已被核心或其他插件注册的 connector
        if ( $registry->is_registered( $provider_id ) ) {
            debug_log( "Connector '{$provider_id}' already registered, skipping" );
            continue;
        }

        $setting_name = 'connectors_ai_' . $provider_id . '_api_key';

        // 解析 logo URL
        $logo_path = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $provider_id . '.svg';
        $logo_url  = file_exists( $logo_path )
            ? WPMIND_PLUGIN_URL . 'assets/images/providers/' . $provider_id . '.svg'
            : null;

        $registry->register( $provider_id, [
            'name'           => $connector_data['name'],
            'description'    => $connector_data['description'],
            'type'           => 'ai_provider',
            'logo_url'       => $logo_url,
            'authentication' => [
                'method'          => 'api_key',
                'credentials_url' => $connector_data['credentials_url'] ?? '',
                'setting_name'    => $setting_name,
            ],
        ] );

        // 将 WPMind 管理的 API key 同步到 connector 的 option
        if ( ! empty( $endpoints[ $provider_id ]['api_key'] ) ) {
            $existing = get_option( $setting_name, '' );
            if ( '' === $existing ) {
                update_option( $setting_name, $endpoints[ $provider_id ]['api_key'] );
            }
        }
    }

    debug_log( 'WPMind connectors registered for WP 7.0+' );
}

// WP 7.0+ Connectors API — _wp_connectors_init 在 init:15 触发
if ( class_exists( 'WP_Connector_Registry' ) ) {
    add_action( 'wp_connectors_init', __NAMESPACE__ . '\\register_wpmind_connectors' );
}
