# API Gateway 模块实施计划

> 基于 API-GATEWAY-MODULE.md 设计方案 + Codex 三轮评审结果 (R1 审查修正版)

## 实施原则

- **质量优先**: 不赶时间，每个阶段必须通过验收标准
- **渐进式**: 按依赖关系分阶段实施，每阶段可独立验证
- **WordPress 规范**: strict_types、snake_case、Tab 缩进、类型声明
- **安全第一**: 常数时间校验、输入验证、错误脱敏、CSRF 防护、SSRF 防护
- **契约对齐**: 模块必须严格遵循现有 ModuleInterface 契约和 module.json 格式

## 团队结构

| 角色 | 职责 | 主要阶段 |
|------|------|----------|
| **Leader** (Claude) | 架构决策、代码审查、集成协调、质量把关、安全审计 | 全程 |
| **WP Expert** | DB schema、REST API、Admin UI、WP 编码规范、部署配置 | P0, P1, P2, P8, P9 |
| **AI Expert** | Provider 适配、SSE 流、模型映射、格式转换 | P5, P6 |
| **协作** | 中间件框架、认证限流、错误处理 | P3, P4, P7 |

## Codex 审查节点

| 节点 | 时机 | 审查内容 |
|------|------|----------|
| R1 | 计划完成 | 整体计划合理性、遗漏检查 ✅ 已完成 |
| R2 | Phase 0-3 完成 | 基础架构代码质量 + 模块契约对齐 |
| R3 | Phase 4-7 完成 | 核心功能代码 + 安全审查 |
| R4 | Phase 8-10 完成 | 最终审查 + OpenAI 兼容性验证 |

### R1 审查发现 (已修正)

| 级别 | 问题 | 修正 |
|------|------|------|
| Critical | module.json 格式与 ModuleLoader 不匹配 | 新增 Phase 0 对齐 |
| Critical | 子类无自动加载，缺 require_once 策略 | Phase 0 明确加载方案 |
| Critical | Pipeline 顺序矛盾 (Transform 应拆分前后) | 拆分为 8 阶段 |
| High | 认证边界未按路由类型分离 | Phase 4 明确分离 |
| High | /responses 端点标记为可选 | 改为 MVP 必须 |
| High | 缺少预算拦截中间件 | 新增 BudgetMiddleware |
| High | 缺少 Key 元数据缓存 | Phase 2 新增缓存任务 |

---

## Phase 0: 框架对齐 (R1 审查新增)

**负责**: WP Expert | **依赖**: 无

> Codex R1 发现模块契约、类加载、Pipeline 顺序存在 Critical 级别不匹配。此阶段在编码前完成对齐。

### 任务

0.1 确认 ModuleInterface 契约
- 必须实现: `get_id()`, `get_name()`, `get_description()`, `get_version()`, `init()`, `check_dependencies()`, `get_settings_tab()`
- **不存在** `boot()`/`activate()`/`deactivate()` 接口方法 (模块可自行添加但非接口要求)
- 参考: `includes/Core/ModuleInterface.php`

0.2 确认 module.json 格式 (对齐现有模块)
```json
{
    "id": "api-gateway",
    "name": "API Gateway",
    "description": "OpenAI 兼容的 AI API 网关",
    "version": "1.0.0",
    "author": "WPMind",
    "icon": "ri-server-line",
    "class": "WPMind\\Modules\\ApiGateway\\ApiGatewayModule",
    "can_disable": true,
    "settings_tab": "api-gateway",
    "features": [
        "OpenAI 兼容 API",
        "API Key 管理",
        "速率限制",
        "SSE 流式输出",
        "审计日志"
    ],
    "requires": {
        "php": "8.1",
        "wordpress": "6.4"
    }
}
```

0.3 确认类加载策略
- 主模块文件使用 `require_once __DIR__ . '/includes/...'` 加载所有子类
- 参考 `modules/geo/GeoModule.php:17-30` 和 `modules/cost-control/CostControlModule.php:26-28`
- 不依赖 PSR-4 自动加载

0.4 确认 Pipeline 阶段顺序 (8 阶段，修正 R1 矛盾)
```
auth → budget → quota → request_transform → route → response_transform → error → log
```
- `auth`: 认证 (Bearer Key 或 Cookie+Nonce)
- `budget`: 预算检查 (月度预算上限)，管理端点跳过
- `quota`: 限流 (RPM/TPM)，管理端点跳过
- `request_transform`: OpenAI 格式 → WPMind 内部格式 (路由前)
- `route`: 分发到 PublicAPI 对应方法
- `response_transform`: WPMind 结果 → OpenAI 格式 (路由后)
- `error`: 统一错误映射 (始终执行)
- `log`: 审计日志 (始终执行)

> **管理端点跳过规则**: `/status` 等管理端点通过 Cookie+Nonce 认证后，budget 和 quota 中间件检测到非 API Key 认证直接跳过。

0.5 确认认证策略分离
- 外部 API 端点 (`/chat/completions`, `/embeddings` 等): **仅 Bearer Key**
- 管理端点 (`/status`, Admin AJAX): **Cookie + X-WP-Nonce** 或 **Application Password**
- `permission_callback` 仍为 `__return_true`，但 AuthMiddleware 内部按路由类型强制分离

### 验收标准

- [ ] module.json 字段与 ModuleLoader 发现逻辑完全匹配
- [ ] 类加载策略文档化，所有 require_once 路径确认
- [ ] Pipeline 8 阶段顺序无矛盾
- [ ] 认证策略按路由类型分离，文档化

---

## Phase 1: 模块骨架 + DB 基础设施

**负责**: WP Expert | **依赖**: Phase 0

### 任务

1.1 创建目录结构 `modules/api-gateway/`
```
modules/api-gateway/
├── ApiGatewayModule.php
├── module.json              # 格式见 Phase 0.2
├── includes/
│   └── SchemaManager.php
└── templates/
    └── settings.php (空占位)
```

1.2 `module.json` — 使用 Phase 0.2 确认的格式

1.3 `ApiGatewayModule.php`
- implements `ModuleInterface`
- `get_id()`: 返回 'api-gateway'
- `get_name()`: 返回 'API Gateway'
- `init()`: 注册 `rest_api_init` hook + 调用 `SchemaManager::maybe_upgrade()`
- `check_dependencies()`: 检查 PHP 8.1+ 和 WP 6.4+
- `get_settings_tab()`: 返回 'api-gateway'
- 顶部 `require_once` 加载所有子类 (Phase 0.3 策略)

1.4 `SchemaManager.php`
- `create_tables()`: 使用 `dbDelta()` 创建 3 张表
- `get_schema_version()`: 返回当前 schema 版本
- `maybe_upgrade()`: 版本比较 + 幂等迁移
- 表结构见 API-GATEWAY-MODULE.md P0-1 DB Schema

1.5 卸载清理
- 在 `uninstall.php` 中添加 API Gateway 表的 DROP 逻辑

### 验收标准

- [ ] 模块可在 WPMind 后台启用/停用
- [ ] 启用时自动创建 3 张 DB 表
- [ ] `wp_wpmind_api_keys` / `wp_wpmind_api_key_usage` / `wp_wpmind_api_audit_log` 表结构正确
- [ ] schema 版本号正确存储在 `wp_options`
- [ ] 停用后重新启用不会报错 (幂等)

---

## Phase 2: API Key 系统

**负责**: WP Expert | **依赖**: Phase 1

### 任务

2.1 `Auth/ApiKeyHasher.php`
- `make_salt_hex()`: `bin2hex(random_bytes(16))` → 32 字符
- `hash_secret(string $secret, string $salt_hex)`: `hash('sha256', $salt_hex . $secret)` → 64 字符
- `constant_time_verify()`: 内部调用 `hash_equals()`
- 定义 `DUMMY_SALT_HEX` 和 `DUMMY_HASH_HEX` 常量

2.2 `Auth/ApiKeyRepository.php`
- `insert_key(array $data)`: INSERT + 返回 ID
- `find_by_key_id(string $key_id)`: SELECT by UNIQUE key_id
- `update_last_used(string $key_id)`: UPDATE last_used_at
- `revoke_key(string $key_id, int $actor, string $reason)`: UPDATE status + revoked_at
- `list_keys(int $owner_id, int $page, int $per_page)`: 分页列表
- `delete_expired_keys()`: 清理过期 Key

2.3 `Auth/ApiKeyManager.php`
- `create_api_key(array $attrs)`: 生成 key_id + secret → hash → 存储 → 返回明文 Key (仅一次)
- `authenticate_bearer_header(string $header, string $ip)`: 解析 → 查库 → 常数时间校验
- `parse_api_key(string $raw)`: 正则解析 `sk_mind_{key_id}_{secret}`
- `is_key_expired(array $row)`: 检查 expires_at
- `is_ip_allowed(array $row, string $ip)`: IP 白名单校验

2.4 `Auth/ApiKeyAuthResult.php` (DTO)
- 属性: key_id, owner_user_id, allowed_providers, rpm_limit, tpm_limit, concurrency_limit, monthly_budget_usd

2.5 Key 元数据缓存 (R1 审查新增)
- 认证成功后将 key 元数据缓存到 `wp_cache` (对象缓存)
- TTL: 60 秒 (短 TTL 平衡性能和一致性)
- 吊销/修改 Key 时主动失效缓存: `wp_cache_delete('wpmind_key_' . $key_id)`
- 缓存 key: `wpmind_key_{key_id}`
- 注意: 仅缓存元数据 (限额/状态)，不缓存 secret_hash (安全)

### 验收标准

- [ ] `create_api_key()` 返回格式正确的 `sk_mind_xxxx_yyyy` Key
- [ ] 同一 Key 二次认证成功
- [ ] 错误 Key 认证失败，返回 WP_Error
- [ ] 不存在的 key_id 认证耗时与存在的 key_id 一致 (常数时间)
- [ ] 过期 Key 认证失败
- [ ] IP 白名单外的 IP 认证失败
- [ ] 吊销后的 Key 认证失败

---

## Phase 3: 请求上下文 + 中间件框架

**负责**: WP Expert + AI Expert 协作 | **依赖**: Phase 1

### 任务

3.1 `Pipeline/GatewayRequestContext.php`
- 静态工厂: `from_rest_request(string $operation, WP_REST_Request $request)`
- 属性: operation, request_id (UUID v4), raw_body, auth_result, error, exception
- 属性: internal_payload, internal_result, rest_response, response_headers
- 方法: `key_id()`, `has_error()`, `set_error()`, `set_exception()`
- 方法: `set_response_header()`, `to_rest_response()`
- 方法: `retry_after_sec()`, `get_upstream_request()`

3.2 `Pipeline/GatewayStageInterface.php`
```php
interface GatewayStageInterface {
    public function process(GatewayRequestContext $context): void;
}
```

3.3 `Pipeline/GatewayPipeline.php`
- 构造函数注入 8 个中间件 (auth → budget → quota → request_transform → route → response_transform → error → log)
- `handle()`: try { auth→budget→quota→request_transform→route→response_transform } catch → error → log
- 返回 `WP_REST_Response`

3.4 `GatewayRequestSchema.php`
- `chat_completions()`: model, messages, temperature, max_tokens, stream, tools, tool_choice 等
- `embeddings()`: model, input, encoding_format
- `responses()`: model, input, instructions, tools (OpenAI Responses API 格式)
- `models()`: 无参数
- 每个 schema 定义类型、默认值、sanitize_callback

### 验收标准

- [ ] `GatewayRequestContext` 可从 `WP_REST_Request` 正确构建
- [ ] `request_id` 为有效 UUID v4
- [ ] Pipeline 按顺序执行中间件
- [ ] 中间件抛异常时 error/log 仍然执行
- [ ] Schema 验证拒绝无效参数

---

## Phase 4: 认证 + 预算 + 限流中间件

**负责**: WP Expert + AI Expert 协作 | **依赖**: Phase 2, 3

### 任务

4.1 `Pipeline/AuthMiddleware.php`
- **外部 API 端点** (chat/completions, embeddings 等): **仅 Bearer Key**
  - 调用 `ApiKeyManager::authenticate_bearer_header()`
  - 拒绝 Cookie/Application Password 认证
- **管理端点** (status, Admin AJAX): **Cookie + X-WP-Nonce** 或 **Application Password**
  - Cookie 模式: 验证 `X-WP-Nonce` + `wp_verify_nonce()` + `current_user_can('manage_options')`
  - Application Password 模式: WordPress 内置 `wp_authenticate_application_password()` + `current_user_can('manage_options')`
  - 拒绝 Bearer Key 认证
- 路由类型判断: 基于 `$context->operation()` 前缀
- 认证失败: `$context->set_error(WP_Error)` + 提前返回
- 认证成功: `$context->set_auth_result(ApiKeyAuthResult)`

4.2 `Pipeline/BudgetMiddleware.php` (R1 审查新增)
- 读取 `api_key_usage` 表当前月用量
- 对比 `api_keys.monthly_budget_usd`
- 超预算: 返回 429 + `insufficient_quota` (OpenAI 兼容)
- 复用 `BudgetChecker` 逻辑 (如可用)
- 预算为 0 表示无限制

4.2 `RateLimit/RateStoreInterface.php`
```php
interface RateStoreInterface {
    public function consume(string $key, int $window, int $cost, int $limit, string $rid, int $now): RateStoreResult;
    public function rollback(string $key, string $rid): void;
}
```

4.3 `RateLimit/RedisRateStore.php`
- 检测 Redis 可用性 (`wp_cache_supports('redis')` 或直接检测)
- Lua 脚本: ZREMRANGEBYSCORE → ZCARD/ZRANGEBYSCORE → ZADD → EXPIRE
- 滑动窗口: 60 秒 (RPM) / 60 秒 (TPM)
- 返回: allowed, remaining, reset_epoch

4.4 `RateLimit/TransientRateStore.php`
- 短锁: `set_transient('wpmind_rl_lock_' . md5($key), 1, 2)`
- 事件列表: `get_transient('wpmind_rl_' . md5($key))` → 清理过期 → 判限 → 写回
- 锁失败: 默认放行 (fail-open) 或拒绝 (fail-close，可配置)

4.5 `RateLimit/RateLimiter.php`
- 构造函数: primary (Redis) + fallback (Transient)
- `check_and_consume()`: 尝试 primary，异常时降级 fallback
- 双维度: RPM (cost=1) + TPM (cost=estimated_tokens)

4.6 `Pipeline/QuotaMiddleware.php`
- 估算 token: 简单按字符数 / 4 估算
- 调用 `RateLimiter::check_and_consume()`
- 超限: set_error + 429
- 通过: 设置 `x-ratelimit-remaining` / `x-ratelimit-reset` 响应头

### 验收标准

- [ ] Bearer Key 认证正确工作
- [ ] Cookie+Nonce 认证正确工作 (仅管理端)
- [ ] 无认证信息返回 401
- [ ] RPM 限流: 超过 60 次/分返回 429
- [ ] TPM 限流: 超过 100K tokens/分返回 429
- [ ] Redis 不可用时自动降级到 Transient
- [ ] 响应包含 `x-ratelimit-remaining` 头

---

## Phase 5: 路由 + 转换层

**负责**: AI Expert | **依赖**: Phase 3

### 任务

5.1 `Transform/RequestTransformer.php`
- OpenAI `messages` → WPMind `$messages` 格式
- OpenAI `model` → WPMind `$options['provider']` + `$options['model']`
- 模型映射: auto → 智能路由, 具体模型名 → 对应 provider
- 参数映射: temperature, max_tokens, top_p, frequency_penalty 等
- 别名解析: 后台配置的模型别名 (如 gpt-4o → deepseek-chat)

5.2 `Transform/ResponseTransformer.php`
- WPMind chat 结果 → OpenAI `chat.completion` 格式
- 生成 `id`: `wpmind-{uuid}`
- 填充 `object`, `created`, `model`, `choices`, `usage`
- WPMind embedding 结果 → OpenAI `embedding` 格式
- WPMind 错误 → 不处理 (交给 ErrorMiddleware)

5.3 `Pipeline/RouteMiddleware.php`
- 根据 `$context->operation()` 分发到对应 PublicAPI 方法
- `chat.completions` (非流式) → `PublicAPI::instance()->chat()`
- `chat.completions` (流式) → `SseStreamController::serve_chat_stream()`
- `embeddings` → `PublicAPI::instance()->embed()`
- `models` → 返回可用模型列表
- `models/{id}` → 返回单个模型信息
- `responses` → 转换为 chat 调用 (OpenAI Responses API 兼容)

5.4 `Pipeline/RequestTransformMiddleware.php` (路由前执行)
- 调用 `RequestTransformer` 转换 OpenAI → WPMind
- 设置 `$context->set_internal_payload()`
- **SSRF 防护** (R1 审查新增): 验证上游 URL 在白名单内，拒绝私网/IP/metadata 地址
- **请求体大小限制**: 检查 `Content-Length`，默认上限 10MB
- **max_tokens 上限**: 强制不超过配置的全局上限

5.5 `Pipeline/ResponseTransformMiddleware.php` (路由后执行)
- 调用 `ResponseTransformer` 转换 WPMind → OpenAI
- 设置 `$context->set_rest_response()`

5.5 模型映射配置
- 默认映射表 (硬编码)
- 后台可配置别名 (存 wp_options)
- `auto` 模型走 IntelligentRouter
- 未知模型返回 400 `model_not_found`

### 验收标准

- [ ] OpenAI 格式请求正确转换为 WPMind 内部格式
- [ ] WPMind 响应正确转换为 OpenAI 格式
- [ ] `model: "auto"` 走智能路由
- [ ] `model: "deepseek-chat"` 正确路由到 DeepSeek
- [ ] 模型别名正确解析
- [ ] 未知模型返回 400 + `model_not_found`
- [ ] 响应 `id` 格式为 `wpmind-{uuid}`

---

## Phase 6: SSE 流式处理

**负责**: AI Expert | **依赖**: Phase 3, 5

### 任务

6.1 `Stream/CancellationToken.php`
- `cancel(string $reason)`: 设置取消标志 + 记录原因
- `is_cancelled()`: 返回取消状态
- `get_reason()`: 返回取消原因

6.2 `Stream/SseConcurrencyGuard.php`
- `acquire_slot(string $key_id, string $request_id, int $ttl)`: 检查全局+Key并发 → 获取槽位
- `heartbeat_slot(SseSlot $slot, int $ttl)`: 续期槽位 TTL
- `release_slot(SseSlot $slot)`: 释放槽位
- 存储: Redis SETNX 或 Transient
- 全局限制: `get_option('wpmind_gateway_sse_global_limit', 20)`
- 每 Key 限制: 读 `api_keys.concurrency_limit`

6.3 `Stream/UpstreamStreamClient.php`
- 基于 cURL 的流式 HTTP 客户端
- `CURLOPT_WRITEFUNCTION`: 解析 SSE data → 调用 $on_chunk → 检查 CancellationToken
- `CURLOPT_XFERINFOFUNCTION`: 检查 CancellationToken → 返回非 0 中断
- 超时: `CURLOPT_TIMEOUT` = 120s
- 返回 `StreamResult` (tokens_used, finish_reason, error)

6.4 `Stream/SseStreamController.php`
- `serve_chat_stream()`: 获取槽位 → 清理 OB → 发送 SSE 头 → 流式输出 → 释放槽位
- `send_sse_headers()`: Content-Type, Cache-Control, X-Accel-Buffering, X-Request-Id
- `emit_sse_data()`: `echo "data: " . json_encode($chunk) . "\n\n"; flush();`
- `connection_aborted()` 检测客户端断开
- `ignore_user_abort(true)` 确保 finally 块执行

6.5 集成到 RouteMiddleware
- `stream=true` 时调用 `SseStreamController` 而非 `PublicAPI::chat()`
- SSE 响应绕过 `TransformMiddleware` (直接输出)

### 验收标准

- [ ] `stream: true` 返回 `text/event-stream`
- [ ] 每个 chunk 格式: `data: {"id":"...","object":"chat.completion.chunk",...}\n\n`
- [ ] 最后发送 `data: [DONE]\n\n`
- [ ] 客户端断开后上游请求被取消
- [ ] 并发超限返回 429
- [ ] 槽位 TTL 过期后自动释放 (防僵尸)
- [ ] `X-Accel-Buffering: no` 头正确设置

---

## Phase 7: 错误处理 + 日志审计

**负责**: WP Expert + AI Expert 协作 | **依赖**: Phase 3

### 任务

7.1 `Transform/OpenAIErrorMapper.php`
- `map_error()`: WP_Error/Throwable → OpenAIErrorEnvelope
- 映射表: 见 API-GATEWAY-MODULE.md P0-5 错误码映射表
- `sanitize_message()`: 移除内部路径、Provider 名、堆栈信息
- `to_rest_response()`: 设置 HTTP 状态码 + JSON body

7.2 `Pipeline/ErrorMiddleware.php`
- 检查 `$context->has_error()` 或 `$context->has_exception()`
- 调用 `OpenAIErrorMapper::map_error()`
- 设置 `Retry-After` 头 (429 时)
- 设置 `x-request-id` 头

7.3 `Logging/AuditLogger.php`
- `log_request()`: 记录 API 请求到 `api_audit_log` 表
- `log_key_event()`: 记录 Key 创建/吊销/修改事件
- 字段: event_type, key_id, request_id, ip_hash, user_agent, detail_json
- IP 存储: `hash('sha256', $ip)` (隐私保护)
- 可配置: 是否记录 prompt/response (默认不记录)

7.4 `Pipeline/LogMiddleware.php`
- 始终执行 (finally 语义)
- 记录: request_id, key_id, operation, model, provider, status, latency_ms, tokens, cost, cache_hit
- 触发 `wpmind_after_request` hook (复用 cost-control 模块)
- 更新 `api_key_usage` 聚合表

### 验收标准

- [ ] 所有错误返回 `{"error":{"message","type","param","code"}}` 格式
- [ ] 401 错误不泄露 Key 是否存在
- [ ] 500 错误不泄露内部路径/堆栈
- [ ] 审计日志正确记录到 DB
- [ ] IP 以 hash 形式存储
- [ ] 用量聚合表按月正确累计
- [ ] cost-control 模块的 hook 被正确触发

---

## Phase 8: REST 控制器 + 端点注册

**负责**: WP Expert | **依赖**: Phase 4, 5, 6, 7

### 任务

8.1 `RestController.php`
- `register_routes()`: 注册所有端点到 `mind/v1` namespace
- 每个端点: methods, callback, permission_callback, args
- `permission_callback` 统一 `__return_true` (auth 在 pipeline 内)

8.2 端点列表 (MVP 必须)
```
POST /wp-json/mind/v1/chat/completions
POST /wp-json/mind/v1/embeddings
POST /wp-json/mind/v1/responses                              # R1 审查: 改为必须
GET  /wp-json/mind/v1/models
GET  /wp-json/mind/v1/models/(?P<model_id>[a-zA-Z0-9_.-]+)  # R1 审查: 改为必须
GET  /wp-json/mind/v1/status
```

8.3 可选端点 (Phase 2 实施)
```
POST /wp-json/mind/v1/audio/transcriptions
POST /wp-json/mind/v1/audio/speech
POST /wp-json/mind/v1/images/generations
```

8.4 不支持的端点策略 (R1 审查新增)
- 未实现的端点返回 OpenAI 格式 404: `{"error":{"type":"invalid_request_error","code":"endpoint_not_found"}}`
- 不支持的参数 (如 `response_format.json_schema`, `seed`) 返回明确的 reject 错误
- 契约测试覆盖所有 reject 场景

8.4 Nginx 配置文档
- `X-Accel-Buffering: no` (SSE)
- `proxy_read_timeout 300s`
- `gzip off` (SSE 端点)
- Authorization 头透传

### 验收标准

- [ ] `curl -H "Authorization: Bearer sk_mind_xxx" -d '{"model":"auto","messages":[...]}' /wp-json/mind/v1/chat/completions` 返回正确响应
- [ ] `GET /wp-json/mind/v1/models` 返回可用模型列表
- [ ] `GET /wp-json/mind/v1/status` 返回网关状态
- [ ] 无认证请求返回 401 OpenAI 格式错误
- [ ] 所有响应包含 `x-request-id` 头

---

## Phase 9: Admin UI

**负责**: WP Expert | **依赖**: Phase 2, 8

### 任务

9.1 `templates/settings.php` - 设置页
- 网关开关 (启用/停用 REST 端点)
- 全局 SSE 并发限制
- 默认 RPM/TPM 限制
- 日志配置 (是否记录 prompt/response)

9.2 API Key 管理界面
- 创建 Key: 名称、绑定用户、Provider 限制、RPM/TPM、预算、IP 白名单、过期时间
- 列表: key_prefix 显示、状态、最后使用时间、用量统计
- 操作: 吊销、编辑限制
- 创建成功弹窗: 显示完整 Key (仅一次)

9.3 用量统计
- 按 Key 的月度用量图表
- 按 Provider 的请求分布
- 错误率统计

9.4 AJAX 接口
- `wp_ajax_wpmind_create_api_key`
- `wp_ajax_wpmind_list_api_keys`
- `wp_ajax_wpmind_revoke_api_key`
- 安全三要素: check_ajax_referer + current_user_can + sanitize

### 验收标准

- [ ] 后台可创建 API Key 并显示完整 Key
- [ ] Key 列表正确显示 prefix + 状态
- [ ] 可吊销 Key
- [ ] 用量统计数据正确
- [ ] AJAX 接口有 CSRF + 权限 + 清理保护

---

## Phase 10: 测试 + 文档

**负责**: 全团队 | **依赖**: Phase 1-9

### 任务

10.1 单元测试
- `ApiKeyHasherTest`: hash 一致性、constant_time_verify、dummy hash
- `RateLimiterTest`: RPM/TPM 限流、Redis 降级、rollback
- `OpenAIErrorMapperTest`: 所有错误码映射正确
- `RequestTransformerTest`: OpenAI → WPMind 转换
- `ResponseTransformerTest`: WPMind → OpenAI 转换

10.2 集成测试
- 端到端 chat/completions (非流式)
- 端到端 chat/completions (流式)
- 认证失败场景
- 限流触发场景
- Provider failover 场景

10.3 契约测试
- OpenAI Python SDK 兼容性测试
- OpenAI Node.js SDK 兼容性测试
- 响应格式严格校验 (JSON Schema)

10.4 部署文档
- Nginx 配置模板
- Apache .htaccess 配置
- Cloudflare 配置注意事项
- 故障排查指南

### 验收标准

- [ ] 单元测试覆盖率 > 80%
- [ ] 集成测试全部通过
- [ ] OpenAI Python SDK 可正常调用
- [ ] 部署文档完整可执行

---

## 依赖关系图

```
Phase 0 (框架对齐)
    │
    └── Phase 1 (骨架+DB)
            ├── Phase 2 (API Key) ──────┐
            │                            ├── Phase 4 (认证+预算+限流)
            └── Phase 3 (上下文+中间件) ──┤
                 │                        ├── Phase 7 (错误+日志)
                 ├── Phase 5 (路由+转换) ──┤
                 │                        │
                 └── Phase 6 (SSE) ───────┘
                                           │
                                           └── Phase 8 (REST 控制器)
                                                   │
                                                   ├── Phase 9 (Admin UI)
                                                   │
                                                   └── Phase 10 (测试+文档)
```

## 并行执行策略

| 批次 | 阶段 | 可并行 | 说明 |
|------|------|--------|------|
| Batch 0 | Phase 0 | 单独执行 | 框架对齐，编码前完成 |
| Batch 1 | Phase 1 | 单独执行 | DB + 模块骨架 |
| Batch 2 | Phase 2 + Phase 3 | ✅ 可并行 | WP Expert 做 P2, AI Expert 做 P3 |
| Batch 3 | Phase 4 + Phase 5 | ✅ 可并行 | 协作做 P4, AI Expert 做 P5 |
| Batch 4 | Phase 6 + Phase 7 | ✅ 可并行 | AI Expert 做 P6, 协作做 P7 |
| Batch 5 | Phase 8 | 单独执行 | 依赖 Batch 3+4 |
| Batch 6 | Phase 9 | 单独执行 | 依赖 Phase 8 |
| Batch 7 | Phase 10 | 单独执行 | 依赖 Phase 9 (需完整功能才能测试) |

## 风险清单

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| PHP-FPM worker 耗尽 | SSE 高并发时站点不可用 | 全局并发阈值 + 快速失败 |
| Redis 不可用 | 限流精度下降 | Transient 降级 + 告警 |
| Authorization 头被吞 | 认证失败 | 部署文档 + 自动检测 + 提示 |
| 上游 Provider 超时 | 请求堆积 | 超时控制 + 熔断器复用 |
| DB 表膨胀 | 性能下降 | 审计日志定期清理 + 用量按月聚合 |
| OpenAI SDK 更新 | 兼容性破坏 | 契约测试 + 版本锁定 |
| SSRF 攻击 | 内网探测/数据泄露 | 上游域名白名单 + DNS 二次校验 + 私网 IP 拒绝 |
| 请求体过大 | 内存溢出 | Content-Length 上限 + max_tokens 上限 |
| status 端点信息泄露 | 暴露内部架构 | 默认最小化输出，仅返回健康状态 |
| 多站点卸载不完整 | 残留数据 | multisite 循环清理 + 幂等迁移测试 |
