#!/usr/bin/env php
<?php
/**
 * WPMind 集成测试脚本
 *
 * 用于部署后验证所有模板依赖的方法是否存在
 *
 * 使用方法:
 *   php tests/test-integration.php
 *   或部署后: php /www/wwwroot/wpcy.com/wp-content/plugins/wpmind/tests/test-integration.php
 *
 * @package WPMind
 * @since 3.2.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 自动检测插件路径
$plugin_path = dirname(__DIR__) . '/';
if (!file_exists($plugin_path . 'wpmind.php')) {
    $plugin_path = '/www/wwwroot/wpcy.com/wp-content/plugins/wpmind/';
}

echo "=== WPMind 集成测试 ===\n";
echo "插件路径: $plugin_path\n\n";

$errors = [];
$warnings = [];

/**
 * 检查文件中是否存在指定方法
 */
function check_method(string $file, string $method): bool {
    if (!file_exists($file)) {
        return false;
    }
    $content = file_get_contents($file);
    return (bool) preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $content);
}

/**
 * 从文件中提取调用的静态方法
 */
function extract_static_calls(string $file, string $class): array {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    preg_match_all('/' . preg_quote($class, '/') . '::([a-zA-Z_]+)\(/', $content, $matches);
    return array_unique($matches[1] ?? []);
}

// 1. 文件存在性检查
echo "--- 文件存在性检查 ---\n";
$required_files = [
    'wpmind.php',
    'modules/cost-control/CostControlModule.php',
    'modules/cost-control/includes/UsageTracker.php',
    'modules/cost-control/includes/BudgetManager.php',
    'modules/cost-control/includes/BudgetChecker.php',
    'modules/cost-control/includes/BudgetAlert.php',
    'modules/cost-control/templates/settings.php',
    'modules/geo/GeoModule.php',
    'modules/geo/includes/CrawlerTracker.php',
    'modules/geo/templates/settings.php',
    'includes/Usage/UsageTracker.php',
    'includes/Usage/UsageTrackerFallback.php',
    'includes/Budget/BudgetManager.php',
    'includes/Budget/BudgetManagerFallback.php',
    'templates/tabs/dashboard.php',
    'templates/tabs/budget.php',
    'templates/settings-page.php',
];

foreach ($required_files as $file) {
    $full_path = $plugin_path . $file;
    if (file_exists($full_path)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file (不存在)\n";
        $errors[] = "文件不存在: $file";
    }
}

// 2. 语法检查
echo "\n--- PHP 语法检查 ---\n";
$php_files = glob($plugin_path . '{*.php,**/*.php,**/**/*.php}', GLOB_BRACE);
$syntax_errors = 0;
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l '$file' 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        $relative = str_replace($plugin_path, '', $file);
        echo "❌ $relative: " . implode("\n", $output) . "\n";
        $errors[] = "语法错误: $relative";
        $syntax_errors++;
    }
}
if ($syntax_errors === 0) {
    echo "✅ 所有 PHP 文件语法正确\n";
}

// 3. dashboard.php 依赖检查
echo "\n--- dashboard.php 依赖检查 ---\n";
$tracker_file = $plugin_path . 'modules/cost-control/includes/UsageTracker.php';
$dashboard_methods = extract_static_calls($plugin_path . 'templates/tabs/dashboard.php', 'UsageTracker');

foreach ($dashboard_methods as $method) {
    if (check_method($tracker_file, $method)) {
        echo "✅ UsageTracker::$method()\n";
    } else {
        echo "❌ UsageTracker::$method() 缺失\n";
        $errors[] = "方法缺失: UsageTracker::$method()";
    }
}

// 4. budget.php 依赖检查
echo "\n--- budget.php 依赖检查 ---\n";
$manager_file = $plugin_path . 'modules/cost-control/includes/BudgetManager.php';
$checker_file = $plugin_path . 'modules/cost-control/includes/BudgetChecker.php';

if (check_method($manager_file, 'get_settings')) {
    echo "✅ BudgetManager::get_settings()\n";
} else {
    echo "❌ BudgetManager::get_settings() 缺失\n";
    $errors[] = "方法缺失: BudgetManager::get_settings()";
}

if (check_method($checker_file, 'get_summary')) {
    echo "✅ BudgetChecker::get_summary()\n";
} else {
    echo "❌ BudgetChecker::get_summary() 缺失\n";
    $errors[] = "方法缺失: BudgetChecker::get_summary()";
}

// 5. Cost Control 模块设置页面依赖检查
echo "\n--- cost-control/templates/settings.php 依赖检查 ---\n";
$cc_settings = $plugin_path . 'modules/cost-control/templates/settings.php';
if (file_exists($cc_settings)) {
    $cc_tracker_methods = extract_static_calls($cc_settings, 'UsageTracker');
    foreach ($cc_tracker_methods as $method) {
        if (check_method($tracker_file, $method)) {
            echo "✅ UsageTracker::$method()\n";
        } else {
            echo "❌ UsageTracker::$method() 缺失\n";
            $errors[] = "方法缺失: UsageTracker::$method()";
        }
    }

    $cc_manager_methods = extract_static_calls($cc_settings, 'BudgetManager');
    foreach ($cc_manager_methods as $method) {
        if (check_method($manager_file, $method)) {
            echo "✅ BudgetManager::$method()\n";
        } else {
            echo "❌ BudgetManager::$method() 缺失\n";
            $errors[] = "方法缺失: BudgetManager::$method()";
        }
    }
}

// 6. GEO 模块设置页面依赖检查
echo "\n--- geo/templates/settings.php 依赖检查 ---\n";
$geo_settings = $plugin_path . 'modules/geo/templates/settings.php';
$crawler_file = $plugin_path . 'modules/geo/includes/CrawlerTracker.php';
if (file_exists($geo_settings) && file_exists($crawler_file)) {
    echo "✅ GEO 模块文件完整\n";
}

// 7. Fallback 完整性检查
echo "\n--- Fallback 完整性检查 ---\n";
$fallback_tracker = $plugin_path . 'includes/Usage/UsageTrackerFallback.php';
$fallback_manager = $plugin_path . 'includes/Budget/BudgetManagerFallback.php';

foreach ($dashboard_methods as $method) {
    if (!check_method($fallback_tracker, $method)) {
        echo "⚠️ UsageTrackerFallback::$method() 缺失\n";
        $warnings[] = "Fallback 方法缺失: UsageTrackerFallback::$method()";
    }
}

// 结果汇总
echo "\n=== 测试结果汇总 ===\n";
if (empty($errors)) {
    echo "✅ 所有测试通过\n";
    exit(0);
} else {
    echo "❌ 发现 " . count($errors) . " 个错误:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    if (!empty($warnings)) {
        echo "\n⚠️ 发现 " . count($warnings) . " 个警告:\n";
        foreach ($warnings as $warning) {
            echo "  - $warning\n";
        }
    }
    exit(1);
}
