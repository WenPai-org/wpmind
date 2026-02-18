# GEO 品牌实体优化调研

*调研日期: 2026-02-09*

## 核心结论

品牌实体是 GEO 的基础层，不是可选功能。AI 搜索引擎在决定引用谁时，首先评估的是实体身份，而非页面内容。

## 行业背景

### Algorithmic Trinity（算法三位一体）

来源：Kalicube (Jason Barnard, 2026-01)

| 层级 | 功能 | 对品牌的意义 |
|------|------|-------------|
| **Knowledge Graph** | 理解层 — 实体身份识别 | AI 必须先知道"你是谁" |
| **Search Engine** | 检索层 — 信息来源 | 排名只是候选，不是终点 |
| **LLM** | 合成层 — 生成回答 | AI 只推荐它"信任"的实体 |

### UCD 框架

```
Understandability（可理解性）→ AI 认识你，不再误述你
    ↓
Credibility（可信度）→ AI 信任你，把你列入候选
    ↓
Deliverability（可传递性）→ AI 主动推荐你
```

### 三层结构化数据

来源：Ranktracker AEO Guide

1. **Entity-Level Schema** — 定义"谁"（Organization, Person, Product）
2. **Content-Level Schema** — 定义"什么"（Article, FAQPage, HowTo）
3. **Relationship Schema** — 定义"关联"（sameAs, about, mentions）

## WPMind 现有覆盖

| 能力 | 状态 | 说明 |
|------|------|------|
| Article Schema | ✅ | BlogPosting/NewsArticle + author + publisher |
| Publisher (Organization) | ⚠️ 基础 | 只有 name + logo，缺少 url/sameAs/社交/联系方式 |
| 文章级实体关联 | ✅ | EntityLinker — about 属性链接 Wikidata |
| 站点级品牌实体 | ❌ | 没有 Organization 完整 Schema |
| 社交媒体 sameAs | ❌ | 没有社交档案链接 |
| Brand Schema | ❌ | 没有品牌类型支持 |
| LocalBusiness | ❌ | 没有本地商家 Schema |
| Author E-E-A-T | ⚠️ 基础 | Person 只有 name + url |

## 竞品对比

| 功能 | Rank Math | Yoast | WPMind |
|------|-----------|-------|--------|
| Organization Schema 设置 | ✅ | ✅ | ❌ |
| 社交媒体档案 (sameAs) | ✅ | ✅ | ❌ |
| Knowledge Graph 设置 | ✅ | ✅ | ❌ |
| LocalBusiness Schema | ✅ (Pro) | ✅ (Pro) | ❌ |
| Author Schema 增强 | ✅ | ✅ | ⚠️ 基础 |
| 文章级实体关联 | ❌ | ❌ | ✅ |
| AI 爬虫管理 | ❌ | ❌ | ✅ |
| FAQ Schema 自动生成 | ❌ | ❌ | ✅ |

## 实施建议

增强现有 GEO 模块，不需要独立模块。在 GEO 设置页新增"品牌实体"子标签。

### 功能范围

1. **组织信息**: 类型/名称/描述/Logo/URL/创立日期
2. **社交档案**: Facebook/Twitter/LinkedIn/YouTube/GitHub/微博/知乎 → sameAs
3. **联系信息**: 地址/电话/邮箱（LocalBusiness 需要）
4. **Knowledge Graph**: 组织 Wikidata ID / Wikipedia URL
5. **Author 增强**: 默认作者 Schema 模板

### 技术要点

- 增强 SchemaGenerator.php 的 publisher 输出
- 首页输出独立 Organization JSON-LD
- 通过 wpmind_article_schema filter 丰富 publisher
- 复用现有 GEO 子标签 UI 模式

## 参考资料

- [Kalicube: Entity-Based Brand Strategy in the AI Era](https://kalicube.com/learning-spaces/faq-list/generative-ai/the-foundational-principles-of-generative-engine-optimization-a-definitive-analysis-of-entity-based-brand-strategy-in-the-ai-era/)
- [Forbes: 2026 GEO Strategy](https://www.forbes.com/councils/forbesagencycouncil/2026/01/21/2026-geo-strategy-optimizing-your-content-for-ai-powered-search/)
- [Ranktracker: Structured Data for AEO Guide](https://www.ranktracker.com/blog/structured-data-for-aeo-guide/)
- [Hook Agency: Brand Authority & E-E-A-T in AI Search](https://hookagency.com/blog/brand-authority-ai-search/)
- [Digital Information World: Schema Markup Redefining Brand Visibility](https://www.digitalinformationworld.com/2025/12/how-schema-markup-is-redefining-brand.html)
