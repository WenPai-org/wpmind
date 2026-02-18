# WPMind GEO 模块评估报告

> 评估日期: 2026-02-05
> 评估目的: 确保 WPMind GEO 模块与 WordPress 官方 AI 发展路径协同而非冲突

---

## 一、官方 WordPress AI 现状分析

### 1.1 官方 Markdown Feeds (PR #194)

| 项目 | 详情 |
|------|------|
| PR 链接 | https://github.com/WordPress/ai/pull/194 |
| 状态 | Open |
| 里程碑 | Future Release |
| 作者 | @Jameswlepage |
| 最后更新 | 2026-02-04 |

**官方功能范围：**
- `/?feed=markdown` Feed 端点
- `.md` 后缀访问单篇文章
- `Accept: text/markdown` 内容协商
- `WP_HTML_Processor` HTML 转 Markdown
- 设置开关控制各功能
- 提供 Section Filters 供扩展

**官方提供的 Filter Hooks：**
```php
// Feed 输出过滤
ai_experiments_markdown_feed_post_sections

// 单篇文章输出过滤
ai_experiments_markdown_singular_post_sections
```

### 1.2 官方完全没有的功能

| 功能 | 官方状态 | WPMind 状态 |
|------|----------|-------------|
| 国产 AI 模型支持 | ❌ 无 | ✅ 11个 Provider |
| 中文内容优化 | ❌ 无 | ✅ ChineseOptimizer |
| GEO 信号注入 | ❌ 无 | ✅ GeoSignalInjector |
| AI 爬虫追踪 | ❌ 无 | ✅ CrawlerTracker |
| 智能路由 | ❌ 无 | ✅ 5种策略 |
| 预算管理 | ❌ 无 | ✅ BudgetManager |

---

## 二、WPMind GEO 模块架构评估

### 2.1 当前架构（正确）

```
┌─────────────────────────────────────────────────────────┐
│                    WPMind GEO 模块                       │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────┐    ┌─────────────────┐            │
│  │ MarkdownEnhancer│    │  MarkdownFeed   │            │
│  │   (增强模式)     │    │   (独立模式)     │            │
│  └────────┬────────┘    └────────┬────────┘            │
│           │                      │                      │
│           ▼                      ▼                      │
│  官方插件已安装时          官方插件未安装时              │
│  通过 Filter 增强          提供完整功能                  │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                    共享组件                              │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │ChineseOptimizer │  │GeoSignalInjector│              │
│  └─────────────────┘  └─────────────────┘              │
│  ┌─────────────────┐  ┌─────────────────┐              │
│  │HtmlToMarkdown   │  │ CrawlerTracker  │              │
│  └─────────────────┘  └─────────────────┘              │
└─────────────────────────────────────────────────────────┘
```

### 2.2 架构评估结论

**✅ 正确的设计决策：**

1. **双模式架构** - MarkdownEnhancer (增强) + MarkdownFeed (独立)
2. **官方 Filter 集成** - 使用 `ai_experiments_markdown_*_sections` filters
3. **功能开关** - 用户可独立控制各功能
4. **差异化定位** - 专注官方没有的功能

**⚠️ 需要注意的风险：**

1. **功能重叠** - 独立模式的 Markdown Feed 与官方功能重叠
2. **Filter 名称变化** - 官方 Filter 名称可能在正式发布时变化
3. **HTML 转换差异** - WPMind 使用自己的转换器，可能与官方输出不一致

---

## 三、与官方路径的协同策略

### 3.1 核心原则

```
WPMind 定位 = 官方功能的增强层 + 国产化适配 + 差异化功能
```

### 3.2 具体策略

| 场景 | WPMind 行为 | 原因 |
|------|-------------|------|
| 官方插件已安装 | 禁用独立 Markdown Feed，仅通过 Filter 增强 | 避免冲突 |
| 官方插件未安装 | 提供独立 Markdown Feed | 填补空白 |
| 官方 PR 合并后 | 自动切换到增强模式 | 无缝过渡 |

### 3.3 WPMind 专注领域（不与官方冲突）

1. **国产 AI 模型支持** - 官方完全没有
2. **中文内容优化** - 官方没有本土化考虑
3. **GEO 信号注入** - 官方只做基础 Markdown 转换
4. **AI 爬虫追踪** - 官方没有分析功能
5. **智能路由** - 官方没有多模型调度
6. **预算管理** - 官方没有成本控制

---

## 四、GEO 行业最佳实践对照

### 4.1 2026 GEO 最佳实践

根据 [Firebrand Marketing](https://www.firebrand.marketing/2025/12/geo-best-practices-2026/) 等来源：

| 最佳实践 | WPMind 支持 | 优先级 |
|----------|-------------|--------|
| 结构化内容 (FAQ, 清单) | ⚠️ 部分 | 高 |
| Schema.org 标记 | ❌ 未实现 | 高 |
| 权威性信号 (作者、日期) | ✅ 已实现 | - |
| 引用格式 | ✅ 已实现 | - |
| 第三方引用追踪 | ❌ 未实现 | 中 |
| 跨渠道信号整合 | ❌ 未实现 | 低 |

### 4.2 建议新增功能

**高优先级：**
1. **Schema.org 集成** - 添加 Article、FAQ、HowTo 等结构化数据
2. **FAQ 自动提取** - 从内容中识别问答对
3. **llms.txt 支持** - 新兴标准，告诉 AI 如何处理网站

**中优先级：**
4. **引用监控** - 追踪内容在 AI 回答中的出现
5. **内容新鲜度信号** - 强调最后更新时间

---

## 五、代码改进建议

### 5.1 MarkdownFeed.php 改进

```php
// 当前：检测官方插件类
if ( class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' ) ) {
    return; // 不激活独立模式
}

// 建议：增加更精确的检测
private function should_use_standalone_mode(): bool {
    // 1. 官方插件未安装
    if ( ! class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' ) ) {
        return true;
    }

    // 2. 官方插件已安装但 Markdown Feeds 未启用
    if ( ! get_option( 'ai_experiments_markdown_feeds_enabled', false ) ) {
        return true;
    }

    return false;
}
```

### 5.2 Filter 名称兼容性

```php
// 建议：支持多个可能的 Filter 名称
private function get_feed_filter_name(): string {
    // 优先使用官方 Filter
    if ( has_filter( 'ai_experiments_markdown_feed_post_sections' ) ) {
        return 'ai_experiments_markdown_feed_post_sections';
    }
    // 备用名称（官方可能改名）
    if ( has_filter( 'ai_experiments_markdown_post_sections' ) ) {
        return 'ai_experiments_markdown_post_sections';
    }
    // WPMind 自己的 Filter
    return 'wpmind_markdown_post_sections';
}
```

---

## 六、结论与建议

### 6.1 总体评估

| 维度 | 评分 | 说明 |
|------|------|------|
| 与官方协同 | ⭐⭐⭐⭐ | 双模式架构正确，使用官方 Filter |
| 差异化定位 | ⭐⭐⭐⭐⭐ | 国产模型、中文优化、爬虫追踪独占 |
| 功能完整性 | ⭐⭐⭐ | 缺少 Schema.org、FAQ 提取 |
| 代码质量 | ⭐⭐⭐⭐ | 通过 Codex 评审，结构清晰 |

### 6.2 下一步行动

**立即执行：**
1. ✅ GEO 设置界面 - 已完成
2. 更新 MarkdownFeed 检测逻辑 - 更精确判断何时使用独立模式

**短期规划 (v3.1)：**
3. 添加 Schema.org 结构化数据支持
4. 实现 llms.txt 生成器

**中期规划 (v3.5)：**
5. FAQ 自动提取功能
6. AI 引用监控仪表板

---

## 七、参考资源

- [WordPress AI PR #194](https://github.com/WordPress/ai/pull/194)
- [GEO Best Practices 2026](https://www.firebrand.marketing/2025/12/geo-best-practices-2026/)
- [Generative Engine Optimization Guide](https://geneo.app/blog/best-geo-strategies-2026-generative-engine-optimization/)
- [WPMind Roadmap](./WPMIND-ROADMAP.md)

---

*报告生成: 2026-02-05 | 作者: Claude Opus 4.5*
