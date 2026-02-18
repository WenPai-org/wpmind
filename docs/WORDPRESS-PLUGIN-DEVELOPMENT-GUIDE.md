# WordPress 插件开发指导手册

> 基于 WPMind v1.0→v3.6.0 开发实践总结，适用于中大型 WordPress 插件项目。

**文档版本**: 1.1.0
**最后更新**: 2026-02-07
**适用范围**: WordPress 插件/主题开发，AI 辅助协同开发

---

## 目录

1. [项目准备阶段](#1-项目准备阶段)
2. [编码规范](#2-编码规范)
3. [架构设计](#3-架构设计)
4. [开发实施流程](#4-开发实施流程)
5. [AI 协同开发模式（可选）](#5-ai-协同开发模式可选)
6. [质量控制体系](#6-质量控制体系)
7. [版本管理与发布](#7-版本管理与发布)
8. [部署与验证](#8-部署与验证)
9. [文档体系](#9-文档体系)
10. [常见陷阱与教训](#10-常见陷阱与教训)

---

## 1. 项目准备阶段

### 1.1 需求分析与文档先行

在写任何代码之前，先建立文档框架：

```
project/
├── plugin-name.php        # 主文件（含完整插件头注释）
├── readme.txt             # WP.org 标准格式（必须）
├── LICENSE                # 开源许可证（GPL-2.0-or-later）
├── CLAUDE.md              # 项目主索引（AI 入口点，可选）
├── CHANGELOG.md           # 版本更新日志（从 v1.0.0 开始）
├── CODING-STANDARDS.md    # 编码规范（团队共识）
├── languages/             # 翻译文件（.pot/.po/.mo）
├── docs/
│   ├── ROADMAP.md         # 产品路线图（阶段划分）
│   └── ARCHITECTURE.md    # 架构设计文档
└── README.md              # 用户文档
```

**关键原则**: 文档不是事后补充，而是开发的起点。ROADMAP 定义做什么，ARCHITECTURE 定义怎么做，CODING-STANDARDS 定义怎么写。

### 1.2 WP 插件必备清单

在开始编码前，确认以下基础项：

| 项目 | 说明 |
|------|------|
| **插件头注释** | Plugin Name / Version / Text Domain / Requires at least / Requires PHP / License |
| **Text Domain** | 与插件 slug 一致，用于国际化 |
| **License** | GPL-2.0-or-later（WP.org 要求） |
| **readme.txt** | 遵循 WP.org 标准格式（Stable tag / Tested up to / Description） |
| **最低版本** | 明确 PHP 最低版本和 WordPress 最低版本 |
| **languages/** | 翻译文件目录，即使初始只有英文也应预留 |

### 1.3 CLAUDE.md 项目索引（AI 协作可选）

每个项目根目录建议有 `CLAUDE.md`，作为 AI 助手的入口点（非 AI 团队可选）：

```markdown
# 项目名称

## 版本信息
- 当前版本: x.y.z
- PHP 最低版本: 8.1
- WordPress 最低版本: 6.7

## 项目结构
（目录树 + 关键文件说明）

## 编码规范
（核心规则摘要或链接到 CODING-STANDARDS.md）

## 开发命令
（构建、测试、部署命令）

## 当前进度
（链接到 ROADMAP 或 CHANGELOG）
```

### 1.4 路线图分阶段规划

将大型项目拆分为可独立交付的阶段，每阶段产出一个可运行版本：

```
Phase 1: 基础架构（v1.0）
  → 核心类、自动加载、基本功能
  → 验收：插件可激活，核心功能可用

Phase 2: 核心功能（v1.5-2.0）
  → 业务逻辑、管理界面
  → 验收：主要功能完整

Phase 3: 优化迭代（v2.x-3.x）
  → 性能优化、架构重构、新模块
  → 验收：通过审计，无已知严重问题
```

**WPMind 实践**: 从 v1.0 到 v3.6.0 经历了 14 个版本，每个版本都是可运行的完整状态。

---

## 2. 编码规范

### 2.1 命名规范（必须遵守）

| 元素 | 规范 | 示例 |
|------|------|------|
| 类名 | `PascalCase` | `ModuleLoader`, `SDKAdapter` |
| 方法/函数 | `snake_case` | `get_api_key()`, `should_use_sdk()` |
| 常量 | `UPPER_SNAKE_CASE` | `WPMIND_VERSION`, `MAX_RETRIES` |
| 变量 | `snake_case` | `$provider_list`, `$retry_count` |
| 钩子名 | 前缀 + snake_case | `wpmind_before_chat`, `wpmind_sdk_providers` |
| 选项名 | 前缀 + snake_case | `wpmind_sdk_enabled`, `wpmind_default_provider` |

### 2.2 文件头部模板

每个 PHP 文件必须包含：

```php
<?php
/**
 * 文件描述（一句话说明用途）
 *
 * @package PluginName\Namespace
 * @since   1.0.0
 */

declare(strict_types=1);

namespace PluginName\Namespace;
```

### 2.3 类型安全

```php
// ✅ 正确：参数、返回值、属性都声明类型（命名空间内需用 use 或反斜杠前缀）
use WP_Error;

public function execute_request(array $args, string $provider, string $model): array|WP_Error
{
    // ...
}

// ❌ 错误：缺少类型声明
public function execute_request($args, $provider, $model)
{
    // ...
}
```

> **注意**: 在命名空间文件中使用 WordPress 核心类时，必须 `use WP_Error;` 或写全限定名 `\WP_Error`，否则 PHP 会在当前命名空间下查找导致 class not found。

### 2.4 安全规范（输入清理 vs 输出转义）

WordPress 安全的核心原则：**输入时清理（sanitize），输出时转义（escape）**。两者不可混淆。

#### 输入清理（Sanitize）

对所有外部输入在存储/处理前清理：

```php
// 文本字段
$title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));

// 富文本（保留安全 HTML）
$content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));

// Email
$email = sanitize_email($_POST['email'] ?? '');

// URL
$url = esc_url_raw($_POST['url'] ?? '');

// 整数
$page = absint($_GET['page'] ?? 1);

// 文件名
$filename = sanitize_file_name($_FILES['upload']['name'] ?? '');
```

#### 输出转义（Escape）

对所有动态内容在输出到页面前转义：

```php
// HTML 上下文
echo esc_html($user_input);

// HTML 属性
echo '<input value="' . esc_attr($value) . '">';

// URL
echo '<a href="' . esc_url($link) . '">';

// JavaScript
echo '<script>var data = ' . wp_json_encode($data) . ';</script>';

// 翻译 + 转义（推荐组合）
echo esc_html__('Settings saved', 'plugin-textdomain');
echo wp_kses_post($html_content);
```

#### SQL 预处理

所有数据库查询必须使用 `$wpdb->prepare()`：

```php
global $wpdb;

// ✅ 正确：使用 prepare 防止 SQL 注入
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}plugin_logs WHERE provider = %s AND status = %d",
        $provider,
        $status
    )
);

// ❌ 错误：直接拼接变量
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}plugin_logs WHERE provider = '{$provider}'"
);
```

### 2.5 请求处理安全

#### AJAX Handler

```php
public function handle_ajax_request(): void
{
    // 1. CSRF 防护
    check_ajax_referer('plugin_nonce_action', 'nonce');

    // 2. 权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
        return;
    }

    // 3. 输入清理
    $input = sanitize_text_field(wp_unslash($_POST['input'] ?? ''));

    // 业务逻辑...
    wp_send_json_success($result);
}
```

#### REST API 端点

```php
register_rest_route('plugin/v1', '/data', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_rest_request'],
    'permission_callback' => function (\WP_REST_Request $request): bool {
        return current_user_can('manage_options');
    },
    'args'                => [
        'provider' => [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function ($value): bool {
                return in_array($value, ['openai', 'anthropic', 'google'], true);
            },
        ],
    ],
]);
```

#### Settings 表单

```php
// 注册设置时指定清理回调
register_setting('plugin_options', 'plugin_api_key', [
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
]);

// 表单中输出 nonce
wp_nonce_field('plugin_settings_save', 'plugin_settings_nonce');
```

#### 远程请求安全

```php
// ✅ 使用 WordPress HTTP API（支持代理、SSL 配置）
$response = wp_safe_remote_get($url, [
    'timeout' => 30,
    'headers' => ['Authorization' => 'Bearer ' . $api_key],
]);

// ✅ URL 验证（拒绝内网地址，防止 SSRF）
if (!wp_http_validate_url($url)) {
    return new \WP_Error('invalid_url', 'URL validation failed');
}

// ❌ 避免直接使用 fopen/file_get_contents 访问远程 URL
// 不走 WP HTTP API，受 allow_url_fopen 限制，无代理支持
```

#### 文件上传安全

```php
// 验证文件类型
$filetype = wp_check_filetype($filename, [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
]);
if (!$filetype['type']) {
    return new \WP_Error('invalid_filetype', 'File type not allowed');
}

// 限制文件大小
$max_size = 25 * MB_IN_BYTES;
if (filesize($filepath) > $max_size) {
    return new \WP_Error('file_too_large', 'File exceeds size limit');
}

// 限制路径在 uploads 目录内
$upload_dir = wp_upload_dir();
$realpath = realpath($filepath);
if ($realpath === false || strpos($realpath, $upload_dir['basedir']) !== 0) {
    return new \WP_Error('invalid_path', 'File path not allowed');
}
```

### 2.6 防御性编程

```php
// 外部类依赖：先检查再使用
if (!class_exists('\\External\\SomeClass')) {
    return new \WP_Error('dependency_missing', 'Required class not found');
}

// JSON 解码：检查返回值
$data = json_decode($response_body, true);
if (!is_array($data)) {
    return new \WP_Error('invalid_json', 'Response is not valid JSON');
}

// 数组访问：使用 null 合并运算符
$model = $args['model'] ?? 'default-model';
$temperature = $args['temperature'] ?? 0.7;
```

---

## 3. 架构设计

### 3.1 目录结构模板

```
plugin-name/
├── plugin-name.php         # 主文件（入口点、自动加载、初始化）
├── uninstall.php           # 卸载清理
├── readme.txt              # WP.org 标准描述文件
├── LICENSE                 # GPL-2.0-or-later
├── includes/
│   ├── Core/               # 核心系统（模块加载、生命周期）
│   ├── API/                # 公共 API（对外接口）
│   ├── Admin/              # 管理后台（设置页面、AJAX）
│   ├── Providers/          # 服务提供者（可替换实现）
│   └── SDK/                # 外部 SDK 适配器
├── modules/                # 可选模块（按需加载）
│   └── module-name/
│       ├── module.json     # 模块元数据
│       └── Module.php      # 模块入口
├── assets/
│   ├── css/                # 样式（按功能拆分）
│   └── js/                 # 脚本（按功能拆分）
├── languages/              # 翻译文件（.pot/.po/.mo）
├── templates/              # 管理页面模板
├── tests/                  # 测试文件
└── docs/                   # 项目文档
```

### 3.2 主文件结构

```php
<?php
/**
 * Plugin Name:       Plugin Name
 * Plugin URI:        https://example.com/plugin-name
 * Description:       Brief description of the plugin.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Author Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-name
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace PluginName;

if (!defined('ABSPATH')) {
    exit;
}

define('PLUGIN_NAME_VERSION', '1.0.0');
define('PLUGIN_NAME_FILE', __FILE__);
define('PLUGIN_NAME_DIR', plugin_dir_path(__FILE__));

// PSR-4 自动加载
spl_autoload_register(function (string $class): void {
    $prefix = 'PluginName\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = PLUGIN_NAME_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 激活/停用钩子
register_activation_hook(__FILE__, function (): void {
    // 创建数据库表、设置默认选项、刷新重写规则等
    update_option('plugin_name_version', PLUGIN_NAME_VERSION);
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function (): void {
    // 清理临时数据、移除定时任务等
    wp_clear_scheduled_hook('plugin_name_cron_event');
    flush_rewrite_rules();
});

// 单例初始化
final class Plugin
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'load_modules']);
        add_action('admin_menu', [$this, 'register_admin_pages']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('plugin-name', false, dirname(plugin_basename(PLUGIN_NAME_FILE)) . '/languages');
    }
}

Plugin::get_instance();
```

### 3.3 适配器模式（SDK 集成）

当需要集成外部 SDK 时，使用适配器隔离依赖：

```php
// 伪代码示例 — 展示适配器模式的结构
class SDKAdapter
{
    private object $sdk_client;

    public function __construct(object $sdk_client)
    {
        $this->sdk_client = $sdk_client;
    }

    /**
     * 将外部 SDK 调用结果转换为插件内部格式
     */
    public function chat(array $args, string $provider, string $model): array|\WP_Error
    {
        try {
            // 1. 转换参数格式：内部 → SDK
            $sdk_args = $this->convert_args($args);

            // 2. 调用 SDK
            $result = $this->sdk_client->generate($sdk_args);

            // 3. 转换结果格式：SDK → 内部
            return $this->convert_result($result);

        } catch (\Exception $e) {
            // 4. 异常 → WP_Error（注意脱敏，不要暴露内部细节）
            return $this->convert_exception($e);
        }
    }
}
```

**WPMind 教训**: SDKAdapter 的 `convert_exception_to_wp_error()` 需要从异常消息中提取 HTTP 状态码（SDK 不直接暴露），用正则匹配实现。设计适配器时要考虑信息丢失问题。

### 3.4 能力 Gate 模式

当存在多条执行路径时，用 gate 方法决定走哪条：

```php
private function should_use_sdk(string $provider, array $args): bool
{
    // 第 1 层：适配器类是否存在
    if (!class_exists('\\PluginName\\SDK\\SDKAdapter')) {
        return false;
    }

    // 第 2 层：外部 SDK 是否可用
    if (!class_exists('\\External\\SDK\\Client')) {
        return false;
    }

    // 第 3 层：用户配置是否启用
    if (!get_option('plugin_sdk_enabled', true)) {
        return false;
    }

    // 第 4 层：当前请求是否兼容
    if (!empty($args['unsupported_feature'])) {
        return false;
    }

    // 第 5 层：Provider 白名单
    $sdk_providers = apply_filters('plugin_sdk_providers', ['provider_a', 'provider_b']);
    return in_array($provider, $sdk_providers, true);
}
```

---

## 4. 开发实施流程

### 4.1 审计驱动开发（Audit-Driven Development）

对于已有代码库的优化项目，推荐审计驱动模式：

```
Step 1: 全面审计
  → 逐文件阅读核心代码
  → 记录所有问题（分级：Critical / Important / Nice-to-have）
  → 输出审计报告

Step 2: Codex 评审
  → 将审计发现提交 Codex 讨论
  → 调整问题优先级
  → 达成修复方案共识

Step 3: 分阶段实施
  → Phase A: 修复确定性 Bug（不依赖调研）
  → Phase B: 功能增强（需要设计决策）
  → Phase C: 架构优化（需要调研和共识）

Step 4: 每阶段验证
  → PHP 语法检查
  → 安全评审（输入清理、输出转义、权限检查）
  → 部署到测试环境
  → HTTP 请求验证
  → 版本发布
```

**版本兼容矩阵**: 每个阶段发布前确认支持的环境范围：

| 项目 | 最低版本 | 测试版本 |
|------|----------|----------|
| PHP | 8.1 | 8.1 / 8.2 / 8.3 |
| WordPress | 6.7 | 6.7 / 最新 |
| MySQL | 8.0 | 8.0 / MariaDB 10.6 |

**WPMind 实践**: AI-PIPELINE-AUDIT.md 记录了完整的审计过程，从发现 15 个问题到分 3 个阶段修复，每阶段产出一个版本（v3.4.0→v3.5.0→v3.6.0）。

### 4.2 问题分级标准

| 级别 | 定义 | 处理方式 |
|------|------|----------|
| **CRITICAL** | 功能性 Bug，影响核心流程 | 立即修复，Phase A |
| **IMPORTANT** | 影响可靠性或一致性 | 优先修复，Phase A/B |
| **NICE-TO-HAVE** | 改进项，不影响现有功能 | 排期修复，Phase B/C |
| **PRODUCT GAP** | 产品方向缺失 | 评估后纳入路线图 |

### 4.3 单阶段实施流程

```
1. 任务拆分
   → 将阶段目标拆为独立任务（A1, A2, A3...）
   → 标注依赖关系（A3 依赖 A1）
   → 标注优先级（P0 > P1 > P2）

2. 并行开发
   → 无依赖的任务可并行（不同方法/不同文件）
   → 有依赖的任务串行执行
   → 同一文件的不同方法可并行（注意合并）

3. 集成验证
   → 所有任务完成后统一验证
   → PHP 语法检查：php -l file.php
   → 部署测试：deploy.sh + curl 验证
   → 功能回归：核心流程手动测试

4. 版本发布
   → 更新版本号（主文件 + CLAUDE.md）
   → 更新 CHANGELOG.md
   → Git 提交 + 标签
```

---

## 5. AI 协同开发模式（可选）

> 本章适用于使用 AI 工具辅助开发的团队。传统团队可跳过本章。

### 5.1 敏感信息保护

在 AI 协作中，必须注意：
- **不要**将 API 密钥、数据库凭据、用户数据提交给 AI 工具
- 代码片段中的敏感值用占位符替代（如 `YOUR_API_KEY`）
- 审计报告中脱敏处理所有真实 URL、IP、凭据
- AI 生成的错误消息不应包含内部实现细节

### 5.2 双 AI 审计模式

```
Claude（主力开发）          Codex（审查验证）
    │                           │
    ├── 代码审计 ──────────────→ 评审发现
    │                           │
    ├── 方案设计 ──────────────→ 方案评审
    │                           │
    ├── 代码实现                 │
    │                           │
    ├── 实现结果 ──────────────→ 代码审查
    │                           │
    └── 修复问题 ←────────────── 反馈意见
```

**使用时机**:
- 架构决策前：让 Codex 评审方案
- 阶段完成后：让 Codex 审查代码
- 遇到分歧时：多轮讨论达成共识

### 5.3 团队协同开发（Sub-Agent 模式）

对于大型阶段，可组建 AI 团队并行开发：

```
项目领导（Claude 主进程）
    │
    ├── dev-a（子代理）→ 任务 A1: 缓存键修复
    ├── dev-b（子代理）→ 任务 A2: JSON 防护
    └── dev-c（子代理）→ 任务 A3: 默认值统一
```

**关键规则**:
1. **任务边界清晰**: 每个子代理负责独立的方法或文件
2. **避免文件冲突**: 同一文件的不同方法可并行，但需明确边界
3. **超时处理**: 子代理卡死时 kill 并重试
4. **集成由领导完成**: 子代理完成后，领导负责集成验证

**WPMind 教训**:
- 子代理可能创建重复任务，领导需要清理
- 同一文件并行修改时，后完成的子代理可能覆盖先完成的修改
- 建议：一个文件尽量只分配给一个子代理

### 5.4 Codex 协作规范

```bash
# 提交问题给 Codex 讨论（也可替换为其他代码审查工具或人工 Review）
codex exec --full-auto "
请评审以下方案：
1. [方案描述]
2. [备选方案]
请从以下角度评估：
- 可行性
- 风险
- WordPress 生态兼容性
"
```

**讨论格式**:
- 提出具体问题（不要开放式提问）
- 提供上下文代码片段
- 要求给出评级（A/B/C）和理由
- 记录共识到审计文档

---

## 6. 质量控制体系

### 6.1 自动化质量门禁

#### PHPCS + WordPress Coding Standards

```bash
# 安装
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer

# 检查代码规范
vendor/bin/phpcs --standard=WordPress includes/

# 自动修复
vendor/bin/phpcbf --standard=WordPress includes/
```

#### PHPStan 静态分析

```bash
# 安装
composer require --dev phpstan/phpstan szepeviktor/phpstan-wordpress

# 运行分析（level 0-9，建议从 5 开始）
vendor/bin/phpstan analyse includes/ --level=5
```

#### PHPUnit + WP Test Suite

```bash
# 安装 WP 测试框架
composer require --dev yoast/phpunit-polyfills

# 运行测试
vendor/bin/phpunit --configuration phpunit.xml
```

### 6.2 手动检查清单

每次提交前检查：

```bash
# PHP 语法检查（必做）
find includes/ -name "*.php" -exec php -l {} \;

# 检查 strict_types 声明
grep -rL "declare(strict_types=1)" includes/ --include="*.php"

# 检查未使用的 use 语句（PHPStan 或 IDE 辅助）
```

### 6.3 集成验证流程

```bash
# 1. 部署到测试环境
./deploy.sh

# 2. 验证站点可访问
curl -sI https://test-site.com | head -1
# 期望: HTTP/2 200

# 3. 验证插件激活（通过 WP-CLI，推荐方式）
wp plugin list --status=active --path=/path/to/wordpress
# 或通过 REST API（公开端点）
curl -s https://test-site.com/wp-json/ | grep plugin-name

# 4. 验证核心功能（通过 WP-CLI）
wp eval 'var_dump(function_exists("wpmind_chat"));' --path=/path/to/wordpress

# 5. 验证 REST API 端点（需要认证）
# 使用 Application Password 或 cookie 认证
curl -s -u "admin:APPLICATION_PASSWORD" \
  https://test-site.com/wp-json/plugin/v1/status
```

### 6.4 错误分类与重试策略

```php
// 错误分类决定重试行为
$error_code = $result->get_error_code();

// 适配层错误：不消耗重试预算，回退到备用路径
if ($error_code === 'sdk_invalid_args' || $error_code === 'sdk_unavailable') {
    do_action('plugin_sdk_fallback', $provider, $error_code);
    return $this->execute_native($args, $provider, $model);
}

// Provider 错误：消耗重试预算，可能触发故障转移
// 429 (Rate Limit) / 503 (Service Unavailable) → 重试
// 401 (Unauthorized) / 404 (Not Found) → 不重试，直接故障转移
```

---

## 7. 版本管理与发布

### 7.1 语义化版本

```
MAJOR.MINOR.PATCH

MAJOR: 不兼容的架构变更（1.x → 2.x）
MINOR: 向后兼容的新功能（3.5 → 3.6）
PATCH: 向后兼容的 Bug 修复（3.2.0 → 3.2.1）
```

### 7.2 版本号更新位置

每次发布需同步更新：

1. **主文件头部**: `Version: x.y.z`
2. **主文件常量**: `define('PLUGIN_VERSION', 'x.y.z')`
3. **readme.txt**: `Stable tag: x.y.z`
4. **CLAUDE.md**: 版本信息（如使用）
5. **CHANGELOG.md**: 新版本条目

### 7.3 readme.txt 规范

WP.org 要求的标准格式：

```
=== Plugin Name ===
Contributors: author-slug
Tags: tag1, tag2
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Short description (max 150 characters).

== Description ==

Full description.

== Installation ==

1. Upload to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu

== Changelog ==

= 1.0.0 =
* Initial release
```

### 7.4 CHANGELOG 格式

```markdown
## [3.6.0] - 2026-02-07

### 新增
- SDKAdapter 适配器类，桥接 WP AI Client SDK
- `should_use_sdk()` 五层能力检查
- `wpmind_sdk_providers` 过滤器

### 修复
- SDK 异常未正确转换为 WP_Error

### 变更
- Anthropic/Google 请求默认走 SDK 路径
```

### 7.5 Git 提交规范

```
<type>: <subject>

<body>（可选，说明为什么这样做）

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
```

**Type 类型**:

| Type | 用途 | 示例 |
|------|------|------|
| `feat` | 新功能 | `feat: 智能路由系统 v1.9.0` |
| `fix` | Bug 修复 | `fix: 缓存键未包含 provider` |
| `refactor` | 重构 | `refactor: snake_case 命名规范化` |
| `docs` | 文档 | `docs: 更新 API 文档` |
| `security` | 安全修复 | `security: XSS 防护加固` |
| `perf` | 性能优化 | `perf: 减少数据库查询` |

---

## 8. 部署与验证

### 8.1 部署脚本模板

```bash
#!/bin/bash
# deploy.sh - 同步插件到测试站点

set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"
TARGET_DIR="/path/to/wordpress/wp-content/plugins/plugin-name"
DRY_RUN="${1:-}"

# 安全检查
if [ ! -d "$TARGET_DIR" ]; then
    echo "Error: Target directory not found"
    exit 1
fi

# dry-run 模式（预览变更）
RSYNC_FLAGS="-av"
if [ "$DRY_RUN" = "--dry-run" ]; then
    RSYNC_FLAGS="-avn"
    echo "=== DRY RUN MODE ==="
fi

# 同步文件（排除开发文件）
rsync $RSYNC_FLAGS --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='docs' \
    --exclude='.claude' \
    --exclude='.env' \
    "$SOURCE_DIR/" "$TARGET_DIR/"

if [ "$DRY_RUN" = "--dry-run" ]; then
    echo "=== Preview complete. Run without --dry-run to apply. ==="
    exit 0
fi

# 修复权限（根据环境调整，托管环境可能不需要 sudo）
# sudo chown -R www:www "$TARGET_DIR"
# sudo chmod -R 755 "$TARGET_DIR"

echo "Deployed successfully"
```

> **注意**: `rsync --delete` 会删除目标目录中源目录没有的文件。首次使用建议先 `./deploy.sh --dry-run` 预览。权限修复命令根据服务器环境调整，托管环境通常不需要 `sudo`。

#### WP.org SVN 发布（如适用）

```bash
# 1. 检出 SVN 仓库
svn co https://plugins.svn.wordpress.org/plugin-name/ svn-plugin

# 2. 同步代码到 trunk
rsync -av --delete --exclude='.git' --exclude='tests' --exclude='docs' \
    ./plugin-name/ svn-plugin/trunk/

# 3. 创建版本标签
cd svn-plugin
svn cp trunk tags/1.0.0

# 4. 提交
svn ci -m "Release 1.0.0"
```

### 8.2 部署后验证清单

```bash
# 1. 站点可访问
curl -sI https://site.com | head -1

# 2. 无 PHP Fatal Error
sudo tail -5 /var/log/php-error.log

# 3. 插件功能正常
curl -s https://site.com/wp-json/plugin/v1/status

# 4. 管理后台可访问
curl -sI https://site.com/wp-admin/ | head -1
```

---

## 9. 文档体系

### 9.1 必备文档

| 文档 | 用途 | 更新频率 |
|------|------|----------|
| `readme.txt` | WP.org 标准描述 | 每次版本发布 |
| `LICENSE` | 开源许可证（GPL-2.0-or-later） | 创建时 |
| `CHANGELOG.md` | 版本历史 | 每次版本发布 |
| `CODING-STANDARDS.md` | 编码规范 | 规范变更时 |
| `CLAUDE.md` | AI 入口点（AI 团队） | 每次版本发布 |

### 9.2 推荐文档

| 文档 | 用途 | 创建时机 |
|------|------|----------|
| `SECURITY.md` | 安全漏洞报告流程 | 项目公开时 |
| `CONTRIBUTING.md` | 贡献指南 | 接受外部贡献时 |
| `docs/ROADMAP.md` | 产品路线图 | 阶段完成时 |

### 9.3 按需文档

| 文档 | 用途 | 创建时机 |
|------|------|----------|
| `docs/AUDIT-REPORT.md` | 审计报告 | 代码审计后 |
| `docs/ARCHITECTURE.md` | 架构设计 | 重大架构变更 |
| `docs/public-api.md` | API 文档 | 公共 API 发布 |
| `docs/MIGRATION.md` | 迁移指南 | 破坏性变更 |

### 9.4 状态追踪

使用 `state.json` 跨会话追踪项目状态（建议加入 `.gitignore`，仅用于本地 AI 协作）：

```json
{
  "currentProject": "plugin-name",
  "version": "3.6.0",
  "lastSession": "2026-02-07",
  "activeTasks": [],
  "completedPhases": ["A", "B", "C"],
  "pendingItems": ["Codex 审计", "性能测试"]
}
```

---

## 10. 常见陷阱与教训

### 10.1 架构陷阱

**双系统并行问题**

当项目同时使用自建实现和外部 SDK 时，容易出现：
- 功能不一致（一条路径有故障转移，另一条没有）
- 维护负担翻倍
- 用户困惑（哪个在工作？）

**解决方案**: 使用适配器模式统一入口，gate 方法决定走哪条路径，确保对外接口一致。

**死代码问题**

写了工具方法但没有调用点：
- `ErrorHandler::should_retry()` 存在但从未被调用
- 重试延迟计算逻辑完整但未接入主流程

**解决方案**: 审计时专门检查"有定义无调用"的方法。

### 10.2 开发陷阱

**缓存键不完整**

```php
// ❌ 缓存键缺少关键维度
$cache_key = 'chat_' . md5(serialize($messages));

// ✅ 包含所有影响结果的参数
$cache_key = 'chat_' . md5(serialize($messages) . $provider . $model);
```

**JSON 解码不防护**

```php
// ❌ 非 JSON 响应会导致后续代码 TypeError
$data = json_decode($body, true);
$content = $data['choices'][0]['message']['content'];

// ✅ 检查解码结果
$data = json_decode($body, true);
if (!is_array($data)) {
    return new \WP_Error('invalid_json', 'Response is not valid JSON');
}
```

**Failover 时模型不重选**

```php
// ❌ 模型在循环外确定，failover 到其他 provider 时模型不存在
$model = $this->resolve_model($args);
foreach ($providers as $provider) {
    $result = $this->call($provider, $model);
}

// ✅ 每次 failover 重新选择模型
foreach ($providers as $provider) {
    $model = $this->resolve_model_for_provider($args, $provider);
    $result = $this->call($provider, $model);
}
```

### 10.3 协作陷阱

**子代理文件冲突**

多个子代理同时修改同一文件时，后完成的会覆盖先完成的修改。

**解决方案**:
- 一个文件尽量只分配给一个子代理
- 如果必须并行，明确各自负责的方法范围
- 领导在集成时逐一验证所有修改

**子代理重复创建任务**

子代理可能在自己的上下文中创建任务，与领导的任务列表重复。

**解决方案**: 领导定期检查任务列表，清理重复项。

### 10.4 安全陷阱

**输出未转义**

```php
// ❌ 直接输出用户数据，XSS 风险
echo '<p>' . $user_input . '</p>';

// ✅ 转义后输出
echo '<p>' . esc_html($user_input) . '</p>';
```

**SQL 拼接**

```php
// ❌ 直接拼接变量，SQL 注入风险
$wpdb->query("DELETE FROM {$wpdb->prefix}logs WHERE id = {$id}");

// ✅ 使用 prepare
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}logs WHERE id = %d", $id));
```

**SSRF（服务端请求伪造）**

```php
// ❌ 用户提供的 URL 直接请求，可访问内网
$response = wp_remote_get($user_provided_url);

// ✅ 验证 URL 安全性
if (!wp_http_validate_url($user_provided_url)) {
    return new \WP_Error('invalid_url', 'URL not allowed');
}
$response = wp_safe_remote_get($user_provided_url);
```

**文件路径遍历**

```php
// ❌ 用户提供的路径未验证，可读取任意文件
$content = file_get_contents($user_path);

// ✅ 限制在安全目录内
$upload_dir = wp_upload_dir();
$realpath = realpath($user_path);
if ($realpath === false || strpos($realpath, $upload_dir['basedir']) !== 0) {
    return new \WP_Error('invalid_path', 'Path not allowed');
}
```

### 10.5 环境陷阱

**PHP CLI 与 Web 环境差异**

```bash
# CLI 环境可能缺少 WordPress 上下文
php -r "require 'wp-load.php';"
# 可能报错：SQLite readonly / Plugin fatal

# 解决方案：用 HTTP 请求代替 CLI 测试
curl -s https://site.com/wp-json/plugin/v1/test
```

---

## 附录 A: 快速检查清单

### 新项目启动

- [ ] 创建完整插件头注释（Plugin Name / Version / Text Domain / Requires at least / Requires PHP / License）
- [ ] 创建 `readme.txt`（WP.org 标准格式）
- [ ] 创建 `LICENSE` 文件（GPL-2.0-or-later）
- [ ] 创建 CHANGELOG.md（从 v1.0.0 开始）
- [ ] 创建 CODING-STANDARDS.md
- [ ] 创建 `languages/` 目录，调用 `load_plugin_textdomain()`
- [ ] 设计目录结构
- [ ] 配置 PSR-4 自动加载
- [ ] 注册激活/停用/卸载钩子
- [ ] 所有 PHP 文件添加 `declare(strict_types=1)`
- [ ] 安装 PHPCS + WPCS + PHPStan

### 每次提交前

- [ ] `php -l` 语法检查通过
- [ ] PHPCS（WPCS 标准）检查通过
- [ ] 无硬编码密钥或凭据
- [ ] 所有输入已清理（`sanitize_*`）
- [ ] 所有输出已转义（`esc_*`）
- [ ] SQL 查询使用 `$wpdb->prepare()`
- [ ] AJAX/REST handler 包含 nonce + 权限检查
- [ ] 新方法有类型声明
- [ ] CHANGELOG 已更新

### 版本发布前

- [ ] 版本号已同步更新（主文件头、常量、readme.txt Stable tag）
- [ ] CHANGELOG 条目完整
- [ ] readme.txt 的 Tested up to 已更新
- [ ] 部署到测试环境验证通过
- [ ] 核心功能回归测试通过
- [ ] PHPStan 静态分析通过
- [ ] 安全审查完成（输入/输出/SQL/权限）
- [ ] Git 提交消息规范

### 阶段完成时

- [ ] 审计报告已更新
- [ ] state.json 已更新
- [ ] ROADMAP 进度已标记
- [ ] Codex 审查已完成（如适用）

---

## 附录 B: 参考项目

- **WPMind**: AI 服务聚合插件，本文档的实践来源
  - 135+ 次提交，14 个版本迭代
  - 双 AI 审计模式（Claude + Codex）
  - 团队协同开发（Sub-Agent 模式）
  - 完整的审计→分阶段修复→验证流程
