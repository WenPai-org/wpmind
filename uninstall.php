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
		'wpmind_installed_version',
		// endpoint schema v2 迁移.
		'wpmind_endpoint_schema_version',
		'wpmind_migration_log',
		'wpmind_migration_lock',
		'wpmind_custom_endpoints_v1_backup',
		'wpmind_custom_endpoints_v2',
		'wpmind_custom_endpoints_backups',
		'wenpai_license_key_wpmind',
		'wenpai_license_wpmind_grace',
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
		'wpmind_gateway_enabled',
		'wpmind_gateway_sse_global_limit',
		'wpmind_gateway_default_rpm',
		'wpmind_gateway_default_tpm',
		'wpmind_gateway_max_body_bytes',
		'wpmind_gateway_max_tokens_cap',
		'wpmind_gateway_log_prompts',
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
		'wpmind_db_version',
	];

	// Only delete connector mirrors whose value equals the WPMind key.
	// Compare before deleting wpmind_custom_endpoints.
	$wpmind_endpoints = get_option( 'wpmind_custom_endpoints', [] );
	if ( ! is_array( $wpmind_endpoints ) ) {
		$wpmind_endpoints = [];
	}
	$wpmind_connector_ids = [
		'deepseek',
		'qwen',
		'zhipu',
		'moonshot',
		'doubao',
		'siliconflow',
		'baidu',
		'minimax',
	];
	foreach ( $wpmind_connector_ids as $wpmind_provider_id ) {
		$wpmind_option = 'connectors_ai_' . $wpmind_provider_id . '_api_key';
		$wpmind_mirror = get_option( $wpmind_option, null );
		if ( ! is_string( $wpmind_mirror ) || '' === $wpmind_mirror ) {
			continue;
		}
		$wpmind_entry = $wpmind_endpoints[ $wpmind_provider_id ] ?? null;
		$wpmind_key   = is_array( $wpmind_entry ) ? (string) ( $wpmind_entry['api_key'] ?? '' ) : '';
		if ( '' !== $wpmind_key && $wpmind_mirror === $wpmind_key ) {
			delete_option( $wpmind_option );
		}
	}

	// 带时间戳的端点配置备份（名字记在索引里，要在删索引之前读）.
	$wpmind_backups = get_option( 'wpmind_custom_endpoints_backups', [] );
	foreach ( is_array( $wpmind_backups ) ? $wpmind_backups : [] as $wpmind_backup ) {
		if ( is_string( $wpmind_backup ) && 0 === strpos( $wpmind_backup, 'wpmind_custom_endpoints_backup_' ) ) {
			delete_option( $wpmind_backup );
		}
	}

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// 删除 transients（固定键名）
	$transients = [
		'wpmind_budget_alerts_sent',
		'wpmind_provider_health',
		'wpmind_failover_order',
		'wpmind_health_check_enabled',
		'wpmind_budget_notices',
		'wpmind_round_robin_index',
		'wpmind_daily_stats_lock',
		'wenpai_license_wpmind',
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
                OR option_name LIKE %s
                OR option_name LIKE %s
                OR option_name LIKE %s",
			'_transient_wpmind_cb_%',
			'_transient_timeout_wpmind_cb_%',
			'_transient_wpmind_provider_health_%',
			'_transient_timeout_wpmind_provider_health_%'
		)
	);
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpmind_cb_probe_%' OR option_name LIKE 'wpmind_cb_wlock_%'"
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

	// 删除动态模块设置、网关/GEO 设置和 llms.txt 缓存。
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s
                OR option_name LIKE %s
                OR option_name LIKE %s
                OR option_name LIKE %s
                OR option_name LIKE %s",
			'wpmind_module_%',
			'_transient_wpmind_llms_txt_%',
			'wpmind_gateway_%',
			'wpmind_geo_%',
			'wpmind_ai_%',
			'wpmind_brand_%'
		)
	);

	// Delete unified workflow task CPT posts.
	$task_ids = get_posts(
		[
			'post_type'      => 'wpmind_task',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);
	foreach ( $task_ids as $task_id ) {
		wp_delete_post( (int) $task_id, true );
	}

	// 删除 API Gateway 模块的数据库表
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_audit_log" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_key_usage" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpmind_api_keys" );
}

// 清理当前站点
wpmind_cleanup_site_data();

// 如果是多站点，清理所有站点的选项
// get_sites() 默认只返回 100 个（B15），这里分批取完所有子站。
if ( is_multisite() ) {
	$wpmind_batch  = 100;
	$wpmind_offset = 0;
	do {
		$wpmind_sites = get_sites(
			[
				'fields' => 'ids',
				'number' => $wpmind_batch,
				'offset' => $wpmind_offset,
			]
		);
		foreach ( $wpmind_sites as $wpmind_site_id ) {
			switch_to_blog( (int) $wpmind_site_id );
			wpmind_cleanup_site_data();
			restore_current_blog();
		}
		$wpmind_got     = count( $wpmind_sites );
		$wpmind_offset += $wpmind_batch;
	} while ( $wpmind_got === $wpmind_batch );
}
