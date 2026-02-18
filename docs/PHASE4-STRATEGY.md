# WPMind Phase 4 战略方向讨论

> 讨论日期: 2026-02-07 | 状态: 讨论中

---

## 背景

WPMind v3.8.0 完成了 Phase 3.5 架构优化的全部收尾工作（兼容层清理、Provider 懒加载、路由策略可插拔）。现在需要决定 Phase 4 的方向。

## 原 Roadmap Phase 4 的同质化问题

原规划的 Phase 4 功能存在严重同质化：

| 规划功能 | 已有竞品 | 竞争壁垒 |
|----------|----------|----------|
| Content Assistant (标题/摘要) | Jetpack AI、Flavor AI、AI Engine | **低** |
| Auto-Meta (自动标签) | Rank Math AI、FLAVOR、SEOPress | **低** |
| Comment Intelligence | Akismet + AI 审核插件 | **低** |
| Media Intelligence (alt text) | Flavor AI、AltText.ai | **低** |
| Translation | TranslatePress、WPML、Weglot | **极低** |

**核心问题：任何能调 OpenAI API 的插件都能做这些功能，WPMind 没有结构性优势。**

## WPMind 的真正护城河

已建成的独特资产：

1. **多 Provider 智能路由** — 8+ 国产 AI 统一接入 + 自动故障转移，没有第二家
2. **成本控制系统** — 跨 Provider 预算管理，对标企业级需求
3. **WordPress AI Client SDK 深度集成** — 官方基础设施的增强层
4. **`wpmind_*()` 全局函数 API** — 15 个标准化函数，完整的开发者 API
5. **可插拔扩展点** — `wpmind_provider_map` filter + `wpmind_register_routing_strategies` action

## 三个差异化方向

### 方向 A: AI 基础设施层（平台策略）⭐ 推荐

**不做终端功能，做其他插件的 AI 引擎。**

```
现在:  用户 → WPMind → AI Provider
未来:  用户 → [任何插件] → WPMind API → 智能路由 → 最优 Provider
```

具体做法：
- 发布 `wpmind_*()` 为稳定的开发者 SDK 文档
- 为热门插件做集成包（Rank Math 用 WPMind 路由、WooCommerce 用 WPMind 生成产品描述）
- 其他插件调 `wpmind_chat()` 就自动获得路由/failover/成本控制
- **收入模式**：免费 API 调用额度 + 付费提升额度

**优势**：不跟任何插件竞争，反而成为它们的依赖。类似 Stripe 之于电商。

### 方向 B: MCP Server（最创新）

**让 AI 助手（Claude/ChatGPT）直接管理 WordPress 站点。**

具体做法：
- WPMind 暴露 MCP Server 端点
- Claude Desktop / ChatGPT 通过 MCP 协议操作 WordPress
- 支持：发布文章、管理评论、查看统计、修改设置、SEO 优化
- 结合 WPMind 的 AI 能力做智能操作

**优势**：全新品类，没有竞品。WordPress + MCP 的结合点只有 WPMind 有技术基础做。

### 方向 C: 中国 AI 生态专精

**利用国产模型的独特优势做别人做不了的事。**

- 中文内容质量优化（国产模型中文能力远超 GPT）
- 跨境内容桥接（中文 ↔ 英文文化适配）
- 国内平台集成（微信公众号/小红书/知乎内容同步生成）
- 合规审核（中国互联网内容合规检测）

**优势**：国际插件做不了，因为它们不接国产 AI。

## 建议优先级

**A > B > C**

方向 A 投入产出比最高（API 层已建好），方向 B 最有话题性和创新性，方向 C 作为补充。

原 Roadmap 的 Content Assistant 等功能可作为**演示 WPMind API 能力的示例模块**，而非核心卖点。

## 外部参考研究

### 参考 1: WordPress MCP Adapter（官方）

> 来源: https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/
> 发布: 2026-02-04 | WordPress Developer Blog

**核心架构：**

WordPress 6.9 引入了 **Abilities API**（`wp_register_ability()`），提供标准化、可发现、类型化、可执行的功能注册机制。MCP Adapter 是官方的桥接层，将 Abilities 映射为 MCP 的三种原语：

| MCP 原语 | 用途 | WordPress 映射 |
|----------|------|----------------|
| **Tools** | 可执行函数 | Abilities（默认映射） |
| **Resources** | 只读数据源 | 只读 Abilities（如日志、配置） |
| **Prompts** | 预配置模板 | 工作流模板 |

**关键技术细节：**

1. **Ability 注册**：`namespace/ability-name` + typed schema + `permission_callback` + `execute_callback`
2. **MCP 暴露**：需设置 `meta.mcp.public = true`，或通过自定义 MCP Server 显式指定
3. **传输方式**：
   - **STDIO**（本地）：通过 WP-CLI `wp mcp-adapter serve --server=xxx --user=admin`
   - **HTTP**（远程）：通过 `@automattic/mcp-wordpress-remote` 代理 + Application Passwords
4. **自定义 MCP Server**：`composer require wordpress/mcp-adapter` → `mcp_adapter_init` action → `$adapter->create_server()`
5. **默认工具**：`mcp-adapter-discover-abilities`、`mcp-adapter-get-ability-info`、`mcp-adapter-execute-ability`

**对 WPMind 的启示：**

- WordPress 官方已经铺好了 MCP 基础设施，WPMind 不需要从零实现 MCP 协议
- WPMind 可以将自己的核心能力注册为 Abilities，自动获得 MCP 兼容性
- 关键机会：**WPMind 是唯一有多 Provider 路由能力的插件**，注册为 Ability 后，AI Agent 可以通过 MCP 直接使用 WPMind 的智能路由

### 参考 2: cc-switch（桌面工具）

> 来源: https://github.com/farion1231/cc-switch
> Stars: 16,663 | Forks: 1,025 | 语言: Rust (Tauri)

**产品定位：** 跨平台桌面 All-in-One 助手，管理 Claude Code / Codex / Gemini CLI 的配置。

**核心功能：**

| 功能 | 说明 | WPMind 对应 |
|------|------|-------------|
| Provider Management | API Key 切换、端点管理、速度测试 | ✅ WPMind 已有（更强） |
| MCP Server Management | 跨 CLI 统一管理 MCP 服务器 | 🔲 WPMind 可做 WordPress 侧 |
| Skills Management | 扫描 GitHub 仓库、一键安装 Skills | 🔲 新机会 |
| Prompts Management | 多预设系统提示词管理 | 🔲 新机会 |
| Speed Testing | API 端点延迟测量 | ✅ WPMind 已有 |
| Import/Export | 配置备份恢复 | 🔲 可做 |

**市场验证：**

- 16K+ Stars 证明 AI 工具管理有巨大需求
- 赞助商全是 API 中转服务商（PackyCode、AIGoCode、DMXAPI 等），说明**中国开发者是核心用户群**
- cc-switch 管理的是 CLI 端配置，WPMind 管理的是 WordPress 端配置 — **互补而非竞争**

**对 WPMind 的启示：**

- cc-switch 的成功验证了"AI 基础设施管理工具"的市场需求
- WPMind 可以成为 WordPress 生态的 cc-switch — 不是做终端 AI 功能，而是做 AI 基础设施管理
- Skills/Prompts 管理是值得借鉴的功能方向

---

## 综合分析：Phase 4 最优路径

### 核心洞察

三个信息源指向同一结论：**AI 基础设施层 > AI 应用层**。

```
cc-switch 的成功 = CLI 端 AI 基础设施管理的需求验证
WordPress MCP Adapter = WordPress 端 AI 基础设施的官方标准
WPMind 的机会 = 在 WordPress MCP 生态中做 AI 路由基础设施
```

### Phase 4 方案：A+B 融合

不再将方向 A（基础设施层）和方向 B（MCP Server）分开，而是融合为统一方案：

**WPMind 作为 WordPress AI Gateway，通过 Abilities API 暴露为 MCP 工具。**

#### 实施示例

**注册 WPMind Abilities：**

```php
wp_register_ability( 'wpmind/chat', [
    'schema' => [ /* prompt, model, options */ ],
    'permission_callback' => 'current_user_can("edit_posts")',
    'execute_callback' => 'wpmind_chat',
    'meta' => [ 'mcp' => [ 'public' => true ] ],
] );
```

**创建 MCP Server：**

```php
add_action( 'mcp_adapter_init', function( $adapter ) {
    $adapter->create_server(
        'wpmind-ai-gateway', 'wpmind', 'mcp',
        'WPMind AI Gateway',
        'Intelligent AI routing with multi-provider support',
        'v1.0.0',
        [ \WP\MCP\Transport\HttpTransport::class ],
        /* error/observability handlers */
        [ 'wpmind/chat', 'wpmind/get-providers', 'wpmind/get-usage-stats' ],
    );
} );
```

**AI Agent 使用场景：**

```
Claude Desktop → MCP → wpmind/chat → 智能路由 → DeepSeek（最优性价比）→ 返回内容
```

---

## 竞争情报（2026-02-07 联网调研）

### WordPress MCP 领域已有竞品

| 竞品 | 安装量 | 核心能力 | WPMind 差异 |
|------|--------|----------|-------------|
| **StifLi Flex MCP** | 400+ | 117 个 MCP 工具（55 WP + 61 WooCommerce）、内置 AI Chat、自定义工具系统 | **无路由/failover/成本控制** |
| **WooCommerce 官方 MCP** | 内置 | WooCommerce 数据 CRUD | 仅电商，无 AI 路由 |
| **WordPress Abilities Extended** | 新 | 扩展 Core Abilities | 工具集，非路由层 |
| **AIOHM** | 新 | AI 知识库 + MCP Server | 单 Provider，无路由 |

**关键发现：所有竞品都是"MCP 工具集"（暴露 WordPress CRUD），没有一个做"AI 路由层"。**

WPMind 不应复制 StifLi 的 117 个工具，而应做**它们都缺少的路由/成本/分析基础设施**。

```
竞品定位:  AI Agent → [StifLi/WAE] → 单个 Provider（用户手动选）
WPMind 定位: AI Agent → WPMind MCP Gateway → 智能路由 → 最优 Provider
```

### 语义缓存市场验证

- Upstash、Athenic 等方案验证语义缓存可降低 40-80% 成本
- WordPress/PHP 环境需轻量方案（SimHash 而非全向量检索）
- 应分层实现：精确缓存（P0）+ 语义缓存（P1）

> 参考: [Upstash Semantic Caching](https://upstash.com/blog/semantic-caching-for-speed-and-savings) | [Athenic AI Agent Caching](https://getathenic.com/blog/semantic-caching-ai-agents-cost-latency)

---

## Codex 评审反馈（2026-02-07）

> 评审模型: gpt-5.2-codex | 模式: full-auto

### 采纳的建议

1. **Response Cache 拆分**：精确缓存（P0）+ 语义缓存（P1），避免前后倒置
2. **Embeddings 提升到 P1**：语义缓存的前置依赖
3. **补充平台化基础设施**：计量计费、可观测性、Provider Health（部分已有，需增强）
4. **Batch AI + Automation 合并**为"任务引擎"，共享队列/重试/监控
5. **MCP Gateway 版本兼容**：WP 6.5+ 提供轻量降级
6. **复杂度重新评估**：整体偏乐观，需上调

### 部分采纳的建议

7. **中国市场合规**（PIPL、内容安全）：纳入考虑，但非 P0
8. **多站点支持**：后续版本考虑
9. **SDK 文档独立模块化**：作为 MCP Gateway 的子任务

### 未采纳的建议

10. **微信/支付宝支付集成**：WPMind 是开源插件，不做 SaaS 计费
11. **私有云部署方案**：超出插件范畴

---

## 修订后的模块规划（v2）

> 核心原则：利用 WPMind 的多 Provider 路由优势，做竞品做不了的事。
> 不做"又一个 MCP 工具集"，做"MCP 工具的 AI 路由层"。

### Tier 0: 平台基础设施 — 必须先行

#### 模块 0a: MCP Gateway（P0）

将 WPMind 核心能力注册为 WordPress Abilities，暴露 MCP 端点。

**聚焦路由/成本/分析能力，不做 WordPress CRUD（StifLi 已覆盖）：**

| Ability | 功能 | 竞品有无 |
|---------|------|----------|
| `wpmind/chat` | 智能路由 AI 对话 | ❌ 无 |
| `wpmind/get-providers` | 查询可用 Provider 及状态 | ❌ 无 |
| `wpmind/get-usage-stats` | 用量/成本统计 | ❌ 无 |
| `wpmind/get-budget-status` | 预算状态查询 | ❌ 无 |
| `wpmind/switch-strategy` | 切换路由策略 | ❌ 无 |

- 版本兼容：WP 6.9+ 使用官方 Abilities API；WP 6.5-6.8 提供轻量 Ability Registry 降级
- 子任务：权限模型（`permission_callback` 按角色隔离）、审计日志
- 复杂度: **中高**（权限、HTTP transport、安全、兼容性）
- 壁垒: **极高**

#### 模块 0b: Exact Cache — 精确缓存（P0）

基于请求哈希的精确匹配缓存，零依赖即可部署。

- 请求归一化（prompt + model + temperature + 角色/站点隔离）→ SHA256 哈希
- 命中缓存直接返回，跳过 API 调用
- 可配置 TTL、缓存大小上限、命中率统计面板
- 跨 Provider 共享（相同请求不同 Provider 的缓存可复用）
- **预期降低 20-30% API 成本**（重复请求场景）
- 复杂度: **中**（缓存键设计、隔离策略、失效机制）
- 壁垒: **中高**（跨 Provider 缓存键归一化）

#### 模块 0c: 可观测性增强（P0）

增强现有 analytics 和 cost-control 模块，补齐平台化缺口。

- 路由决策日志（每次请求记录：选择了哪个 Provider、为什么、耗时、成本）
- Provider 健康面板（延迟、成功率、熔断状态可视化）
- 成本趋势图（日/周/月维度，按 Provider 分组）
- 缓存命中率统计（Exact Cache 模块的配套）
- 复杂度: **中**（现有模块增强，非从零开发）

### Tier 1: 核心应用模块

#### 模块 1a: Embeddings — 向量基础设施（P1）

提供 `wpmind_embed()` API，支持多 Provider embedding 模型路由。

- OpenAI text-embedding-3-small、Qwen embedding、智谱 embedding 等
- 向量存储：WordPress 自定义表（小规模）或外部向量库（Qdrant/pgvector，可选）
- 为语义缓存、RAG、内容推荐提供基础
- 复杂度: **高**（向量存储/索引、性能、成本）
- 壁垒: **中高**

#### 模块 1b: Semantic Cache — 语义缓存（P1）

基于 Embeddings 模块的语义级缓存，依赖模块 1a。

- 请求 embedding → 相似度检索 → 阈值命中返回缓存
- 仅对可泛化场景启用（标题/摘要/翻译类请求）
- 可配置相似度阈值、动态调优
- 支持降级：无 Embeddings 时自动回退到 Exact Cache
- WordPress 环境适配：轻量近似算法（SimHash/MinHash）作为默认，外部向量库作为可选
- **在 Exact Cache 基础上额外降低 20-30% 成本**
- 复杂度: **高**（阈值调优、缓存污染风险、隐私隔离）
- 壁垒: **高**

#### 模块 1c: Task Engine — 任务引擎（P1）

合并原 Batch AI + AI Automation 为统一任务引擎。

**批量处理能力：**

| 批量任务 | 自动路由策略 |
|----------|-------------|
| 1000 张图片生成 alt text | 选最便宜的 Provider（DeepSeek ~¥2） |
| 500 篇文章生成 SEO meta | 按 token 成本路由 |
| 全站内容翻译 | 中→英用 Qwen，英→中用 Claude |

**事件驱动能力：**

| 触发器 | AI 动作 |
|--------|---------|
| 文章发布 (publish_post) | 自动生成 SEO meta + 摘要 |
| 新评论 (wp_insert_comment) | 情感分析 + 垃圾检测 |
| WooCommerce 新订单 | 自动生成感谢邮件 |
| 定时任务 (每周) | 内容质量报告 |

- 共享基础设施：Action Scheduler 队列、重试/失败补偿、任务可视化面板
- 用户在后台配置规则，零代码
- 复杂度: **中高**（队列、重试、UI 配置、任务监控）
- 壁垒: **中**（路由成本优化是差异点）

### Tier 2: 文派生态插件接口 — 为生态铺路

> 以下模块不再作为 WPMind 内置功能开发，而是作为独立的文派生态插件发布。
> WPMind 只需确保 API 稳定、文档完善、集成示例充分。

#### 文派内容（生态插件，原模块 2a）

独立插件，Gutenberg 侧边栏 AI 面板：

- 调用 `wpmind_chat()` 实现 AI 写作
- 显示路由选择、成本、Provider 对比
- 作为 WPMind API 的最佳实践示例

#### 文派客服（生态插件，原模块 2b）

独立插件，基于站点内容的 RAG 聊天机器人：

- 调用 `wpmind_chat()` + `wpmind_embed()` 实现 RAG
- 多 Provider failover 保证 24/7 可用

#### 文派电商（生态插件，原模块 2c）

独立插件，电商场景 AI 集成：

- 调用 `wpmind_chat()` + Task Engine 实现批量操作
- 产品描述生成、评论分析、智能推荐

### 修订后的优先级总览

**WPMind 核心（基础设施层）：**

| 优先级 | 模块 | 复杂度 | 理由 |
|--------|------|--------|------|
| **P0** | MCP Gateway | 中高 | 战略核心，聚焦路由能力而非 CRUD |
| **P0** | Exact Cache | 中 | 零依赖降成本，用户感知强 |
| **P0** | 可观测性增强 | 中 | 现有模块增强，平台化必需 |
| **P1** | Embeddings | 高 | 语义缓存/RAG 的前置依赖 |
| **P1** | Semantic Cache | 高 | Exact Cache 的进阶，额外降本 |
| **P1** | Task Engine | 中高 | 批量+自动化合一，生态插件的共享基础设施 |

**文派生态插件（应用层）：**

| 批次 | 插件 | 依赖的 WPMind 模块 | 理由 |
|------|------|-------------------|------|
| **第一批** | 文派批量、文派内容 | Task Engine, wpmind_chat() | 最能展示路由/成本优势 |
| **第二批** | 文派翻译、文派SEO | wpmind_chat() | 用户需求明确，市场大 |
| **第三批** | 文派客服、文派电商 | Embeddings, Task Engine | 依赖 Embeddings 成熟 |
| **第四批** | 文派审核、文派同步 | wpmind_chat() | 中国市场特色，按需求决定 |

### 实施路线

```
Phase 4.0 (P0):  MCP Gateway + Exact Cache + 可观测性增强
Phase 4.1 (P1):  Embeddings + Semantic Cache + Task Engine
Phase 4.2 (P2+): 文派生态插件（独立插件，原生集成 WPMind）
```

---

## 文派生态插件规划

> 核心策略：WPMind 专注 AI 基础设施，应用层功能由文派生态插件实现。
> 每个生态插件独立发布、独立定价，但都通过 `wpmind_*()` API 原生集成 WPMind。
> 这是 **Stripe 模式** — WPMind 不做终端功能，但所有 AI 功能都用 WPMind。

### 架构关系

```
┌─────────────────────────────────────────────────────┐
│                   文派生态插件（应用层）                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │ 文派翻译  │ │ 文派批量  │ │ 文派内容  │ │文派客服 │ │
│  │ WP Trans │ │ WP Batch │ │WP Content│ │WP Chat │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └───┬────┘ │
│       │            │            │            │      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │ 文派电商  │ │ 文派SEO  │ │ 文派审核  │ │文派同步 │ │
│  │WP Comrce │ │ WP SEO   │ │WP Review │ │WP Sync │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └───┬────┘ │
└───────┼────────────┼────────────┼────────────┼──────┘
        │            │            │            │
   ─────┴────────────┴────────────┴────────────┴──────
        │      wpmind_chat() / wpmind_embed()         │
   ───────────────────────────────────────────────────
┌─────────────────────────────────────────────────────┐
│              WPMind（AI 基础设施层）                   │
│  智能路由 │ 成本控制 │ MCP Gateway │ Cache │ Embeddings │
│  Failover │ Analytics │ Task Engine │ Provider Health  │
└─────────────────────────────────────────────────────┘
```

### 生态插件清单

| 插件 | 核心功能 | 调用的 WPMind API | 差异化优势 |
|------|----------|-------------------|-----------|
| **文派翻译** | 多语言内容翻译 | `wpmind_chat()` | 自动路由：中→英用 Qwen，英→中用 Claude，成本比 WPML AI 低 80% |
| **文派批量** | 批量 AI 操作（alt text、SEO meta、摘要） | `wpmind_chat()` + Task Engine | 自动选最便宜 Provider，1000 张图 alt text ~¥2 |
| **文派内容** | Gutenberg AI 写作助手 | `wpmind_chat()` | 透明路由体验：显示 Provider 选择、成本、可手动切换对比 |
| **文派客服** | RAG 聊天机器人 | `wpmind_chat()` + `wpmind_embed()` | 多 Provider failover 保证 24/7，基于站点内容的 RAG |
| **文派电商** | WooCommerce AI 集成 | `wpmind_chat()` + Task Engine | 产品描述批量生成、评论分析、智能推荐 |
| **文派SEO** | AI 驱动的 SEO 优化 | `wpmind_chat()` | 自动生成 meta、结构化数据、内链建议，成本可控 |
| **文派审核** | 内容合规检测 | `wpmind_chat()` | 国产模型中文合规能力强，PIPL/敏感词检测 |
| **文派同步** | 多平台内容分发 | `wpmind_chat()` | WordPress → 公众号/小红书/知乎，AI 适配各平台风格 |

### 生态插件的 WPMind 集成模式

每个生态插件遵循统一的集成模式：

```php
// 生态插件只需调用 wpmind_*() API，自动获得：
// ✅ 智能路由（自动选最优 Provider）
// ✅ 故障转移（Provider 挂了自动切换）
// ✅ 成本控制（预算内自动降级）
// ✅ 缓存命中（重复请求零成本）
// ✅ 用量统计（统一 Dashboard 可见）

// 示例：文派翻译插件的核心代码
$translated = wpmind_chat( [
    'prompt'  => "Translate to English:\n{$content}",
    'options' => [
        'task_type'  => 'translation',    // WPMind 按任务类型路由
        'max_tokens' => 2000,
        'cache'      => true,             // 启用缓存
    ],
] );
```

### 生态优势 vs 单插件模式

| 维度 | WPMind 单插件做所有功能 | 文派生态插件模式 |
|------|------------------------|-----------------|
| **开发效率** | 一个团队做所有 | 可并行开发，各插件独立迭代 |
| **市场定位** | "又一个 AI 插件" | "AI 基础设施 + 专业应用生态" |
| **用户选择** | 全有或全无 | 按需安装，灵活组合 |
| **定价灵活** | 单一定价 | WPMind 免费/低价 + 生态插件各自定价 |
| **竞争壁垒** | 每个功能都有竞品 | 生态整体无竞品（路由+成本+应用） |
| **第三方参与** | 封闭 | 开放 API，第三方也可开发生态插件 |

### 生态插件开发优先级

| 优先级 | 插件 | 理由 |
|--------|------|------|
| **第一批** | 文派批量、文派内容 | 最能展示 WPMind 路由/成本优势 |
| **第二批** | 文派翻译、文派SEO | 用户需求明确，市场大 |
| **第三批** | 文派客服、文派电商 | 依赖 Embeddings 模块成熟 |
| **第四批** | 文派审核、文派同步 | 中国市场特色，按需求决定 |

---

## 版本兼容策略

| WordPress 版本 | MCP Gateway 行为 |
|----------------|-----------------|
| 6.9+ | 使用官方 Abilities API + MCP Adapter |
| 6.5-6.8 | 轻量 Ability Registry 降级（自建 REST 端点模拟 Abilities） |
| < 6.5 | 仅提供 `wpmind_*()` PHP API，不支持 MCP |

---

## 中国市场特殊考虑

| 维度 | 策略 |
|------|------|
| **模型优先级** | 国产模型（DeepSeek/Qwen/智谱/豆包）作为默认路由首选 |
| **网络可用性** | 国外 Provider 不可用时自动切换国内（已有 failover） |
| **内容合规** | 后续版本考虑敏感词过滤/PIPL 合规检测模块 |
| **生态集成** | 方向 C 的公众号/小红书/知乎同步作为 Task Engine 的扩展任务类型 |
| **WP 版本现实** | 大量国内站点版本落后，版本兼容策略（见上表）是硬需求 |

---

## 风险清单

| 风险 | 等级 | 缓解措施 |
|------|------|----------|
| WP 6.9 Abilities API 普及慢 | **高** | 版本兼容策略，6.5+ 降级方案 |
| 语义缓存命中"错答" | **中** | 仅对可泛化场景启用，可配置阈值，支持关闭 |
| 缓存跨用户数据泄露 | **高** | 按站点/角色/语言/上下文切分缓存键 |
| Embedding 调用增加成本 | **中** | 异步预计算 + 缓存 embedding 结果 |
| StifLi 等竞品扩展路由能力 | **低** | WPMind 路由系统成熟度远超，短期无法追赶 |
| WordPress 共享主机性能限制 | **中** | 语义缓存可选关闭，Exact Cache 零额外开销 |

---

## 决策记录

| 日期 | 决策 | 依据 |
|------|------|------|
| 2026-02-07 | Phase 4 采用 A+B 融合方案（AI Gateway + MCP） | 竞品分析 + 护城河评估 |
| 2026-02-07 | 不做 WordPress CRUD MCP 工具（StifLi 已覆盖 117 个） | 联网竞品调研 |
| 2026-02-07 | Response Cache 拆分为 Exact（P0）+ Semantic（P1） | Codex 评审建议 |
| 2026-02-07 | Batch AI + Automation 合并为 Task Engine | Codex 评审建议 |
| 2026-02-07 | Embeddings 从 P2 提升到 P1 | 语义缓存前置依赖 |
| 2026-02-07 | 增加版本兼容策略（WP 6.5+ 降级） | Codex + 中国市场现实 |
| 2026-02-07 | 应用层功能改为文派生态插件独立发布 | Stripe 模式：基础设施 + 生态 |
| 2026-02-07 | 生态插件通过 `wpmind_*()` API 原生集成 | 避免单插件同质化竞争 |
