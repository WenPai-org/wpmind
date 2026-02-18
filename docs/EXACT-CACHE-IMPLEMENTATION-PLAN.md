# Exact Cache 模块实施计划

> 版本: v2.2 | 日期: 2026-02-09 | 状态: Codex 三轮审查后修订
> 经过 3 轮 Codex 审查（v1.0→v2.0→v2.1→v2.2），所有 P0/P1 问题已解决。

---

## 0. 审查修订记录

### v2.1 → v2.2 修订（Codex 三轮审查）

| # | 问题 | 严重度 | 修订措施 |
|---|------|--------|----------|
| F | ExactCacheModule 缺 ModuleInterface 必需方法（get_id/get_name 等） | P0 | 补全所有 7 个接口方法的完整实现 |
| G | 模块类加载链不完整：缺 require_once，autoloader 不覆盖 modules/ | P0 | 添加 require_once 加载 3 个 includes/ 类（与 GEO/API Gateway 相同模式） |
| H | nonce 名称不匹配：用 `wpmind_cache_nonce` 但现有体系用 `wpmind_ajax` | P1 | 统一使用 `wpmind_ajax`（复用 AdminAssets 已下发的 nonce） |
| I | Chart.js 句柄不匹配：写 `chart-js` 但已注册句柄是 `chartjs` | P1 | 改为 `chartjs` |
| J | 容量上限口径冲突：文档称 500 但保存允许 5000 | P2 | 明确：默认 500，用户可配置 10-5000 |
| K | 文件清单缺 `admin-exact-cache.js` | P2 | 新建文件改为 7 个，补充 JS 文件 |
| L | Step 1 称"全部 POST-only"但 get_stats 允许 GET | P2 | 修正描述：3 个写操作 POST-only，1 个只读允许 GET |
| M | 卸载未清理 transient 缓存条目 | P2 | 添加 `$wpdb->query()` 批量删除 `_transient_wpmind_ec_%` |
| N | services.php 存在重复缓存设置字段 | P2 | 文档标注：v1 保留 services.php 旧字段（向后兼容），v1.1 迁移后移除 |
| O | scope_mode 挂在可禁用模块，禁用后回退默认 | P2 | 文档标注：设计如此，禁用模块 = 使用默认 role 隔离（最安全默认值） |

### v2.0 → v2.1 修订（Codex 二轮审查，5 项已全部解决）

| # | 问题 | 严重度 | 修订措施 |
|---|------|--------|----------|
| A | 富索引自相矛盾：v1 范围列了"富索引"，但 4.6 节说推迟到 v1.1 | P0 | **v1 完全移除富索引**。不修改 `set()`/`touch_index()` 索引格式。仅添加 `get_entries()` 只读方法 |
| B | `wpmind_settings_tabs` filter 从未被消费，模板完全静态 | P0 | **新增修改文件 `settings-page.php`**，手动添加 exact-cache tab（与 GEO/API Gateway 相同模式） |
| C | AJAX 破坏性操作缺 POST-only 强制 + `wp_unslash()` | P1 | 3 个写操作 handler 添加 `$_SERVER['REQUEST_METHOD'] === 'POST'` 检查；所有 `$_POST` 读取包裹 `wp_unslash()` |
| D | DailyStats lock 被持有时直接 return，`$pending` 数据随请求结束丢失 | P1 | 改为 retry-once 模式：lock 被持有时 `usleep(100000)` 后重试一次，仍失败则接受丢失（统计非关键数据） |
| E | `scope_mode` option 保存了但未接入 ExactCache 的 `wpmind_exact_cache_scope_mode` filter | P2 | ExactCacheModule::init() 中添加 `add_filter()` 读取 option 值 |

### v1.0 → v2.0 修订（Codex 首轮审查，10 项已全部解决）

| # | 原问题 | 修订措施 |
|---|--------|----------|
| 1 | `delete_by_prefix()` 无法工作（key 是 hash） | v1 不做按条件删除，推迟到 v1.1 富索引 |
| 2 | 设置页静态 Tab 不消费 `wpmind_settings_tabs` | → 见 v2.1 修订 B |
| 3 | `ajax_get_cache_stats()` 缺安全三要素 | 所有 4 个 AJAX handler 均强制 nonce + capability + sanitize |
| 4 | EmbeddingService 无缓存链路 | **v1 移除 per-type TTL**，仅保留全局 TTL |
| 5 | StructuredOutput 委托 ChatService | 同上，v1 不做 per-type TTL |
| 6 | 多数 Service 默认 cache_ttl > 0 | 同上，v1 不改 AbstractService |
| 7 | "重置统计"无 AJAX handler | 新增 `ajax_reset_cache_stats()` handler |
| 8 | 类型 TTL 修改风险被低估 | v1 完全不修改 AbstractService，零风险 |
| 9 | DailyStats 并发覆盖风险 | → 见 v2.1 修订 D（retry-once 模式） |
| 10 | 卸载清理未覆盖 | 新增 uninstall 清理项 |

---

## 1. 现状分析

### 已有基础设施（不修改）

| 组件 | 文件 | 说明 |
|------|------|------|
| ExactCache 核心类 | `includes/Cache/ExactCache.php` | SHA256 哈希键，transient 存储，LRU 淘汰，scope 隔离 |
| AbstractService 集成 | `includes/API/Services/AbstractService.php:190-286` | cache key 生成 / 读取 / 写入 |
| ChatService 缓存流程 | `includes/API/Services/ChatService.php` | 缓存命中跳过 API 调用和用量统计 |
| 缓存统计 API | `ExactCache::get_stats()` | hits/misses/writes/hit_rate/entries |

### v1 范围（本次实施）

| 功能 | 说明 |
|------|------|
| 模块 UI | 设置页面：启用/禁用、TTL、最大条目、隔离模式 |
| 统计面板 | 4 个统计卡片 + 7 天趋势图 |
| 成本节省估算 | 基于 UsageTracker 历史数据 |
| 缓存管理 | 清空缓存、重置统计 |
| 条目只读查询 | `get_entries()` 方法，仅读取现有索引，不改索引格式 |

### v1.1 范围（后续实施，本次不做）

| 功能 | 原因 |
|------|------|
| 富索引 | 索引存储 type/provider 元数据，需修改 `set()`/`touch_index()`，影响核心层 |
| 按类型 TTL | EmbeddingService 无缓存链路，StructuredOutput 委托 ChatService，需先审计所有 Service 缓存行为 |
| 缓存条目浏览 | 依赖富索引稳定后 |
| 按条件清除 | 依赖富索引稳定后 |
| CacheStoreInterface 抽象 | v2 自定义表时引入 |

---

## 2. 架构决策

### 2.1 模块 vs 核心

```
includes/Cache/ExactCache.php     ← 核心层（仅添加 get_entries() 方法）
modules/exact-cache/              ← 新模块（UI + 统计 + 管理）
```

**v1 不修改 AbstractService**，零影响面。

### 2.2 索引策略

**v1 不修改索引格式**。当前索引结构 `key => timestamp` 保持不变。

仅添加只读方法 `get_entries()` 供管理界面显示条目数和最后访问时间。

**v1.1 计划**：引入富索引（`key => ['ts' => ..., 'type' => ..., 'provider' => ...]`），支持按条件查询和删除。需要修改 `set()`/`touch_index()`，属于核心层变更，v1 不做。

### 2.3 存储方案

保持 transient 存储。默认 500 条上限，用户可配置 10-5000（AJAX 保存时边界校验）。

### 2.4 成本节省估算

```
节省金额 = 缓存命中次数 × 平均每次请求成本
平均每次请求成本 = 总成本 / 总请求数（从 UsageTracker 获取）
```

明确标注"估算值"，UI 显示计算公式 tooltip。

---

## 3. 文件清单

### 3.1 新建文件（7 个）

```
modules/exact-cache/
├── module.json                              # 模块元数据
├── ExactCacheModule.php                     # 主模块类
├── includes/
│   ├── CostEstimator.php                    # 成本节省估算器
│   ├── DailyStats.php                       # 日维度统计（7天滚动）
│   └── CacheAjaxController.php              # AJAX 处理器（4 个 handler）
└── templates/
    └── settings.php                         # 管理界面模板

assets/js/
└── admin-exact-cache.js                     # 缓存管理 JS（统计刷新、图表、AJAX 操作）
```

### 3.2 修改文件（4 个）

| 文件 | 修改内容 |
|------|----------|
| `includes/Cache/ExactCache.php` | 仅添加 `get_entries()` 只读方法，不改索引格式 |
| `templates/settings-page.php` | 添加 exact-cache tab 导航 + 内容面板（与 GEO/API Gateway 相同模式） |
| `assets/css/admin.css` | 添加 `.wpmind-cache-*` 组件样式 |
| `uninstall.php` | 添加 exact-cache 相关 option/transient 清理 |

---

## 4. 详细实现

### 4.1 module.json

```json
{
    "id": "exact-cache",
    "name": "Exact Cache",
    "description": "AI 请求精确缓存 - 降低 API 成本、加速重复请求",
    "version": "1.0.0",
    "author": "WPMind",
    "icon": "ri-database-2-line",
    "class": "WPMind\\Modules\\ExactCache\\ExactCacheModule",
    "can_disable": true,
    "settings_tab": "exact-cache",
    "features": [
        "精确匹配缓存（SHA256 哈希）",
        "成本节省估算",
        "7 天趋势统计",
        "缓存命中率面板",
        "LRU 自动淘汰",
        "角色/用户级隔离"
    ],
    "requires": {
        "php": "8.1",
        "wordpress": "6.0"
    }
}
```

### 4.2 ExactCacheModule.php

```php
declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

use WPMind\Core\ModuleInterface;

// Load module classes（autoloader 仅覆盖 includes/，modules/ 需手动加载）.
require_once __DIR__ . '/includes/CostEstimator.php';
require_once __DIR__ . '/includes/DailyStats.php';
require_once __DIR__ . '/includes/CacheAjaxController.php';

final class ExactCacheModule implements ModuleInterface {

    public function get_id(): string {
        return 'exact-cache';
    }

    public function get_name(): string {
        return __('精确缓存', 'wpmind');
    }

    public function get_description(): string {
        return __('AI 请求精确缓存 - 降低 API 成本、加速重复请求', 'wpmind');
    }

    public function get_version(): string {
        return '1.0.0';
    }

    public function check_dependencies(): bool {
        return true; // 无外部依赖
    }

    public function get_settings_tab(): ?string {
        return 'exact-cache';
    }

    public function init(): void {
        // 1. 注册 settings tab（vestigial filter，实际 tab 由 settings-page.php 静态渲染）
        add_filter('wpmind_settings_tabs', [$this, 'register_settings_tab']);

        // 2. 接入 scope_mode option → ExactCache 核心 filter
        //    注意：模块禁用后此 filter 不生效，核心回退默认 'role'（最安全默认值）
        add_filter('wpmind_exact_cache_scope_mode', function (): string {
            return get_option('wpmind_exact_cache_scope_mode', 'role');
        });

        // 3. 注册 AJAX handlers
        $ajax = new CacheAjaxController();
        $ajax->register_hooks();

        // 4. 注册缓存事件 hooks → DailyStats
        add_action('wpmind_exact_cache_hit', [DailyStats::class, 'record_hit']);
        add_action('wpmind_exact_cache_miss', [DailyStats::class, 'record_miss']);
        add_action('wpmind_exact_cache_store', [DailyStats::class, 'record_write']);

        // 5. 管理页面脚本（仅在 WPMind 设置页加载）
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_settings_tab(array $tabs): array {
        $tabs['exact-cache'] = [
            'title'    => __('精确缓存', 'wpmind'),
            'icon'     => 'ri-database-2-line',
            'template' => __DIR__ . '/templates/settings.php',
            'priority' => 20,
        ];
        return $tabs;
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        // 仅在 WPMind 设置页加载
        if ('toplevel_page_wpmind' !== $hook_suffix) {
            return;
        }
        wp_enqueue_script(
            'wpmind-admin-exact-cache',
            WPMIND_PLUGIN_URL . 'assets/js/admin-exact-cache.js',
            ['jquery', 'chartjs', 'wpmind-admin-boot'],
            WPMIND_VERSION,
            true
        );
    }
}
```

### 4.3 CacheAjaxController.php — 4 个 AJAX handler

```php
declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

final class CacheAjaxController {

    public function register_hooks(): void {
        add_action('wp_ajax_wpmind_save_cache_settings', [$this, 'ajax_save_cache_settings']);
        add_action('wp_ajax_wpmind_flush_cache', [$this, 'ajax_flush_cache']);
        add_action('wp_ajax_wpmind_reset_cache_stats', [$this, 'ajax_reset_cache_stats']);
        add_action('wp_ajax_wpmind_get_cache_stats', [$this, 'ajax_get_cache_stats']);
    }

    /**
     * 安全前置检查（所有 handler 共用）
     * 使用 wpmind_ajax nonce（AdminAssets 已全局下发，复用现有体系）
     */
    private function verify_request(bool $require_post = false): void {
        if ($require_post && 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
            wp_send_json_error(['message' => 'Method not allowed'], 405);
        }
        check_ajax_referer('wpmind_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }
    }

    // 1. 保存设置（POST only）
    public function ajax_save_cache_settings(): void {
        $this->verify_request(true);

        // 白名单字段 + 边界校验 + wp_unslash
        $raw = wp_unslash($_POST);
        $enabled = in_array($raw['enabled'] ?? '', ['1', '0'], true) ? $raw['enabled'] : '1';
        $default_ttl = max(0, min(86400, (int) ($raw['default_ttl'] ?? 900)));
        $max_entries = max(10, min(5000, (int) ($raw['max_entries'] ?? 500)));
        $scope_mode = in_array($raw['scope_mode'] ?? '', ['role', 'user', 'none'], true)
            ? $raw['scope_mode'] : 'role';

        update_option('wpmind_exact_cache_enabled', $enabled, false);
        update_option('wpmind_exact_cache_default_ttl', $default_ttl, false);
        update_option('wpmind_exact_cache_max_entries', $max_entries, false);
        update_option('wpmind_exact_cache_scope_mode', $scope_mode, false);

        wp_send_json_success();
    }

    // 2. 清空缓存（POST only，破坏性操作）
    public function ajax_flush_cache(): void {
        $this->verify_request(true);

        \WPMind\Cache\ExactCache::instance()->flush();
        wp_send_json_success();
    }

    // 3. 重置统计（POST only，破坏性操作）
    public function ajax_reset_cache_stats(): void {
        $this->verify_request(true);

        // 重置核心统计
        update_option('wpmind_exact_cache_stats', [
            'hits' => 0, 'misses' => 0, 'writes' => 0,
            'last_hit_at' => 0, 'last_miss_at' => 0, 'last_write_at' => 0,
            'last_key' => '',
        ], false);
        // 重置日统计
        DailyStats::reset();
        wp_send_json_success();
    }

    // 4. 获取统计（GET 允许，只读操作，仍需安全校验）
    public function ajax_get_cache_stats(): void {
        $this->verify_request(false);

        wp_send_json_success([
            'stats'    => \WPMind\Cache\ExactCache::instance()->get_stats(),
            'daily'    => DailyStats::get_daily_data(),
            'savings'  => CostEstimator::get_estimated_savings(),
        ]);
    }
}
```

### 4.4 DailyStats.php（含 retry-once lock）

```php
declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

final class DailyStats {

    private const OPTION_KEY = 'wpmind_exact_cache_daily_stats';
    private const LOCK_KEY = 'wpmind_daily_stats_lock';
    private const MAX_DAYS = 7;

    private static array $pending = [];
    private static bool $shutdown_registered = false;

    public static function record_hit(): void { self::buffer('hits'); }
    public static function record_miss(): void { self::buffer('misses'); }
    public static function record_write(): void { self::buffer('writes'); }

    private static function buffer(string $metric): void {
        $today = wp_date('Y-m-d');
        if (!isset(self::$pending[$today])) {
            self::$pending[$today] = ['hits' => 0, 'misses' => 0, 'writes' => 0];
        }
        self::$pending[$today][$metric]++;

        if (!self::$shutdown_registered) {
            add_action('shutdown', [self::class, 'flush_pending']);
            self::$shutdown_registered = true;
        }
    }

    public static function flush_pending(): void {
        if (empty(self::$pending)) { return; }

        // Retry-once lock：首次失败后等 100ms 重试一次
        if (!self::acquire_lock()) {
            usleep(100000); // 100ms
            if (!self::acquire_lock()) {
                // 仍然失败，接受丢失（统计非关键数据，下次请求会补上新数据）
                return;
            }
        }

        $data = get_option(self::OPTION_KEY, []);
        if (!is_array($data)) { $data = []; }

        foreach (self::$pending as $date => $metrics) {
            if (!isset($data[$date])) {
                $data[$date] = ['hits' => 0, 'misses' => 0, 'writes' => 0];
            }
            $data[$date]['hits'] += $metrics['hits'];
            $data[$date]['misses'] += $metrics['misses'];
            $data[$date]['writes'] += $metrics['writes'];
        }

        // 滚动清理：只保留最近 7 天
        ksort($data);
        while (count($data) > self::MAX_DAYS) {
            array_shift($data);
        }

        update_option(self::OPTION_KEY, $data, false);
        delete_transient(self::LOCK_KEY);
        self::$pending = [];
    }

    private static function acquire_lock(): bool {
        if (get_transient(self::LOCK_KEY)) {
            return false;
        }
        set_transient(self::LOCK_KEY, 1, 5);
        return true;
    }

    public static function get_daily_data(): array {
        $data = get_option(self::OPTION_KEY, []);
        if (!is_array($data)) { return []; }

        // 补齐最近 7 天（无数据的天填 0）
        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = wp_date('Y-m-d', strtotime("-{$i} days"));
            $result[$date] = $data[$date] ?? ['hits' => 0, 'misses' => 0, 'writes' => 0];
        }
        return $result;
    }

    public static function reset(): void { delete_option(self::OPTION_KEY); }
}
```

### 4.5 CostEstimator.php

```php
declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

final class CostEstimator {

    public static function get_estimated_savings(): array {
        $cache_stats = \WPMind\Cache\ExactCache::instance()->get_stats();
        $hits = (int) ($cache_stats['hits'] ?? 0);

        if ($hits === 0) {
            return ['total_usd' => 0.0, 'total_cny' => 0.0, 'avg_cost_per_request' => 0.0];
        }

        // 从 UsageTracker 获取平均请求成本
        $usage_stats = [];
        if (class_exists('\WPMind\Modules\CostControl\UsageTracker')) {
            $usage_stats = \WPMind\Modules\CostControl\UsageTracker::get_stats();
        }

        $total_cost = (float) ($usage_stats['total']['cost_usd'] ?? 0.0);
        $total_requests = (int) ($usage_stats['total']['requests'] ?? 0);

        if ($total_requests === 0) {
            return ['total_usd' => 0.0, 'total_cny' => 0.0, 'avg_cost_per_request' => 0.0];
        }

        $avg_cost = $total_cost / $total_requests;
        $saved_usd = $hits * $avg_cost;
        $saved_cny = $saved_usd * 7.2; // 近似汇率

        return [
            'total_usd'            => round($saved_usd, 4),
            'total_cny'            => round($saved_cny, 2),
            'avg_cost_per_request' => round($avg_cost, 6),
        ];
    }
}
```

### 4.6 ExactCache.php 修改（最小化）

仅添加 1 个只读方法，**不修改任何现有方法**，不改索引格式：

```php
/**
 * 获取索引条目列表（供管理界面使用）
 *
 * @return array<int, array{key: string, last_access: int}>
 */
public function get_entries(): array {
    $index = $this->load_index();
    arsort($index, SORT_NUMERIC);
    $entries = [];
    foreach ($index as $key => $timestamp) {
        $entries[] = [
            'key'         => $key,
            'last_access' => (int) $timestamp,
        ];
    }
    return $entries;
}
```

**零影响面**：不修改 `set()`、`get()`、`touch_index()`、`evict()` 等任何现有方法。

### 4.7 templates/settings.php

UI 结构同 v1，移除"按类型 TTL"区域。

```
统计卡片行（命中率 | 缓存条目 | 节省成本 | 总请求）
7 天趋势图（Chart.js 折线图）
缓存设置（启用 | 默认 TTL | 最大条目 | 隔离模式）
缓存管理（清空缓存 | 重置统计）
```

**JS 处理**：使用独立 `admin-exact-cache.js` 文件，通过 `wp_enqueue_script` 加载，依赖 `jquery` 和 `chartjs`（已在 AdminAssets 中注册）。模块在 `enqueue_admin_assets()` 中注册脚本。

**注意**：`templates/tabs/services.php` 中已有 `wpmind_exact_cache_enabled`/`default_ttl`/`max_entries` 三个字段（旧版设置入口）。v1 保留这些字段（向后兼容），新模块 tab 提供完整设置（含 scope_mode）。v1.1 迁移完成后移除 services.php 中的重复字段。

### 4.8 uninstall.php 补充

```php
// 在 clean_site_data() 中添加：

// 1. Options 清理
delete_option('wpmind_exact_cache_enabled');
delete_option('wpmind_exact_cache_default_ttl');
delete_option('wpmind_exact_cache_max_entries');
delete_option('wpmind_exact_cache_scope_mode');
delete_option('wpmind_exact_cache_index');
delete_option('wpmind_exact_cache_stats');
delete_option('wpmind_exact_cache_daily_stats');

// 2. Transient 缓存条目批量清理
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wpmind_ec_%'
        OR option_name LIKE '_transient_timeout_wpmind_ec_%'"
);

// 3. DailyStats lock transient（通常已过期，保险起见删除）
delete_transient('wpmind_daily_stats_lock');
```

### 4.9 settings-page.php 修改

**已知限制**：`wpmind_settings_tabs` filter 被所有模块注册但从未被消费。模板使用静态硬编码 + `ModuleLoader::get_module()` 条件渲染。这是已有技术债，v1 遵循现有模式，不做动态化改造。

**修改 1 — 模块状态检查**（在现有模块检查块末尾添加）：

```php
$exact_cache_module = $module_loader->get_module('exact-cache');
$exact_cache_enabled = $exact_cache_module && $exact_cache_module['enabled'];
```

**修改 2 — Tab 导航**（在 API Gateway tab 之后、模块管理 tab 之前插入）：

```php
<?php if ($exact_cache_enabled): ?>
<a href="#exact-cache" class="wpmind-tab" data-tab="exact-cache">
    <?php esc_html_e('精确缓存', 'wpmind'); ?>
</a>
<?php endif; ?>
```

**修改 3 — Tab 内容面板**（在 API Gateway 面板之后、模块管理面板之前插入）：

```php
<?php if ($exact_cache_enabled): ?>
<div id="exact-cache" class="wpmind-tab-pane">
    <?php
    $exact_cache_settings = WPMIND_PATH . 'modules/exact-cache/templates/settings.php';
    if (file_exists($exact_cache_settings)) {
        include $exact_cache_settings;
    }
    ?>
</div>
<?php endif; ?>
```

---

## 5. CSS 样式

在 `assets/css/admin.css` 末尾添加：

```css
/* === Exact Cache Module === */
.wpmind-cache-trend-chart { ... }
.wpmind-cache-actions { ... }
```

复用：`.wpmind-geo-header`, `.wpmind-stat-card`, `form-table`

---

## 6. 实施步骤

### Step 1: 模块骨架 + AJAX
1. 创建 `modules/exact-cache/module.json`
2. 创建 `ExactCacheModule.php`（实现全部 7 个 ModuleInterface 方法 + require_once 加载类 + scope_mode filter）
3. 创建 `CacheAjaxController.php`（3 个写操作 POST-only + 1 个只读允许 GET，全部含 nonce + capability + wp_unslash）
4. 修改 `templates/settings-page.php`（添加 exact-cache tab 导航 + 内容面板）
5. 验证模块加载和 Tab 显示

### Step 2: 业务逻辑
1. 创建 `CostEstimator.php`
2. 创建 `DailyStats.php`（含 retry-once lock）
3. `ExactCache.php` 添加 `get_entries()` 只读方法

### Step 3: 管理界面
1. 创建 `templates/settings.php`
2. 创建 `assets/js/admin-exact-cache.js`
3. 添加 CSS 样式到 `admin.css`

### Step 4: 清理 + 集成
1. `uninstall.php` 添加清理项
2. 模块启用/禁用测试
3. AJAX 安全测试（无 nonce / 无权限 / 非法参数 / GET 请求到 POST-only 端点）
4. 统计准确性验证
5. scope_mode 切换验证（role → user → none → role）
6. 禁用模块后核心缓存行为不变

---

## 7. 团队分工

| 角色 | 负责 | 说明 |
|------|------|------|
| **WordPress 专家** | Step 1 + Step 3 | 模块骨架、AJAX、管理界面、CSS/JS |
| **AI 专家** | Step 2 | 成本估算、日统计、核心类增强 |
| **项目负责人** | Step 4 | 集成测试、代码审查、部署 |

---

## 8. 风险与缓解

| 风险 | 等级 | 缓解 |
|------|------|------|
| transient 存储性能 | 中 | 默认 500 条 + LRU 已有，可配置上限 5000 |
| 成本估算偏差 | 低 | 标注"估算"，显示公式 |
| DailyStats 并发丢数据 | 低 | retry-once lock（100ms 重试），最坏丢 1 次请求的统计 |
| settings-page.php 静态 tab | 低 | 遵循现有模式，技术债留给后续统一动态化 |
| scope_mode 模块禁用后回退 | 低 | 设计如此：禁用模块 = 使用默认 role 隔离（最安全默认值） |
| services.php 重复设置字段 | 低 | v1 保留向后兼容，v1.1 迁移后移除 |
| **v1 不修改 AbstractService** | **无** | **零影响面，核心层完全不变** |
| **v1 不修改索引格式** | **无** | **仅添加只读方法，核心缓存行为不变** |

---

## 9. 验收标准

1. 模块在"模块管理"页面可见，可启用/禁用
2. 启用后"精确缓存"tab 出现在设置页导航中
3. 设置页面显示 4 个统计卡片
4. 7 天趋势图正确渲染（无数据天显示 0）
5. 设置保存后立即生效（TTL、最大条目、隔离模式）
6. scope_mode 切换后缓存 key 隔离行为正确变化
7. 清空缓存后索引清空、transient 删除
8. 重置统计后计数归零、日统计清空
9. 禁用模块后缓存仍工作（核心层独立）
10. 所有 AJAX 端点通过安全测试：
    - 无 nonce → 403
    - 无权限 → 403
    - GET 请求到 POST-only 端点 → 405
    - 非法参数 → 使用默认值（不报错）
11. 无 PHP 错误、无 JS 控制台错误
12. uninstall 后无残留 option
