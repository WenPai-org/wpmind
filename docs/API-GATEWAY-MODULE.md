# WPMind API Gateway 模块设计方案

> Phase 4.0 核心模块 — 将 WPMind 变为自托管的 OpenAI 兼容 AI API 网关

## 定位

任何 WordPress 站点一键变成 AI API 网关。用户只需将 base URL 改为
`https://yoursite.com/wp-json/mind/v1/`，所有支持 OpenAI 格式的工具即可直接使用。

## 端点设计

```
POST /wp-json/mind/v1/chat/completions       # 对话（含 streaming）
POST /wp-json/mind/v1/embeddings             # 向量化
POST /wp-json/mind/v1/audio/transcriptions   # 语音转文字
POST /wp-json/mind/v1/audio/speech           # 文字转语音
POST /wp-json/mind/v1/images/generations     # 图片生成
GET  /wp-json/mind/v1/models                 # 列出可用模型
GET  /wp-json/mind/v1/status                 # 网关状态（WPMind 扩展）
```

### 为什么用 `mind/v1` 而不是 `wpmind/v1`

- 更短的 URL，对标 `openai/v1`
- 品牌简洁性
- 与 WPMind 内部管理 API 分离（管理 API 可保留 `wpmind/v1`）

## 请求/响应格式

完全兼容 OpenAI Chat Completions API：

```json
// 请求
{
  "model": "deepseek-chat",
  "messages": [
    {"role": "system", "content": "You are a helpful assistant."},
    {"role": "user", "content": "Hello"}
  ],
  "temperature": 0.7,
  "max_tokens": 1000,
  "stream": false
}

// 响应
{
  "id": "wpmind-xxxx",
  "object": "chat.completion",
  "created": 1700000000,
  "model": "deepseek-chat",
  "choices": [{
    "index": 0,
    "message": {"role": "assistant", "content": "..."},
    "finish_reason": "stop"
  }],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 20,
    "total_tokens": 30
  }
}
```

## 模型映射

```
请求 model 名          →  WPMind 路由目标
─────────────────────────────────────────
auto                   →  智能路由（WPMind 独有）
deepseek-chat          →  DeepSeek provider
qwen-turbo             →  Qwen provider
doubao-seed-*          →  Doubao provider
glm-4                  →  Zhipu provider
gpt-4o                 →  OpenAI provider（如已配置）
claude-3-*             →  Anthropic provider（如已配置）
```

后台可配置模型别名映射，例如 `gpt-4o → deepseek-chat`。

## 认证方案

```
Authorization: Bearer sk_mind_xxxxxxxxxxxxxxxxxxxx
```

### API Key 管理

- 后台 GUI 生成/吊销 API Key
- 每个 Key 可绑定：
  - 允许的 Provider 列表
  - 速率限制（RPM / TPM）
  - 月度预算上限
  - IP 白名单（可选）
  - 过期时间（可选）
- 同时支持 WordPress Application Passwords（同站点插件调用）
- 同时支持 WordPress cookie 认证（后台 AJAX 调用）

### Key 存储

- API Key 哈希存储（类似 WordPress password hash）
- 明文 Key 仅在创建时展示一次
- 存储在 `wp_wpmind_api_keys` 自定义表

## 速率限制

- 基于 API Key 的滑动窗口限流
- 默认限制：60 RPM / 100,000 TPM
- 后台可按 Key 自定义
- 超限返回 HTTP 429 + `Retry-After` 头
- 使用 WordPress transient 或对象缓存存储计数器

## Streaming (SSE)

```
POST /wp-json/mind/v1/chat/completions
{"stream": true, ...}

// 响应 Content-Type: text/event-stream
data: {"id":"...","choices":[{"delta":{"content":"Hello"},...}]}

data: {"id":"...","choices":[{"delta":{},"finish_reason":"stop",...}]}

data: [DONE]
```

### WordPress SSE 注意事项

- 需要在请求处理前关闭输出缓冲：`ob_end_clean()`
- 设置 `header('X-Accel-Buffering: no')` 禁用 Nginx 缓冲
- 设置合理的 `max_execution_time`
- WPMind 已有 stream 实现可复用

## 模块结构

```
modules/api-gateway/
├── module.json
├── ApiGatewayModule.php          # 模块入口 + hook 注册
├── includes/
│   ├── RestController.php        # REST API 路由注册
│   ├── ChatCompletionsEndpoint.php
│   ├── EmbeddingsEndpoint.php
│   ├── ModelsEndpoint.php
│   ├── ApiKeyManager.php         # API Key CRUD + 验证
│   ├── RateLimiter.php           # 滑动窗口限流
│   ├── RequestTransformer.php    # OpenAI 格式 → WPMind 内部格式
│   ├── ResponseTransformer.php   # WPMind 内部格式 → OpenAI 格式
│   └── StreamHandler.php         # SSE streaming 处理
└── templates/
    └── settings.php              # 后台设置页
```

## 与现有架构的关系

```
外部请求 (OpenAI 格式)
    ↓
API Gateway 模块 (认证 + 限流 + 格式转换)
    ↓
PublicAPI Facade (现有)
    ↓
Service 层 (ChatService / EmbeddingService / ...)
    ↓
路由引擎 + Provider 适配器
    ↓
上游 AI API
```

API Gateway 模块只负责 HTTP 入口层，不重复实现任何 AI 逻辑。

## 竞品对比

| 能力 | OpenRouter | LiteLLM | One API | **WPMind** |
|------|-----------|---------|---------|-----------|
| 自托管 | ❌ | ✅ | ✅ | ✅ |
| 零额外基础设施 | - | ❌ Python | ❌ Go | **✅ WordPress** |
| 智能路由 | ✅ | ✅ | ❌ | **✅** |
| 成本控制 | ❌ | 部分 | 部分 | **✅ 完整** |
| 精确缓存 | ❌ | ❌ | ❌ | **✅** |
| WordPress 生态 | ❌ | ❌ | ❌ | **✅** |
| GUI 管理 | ✅ | ❌ | ✅ | **✅** |
| OpenAI 兼容 | ✅ | ✅ | ✅ | **✅** |

## 待讨论事项

- [ ] 是否需要支持 Function Calling / Tool Use？
- [ ] 是否需要支持 Vision（图片输入）？
- [ ] API Key 是否需要支持多租户（不同用户不同 Key）？
- [ ] 是否需要 Webhook 回调（异步任务完成通知）？
- [ ] 是否作为免费功能还是 Pro 功能？
- [ ] 日志/审计：是否记录所有 API 请求？

---

## Codex 审查反馈 (2026-02-08)

> 审查结论：方案方向正确，但从"功能草案"到"生产级 OpenAI 兼容网关"还需补充以下 P0 设计项。

### P0 高优先级风险

| # | 风险 | 说明 |
|---|------|------|
| 1 | OpenAI 兼容定义过宽 | 文档只覆盖少量端点和示例，容易出现"SDK 能连上但行为不一致" |
| 2 | 认证边界未定义 | Bearer Key / Application Password / Cookie 同时支持但无 CSRF 防护策略 |
| 3 | 限流原子性缺失 | transient/object cache 无原子性保证，高并发下可被绕过 |
| 4 | API Key 校验性能 | 仅哈希存储无可索引的 key_id/prefix，认证会 O(n) 校验 |
| 5 | SSE 长连接风险 | 未考虑 PHP-FPM worker 占用、客户端断开取消、代理层超时 |

### 1. 安全盲点

- **SSRF 防护**: 强制上游域名白名单 + DNS 二次校验，拒绝私网/IP/metadata 地址
- **认证分层**: 外部 API 仅 Bearer Key；Cookie 认证仅限管理端并强制 `X-WP-Nonce`
- **Key 格式**: `sk_mind_{key_id}_{secret}`，DB 存 `key_id + secret_hash + prefix`，常数时间定位
- **输入保护**: 每个端点定义 JSON Schema、请求体大小上限、max_tokens 上限
- **错误脱敏**: 统一错误映射，禁止透传上游 raw error、堆栈、内部路由信息
- **status 端点**: 默认仅返回健康状态，不暴露 provider 名单、版本、限流阈值

### 2. WordPress 特有限制

- **PHP-FPM worker 占用**: SSE 长连接占进程；需全局并发阈值 + 每 key 并发阈值 + 超限快速失败
- **限流存储**: 无 Redis 时 transient 落库有锁争用；建议 Redis 原子 `INCR+EXPIRE`，降级用 transient
- **内存压力**: WP REST 全量载入 body；音频/图片接口优先 `multipart/form-data` 流式处理
- **代理链路**: Nginx/Cloudflare 可能缓冲 SSE；需部署文档写明 `X-Accel-Buffering: no`、`proxy_read_timeout`、gzip off
- **Authorization 头**: Apache/FastCGI 可能吞 `Authorization`；需 `.htaccess` rewrite 或 `SetEnvIf`

### 3. OpenAI 兼容性细节（最容易遗漏）

- **错误格式**: 必须返回 `{"error":{"message","type","param","code"}}` + 正确 HTTP 状态码
- **新端点**: 补 `POST /responses`（新版 SDK 常用）和 `GET /models/{id}`
- **Chat 参数**: `tools/tool_choice/response_format/json_schema/seed/stream_options.include_usage` 要么支持要么明确 reject
- **Streaming chunk**: `object=chat.completion.chunk`、`delta.role/content/tool_calls`、`finish_reason`、尾部 `[DONE]`
- **音频响应**: `audio/speech` 返回二进制音频而非 JSON；转写支持 multipart 上传
- **响应头**: 返回 `x-request-id`，补充限流头 `x-ratelimit-remaining/reset`

### 4. 运维考量

- **结构化日志**: `request_id / key_id / model / provider / status / latency / tokens / cost / cache_hit`，默认脱敏
- **监控 SLI/SLO**: 成功率、P95 延迟、429 比例、上游错误率 + 告警阈值
- **调试追踪**: 按 `request_id` 追踪 + 可控采样率，避免全量记录引发隐私风险
- **DB 迁移**: schema 版本号 + 幂等迁移 + 失败回滚策略
- **操作审计**: Key 创建/吊销、限额变更、预算超限等操作可追溯

### 5. 架构风险

- **中间件链**: 需定义统一 pipeline `auth → budget → quota → request_transform → route → response_transform → error → log`（8 阶段），避免各端点重复逻辑
- **DB schema 扩展**: 至少 3 张表：`api_keys`、`api_key_usage`（聚合计费）、`api_audit_log`（操作审计）
- **预算窗口**: 按 UTC 月窗口实时计算，不依赖定时任务重置
- **Key 元数据缓存**: 每请求查库 + hash 校验开销大；需本地短 TTL 缓存 key 元数据
- **协议契约测试**: 转换层依赖内部 DTO，内部改字段会破坏外部兼容；需加契约测试

### 6. 其他容易忽略

- **合规与隐私**: 是否存储 prompt/response、保留多久、是否可关闭日志
- **反滥用**: IP 信誉、突发熔断、异常模型调用检测
- **取消传播**: 客户端断开时终止上游请求，避免白白消耗 token
- **兼容矩阵**: 明确"完全兼容/部分兼容/不支持"的功能列表，比"完全兼容"更可执行

---

## P0 解决方案设计 (Codex 第二轮评审 2026-02-08)

> 基于第一轮审查的 5 个 P0 风险，Codex 给出了伪代码级实现方案（130K tokens 分析）。

### 模块骨架

```
modules/api-gateway/
├── ApiGatewayModule.php              # 模块入口 (implements ModuleInterface)
├── module.json
├── includes/
│   ├── Auth/
│   │   ├── ApiKeyManager.php         # Key 生命周期管理
│   │   ├── ApiKeyRepository.php      # DB CRUD
│   │   └── ApiKeyHasher.php          # 哈希 + 常数时间校验
│   ├── Pipeline/
│   │   ├── GatewayStageInterface.php # 中间件接口
│   │   ├── GatewayPipeline.php       # 流水线编排
│   │   ├── GatewayRequestContext.php  # 请求上下文 DTO
│   │   ├── AuthMiddleware.php
│   │   ├── QuotaMiddleware.php
│   │   ├── RouteMiddleware.php
│   │   ├── TransformMiddleware.php
│   │   ├── ErrorMiddleware.php
│   │   └── LogMiddleware.php
│   ├── RateLimit/
│   │   ├── RateStoreInterface.php    # 限流存储抽象
│   │   ├── RedisRateStore.php        # Redis 滑动窗口
│   │   ├── TransientRateStore.php    # Transient 降级
│   │   └── RateLimiter.php           # 统一限流器
│   ├── Stream/
│   │   ├── SseConcurrencyGuard.php   # 并发槽位管理
│   │   ├── SseStreamController.php   # SSE 输出控制
│   │   ├── UpstreamStreamClient.php  # 上游 cURL 流
│   │   └── CancellationToken.php     # 取消令牌
│   ├── Transform/
│   │   ├── OpenAIErrorMapper.php     # 错误格式映射
│   │   ├── RequestTransformer.php    # OpenAI → WPMind
│   │   └── ResponseTransformer.php   # WPMind → OpenAI
│   ├── Logging/
│   │   └── AuditLogger.php           # 审计日志
│   ├── RestController.php            # REST 路由注册
│   ├── GatewayRequestSchema.php      # 参数 Schema 定义
│   └── SchemaManager.php             # DB 表创建/迁移
└── templates/
    └── settings.php                  # 后台设置页
```

### P0-1: API Key 设计

#### 密钥格式

```
sk_mind_{key_id}_{secret}
         ^^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
         12位      43位 base64url (32字节随机数)
         base32

示例: sk_mind_A1B2C3D4E5F6_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

- `key_id`: 12 字符 base32，可索引、可读
- `secret`: 32 字节随机数 base64url 编码（43 字符）
- `key_prefix`: secret 前 8 字符，后台显示用（`sk_mind_...xxxx****`）
- 明文仅创建时展示一次

#### DB Schema (3 表)

```sql
-- 1. API Keys 主表
CREATE TABLE {$wpdb->prefix}wpmind_api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    key_prefix CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    secret_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    secret_salt CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT '',
    owner_user_id BIGINT UNSIGNED NULL,
    allowed_providers LONGTEXT NULL,
    rpm_limit INT UNSIGNED NOT NULL DEFAULT 60,
    tpm_limit INT UNSIGNED NOT NULL DEFAULT 100000,
    concurrency_limit SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    monthly_budget_usd DECIMAL(12,6) NOT NULL DEFAULT 0.000000,
    ip_whitelist LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_key_id (key_id),
    KEY idx_status_expires (status, expires_at),
    KEY idx_owner (owner_user_id)
) {$charset_collate};

-- 2. 用量聚合表 (按月窗口)
CREATE TABLE {$wpdb->prefix}wpmind_api_key_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_month CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_cost_usd DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_key_month (key_id, window_month),
    KEY idx_window_month (window_month)
) {$charset_collate};

-- 3. 审计日志表
CREATE TABLE {$wpdb->prefix}wpmind_api_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(40) NOT NULL,
    key_id CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    user_agent VARCHAR(255) NULL,
    detail_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_event_time (event_type, created_at),
    KEY idx_key_time (key_id, created_at),
    KEY idx_request_id (request_id)
) {$charset_collate};
```

#### 核心类签名

```php
final class ApiKeyManager {
    public function create_api_key(array $attrs): array {}
    public function authenticate_bearer_header(string $authorization, string $client_ip): ApiKeyAuthResult|WP_Error {}
    private function parse_api_key(string $raw_key): ?array {}
    private function is_key_expired(array $row): bool {}
    private function is_ip_allowed(array $row, string $client_ip): bool {}
}

final class ApiKeyRepository {
    public function insert_key(array $row): int {}
    public function find_by_key_id(string $key_id): ?array {}
    public function update_last_used(string $key_id, string $request_id): void {}
    public function revoke_key(string $key_id, int $actor_user_id, string $reason): void {}
}

final class ApiKeyHasher {
    public function make_salt_hex(): string {}
    public function hash_secret(string $secret, string $salt_hex): string {}
    public function constant_time_verify(string $secret, string $salt_hex, string $expected): bool {}
}
```

#### 常数时间认证流程

```php
public function authenticate_bearer_header(string $header, string $ip): ApiKeyAuthResult|WP_Error {
    $raw_key = $this->extract_bearer_token($header);
    $parts = $this->parse_api_key($raw_key);
    if (null === $parts) {
        return new WP_Error('wpmind_auth_invalid_key_format', 'Invalid API key format.');
    }

    $row = $this->repository->find_by_key_id($parts['key_id']);

    // 防 timing 枚举：无论 row 存在与否都走同一长度 hash_equals
    $salt_hex = $row['secret_salt'] ?? self::DUMMY_SALT_HEX;
    $expected = $row['secret_hash'] ?? self::DUMMY_HASH_HEX;
    $actual   = $this->hasher->hash_secret($parts['secret'], $salt_hex);
    $secret_ok = hash_equals($expected, $actual);

    $row_ok = !empty($row)
        && 'active' === $row['status']
        && !$this->is_key_expired($row)
        && $this->is_ip_allowed($row, $ip);

    if (!$secret_ok || !$row_ok) {
        return new WP_Error('wpmind_auth_invalid_api_key', 'Incorrect API key provided.');
    }

    return new ApiKeyAuthResult($row);
}
```

### P0-2: 中间件链架构

#### 核心接口

```php
interface GatewayStageInterface {
    public function process(GatewayRequestContext $context): void;
}
```

#### Pipeline 编排

```php
final class GatewayPipeline {

    public function __construct(
        private AuthMiddleware $auth_middleware,
        private QuotaMiddleware $quota_middleware,
        private RouteMiddleware $route_middleware,
        private TransformMiddleware $transform_middleware,
        private ErrorMiddleware $error_middleware,
        private LogMiddleware $log_middleware
    ) {}

    public function handle(string $operation, WP_REST_Request $request): WP_REST_Response {
        $context = GatewayRequestContext::from_rest_request($operation, $request);

        try {
            $this->auth_middleware->process($context);
            $this->quota_middleware->process($context);
            $this->route_middleware->process($context);
            $this->transform_middleware->process($context);
        } catch (\Throwable $e) {
            $context->set_exception($e);
        }

        $this->error_middleware->process($context); // 统一 OpenAI error envelope
        $this->log_middleware->process($context);   // finally 语义，始终执行

        return $context->to_rest_response();
    }
}
```

#### REST 路由注册

```php
final class RestController {

    public function register_routes(): void {
        register_rest_route('mind/v1', '/chat/completions', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'dispatch_chat_completions'],
            'permission_callback' => '__return_true', // auth 在 pipeline 内处理
            'args'                => GatewayRequestSchema::chat_completions(),
        ]);
        // embeddings / audio/* / images/* / models / models/(?P<id>...) / responses
    }

    public function dispatch_chat_completions(WP_REST_Request $request): WP_REST_Response {
        return $this->pipeline->handle('chat.completions', $request);
    }
}
```

#### 路由阶段复用 PublicAPI

```php
final class RouteMiddleware implements GatewayStageInterface {

    public function process(GatewayRequestContext $context): void {
        $payload = $context->get_internal_payload();

        if ('chat.completions' === $context->operation()) {
            if (!empty($payload['stream'])) {
                $this->sse_controller->serve_chat_stream($context);
                return;
            }
            $result = \WPMind\API\PublicAPI::instance()->chat(
                $payload['messages'], $payload['options']
            );
            $context->set_internal_result($result);
            return;
        }
        // embeddings / transcriptions / speech / images 同理
    }
}
```

### P0-3: SSE 并发控制

#### 核心类

```php
final class SseConcurrencyGuard {
    public function acquire_slot(string $key_id, string $request_id, int $ttl): SseSlot|WP_Error {}
    public function heartbeat_slot(SseSlot $slot, int $ttl): void {}
    public function release_slot(SseSlot $slot): void {}
}

final class SseStreamController {
    public function serve_chat_stream(GatewayRequestContext $context): void {}
    private function send_sse_headers(string $request_id): void {}
    private function emit_sse_data(array|string $payload): void {}
}

final class UpstreamStreamClient {
    public function stream_chat(array $request, callable $on_chunk, CancellationToken $token): StreamResult {}
}

final class CancellationToken {
    public function cancel(string $reason): void {}
    public function is_cancelled(): bool {}
}
```

#### SSE 主流程

```php
public function serve_chat_stream(GatewayRequestContext $context): void {
    $slot = $this->concurrency_guard->acquire_slot(
        $context->key_id(), $context->request_id(), 45
    );
    if (is_wp_error($slot)) {
        $context->set_error($slot);
        return;
    }

    ignore_user_abort(true);
    while (ob_get_level() > 0) { ob_end_clean(); }

    $this->send_sse_headers($context->request_id());
    $token = new CancellationToken();

    try {
        $this->upstream_client->stream_chat(
            $context->get_upstream_request(),
            function(array $chunk) use ($token, $slot) {
                if (connection_aborted()) {
                    $token->cancel('client_disconnected');
                    return false;
                }
                $this->emit_sse_data($chunk);
                $this->concurrency_guard->heartbeat_slot($slot, 45);
                return true;
            },
            $token
        );
        $this->emit_sse_data('[DONE]');
    } finally {
        $this->concurrency_guard->release_slot($slot);
    }
}
```

#### 上游取消机制 (cURL)

- `CURLOPT_WRITEFUNCTION`: 检查 `$token->is_cancelled()`，返回 `0` 触发 cURL 终止
- `CURLOPT_XFERINFOFUNCTION`: 返回非 0 中断传输
- 链路: 客户端断开 → `connection_aborted()` 检测 → 取消令牌 → 终止上游请求 → 释放 worker

#### 并发阈值

- 全局: `wpmind_gateway_sse_global_limit` (默认 20)
- 每 Key: 读 `api_keys.concurrency_limit` (默认 2)
- 超限: HTTP 429 + `rate_limit_error` + `Retry-After`

### P0-4: 限流方案 (Redis 优先 + Transient 降级)

#### 核心接口

```php
interface RateStoreInterface {
    public function consume(
        string $bucket_key, int $window_sec, int $cost,
        int $limit, string $request_id, int $now_ms
    ): RateStoreResult;

    public function rollback(string $bucket_key, string $request_id): void;
}

final class RedisRateStore implements RateStoreInterface { ... }
final class TransientRateStore implements RateStoreInterface { ... }

final class RateLimiter {
    public function __construct(
        private RateStoreInterface $primary_store,
        private RateStoreInterface $fallback_store
    ) {}

    public function check_and_consume(GatewayRequestContext $context): RateLimitDecision {}
}
```

#### Quota 中间件

```php
public function process(GatewayRequestContext $context): void {
    $token_cost = $this->token_estimator->estimate_request_tokens($context->raw_body());
    $decision = $this->rate_limiter->check_and_consume($context);

    if (!$decision->allowed()) {
        $error = new WP_Error('wpmind_rate_limited', 'Rate limit reached.', [
            'status'      => 429,
            'retry_after' => $decision->retry_after_sec(),
        ]);
        $context->set_error($error);
        return;
    }

    $context->set_response_header('x-ratelimit-remaining', (string) $decision->remaining_min());
    $context->set_response_header('x-ratelimit-reset', (string) $decision->reset_epoch());
}
```

#### Redis 滑动窗口 (原子 Lua)

- 维度 1: `rpm`, `cost=1`
- 维度 2: `tpm`, `cost=estimated_tokens`
- Key 格式: `wpmind:rl:{key_id}:{rpm|tpm}`
- Lua 脚本: 清理过期成员 → 读取窗口累计 → 判限 → 通过则写入并设置 TTL（一次原子操作）

#### Transient 降级

- 结构: `events[] + sum`
- 短锁: `wpmind_rl_lock_{md5(bucket_key)}` (2 秒 TTL)
- 清理过期事件后判限并写回
- 注意: 降级模式是"近似原子，单节点最佳"

### P0-5: OpenAI 错误格式兼容

#### 核心类

```php
final class OpenAIErrorMapper {
    public function map_error(WP_Error|\Throwable $error, GatewayRequestContext $context): OpenAIErrorEnvelope;
    public function to_rest_response(OpenAIErrorEnvelope $envelope, string $request_id): WP_REST_Response;
    private function sanitize_message(string $message): string {}
}
```

#### 统一错误响应格式

```json
{
    "error": {
        "message": "xxx",
        "type": "invalid_request_error",
        "param": "model",
        "code": "model_not_found"
    }
}
```

#### 错误码映射表

| WPMind 错误码 | HTTP | type | code |
|---------------|------|------|------|
| `wpmind_auth_invalid_api_key` | 401 | `authentication_error` | `invalid_api_key` |
| `wpmind_auth_missing` | 401 | `authentication_error` | `invalid_api_key` |
| `wpmind_invalid_params` | 400 | `invalid_request_error` | `invalid_parameter` |
| `wpmind_model_not_supported` | 400 | `invalid_request_error` | `model_not_found` |
| `wpmind_rate_limited` | 429 | `rate_limit_error` | `rate_limit_exceeded` |
| `wpmind_budget_exceeded` | 429 | `insufficient_quota` | `insufficient_quota` |
| `wpmind_api_timeout` | 504 | `server_error` | `upstream_timeout` |
| `wpmind_api_error` | 502 | `server_error` | `upstream_error` |
| unknown | 500 | `server_error` | `internal_error` |

#### 错误中间件

```php
public function process(GatewayRequestContext $context): void {
    if (!$context->has_error()) {
        return;
    }

    $envelope = $this->mapper->map_error($context->error(), $context);
    $response = $this->mapper->to_rest_response($envelope, $context->request_id());

    if ($context->retry_after_sec() > 0) {
        $response->header('Retry-After', (string) $context->retry_after_sec());
    }

    $context->set_rest_response($response);
}
```

### 与现有 WPMind 的关键集成点

| 集成点 | 文件 | 行号 | 说明 |
|--------|------|------|------|
| 模块自动发现 | `includes/Core/ModuleLoader.php` | :72 | 模块加载入口 |
| 模块目录约定 | `includes/Core/ModuleLoader.php` | :90 | 目录扫描规则 |
| AI Facade 入口 | `includes/API/PublicAPI.php` | :64 | 所有 AI 调用入口 |
| Chat 调用 | `includes/API/PublicAPI.php` | :332 | 非流式 chat 方法 |
| Stream 调用 | `includes/API/PublicAPI.php` | :332 | 流式 stream 方法 |
| 请求生命周期 hook | `includes/API/Services/ChatService.php` | :85 | `wpmind_before_request` |
| 用量回调 | `includes/API/Services/ChatService.php` | :157 | `wpmind_after_request` |
| 成本模块消费 | `modules/cost-control/CostControlModule.php` | :108 | 用量事件处理 |
| 预算拦截 | `modules/cost-control/includes/BudgetChecker.php` | :194 | 可复用预算检查 |
| Provider 配置 | `wpmind.php` | :615 | `get_custom_endpoints()` |
| 错误码定义 | `includes/API/ErrorHandler.php` | :29 | 现有错误码 |
