#!/usr/bin/env php
<?php
/**
 * API Gateway 集成测试脚本
 *
 * 验证 API Gateway 模块的文件完整性、语法、命名空间、接口实现和安全性。
 *
 * Usage: php tests/test-api-gateway.php
 *
 * @package WPMind
 * @since 3.6.0
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 自动检测插件路径
$plugin_path = dirname(__DIR__) . '/';
if (!file_exists($plugin_path . 'wpmind.php')) {
	$plugin_path = '/www/wwwroot/wpcy.com/wp-content/plugins/wpmind/';
}

$module_path = $plugin_path . 'modules/api-gateway/';

echo "=== API Gateway 集成测试 ===\n";
echo "模块路径: $module_path\n\n";

$errors   = [];
$warnings = [];

// ---------------------------------------------------------------------------
// 1. 文件存在性检查
// ---------------------------------------------------------------------------
echo "--- 1. 文件存在性检查 ---\n";

$required_files = [
	'ApiGatewayModule.php',
	'module.json',
	'includes/Admin/GatewayAjaxController.php',
	'includes/Auth/ApiKeyAuthResult.php',
	'includes/Auth/ApiKeyHasher.php',
	'includes/Auth/ApiKeyManager.php',
	'includes/Auth/ApiKeyRepository.php',
	'includes/Error/ErrorMapper.php',
	'includes/Pipeline/AuthMiddleware.php',
	'includes/Pipeline/BudgetMiddleware.php',
	'includes/Pipeline/ErrorMiddleware.php',
	'includes/Pipeline/GatewayPipeline.php',
	'includes/Pipeline/GatewayRequestContext.php',
	'includes/Pipeline/GatewayStageInterface.php',
	'includes/Pipeline/LogMiddleware.php',
	'includes/Pipeline/QuotaMiddleware.php',
	'includes/Pipeline/RequestTransformMiddleware.php',
	'includes/Pipeline/ResponseTransformMiddleware.php',
	'includes/Pipeline/RouteMiddleware.php',
	'includes/RateLimit/RateLimiter.php',
	'includes/RateLimit/RateStoreInterface.php',
	'includes/RateLimit/RateStoreResult.php',
	'includes/RateLimit/RedisRateStore.php',
	'includes/RateLimit/TransientRateStore.php',
	'includes/Stream/CancellationToken.php',
	'includes/Stream/SseConcurrencyGuard.php',
	'includes/Stream/SseSlot.php',
	'includes/Stream/SseStreamController.php',
	'includes/Stream/StreamResult.php',
	'includes/Stream/UpstreamStreamClient.php',
	'includes/Transform/ModelMapper.php',
	'includes/Transform/RequestTransformer.php',
	'includes/Transform/ResponseTransformer.php',
	'includes/GatewayRequestSchema.php',
	'includes/RestController.php',
	'includes/SchemaManager.php',
	'templates/settings.php',
];

$file_count = count($required_files);
$found      = 0;

foreach ($required_files as $file) {
	$full_path = $module_path . $file;
	if (file_exists($full_path)) {
		echo "  ✅ $file\n";
		$found++;
	} else {
		echo "  ❌ $file (不存在)\n";
		$errors[] = "文件不存在: $file";
	}
}

echo "  文件统计: $found / $file_count\n";

// ---------------------------------------------------------------------------
// 2. PHP 语法检查
// ---------------------------------------------------------------------------
echo "\n--- 2. PHP 语法检查 ---\n";

$php_files     = [];
$iterator      = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($module_path, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file_info) {
	if ($file_info->getExtension() === 'php') {
		$php_files[] = $file_info->getPathname();
	}
}

sort($php_files);
$syntax_errors = 0;

foreach ($php_files as $file) {
	$output     = [];
	$return_var = 0;
	exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
	if ($return_var !== 0) {
		$relative = str_replace($module_path, '', $file);
		echo "  ❌ $relative: " . implode("\n", $output) . "\n";
		$errors[] = "语法错误: $relative";
		$syntax_errors++;
	}
}

if ($syntax_errors === 0) {
	echo "  ✅ 所有 " . count($php_files) . " 个 PHP 文件语法正确\n";
}

// ---------------------------------------------------------------------------
// 3. 命名空间一致性检查
// ---------------------------------------------------------------------------
echo "\n--- 3. 命名空间一致性检查 ---\n";

$namespace_errors = 0;

foreach ($php_files as $file) {
	$relative = str_replace($module_path, '', $file);

	// 跳过 templates/ 目录
	if (str_starts_with($relative, 'templates/')) {
		continue;
	}

	$content = file_get_contents($file);
	if (preg_match('/^namespace\s+(.+?);/m', $content, $matches)) {
		$ns = $matches[1];
		if (!str_starts_with($ns, 'WPMind\\Modules\\ApiGateway')) {
			echo "  ❌ $relative: 命名空间 '$ns' 不以 WPMind\\Modules\\ApiGateway 开头\n";
			$errors[] = "命名空间错误: $relative ($ns)";
			$namespace_errors++;
		}
	} else {
		echo "  ❌ $relative: 未声明命名空间\n";
		$errors[] = "缺少命名空间: $relative";
		$namespace_errors++;
	}
}

if ($namespace_errors === 0) {
	echo "  ✅ 所有非模板 PHP 文件命名空间正确\n";
}

// ---------------------------------------------------------------------------
// 4. 接口实现检查
// ---------------------------------------------------------------------------
echo "\n--- 4. 接口实现检查 ---\n";

/**
 * 检查文件内容是否包含指定字符串
 */
function file_contains(string $path, string $needle): bool {
	if (!file_exists($path)) {
		return false;
	}
	return str_contains(file_get_contents($path), $needle);
}

// ApiGatewayModule implements ModuleInterface
$module_file = $module_path . 'ApiGatewayModule.php';
if (file_contains($module_file, 'implements ModuleInterface')) {
	echo "  ✅ ApiGatewayModule implements ModuleInterface\n";
} else {
	echo "  ❌ ApiGatewayModule 未实现 ModuleInterface\n";
	$errors[] = "ApiGatewayModule 未实现 ModuleInterface";
}

// Pipeline middleware files implement GatewayStageInterface
$middleware_files = [
	'AuthMiddleware',
	'BudgetMiddleware',
	'ErrorMiddleware',
	'LogMiddleware',
	'QuotaMiddleware',
	'RequestTransformMiddleware',
	'ResponseTransformMiddleware',
	'RouteMiddleware',
];

foreach ($middleware_files as $mw) {
	$mw_path = $module_path . "includes/Pipeline/$mw.php";
	if (file_contains($mw_path, 'implements GatewayStageInterface')) {
		echo "  ✅ $mw implements GatewayStageInterface\n";
	} else {
		echo "  ❌ $mw 未实现 GatewayStageInterface\n";
		$errors[] = "$mw 未实现 GatewayStageInterface";
	}
}

// RateStoreInterface is declared as interface
$rsi_path = $module_path . 'includes/RateLimit/RateStoreInterface.php';
if (file_contains($rsi_path, 'interface RateStoreInterface')) {
	echo "  ✅ RateStoreInterface 声明为 interface\n";
} else {
	echo "  ❌ RateStoreInterface 未声明为 interface\n";
	$errors[] = "RateStoreInterface 未声明为 interface";
}

// RedisRateStore and TransientRateStore implement RateStoreInterface
foreach (['RedisRateStore', 'TransientRateStore'] as $store) {
	$store_path = $module_path . "includes/RateLimit/$store.php";
	if (file_contains($store_path, 'implements RateStoreInterface')) {
		echo "  ✅ $store implements RateStoreInterface\n";
	} else {
		echo "  ❌ $store 未实现 RateStoreInterface\n";
		$errors[] = "$store 未实现 RateStoreInterface";
	}
}

// ---------------------------------------------------------------------------
// 5. 安全检查
// ---------------------------------------------------------------------------
echo "\n--- 5. 安全检查 ---\n";

$ajax_file   = $module_path . 'includes/Admin/GatewayAjaxController.php';
$hasher_file = $module_path . 'includes/Auth/ApiKeyHasher.php';

// check_ajax_referer
if (file_contains($ajax_file, 'check_ajax_referer')) {
	echo "  ✅ GatewayAjaxController 包含 check_ajax_referer (CSRF 防护)\n";
} else {
	echo "  ❌ GatewayAjaxController 缺少 check_ajax_referer\n";
	$errors[] = "安全: GatewayAjaxController 缺少 CSRF 防护";
}

// current_user_can
if (file_contains($ajax_file, 'current_user_can')) {
	echo "  ✅ GatewayAjaxController 包含 current_user_can (权限检查)\n";
} else {
	echo "  ❌ GatewayAjaxController 缺少 current_user_can\n";
	$errors[] = "安全: GatewayAjaxController 缺少权限检查";
}

// hash_equals (constant-time comparison)
if (file_contains($hasher_file, 'hash_equals')) {
	echo "  ✅ ApiKeyHasher 包含 hash_equals (常量时间比较)\n";
} else {
	echo "  ❌ ApiKeyHasher 缺少 hash_equals\n";
	$errors[] = "安全: ApiKeyHasher 缺少常量时间比较";
}

// ---------------------------------------------------------------------------
// 6. REST 端点注册检查
// ---------------------------------------------------------------------------
echo "\n--- 6. REST 端点注册检查 ---\n";

$rest_file    = $module_path . 'includes/RestController.php';
$rest_content = file_exists($rest_file) ? file_get_contents($rest_file) : '';

$expected_routes = [
	'chat/completions' => "'/chat/completions'",
	'embeddings'       => "'/embeddings'",
	'responses'        => "'/responses'",
	'models'           => "'/models'",
	'status'           => "'/status'",
];

foreach ($expected_routes as $label => $pattern) {
	if (str_contains($rest_content, $pattern)) {
		echo "  ✅ 路由已注册: $label\n";
	} else {
		echo "  ❌ 路由未注册: $label\n";
		$errors[] = "REST 路由缺失: $label";
	}
}

// ---------------------------------------------------------------------------
// 7. module.json 检查
// ---------------------------------------------------------------------------
echo "\n--- 7. module.json 检查 ---\n";

$json_path = $module_path . 'module.json';

if (!file_exists($json_path)) {
	echo "  ❌ module.json 不存在\n";
	$errors[] = "module.json 不存在";
} else {
	$json_raw  = file_get_contents($json_path);
	$json_data = json_decode($json_raw, true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "  ❌ module.json 不是有效 JSON: " . json_last_error_msg() . "\n";
		$errors[] = "module.json 解析失败: " . json_last_error_msg();
	} else {
		echo "  ✅ module.json 是有效 JSON\n";

		$required_keys = ['id', 'name', 'version'];
		foreach ($required_keys as $key) {
			if (isset($json_data[$key])) {
				echo "  ✅ 包含必需字段: $key = " . $json_data[$key] . "\n";
			} else {
				echo "  ❌ 缺少必需字段: $key\n";
				$errors[] = "module.json 缺少字段: $key";
			}
		}
	}
}

// ---------------------------------------------------------------------------
// 8. require_once 完整性检查
// ---------------------------------------------------------------------------
echo "\n--- 8. require_once 完整性检查 ---\n";

$module_content = file_exists($module_file) ? file_get_contents($module_file) : '';

preg_match_all(
	"/require_once\s+__DIR__\s*\.\s*'([^']+)'/",
	$module_content,
	$req_matches
);

$require_files  = $req_matches[1] ?? [];
$require_errors = 0;

if (empty($require_files)) {
	echo "  ⚠️ 未找到 require_once 语句\n";
	$warnings[] = "ApiGatewayModule.php 中未找到 require_once";
} else {
	foreach ($require_files as $req_file) {
		// $req_file 以 / 开头，如 /includes/SchemaManager.php
		$full = $module_path . ltrim($req_file, '/');
		if (file_exists($full)) {
			echo "  ✅ $req_file\n";
		} else {
			echo "  ❌ $req_file (文件不存在)\n";
			$errors[] = "require_once 引用的文件不存在: $req_file";
			$require_errors++;
		}
	}

	if ($require_errors === 0) {
		echo "  require_once 统计: " . count($require_files) . " 个文件全部存在\n";
	}
}

// ---------------------------------------------------------------------------
// 结果汇总
// ---------------------------------------------------------------------------
echo "\n=== 测试结果汇总 ===\n";

if (empty($errors) && empty($warnings)) {
	echo "✅ 所有测试通过\n";
	exit(0);
}

if (!empty($errors)) {
	echo "❌ 发现 " . count($errors) . " 个错误:\n";
	foreach ($errors as $error) {
		echo "  - $error\n";
	}
}

if (!empty($warnings)) {
	echo "⚠️ 发现 " . count($warnings) . " 个警告:\n";
	foreach ($warnings as $warning) {
		echo "  - $warning\n";
	}
}

exit(empty($errors) ? 0 : 1);
