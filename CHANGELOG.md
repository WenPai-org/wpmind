# WPMind 更新日志

## [3.6.0] - 2026-02-07

### 🔗 执行层统一到 WP AI Client SDK (Phase C)

#### C1: SDKAdapter 适配器类
- **新增** `includes/SDK/SDKAdapter.php`: 封装 WP AI Client SDK 调用
- 异常→WP_Error 转换，保留 HTTP 状态码信息用于重试判断
- GenerativeAiResult→PublicAPI 数组格式转换
- 支持 SDK 内置 Provider (OpenAI/Anthropic/Google) + WPMind 注册 Provider

#### C2: SDK 路径集成
- **execute_chat_request()** 增加 SDK 优先路径，失败自动回退原 HTTP 实现
- **错误分类处理**: 适配错误静默回退（不消耗重试预算），Provider 错误记录失败
- 健康统计在 SDK 和 HTTP 两条路径下都正常记录
- 新增 `wpmind_sdk_fallback` action hook 用于监控回退事件

#### C3: 能力 gate + Provider 白名单
- **should_use_sdk()**: 5 层检查（SDK 可用性、用户配置、能力 gate、Provider 白名单）
- 默认对 Anthropic/Google 启用 SDK（解决 PublicAPI 中不可用的问题）
- tools 请求暂不走 SDK（v3.6.0 限制）
- `wpmind_sdk_providers` filter 允许扩展白名单
- `wpmind_sdk_enabled` 选项允许全局禁用

> 基于 Claude + Codex 评审共识，详见 `docs/AI-PIPELINE-AUDIT.md` 第 9 节

---

## [3.5.0] - 2026-02-07

### 🔀 模型重选 + 路由统一 (Phase B)

#### B1: Failover 模型重选
- **model=auto 下移**: failover 循环内每个 provider 动态获取默认模型，不再固化首选 provider 的模型 (N2)
- **显式模型回退**: 目标 provider 不支持用户指定模型时，自动回退到该 provider 默认模型
- **model_fallback 标记**: 模型被自动替换时在结果中标注 `model_fallback: true` + `original_model`

#### B2: stream() 接入路由和故障转移
- **路由接入**: stream() 接入 `wpmind_select_provider` filter (I2)
- **故障转移**: 通过 FailoverManager 获取故障转移链，fopen 失败自动切换 provider
- **健康记录**: 成功/失败均记录到 FailoverManager，影响后续路由决策

#### B3: embed() 接入路由和故障转移
- **路由接入**: embed() 接入 `wpmind_select_provider` filter (I2)
- **故障转移**: wp_remote_post 失败或 HTTP 错误时自动切换 provider
- **动态模型**: embed model 在循环内根据 provider 动态选择

#### B4: transcribe/speech 接入路由
- **路由接入**: transcribe() 和 speech() 接入 `wpmind_select_provider` filter
- **能力过滤**: failover 链自动过滤不支持 audio API 的 provider
- **支持列表**: transcribe 仅 OpenAI，speech 支持 OpenAI + DeepSeek

> 基于 Claude + Codex 审计 Phase B 计划，详见 `docs/AI-PIPELINE-AUDIT.md`

---

## [3.4.0] - 2026-02-07

### 🛡️ AI 请求链路可靠性修复 (Phase A)

#### P0 修复
- **缓存键加入 provider/model**: `generate_cache_key()` 包含服务商和模型参数，避免跨 Provider 缓存污染 (N1)
- **非 JSON 响应防护**: `execute_chat_request()` 检测 `json_decode` 失败，返回 `wpmind_invalid_response` 错误而非 fatal (N3)
- **stream() 默认 provider 统一**: 从硬编码 `deepseek` 改为 `get_option()`，与 `chat()` 行为一致 (N4)

#### P1 修复
- **per-provider 重试逻辑**: 激活 `ErrorHandler::should_retry()` + `get_retry_delay()` 死代码，429/5xx 先重试再 failover (I1)
  - 非最后 Provider: 最多 1 次重试
  - 最后 Provider: 最多 3 次重试，指数退避 1s→2s→4s
  - 不可重试错误 (401/403/配置缺失) 直接跳过
  - 新增 `wpmind_retry` action hook 用于监控

> 基于 Claude + Codex 两轮审计，详见 `docs/AI-PIPELINE-AUDIT.md`

---

## [3.3.0] - 2026-02-07

### 🔧 编码规范化 (Phase 1 完成)
- **方法名 camelCase → snake_case**: 39 文件全量重命名，符合 WordPress PHP 编码规范
- 涉及模块: Routing、Failover、Budget、Analytics、Usage、ErrorHandler、API
- 兼容层 (`__callStatic` 代理) 同步更新
- 模板文件静态调用同步更新
- 外部库接口方法 (Providers/Image) 保持不变
- **Chart.js CDN 兜底**: 本地优先加载，失败时自动切换 CDN
- **后台 JS 模块化**: admin 逻辑拆分为 `admin-*.js`，Chart.js 仅 analytics 依赖
- **模板去内嵌**: modules/cost-control 模板移除内联脚本，Modules 样式迁移到 `assets/css/modules.css`
- **后台 PHP 拆分**: admin 逻辑迁移至 `includes/Admin/*`

---

## [3.2.1] - 2026-02-06

### 🔒 全面审查修复 (18 个问题)

#### P0 紧急修复
- **AnalyticsModule Fatal Error**: 移除私有构造函数单例模式，改为 public 构造函数
- **版本号统一**: 插件头部、常量、CLAUDE.md 统一为 3.2.0
- **损坏注释块**: 删除 wpmind.php 残留的未闭合 PHPDoc 注释
- **设置链接 404**: `options-general.php` 修正为 `admin.php`

#### P1 高优先级修复
- **ImageRouter 命名空间**: `Routing\ImageRouter` 修正为 `Providers\Image\ImageRouter`
- **Analytics nonce 错误**: `wpmind_admin_nonce` 修正为 `wpmind_ajax`
- **AJAX 重复注册**: 移除 `wpmind_clear_usage_stats` 重复注册
- **卸载脚本补全**: 添加 GEO/模块状态等 13 个选项清理 + `$wpdb->prepare()`
- **测试端点安全**: 添加 nonce 验证，移除 nopriv 未认证访问
- **speech() 路径遍历**: 添加 uploads 目录路径验证
- **模块依赖排序**: ModuleLoader 添加 `resolve_load_order()` 确保加载顺序

#### P2 中优先级修复
- **XSS 防护**: admin.js errorCode 添加 `escapeHtml()`
- **wp_unslash**: GeoModule/CostControlModule 统一使用 `wp_unslash()`
- **命名空间验证**: ModuleLoader 添加 `WPMind\` 前缀安全检查
- **定价数据去重**: 提取共享 `Pricing.php` 类，消除 ~160 行重复
- **GEO 设置去重**: 从 wpmind.php 移除 5 个重复的 register_setting
- **strict_types**: 29 个文件添加 `declare(strict_types=1)`

---

## [3.2.0] - 2026-02-05

### ✨ 模块化架构

将 Cost Control 和 Analytics 功能迁移为独立可选模块：

#### 🏗️ 模块系统
- **ModuleLoader**: 模块发现、加载、生命周期管理
- **ModuleInterface**: 标准模块契约
- **module.json**: 模块元数据和配置
- 支持模块启用/禁用切换

#### 📦 三个模块
- **Cost Control**: 用量追踪、预算限额、告警通知
- **Analytics**: 用量趋势、服务商对比、成本分析
- **GEO**: Markdown Feeds、llms.txt、Schema.org、AI 爬虫追踪

#### 🔧 兼容层
- 保留 `includes/Usage/`、`includes/Budget/`、`includes/Analytics/` 兼容层
- 模块加载时委托给模块实现，未加载时使用 Fallback

---

## [3.1.0] - 2026-02-05

### ✨ GEO 增强

#### 统一核心管线
- **MarkdownProcessor**: 统一的 Markdown 处理管线，替代分散的处理逻辑
- **ProcessOptions**: 处理选项封装类

#### 新功能
- **llms.txt 生成器**: `/llms.txt` 端点，AI 友好的站点描述
- **Schema.org 集成**: 自动注入结构化数据 (Article/WebPage)
- **GEO 设置界面**: 5 个配置选项的管理界面

#### 修复
- admin.js AJAX 变量错误 (wpmind_admin → wpmindData)
- 多站点缓存键问题 (添加 blog_id)
- 中文阅读时间计算 (400字/分钟)

---

## [3.0.0] - 2026-02-05

### ✨ GEO 优化模块 (Generative Engine Optimization)

面向 AI 搜索引擎的内容优化：

#### 核心功能
- **Markdown Feed**: `/?feed=markdown` 端点，AI 友好的内容格式
- **单篇 .md 支持**: 任意文章添加 `.md` 后缀获取 Markdown 版本
- **Accept 内容协商**: `Accept: text/markdown` 自动返回 Markdown
- **中文内容优化器**: 针对中文内容的 Markdown 优化
- **GEO 信号注入**: 权威性声明、引用格式等 AI 引用信号
- **AI 爬虫追踪**: 追踪 GPTBot、ClaudeBot 等 AI 爬虫访问

#### 📁 新增文件
```
includes/GEO/
├── MarkdownFeed.php        # Markdown Feed 端点
├── HtmlToMarkdown.php      # HTML 转 Markdown
├── MarkdownEnhancer.php    # Markdown 增强
├── ChineseOptimizer.php    # 中文优化
├── GeoSignalInjector.php   # GEO 信号注入
└── CrawlerTracker.php      # AI 爬虫追踪
```

---

## [2.5.0] - 2026-02-04

### ✨ 稳定性增强

#### 公共 API
- **PublicAPI 类**: 统一的 AI 能力调用接口
- **递归调用保护**: 防止无限循环
- **便捷函数**: `wpmind_chat()`, `wpmind_translate()`, `wpmind_summarize()` 等
- **图像生成 API**: 支持 8 个图像生成 Provider

#### UI 错误反馈优化
- Toast 通知位置调整
- 自动跳过不健康 Provider
- 手动优先级设置

#### 安全修复 (Codex 审计)
- 输入验证加强
- ErrorHandler 加载顺序修复

---

## [2.0.0] - 2026-02-01

### ✨ 重大更新：Gutenberg 风格设计系统

全新的现代化 UI 设计，采用 Gutenberg 设计语言：

#### 🎨 设计系统
- **CSS 变量**: 统一的颜色、间距、字体、阴影令牌
- **8px 网格系统**: 一致的间距规范
- **现代配色**: 基于 Tailwind 的灰度和状态色
- **组件化样式**: 可复用的按钮、徽章、卡片、表单组件

#### 📁 CSS 模块化
```
assets/css/
├── admin.css      # 设计系统 + 基础组件
├── panels.css     # 面板样式（Status/Budget/Routing/Analytics）
└── responsive.css # 响应式适配
```

#### 🔧 Tab 导航
- **仪表板**: 用量统计 + 分析图表 + 服务状态
- **服务配置**: AI 服务端点 + 全局设置
- **智能路由**: 路由策略 + Provider 排名
- **预算管理**: 预算限额 + 告警设置

#### 📱 响应式优化
- 平板/手机端 Tab 导航自适应
- 网格布局响应式断点
- 对话框移动端适配

---

## [1.9.0] - 2026-02-01

### ✨ 新功能：智能路由系统

基于策略的 Provider 智能路由选择，自动优化 AI 服务调用：

#### 🎯 路由策略
- **成本优先**: 选择成本最低的 Provider，适合预算敏感场景
- **延迟优先**: 选择响应最快的 Provider，适合实时性要求高的场景
- **可用性优先**: 选择健康分数最高的 Provider，适合稳定性要求高的场景
- **负载均衡**: 在多个 Provider 之间分散请求，避免单点过载

#### 🔀 复合策略
- **平衡策略**: 成本、延迟、可用性各占 1/3
- **性能优先**: 延迟 50%，可用性 30%，成本 20%
- **经济策略**: 成本 60%，可用性 30%，延迟 10%

#### 📊 可视化
- Provider 得分排名实时显示
- 故障转移链可视化
- 推荐 Provider 高亮显示

#### 🏗️ 技术实现
- 新增 `includes/Routing/` 目录
  - `RoutingStrategyInterface.php`: 策略接口定义
  - `RoutingContext.php`: 路由上下文封装
  - `AbstractStrategy.php`: 策略基类
  - `IntelligentRouter.php`: 智能路由器主类
- 新增策略实现 `includes/Routing/Strategies/`
  - `CostStrategy.php`: 成本优先策略
  - `LatencyStrategy.php`: 延迟优先策略
  - `AvailabilityStrategy.php`: 可用性优先策略
  - `LoadBalancedStrategy.php`: 负载均衡策略
  - `CompositeStrategy.php`: 复合策略
- 新增 AJAX 接口: `wpmind_get_routing_status`, `wpmind_set_routing_strategy`, `wpmind_route_request`
- 设置页面新增智能路由面板

---

## [1.8.0] - 2026-02-01

### ✨ 新功能：分析仪表板

全新的可视化分析仪表板，帮助您了解 AI 服务使用情况：

#### 📊 图表功能
- **用量趋势图**: 展示 Token 使用量和请求数的时间趋势
- **服务商对比**: 环形图展示各服务商的请求分布
- **成本趋势**: 柱状图展示 USD/CNY 费用趋势
- **模型排行**: 横向柱状图展示最常用的模型

#### 🔧 技术实现
- 集成 Chart.js 4.5.0 图表库（CDN 加载）
- 新增 `includes/Analytics/AnalyticsManager.php` 数据聚合类
- 新增 AJAX 接口 `wpmind_get_analytics_data`
- 支持 7 天/30 天时间范围切换

---

## [1.7.0] - 2026-02-01

### ✨ 新功能：预算与支出护栏

全新的预算管理系统，帮助您控制 AI 服务支出：

#### 🎯 预算限制
- **全局预算**: 设置每日/每月的支出上限
- **多币种支持**: 同时支持 USD 和 CNY 限额
- **实时监控**: 进度条显示当前预算使用情况

#### ⚠️ 告警系统
- **预警阈值**: 可配置的预警百分比（默认 80%）
- **三种执行模式**:
  - 仅告警：超限时发送通知但不阻止请求
  - 禁用服务：超限时自动禁用 AI 服务
  - 降级模型：超限时自动切换到更便宜的模型

#### 📧 通知方式
- **管理后台通知**: WordPress 原生 admin notice
- **邮件告警**: 可选的邮件通知功能
- **告警去重**: 使用 transient 防止重复告警

#### 🏗️ 技术实现
- 新增 `includes/Budget/` 目录
  - `BudgetManager.php`: 预算配置管理
  - `BudgetChecker.php`: 预算检查逻辑
  - `BudgetAlert.php`: 告警通知系统
- 新增 AJAX 接口: `wpmind_save_budget_settings`, `wpmind_get_budget_status`
- 设置页面新增预算管理面板

---

## [1.6.7] - 2026-02-01

### 🎨 图标系统修正
- **升级 Remixicon 到 4.9.1**: 使用最新版本的 Remixicon 图标库
- **使用官方 AI 品牌图标**: Remixicon 4.9.1 新增了大量 AI 服务品牌图标
  - OpenAI、Claude、Gemini、DeepSeek、Qwen、智谱 AI 等均有官方品牌图标

### 📝 更新后的图标映射表
| 服务商 | 图标 | 类型 |
|--------|------|------|
| OpenAI | `ri-openai-fill` | 品牌图标 |
| Anthropic | `ri-claude-fill` | 品牌图标 |
| Google | `ri-gemini-fill` | 品牌图标 |
| DeepSeek | `ri-deepseek-fill` | 品牌图标 |
| 通义千问 | `ri-qwen-ai-fill` | 品牌图标 |
| 智谱 AI | `ri-zhipu-ai-fill` | 品牌图标 |
| Moonshot | `ri-moon-fill` | 语义图标 |
| 豆包 | `ri-fire-fill` | 语义图标 |
| 硅基流动 | `ri-cpu-fill` | 语义图标 |
| 百度文心 | `ri-baidu-fill` | 品牌图标 |
| MiniMax | `ri-sparkling-fill` | 语义图标 |

---

## [1.6.6] - 2026-02-01

### 🎨 图标系统升级
- **引入 Remixicon**: 使用 Remixicon 4.6.0 替代 lobe-icons
  - 通过 jsDelivr CDN 加载
  - 不再依赖外部 SVG 图标
- **Provider 图标映射**: 为每个服务商配置专属图标
  - OpenAI: `ri-openai-fill`
  - Google: `ri-gemini-fill`
  - 百度: `ri-baidu-fill`
  - 其他服务商使用语义化图标
- **品牌颜色**: 为每个服务商配置品牌色

### 🔧 技术改进
- 新增 `getProviderIcon()` 方法获取 Remixicon 图标类
- 新增 `getProviderColor()` 方法获取品牌颜色
- 移除 lobe-icons CDN 依赖和 `<img>` 标签
- 使用 `<i>` 标签和 CSS 类显示图标

### 📝 图标映射表
| 服务商 | 图标 | 颜色 |
|--------|------|------|
| OpenAI | `ri-openai-fill` | #10a37f |
| Anthropic | `ri-robot-2-fill` | #d4a27f |
| Google | `ri-gemini-fill` | #4285f4 |
| DeepSeek | `ri-brain-fill` | #0066ff |
| 通义千问 | `ri-sparkling-2-fill` | #6366f1 |
| 智谱 AI | `ri-lightbulb-fill` | #1e40af |
| Moonshot | `ri-moon-fill` | #6b7280 |
| 豆包 | `ri-fire-fill` | #ef4444 |
| 硅基流动 | `ri-cpu-fill` | #8b5cf6 |
| 百度文心 | `ri-baidu-fill` | #2932e1 |
| MiniMax | `ri-magic-fill` | #f59e0b |

---

## [1.6.5] - 2026-01-31

### 🐛 高优先级修复
- **并发安全**: 使用 WordPress transient 锁防止并发写入导致数据丢失
  - `updateStats()` 和 `addToHistory()` 现在使用独立的锁机制
  - 最多重试 3 次，每次间隔 50ms
- **零 Token 请求漏记**: 即使 tokens 为 0 也计入请求数，只是不记录到历史

### 🔧 中优先级修复
- **时区修复**: 使用 `wp_date()` 替代 `date()`，统计按 WordPress 站点时区
- **类型安全**: 所有 `get_option()` 返回值添加 `is_array()` 检查，防止 TypeError

### 🎨 UI 改进
- **上次更新时间**: 标题区显示"更新于 X 分钟前"
- **空状态提示**: 无数据时显示友好的空状态界面
- **费用说明**: 添加"费用为估算值"的说明提示
- **无障碍优化**: 为刷新/清除按钮添加 `aria-label`
- **响应式布局**: 小屏幕自动切换为纵向堆叠布局

### 📱 响应式断点
- `< 782px`: 卡片内容纵向排列，2 列网格
- `< 480px`: 单列网格布局

---

## [1.6.4] - 2026-01-31

### ✨ 新功能
- **分货币费用统计**: 汇总统计分别显示 USD 和 CNY 费用，避免货币混乱
  - 今日/本周/本月/总计 费用显示格式：`$0.50 / ¥2.00`
  - 只有一种货币时只显示该货币

### 🔧 技术改进
- 数据结构改为分货币存储：`cost_usd` 和 `cost_cny`
- 新增 `formatCostByCurrency()` 方法格式化分货币显示
- `updateStats()` 根据 Provider 货币类型分别累加费用
- 优化费用显示样式，支持较长的双货币文本

### 📊 显示效果
```
今日：1.2K Tokens | $0.50 / ¥2.00 | 5 请求
本周：8.5K Tokens | $3.20 / ¥15.00 | 32 请求
```

---

## [1.6.3] - 2026-01-31

### ✨ 新功能
- **周用量统计**: 新增本周用量统计卡片
  - 统计周一到今天的累计用量
  - 显示 Tokens、费用、请求数

### 🔧 技术改进
- 新增 `getWeekStats()` 方法获取本周统计数据
- 用量统计面板现在显示：今日 → 本周 → 本月 → 总计

---

## [1.6.2] - 2026-01-31

### ✨ 新功能
- **多货币支持**: 国内服务商使用人民币 (CNY) 计价，国际服务商使用美元 (USD)
- **分渠道用量统计**: 设置页面新增各渠道独立用量统计网格
  - 显示每个 Provider 的 tokens、费用、请求数
  - 费用按服务商货币显示（$ 或 ¥）

### 🔧 技术改进
- 新增 `getCurrency()` 方法获取服务商货币类型
- 新增 `getProviderDisplayName()` 方法获取服务商中文名称
- `formatCost()` 方法支持货币参数，自动显示正确的货币符号
- PRICING 常量添加 `currency` 字段区分 USD/CNY

### 💰 定价更新（人民币计价）
| 服务商 | 输入价格 | 输出价格 | 单位 |
|--------|----------|----------|------|
| DeepSeek | ¥1 | ¥2 | /1M tokens |
| 通义千问 | ¥2 | ¥6 | /1M tokens |
| 智谱 AI | ¥1 | ¥1 | /1M tokens |
| Moonshot | ¥12 | ¥12 | /1M tokens |
| 豆包 | ¥0.8 | ¥2 | /1M tokens |
| 硅基流动 | ¥1 | ¥2 | /1M tokens |
| 百度文心 | ¥1.2 | ¥1.2 | /1M tokens |
| MiniMax | ¥1 | ¥1 | /1M tokens |

---

## [1.6.1] - 2026-01-31

### 🐛 修复
- **Anthropic 格式兼容**: 支持 `input_tokens`/`output_tokens` 格式（Anthropic API）
- **输入验证**: 确保 tokens 和延迟值非负

### 🚀 性能优化
- **对象缓存**: 使用 `wp_cache` 减少数据库读取
  - 统计数据缓存 5 分钟
  - 历史记录缓存 5 分钟
  - 清除统计时同步清除缓存

---

## [1.6.0] - 2026-01-31

### ✨ 新功能
- **Token 用量统计**: 自动追踪每次 AI 请求的 token 用量
  - 记录 input/output tokens
  - 自动计算费用估算（基于各服务商定价）
  - 今日/本月/总计统计
  - 按 Provider 和模型分类统计
- **用量统计面板**: 设置页面新增用量统计卡片
  - 实时显示 tokens、费用、请求数
  - 支持刷新和清除统计

### 📁 新增文件
```
includes/Usage/
└── UsageTracker.php    # 用量追踪核心类
```

### 🔧 技术改进
- 支持 11 个服务商的定价配置
- 自动清理 30 天前的日统计和 12 个月前的月统计
- 历史记录最多保留 1000 条

---

## [1.5.3] - 2026-01-31

### 🎨 UI 改进
- **WordPress 原生通知样式**: Toast 通知改用 WordPress 原生 `.notice` 类
- 支持 `notice-success/error/warning/info` 四种类型
- 使用 `slideDown/slideUp` 动画效果
- 移除自定义 Toast CSS 样式，保持与 WordPress 后台一致

---

## [1.5.2] - 2026-01-31

### 🐛 修复 (Codex 审查)
- **时间窗口过滤**: CircuitBreaker 现在只统计最近 60 秒内的请求计算失败率
- **类型安全**: `getData()` 和 `getAllHealth()` 添加 `is_array()` 类型检查
- **半开状态计数**: `recordFailure()` 在半开状态下正确递增 `half_open_failures`
- **延迟计算修复**: ProviderHealthTracker 使用 `foreach` 替代有问题的 `array_filter`
- **XSS 防护**: Toast 组件使用 `.text()` 方法防止 XSS 攻击

### 🎨 UI 改进
- **自定义 Dialog**: 使用自定义确认对话框替代浏览器原生 `confirm()`
- **事件委托**: 使用 `$(document).on()` 修复动态元素点击问题
- 移除所有 `alert()` 和 `confirm()` 调用

---

## [1.5.1] - 2026-01-31

### 🐛 修复
- **Transient TTL 修复**: 将熔断器状态存储时间从 600s 增加到 2400s，避免状态过早重置
- **状态查询优化**: 新增 `isAvailableReadOnly()` 方法，状态查询不再触发状态转换
- **实际请求集成**: 故障转移现在集成到实际 AI 请求流程，不仅限于测试连接
- **双重计数修复**: 测试连接请求添加 `_wpmind_skip_tracking` 标记，避免与 `http_api_debug` 钩子双重计数
- **自定义 URL 支持**: `identify_provider_from_url()` 现在支持用户自定义的 base_url

### 🔧 技术改进
- `filter_preferred_models` 现在会排除熔断中的 Provider（使用只读方法）
- 新增 `http_api_debug` 钩子追踪 AI 请求结果
- 新增 `identify_provider_from_url()` 方法识别请求目标，支持 11 个 Provider
- 添加百度和 MiniMax 的默认域名模式

---

## [1.5.0] - 2026-01-31

### ✨ 新功能
- **故障转移机制**: 实现双层故障转移架构
  - Layer 1: 软故障转移 - 单次请求失败时自动重试
  - Layer 2: 熔断器 - 持续故障时自动切换到备用服务
- **Circuit Breaker 熔断器**: 三状态模型 (Closed/Open/Half-Open)
  - 连续失败 5 次或失败率 > 40% 触发熔断
  - 20 分钟后自动恢复探测
- **Provider 健康追踪**: 记录每个服务的成功率和延迟
- **AJAX API**: 新增获取状态和重置熔断器接口

### 📁 新增文件
```
includes/Failover/
├── CircuitBreaker.php          # 熔断器核心类
├── ProviderHealthTracker.php   # 健康状态追踪
└── FailoverManager.php         # 故障转移管理器
```

### 🔧 技术改进
- 基于 Salesforce Agentforce 实践的参数配置
- 使用 WordPress transient 存储状态
- 模块化设计，可独立禁用

---

## [1.4.0] - 2026-01-31

### ✨ 新功能
- **新增百度文心供应商**: 支持 ERNIE-4.0、ERNIE-3.5 等模型
- **新增 MiniMax 供应商**: 支持 abab6.5s-chat、abab6.5-chat 等模型
- **集成 lobe-icons**: 使用 LobeHub 提供的 AI 供应商图标
- 为所有供应商添加 `icon` 配置字段
- **完整 Provider 类实现**: 为百度和 MiniMax 创建完整的 Provider 架构
  - `includes/Providers/Baidu/` - 百度 ERNIE Provider
  - `includes/Providers/MiniMax/` - MiniMax Provider

### 🎨 UI 改进
- 使用 CDN 加载供应商图标 (npmmirror.com)
- 图标加载失败时自动回退到 dashicons
- 优化图标显示样式
- 使用英文产品名称作为显示名称 (Qwen, ChatGLM, Doubao, SiliconFlow, ERNIE)

### 🔧 技术改进
- 添加百度和 MiniMax 的 API Key 验证规则
- 更新供应商配置结构，支持自定义图标
- 更新 ProviderRegistrar 支持 8 个国内 Provider

### 🛡️ 错误处理增强
- **新增 ErrorHandler 类**: 统一的错误消息映射和用户友好的错误反馈
- **HTTP 状态码映射**: 将 HTTP 错误码转换为用户友好的中文消息
- **Provider 特定错误提示**: 针对不同供应商提供具体的错误解决建议
- **自动重试机制**: 对可重试的错误（429、500、502、503、504）自动重试最多 2 次
- **指数退避**: 重试间隔采用指数退避策略（1s、2s、4s...）
- **详细错误信息**: 前端显示更详细的错误信息，支持 hover 查看详情

### 📝 供应商图标映射
| 供应商 | 图标 |
|--------|------|
| OpenAI | openai |
| Anthropic | claude |
| Google AI | gemini |
| DeepSeek | deepseek |
| 通义千问 | qwen |
| 智谱 AI | zhipu |
| Moonshot | kimi |
| 豆包 | doubao |
| 硅基流动 | siliconcloud |
| 百度文心 | wenxin |
| MiniMax | minimax |

---

## [1.3.1] - 2026-01-31

### 🐛 Bug 修复
- 修复高级设置按钮无响应问题(通过版本号更新强制缓存刷新)
- 修复 HTML 验证错误: label 的 `for` 属性指向不存在的 input ID
- **修复样式丢失问题**: 更新 hook suffix 从 `settings_page_wpmind` 到 `toplevel_page_wpmind`

### ✨ 功能改进
- **提升为一级菜单**: WPMind 现在是 WordPress 后台的一级菜单,名称为"心思"
- **优化服务显示名称**: 使用更友好的产品名称(如 ChatGPT、Claude、Gemini)替代技术 ID
- **统一 API Key 管理**: 所有 AI 服务(包括官方服务)现在都在 WPMind 中直接管理
- 移除"由 WordPress AI Client 管理"的跳转提示
- 官方服务的 API Key 会自动同步到 WordPress AI Client
- 简化连接测试按钮逻辑,所有服务统一处理

### 🎨 UI/UX 改进
- 菜单图标: 使用心形图标 (dashicons-heart)
- 菜单位置: 位于 WordPress 后台左侧菜单栏第 30 位
- 服务标识: 显示产品名称而非技术 ID
  - openai → ChatGPT
  - anthropic → Claude
  - google → Gemini
  - moonshot → Kimi
  - zhipu → 智谱清言
  - doubao → 豆包

### 🔧 技术改进
- 添加 JavaScript 调试日志,便于问题诊断
- 优化高级设置切换功能的控制台输出
- 改进代码注释和文档

### 📝 技术细节
- 更新插件版本号从 1.3.0 到 1.3.1
- 将 `add_options_page()` 改为 `add_menu_page()` 创建一级菜单
- 为每个服务添加 `display_name` 字段
- 在 `initAdvancedToggle()` 函数中添加调试信息
- 修改 `settings-page.php` 模板,移除官方服务的特殊处理
- 所有服务统一使用 API Key 输入界面

---

## [1.3.0] - 2026-01-26

### ✨ 新增功能
- 完整的 Provider 架构实现
- 6 个国内 AI 服务 Provider (DeepSeek、Qwen、Zhipu、Moonshot、Doubao、SiliconFlow)
- 3 个官方服务支持 (OpenAI、Anthropic、Google AI)
- WordPress AI 原生功能支持(标题生成已验证)
- API Key 格式验证(9 种服务的正则表达式验证)
- 连接测试功能
- 自定义 Base URL 支持
- 折叠式高级设置界面

### 🔧 技术改进
- AuthenticatedProviderAvailability - 解决 API Key 认证传递问题
- AbstractOpenAiCompatibleTextGenerationModel - 强制 n=1 适配国内 API
- pre_option_ filter - 合并 WPMind 凭据到 AI Client
- 实时 API Key 格式验证
- AJAX 连接测试

### 🐛 已解决的问题
- Provider 注册时机问题(改用 init 钩子)
- HTTP transporter 未初始化问题
- candidateCount 限制导致模型被过滤问题
- API n 参数不支持多候选问题
