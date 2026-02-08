<?php
/**
 * WPMind 卸载脚本
 *
 * 当插件被删除时清理数据库中的选项
 *
 * @package WPMind
 * @since 1.1.0
 */

declare(strict_types=1);

// 如果不是通过 WordPress 卸载，则退出
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 清理单个站点的所有 WPMind 数据
 */
function wpmind_cleanup_site_data(): void {
    // 删除所有插件选项
    $options = [
        // 核心设置
        'wpmind_custom_endpoints',
        'wpmind_request_timeout',
        'wpmind_default_provider',
        'wpmind_image_endpoints',
        'wpmind_default_image_provider',
        'wpmind_usage_stats',
        'wpmind_usage_history',
        'wpmind_budget_settings',
        'wpmind_routing_settings',
        // GEO 模块设置
        'wpmind_geo_enabled',
        'wpmind_chinese_optimize',
        'wpmind_geo_signals',
        'wpmind_standalone_markdown_feed',
        'wpmind_crawler_tracking',
        'wpmind_llms_txt_enabled',
        'wpmind_schema_enabled',
        'wpmind_schema_mode',
        'wpmind_crawler_logs',
        'wpmind_crawler_stats',
        // API Gateway 模块设置
        'wpmind_api_gateway_schema_version',
        // Exact Cache 模块设置
        'wpmind_exact_cache_enabled',
        'wpmind_exact_cache_default_ttl',
        'wpmind_exact_cache_max_entries',
        'wpmind_exact_cache_scope_mode',
        'wpmind_exact_cache_index',
        'wpmind_exact_cache_stats',
        'wpmind_exact_cache_daily_stats',
        // 激活标记
        'wpmind_flush_rewrite_rules',
    ];

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // 删除 transients（固定键名）
    $transients = [
        'wpmind_budget_alerts_sent',
        'wpmind_provider_health',
        'wpmind_budget_notices',
        'wpmind_round_robin_index',
        'wpmind_daily_stats_lock',
    ];

    foreach ( $transients as $transient ) {
        delete_transient( $transient );
    }

    // 删除动态 transients（熔断器状态，按 provider 命名）
    // 格式: wpmind_cb_{provider_id}
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s",
            '_transient_wpmind_cb_%',
            '_transient_timeout_wpmind_cb_%'
        )
    );

    // 删除 Exact Cache transient 缓存条目
    // 格式: wpmind_ec_{hash}
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s",
            '_transient_wpmind_ec_%',
            '_transient_timeout_wpmind_ec_%'
        )
    );

    // 删除动态模块启用选项和 llms.txt 缓存
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s",
            'wpmind_module_%',
            '_transient_wpmind_llms_txt_%'
        )
    );

    // 删除 API Gateway 模块的数据库表
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_audit_log" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_key_usage" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_keys" );
}

// 清理当前站点
wpmind_cleanup_site_data();

// 如果是多站点，清理所有站点的选项
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        wpmind_cleanup_site_data();
        restore_current_blog();
    }
}
