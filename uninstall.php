<?php
/**
 * WPMind 卸载脚本
 *
 * 当插件被删除时清理数据库中的选项
 *
 * @package WPMind
 * @since 1.1.0
 */

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
        'wpmind_custom_endpoints',
        'wpmind_request_timeout',
        'wpmind_default_provider',
        'wpmind_image_endpoints',
        'wpmind_default_image_provider',
        'wpmind_usage_stats',
        'wpmind_usage_history',
        'wpmind_budget_settings',
        'wpmind_routing_settings',
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
    ];

    foreach ( $transients as $transient ) {
        delete_transient( $transient );
    }

    // 删除动态 transients（熔断器状态，按 provider 命名）
    // 格式: wpmind_cb_{provider_id}
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_wpmind_cb_%'
            OR option_name LIKE '_transient_timeout_wpmind_cb_%'"
    );
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
