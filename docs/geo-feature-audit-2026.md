# GEO 模块功能评审 (2026-02-08)

## 调研背景

对比 WPMind GEO 模块与市场竞品，评估功能完整性并规划下一步开发。

## 市场竞品分析

### 主要竞品

| 插件 | 核心功能 | 特色 |
|------|----------|------|
| PlugStudio AI SEO & GEO | Entity Linking + AI Summary + geo-sitemap.xml | Wikidata 实体关联，Ghost Mode 摘要 |
| GEO Pilot | llms.txt + AI Context Summary + Jargon Slayer | 编辑器 AI 摘要字段，废话词检测 |
| Opttab AI Visibility | GEO Score + ai.txt + 改进建议 | 本地 GEO 评分分析 |
| LLMS Central Bot Tracker | 16+ AI 爬虫追踪 + llms.txt | 外部分析平台 |
| Meerkat Markdown | llms.txt + .html.md + html-md-sitemap.xml | Markdown 页面 + 专属 sitemap |
| Better Robots.txt | robots.txt AI 爬虫管理 | 可视化 Allow/Disallow |
| Block AI Crawlers | AI 爬虫屏蔽 | 一键屏蔽所有 AI 爬虫 |

### WPMind 已覆盖功能 (7项)

| 功能 | 组件 | 对标竞品 |
|------|------|----------|
| Markdown Feed + .md 后缀 | MarkdownFeed | Meerkat Markdown |
| llms.txt + llms-full.txt | LlmsTxtGenerator | GEO Pilot, LLMS Central |
| Schema.org 结构化数据 | SchemaGenerator | PlugStudio |
| AI 爬虫追踪 | CrawlerTracker | LLMS Central |
| AI 索引指令 (noai/nollm) | AiIndexingManager | Block AI Crawlers |
| AI Sitemap | AiSitemapGenerator | PlugStudio geo-sitemap |
| 中文优化 + GEO 信号 | ChineseOptimizer + GeoSignalInjector | 无竞品 (独有) |

### 市场存在但 WPMind 缺少的功能

#### 高价值 (计划实现)

| 功能 | 说明 | 代表竞品 |
|------|------|----------|
| **AI Context Summary** | 编辑器 AI 摘要字段，控制 AI 如何描述文章 | GEO Pilot, PlugStudio |
| **Entity Linking** | Wikidata 实体关联，JSON-LD sameAs/about | PlugStudio |
| **robots.txt AI 管理** | 可视化管理 AI 爬虫 Allow/Disallow | Better Robots.txt |

#### 中等价值 (暂缓)

| 功能 | 说明 | 原因 |
|------|------|------|
| GEO Score 评分 | 页面 AI 可发现性评分 | 需要评分算法设计 |
| ai.txt 文件 | AI 专用策略声明文件 | 标准尚未成熟 |
| Markdown Sitemap | html-md-sitemap.xml | 优先级较低 |
| 内容质量分析 | 废话词检测 | 需要词库维护 |

#### 需要外部服务 (不做)

| 功能 | 原因 |
|------|------|
| AI 可见性追踪 | 需要付费 API |
| AI 内容自动生成 | 需要 API Key，与插件定位不符 |

## 架构评审

### 当前状态

- **组件数**: 12 个类文件 (~3,000 行代码)
- **设置页**: 407 行，7 个 section (左栏 6 + 右栏 1)
- **JS**: 115 行 (admin-geo.js)
- **新增 3 个功能后**: 预计 10 个 section，~15 个组件

### 方案对比

#### 方案 A: 拆分为多个模块

```
modules/
├── geo/           → 基础 GEO (Markdown, llms.txt, 中文优化, GEO 信号)
├── geo-schema/    → 结构化数据 (Schema.org, Entity Linking)
├── geo-access/    → AI 访问控制 (索引指令, robots.txt 管理)
└── geo-content/   → AI 内容 (AI Sitemap, AI Summary)
```

**优点**: 职责清晰，可独立启用/禁用
**缺点**: 模块间依赖复杂 (AI Sitemap 依赖 AiIndexingManager)，用户需要在多个 tab 间切换，过度工程化

#### 方案 B: 保持单模块 + 设置页内子导航

```
GEO 优化 Tab
├── [内容输出]  Markdown Feed / llms.txt / AI Sitemap
├── [结构化数据] Schema.org / Entity Linking
├── [AI 控制]   AI 索引指令 / robots.txt 管理 / AI Summary
└── [监控]      爬虫统计 (始终显示在右栏)
```

**优点**: 功能集中，组件可共享依赖，用户体验统一
**缺点**: 单模块代码量较大

#### 方案 C: 保持现状，直接追加 section

**优点**: 最简单
**缺点**: 10 个 section 滚动过长，用户体验差

### 推荐: 方案 B

理由:
1. **功能内聚**: 所有功能都是 GEO 范畴，拆分模块是人为割裂
2. **依赖简单**: AI Sitemap 用 AiIndexingManager，Entity Linking 增强 Schema.org，同模块内调用更自然
3. **用户体验**: 一个 tab 内用 pill 导航分组，比多个 tab 更直观
4. **可扩展**: 子导航模式可以继续添加分组而不影响整体结构
5. **WordPress 惯例**: WooCommerce、Rank Math 等大型插件都采用 tab 内子导航

## 实施计划 (方案 B)

### 新增组件

| 文件 | 功能 |
|------|------|
| `includes/AiSummaryManager.php` | AI Context Summary 编辑器字段 + meta 输出 |
| `includes/EntityLinker.php` | Wikidata 实体关联 + Schema.org sameAs |
| `includes/RobotsTxtManager.php` | robots.txt AI 爬虫规则管理 |

### 设置页重构

将 settings.php 从平铺 section 改为 pill 子导航:

```
[内容输出] [结构化数据] [AI 控制] [监控]
```

每个子 tab 只显示对应的 section，右栏爬虫统计始终可见。

### 功能详情

#### 1. AI Context Summary (AI 摘要)
- 编辑器 metabox: textarea 输入 AI 专用摘要
- 自动 fallback: 无手动摘要时从 excerpt 生成
- 前端输出: `<meta name="description" data-ai-summary="...">` 或 JSON-LD
- Post meta: `_wpmind_ai_summary`
- 设置: 启用开关 + 自动生成策略 (excerpt/首段/禁用)

#### 2. Entity Linking (实体关联)
- 编辑器 metabox: 输入 Wikidata URL 或搜索实体
- Schema.org 输出: 在 Article JSON-LD 中添加 `about.sameAs` 指向 Wikidata
- Post meta: `_wpmind_entity_url`, `_wpmind_entity_name`
- 设置: 启用开关

#### 3. robots.txt AI 管理
- 设置页: 列出已知 AI 爬虫 (GPTBot, ClaudeBot, PerplexityBot 等)
- 每个爬虫可选: Allow / Disallow / 不管理
- 通过 `robots_txt` filter 注入规则
- 不修改物理 robots.txt 文件

## 参考资料

- [PlugStudio AI SEO & GEO](https://mfe.wordpress.org/plugins/mz-ai-seo-geo-optimize-for-chatgpt-gemini-searchgpt/)
- [GEO Pilot](https://wenpai.org/plugins/zh-hk/geo-pilot/)
- [Opttab AI Visibility](https://wordpress.org/plugins/opttab-ai-visibility-geo/)
- [LLMS Central Bot Tracker](https://wordpress.org/plugins/llms-central-ai-bot-tracker/)
- [Meerkat Markdown](https://roh.wordpress.org/plugins/meerkat-markdown-for-ai-visibility/)
- [Tekta.ai GEO Guide](https://www.tekta.ai/guides/ai-search-optimization-geo-llms-txt)
- [Superlines GEO Best Practices 2026](https://www.superlines.io/articles/generative-engine-optimization-best-practices)
- [WordPress AEO & GEO Guide](https://www.nikhilmakwana.com/blog/optimize-wordpress-aeo-geo-ai-search/)
