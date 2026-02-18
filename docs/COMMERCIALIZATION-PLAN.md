# WPMind 商业化功能分层方案

> 文档版本: 1.0 | 创建日期: 2026-02-10 | 状态: 讨论中

---

## 1. 现状分析

### 插件概况

| 项目 | 值 |
|------|-----|
| 当前版本 | 0.11.3 |
| 模块总数 | 7 个（全部已实现） |
| 许可证代码 | 无，需从零构建 |
| 路线图定价 | Free ¥0 / Pro ¥99/月 / Enterprise 定制 |
| 待开发模块 | content-assistant, comment-intelligence, semantic-search, translation |

### 已实现模块清单

| 模块 | 文件数 | 核心功能 | 可禁用 |
|------|--------|----------|--------|
| **analytics** | ~10 | 用量趋势、Provider 对比、成本统计 | 是 |
| **api-gateway** | 36 | OpenAI 兼容 API、8 阶段管道、密钥管理 | 是 |
| **auto-meta** | ~7 | 自动摘要/标签/分类/FAQ/SEO 描述 | 是 |
| **cost-control** | ~8 | Token 追踪、预算限制、告警通知 | 否（核心依赖） |
| **exact-cache** | ~6 | 精确匹配缓存、命中率统计、LRU 淘汰 | 是 |
| **geo** | 14 | Schema.org、Markdown Feed、llms.txt、AI Sitemap、品牌实体 | 是 |
| **media-intelligence** | ~6 | Alt text 生成、NSFW 检测、批量处理 | 是 |

### 核心层（不可拆分）

| 子系统 | 说明 |
|--------|------|
| Providers/ | 11 个 AI 服务商（8 国产 + 3 国际） |
| Routing/ | 5 种智能路由策略 + 复合策略 |
| Failover/ | 熔断器 + 健康追踪 |
| API/ | PublicAPI Facade + 6 个 Service |
| SDK/ | WP AI Client SDK 适配 |
| Admin/ | 管理界面框架 |
| Core/ | 模块加载器 |

---

## 2. 功能分层方案

### Free 免费版 — ¥0

**定位**: 个人博客、小站长，体验 AI 能力

| 模块/功能 | 可用范围 | 限制 |
|-----------|----------|------|
| **AI Provider 接入** | 全部 11 个 Provider | 无限制 |
| **智能路由** | 仅成本优先策略 | 其他 4 种策略锁定 |
| **cost-control** | 完整功能 | 无限制（核心依赖） |
| **analytics** | 基础统计 | 仅 7 天数据，无导出 |
| **exact-cache** | 基础缓存 | 最多 500 条 |
| **geo 基础** | Markdown Feed + llms.txt + robots.txt | 无 Schema/Brand/Sitemap |
| **auto-meta** | 基础元数据 | 每日 30 篇 |
| **media-intelligence** | 基础图片 AI | 每日 20 张，无批量 |
| **API Gateway** | — | 不含 |

**免费版设计原则**:
- Provider 接入不限制（核心差异化，吸引用户）
- 所有模块可见但功能受限（让用户看到 Pro 价值）
- 额度限制而非功能锁死（降低升级心理门槛）

### Pro 专业版 — ¥99/月

**定位**: 企业站、内容站、SEO 从业者

| 模块/功能 | 说明 |
|-----------|------|
| **智能路由** | 全部 5 种策略 + 复合策略 |
| **analytics** | 90 天数据 + CSV/JSON 导出 |
| **exact-cache** | 无限缓存 + 完整 LRU 策略 |
| **geo 完整** | Schema 全套 + Brand Entity + AI Sitemap + 中文优化 + 实体关联 |
| **auto-meta** | 无限篇数 + FAQ Schema 生成 |
| **media-intelligence** | 无限张数 + 批量处理 + NSFW 检测 |
| **API Gateway** | 完整（8 阶段管道 + 速率限制 + 审计日志） |
| **content-assistant** (v4.0) | Gutenberg AI 面板（标题/摘要/大纲/改写） |
| 优先技术支持 | 工单 48h 响应 |

### Enterprise 企业版 — 定制价格

**定位**: 大型客户、媒体集团、SaaS 平台

| 功能 | 说明 |
|------|------|
| 私有部署 | 自定义 AI 端点，数据不出企业网络 |
| 多站点许可 | 不限站点数量 |
| 自定义模型 | 接入企业私有模型（如私有化 DeepSeek） |
| SLA 保障 | 99.9% 可用性承诺 |
| 白标支持 | 移除 WPMind 品牌标识 |
| 专属技术支持 | 1v1 技术对接 + 微信群 |
| API 调用量 | 无限制 |

---

## 3. 架构决策点

### 3.1 许可证验证方式

| 方案 | 优点 | 缺点 | 适用场景 |
|------|------|------|----------|
| **A) 远程验证** | 防盗版、可实时吊销、支持订阅 | 需服务器、离线不可用 | 订阅制 SaaS |
| **B) 本地密钥 (JWT)** | 离线可用、无服务器成本 | 易破解、无法实时吊销 | 一次性买断 |
| **C) 功能即服务** | 天然防盗版、按量计费 | 依赖网络、延迟增加 | AI 统一接入模式 |
| **D) 混合方案** | 兼顾安全和体验 | 实现复杂 | 推荐方案 |

**推荐: D) 混合方案**

```
启动验证流程:
  1. 插件激活时，向 wpcy.com/api/license/verify 发送许可证密钥
  2. 服务器返回签名的 JWT（含 plan/expiry/features）
  3. JWT 本地缓存 24h，期间无需网络
  4. 过期后重新验证，验证失败降级为 Free

功能即服务（补充）:
  - 文派心思 AI 统一接入本身就是服务端控制
  - Pro 用户获得更高的 API 配额和优先路由
  - 这部分天然防盗版
```

### 3.2 功能门控粒度

| 方案 | 优点 | 缺点 |
|------|------|------|
| **模块级** | 简单、易维护 | 粗糙，Free 用户体验差 |
| **功能级** | 精细控制 | 代码侵入性强 |
| **额度级** | 用户体验好 | 需要计数器基础设施 |
| **混合** | 最佳体验 | 实现最复杂 |

**推荐: 混合方案（模块级 + 额度级）**

```
门控层次:
  1. 模块级: API Gateway 整体锁定（Pro only）
  2. 功能级: GEO 模块内 Schema/Brand 功能锁定
  3. 额度级: auto-meta/media-intelligence 按日限额

实现方式:
  - 新增 LicenseManager 类，提供 can() / quota() / plan() 方法
  - 各模块通过 LicenseManager::can('feature_name') 检查权限
  - 额度通过 LicenseManager::quota('auto_meta_daily') 检查
```

### 3.3 分发渠道

| 方案 | 优点 | 缺点 |
|------|------|------|
| **WordPress.org (Free) + 官网 (Pro)** | 最大曝光、信任度高 | 审核严格、不能含商业代码 |
| **全部官网分发** | 完全控制、灵活定价 | 曝光少、需自建更新系统 |
| **WordPress.org + 远程解锁** | 兼顾曝光和商业 | 需要精心设计升级流程 |

**推荐: WordPress.org (Free) + 远程解锁 (Pro)**

```
分发策略:
  1. 免费版发布到 WordPress.org（最大化安装量）
  2. Pro 功能通过许可证密钥远程解锁（无需下载不同版本）
  3. 插件内置升级引导 UI（设置页 + 功能锁定提示）
  4. 自建更新服务器处理 Pro 版本更新
```

---

## 4. 技术实现规划

### 4.1 LicenseManager 架构

```php
namespace WPMind\License;

class LicenseManager {
    // 计划常量
    const PLAN_FREE       = 'free';
    const PLAN_PRO        = 'pro';
    const PLAN_ENTERPRISE = 'enterprise';

    // 检查功能权限
    public function can(string $feature): bool;

    // 检查剩余额度
    public function quota(string $resource): int;

    // 消耗额度
    public function consume(string $resource, int $amount = 1): bool;

    // 获取当前计划
    public function plan(): string;

    // 验证许可证
    public function verify(string $license_key): array;

    // 获取计划功能列表
    public function features(): array;
}
```

### 4.2 功能注册表

```php
// 功能 → 计划映射
$feature_map = [
    // 路由策略
    'routing.cost'          => 'free',
    'routing.latency'       => 'pro',
    'routing.availability'  => 'pro',
    'routing.load_balanced' => 'pro',
    'routing.composite'     => 'pro',

    // Analytics
    'analytics.basic'       => 'free',   // 7 天
    'analytics.extended'    => 'pro',    // 90 天
    'analytics.export'      => 'pro',

    // Cache
    'cache.basic'           => 'free',   // 500 条
    'cache.unlimited'       => 'pro',

    // GEO
    'geo.markdown_feed'     => 'free',
    'geo.llms_txt'          => 'free',
    'geo.robots_txt'        => 'free',
    'geo.schema'            => 'pro',
    'geo.brand_entity'      => 'pro',
    'geo.ai_sitemap'        => 'pro',
    'geo.chinese_optimizer' => 'pro',
    'geo.entity_linker'     => 'pro',

    // Auto-Meta
    'auto_meta.basic'       => 'free',   // 30/天
    'auto_meta.unlimited'   => 'pro',
    'auto_meta.faq_schema'  => 'pro',

    // Media Intelligence
    'media.basic'           => 'free',   // 20/天
    'media.unlimited'       => 'pro',
    'media.batch'           => 'pro',
    'media.nsfw'            => 'pro',

    // API Gateway
    'api_gateway'           => 'pro',

    // Enterprise
    'multisite'             => 'enterprise',
    'white_label'           => 'enterprise',
    'custom_model'          => 'enterprise',
    'sla'                   => 'enterprise',
];
```

### 4.3 额度配置

```php
$quota_config = [
    'free' => [
        'auto_meta_daily'  => 30,
        'media_daily'      => 20,
        'cache_max'        => 500,
        'analytics_days'   => 7,
    ],
    'pro' => [
        'auto_meta_daily'  => PHP_INT_MAX,
        'media_daily'      => PHP_INT_MAX,
        'cache_max'        => PHP_INT_MAX,
        'analytics_days'   => 90,
    ],
    'enterprise' => [
        'auto_meta_daily'  => PHP_INT_MAX,
        'media_daily'      => PHP_INT_MAX,
        'cache_max'        => PHP_INT_MAX,
        'analytics_days'   => 365,
    ],
];
```

### 4.4 实现优先级

| 阶段 | 任务 | 说明 |
|------|------|------|
| **P0** | LicenseManager 核心类 | can() / quota() / plan() / verify() |
| **P0** | 许可证验证 API | wpcy.com 服务端接口 |
| **P1** | 功能门控集成 | 各模块接入 LicenseManager |
| **P1** | 升级引导 UI | 设置页内 Pro 功能提示 |
| **P2** | 额度计数器 | auto-meta / media 日限额 |
| **P2** | 自建更新服务器 | Pro 版本更新推送 |
| **P3** | WordPress.org 提交 | 免费版审核上架 |

---

## 5. 竞品定价参考

| 插件 | 免费版 | Pro 版 | 模式 |
|------|--------|--------|------|
| Rank Math | 基础 SEO | $5.75/月起 | 功能分层 |
| Yoast SEO | 基础 SEO | $8.25/月 | 功能分层 |
| JEEP AI | 基础 AI | $9/月 | 额度 + 功能 |
| Jepi AI | 基础 AI | $12/月 | 额度制 |
| AI Engine | 基础 AI | $49/年 | 功能分层 |

**WPMind ¥99/月 (≈$13.5/月)** 定价处于中等偏上，考虑到包含 GEO + API Gateway + 11 个 Provider，性价比合理。

---

## 6. 风险与对策

| 风险 | 影响 | 对策 |
|------|------|------|
| GPL 许可证限制 | 代码必须开源 | 功能即服务 + 远程验证（服务端不受 GPL 约束） |
| 盗版破解 | 收入损失 | 核心价值在服务端（AI 统一接入） |
| WordPress.org 审核 | 上架延迟 | 提前准备，遵循审核指南 |
| 用户付费意愿低 | 转化率低 | 免费版足够好用，Pro 提供明显增值 |
| 竞品跟进 | 差异化缩小 | 持续迭代 GEO + 国产模型优势 |

---

## 7. 待讨论事项

- [ ] 许可证验证方案最终确认（混合方案 vs 纯服务端）
- [ ] ¥99/月定价是否需要调整（年付优惠？）
- [ ] Enterprise 版是否需要单独代码分支
- [ ] WordPress.org 提交时间节点
- [ ] 文派心思 AI 统一接入的服务端架构
- [ ] 付款渠道（微信/支付宝/Stripe）

---

## 8. Codex 评审意见 (2026-02-10)

> 评审模型: gpt-5.3-codex | 评审模式: read-only sandbox + web search

### 高风险项（需优先修复）

| # | 风险 | 说明 |
|---|------|------|
| 1 | **WP.org 合规边界** | "WP.org + 远程解锁 + 自建更新"存在审核风险，建议 WP.org 仅放 Free 核心，Pro 作为独立 GPL 插件在官网分发 |
| 2 | **¥99 单一价位转化阻力** | 在纯插件市场偏高，建议增加低门槛 Pro Lite（¥39~59） |
| 3 | **License 验证失败即降级** | 24h 缓存 + 失败即降级会误伤付费用户，建议软失效 + 7 天宽限期 + 指数退避重试 |

### 分层调整建议

- **API Gateway**: Free 不应完全锁定，建议 Free Lite（1 endpoint、低 QPS、无审计）
- **Multisite**: 不应仅限 Enterprise，建议 Pro 给 3-10 站，Enterprise 不限站点
- **Enterprise API 调用量**: "无限制"成本风险极高，改为承诺用量 + 超额阶梯计费 + fair use
- **PHP_INT_MAX**: 不利于后续计费扩展，改为显式 `null/unlimited` 语义
- **Provider 接入**: 保留"可接入全部"，但限制并发路由策略/自动故障切换到 Pro

### 定价调整建议

| 层级 | 原方案 | Codex 建议 |
|------|--------|-----------|
| Free | ¥0 | ¥0（不变） |
| Pro Lite | — | **¥39~59/月**（新增，降低首购门槛） |
| Pro/Business | ¥99/月 | ¥99/月（改名 Business） |
| Enterprise | 定制 | 定制（不变） |
| 年付 | 未定 | ¥990/年（送2月）或首发 ¥799/年限时 |

竞品实际定价参考（2026-02-10 核验）：
- Rank Math Pro: $6.59/mo（年付折算）
- Yoast Premium: $9.9/mo（年付折算）
- AI Engine Starter: $4.9/mo（年付折算）
- AIWU Pro: $9/mo
- WapiGPT Pro: €29.99/mo（SaaS 型）

### LicenseManager 架构补充

需增加的方法：
- `status()` — 许可证状态（active/expired/grace/suspended）
- `expiresAt()` — 过期时间
- `graceUntil()` — 宽限期截止
- `siteLimit()` — 站点数限制
- `reasonCode()` — 降级原因码

JWT 必须包含的 claims：
```
iss/aud/sub(site_hash)/plan/features/quota/iat/nbf/exp/jti/kid
```

`consume()` 必须原子化（并发扣减会穿透限额），建议 DB 原子更新或服务端配额为准。

混合方案风险清单：
- 许可证服务故障 → 大规模降级（需 SLA + 宽限期）
- 系统时间回拨 → 绕过过期（需时间漂移保护）
- 站点克隆 → 刷配额（需设备绑定 + 异常行为风控）

### GPL 合规建议

- GPL 不禁止收费，但不能附加限制用户再分发代码
- 卖的是：下载、更新、支持、服务，不是"禁止传播的代码许可"
- **推荐策略**: WP.org 仅放 Free 核心；Pro 作为独立 GPL 插件在官网分发；付费点放在更新服务、支持、云端能力、配额
- 需补齐：服务条款、隐私政策、DPA（企业版）、数据删除与导出机制

### 转化漏斗优化

建议 4 段漏斗事件（每段埋点）：
```
安装 → 首个成功生成 → 达到 70% 配额 → 触发升级
```

- "硬挡板"改"软挡板"：到限额后给一次性 burst（额外 10 次）
- 模块内上下文升级（非统一弹窗）：如点 geo.schema 时展示"预计可提升的收录字段"
- 流失回收：取消订阅时给"降级保留历史报表 + 次月优惠券"

### 方案遗漏项

- [ ] **站点授权模型**: 1站/3站/10站/无限站的价格与功能
- [ ] **支付与财务闭环**: 发票、退款、扣款失败追缴（dunning）
- [ ] **单位经济模型**: 每层级 token 成本上限、毛利红线、超量策略
- [ ] **SRE 与安全**: 许可证 API SLA、密钥轮换、审计日志保留周期、灾备
- [ ] **产品指标目标**: 90 天目标（激活率、Free→Paid、月流失率、ARPU）
- [ ] **竞品表修正**: JEEP AI / Jepi AI 无可信官方定价，替换为可验证竞品

### 参考来源

- [Rank Math 定价](https://rankmath.com/pricing/)
- [Yoast Premium 定价](https://yoast.com/wordpress/plugins/seo/pricing/)
- [AI Engine 定价 (Meow Apps)](https://meowapps.com/ai-engine/pricing/)
- [WordPress 详细插件指南](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress 与 GPL](https://wordpress.org/about/license/)
- [GNU GPL FAQ](https://www.gnu.org/licenses/gpl-faq.en.html)

---

*最后更新: 2026-02-10*
