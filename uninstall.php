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

    // 删除 transients
    delete_transient( 'wpmind_budget_alerts_sent' );
    delete_transient( 'wpmind_provider_health' );
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
