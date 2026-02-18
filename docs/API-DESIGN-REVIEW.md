# WPMind API 设计专家审查报告

> 审查日期：2026-02-01
> 审查工具：Gemini CLI
> 审查角色：WordPress 核心开发专家 + AI/LLM 工程专家

---

## 角色 1：WordPress 核心开发专家审查

### 1. 返回值设计问题

**问题**：`wpmind_generate_image()` 返回 `array` 不符合 WP 惯例

**改进建议**：
```php
// WP 开发者期望直接获得 Attachment ID
$options = [
    'return_format' => 'url' | 'id' | 'object',
];

// 建议增加：
function wpmind_create_image_attachment($prompt, $options = []): int|WP_Error;
```

---

### 2. Hooks 设计太粗

**问题**：无法区分不同调用场景

**改进建议**：

```php
// ❌ 当前（无法区分场景）
apply_filters('wpmind_chat', $prompt, $options);

// ✅ 改进（带 context）
apply_filters('wpmind_chat_args', $args, $context, $original_prompt);

// ✅ 新增：结果过滤
apply_filters('wpmind_chat_response', $response, $args, $context);

// ✅ 新增：模型选择过滤（VIP 用户用 GPT-4）
apply_filters('wpmind_select_model', $model, $context, $user_id);
```

**必须在 `$options` 中强制要求传入 context**：
```php
wpmind_chat($prompt, [
    'context' => 'seo_title_generation', // 用于 Hook 区分
]);
```

---

### 3. 性能与异步处理 ⚠️ 严重

**问题**：`wpmind_batch` 和 `wpmind_embed` 同步运行会导致 PHP 超时

**改进建议**：

```php
// ❌ 当前：同步返回结果
$results = wpmind_batch($items, $prompt);

// ✅ 改进：返回 Job ID，后台异步处理
$job_id = wpmind_batch_queue($items, $prompt, [
    'callback' => 'my_callback_function',
]);

// 使用 Action Scheduler（WooCommerce 标准库）
```

**缓存机制**：
```php
wpmind_chat($prompt, [
    'cache_ttl' => 3600, // 缓存 1 小时
]);
```

---

### 4. 核心集成与兼容性

| 维度 | 要求 |
|-----|------|
| **HTTP 传输** | 必须使用 `wp_safe_remote_post` |
| **多站点** | 检测并回退 `get_site_option` / `get_option` |
| **国际化** | `WP_Error` 消息必须使用 `__()` |
| **对象缓存** | 集成 `set_transient` |

---

## 角色 2：AI/LLM 工程专家审查

### 1. 致命缺失：Function Calling ⚠️

**问题**：只支持纯文本对话，无法构建 AI Agent

**改进建议**：
```php
wpmind_chat($messages, [
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_product_stock',
                'description' => '查询产品库存',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
]);

// 返回值需要处理 tool_calls
[
    'content' => null,
    'tool_calls' => [
        [
            'id' => 'call_xxx',
            'function' => [
                'name' => 'get_product_stock',
                'arguments' => '{"product_id": 123}',
            ],
        ],
    ],
]
```

---

### 2. 多模态支持不足

**问题**：`$prompt` 只支持字符串，无法传图片

**改进建议**：
```php
// 支持多模态消息
wpmind_chat([
    [
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => '这张图片里有什么？'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://...']],
        ],
    ],
]);
```

---

### 3. 对话上下文管理

**问题**：`wpmind_chat($prompt)` 暗示单轮指令模式

**改进建议**：
```php
// API 签名应改为 Messages List
function wpmind_chat(array $messages, array $options = []);

// 支持多轮对话
wpmind_chat([
    ['role' => 'system', 'content' => '你是一个 SEO 专家'],
    ['role' => 'user', 'content' => '第一个问题'],
    ['role' => 'assistant', 'content' => 'AI 回复'],
    ['role' => 'user', 'content' => '跟进问题'],
]);
```

---

### 4. 流式传输挑战

**问题**：PHP 缓冲输出，`wpmind_stream` 可能无效

**改进建议**：
```php
// 提供 SSE Helper
function wpmind_stream_sse($prompt, $options = []) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // Nginx
    
    wpmind_stream($prompt, function($chunk) {
        echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
        ob_flush();
        flush();
    });
}
```

---

### 5. 结构化输出重试

**问题**：AI 返回 JSON 格式错误时直接报错

**改进建议**：
```php
// 内部自动重试 + 自我修正
function wpmind_structured($messages, $schema, $options = []) {
    $max_retries = $options['retries'] ?? 3;
    
    for ($i = 0; $i < $max_retries; $i++) {
        $result = $this->chat($messages, ['json_mode' => true]);
        
        $parsed = json_decode($result['content'], true);
        if ($parsed && $this->validate_schema($parsed, $schema)) {
            return $parsed;
        }
        
        // 自我修正：把错误喂回 AI
        $messages[] = [
            'role' => 'user',
            'content' => "JSON 格式错误，请重新生成：" . json_last_error_msg(),
        ];
    }
    
    return new WP_Error('json_parse_failed', '...');
}
```

---

### 6. Token 管理

**建议新增**：
```php
// Token 计数
function wpmind_count_tokens($text_or_messages): int;

// 在响应中暴露用量
[
    'content' => '...',
    'usage' => [
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
    ],
]
```

---

## 综合修订后的 API 签名

### 基础 API (v2.5.0)

```php
// 核心对话（支持多轮、多模态）
function wpmind_chat(
    array|string $messages,  // 字符串或 Messages 数组
    array $options = []
): array|WP_Error;

// 翻译
function wpmind_translate(
    string $text,
    string $from = 'auto',
    string $to = 'en',
    array $options = []
): string|WP_Error;

// 图像（返回 Attachment ID）
function wpmind_create_image_attachment(
    string $prompt,
    array $options = []
): int|WP_Error;
```

### 增强 API (v2.6.0)

```php
// 结构化（内置重试）
function wpmind_structured(
    array|string $messages,
    array $json_schema,
    array $options = []
): array|WP_Error;

// 异步批量（返回 Job ID）
function wpmind_batch_queue(
    array $items,
    string $prompt_template,
    array $options = []
): int|WP_Error;

// 流式 SSE
function wpmind_stream_sse(
    array|string $messages,
    array $options = []
): void;

// Token 计数
function wpmind_count_tokens(
    string|array $content
): int;
```

---

## 必须新增的 Hooks

```php
// 参数过滤（带 context）
apply_filters('wpmind_chat_args', $args, $context, $original_messages);

// 结果过滤
apply_filters('wpmind_chat_response', $response, $args, $context);

// 模型选择
apply_filters('wpmind_select_model', $model, $context, $user_id);

// 缓存键
apply_filters('wpmind_cache_key', $key, $type, $args);

// 请求前
do_action('wpmind_before_request', $type, $args, $context);

// 请求后（含用量）
do_action('wpmind_after_request', $type, $response, $args, $usage);

// 错误
do_action('wpmind_error', $error, $type, $args);
```

---

## 优先级总结

### 🔴 必须实现（v2.5.0）

| 改进项 | 原因 |
|-------|------|
| Messages 数组支持 | 多轮对话基础 |
| Context 强制参数 | Hook 区分场景 |
| 响应结果 Filter | 可扩展性 |
| WP_Error 国际化 | WordPress 标准 |

### 🟡 应该实现（v2.6.0）

| 改进项 | 原因 |
|-------|------|
| Function Calling / Tools | AI Agent 核心 |
| 异步批量（Action Scheduler） | 性能 |
| 结构化输出自动重试 | 可靠性 |
| Token 计数函数 | 成本控制 |

### 🟢 可以延后（v2.7.0+）

| 改进项 | 原因 |
|-------|------|
| 多模态（图片输入） | 高级场景 |
| 向量相似度计算 | RAG 场景 |
| wpmind_create_image_attachment | 便捷函数 |

---

*专家审查报告结束*
