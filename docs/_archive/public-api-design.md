# WPMind 公共 API 设计文档 v3.0

> 版本：3.0（融合专家审查意见）
> 更新日期：2026-02-02
> 状态：**v2.5.0 + v2.6.0 已实施** ✅

---

## 1. 设计原则

| 原则 | 说明 |
|-----|------|
| **WordPress 原生** | 全局函数 + Hooks |
| **多轮对话** | Messages 数组支持 |
| **上下文感知** | context 参数区分场景 |
| **优雅降级** | WPMind 未安装时优雅处理 |
| **性能优先** | 缓存 + 异步 |

---

## 2. 基础 API (v2.5.0)

### 2.1 `wpmind_is_available()`

```php
/**
 * 检查 WPMind 是否可用
 *
 * @return bool
 */
function wpmind_is_available(): bool;

// 使用
if (wpmind_is_available()) {
    // 使用 WPMind
} else {
    // 降级方案
}
```

---

### 2.2 `wpmind_get_status()`

```php
/**
 * 获取 WPMind 状态
 *
 * @return array
 */
function wpmind_get_status(): array;

// 返回
[
    'available'       => true,
    'provider'        => 'deepseek',
    'model'           => 'deepseek-chat',
    'usage' => [
        'today'       => 1234,
        'month'       => 56789,
        'limit'       => 100000,
    ],
]
```

---

### 2.3 `wpmind_chat()` ⭐ 核心

```php
/**
 * AI 对话（支持多轮、多模态）
 *
 * @param array|string $messages 消息或简单 Prompt
 * @param array        $options  选项
 * @return array|WP_Error
 */
function wpmind_chat($messages, array $options = []): array|WP_Error;
```

#### 参数说明

**$messages**：
```php
// 简单模式（单轮）
$result = wpmind_chat('写一首关于春天的诗');

// 多轮对话模式
$result = wpmind_chat([
    ['role' => 'system', 'content' => '你是一个 SEO 专家'],
    ['role' => 'user', 'content' => '为这篇文章生成标题：...'],
]);
```

**$options**：
| 参数 | 类型 | 默认值 | 说明 |
|-----|------|-------|------|
| `context` | string | `''` | **必填推荐** - 用于 Hook 区分场景 |
| `system` | string | `''` | 系统提示词（简单模式） |
| `max_tokens` | int | `1000` | 最大 token |
| `temperature` | float | `0.7` | 温度 (0-1) |
| `model` | string | `'auto'` | 模型选择 |
| `provider` | string | `'auto'` | 服务商 |
| `json_mode` | bool | `false` | JSON 模式 |
| `cache_ttl` | int | `0` | 缓存秒数 (0=不缓存) |

#### 返回值

```php
[
    'content'   => 'AI 生成的内容',
    'provider'  => 'deepseek',
    'model'     => 'deepseek-chat',
    'usage'     => [
        'prompt_tokens'     => 100,
        'completion_tokens' => 50,
        'total_tokens'      => 150,
    ],
]
```

---

### 2.4 `wpmind_translate()`

```php
/**
 * 翻译文本
 *
 * @param string $text    要翻译的文本
 * @param string $from    源语言 (auto/zh/en/ja...)
 * @param string $to      目标语言
 * @param array  $options 选项
 * @return string|WP_Error
 */
function wpmind_translate(
    string $text,
    string $from = 'auto',
    string $to = 'en',
    array $options = []
): string|WP_Error;
```

**$options**：
| 参数 | 类型 | 默认值 | 说明 |
|-----|------|-------|------|
| `context` | string | `'translation'` | Hook 上下文 |
| `format` | string | `'text'` | text/slug/json |
| `hint` | string | `''` | 翻译提示 |
| `cache_ttl` | int | `86400` | 缓存秒数 (默认1天) |

---

### 2.5 `wpmind_generate_image()`

```php
/**
 * 生成图像
 *
 * @param string $prompt  图像描述
 * @param array  $options 选项
 * @return array|WP_Error
 */
function wpmind_generate_image(
    string $prompt,
    array $options = []
): array|WP_Error;
```

**$options**：
| 参数 | 类型 | 默认值 | 说明 |
|-----|------|-------|------|
| `size` | string | `'1024x1024'` | 尺寸 |
| `quality` | string | `'standard'` | standard/hd |
| `style` | string | `'natural'` | natural/vivid |
| `return_format` | string | `'url'` | url/attachment_id |

---

## 3. Hooks 设计

### 3.1 参数过滤

```php
// 请求参数过滤（带 context）
$args = apply_filters('wpmind_chat_args', $args, $context, $original_messages);

// 翻译参数过滤
$args = apply_filters('wpmind_translate_args', $args, $context);
```

### 3.2 响应过滤

```php
// 响应结果过滤
$response = apply_filters('wpmind_chat_response', $response, $args, $context);

// 翻译结果过滤
$translated = apply_filters('wpmind_translate_response', $translated, $text, $from, $to);
```

### 3.3 模型选择

```php
// 动态选择模型（如 VIP 用户用更好的模型）
$model = apply_filters('wpmind_select_model', $model, $context, $user_id);

// 动态选择服务商
$provider = apply_filters('wpmind_select_provider', $provider, $context);
```

### 3.4 Actions

```php
// 请求前
do_action('wpmind_before_request', $type, $args, $context);

// 请求后（含用量）
do_action('wpmind_after_request', $type, $response, $args, $usage);

// 错误
do_action('wpmind_error', $error, $type, $args);
```

---

## 4. 实现规范

### 4.1 文件结构

```
wpmind/includes/
├── API/
│   ├── PublicAPI.php       # 公共 API 主类
│   ├── ChatHandler.php     # Chat 处理器
│   ├── TranslateHandler.php # 翻译处理器
│   ├── ImageHandler.php    # 图像处理器
│   └── functions.php       # 全局函数定义
```

### 4.2 全局函数封装

```php
// includes/API/functions.php

if (!function_exists('wpmind_chat')) {
    function wpmind_chat($messages, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error(
                'wpmind_not_available',
                __('WPMind 插件未激活', 'wpmind')
            );
        }
        return WPMind\API\PublicAPI::instance()->chat($messages, $options);
    }
}
```

### 4.3 缓存策略

```php
// 缓存键生成
$cache_key = 'wpmind_' . $type . '_' . md5(serialize($args));

// 允许过滤
$cache_key = apply_filters('wpmind_cache_key', $cache_key, $type, $args);

// 使用 Transients
$cached = get_transient($cache_key);
if ($cached !== false) {
    return $cached;
}

// 存储结果
if ($options['cache_ttl'] > 0) {
    set_transient($cache_key, $result, $options['cache_ttl']);
}
```

### 4.4 错误处理

```php
// 所有错误消息必须国际化
return new WP_Error(
    'wpmind_api_error',
    __('API 请求失败', 'wpmind'),
    ['status' => $status_code]
);
```

---

## 5. 使用示例

### 5.1 WPBEE 调用

```php
// WPBEE 执行 Prompt
$result = wpmind_chat($prompt_content, [
    'context'     => 'wpbee_prompt_' . $prompt_id,
    'max_tokens'  => $prompt->max_tokens,
    'temperature' => $prompt->temperature,
]);
```

### 5.2 wpslug 调用

```php
// wpslug 翻译 Slug
$english_slug = wpmind_translate($chinese_title, 'zh', 'en', [
    'context'   => 'wpslug_translation',
    'format'    => 'slug',
    'cache_ttl' => 86400 * 30, // 缓存 30 天
]);
```

### 5.3 第三方插件

```php
// SEO 插件生成元数据
$seo_data = wpmind_chat([
    ['role' => 'system', 'content' => '你是 SEO 专家，返回 JSON'],
    ['role' => 'user', 'content' => "为文章生成 SEO：\n\n{$content}"],
], [
    'context'   => 'seo_generation',
    'json_mode' => true,
]);
```

---

## 6. v2.5.0 实施清单

### Phase 1：核心结构

- [ ] 创建 `includes/API/` 目录
- [ ] 实现 `PublicAPI.php` 主类
- [ ] 实现 `functions.php` 全局函数
- [ ] 在 `wpmind.php` 中加载

### Phase 2：Chat API

- [ ] 实现 `wpmind_chat()` 函数
- [ ] 支持字符串和数组两种输入
- [ ] 集成现有 IntelligentRouter
- [ ] 添加缓存支持
- [ ] 注册相关 Hooks

### Phase 3：翻译 API

- [ ] 实现 `wpmind_translate()` 函数
- [ ] 支持 format: text/slug
- [ ] 添加翻译专用 Prompt
- [ ] 缓存翻译结果

### Phase 4：辅助函数

- [ ] 实现 `wpmind_is_available()`
- [ ] 实现 `wpmind_get_status()`
- [ ] 实现 `wpmind_generate_image()`

### Phase 5：测试

- [ ] 单元测试
- [ ] wpslug 集成测试
- [ ] WPBEE 集成测试

---

*文档结束*
