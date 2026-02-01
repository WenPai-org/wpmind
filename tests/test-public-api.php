<?php
/**
 * WPMind 公共 API 测试脚本
 *
 * 使用方法：
 * 1. 通过 WP-CLI: wp eval-file tests/test-public-api.php
 * 2. 或者在主题 functions.php 中临时调用
 *
 * @package WPMind
 * @since 2.5.0
 */

// 确保在 WordPress 环境中运行
if (!defined('ABSPATH')) {
    echo "请在 WordPress 环境中运行此脚本\n";
    exit(1);
}

echo "\n";
echo "================================================\n";
echo "       WPMind 公共 API 测试\n";
echo "================================================\n\n";

// 测试 1: wpmind_is_available()
echo "【测试 1】wpmind_is_available()\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_is_available')) {
    $available = wpmind_is_available();
    echo "结果: " . ($available ? "✅ 可用" : "❌ 不可用") . "\n";
} else {
    echo "❌ 函数未定义\n";
}
echo "\n";

// 测试 2: wpmind_get_status()
echo "【测试 2】wpmind_get_status()\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_get_status')) {
    $status = wpmind_get_status();
    echo "可用状态: " . ($status['available'] ? "是" : "否") . "\n";
    echo "当前服务商: " . ($status['provider'] ?: "未设置") . "\n";
    echo "当前模型: " . ($status['model'] ?: "未设置") . "\n";
    echo "今日用量: " . $status['usage']['today'] . " tokens\n";
    echo "本月用量: " . $status['usage']['month'] . " tokens\n";
} else {
    echo "❌ 函数未定义\n";
}
echo "\n";

// 测试 3: wpmind_chat() - 简单模式
echo "【测试 3】wpmind_chat() - 简单模式\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_chat')) {
    echo "发送: \"你好，请用一句话介绍你自己\"\n";
    echo "请求中...\n";
    
    $start = microtime(true);
    $result = wpmind_chat('你好，请用一句话介绍你自己', [
        'context'    => 'test_simple',
        'max_tokens' => 100,
    ]);
    $duration = round((microtime(true) - $start) * 1000);
    
    if (is_wp_error($result)) {
        echo "❌ 错误: " . $result->get_error_message() . "\n";
    } else {
        echo "✅ 响应 ({$duration}ms):\n";
        echo "   内容: " . mb_substr($result['content'], 0, 100) . "...\n";
        echo "   服务商: " . $result['provider'] . "\n";
        echo "   模型: " . $result['model'] . "\n";
        echo "   Token: " . $result['usage']['total_tokens'] . "\n";
    }
} else {
    echo "❌ 函数未定义\n";
}
echo "\n";

// 测试 4: wpmind_chat() - 多轮对话
echo "【测试 4】wpmind_chat() - 多轮对话模式\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_chat')) {
    $messages = [
        ['role' => 'system', 'content' => '你是一个 WordPress 专家'],
        ['role' => 'user', 'content' => '什么是 Hook？用一句话回答'],
    ];
    
    echo "发送多轮对话...\n";
    
    $start = microtime(true);
    $result = wpmind_chat($messages, [
        'context'    => 'test_multiround',
        'max_tokens' => 100,
    ]);
    $duration = round((microtime(true) - $start) * 1000);
    
    if (is_wp_error($result)) {
        echo "❌ 错误: " . $result->get_error_message() . "\n";
    } else {
        echo "✅ 响应 ({$duration}ms):\n";
        echo "   内容: " . $result['content'] . "\n";
    }
} else {
    echo "❌ 函数未定义\n";
}
echo "\n";

// 测试 5: wpmind_translate()
echo "【测试 5】wpmind_translate()\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_translate')) {
    echo "翻译: \"WordPress 性能优化指南\" -> 英文\n";
    
    $start = microtime(true);
    $result = wpmind_translate('WordPress 性能优化指南', 'zh', 'en', [
        'context'   => 'test_translate',
        'cache_ttl' => 0, // 测试时不缓存
    ]);
    $duration = round((microtime(true) - $start) * 1000);
    
    if (is_wp_error($result)) {
        echo "❌ 错误: " . $result->get_error_message() . "\n";
    } else {
        echo "✅ 翻译结果 ({$duration}ms): " . $result . "\n";
    }
    
    // 测试 slug 格式
    echo "\n翻译为 Slug 格式...\n";
    $start = microtime(true);
    $result = wpmind_translate('WordPress 性能优化指南', 'zh', 'en', [
        'format'    => 'slug',
        'cache_ttl' => 0,
    ]);
    $duration = round((microtime(true) - $start) * 1000);
    
    if (is_wp_error($result)) {
        echo "❌ 错误: " . $result->get_error_message() . "\n";
    } else {
        echo "✅ Slug 结果 ({$duration}ms): " . $result . "\n";
    }
} else {
    echo "❌ 函数未定义\n";
}
echo "\n";

// 测试 6: 缓存功能
echo "【测试 6】缓存功能\n";
echo "─────────────────────────────────────────────────\n";

if (function_exists('wpmind_chat')) {
    $prompt = '1+1等于几？只回答数字';
    
    echo "第一次请求 (无缓存)...\n";
    $start = microtime(true);
    $result1 = wpmind_chat($prompt, [
        'context'   => 'test_cache',
        'cache_ttl' => 60, // 缓存 60 秒
    ]);
    $duration1 = round((microtime(true) - $start) * 1000);
    
    if (!is_wp_error($result1)) {
        echo "  耗时: {$duration1}ms\n";
        
        echo "第二次请求 (有缓存)...\n";
        $start = microtime(true);
        $result2 = wpmind_chat($prompt, [
            'context'   => 'test_cache',
            'cache_ttl' => 60,
        ]);
        $duration2 = round((microtime(true) - $start) * 1000);
        echo "  耗时: {$duration2}ms\n";
        
        if ($duration2 < $duration1 / 2) {
            echo "✅ 缓存生效 (第二次快于第一次)\n";
        } else {
            echo "⚠️ 缓存可能未生效\n";
        }
        
        // 清理测试缓存
        $cache_key = 'wpmind_chat_' . md5(serialize(['messages' => [['role' => 'user', 'content' => $prompt]], 'max_tokens' => 1000, 'temperature' => 0.7, 'json_mode' => false]));
        delete_transient($cache_key);
    } else {
        echo "❌ 错误: " . $result1->get_error_message() . "\n";
    }
}
echo "\n";

// 测试 7: Hooks
echo "【测试 7】Hooks 测试\n";
echo "─────────────────────────────────────────────────\n";

$hook_triggered = false;

// 注册测试 Hook
add_action('wpmind_before_request', function($type, $args, $context) use (&$hook_triggered) {
    $hook_triggered = true;
    echo "  ✅ wpmind_before_request 触发 (type: {$type}, context: {$context})\n";
}, 10, 3);

if (function_exists('wpmind_chat')) {
    wpmind_chat('测试 Hook', [
        'context'    => 'test_hooks',
        'max_tokens' => 10,
    ]);
    
    if (!$hook_triggered) {
        echo "  ⚠️ Hook 未触发\n";
    }
}
echo "\n";

echo "================================================\n";
echo "       测试完成\n";
echo "================================================\n\n";
