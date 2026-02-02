<?php
/**
 * WPMind API 集成测试
 *
 * 在 WordPress CLI 中运行: wp eval-file tests/integration-test.php
 *
 * @package WPMind
 * @since 2.5.0
 */

// 确保在 WordPress 环境中运行
if (!defined('ABSPATH')) {
    echo "请在 WordPress 环境中运行此测试\n";
    echo "使用: wp eval-file tests/integration-test.php\n";
    exit(1);
}

// 测试结果统计
$tests_passed = 0;
$tests_failed = 0;
$test_results = [];

/**
 * 断言函数
 */
function assert_true($condition, $test_name) {
    global $tests_passed, $tests_failed, $test_results;
    
    if ($condition) {
        $tests_passed++;
        $test_results[] = ['name' => $test_name, 'status' => 'PASS', 'message' => ''];
        echo "✅ $test_name\n";
        return true;
    } else {
        $tests_failed++;
        $test_results[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Condition is false'];
        echo "❌ $test_name\n";
        return false;
    }
}

function assert_equals($expected, $actual, $test_name) {
    global $tests_passed, $tests_failed, $test_results;
    
    if ($expected === $actual) {
        $tests_passed++;
        $test_results[] = ['name' => $test_name, 'status' => 'PASS', 'message' => ''];
        echo "✅ $test_name\n";
        return true;
    } else {
        $tests_failed++;
        $message = "Expected: " . print_r($expected, true) . ", Got: " . print_r($actual, true);
        $test_results[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $message];
        echo "❌ $test_name\n";
        echo "   Expected: " . print_r($expected, true) . "\n";
        echo "   Got: " . print_r($actual, true) . "\n";
        return false;
    }
}

function assert_is_wp_error($value, $test_name) {
    global $tests_passed, $tests_failed, $test_results;
    
    if (is_wp_error($value)) {
        $tests_passed++;
        $test_results[] = ['name' => $test_name, 'status' => 'PASS', 'message' => ''];
        echo "✅ $test_name\n";
        return true;
    } else {
        $tests_failed++;
        $test_results[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Expected WP_Error'];
        echo "❌ $test_name\n";
        return false;
    }
}

function assert_not_wp_error($value, $test_name) {
    global $tests_passed, $tests_failed, $test_results;
    
    if (!is_wp_error($value)) {
        $tests_passed++;
        $test_results[] = ['name' => $test_name, 'status' => 'PASS', 'message' => ''];
        echo "✅ $test_name\n";
        return true;
    } else {
        $tests_failed++;
        $message = 'Got WP_Error: ' . $value->get_error_message();
        $test_results[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $message];
        echo "❌ $test_name: " . $value->get_error_message() . "\n";
        return false;
    }
}

/**
 * 测试套件
 */

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  WPMind API 集成测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ==================
// 1. 基础可用性测试
// ==================
echo "1. 基础可用性测试\n";
echo "─────────────────────────────────────\n";

assert_true(function_exists('wpmind_is_available'), '函数 wpmind_is_available 存在');
assert_true(function_exists('wpmind_chat'), '函数 wpmind_chat 存在');
assert_true(function_exists('wpmind_translate'), '函数 wpmind_translate 存在');
assert_true(function_exists('wpmind_pinyin'), '函数 wpmind_pinyin 存在');
assert_true(function_exists('wpmind_get_status'), '函数 wpmind_get_status 存在');

$is_available = wpmind_is_available();
assert_true(is_bool($is_available), 'wpmind_is_available 返回布尔值');

echo "\n";

// ==================
// 2. ErrorHandler 测试
// ==================
echo "2. ErrorHandler 测试\n";
echo "─────────────────────────────────────\n";

use WPMind\API\ErrorHandler;

// 测试错误创建
$error = ErrorHandler::create(ErrorHandler::ERROR_NOT_AVAILABLE);
assert_is_wp_error($error, 'ErrorHandler::create 返回 WP_Error');
assert_equals('wpmind_not_available', $error->get_error_code(), '错误代码正确');

// 测试快捷方法
$error = ErrorHandler::not_available();
assert_equals('wpmind_not_available', $error->get_error_code(), 'not_available 错误代码正确');

$error = ErrorHandler::recursive_call('chat', 'test123');
assert_equals('wpmind_recursive_call', $error->get_error_code(), 'recursive_call 错误代码正确');

$error = ErrorHandler::call_depth_exceeded('chat', 3, 3);
assert_equals('wpmind_call_depth_exceeded', $error->get_error_code(), 'call_depth_exceeded 错误代码正确');

// 测试可重试检查
$timeout_error = ErrorHandler::create(ErrorHandler::ERROR_API_TIMEOUT);
assert_true(ErrorHandler::is_retryable($timeout_error), '超时错误应该可重试');

$auth_error = ErrorHandler::create(ErrorHandler::ERROR_API_AUTH);
assert_true(!ErrorHandler::is_retryable($auth_error), '认证错误不应该可重试');

echo "\n";

// ==================
// 3. 循环调用保护测试
// ==================
echo "3. 循环调用保护测试\n";
echo "─────────────────────────────────────\n";

// 如果 WPMind 可用，进行 API 测试
if (wpmind_is_available()) {
    // 正常调用应该成功
    $result = wpmind_chat('Say "test passed"', [
        'max_tokens' => 10,
        'cache_ttl' => 0,
    ]);
    assert_not_wp_error($result, '正常 chat 调用成功');
    
    // 模拟循环调用场景（通过 filter）
    $recursive_test_passed = true;
    add_filter('wpmind_chat_response', function($response, $messages, $context) use (&$recursive_test_passed) {
        if ($context === 'recursive_test') {
            // 在响应过滤器中再次调用 chat（应该被保护）
            $nested_result = wpmind_chat('Nested call', [
                'context' => 'recursive_test', // 相同 context
                'max_tokens' => 10,
            ]);
            
            // 如果返回 WP_Error 且是循环调用错误，测试通过
            if (!is_wp_error($nested_result)) {
                $recursive_test_passed = false;
            }
        }
        return $response;
    }, 10, 3);
    
    // 触发可能的循环调用
    $result = wpmind_chat('Test recursive protection', [
        'context' => 'recursive_test',
        'max_tokens' => 10,
        'cache_ttl' => 0,
    ]);
    
    echo "   (循环保护测试完成)\n";
} else {
    echo "   ⚠️ WPMind 未配置，跳过 API 调用测试\n";
}

echo "\n";

// ==================
// 4. 翻译 API 测试
// ==================
echo "4. 翻译 API 测试\n";
echo "─────────────────────────────────────\n";

if (wpmind_is_available()) {
    // 测试基本翻译
    $result = wpmind_translate('你好', 'zh', 'en', ['cache_ttl' => 0]);
    assert_not_wp_error($result, 'translate 调用成功');
    
    if (!is_wp_error($result)) {
        assert_true(is_string($result), 'translate 返回字符串');
        assert_true(strlen($result) > 0, 'translate 结果不为空');
        echo "   翻译结果: $result\n";
    }
    
    // 测试 slug 格式
    $result = wpmind_translate('你好世界', 'zh', 'en', [
        'format' => 'slug',
        'cache_ttl' => 0,
    ]);
    assert_not_wp_error($result, 'translate(format=slug) 调用成功');
    
    if (!is_wp_error($result)) {
        // slug 应该只包含小写字母、数字和连字符
        assert_true(preg_match('/^[a-z0-9\-]+$/', $result) === 1, 'Slug 格式正确');
        echo "   Slug 结果: $result\n";
    }
} else {
    echo "   ⚠️ WPMind 未配置，跳过翻译测试\n";
}

echo "\n";

// ==================
// 5. 语义化拼音测试
// ==================
echo "5. 语义化拼音测试\n";
echo "─────────────────────────────────────\n";

if (wpmind_is_available()) {
    $result = wpmind_pinyin('你好世界', ['cache_ttl' => 0]);
    assert_not_wp_error($result, 'pinyin 调用成功');
    
    if (!is_wp_error($result)) {
        assert_true(is_string($result), 'pinyin 返回字符串');
        assert_true(strlen($result) > 0, 'pinyin 结果不为空');
        echo "   拼音结果: $result\n";
        
        // 检查是否按词分隔（不应该是 ni-hao-shi-jie 这样按字分隔）
        $words = explode('-', $result);
        if (count($words) < 4) {
            echo "   ✓ 按词分隔（$result），不是按字分隔\n";
        }
    }
} else {
    echo "   ⚠️ WPMind 未配置，跳过拼音测试\n";
}

echo "\n";

// ==================
// 6. 缓存测试
// ==================
echo "6. 缓存测试\n";
echo "─────────────────────────────────────\n";

if (wpmind_is_available()) {
    $test_text = '缓存测试' . time(); // 唯一文本避免命中现有缓存
    
    // 第一次调用（无缓存）
    $start = microtime(true);
    $result1 = wpmind_translate($test_text, 'zh', 'en', ['cache_ttl' => 3600]);
    $time1 = round((microtime(true) - $start) * 1000);
    
    // 第二次调用（应该命中缓存）
    $start = microtime(true);
    $result2 = wpmind_translate($test_text, 'zh', 'en', ['cache_ttl' => 3600]);
    $time2 = round((microtime(true) - $start) * 1000);
    
    echo "   第一次调用: {$time1}ms\n";
    echo "   第二次调用: {$time2}ms\n";
    
    assert_equals($result1, $result2, '缓存结果一致');
    
    // 缓存应该显著快于首次调用
    if ($time1 > 100 && $time2 < $time1 / 2) {
        echo "   ✓ 缓存生效（第二次调用快 " . round(($time1 - $time2) / $time1 * 100) . "%）\n";
    }
    
    // 清理测试缓存
    delete_transient('wpmind_translate_' . md5(serialize([
        'text' => $test_text,
        'from' => 'zh',
        'to' => 'en',
        'options' => ['context' => 'translation', 'format' => 'text', 'hint' => '', 'cache_ttl' => 3600]
    ])));
} else {
    echo "   ⚠️ WPMind 未配置，跳过缓存测试\n";
}

echo "\n";

// ==================
// 测试总结
// ==================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  测试结果\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  通过: $tests_passed\n";
echo "  失败: $tests_failed\n";
echo "  总计: " . ($tests_passed + $tests_failed) . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 返回退出码
exit($tests_failed > 0 ? 1 : 0);
