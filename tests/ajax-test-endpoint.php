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
 *
 * 安全说明：
 * - wp_ajax_ 端点仅限已登录用户
 * - 需要管理员权限和 nonce 验证
 */
add_action('wp_ajax_wpmind_test_api', 'wpmind_run_api_test');

function wpmind_run_api_test() {
    // Nonce 验证
    check_ajax_referer('wpmind_ajax', 'nonce');

    // 权限检查：必须是管理员
    if (!current_user_can('manage_options')) {
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

    // ============================================
    // v2.6.0 增强 API 测试
    // ============================================

    // 测试 5: wpmind_count_tokens()
    if (function_exists('wpmind_count_tokens')) {
        $test_text = '这是一段测试文本，用于测试 token 计数功能。This is a test.';
        $tokens = wpmind_count_tokens($test_text);
        
        $results['count_tokens'] = [
            'name' => 'wpmind_count_tokens()',
            'exists' => true,
            'success' => $tokens > 0,
            'result' => [
                'text_length' => mb_strlen($test_text),
                'estimated_tokens' => $tokens,
            ],
        ];
    }

    // 测试 6: wpmind_structured()
    if (function_exists('wpmind_structured')) {
        $start = microtime(true);
        $structured_result = wpmind_structured('北京是中国首都，人口超过2000万', [
            'type' => 'object',
            'required' => ['city', 'country'],
            'properties' => [
                'city' => ['type' => 'string'],
                'country' => ['type' => 'string'],
                'population' => ['type' => 'string'],
            ],
        ], [
            'retries' => 2,
        ]);
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['structured'] = [
            'name' => 'wpmind_structured()',
            'exists' => true,
            'success' => !is_wp_error($structured_result),
            'duration_ms' => $duration,
            'result' => is_wp_error($structured_result) ? [
                'error' => $structured_result->get_error_message(),
            ] : $structured_result,
        ];
    }

    // 测试 7: wpmind_batch() - 只测试 2 个项目
    if (function_exists('wpmind_batch')) {
        $start = microtime(true);
        $batch_result = wpmind_batch(
            ['苹果', '香蕉'],
            '用英文回答：{{item}} 的英文是什么？只回答单词。',
            [
                'max_tokens' => 20,
                'delay_ms' => 50,
            ]
        );
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['batch'] = [
            'name' => 'wpmind_batch()',
            'exists' => true,
            'success' => !is_wp_error($batch_result) && ($batch_result['success_count'] ?? 0) > 0,
            'duration_ms' => $duration,
            'result' => is_wp_error($batch_result) ? [
                'error' => $batch_result->get_error_message(),
            ] : [
                'success_count' => $batch_result['success_count'],
                'error_count' => $batch_result['error_count'],
                'total_tokens' => $batch_result['total_tokens'],
            ],
        ];
    }

    // ============================================
    // v2.7.0 专用 API 测试
    // ============================================

    // 测试 8: wpmind_summarize()
    if (function_exists('wpmind_summarize')) {
        $start = microtime(true);
        $long_text = '人工智能（Artificial Intelligence，简称AI）是计算机科学的一个分支，它企图了解智能的实质，并生产出一种新的能以人类智能相似的方式做出反应的智能机器。人工智能从诞生以来，理论和技术日益成熟，应用领域也在不断扩大。';
        
        $summarize_result = wpmind_summarize($long_text, [
            'style' => 'title',
            'cache_ttl' => 0,
        ]);
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['summarize'] = [
            'name' => 'wpmind_summarize()',
            'exists' => true,
            'success' => !is_wp_error($summarize_result),
            'duration_ms' => $duration,
            'result' => is_wp_error($summarize_result) ? [
                'error' => $summarize_result->get_error_message(),
            ] : $summarize_result,
        ];
    }

    // 测试 9: wpmind_moderate()
    if (function_exists('wpmind_moderate')) {
        $start = microtime(true);
        $moderate_result = wpmind_moderate('这是一段正常的文本内容，用于测试内容审核功能。', [
            'categories' => ['spam', 'adult'],
            'cache_ttl' => 0,
        ]);
        $duration = round((microtime(true) - $start) * 1000);
        
        $results['moderate'] = [
            'name' => 'wpmind_moderate()',
            'exists' => true,
            'success' => !is_wp_error($moderate_result),
            'duration_ms' => $duration,
            'result' => is_wp_error($moderate_result) ? [
                'error' => $moderate_result->get_error_message(),
            ] : [
                'safe' => $moderate_result['safe'],
                'summary' => $moderate_result['summary'] ?? '',
            ],
        ];
    }

    wp_send_json_success([
        'message' => 'WPMind API 测试完成 (v2.5.0 + v2.6.0 + v2.7.0)',
        'version' => '2.7.0',
        'tests' => $results,
    ]);
}
