<?php
/**
 * WPMind 公共 API 测试 - AJAX 测试端点
 *
 * 通过访问 /wp-admin/admin-ajax.php?action=wpmind_test_api 触发测试
 *
 * @package WPMind
 * @since 2.5.0
 */

// 确保在 WordPress 环境中运行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册 AJAX 测试端点
 */
add_action('wp_ajax_wpmind_test_api', 'wpmind_run_api_test');
add_action('wp_ajax_nopriv_wpmind_test_api', 'wpmind_run_api_test'); // 仅开发环境

function wpmind_run_api_test() {
    // 安全检查：仅管理员可访问
    if (!current_user_can('manage_options') && !defined('WPMIND_DEV_MODE')) {
        wp_send_json_error(['message' => '权限不足']);
    }

    header('Content-Type: application/json; charset=utf-8');
    
    $results = [];

    // 测试 1: wpmind_is_available()
    $results['is_available'] = [
        'name' => 'wpmind_is_available()',
        'exists' => function_exists('wpmind_is_available'),
        'result' => function_exists('wpmind_is_available') ? wpmind_is_available() : null,
    ];

    // 测试 2: wpmind_get_status()
    $results['get_status'] = [
        'name' => 'wpmind_get_status()',
        'exists' => function_exists('wpmind_get_status'),
        'result' => function_exists('wpmind_get_status') ? wpmind_get_status() : null,
    ];

    // 测试 3: wpmind_chat() - 简单模式
    if (function_exists('wpmind_chat')) {
        $start = microtime(true);
        $chat_result = wpmind_chat('你好，请用一句话回答：1+1=?', [
            'context'    => 'api_test',
            'max_tokens' => 50,
        ]);
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['chat_simple'] = [
            'name' => 'wpmind_chat() - 简单模式',
            'exists' => true,
            'success' => !is_wp_error($chat_result),
            'duration_ms' => $duration,
            'result' => is_wp_error($chat_result) ? [
                'error' => $chat_result->get_error_message(),
            ] : $chat_result,
        ];
    } else {
        $results['chat_simple'] = [
            'name' => 'wpmind_chat() - 简单模式',
            'exists' => false,
        ];
    }

    // 测试 4: wpmind_translate()
    if (function_exists('wpmind_translate')) {
        $start = microtime(true);
        $translate_result = wpmind_translate('你好世界', 'zh', 'en', [
            'context'   => 'api_test',
            'cache_ttl' => 0,
        ]);
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['translate'] = [
            'name' => 'wpmind_translate()',
            'exists' => true,
            'success' => !is_wp_error($translate_result),
            'duration_ms' => $duration,
            'result' => is_wp_error($translate_result) ? [
                'error' => $translate_result->get_error_message(),
            ] : $translate_result,
        ];
    } else {
        $results['translate'] = [
            'name' => 'wpmind_translate()',
            'exists' => false,
        ];
    }

    wp_send_json_success([
        'message' => 'WPMind API 测试完成',
        'tests' => $results,
    ]);
}
