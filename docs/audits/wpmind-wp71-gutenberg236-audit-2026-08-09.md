# WPMind 全功能兼容性与产品收敛审计（2026-08-09）

> 审计基线：WPMind `0.12.0`，commit `0548f5f`；目标环境 WordPress 7.0.x、WordPress 7.1 RC、Gutenberg 23.6.x。
>
> 范围：插件核心、8 个国产模型 Provider、公共 AI API、路由/故障转移、成本控制、缓存、分析、API Gateway、Auto-Meta、媒体智能、GEO、Abilities/MCP、后台界面、测试与发布工具链。
>
> 本文只记录已从代码、官方文档或测试得到的事实；未完成真实环境验证的项目明确标注为待验证。

## 1. 结论

WPMind 目前最严重的问题不是“和 Gutenberg 大面积冲突”，而是**几乎没有接入 Gutenberg**：代码中没有 `register_block_type`、`enqueue_block_editor_assets`、`registerPlugin`、`PluginSidebar`、`BlockControls` 或任何 `@wordpress/*` 运行依赖。因而新编辑器能力基本没有使用，也没有形成编辑器内可见的产品入口。

同时已经发现 4 个必须在下一版本先处理的兼容性问题：

1. WPMind 的全局熔断过滤器可能阻断同站其他插件和官方 Provider 的 AI 请求。
2. 自定义 MCP Server 仍按旧版 Adapter 签名注册，和当前官方 MCP Adapter 的参数契约不一致。
3. Connector 凭据同时保存在 WPMind option 和 WordPress Connector option，并在初始化时复制或删除，形成双真相源。
4. WordPress 7.1 的客户端媒体处理改变了附件处理时序，当前只监听 `add_attachment` 的实现没有覆盖新路径和重复元数据事件。

下一主版本应当先把 WPMind 收敛成“一套插件、一个配置入口、一个 AI 调用路径”，再做编辑器功能。不要继续横向增加 Provider、管理页和独立模块。

## 2. 审计基线与体量

- 插件头：Requires WordPress 6.4，Requires PHP 8.1，版本 0.12.0：`wpmind.php:3-15`。
- 自有 PHP 约 36,737 行，后台/E2E JavaScript 约 2,984 行。
- 模块：Analytics、API Gateway、Auto-Meta、Cost Control、Exact Cache、GEO、Media Intelligence，共 7 个 module manifest。
- REST 路由全部来自 API Gateway；其余管理操作依赖 30 余个 `wp_ajax_*` handler。
- 运行时 JavaScript 只有 jQuery 后台页面；`package.json` 仅把 `@wordpress/env` 和 Playwright 作为开发依赖：`package.json:20-23`。
- 当前 48 个 PHPUnit 测试全部通过，但它们主要是隔离 mock，不等于真实 WordPress/Gutenberg 兼容测试。
- Composer 定义了 `composer analyze`，但 `composer.json` 未声明 PHPStan，当前环境无法执行该脚本：`composer.json:9-30`。

## 3. P0：会影响其他插件或关键集成

### 3.1 全局熔断会误伤站内其他 AI Provider

WPMind 把 `should_prevent_prompt()` 挂到全局 `wp_ai_client_prevent_prompt`。只要 WPMind 至少启用一个 Provider，且 WPMind 自己判断没有可用 Provider，就返回 `true`：`wpmind.php:317-325`、`wpmind.php:395-424`。

该过滤器作用于 WordPress AI Client 的每一个 prompt，而不是只作用于 WPMind 发起的请求。站点即使还有官方 OpenAI、Anthropic、Google Provider，或者其他插件注册的 Provider，也会一起被阻断。

**修复要求：**

- 删除全局“WPMind Provider 全挂即阻断”的逻辑。
- 只在 WPMind 自己的路由请求中短路。
- 如果必须使用 `wp_ai_client_prevent_prompt`，必须读取第二个 `WP_AI_Client_Prompt_Builder` 参数并能证明当前 prompt 明确指定了 WPMind Provider；不能以 WPMind 局部状态代表整站 AI 状态。
- 增加真实 WordPress 测试：WPMind Provider 熔断 + 第三方 Provider 可用时，第三方 prompt 仍成功。

### 3.2 自定义 MCP Server 对当前官方 Adapter 已失配

当前实现向 `create_server()` 传 9 个参数，把 `$ability_names` 放在第 9 位，并在 `ArgumentCountError` 后回退到 3 参数：`includes/MCP/Gateway.php:147-214`。

当前官方示例的参数顺序是：

1. server ID
2. namespace
3. route
4. name
5. description
6. version
7. transports
8. error handler class
9. observability handler class
10. abilities
11. resources
12. prompts

所以当前第 9 个参数会被当成 observability handler，而不是 abilities；3 参数回退也不符合当前契约。插件同时过滤 `mcp_adapter_default_server_config`，把共享默认 Server 名称和版本改成 WPMind：`includes/MCP/Gateway.php:69-93`。这会影响站内其他使用默认 Server 的插件。

**修复要求：**

- 优先删除自定义 Server 和 `mcp_adapter_default_server_config` 过滤器。
- 把 WPMind abilities 标为官方 Default Server 可发现，由 Default Server 统一暴露。
- 只有确有独立 URL、独立鉴权、独立审计需求时才建立自定义 Server，并严格使用当前 12 参数契约。
- WPMind 发布包若要满足“一次安装”，可以把官方 MCP Adapter 作为 Composer 依赖打入发布构建，但必须采用官方建议的共享包自动加载方案；不能再维护一套自制 MCP 协议实现。

### 3.3 Connector 凭据存在双真相源和反向删除风险

WPMind 目前同时：

- 从 `wpmind_custom_endpoints` 读取 API Key；
- 通过 `pre_option_wp_ai_client_provider_credentials` 注入旧凭据 option：`includes/Providers/register.php:99-157`；
- 在 `wp_connectors_init` 把 Key 写入 `connectors_ai_{id}_api_key`：`includes/Providers/register.php:159-246`。

当 WPMind endpoint 处于 enabled 但本地 API Key 为空时，`sync_api_key_to_connector()` 会删除 Connector option：`includes/Providers/register.php:230-240`。这意味着用户若在 WordPress 的 Settings > Connectors 中录入 Key，WPMind 可能在下次初始化把它删掉。

WordPress 7.0 已经会从默认 Provider Registry 自动发现 Connector，不需要 Provider 插件手工再注册 Connector。Connector 也已定义环境变量、PHP 常量、数据库三种来源及优先级。

**修复要求：**

- WordPress 7.0+ 只读 Connector API，不复制、不删除、不回写 Core Connector Key。
- WPMind 设置页改为展示连接状态并链接到 Settings > Connectors。
- 仅 WordPress < 7.0 保留旧凭据兼容路径，并在 1 个明确的兼容类中隔离。
- 新安装使用 Connector option 作为唯一真相源；旧安装执行一次性、可回滚迁移，迁移后不再双写。
- 不再手工注册已经由 Provider Registry 自动发现的 Connector。

### 3.4 MCP/REST 对“公开”的描述需要重新核对

5 个 abilities 使用 `meta.mcp.public=true` 和 `meta.show_in_rest=true`，但没有使用 WordPress 7.1 开始统一解释的 `meta.public`：`includes/MCP/Gateway.php:448-587`。

另外 `mind/chat` 被标记为 `readonly=true`：`includes/MCP/Gateway.php:448-489`，但执行会产生外部 API 调用和费用。它不修改 WordPress 内容，不代表它没有副作用。

**修复要求：**

- 对 7.1 采用 `meta.public`；兼容 7.0 时再补 `meta.show_in_rest`。
- `mind/chat` 的 annotations 应明确非只读或至少说明产生费用与外部请求。
- 每个可写 ability 保留细粒度 capability，而不是统一 `manage_options`。
- 利用 7.1 的 ability lifecycle filters 统一实现预算、速率限制、审批、输入归一化、结果脱敏和审计。

## 4. WordPress 7.1 / Gutenberg 23.6 兼容性

### 4.1 强制 iframe 编辑器

Gutenberg 23.6 和 WordPress 7.1 无论 block API 版本都强制使用 iframe 编辑器。WPMind 当前没有 block 或 editor asset，因此不会因为 iframe 直接出现现有 block 崩溃；但这也证明插件没有编辑器产品入口。

GEO 的 Entity Link、AI Summary、AI Indexing 仍使用传统 meta box：`modules/geo/includes/EntityLinker.php:38-40`、`modules/geo/includes/AiSummaryManager.php:37-40`、`modules/geo/includes/AiIndexingManager.php:55-58`。这些表单继续位于编辑器外层 UI，无法利用现代侧栏、撤销/重做、数据存储和协作状态。

**改进：**

- 新增一个 WPMind Editor Sidebar，而不是多个 meta box。
- 编辑画布内脚本必须通过正确的 block editor enqueue 入口加载，不能查询顶层 document 或依赖后台全局 CSS。
- 新编辑器功能从 block API v3 和 iframe 环境起步，不再写 v2 兼容层。
- 可先做“摘要、标题、SEO 描述、Alt、重写”五个动作，不要一开始做自定义 AI block。

### 4.2 媒体处理时序改变

媒体智能只在 `add_attachment` 时立即安排 Cron：`modules/media-intelligence/includes/AltTextGenerator.php:21-56`，Cron 运行时读取 medium URL，失败才回退原图：`modules/media-intelligence/includes/AltTextGenerator.php:180-194`。

WordPress 7.1 的客户端媒体处理会使 `wp_generate_attachment_metadata` 在 create/update 两阶段触发，部分服务器端图片 hook 在客户端路径不再触发。图片裁剪、HEIC/AVIF、动画 GIF 转 Video block 也会产生不同对象和处理路径。

**修复要求：**

- 从只监听 `add_attachment` 改为监听“附件已完成 finalization/metadata 可用”的可靠节点。
- 处理 `wp_generate_attachment_metadata` 的 create/update context，执行必须幂等。
- 记录源文件 hash 或 attachment edit revision，避免同一图片重复计费。
- 明确跳过转为 Video block 的动画 GIF；补 HEIC/AVIF 与远程导入测试。
- 生成前再次检查人工 alt，避免上传后用户已经填写仍被 Cron 覆盖。
- 去掉 attachment 字段中的内联 `onclick`，改为独立脚本和事件绑定：`modules/media-intelligence/includes/AltTextGenerator.php:150-177`。

### 4.3 协作编辑和频繁保存

Auto-Meta 同时监听 `transition_post_status` 和每次 `post_updated`：`modules/auto-meta/includes/MetaGenerator.php:25-42`。它用标题+正文 MD5 和 Cron 去重：`modules/auto-meta/includes/MetaGenerator.php:68-97`，已能过滤很多重复事件；但没有显式排除 revision、autosave、同步/协作产生的中间状态。

**修复要求：**

- 显式排除 `wp_is_post_revision()`、`wp_is_post_autosave()` 和非最终协作提交。
- 由编辑器动作触发“预览生成”，由发布成功触发“最终落库”，不要让每次内容保存都自动消费。
- 生成字段要区分人工值、AI 草稿、已接受 AI 值；不能只用 `source=auto`。
- 采用单一内容智能作业队列，合并 Auto-Meta、GEO Summary、FAQ、标签的重复 prompt。

### 4.4 7.1 编辑器组件变更

WPMind 当前不使用 `@wordpress/components`，所以 `Navigation` 移除、40px 控件、`View css` no-op 等变化暂不构成运行冲突。新编辑器 UI 必须从一开始使用 `Navigator`、稳定 style/className 和 40px 控件，不要复制旧实验 API。

### 4.5 新能力目前全部错过

WPMind 尚未使用：

- `@wordpress/abilities` 客户端消费；
- AI Client 的 `using_abilities()`；
- Command Palette；
- DataViews/DataForms；
- block bindings；
- PHP-only block registration / `autoRegister`；
- pattern `contentOnly` 与内容 role；
- Gutenberg 23.6 的媒体编辑、响应式状态、Icons、Tabs/Playlist、Notes/协作入口。

不是每项都应该做。最有价值的顺序是：

1. Editor Sidebar + Command Palette；
2. 用 Abilities 描述所有具体动作；
3. Media Library 中的 Alt/标题批处理；
4. DataViews 展示 AI 作业、费用和失败记录；
5. 只在有明确用户场景时做 block bindings 或自定义 block。

## 5. AI Client 跟进差距

### 5.1 内部调用路径仍然过重

WordPress 7.0 推荐插件功能经 `wp_ai_client_prompt()` 调用。WPMind 仍保留 PublicAPI、6 个 Service、SDKAdapter、原生 HTTP、路由、failover 多条路径。ChatService 会在 SDK 与 native HTTP 之间切换：`includes/API/Services/ChatService.php:391-452`。

这导致同一请求可能经历 WPMind 路由、Core 模型选择、WPMind fallback 和原生 HTTP fallback，故障语义与计费很难证明一致。

**收敛目标：**

- WordPress 7.0+：Core AI Client 是唯一执行入口；WPMind 负责 Provider 实现、策略、预算、缓存和产品动作。
- WordPress 6.9：若必须支持，仅维护一个兼容适配层。
- WordPress 6.8 及以下：下一主版本停止支持，不再扩大兼容矩阵。
- 移除“SDK 失败自动转自制 HTTP”的静默回退；失败必须可观察，避免同一请求重复收费。

### 5.2 模型目录硬编码已经落后

默认模型仍包括 `gpt-4o`、`gpt-3.5-turbo`、Claude 3、Gemini 1.5、旧 Moonshot/Doubao/Minimax 名称：`wpmind.php:198-305`。WordPress 7.0 官方文档已以 Claude Sonnet 4.6、Gemini 3.1 Pro、GPT-5.4 作为模型偏好示例，并明确要求 Provider 自己按可用性和新旧顺序提供模型。

**修复要求：**

- 不再由插件主文件维护全局静态模型清单。
- 每个 Provider 通过模型发现 API或独立可更新目录返回能力、模态、上下文、价格与弃用状态。
- UI 默认选择“自动”；具体模型只是偏好，不是硬绑定。
- 模型目录更新应与插件版本解耦，但必须签名、缓存、可回退。

### 5.3 Embeddings 与 Streaming

WPMind 已有自制 EmbeddingService 和 API Gateway SSE。WordPress 7.1 正在把 embeddings 加入 AI Client；PHP AI Client 开始支持 streaming，但官方明确说明 WordPress 请求栈和常见主机并不天然支持长连接。

**建议：**

- EmbeddingService 迁移到 Core AI Client capability，保留 WPMind 的索引与成本逻辑。
- 不把 SSE 作为普通 WordPress 站点的默认能力。
- 若保留外部 OpenAI-compatible Gateway，SSE 只能作为明确的高级部署模式，并提供队列/轮询 fallback、主机检测和并发上限。
- 不把“支持 PHP streaming”误写成“WordPress 环境可稳定 streaming”。

## 6. 功能去留建议

### 保留并成为主线

1. 国产 Provider 适配，但改用 Core Registry/Connector。
2. 路由与故障转移，但只在 WPMind 范围内生效。
3. 成本、预算、审计；这是 Core AI Client 目前仍缺的真实需求。
4. Exact Cache 的引擎能力，合并到成本模块，不保留独立大页面。
5. Auto-Meta + Media Intelligence，合并为“内容助手”。
6. Abilities，作为编辑器、REST、MCP、自动化共同的动作契约。
7. 一个统一设置入口。

### 合并

- Analytics 合并进 Cost Control。
- Auto-Meta、GEO Summary、FAQ、Entity Linking 合并为一次内容分析作业。
- Provider 设置与 WordPress Connectors 合并；WPMind 不再保存第二份 Key。
- MCP Gateway 与 Abilities 合并；不维护独立 Server。
- 多个后台 tab 改成“连接 / 内容助手 / 用量 / 高级”四个区域。

### 默认关闭并设退出条件

- API Gateway：只有确有外部客户端调用、能维护安全/限流/SSE/协议兼容时保留；否则在下一主版本移除。
- GEO：只保留能验证价值的 llms.txt、结构化数据和内容摘要。Crawler Tracker、所谓 AI signal、AI Sitemap 若没有真实使用数据，在一个版本观察期后删除。
- 图像生成 Provider：只保留经过真实回归测试且有用户使用的实现，不再追求数量。

### 直接删除

- 全局 MCP 默认 Server 改名。
- 手工 Connector 注册与 Key 双写/删除。
- Core AI Client 之外的静默 HTTP fallback。
- 各模块重复的状态卡、统计页和 AJAX handler。
- 仅为“看起来像 Gutenberg”而维护的自制 CSS 设计系统；新页面应使用 WordPress 稳定组件或简洁原生表单。

## 7. 单插件版本结构

不再做 Pro 插件，也不要求用户安装 WPMind 扩展。建议发布物仍只有一个 WPMind：

- **基础核心**：Connectors、Provider、AI Client adapter、Abilities、权限、作业队列。
- **内置功能开关**：内容助手、媒体助手、GEO、外部 Gateway。
- **所有代码随同一个插件交付**，功能可见且可用；开关只控制是否运行，不作为付费代码门。
- 官方依赖若必须使用，应在发布构建中正确打包和隔离，不让用户手工再装配套扩展。
- WordPress 7.0+ 走原生接口；6.9 仅保留有限兼容。不要再以 6.4 为真实完整功能基线。

## 8. 商业化方向（不依赖 Pro 插件）

付费对象应当是持续服务，而不是第二套插件代码：

1. **托管 AI Relay**：国内外模型统一结算、可用性、账单、团队配额。
2. **模型目录与策略服务**：经过验证的模型能力、价格、地区可达性与推荐策略。
3. **团队治理**：组织预算、审批、审计留存、成员权限、站点集中管理。
4. **内容工作流**：行业模板、批量作业、质量规则、人工审核和回滚。
5. **企业支持**：私有部署、SLA、迁移、合规与定制 Provider。

插件本身保持同一套功能和代码；免费用户自备 Key、本地运行，付费用户购买托管额度、治理、同步和支持。这样不会再出现 Free/Pro 两套兼容矩阵。

## 9. 版本顺序

### 0.13：兼容修复，不加新功能

- 修复全局 prompt 阻断。
- 去掉 Connector Key 双写/删除。
- 修复或删除自定义 MCP Server。
- 补 WordPress 7.0、7.1 RC、Gutenberg 23.6 真实集成测试。
- 修复 `wp-env:start` 脚本。
- 把 PHPStan 加入 dev dependencies，恢复静态分析。
- 媒体处理改成 7.1 幂等路径。

### 0.14：编辑器最小闭环

- Editor Sidebar。
- Command Palette。
- 摘要、标题、SEO 描述、Alt、重写 5 个 abilities。
- 作业状态、取消、重试、接受/拒绝。
- 不新增自定义 block。

### 1.0：产品收敛

- 统一 Connector 和 AI Client 路径。
- 合并 Cost/Analytics/Cache。
- 合并 Auto-Meta/GEO 内容分析。
- 根据真实使用数据决定 API Gateway 与 GEO 子功能去留。
- 同一插件接入托管服务，但不做 Pro 插件。

## 10. 验收矩阵

| 环境 | 激活/后台 | Core AI Client | Connector | Abilities/MCP | Editor iframe | Media | Auto-Meta/GEO |
|---|---|---|---|---|---|---|---|
| WP 6.9 + Gutenberg 23.6.2 | 待测 | 兼容层 | 兼容层 | 插件 API | 强制 iframe | 待测 | 待测 |
| WP 7.0.x | 待测 | 必测 | 必测 | 必测 | 内容决定 iframe | 待测 | 待测 |
| WP 7.0.x + Gutenberg 23.6.2 | 待测 | 必测 | 必测 | 必测 | 强制 iframe | 必测 | 必测 |
| WP 7.1 RC1 | 待测 | 必测 | 必测 | lifecycle filters | 强制 iframe | client-side path | collaboration saves |
| WP 7.1 final | 发布前必须重跑 | 必测 | 必测 | 必测 | 必测 | 必测 | 必测 |

每个环境至少验证：

- 激活无 fatal、无 `_doing_it_wrong`、无 PHP warning。
- WPMind Provider 与第三方 Provider 可同时使用。
- WPMind 熔断不影响第三方。
- Connector Key 不被复制、覆盖或删除。
- ability 列表、权限、执行、预算、审计正确。
- 编辑器加载、保存、autosave、revision、Notes/协作不重复消费。
- 图片上传、裁剪、替换、HEIC/AVIF、GIF、批量处理不重复消费。
- 模块开关关闭后不注册运行 hook、不建表、不发请求。

## 11. 本轮验证记录

- `composer check`：通过；PHP lint 176 文件，PHPUnit 48 tests / 76 assertions，Composer audit 无已知漏洞。
- `composer analyze`：未执行成功；项目没有安装 PHPStan，属于工具链缺口。
- `npm run wp-env:start`：失败；`/home/parallels/bin/wp-env-start-patched` 的 Python heredoc 参数传递错误，无法用项目脚本启动环境。
- 直接 `npx wp-env start`：镜像下载阶段长时间未完成；因此本报告不把结构审计写成 WordPress 7.1/Gutenberg 真实运行通过。
- 主工作区原有 `test-results/` 未跟踪目录未删除、未覆盖。


## 12. 官方依据

- WordPress 7.0 Field Guide: https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/
- WordPress 7.0 AI Client: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
- WordPress 7.0 Connectors API: https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
- WordPress 7.1 AI Client changes: https://make.wordpress.org/ai/2026/06/19/whats-coming-to-the-ai-client-in-wp-7-1/
- WordPress 7.1 Abilities lifecycle filters: https://make.wordpress.org/core/2026/07/29/new-execution-lifecycle-filters-for-the-abilities-api-in-wordpress-7-1/
- WordPress 7.1 client-side media processing: https://make.wordpress.org/core/2026/07/22/client-side-media-processing-in-wordpress-7-1/
- WordPress 7.1 editor component changes: https://make.wordpress.org/core/2026/07/23/editor-components-updates-in-wordpress-7-1/
- WordPress 7.1 release schedule: https://make.wordpress.org/core/7-1/
- Gutenberg 23.6 release notes: https://make.wordpress.org/core/2026/07/22/whats-new-in-gutenberg-23-6-july-22-2026/
- Gutenberg releases: https://github.com/WordPress/gutenberg/releases
- Block API versions and iframe behavior: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-api-versions/
- WordPress MCP Adapter: https://github.com/WordPress/mcp-adapter/
- WordPress Abilities API PHP documentation: https://github.com/WordPress/abilities-api/blob/trunk/docs/php-api.md
- Deprecated standalone WP AI Client repository: https://github.com/WordPress/wp-ai-client
