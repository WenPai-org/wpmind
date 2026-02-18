# WPMind 插件战略规划

> 让 WordPress 用户零门槛使用国产 AI

*文档版本: 3.7.0 | 创建日期: 2026-01-26 | 更新日期: 2026-02-07*

---

## 产品定位

### 核心使命

**从"技术桥接"升级为"用户赋能"**

```
传统模式:
用户 → 申请 API Key → 配置 → 使用 AI
      (复杂、门槛高、成本不透明)

WPMind 模式:
用户 → 安装插件 → 立即使用 AI
      (零配置、免费起步、智能路由)
```

### 产品架构

```
┌─────────────────────────────────────────────────────┐
│                    WordPress 用户                    │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│                  WPMind 插件                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │
│  │ 内容创作助手 │  │ 智能运营    │  │ GEO 优化    │  │
│  └─────────────┘  └─────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              文派心思 AI 统一接入                     │
│  ┌─────────────────────────────────────────────┐    │
│  │            智能路由引擎                       │    │
│  │  • 任务类型识别 → 最优模型选择                │    │
│  │  • 成本优化 → 简单任务用便宜模型              │    │
│  │  • 故障转移 → 自动切换备用                   │    │
│  │  • 负载均衡 → 高峰期分流                     │    │
│  └─────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              国产大模型集群                          │
│  DeepSeek │ 通义千问 │ 智谱 │ Moonshot │ 豆包 │ ...  │
└─────────────────────────────────────────────────────┘
```

### 核心价值主张

| 用户痛点 | WPMind 解决方案 |
|---------|----------------|
| 需要申请多个 API Key | 文派心思 AI 统一接入，零配置 |
| 不知道选哪个模型 | 智能路由自动选择最优模型 |
| API 成本不透明 | 免费版够用，专业版固定价 |
| 功能分散难上手 | 一站式内容创作助手 |
| 国际 API 访问不稳定 | 国产模型本地化服务 |

### 插件功能设计决策

> **2026-02-07 确立，所有后续功能设计必须遵守。**

**原则：WPMind 保持纯 AI 能力插件，争议性功能移至 WPCY。**

凡涉及以下特征的功能，**优先移至文派叶子 WPCY（slug: wp-china-yes）插件**实现：

| 特征 | 说明 | 示例 |
|------|------|------|
| **请求拦截** | 拦截/修改其他插件的 HTTP 请求 | OpenAI API 代理、商业插件 AI 请求屏蔽 |
| **UI 替换** | 隐藏/替换其他插件的界面元素 | Block+Hide+Inject 商业插件 AI 面板 |
| **地域特供** | 仅特定地区用户需要的功能 | 国内镜像加速、GFW 绕过 |
| **商业争议** | 可能与商业插件产生利益冲突 | 绕过付费 AI 服务、模拟商业 API 响应 |
| **ToS 灰区** | 可能违反第三方服务条款 | 逆向工程商业 API 格式 |

**WPMind 只做：**
- 提供 AI API（chat/translate/embed/vision/...）
- 提供功能模块（Content Assistant/GEO/Analytics/...）
- 通过标准 WordPress API 集成（post meta/SlotFill/Hooks）
- 面向全球用户，不含地域限制逻辑

**WPCY 负责：**
- 中国网络环境适配（镜像/加速/API 路由）
- 请求拦截和转发（Layer 1 + Layer 3）
- 检测 WPMind 安装状态，协同提供 AI 能力
- 地域特供的 UI 适配和开关

**协同机制：**
```
WPCY 检测 WPMind:
  ├── 已安装 → 调用 WPMind API（路由/模型选择/failover）
  └── 未安装 → 内置简单 DeepSeek 转发（降级方案）
```

---

## 商业模式

### 版本定价

| 版本 | 价格 | 目标用户 | 核心功能 |
|------|------|---------|---------|
| **免费版** | ¥0 | 个人博客、小站 | 基础 AI 功能 + 每日额度 |
| **专业版** | ¥99/月 | 企业站、内容站 | 无限额度 + 高级功能 + GEO |
| **企业版** | 定制 | 大型客户 | 私有部署 + 自定义模型 |

### 免费版功能

| 功能 | 每日额度 | 说明 |
|------|---------|------|
| 标题生成 | 50 次 | 每篇文章 5 个候选 |
| 摘要生成 | 30 次 | 自动提取文章摘要 |
| 内容润色 | 20 次 | 选中文字优化 |
| 错别字检查 | 无限 | 基础质量保障 |

### 专业版增值

| 功能 | 描述 |
|------|------|
| 无限 AI 调用 | 不限次数 |
| 智能模型路由 | 自动选择最优模型 |
| GEO 优化套件 | AI 搜索引擎优化 |
| 批量内容生成 | 新闻源自动化 |
| 评论智能管理 | 自动回复/审核 |
| 多语言翻译 | 一键全文翻译 |
| 优先技术支持 | 工单响应 |

---

## 开发路线图

### Phase 1: 基础架构 (v1.0-1.3) ✅ 已完成

- [x] Provider 架构设计与实现
- [x] 6 个国产 AI Provider
- [x] WordPress AI Client SDK 集成
- [x] 原生 AI 功能支持（标题生成已验证）
- [x] 设置页面与 API Key 管理

**技术实现亮点：**
- `AuthenticatedProviderAvailability` 解决认证传递
- `prepareGenerateTextParams()` 强制 `n=1` 适配国内 API
- `pre_option_` filter 合并凭据到 AI Client

### Phase 2: 核心功能 (v1.5-2.0) ✅ 已完成

- [x] 用量统计系统 (`includes/Usage/`)
- [x] 预算管理系统 (`includes/Budget/`)
- [x] 分析仪表板 (`includes/Analytics/`)
- [x] Gutenberg 风格设计系统
- [x] Tab 导航 UI
- [x] 响应式适配
- [x] Chart.js 图表集成

### Phase 2.5: 稳定性增强 (v2.0-2.5) ✅ 已完成

- [x] **智能路由系统** (`includes/Routing/`)
  - [x] 成本优先策略
  - [x] 延迟优先策略
  - [x] 可用性优先策略
  - [x] 负载均衡策略
  - [x] 复合策略（平衡/性能/经济）
- [x] **故障转移机制** (`includes/Failover/`)
  - [x] Provider 健康检查
  - [x] 自动跳过不健康 Provider
  - [x] 手动优先级设置
- [x] **官方 Filter 集成**
  - [x] 对齐 `ai_experiments_preferred_models_for_text_generation`
- [x] 更多 Provider 支持
  - [x] 百度文心 (`includes/Providers/Baidu/`)
  - [x] MiniMax (`includes/Providers/MiniMax/`)
  - [x] 图像生成 (`includes/Providers/Image/`)
- [x] UI 错误反馈优化
- [x] Toast 通知系统

### Phase 3: GEO 解决方案 (v3.0-3.2) ✅ 已完成

- [x] AI 搜索引擎可见性优化
- [x] 结构化数据增强（Schema.org）
- [x] 内容权威性信号优化
- [x] AI 引用友好格式生成
- [x] 中文内容优化 Prompt 模板
- [x] 批量内容生成（适配新闻源场景）
- [x] **集成官方 Markdown Feeds** (#194)
- [x] 模块化架构（GEO/Cost Control/Analytics 独立模块）

**SEO 插件扩展（独立扩展包）：** 📋 规划中
- [ ] WPMind GEO for Rank Math
- [ ] WPMind GEO for Yoast SEO
- [ ] WPMind GEO for Slim SEO
- [ ] WPMind GEO for AIOSEO
- [ ] WPMind GEO for SEOPress

### Phase 3.5: 架构优化 (v3.3-3.7) ✅ 已完成

- [x] 编码规范化：snake_case 重命名 (v3.3)
- [x] AI 请求链路审计 Phase A: 缓存键/JSON 防护/重试逻辑 (v3.4)
- [x] AI 请求链路审计 Phase B: 模型重选/路由统一 (v3.5)
- [x] AI 请求链路审计 Phase C: WP AI Client SDK 集成 (v3.6)
- [x] PublicAPI Facade 拆分为 6 个 Service 类 (v3.7)
- [x] Codex 安全审计修复（SSRF 防护/文件校验等 9 项）(v3.7)

### Phase 4: 用户功能 + 生态扩展 (v4.0+) 📋 规划中

**新增 AI 能力（API 层）：**

| 能力 | 函数 | 说明 |
|------|------|------|
| 视觉理解 | `wpmind_vision()` | 图片描述/alt text/NSFW 检测，复用多模态 Provider |
| 向量存储 | `wpmind_store()` / `wpmind_search()` | RAG、语义搜索、相关文章 |
| 重排序 | `wpmind_rerank()` | 搜索结果按相关性重排 |

**新增模块（按优先级）：**

| 阶段 | 模块 | 复用 API | 核心功能 |
|------|------|---------|---------|
| v4.0 | **Content Assistant** | chat/stream/structured/summarize | Gutenberg AI 面板、标题/摘要/大纲生成、改写/续写 |
| v4.1 | **Auto-Meta** | structured/batch/summarize | 发布时自动生成摘要/标签/FAQ/关键词 |
| v4.2 | **Comment Intelligence** | moderate/chat/structured | 评论审核/情感分析/AI 自动回复 |
| v4.3 | **Media Intelligence** | vision/generate_image | 图片 alt text/描述自动生成、AI 特色图片 |
| v4.4 | **Semantic Search** | embed + 向量存储 | 相关文章/语义搜索/RAG |
| v4.5 | **Translation** | translate/batch | 一键翻译/批量翻译/翻译记忆 |

**Post-Meta Bridge（SEO 插件集成）：**
- [ ] `PluginDocumentSettingPanel` 注册 WPMind AI 面板
- [ ] SEO 内容生成（标题/描述/关键词）
- [ ] 写入 Rank Math meta (`rank_math_title` 等)
- [ ] 写入 Yoast meta (`_yoast_wpseo_title` 等)
- [ ] 自动检测已安装的 SEO 插件

**AI Gateway 拆分决策（2026-02-07 Claude + Codex 联合分析）：**

> AI 拦截类功能全部移至 **文派叶子 WPCY** 插件实现。
> 理由：WPCY 已有中国本地化拦截基础设施（WordPress.org/Gravatar），
> 用户群完全匹配（中国用户），无商业冲突（目标服务在中国本就不可用）。
> WPMind 保持纯 AI 能力插件定位，不做任何请求拦截或 UI 替换。
> 两个插件协同：WPCY 负责拦截路由，WPMind 负责 AI 能力。

| 层级 | 方案 | 归属 | 决策 |
|------|------|------|------|
| Layer 1 | OpenAI 兼容代理 | → **WPCY** | ✅ WPCY 可选功能 |
| Layer 2 | Post-Meta Bridge | **WPMind** | ✅ WPMind Gutenberg 面板 |
| Layer 3 | Block+Hide+Inject | → **WPCY** | ✅ WPCY 可选功能（实验性） |
| ~~原 Layer 2~~ | ~~覆盖转发~~ | — | ❌ 不做 |

**WPCY + WPMind 协同模式：**

```
WPCY 设置（中国本地化）:
  ☑ WordPress 核心加速（默认开启）
  ☑ Gravatar 替换（默认开启）
  ☐ AI 服务本地化（可选）
      → 拦截 OpenAI/Anthropic/Google API 请求
      → 检测 WPMind → 使用 WPMind 路由引擎
      → 未安装 WPMind → 内置 DeepSeek 转发
  ☐ AI UI 替换（可选，实验性）
      → 隐藏商业插件 AI 面板
      → 注入 WPMind AI 面板（需安装 WPMind）
      → ⚠️ 插件更新后可能需要重新适配
```

**生态扩展：**

- [ ] MCP Server 适配（让 Claude/ChatGPT 管理 WordPress）
- [ ] Abilities API 扩展（注册自定义能力）
- [ ] AI 工作流自动化
- [ ] WooCommerce 集成
- [ ] 多站点支持
- [ ] API 开放
- [ ] **集成官方 Service Account** (#211)

---

## 模块化架构规划

> 2026-02-07 确立。核心层保持最小必要集，所有新功能作为模块实现。

### 架构分层原则

```
核心层（不可拆分，插件运行必需）:
  ├── API/          PublicAPI Facade + 6 个 Service（15 个全局函数）
  ├── Providers/    AI 服务商注册和管理
  ├── Routing/      智能路由器 + 策略引擎
  ├── Failover/     熔断器 + 健康追踪
  ├── SDK/          WP AI Client SDK 适配器
  ├── Admin/        管理界面框架
  └── Core/         模块加载器

模块层（可选功能，用户可启用/禁用）:
  ├── modules/geo/                ✅ 已有 — GEO 优化
  ├── modules/cost-control/       ✅ 已有 — 成本控制
  ├── modules/analytics/          ✅ 已有 — 分析面板
  ├── modules/content-assistant/  🆕 v4.0 — 内容创作助手
  ├── modules/auto-meta/          🆕 v4.1 — 自动元数据
  ├── modules/comment-intelligence/ 🆕 v4.2 — 评论智能
  ├── modules/media-intelligence/ 🆕 v4.3 — 媒体智能
  ├── modules/semantic-search/    🆕 v4.4 — 语义搜索
  └── modules/translation/        🆕 v4.5 — 翻译管理
```

### 模块判断标准

```
✅ 做成模块: 可选 + 自包含 + 用户可见 + 可独立测试 + 可独立更新
❌ 留在核心: 插件必需 / 被其他模块依赖 / 需早于模块加载 / 无用户开关
```

### 核心层优化任务（v3.8）

| 任务 | 说明 | 影响范围 |
|------|------|---------|
| **清理兼容层** | 移除 `includes/Usage/`、`Budget/`、`Analytics/` 兼容层 | 已 deprecated 自 v3.3 |
| **Provider 懒加载** | 只加载用户启用的 Provider，减少内存占用 | `includes/Providers/register.php` |
| **路由策略可插拔** | 开放 `wpmind_routing_strategies` filter，允许模块注册自定义策略 | `includes/Routing/` |

### 新模块结构参考（以 GEO 模块为标准）

```
modules/{module-name}/
├── module.json              # 元数据（id/name/version/requires/features）
├── {ModuleName}Module.php   # 主类，实现 ModuleInterface
├── includes/                # 内部组件
│   ├── Component1.php
│   └── Component2.php
├── assets/                  # 前端资源（可选）
│   ├── js/
│   └── css/
└── templates/
    └── settings.php         # 设置页面模板
```

---

## 文派心思 AI 智能路由

### 路由策略 (已实现)

```
用户请求
    ↓
任务分析器 (识别任务类型)
    ↓
┌─────────────────────────────────────┐
│           路由决策引擎              │
├─────────────────────────────────────┤
│ 成本优先 (CostStrategy)             │
│   → 选择成本最低的 Provider         │
├─────────────────────────────────────┤
│ 延迟优先 (LatencyStrategy)          │
│   → 选择响应最快的 Provider         │
├─────────────────────────────────────┤
│ 可用性优先 (AvailabilityStrategy)   │
│   → 选择健康分数最高的 Provider     │
├─────────────────────────────────────┤
│ 负载均衡 (LoadBalancedStrategy)     │
│   → 在多个 Provider 之间分散请求    │
├─────────────────────────────────────┤
│ 复合策略 (CompositeStrategy)        │
│   → 平衡/性能优先/经济策略          │
└─────────────────────────────────────┘
```

### 模型能力矩阵

| 模型 | 优势场景 | 成本 | 上下文 |
|------|---------|------|--------|
| DeepSeek | 推理、代码、通用 | ⭐ 极低 | 64K |
| 通义千问 | 中文理解、多模态 | ⭐⭐ 中等 | 128K |
| 智谱 AI | 知识问答、Agent | ⭐⭐ 中等 | 128K |
| Moonshot | 超长上下文 | ⭐⭐⭐ 较高 | 128K |
| 豆包 | 对话、创意写作 | ⭐ 低 | 128K |

---

## 技术架构

### Provider 架构

```
WPMind\\Providers\\
├── AbstractOpenAiCompatibleProvider.php
├── AbstractOpenAiCompatibleTextGenerationModel.php
├── AbstractOpenAiCompatibleModelMetadataDirectory.php
├── AuthenticatedProviderAvailability.php
├── ProviderRegistrar.php
├── register.php
├── DeepSeek/
├── Qwen/
├── Zhipu/
├── Moonshot/
├── Doubao/
├── SiliconFlow/
├── Baidu/          # v2.0 新增
├── MiniMax/        # v2.0 新增
└── Image/          # v2.0 新增
```

### 功能模块

```
WPMind\\
├── API/            # Public API (Facade + Services)
│   └── Services/   # ChatService, TextProcessing, Embedding, Audio, Image, StructuredOutput
├── Analytics/      # 分析仪表板
├── Budget/         # 预算管理
├── Failover/       # 故障转移
├── Routing/        # 智能路由
│   └── Strategies/ # 路由策略
├── SDK/            # WP AI Client SDK 适配
└── Usage/          # 用量统计
```

### 支持的 AI 服务

| 服务 | Provider ID | 模型 | 状态 |
|------|-------------|------|------|
| OpenAI | `openai` | gpt-4o, gpt-4o-mini | ✅ |
| Anthropic | `anthropic` | claude-3-5-sonnet | ✅ |
| Google | `google` | gemini-2.0-flash | ✅ |
| DeepSeek | `deepseek` | deepseek-chat, deepseek-reasoner | ✅ |
| 通义千问 | `qwen` | qwen-turbo, qwen-plus, qwen-max | ✅ |
| 智谱 AI | `zhipu` | glm-4, glm-4-flash, glm-4-plus | ✅ |
| Moonshot | `moonshot` | moonshot-v1-8k/32k/128k | ✅ |
| 豆包 | `doubao` | doubao-pro-4k/32k/128k | ✅ |
| 硅基流动 | `siliconflow` | DeepSeek-V3, Qwen2.5-72B | ✅ |
| 百度文心 | `baidu` | ernie-4.0, ernie-3.5 | ✅ |
| MiniMax | `minimax` | abab6.5s-chat | ✅ |

---

## WordPress AI 生态背景

### 官方发布时间线

| 版本 | 时间 | 关键内容 |
|------|------|----------|
| WordPress 6.8 | 2025-04-15 | PHP 依赖现代化准备 |
| WordPress 6.9 | 2025-12-02 | PHP AI Client + MCP Adapter |
| **WordPress 7.0** | **2026-03/04** | **WP AI Client 合并到核心** |

### 官方四大技术支柱

1. **PHP AI Client SDK** - 统一的 LLM 抽象层
2. **Abilities API** - WordPress 能力注册表
3. **MCP Adapter** - 连接外部 AI 助手
4. **AI Experiments** - 功能实验室

### WPMind 与官方的关系

```
官方 AI Building Blocks (WordPress Core)
              ↓
      WPMind (增强层)
        • 国产模型支持
        • 智能路由
        • 用户友好功能
              ↓
文派心思 AI (统一接入)
              ↓
国产大模型集群
```

### 官方 Filter 集成策略

WPMind 通过官方提供的 Filter Hooks 无缝注入国产模型支持：

```php
// 注入国产模型到官方首选列表 (已实现)
add_filter( 'ai_experiments_preferred_models_for_text_generation', function( $models ) {
    return array_merge(
        array(
            array( 'deepseek', 'deepseek-chat' ),
            array( 'qwen', 'qwen-turbo' ),
            array( 'zhipu', 'glm-4-flash' ),
            array( 'moonshot', 'moonshot-v1-8k' ),
            array( 'doubao', 'doubao-pro-4k' ),
        ),
        $models
    );
});
```

### 官方可用 Filter Hooks

| Hook | 用途 | WPMind 应用 |
|------|------|-------------|
| `ai_experiments_preferred_models_for_text_generation` | 文本生成模型偏好 | ✅ 已实现 |
| `ai_experiments_preferred_image_models` | 图像生成模型偏好 | ✅ 已实现 |
| `ai_experiments_pre_has_valid_credentials_check` | 凭据验证前置 | 待实现 |
| `ai_experiments_pre_normalize_content` | 内容预处理 | 待实现 |
| `ai_experiments_normalize_content` | 内容后处理 | 待实现 |

---

## 官方仓库跟踪

> **重要**: 持续跟踪 WordPress/ai 仓库动态，确保 WPMind 与官方保持兼容和协同。

### 跟踪仓库

| 仓库 | 用途 | 跟踪频率 |
|------|------|----------|
| [WordPress/ai](https://github.com/WordPress/ai) | AI Experiments 插件 | 每周 |
| [WordPress/php-ai-client](https://github.com/WordPress/php-ai-client) | PHP AI Client SDK | 每月 |
| [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) | MCP 适配器 | 每月 |

### 官方版本路线图

| 版本 | 状态 | 关键功能 | 对 WPMind 影响 |
|------|------|----------|----------------|
| 0.1.1 | ✅ 已发布 | WP AI Client 0.2.0 | 基础依赖 |
| 0.2.0 | ✅ 已发布 | 基础实验功能 | 参考实现 |
| **0.3.0** | 🚧 开发中 | Service Account, Markdown Feeds | **需要关注** |
| 0.4.0 | 📋 规划中 | Ability Table 扩展 | 可能影响 |

### 重点跟踪 PR

| # | 标题 | 状态 | 与 WPMind 关系 | 最后检查 |
|---|------|------|----------------|----------|
| **#211** | Add Service Account experiment | Draft | ⚠️ 文派心思 AI 可利用 | 2026-02-05 |
| **#194** | Add Markdown Feeds experiment | Open | ✅ GEO 优化可集成 | 2026-02-05 |

### 重点跟踪 Issues

| # | 标题 | 里程碑 | 与 WPMind 关系 | 最后检查 |
|---|------|--------|----------------|----------|
| **#27** | Pre-configured AI providers | 0.3.0 | ⚠️ 影响 Provider 设计 | 2026-02-05 |
| **#21** | Support hundreds of abilities | 0.3.0 | 参考 Layered Tool Pattern | 2026-02-05 |
| **#192** | Custom prompt templates | Future | 参考扩展点设计 | 2026-02-05 |
| **#190** | Site-wide AI content insights | Future | ⚠️ 潜在功能重叠 | 2026-02-05 |

### 差异化优势分析

| 领域 | 官方 AI 插件 | WPMind | 优势 |
|------|-------------|--------|------|
| **Provider 支持** | Anthropic, Google, OpenAI | 11 个 Provider (含国产) | ✅ 独占 |
| **智能路由** | 无 | 5 种策略 + 复合策略 | ✅ 独占 |
| **商业模式** | 无 | 免费版/专业版 | ✅ 独占 |
| **统一接入** | 需用户配置 API Key | 文派心思零配置 | ✅ 独占 |
| **中文优化** | 无 | 中文内容处理优化 | ✅ 独占 |
| **GEO 优化** | Markdown Feeds (基础) | 完整 GEO 套件 | ✅ 增强 |
| **预算管理** | 无 | 支出护栏 + 告警 | ✅ 独占 |
| **分析仪表板** | 无 | Chart.js 可视化 | ✅ 独占 |

### 官方首选模型（无国产）

```php
// 官方 helpers.php - get_preferred_models_for_text_generation()
$preferred_models = array(
    array( 'anthropic', 'claude-haiku-4-5' ),
    array( 'google', 'gemini-2.5-flash' ),
    array( 'openai', 'gpt-4o-mini' ),
    array( 'openai', 'gpt-4.1' ),
);
// ⚠️ 完全没有国产模型！这是 WPMind 的核心差异化优势
```

---

## GEO 解决方案

### 什么是 GEO？

**Generative Engine Optimization (GEO)** - 生成式引擎优化

- **传统 SEO**: 优化 Google/Bing 搜索结果排名
- **GEO**: 优化 AI 搜索引擎（ChatGPT、Perplexity、Google AI Overview）的引用

### GEO 核心策略

1. **引用优化** - 让 AI 更容易引用你的内容
2. **权威性信号** - 增强内容可信度
3. **结构化数据** - 帮助 AI 理解内容
4. **问答格式** - 适配 AI 对话式搜索
5. **实体关联** - 建立知识图谱连接

### SEO 插件扩展

```
WPMind Core (核心插件)
    ├── WPMind GEO for Rank Math
    ├── WPMind GEO for Yoast SEO
    ├── WPMind GEO for Slim SEO
    ├── WPMind GEO for AIOSEO
    └── WPMind GEO for SEOPress
```

---

## 市场机会

| 指标 | 数据 |
|------|------|
| WordPress AI 插件市场 | 2025年 $5亿 → 2033年 $25亿 |
| 年复合增长率 | 25% CAGR |
| 2026年 AI 采用率 | 60%+ WordPress 站点 |
| 内容生产效率提升 | 3小时 → 90分钟 |

---

## 开发环境

| 项目 | 路径 |
|------|------|
| 开发仓库 | `~/Projects/wpmind/` |
| 部署目录 | `/www/wwwroot/wpcy.com/wp-content/plugins/wpmind/` |
| 测试站点 | `wpcy.com` |
| 部署脚本 | `./deploy.sh` |

---

## 相关资源

- [WordPress AI Building Blocks](https://make.wordpress.org/ai/2025/07/17/ai-building-blocks/)
- [AI Experiments Plugin](https://wordpress.org/plugins/ai/)
- [PHP AI Client SDK](https://github.com/WordPress/php-ai-client)
- [MCP Adapter](https://github.com/WordPress/mcp-adapter)
- [Abilities API 文档](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/)

---

## 更新日志

### v3.0.0 (2026-02-05) - 文档同步

**文档更新：**
- 同步开发进度到 v2.5.0
- 合并官方仓库跟踪研究
- 更新开发路线图状态
- 修正开发环境路径
- 更新支持的 AI 服务列表（11 个）

**已完成功能确认：**
- Phase 1: 基础架构 ✅
- Phase 2: 核心功能 ✅
- Phase 2.5: 稳定性增强 ✅
- 智能路由系统 ✅
- 故障转移机制 ✅
- 官方 Filter Hook 对齐 ✅

### v2.1.0 (2026-02-05) - 官方仓库跟踪

**新增内容：**
- 添加官方仓库跟踪章节
- 记录重点 PR (#211 Service Account, #194 Markdown Feeds)
- 记录重点 Issues (#27, #21, #192, #190)
- 添加官方 Filter Hooks 集成策略
- 差异化优势分析

**关键发现：**
- 官方完全没有国产模型支持（核心差异化优势）
- 官方提供 Filter Hooks 可无缝注入国产模型
- Service Account (#211) 可用于文派心思 AI 统一接入
- Markdown Feeds (#194) 可集成到 GEO 优化套件

### v2.0.0 (2026-01-26) - 战略升级

**产品定位：**
- 从"技术桥接"升级为"用户赋能"
- 引入文派心思 AI 统一接入
- 智能路由引擎设计
- 免费版/专业版商业模式

### v1.3.0 (2026-01-26) - Phase 1 完成

**新增功能：**
- 完整的 Provider 架构实现
- 6 个国内 AI 服务 Provider
- WordPress AI 原生功能支持

**技术改进：**
- `AuthenticatedProviderAvailability` - 解决认证传递
- `prepareGenerateTextParams()` - 强制 `n=1`
- `pre_option_` filter - 合并凭据

---

*最后更新: 2026-02-07 13:00*
