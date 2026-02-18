# WPMind 公共 API 文档

## 概述

WPMind 提供了一套简洁的公共 API，方便其他插件和主题集成 AI 功能。所有 API 函数都以 `wpmind_` 为前缀，使用前请确保 WPMind 插件已激活。

## 快速开始

### 检查可用性

```php
if (function_exists('wpmind_is_available') && wpmind_is_available()) {
    // WPMind 可用，可以调用 API
    $result = wpmind_chat('Hello');
}
```

### 基本对话

```php
$result = wpmind_chat('写一首关于春天的诗');
if (!is_wp_error($result)) {
    echo $result['content'];
}
```

---

## 核心 API

### wpmind_is_available()

检查 WPMind 是否可用（已配置且有可用的 API Key）。

```php
function wpmind_is_available(): bool
```

**返回值：**
- `true` - WPMind 可用
- `false` - WPMind 不可用

**示例：**
```php
if (wpmind_is_available()) {
    // 安全使用 WPMind
}
```

---

### wpmind_get_status()

获取 WPMind 当前状态信息。

```php
function wpmind_get_status(): array
```

**返回值：**
```php
[
    'available' => true,           // 是否可用
    'provider'  => 'deepseek',     // 当前服务商
    'model'     => 'deepseek-chat',// 当前模型
    'usage'     => [
        'today' => 1250,           // 今日 Token 用量
        'month' => 45800,          // 本月 Token 用量
        'limit' => 100000          // 月度限额
    ]
]
```

**示例：**
```php
$status = wpmind_get_status();
echo "今日已用: " . $status['usage']['today'] . " tokens";
```

---

### wpmind_chat()

与 AI 进行对话。支持简单文本和多轮对话。

```php
function wpmind_chat($messages, array $options = []): array|WP_Error
```

**参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `$messages` | `string\|array` | 是 | 对话内容 |
| `$options` | `array` | 否 | 可选配置 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `context` | `string` | `'general'` | 上下文标识，用于统计和过滤 |
| `max_tokens` | `int` | `2048` | 最大输出 Token 数 |
| `temperature` | `float` | `0.7` | 创意度 (0-2)，越高越随机 |
| `provider` | `string` | `'auto'` | 服务商，auto 自动选择 |
| `cache_ttl` | `int` | `0` | 缓存时间（秒），0 不缓存 |

**返回值：**
```php
[
    'content'  => 'AI 生成的内容',
    'provider' => 'deepseek',
    'model'    => 'deepseek-chat',
    'usage'    => [
        'prompt_tokens'     => 50,
        'completion_tokens' => 200,
        'total_tokens'      => 250
    ]
]
```

**示例 1 - 简单对话：**
```php
$result = wpmind_chat('解释什么是 WordPress Hook');
if (!is_wp_error($result)) {
    echo $result['content'];
}
```

**示例 2 - 带系统提示：**
```php
$result = wpmind_chat([
    ['role' => 'system', 'content' => '你是一个 SEO 专家'],
    ['role' => 'user', 'content' => '如何优化文章标题？'],
]);
```

**示例 3 - 自定义参数：**
```php
$result = wpmind_chat('生成 5 个文章标题创意', [
    'context'     => 'title_generation',
    'max_tokens'  => 500,
    'temperature' => 1.0,  // 更有创意
    'cache_ttl'   => 3600, // 缓存 1 小时
]);
```

---

### wpmind_translate()

翻译文本到指定语言。

```php
function wpmind_translate(
    string $text, 
    string $from = 'auto', 
    string $to = 'en', 
    array $options = []
): string|WP_Error
```

**参数：**

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `$text` | `string` | - | 要翻译的文本 |
| `$from` | `string` | `'auto'` | 源语言代码 |
| `$to` | `string` | `'en'` | 目标语言代码 |
| `$options` | `array` | `[]` | 可选配置 |

**支持的语言代码：**

| 代码 | 语言 |
|------|------|
| `auto` | 自动检测 |
| `zh` | 中文（简体） |
| `zh-TW` | 中文（繁体） |
| `en` | 英语 |
| `ja` | 日语 |
| `ko` | 韩语 |
| `fr` | 法语 |
| `de` | 德语 |
| `es` | 西班牙语 |
| `ru` | 俄语 |
| `ar` | 阿拉伯语 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `context` | `string` | `'translation'` | 上下文标识 |
| `format` | `string` | `'text'` | 输出格式：`text` / `slug` |
| `hint` | `string` | `''` | 翻译提示 |
| `cache_ttl` | `int` | `86400` | 缓存时间（默认1天） |

**示例 1 - 基本翻译：**
```php
$english = wpmind_translate('你好世界', 'zh', 'en');
// 返回: "Hello, World"
```

**示例 2 - 生成 Slug：**
```php
$slug = wpmind_translate('WordPress 性能优化指南', 'zh', 'en', [
    'format' => 'slug',
]);
// 返回: "wordpress-performance-optimization-guide"
```

**示例 3 - 多语言翻译：**
```php
// 翻译为日语
$japanese = wpmind_translate('Hello World', 'en', 'ja');
// 返回: "こんにちは世界"

// 翻译为韩语
$korean = wpmind_translate('Hello World', 'en', 'ko');
// 返回: "안녕하세요 세계"
```

---

### wpmind_pinyin()

将中文转换为语义化拼音（按词分隔）。

```php
function wpmind_pinyin(string $text, array $options = []): string|WP_Error
```

**参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `$text` | `string` | 是 | 中文文本 |
| `$options` | `array` | 否 | 可选配置 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `context` | `string` | `'pinyin_conversion'` | 上下文标识 |
| `cache_ttl` | `int` | `604800` | 缓存时间（默认7天） |

**特点：**
- 按词语分隔，不是按字分隔
- 保留英文和数字
- 全部小写，无声调

**对比普通拼音：**

| 输入 | 普通拼音 | 语义化拼音 |
|------|----------|------------|
| 你好世界 | `ni-hao-shi-jie` | `nihao-shijie` |
| 人工智能 | `ren-gong-zhi-neng` | `rengong-zhineng` |
| WordPress教程 | `WordPress-jiao-cheng` | `WordPress-jiaocheng` |

**示例：**
```php
$pinyin = wpmind_pinyin('你好世界');
// 返回: "nihao-shijie"

$pinyin = wpmind_pinyin('WordPress性能优化指南');
// 返回: "WordPress-xingneng-youhua-zhinan"
```

---

### wpmind_generate_image()

生成 AI 图像。

```php
function wpmind_generate_image(string $prompt, array $options = []): array|WP_Error
```

**参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `$prompt` | `string` | 是 | 图像描述 |
| `$options` | `array` | 否 | 可选配置 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `size` | `string` | `'1024x1024'` | 图像尺寸 |
| `quality` | `string` | `'standard'` | 质量：`standard` / `hd` |
| `style` | `string` | `'natural'` | 风格：`natural` / `vivid` |
| `provider` | `string` | `'auto'` | 服务商 |
| `return_format` | `string` | `'url'` | 返回格式：`url` / `attachment_id` |

**支持的尺寸：**
- `1024x1024` (正方形)
- `1792x1024` (横向)
- `1024x1792` (纵向)

**返回值：**
```php
[
    'url'            => 'https://...',  // 图像 URL
    'provider'       => 'flux',         // 使用的服务商
    'revised_prompt' => '...'           // 修改后的提示词（部分服务商）
]
```

**示例：**
```php
$image = wpmind_generate_image('一只可爱的熊猫在竹林中', [
    'size'    => '1024x1024',
    'quality' => 'hd',
]);

if (!is_wp_error($image)) {
    echo '<img src="' . esc_url($image['url']) . '">';
}
```

---

### wpmind_summarize()

生成文本摘要。

```php
function wpmind_summarize(string $text, array $options = []): string|WP_Error
```

**参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `$text` | `string` | 是 | 要摘要的文本 |
| `$options` | `array` | 否 | 可选配置 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `max_length` | `int` | `200` | 摘要最大字数 |
| `style` | `string` | `'concise'` | 风格：`concise` / `detailed` |
| `language` | `string` | `'auto'` | 输出语言 |
| `cache_ttl` | `int` | `86400` | 缓存时间 |

**示例：**
```php
$summary = wpmind_summarize($long_article, [
    'max_length' => 150,
    'style'      => 'concise',
]);
```

---

### wpmind_extract_keywords()

提取文本关键词。

```php
function wpmind_extract_keywords(string $text, array $options = []): array|WP_Error
```

**参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `$text` | `string` | 是 | 要提取关键词的文本 |
| `$options` | `array` | 否 | 可选配置 |

**$options 配置：**

| 键 | 类型 | 默认值 | 说明 |
|----|------|--------|------|
| `count` | `int` | `5` | 关键词数量 |
| `language` | `string` | `'auto'` | 语言 |

**返回值：**
```php
['WordPress', '性能', '优化', '缓存', '速度']
```

**示例：**
```php
$keywords = wpmind_extract_keywords($article_content, [
    'count' => 10,
]);

if (!is_wp_error($keywords)) {
    foreach ($keywords as $keyword) {
        echo "<span class='tag'>$keyword</span>";
    }
}
```

---

## 错误处理

所有 API 函数在失败时返回 `WP_Error` 对象。推荐使用以下模式：

```php
$result = wpmind_chat('Hello');

if (is_wp_error($result)) {
    // 处理错误
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    
    error_log("WPMind Error [$error_code]: $error_message");
    
    // 使用回退逻辑
    return fallback_function();
}

// 正常使用结果
echo $result['content'];
```

**常见错误代码：**

| 错误代码 | 说明 |
|----------|------|
| `wpmind_not_available` | WPMind 未激活或未配置 |
| `wpmind_api_error` | API 调用失败 |
| `wpmind_rate_limit` | 请求频率超限 |
| `wpmind_budget_exceeded` | 预算超限 |
| `wpmind_timeout` | 请求超时 |

---

## Hooks（过滤器和动作）

### 过滤器

#### wpmind_chat_args

修改 chat 请求参数。

```php
add_filter('wpmind_chat_args', function($args, $context) {
    // 为 SEO 场景降低创意度
    if ($context === 'seo_generation') {
        $args['options']['temperature'] = 0.3;
    }
    return $args;
}, 10, 2);
```

#### wpmind_chat_response

处理 chat 响应。

```php
add_filter('wpmind_chat_response', function($response, $messages, $context) {
    // 记录日志
    if ($context === 'customer_support') {
        log_support_response($response);
    }
    return $response;
}, 10, 3);
```

#### wpmind_translate_args

修改翻译请求参数。

```php
add_filter('wpmind_translate_args', function($args, $context) {
    return $args;
}, 10, 2);
```

### 动作

#### wpmind_before_request

API 请求前触发。

```php
add_action('wpmind_before_request', function($type, $args, $context) {
    // 记录请求
}, 10, 3);
```

#### wpmind_after_request

API 请求后触发。

```php
add_action('wpmind_after_request', function($type, $response, $context) {
    // 统计用量
}, 10, 3);
```

---

## 最佳实践

### 1. 始终检查可用性

```php
if (!function_exists('wpmind_is_available') || !wpmind_is_available()) {
    return $fallback_result;
}
```

### 2. 使用上下文标识

```php
$result = wpmind_chat($prompt, [
    'context' => 'my_plugin_feature_name',
]);
```

这有助于：
- 统计不同功能的用量
- 应用特定的过滤器
- 调试和日志分析

### 3. 合理使用缓存

```php
// 翻译结果很少变化，适合长期缓存
$slug = wpmind_translate($title, 'zh', 'en', [
    'format'    => 'slug',
    'cache_ttl' => 604800, // 7 天
]);

// 实时对话不应缓存
$chat = wpmind_chat($user_input, [
    'cache_ttl' => 0,
]);
```

### 4. 处理错误和超时

```php
$result = wpmind_translate($text, 'zh', 'en', [
    'timeout' => 10, // 10 秒超时
]);

if (is_wp_error($result)) {
    // 使用回退方案
    return my_fallback_translate($text);
}
```

### 5. 限制输入长度

```php
$max_length = 5000;
if (mb_strlen($text) > $max_length) {
    $text = mb_substr($text, 0, $max_length);
}
```

---

## 集成示例

### 示例 1：自动生成文章摘要

```php
add_action('save_post', function($post_id) {
    // 检查条件
    if (wp_is_post_revision($post_id) || !wpmind_is_available()) {
        return;
    }
    
    $post = get_post($post_id);
    $content = wp_strip_all_tags($post->post_content);
    
    // 生成摘要
    $excerpt = wpmind_summarize($content, [
        'max_length' => 150,
        'context'    => 'auto_excerpt',
    ]);
    
    if (!is_wp_error($excerpt)) {
        wp_update_post([
            'ID'           => $post_id,
            'post_excerpt' => $excerpt,
        ]);
    }
});
```

### 示例 2：自动翻译标题为 Slug

```php
add_filter('wp_insert_post_data', function($data, $postarr) {
    if (empty($data['post_name']) && !empty($data['post_title'])) {
        if (wpmind_is_available()) {
            $slug = wpmind_translate($data['post_title'], 'auto', 'en', [
                'format'  => 'slug',
                'context' => 'slug_generation',
            ]);
            
            if (!is_wp_error($slug)) {
                $data['post_name'] = $slug;
            }
        }
    }
    return $data;
}, 10, 2);
```

### 示例 3：智能评论审核

```php
add_filter('pre_comment_approved', function($approved, $commentdata) {
    if (!wpmind_is_available()) {
        return $approved;
    }
    
    $result = wpmind_chat([
        ['role' => 'system', 'content' => '你是一个评论审核员。判断以下评论是否为垃圾评论，只回复 "spam" 或 "ok"。'],
        ['role' => 'user', 'content' => $commentdata['comment_content']],
    ], [
        'context'     => 'spam_detection',
        'max_tokens'  => 10,
        'temperature' => 0,
    ]);
    
    if (!is_wp_error($result) && strtolower(trim($result['content'])) === 'spam') {
        return 'spam';
    }
    
    return $approved;
}, 10, 2);
```

---

## 版本历史

| 版本 | 日期 | 变更 |
|------|------|------|
| 2.5.0 | 2026-02-02 | 添加 `wpmind_pinyin()` 语义化拼音函数 |
| 2.4.0 | 2026-01-15 | 添加图像生成 API |
| 2.3.0 | 2026-01-01 | 添加翻译 API |
| 2.0.0 | 2025-12-01 | 公共 API 初始版本 |

---

## 技术支持

- **文档**：https://wpcy.com/mind/docs
- **GitHub**：https://github.com/flavor/wpmind
- **问题反馈**：https://github.com/flavor/wpmind/issues
