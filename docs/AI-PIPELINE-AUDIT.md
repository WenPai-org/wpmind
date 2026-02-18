# WPMind AI 请求链路深度审计报告

> 审计日期: 2026-02-07
> 审计范围: AI 请求/响应管线核心功能，不含 Admin UI 和编码规范
> 当前版本: 3.3.0

---

## 1. 架构发现：双系统并行

WPMind 存在**两套完全独立的 AI 请求管线**：

```
管线 A: PublicAPI (wpmind_chat / wpmind_translate 等)
  → 自建 HTTP 客户端 (wp_remote_post)
  → 自建路由/故障转移
  → 仅支持 OpenAI 兼容格式

管线 B: WordPress AI Client SDK (includes/Providers/)
  → 注册到官方 ProviderRegistry
  → 由 WordPress 核心/AI Experiments 调用
  → 支持官方 SDK 的所有格式
```

两套系统**不共享**路由、故障转移、缓存、重试逻辑。

---

## 2. 问题清单

### CRITICAL — 功能性 Bug

#### C1: Anthropic/Google 在 PublicAPI 中不可用

**位置**: `includes/API/PublicAPI.php:1447-1552`

`execute_chat_request()` 假设所有 Provider 都是 OpenAI 兼容格式：
- 使用 `Authorization: Bearer` 头（Anthropic 需要 `x-api-key` + `anthropic-version`）
- 请求 `/chat/completions` 端点（Anthropic 是 `/messages`，Google 完全不同）
- 按 OpenAI 格式解析响应

8 个国产 Provider 都是 OpenAI 兼容的所以正常，但 Anthropic/Google 会静默失败。

**影响**: 用户配置了 Anthropic/Google API Key 后调用 `wpmind_chat()` 会失败。

---

### IMPORTANT — 影响核心功能

#### I1: 重试逻辑是死代码

**位置**: `includes/ErrorHandler.php:220-237` vs `includes/API/PublicAPI.php:321-344`

`ErrorHandler::should_retry()` 和 `get_retry_delay()`（指数退避 1s→2s→4s→8s）已实现但**从未被调用**。

`do_chat()` 的故障转移循环遇到 429/503 时直接跳到下一个 Provider，不会对同一个 Provider 重试。一个临时的 429 会让健康的 Provider 被跳过。

#### I2: stream/embed/transcribe/speech 绕过路由和故障转移

**位置**: `PublicAPI.php:527, 809, 1150, 1294`

只有 `chat()` 走完整的 路由→故障转移→Provider 链路。其他方法硬编码 `get_option('wpmind_default_provider')`。

| 方法 | 路由 | 故障转移 | 缓存 |
|------|------|----------|------|
| chat() | ✅ | ✅ | ✅ |
| translate() | ✅ (via chat) | ✅ (via chat) | ✅ |
| summarize() | ✅ (via chat) | ✅ (via chat) | ✅ |
| moderate() | ✅ (via structured) | ✅ (via structured) | ✅ |
| structured() | ✅ (via chat) | ✅ (via chat) | ❌ |
| stream() | ❌ | ❌ | ❌ |
| embed() | ❌ | ❌ | ❌ |
| transcribe() | ❌ | ❌ | ❌ |
| speech() | ❌ | ❌ | ❌ |
| batch() | ✅ (via chat) | ✅ (via chat) | ❌ |

#### I3: Streaming 实现问题

**位置**: `PublicAPI.php:527-636`

- 使用 raw `fopen()` 绕过 WordPress HTTP API（无代理、无 WP SSL、无 filter）
- 没有前端 SSE 端点（无 AJAX/REST handler 给浏览器消费）
- 没有故障转移
- 没有用量追踪（token usage 为空）

#### I4: 没有主动速率限制

没有 RPM/TPM 追踪或限制。插件会无限制发送请求直到 Provider 返回 429。多用户站点上一个用户可以耗尽所有配额。

#### I5: batch() 和 Qwen 图像生成的同步阻塞

- `batch()` 顺序处理，多项目会触发 PHP max_execution_time
- `QwenImageProvider::generate()` 用 `sleep(2)` 轮询最多 60 次（阻塞 2 分钟）

---

### 产品方向缺失

#### P1: 没有 Function Calling / Tool Use 支持

DeepSeek、Qwen、Zhipu 都支持 Function Calling，但请求链路没有处理 `tools` 参数。`parse_chat_response()` 有提取 `tool_calls` 的代码，但没有工具执行循环。这是 AI Agent 能力的基础。

#### P2: 没有 Prompt 模板系统

`translate()` 和 `summarize()` 的 prompt 是硬编码的。缺少可扩展的 Prompt 模板系统（变量替换、多语言、用户自定义）。

#### P3: 双系统架构维护负担

PublicAPI 和 WP AI Client SDK 是独立系统。通过 Gutenberg AI 功能发出的请求不走 WPMind 的路由/故障转移。任何功能添加都要考虑两条路径。

---

## 3. 已确认正常的功能

| 功能 | 状态 | 说明 |
|------|------|------|
| chat() 端到端链路 | ✅ 正常 | 路由→故障转移→HTTP→响应解析 完整工作 |
| 智能路由接入 | ✅ 正常 | RoutingHooks 通过 wpmind_select_provider filter 接入 |
| 故障转移链 | ✅ 正常 | CircuitBreaker + FailoverManager 工作正常 |
| 翻译缓存 | ✅ 正常 | 默认 86400s TTL |
| 递归调用保护 | ✅ 正常 | begin_call/end_call 防止无限递归 |
| WP AI Client SDK 集成 | ✅ 正常 | 8 个国产 Provider 正确注册 |
| 用量追踪 | ✅ 正常 | http_api_debug hook 捕获两条管线的请求 |

---

## 4. Codex 第一轮讨论 (2026-02-07)

### 4.1 严重程度调整

| 问题 | 原评级 | Codex 建议 | 最终评级 | 理由 |
|------|--------|-----------|----------|------|
| C1 Anthropic/Google 不可用 | Critical | High/Important | **Important** | WP AI Client SDK 管线仍可用，PublicAPI 非唯一入口 |
| I1 重试逻辑死代码 | Important | 同意 | **Important** | 429/503 直接跳过健康 Provider |
| I2 stream/embed 绕过路由 | Important | 同意 | **Important** | 功能一致性问题 |
| I3 Streaming 实现问题 | Important | Medium | **Medium** | 前端 SSE 是产品缺口非 bug |
| I4 无速率限制 | Important | Medium/Important | **Medium** | 非功能性 bug |
| I5 batch/Qwen 阻塞 | Important | Medium/Low | **Medium/Low** | 性能风险非功能 bug |
| P1 Function Calling | 产品缺失 | Low/Medium | **Low/Medium** | 已支持传入 tools，缺执行循环 |
| P2 Prompt 模板 | 产品缺失 | Low | **Low** | 纯产品增强 |
| P3 双系统架构 | 产品缺失 | Medium/High | **Medium/High** | 中长期技术债务 |

### 4.2 Codex 发现的新问题

#### N1: 缓存键未包含 provider/model (Important)

`generate_cache_key()` 只依赖 `$args`（messages/temperature），不含 provider/model。
多 Provider/多模型下可能混用缓存结果。

**位置**: `PublicAPI.php:1684`

#### N2: Failover 时模型不重选 (Important)

`do_chat()` 在 Provider 决定前就把 model 固化为默认 Provider 的模型。
Failover 到其他 Provider 时仍沿用该模型，可能导致"模型不存在"错误。
`execute_chat_request()` 只有在 `model=auto` 时才会改用目标 Provider 的默认模型。

**位置**: `PublicAPI.php:276` → `PublicAPI.php:1470`

#### N3: 非 JSON 响应会触发 TypeError (Important)

`execute_chat_request()` 没检查 `json_decode` 失败就调用 `parse_chat_response()`（类型要求 array）。
遇到 HTML 错误页（如 Cloudflare 502 页面）可能直接 fatal。

**位置**: `PublicAPI.php:1526-1562`

#### N4: stream() 默认 Provider 与 chat() 不一致 (Medium)

`stream()` 默认 `deepseek`，`chat()` 默认从配置取值（缺省 `openai`）。
可能导致"流式不可用但普通聊天可用"的体验不一致。

**位置**: `PublicAPI.php:543` vs `PublicAPI.php:287`

### 4.3 C1 解决方案共识

**长期方案**: 统一 PublicAPI 的执行层到 WP AI Client SDK
- PublicAPI 保留路由/故障转移/缓存逻辑
- 把"实际请求执行"委托给 WP AI Client SDK 的 Provider
- 同时解决 C1 + P3

**短期止血**: 在 PublicAPI 增加 Anthropic/Google 适配器（不推荐，加重双管线维护）

### 4.4 I1 重试逻辑共识

**方案**: 在每个 Provider 的尝试内部做小规模重试，然后再故障转移
- 对 429：若存在其他 Provider，重试 0-1 次；若是最后一个 Provider，充分重试
- 对 5xx/超时：适度重试（1-2 次，指数退避）
- 需要从 error_data 读取 HTTP status 才能调用 should_retry

### 4.5 Top 3 优先级共识

1. **统一 PublicAPI 执行层到 WP AI Client SDK** (解决 C1 + P3)
2. **per-provider 重试 + failover 模型重选** (解决 I1 + N2)
3. **stream/embed 等接入路由与故障转移** (解决 I2 + N4)

---

## 5. 第二轮评审与 Codex 讨论 (2026-02-07)

### 5.1 统一执行层方案共识

**当前调用路径**: `PublicAPI::chat()` → `do_chat()` → `execute_chat_request()` → `wp_remote_post()` → `parse_chat_response()`

**目标调用路径**: `PublicAPI::chat()` → `do_chat()` → `execute_via_sdk()` → WP AI Client SDK → Provider

**关键决策**:
- PublicAPI 保留路由/故障转移/缓存逻辑（`do_chat()` 外层循环不变）
- 把 `execute_chat_request()` 替换为 SDK 调用，每个 provider 用 SDK 执行一次
- **必须保留 `execute_chat_request()` 作为 fallback**（SDK 不可用时回退）
- 健康统计（`record_result`）需迁移到新执行点
- 缓存键需加入 provider/model

**阻塞项**: WP AI Client SDK 的具体 API 接口需要先调研（仓库中只确认了 `AiClient::defaultRegistry()`）

### 5.2 重试逻辑方案共识

**位置**: `do_chat()` 的 failover 循环内部，包裹 `execute_chat_request()`

**逻辑**:
```
foreach failover_chain as provider:
    for retry = 0 to max_retries:
        result = execute_chat_request(provider)
        if success: break both loops
        if not retryable (401/403/key missing): break inner, continue outer
        if retryable (429/5xx/timeout):
            if has_more_providers and retry >= 1: break inner, continue outer
            if last_provider: sleep(get_retry_delay(retry)), continue inner
    record_failure(provider)
```

**参数**:
- 非最后 provider: 最多 1 次重试
- 最后 provider: 最多 3 次重试，指数退避 1s→2s→4s
- HTTP status 从 `WP_Error::get_error_data()['status']` 提取
- 网络错误（无 status）视为可重试

### 5.3 模型重选方案共识

**方案**: auto 下移 + 显式模型保持 + 不支持时自动回退

1. 用户传 `model=auto`: 在 failover 循环内每个 provider 动态调用 `get_current_model($try_provider)`
2. 用户传显式模型: 先尝试原模型，若目标 provider 不支持则回退到该 provider 默认模型，并在结果中标注 `model_fallback: true`
3. 不维护模型映射表（维护成本高，模型更新频繁）

### 5.4 实施顺序共识

```
Phase A: 基础修复（不依赖 SDK 调研）
  ├── A1: 缓存键加入 provider/model
  ├── A2: 非 JSON 响应防护（json_decode 失败检查）
  ├── A3: stream() 默认 provider 与 chat() 统一
  └── A4: per-provider 重试逻辑接入

Phase B: 模型重选 + 路由统一
  ├── B1: failover 模型重选（auto 下移 + 显式回退）
  └── B2: stream/embed/transcribe/speech 接入路由和故障转移

Phase C: 执行层统一（依赖 SDK 调研）
  ├── C0: 调研 WP AI Client SDK 的 chat/stream API
  ├── C1: execute_chat_request() 委托给 SDK（保留 fallback）
  └── C2: 健康统计迁移到新执行点
```

### 5.5 风险控制

| 风险 | 缓解措施 |
|------|----------|
| SDK 接口不明确 | Phase A/B 不依赖 SDK，可先行；C0 调研后再决定 C1/C2 |
| 重试导致延迟增长 | 限制总重试次数和等待时长，非最后 provider 最多 1 次 |
| 模型回退语义变化 | 结果中标注 `model_fallback`，不静默替换 |
| 缓存键变更导致缓存失效 | 一次性失效可接受，新键更准确 |
| 破坏现有 filter 语义 | 保留 `wpmind_select_provider`/`wpmind_chat_args` 不变 |
| 返回结构变化 | 保持 `content/provider/model/usage` 字段不变 |

---

## 6. 最终任务计划

### Phase A: 基础修复 → v3.4.0

> 不依赖 SDK 调研，可立即开始。修复已确认的 bug 和可靠性问题。

| 任务 | 优先级 | 涉及文件 | 说明 |
|------|--------|----------|------|
| **A1: 缓存键加入 provider/model** | P0 | `PublicAPI.php:1684` | `generate_cache_key()` 加入 provider 和 model 参数，避免跨 provider 缓存污染 |
| **A2: 非 JSON 响应防护** | P0 | `PublicAPI.php:1526-1562` | `json_decode` 失败时返回 WP_Error 而非传 null 给 `parse_chat_response()` |
| **A3: stream() 默认 provider 统一** | P0 | `PublicAPI.php:543` | 与 `chat()` 使用相同的默认 provider 获取逻辑 |
| **A4: per-provider 重试逻辑** | P1 | `PublicAPI.php:321-344`, `ErrorHandler.php` | 在 failover 循环内接入 `should_retry()` + `get_retry_delay()`，激活死代码 |

**验收标准**:
- `json_decode` 失败不再 fatal
- 缓存键包含 provider/model，不同 provider 不会命中同一缓存
- stream() 和 chat() 默认 provider 一致
- 429/503 错误先重试再 failover，可通过日志确认重试行为

---

### Phase B: 模型重选 + 路由统一 → v3.5.0

> 依赖 Phase A 完成。提升故障转移成功率和功能一致性。

| 任务 | 优先级 | 涉及文件 | 说明 |
|------|--------|----------|------|
| **B1: failover 模型重选** | P0 | `PublicAPI.php:276-323` | model=auto 下移到循环内；显式模型不支持时回退到目标 provider 默认模型并标注 |
| **B2: stream() 接入路由和故障转移** | P1 | `PublicAPI.php:527-636` | 使用 `wpmind_select_provider` filter + `FailoverManager::get_failover_chain()` |
| **B3: embed() 接入路由和故障转移** | P1 | `PublicAPI.php:809-911` | 同 B2 |
| **B4: transcribe/speech 接入路由** | P2 | `PublicAPI.php:1150, 1294` | 同 B2，优先级较低（使用频率低） |

**验收标准**:
- failover 到不同 provider 时不再出现"模型不存在"错误
- stream/embed 走路由和故障转移，与 chat() 行为一致
- 结果中包含 `model_fallback` 标记（当模型被自动替换时）

---

### Phase C: 执行层统一 → v3.6.0 (依赖 SDK 调研)

> 依赖 C0 调研结果。长期架构优化，解决双系统问题。

| 任务 | 优先级 | 涉及文件 | 说明 |
|------|--------|----------|------|
| **C0: WP AI Client SDK API 调研** | P0 | 外部 SDK | 确认 SDK 的 chat/stream/embed 接口、请求/响应格式、错误处理方式 |
| **C1: execute_chat_request() 委托给 SDK** | P1 | `PublicAPI.php:1447` | 保留原实现作为 fallback，SDK 可用时优先使用 SDK |
| **C2: 健康统计迁移** | P1 | `PublicAPI.php:1516, 1546` | `record_result` 迁移到 SDK 执行路径 |
| **C3: Anthropic/Google 通过 SDK 可用** | P1 | `PublicAPI.php` | 统一后自动解决，无需单独适配 |

**验收标准**:
- `wpmind_chat()` 指定 Anthropic/Google 时正常工作
- SDK 不可用时自动回退到原 `execute_chat_request()`
- 健康统计在两条路径下都正常记录

---

### 版本规划总览

```
v3.3.0 (当前) — snake_case 重构完成
    ↓
v3.4.0 — Phase A: 基础修复（缓存/JSON防护/重试）
    ↓
v3.5.0 — Phase B: 模型重选 + 路由统一
    ↓
v3.6.0 — Phase C: 执行层统一到 SDK
```

---

## 7. Phase A/B 实施记录

### Phase A (v3.4.0) — 已完成 2026-02-07

| 任务 | 状态 | 说明 |
|------|------|------|
| A1: 缓存键加入 provider/model | ✅ 完成 | `generate_cache_key()` 新增参数，4 处调用点更新 |
| A2: 非 JSON 响应防护 | ✅ 完成 | `json_decode` 失败返回 `wpmind_invalid_response` WP_Error |
| A3: stream() 默认 provider 统一 | ✅ 完成 | 硬编码 `deepseek` → `get_option()` |
| A4: per-provider 重试逻辑 | ✅ 完成 | 激活 `ErrorHandler::should_retry()` + `get_retry_delay()` |

### Phase B (v3.5.0) — 已完成 2026-02-07

| 任务 | 状态 | 说明 |
|------|------|------|
| B1: failover 模型重选 | ✅ 完成 | model=auto 下移到循环内，显式模型回退 + model_fallback 标记 |
| B2: stream() 接入路由和故障转移 | ✅ 完成 | wpmind_select_provider + FailoverManager + 健康记录 |
| B3: embed() 接入路由和故障转移 | ✅ 完成 | 同 B2 模式，embed model 动态选择 |
| B4: transcribe/speech 接入路由 | ✅ 完成 | 能力过滤（transcribe 仅 OpenAI，speech 支持 OpenAI+DeepSeek） |

---

## 8. C0: WP AI Client SDK 调研报告 (2026-02-07)

### 8.1 SDK 架构

```
WordPress AI 插件 (/wp-content/plugins/ai/)
└── vendor/wordpress/
    ├── wp-ai-client/          # WordPress 集成层
    │   └── includes/
    │       ├── AI_Client.php  # WordPress 入口
    │       └── HTTP/          # WordPress HTTP 客户端
    └── php-ai-client/         # 核心 SDK
        └── src/
            ├── AiClient.php           # 主入口（静态方法）
            ├── Providers/
            │   ├── ProviderRegistry.php
            │   └── ProviderImplementations/
            │       ├── OpenAi/
            │       ├── Anthropic/
            │       └── Google/
            ├── Builders/
            │   └── PromptBuilder.php  # 链式 API
            └── Results/
                └── DTO/
                    └── GenerativeAiResult.php
```

### 8.2 SDK 核心 API

#### Text Generation（链式 API）

```php
use WordPress\AiClient\AiClient;

// 指定 Provider + 参数
$result = AiClient::prompt($messages)
    ->usingProvider('deepseek')
    ->usingTemperature(0.7)
    ->usingMaxTokens(1000)
    ->usingSystemInstruction('You are helpful')
    ->generateTextResult();

// 指定模型实例
$registry = AiClient::defaultRegistry();
$model = $registry->getProviderModel('deepseek', 'deepseek-chat');
$result = AiClient::prompt($messages)
    ->usingModel($model)
    ->generateTextResult();
```

#### 响应格式：GenerativeAiResult

```php
$result->toText();                                    // string: 文本内容
$result->getProviderMetadata()->getId();               // string: provider ID
$result->getModelMetadata()->getId();                  // string: model ID
$result->getTokenUsage()->getPromptTokens();           // int
$result->getTokenUsage()->getCompletionTokens();       // int
$result->getTokenUsage()->getTotalTokens();            // int
$result->getCandidates()[0]->getFinishReason()->value;  // string: finish_reason
```

#### 错误处理

SDK 使用**异常**（非 WP_Error）：
- `InvalidArgumentException`: 参数错误
- `RuntimeException`: 运行时错误（无可用模型等）

### 8.3 SDK 能力矩阵

| 功能 | SDK 支持 | 可委托给 SDK | 备注 |
|------|---------|-------------|------|
| Text Generation | ✅ 支持 | ✅ 可以 | `prompt()->generateTextResult()` |
| JSON Mode | ✅ 支持 | ✅ 可以 | `asJsonResponse($schema)` |
| Function Calling | ✅ 支持 | ✅ 可以 | `usingFunctionDeclarations()` |
| Streaming | ❌ 不支持 | ❌ 不行 | 保留 PublicAPI fopen/fgets |
| Embedding | ❌ 未实现 | ❌ 不行 | 保留 PublicAPI wp_remote_post |
| Image Generation | ✅ 支持 | ✅ 可以 | `generateImageResult()` |
| Text-to-Speech | ✅ 支持 | ✅ 可以 | `convertTextToSpeechResult()` |
| Failover/重试 | ❌ 不支持 | ❌ 不行 | 保留 PublicAPI FailoverManager |
| 缓存 | ❌ 不支持 | ❌ 不行 | 保留 PublicAPI transient |

### 8.4 PublicAPI ↔ SDK 兼容性

#### 请求参数映射

| PublicAPI 参数 | SDK 方法 | 兼容性 |
|---------------|---------|--------|
| messages | `prompt()` | ✅ |
| system | `usingSystemInstruction()` | ✅ |
| max_tokens | `usingMaxTokens()` | ✅ |
| temperature | `usingTemperature()` | ✅ |
| model | `usingModel()` / `usingProvider()` | ✅ |
| json_mode | `asJsonResponse()` | ✅ |
| tools | `usingFunctionDeclarations()` | ✅ |
| tool_choice | `setCustomOption()` | ⚠️ 需适配 |

#### 响应格式映射

| PublicAPI 字段 | SDK 方法 | 兼容性 |
|---------------|---------|--------|
| content | `toText()` | ✅ |
| provider | `getProviderMetadata()->getId()` | ✅ |
| model | `getModelMetadata()->getId()` | ✅ |
| usage.* | `getTokenUsage()->get*()` | ✅ |
| finish_reason | `getCandidates()[0]->getFinishReason()` | ✅ |
| tool_calls | `getMessage()->getParts()` | ⚠️ 需适配 |

### 8.5 关键发现

1. **SDK 只能替代 `execute_chat_request()` 中的 HTTP 调用**，路由/故障转移/缓存/重试全部保留在 PublicAPI
2. **渐进式迁移最安全**：SDK 优先，失败回退原 HTTP 实现
3. **stream/embed 无法委托**，Phase B 的路由改造仍然必要
4. **异常→WP_Error 转换**是适配层的核心工作

### 8.6 修订后的 Phase C 方案

```
Phase C: 执行层统一 → v3.6.0

C1: 创建 SDKAdapter 类
  ├── 封装 SDK 调用（PromptBuilder 链式 API）
  ├── 异常捕获 → WP_Error 转换
  ├── GenerativeAiResult → PublicAPI 数组格式转换
  └── tool_calls 适配

C2: execute_chat_request() 增加 SDK 路径
  ├── SDK 可用时优先使用 SDK
  ├── SDK 失败时回退到原 HTTP 实现
  ├── should_use_sdk() 判断逻辑
  └── 健康统计在两条路径下都正常记录

C3: 验证 Anthropic/Google 通过 SDK 可用
  ├── 配置 Anthropic API Key 后测试 wpmind_chat()
  ├── 配置 Google API Key 后测试 wpmind_chat()
  └── 确认 failover 链中 Anthropic/Google 正常工作

C4: 集成验证 + 版本发布
```

**与原方案的差异**：
- 新增 SDKAdapter 类（原方案直接在 PublicAPI 中调用 SDK）
- 明确了 SDK 不支持 streaming/embedding 的限制
- 渐进式迁移策略（SDK 优先 + HTTP 回退）

---

## 9. Codex 评审 Phase C 方案 (2026-02-07)

### 9.1 评审问题与共识

#### Q1: SDKAdapter 独立类 vs 内联到 PublicAPI

**Codex 评级**: A（推荐独立类）

- 适配层职责清晰：异常→WP_Error、结果映射、tool_calls 组装集中在边界层
- 更好测试与回滚：SDK 兼容问题被单测集中覆盖
- 便于未来扩展：SDK 升级时改动范围小

**共识**: ✅ 采用独立类 `includes/SDK/SDKAdapter.php`

#### Q2: should_use_sdk() 判断逻辑

**Codex 评级**: A-（推荐方案 C：可配置 + 默认安全）

- 默认策略：仅对已验证 Provider（Anthropic/Google）启用 SDK
- 用户可通过 `wpmind_sdk_enabled` 选项配置
- 能力 gate：stream/embed 请求永远不走 SDK，tools 请求 v3.6.0 暂不走 SDK

**共识**: ✅ 可配置 + 默认 Anthropic/Google + 能力 gate

#### Q3: SDK 失败回退 HTTP 时的重试逻辑

**Codex 评级**: B+（按错误分类重试预算）

- SDK 适配/参数错误（如 tool_choice 未适配）：不消耗重试预算，直接走 HTTP
- Provider 错误（429/5xx/超时）：算作一次失败，HTTP 使用剩余重试预算

**共识**: ✅ 按错误分类处理

#### Q4: tool_calls 适配

**Codex 评级**: B（要么做全要么禁用）

- 不适配 tool_calls 会导致"看似可用但结果缺失"的隐性回归
- v3.6.0 先在 should_use_sdk 中对 tools 请求禁用 SDK
- 后续版本再实现完整 tool_calls 适配

**共识**: ✅ v3.6.0 先禁用，后续版本实现

#### Q5: 遗漏的风险点

**Codex 评级**: A（需补充缓解措施）

| 风险 | 缓解措施 |
|------|----------|
| 错误语义丢失（异常→WP_Error 时 HTTP 状态码映射） | SDKAdapter 中解析异常 message 提取状态码 |
| Model/Provider ID 差异 | SDKAdapter 中维护 ID 映射表 |
| token usage 口径差异 | SDKAdapter 统一转换为 PublicAPI 格式 |
| JSON mode schema 兼容性 | 测试验证，不兼容时回退 HTTP |
| 依赖/加载冲突 | class_exists 检查 SDK 可用性 |
| 性能/内存开销 | 监控 SDK 路径延迟，异常时自动回退 |

#### Q6: 实施顺序

**Codex 评级**: A（最小可验证路径）

```
1. SDKAdapter 类（异常映射、结果映射）
2. should_use_sdk() 能力 gate + Provider 白名单
3. execute_chat_request() SDK 路径 + 回退逻辑
4. 基础测试/集成验证（Anthropic/Google）
5. metrics/统计两条路径一致
6. 版本发布 v3.6.0
```

### 9.2 最终任务计划

#### Phase C: 执行层统一 → v3.6.0

| 任务 | 优先级 | 涉及文件 | 说明 |
|------|--------|----------|------|
| **C1: SDKAdapter 类** | P0 | `includes/SDK/SDKAdapter.php` (新建) | 封装 SDK 调用、异常→WP_Error、GenerativeAiResult→数组、ID 映射 |
| **C2: should_use_sdk() 能力 gate** | P0 | `includes/API/PublicAPI.php` | 可配置 + 默认 Anthropic/Google + stream/embed/tools 禁用 |
| **C3: execute_chat_request() SDK 路径** | P0 | `includes/API/PublicAPI.php` | SDK 优先 + HTTP 回退 + 按错误分类重试 + 健康统计 |
| **C4: 集成验证 + 版本发布** | P1 | 多文件 | PHP 语法、部署测试、版本号、CHANGELOG |

**验收标准**:
- `wpmind_chat()` 指定 Anthropic/Google 时通过 SDK 正常工作
- SDK 不可用时自动回退到原 HTTP 实现
- stream/embed/tools 请求不走 SDK
- 健康统计在两条路径下都正常记录
- 用户可通过 `wpmind_sdk_enabled` 选项控制

---

*文档更新时间: 2026-02-07*
*基于 Claude + Codex 多轮讨论 + SDK 源码调研*
