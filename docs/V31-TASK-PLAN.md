# WPMind v3.1 任务计划

> 创建日期: 2026-02-05
> 状态: ✅ Codex 评审通过 (已根据反馈修订)
> 基于: GEO 评估报告 + Codex 反馈

---

## 一、版本目标

**v3.1.0 - GEO 增强版**

核心目标：
1. 统一核心管线，确保 MarkdownFeed 和 MarkdownEnhancer 输出一致
2. 实现 llms.txt 生成器，支持新兴 AI 爬虫标准
3. 集成 Schema.org 结构化数据，提升 AI 可理解性

---

## 二、任务详情

### Task 1: 统一核心管线

**优先级**: P0 (最高) - 质量基线，必须先行
**预估复杂度**: 中等
**依赖**: 无

#### 1.1 问题分析

当前架构存在双实现问题：

```
MarkdownFeed (独立模式)
├── 使用 HtmlToMarkdown 转换
├── 直接调用 ChineseOptimizer
└── 直接调用 GeoSignalInjector

MarkdownEnhancer (增强模式)
├── 接收官方已转换的 Markdown
├── 调用 ChineseOptimizer
└── 调用 GeoSignalInjector
```

**问题**：
- 两个模式的处理流程不完全一致
- MarkdownEnhancer 有 `add_metadata_section`，MarkdownFeed 没有
- 未来维护需要同步修改两处
- **[Codex 反馈]** 缺少输入契约和幂等性设计

#### 1.2 解决方案

创建统一的 `MarkdownProcessor` 核心类：

```php
// includes/GEO/MarkdownProcessor.php
class MarkdownProcessor {
    /**
     * 处理选项配置
     */
    private ProcessOptions $options;

    /**
     * 已处理标记，防止重复处理
     */
    private const PROCESSED_MARKER = '<!-- wpmind-processed -->';

    /**
     * 处理 Markdown sections
     *
     * @param array    $sections 输入必须是 "已转换 Markdown + sections 结构"
     * @param WP_Post  $post     文章对象
     * @param ProcessOptions $options 处理选项
     * @return array 处理后的 sections
     */
    public function process(array $sections, WP_Post $post, ?ProcessOptions $options = null): array {
        // 幂等性检查：如果已处理则直接返回
        if ($this->is_already_processed($sections)) {
            return $sections;
        }

        // 1. 中文优化 (可选)
        // 2. GEO 信号注入 (可选)
        // 3. 元数据添加 (可选)
        // 4. 添加已处理标记
    }
}

/**
 * 处理选项类
 */
class ProcessOptions {
    public bool $add_metadata = true;
    public bool $allow_rewrite = true;
    public bool $skip_language_opt = false;
    public bool $skip_geo_signals = false;
}
```

#### 1.3 实现步骤

1. 创建 `includes/GEO/MarkdownProcessor.php`
2. 创建 `includes/GEO/ProcessOptions.php`
3. 将共享逻辑从 MarkdownFeed 和 MarkdownEnhancer 提取到 MarkdownProcessor
4. 修改 MarkdownFeed 使用 MarkdownProcessor
5. 修改 MarkdownEnhancer 使用 MarkdownProcessor
6. 添加幂等性测试

#### 1.4 验收标准

- [ ] MarkdownFeed 和 MarkdownEnhancer 使用相同的 MarkdownProcessor
- [ ] 两种模式对相同内容产生相同的 GEO 增强输出
- [ ] 所有现有功能保持正常
- [ ] **[Codex]** 幂等性测试通过：同一内容重复处理不重复注入
- [ ] **[Codex]** 输入契约明确：统一为 "已转换 Markdown + sections 结构"

#### 1.5 边界情况 (Codex 反馈)

- 幂等性：重复处理不会重复插入元数据/信号
- 输入来源差异：HTML→Markdown 与原生 Markdown 的格式漂移
- 非中文内容：需要语言检测或可选开关 (`skip_language_opt`)

---

### Task 2: llms.txt 生成器

**优先级**: P0 (最高) - 可与 Task 1 并行推进 UI/路由
**预估复杂度**: 中等
**依赖**: 无 (可并行)

#### 2.1 背景

llms.txt 是新兴标准 (来源: [AnswerDotAI/llms-txt](https://github.com/AnswerDotAI/llms-txt))：
- **目的**: 提供网站内容的上下文和导航，帮助 AI 理解网站结构
- **注意**: 不是访问控制（访问控制应使用 robots.txt）
- **格式**: Markdown 格式，包含 H1 标题、blockquote 摘要、H2 分组链接列表

#### 2.2 官方规范格式 (Codex 修正)

```markdown
# 站点名称

> 站点简短描述（一句话摘要）

这里是补充说明段落，可以包含更多上下文信息。
引用格式偏好等信息可以放在这里。

## 文档

- [入门指南](/docs/getting-started): 快速开始使用本站
- [API 文档](/docs/api): 完整的 API 参考

## 博客

- [最新文章](/blog): 技术博客和更新

## Optional

- [关于我们](/about): 团队介绍
- [联系方式](/contact): 联系信息
```

**关键格式要求** (来自官方规范):
- H1: 站点名称
- Blockquote: 简短摘要
- 普通段落: 补充说明
- H2: 内容分组
- 链接列表: `- [标题](URL): 说明`
- `## Optional`: 可跳过的信息分组

#### 2.3 实现步骤

1. 创建 `includes/GEO/LlmsTxtGenerator.php`
2. 注册 `/llms.txt` 路由 (WordPress rewrite rules)
3. 实现动态生成逻辑：
   - 站点名称和描述
   - 按分类/类型分组的内容链接
   - 可选信息分组
4. 添加缓存策略 (避免大站点性能问题)
5. 添加设置界面选项
6. 实现格式验证器

#### 2.4 文件结构

```php
// includes/GEO/LlmsTxtGenerator.php
class LlmsTxtGenerator {
    public function __construct();
    public function register_routes(): void;
    public function render(): void;

    private function get_site_header(): string;      // H1 + blockquote
    private function get_description(): string;      // 补充说明段落
    private function get_content_sections(): array;  // H2 分组 + 链接列表
    private function get_optional_section(): string; // ## Optional

    // 缓存相关
    private function get_cached_content(): ?string;
    private function set_cache(string $content): void;
    private function invalidate_cache(): void;       // 发布/更新时触发
}
```

#### 2.5 验收标准

- [ ] 访问 `/llms.txt` 返回正确格式的内容
- [ ] **[Codex]** 格式符合官方规范 (H1/blockquote/H2 链接列表)
- [ ] **[Codex]** 不包含访问控制语义
- [ ] 内容动态反映站点配置
- [ ] 设置界面可控制 llms.txt 生成
- [ ] 仅包含公开内容 (排除草稿/私密/会员内容)
- [ ] 大站点性能可接受 (缓存策略)

#### 2.6 边界情况 (Codex 反馈)

- 大站点性能：需要缓存，发布/分类更新时触发刷新
- 内容过滤：仅公开内容，排除草稿/私密/会员内容
- 多站点部署：子目录路径支持

---

### Task 3: Schema.org 集成

**优先级**: P1 (高) - 需先完成兼容模式设计
**预估复杂度**: 中等
**依赖**: 需先设计兼容模式策略

#### 3.1 背景

Schema.org 结构化数据帮助 AI 理解内容语义：
- Article - 文章基本信息
- FAQPage - 问答内容
- HowTo - 教程步骤
- BreadcrumbList - 导航路径

#### 3.2 兼容模式设计 (Codex 反馈)

```php
/**
 * Schema 输出模式
 */
enum SchemaMode: string {
    case AUTO = 'auto';    // 检测 SEO 插件/已有 JSON-LD，有则不输出
    case MERGE = 'merge';  // 合并到已有 @graph
    case FORCE = 'force';  // 强制输出（可能重复）
}
```

**插件检测策略**:
```php
private function should_output_schema(): bool {
    // 检测常见 SEO 插件
    $seo_plugins = [
        'wordpress-seo/wp-seo.php',           // Yoast SEO
        'seo-by-rank-math/rank-math.php',     // Rank Math
        'all-in-one-seo-pack/all_in_one_seo_pack.php', // AIOSEO
    ];

    foreach ($seo_plugins as $plugin) {
        if (is_plugin_active($plugin)) {
            return $this->mode === SchemaMode::FORCE;
        }
    }

    // 检测页面已有 JSON-LD
    // ...

    return true;
}
```

#### 3.3 实现范围

**Phase 1 (v3.1)**:
- Article schema 自动生成 (选择最具体类型: Article/NewsArticle/BlogPosting)
- 作者信息 (Person schema)
- 发布/更新时间
- 兼容模式实现

**Phase 2 (v3.5)**:
- FAQ 自动检测和 schema 生成
- HowTo 步骤提取

#### 3.4 实现步骤

1. 创建 `includes/GEO/SchemaGenerator.php`
2. 实现兼容模式检测
3. 实现 Article schema 生成 (按 Google 指南)
4. 仅在 `is_singular()` 内容页输出
5. 在 `wp_head` 注入 JSON-LD
6. 添加设置选项控制
7. 实现降级策略 (缺少作者/图片/日期时)

#### 3.5 输出示例 (按 Google 指南)

```json
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": "文章标题",
  "author": {
    "@type": "Person",
    "name": "作者名",
    "url": "https://example.com/author/name"
  },
  "datePublished": "2026-02-05T10:00:00+08:00",
  "dateModified": "2026-02-05T15:30:00+08:00",
  "publisher": {
    "@type": "Organization",
    "name": "站点名称",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.png"
    }
  },
  "image": "https://example.com/featured-image.jpg",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://example.com/post-url"
  }
}
```

#### 3.6 验收标准

- [ ] 文章页面自动输出 Article schema
- [ ] Schema 通过 Google Rich Results Test 验证
- [ ] 设置界面可控制 schema 生成
- [ ] **[Codex]** 兼容模式正常工作 (auto/merge/force)
- [ ] **[Codex]** 仅在 `is_singular()` 输出，避免归档页误注入
- [ ] **[Codex]** 选择最具体的 Article 类型
- [ ] **[Codex]** 缺少字段时有降级策略

#### 3.7 边界情况 (Codex 反馈)

- 仅在 `is_singular()` 内容页输出
- 缺少作者/图片/日期时的降级策略
- 兼容插件存在 `@graph` 时的合并策略
- 字段需与页面可见内容一致

---

## 三、文件变更清单

### 新增文件

| 文件 | 用途 |
|------|------|
| `includes/GEO/MarkdownProcessor.php` | 统一 Markdown 处理核心 |
| `includes/GEO/ProcessOptions.php` | 处理选项配置类 |
| `includes/GEO/LlmsTxtGenerator.php` | llms.txt 生成器 |
| `includes/GEO/SchemaGenerator.php` | Schema.org 生成器 |

### 修改文件

| 文件 | 变更 |
|------|------|
| `includes/GEO/MarkdownFeed.php` | 使用 MarkdownProcessor |
| `includes/GEO/MarkdownEnhancer.php` | 使用 MarkdownProcessor |
| `templates/tabs/geo.php` | 添加 llms.txt 和 Schema 设置 |
| `wpmind.php` | 注册新组件，添加 AJAX 处理 |

---

## 四、测试计划

### 4.1 单元测试

- MarkdownProcessor 输出一致性测试
- **[Codex]** MarkdownProcessor 幂等性测试
- LlmsTxtGenerator 格式验证 (符合官方规范)
- SchemaGenerator JSON-LD 验证

### 4.2 集成测试

- 独立模式 + 增强模式切换测试
- 与官方 AI Experiments 插件兼容性测试
- 与常见 SEO 插件兼容性测试 (Yoast, Rank Math, AIOSEO)
- **[Codex]** Schema 兼容模式测试

### 4.3 手动测试

- wpcy.com 测试站点验证
- Google Rich Results Test
- AI 爬虫模拟访问
- **[Codex]** llms.txt 格式验证器

---

## 五、风险评估

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| Schema 与 SEO 插件冲突 | 中 | 兼容模式设计 (auto/merge/force) |
| llms.txt 标准变化 | **中** (Codex 调整) | 模块化设计 + 可配置模板 + 版本标识 |
| 官方 Filter 名称变化 | 中 | 支持多个 Filter 名称 |
| **[Codex]** 重复处理导致内容膨胀 | 中 | 幂等性设计 + 已处理标记 |
| **[Codex]** 大站点 llms.txt 性能 | 中 | 缓存策略 + 增量更新 |

---

## 六、时间线与依赖

| 阶段 | 任务 | 依赖 | 并行策略 |
|------|------|------|----------|
| Phase 1 | Task 1: 统一核心管线 | 无 | 质量基线，必须先行 |
| Phase 1-2 | Task 2: llms.txt (UI/路由) | 无 | 可与 Task 1 并行 |
| Phase 2 | Task 2: llms.txt (缓存/验证) | Task 1 完成 | - |
| Phase 3 | Task 3: Schema 兼容模式设计 | Task 1 完成 | 设计先行 |
| Phase 3 | Task 3: Schema 实现 | 兼容模式设计完成 | - |
| Phase 4 | 测试 + 文档 + 发布 | 所有任务完成 | - |

---

## 七、Codex 评审结论

### 评审日期: 2026-02-05

**评审结果**: ✅ 通过 (已根据反馈修订)

**主要反馈已整合**:
1. ✅ Task 1: 添加输入契约和幂等性设计
2. ✅ Task 2: 修正 llms.txt 格式为官方规范
3. ✅ Task 3: 添加兼容模式设计 (auto/merge/force)
4. ✅ 风险评估: llms.txt 标准变化风险调整为中等
5. ✅ 时间线: 添加依赖和并行策略

**Open Questions (待实现时确认)**:
- llms.txt 是否需要同步产出 `.md` 镜像页面？
- Schema 合并是"追加新 script"还是"合并进已有 @graph"？
- MarkdownProcessor 的"元数据段"是否需要可配置差异？

---

*计划创建: 2026-02-05 | 作者: Claude Opus 4.5*
*Codex 评审: 2026-02-05 | 状态: 通过*
