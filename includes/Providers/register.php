<?php
/**
 * Provider 注册入口
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy;

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

    // 检查 HTTP 发现策略类是否可用
    if ( ! class_exists( 'WordPress\\AI_Client\\HTTP\\WP_AI_Client_Discovery_Strategy' ) ) {
        debug_log( 'WP_AI_Client_Discovery_Strategy not found' );
        return;
    }

    $registered = true;

    // 先初始化 HTTP 客户端发现策略
    WP_AI_Client_Discovery_Strategy::init();

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

    // 调试：检查每个 WPMind Provider 的配置状态
    foreach ( $endpoints as $key => $endpoint ) {
        if ( ! empty( $endpoint['enabled'] ) && ! empty( $endpoint['api_key'] ) ) {
            $providerClass = ProviderRegistrar::getProviderClass( $key );
            if ( $providerClass && $registry->hasProvider( $providerClass ) ) {
                try {
                    $isConfigured = $registry->isProviderConfigured( $providerClass );
                    debug_log( "Provider $key ($providerClass) isConfigured: " . ( $isConfigured ? 'true' : 'false' ) );
                } catch ( \Exception $e ) {
                    debug_log( "Provider $key check failed: " . $e->getMessage() );
                }
            }
        }
    }
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
