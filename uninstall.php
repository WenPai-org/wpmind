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

// 删除插件选项
delete_option( 'wpmind_custom_endpoints' );
delete_option( 'wpmind_request_timeout' );
delete_option( 'wpmind_default_provider' );

// 如果是多站点，清理所有站点的选项
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        delete_option( 'wpmind_custom_endpoints' );
        delete_option( 'wpmind_request_timeout' );
        delete_option( 'wpmind_default_provider' );
        restore_current_blog();
    }
}
