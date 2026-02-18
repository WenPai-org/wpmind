# 文派生态基础设施规划

> 文档版本: 1.0 | 创建日期: 2026-02-10 | 状态: 规划中

---

## 1. 生态概览

### 四大平台

| 平台 | 域名 | 定位 | 状态 |
|------|------|------|------|
| **文派开源** | wenpai.org | 免费插件/主题目录 + WordPress.org 镜像 | 运营中 |
| **文派集市** | wenpai.net | 多商户商业市场（WooCommerce） | 新建（域名已备案） |
| **飞码托管** | feicode.com | Git 代码托管（Forgejo） | 运营中 |
| **统一更新 API** | 待定 | 免费+商业产品更新服务 | 规划中 |

### 架构全景

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress 用户 / 开发者                │
└─────────────────────────────────────────────────────────┘
        ↓ 免费下载              ↓ 购买/订阅           ↓ 代码贡献
┌──────────────┐    ┌──────────────────┐    ┌──────────────┐
│ WENPAI.ORG   │    │ WENPAI.NET       │    │ feicode.com  │
│ 文派开源      │    │ 文派集市          │    │ 飞码托管     │
│ 独立目录+镜像 │    │ WooCommerce 市场  │    │ Forgejo      │
└──────┬───────┘    └────────┬─────────┘    └──────┬───────┘
       │                     │                      │
       │    ┌────────────────┴──────────────┐       │
       │    │     许可证管理系统              │       │
       │    │  生成 / 验证 / 续期 / 吊销     │       │
       │    └────────────────┬──────────────┘       │
       │                     │                      │
       ▼                     ▼                      ▼
┌─────────────────────────────────────────────────────────┐
│              统一更新 API 服务                            │
│  ┌─────────────────┐    ┌─────────────────────────┐     │
│  │ 免费通道         │    │ 商业通道                 │     │
│  │ (无需密钥)       │    │ (许可证验证 → 下载链接)  │     │
│  └─────────────────┘    └─────────────────────────┘     │
└─────────────────────────────────────────────────────────┘
        ↓                          ↓
   WordPress 站点              WordPress 站点
   (免费插件自动更新)          (商业插件授权更新)
```

---

## 2. 各平台职责

### 2.1 WENPAI.ORG（文派开源）

**当前状态**: 运营中，独立目录 + WordPress.org 镜像

| 功能 | 说明 |
|------|------|
| **独立目录** | 中文 WordPress 插件/主题的独立提交和审核 |
| **镜像服务** | 同步 WordPress.org 官方插件/主题，国内加速 |
| **更新服务** | 兼容 WordPress 标准更新协议，免费产品无需密钥 |
| **下载统计** | 安装量、活跃安装、评分、兼容性 |
| **开发者页面** | 开发者主页、产品列表、贡献记录 |

**与其他平台的关系**:
- 免费产品从 feicode.com 自动发布到 WENPAI.ORG
- 文派集市的商业产品可在 WENPAI.ORG 展示免费版/精简版
- 更新 API 的免费通道由 WENPAI.ORG 提供

### 2.2 WENPAI.NET（文派集市）

**当前状态**: 新建站点，域名已备案

**定位**: 多商户 WordPress 产品市场（类 Envato/CodeCanyon 中文版）

| 功能 | 说明 |
|------|------|
| **产品销售** | 插件、主题、代码片段、服务 |
| **多商户** | 第三方开发者入驻、佣金分成 |
| **订阅管理** | 月付/年付自动续费 |
| **许可证生成** | 购买后自动生成密钥 |
| **更新分发** | 通过商业通道推送更新 |
| **评价系统** | 用户评分、评论、使用反馈 |

**技术栈**:

| 组件 | 推荐方案 | 说明 |
|------|----------|------|
| 核心电商 | WooCommerce | WordPress 生态标准 |
| 订阅管理 | WooCommerce Subscriptions | 自动续费、宽限期、降级 |
| 多供应商 | **MarketKing Pro** | 137+ 功能、现代 UX、Stripe Connect 分账 |
| 许可证管理 | 自建 WP 插件 | 现有插件不满足多商户+更新 API 需求 |
| 支付 | 微信/支付宝 + Stripe Connect | 国内+国际双通道，Stripe 自动分账 |

**对标产品**: Envato Market（ThemeForest / CodeCanyon）— 全球最大的 WordPress 主题/插件市场

| | Envato Market | 文派集市 (wenpai.net) |
|---|---|---|
| 技术栈 | 自建平台（Ruby on Rails） | WooCommerce + MarketKing Pro |
| 支付 | PayPal / Stripe / Skrill | 微信/支付宝 + Stripe Connect |
| 授权更新 | Envato API + Purchase Code | 自建许可证 + 统一更新 API |
| 目标市场 | 全球 | 中文 WordPress 生态 |
| 佣金 | 37.5%-55%（按独占性） | 15%-30%（更有竞争力） |
| 配套设施 | Envato Elements（订阅） | WENPAI.ORG（免费目录）+ feicode.com（Git） |
| 供应商入驻 | 严格审核 | 审核 + 代码质量检查 |

**MarketKing Pro 选型理由**:

| 对比项 | MarketKing Pro | Dokan Pro |
|--------|---------------|-----------|
| 功能数量 | 137+ 功能，25+ 模块 | ~100 功能 |
| Vendor Dashboard | 独立于主题，现代 UI | 依赖前端主题 |
| 分账支付 | Stripe Connect 内置 | 需额外插件 |
| 供应商订阅 | 内置会员包/订阅系统 | 需 Dokan Subscription |
| 同产品多供应商 | 内置（类 Amazon） | 需额外模块 |
| 拆单系统 | 专有 Split Cart | 基础拆单 |
| 佣金系统 | 按供应商/分类/标签/产品 | 按供应商/产品 |
| 定价（无限站点/年） | $299 | $999 |
| WordPress.org 免费版 | 有（核心功能可用） | 有（功能较少） |
| 评分 | 4.98 星 | 4.6 星 |
| 数字产品支持 | 完整 | 完整 |
| Subscriptions 兼容 | WooCommerce/YITH/Subscriptio | WooCommerce |

**MarketKing 核心优势**:
- **性价比高**: 无限站点 $299/年 vs Dokan $999/年
- **现代 Vendor Dashboard**: 独立于主题，任何站点都保持一致的现代 UI
- **Stripe Connect 内置**: 自动分账，供应商直接收款，无需手动打款
- **供应商会员系统**: 供应商可按套餐付费解锁功能（产品数量、分类、徽章等）
- **同产品多供应商**: 类似 Amazon 的 "Other Offers" 面板
- **模块化设计**: 25+ 可配置模块，按需启用

### 2.3 feicode.com（飞码托管）

**当前状态**: 运营中，基于 Forgejo

| 功能 | 说明 |
|------|------|
| **代码托管** | 所有项目的源代码管理 |
| **CI/CD** | Forgejo Actions 自动构建和打包 |
| **Webhook** | tag/release → 触发更新分发 |
| **双通道发布** | 免费项目 → WENPAI.ORG，商业项目 → WENPAI.NET |
| **代码审查** | PR/MR 流程、代码审查 |

**自动化发布流程**:
```
开发者 push tag v1.2.3
    ↓ Forgejo webhook
CI/CD Pipeline (Forgejo Actions)
    ↓
构建 zip 包 + 代码质量检查
    ↓
判断项目类型（通过 repo 配置/标签）
    ├── 免费项目 → 上传到 WENPAI.ORG 更新服务器
    ├── 商业项目 → 上传到 WENPAI.NET + 更新版本号
    └── 双版本项目 → 免费版 → ORG，Pro 版 → NET
```

---

## 3. 授权与认证体系

### 3.1 许可证类型

| 类型 | 适用场景 | 验证方式 |
|------|----------|----------|
| **免费许可** | WENPAI.ORG 免费产品 | 无需密钥，仅版本检查 |
| **商业许可** | WENPAI.NET 付费产品 | 许可证密钥 + 站点绑定 |
| **订阅许可** | 按月/年付费产品 | 许可证密钥 + 有效期 + 自动续期 |
| **开发者许可** | 多站点开发/测试 | 许可证密钥 + 站点数限制 |

### 3.2 许可证密钥格式

```
wenpai_{product}_{plan}_{random}
示例: wenpai_wpmind_pro_A1B2C3D4E5F6
```

### 3.3 许可证生命周期

```
购买/续费
    ↓
生成密钥 (status: active)
    ↓
用户激活 (绑定 site_url + site_hash)
    ↓
定期验证 (24h 缓存 + 7 天宽限期)
    ↓
├── 续费成功 → 续期 (status: active)
├── 续费失败 → 宽限期 (status: grace, 7 天)
├── 宽限期过 → 过期 (status: expired, 停止更新)
├── 违规使用 → 暂停 (status: suspended)
└── 退款/吊销 → 吊销 (status: revoked)
```

### 3.4 许可证状态机

| 状态 | 功能可用 | 可获取更新 | 说明 |
|------|----------|-----------|------|
| `active` | 全部 | 是 | 正常使用 |
| `grace` | 全部 | 是 | 续费宽限期（7 天） |
| `expired` | 已有功能 | 否 | 停止更新，保留功能（GPL 合规） |
| `suspended` | 已有功能 | 否 | 违规暂停，可申诉 |
| `revoked` | 已有功能 | 否 | 永久吊销 |

### 3.5 验证流程

```
插件启动
    ↓
检查本地缓存 JWT
    ├── 有效且未过期 → 使用缓存（不请求服务器）
    └── 无效或过期 → 请求验证 API
                        ↓
              POST wenpai.net/api/v1/license/verify
              {license_key, site_url, site_hash, product_slug}
                        ↓
              ├── 200 OK → 返回签名 JWT，本地缓存 24h
              ├── 402 Expired → 进入宽限期
              ├── 403 Invalid → 降级为 Free
              └── 5xx/超时 → 使用上次缓存（指数退避重试）
```

**JWT Payload 必须包含**:
```json
{
  "iss": "wenpai.net",
  "aud": "wpmind",
  "sub": "site_hash_xxx",
  "plan": "pro",
  "features": ["geo.schema", "geo.brand_entity", "api_gateway", "..."],
  "quota": {"auto_meta_daily": -1, "media_daily": -1},
  "site_limit": 3,
  "iat": 1707500000,
  "nbf": 1707500000,
  "exp": 1707586400,
  "jti": "unique_token_id",
  "kid": "key_rotation_id"
}
```

### 3.6 安全措施

| 风险 | 对策 |
|------|------|
| 跨站点复用密钥 | site_hash 绑定 + 站点数限制 |
| 系统时间回拨 | 服务端时间为准 + 时间漂移检测 |
| 站点克隆刷配额 | 设备指纹 + 异常行为风控 |
| 许可证服务故障 | 7 天宽限期 + 指数退避 + 本地缓存 |
| 密钥泄露 | 支持密钥轮换 + 一键吊销 |
| 中间人攻击 | HTTPS + JWT 签名验证 |

---

## 4. 更新服务架构

### 4.1 双通道设计

| 通道 | 来源 | 验证 | 适用产品 |
|------|------|------|----------|
| **免费通道** | WENPAI.ORG | 无需密钥 | 免费插件/主题 |
| **商业通道** | WENPAI.NET | 许可证密钥 | 付费插件/主题 |

### 4.2 更新 API 协议

兼容 WordPress 标准更新检查协议：

**检查更新**:
```
POST /api/v1/update-check
Content-Type: application/json

{
  "plugins": {
    "wpmind/wpmind.php": {
      "slug": "wpmind",
      "version": "0.11.3",
      "license_key": "wenpai_wpmind_pro_XXX",  // 商业产品必填
      "site_url": "https://example.com"          // 商业产品必填
    }
  }
}

Response 200:
{
  "wpmind/wpmind.php": {
    "slug": "wpmind",
    "new_version": "3.12.0",
    "package": "https://update.wenpai.net/dl/wpmind-3.12.0.zip?token=xxx",
    "url": "https://wenpai.net/plugins/wpmind/",
    "requires": "6.4",
    "tested": "6.9",
    "requires_php": "8.1",
    "icons": {...},
    "banners": {...}
  }
}
```

**获取插件信息**:
```
GET /api/v1/plugin-info?slug=wpmind

Response 200:
{
  "name": "WPMind",
  "slug": "wpmind",
  "version": "3.12.0",
  "author": "文派心思",
  "requires": "6.4",
  "tested": "6.9",
  "requires_php": "8.1",
  "sections": {
    "description": "...",
    "changelog": "...",
    "faq": "..."
  },
  "download_link": "...",
  "active_installs": 1000,
  "rating": 96
}
```

### 4.3 技术选型

| 组件 | 推荐方案 | 说明 |
|------|----------|------|
| 更新服务器 | WP Update Server 库 + 自定义扩展 | 成熟、兼容 WP 协议 |
| 客户端 SDK | Plugin Update Checker 库 | 同一作者，配套使用 |
| 下载分发 | 签名临时 URL + CDN | 防盗链、加速下载 |
| 版本存储 | 对象存储（S3 兼容） | zip 包存储 |

### 4.4 客户端集成（以 WPMind 为例）

```php
// 插件内一行代码接入更新服务
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/load.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://update.wenpai.net/api/v1/plugin-info?slug=wpmind',
    __FILE__,
    'wpmind'
);

// 商业产品：附加许可证密钥
$updateChecker->addQueryArgHeader('X-License-Key', get_option('wpmind_license_key'));
$updateChecker->addQueryArgHeader('X-Site-URL', home_url());
```

---

## 5. 文派集市（WENPAI.NET）详细规划

### 5.1 站点架构

```
wenpai.net (WordPress + WooCommerce)
├── 首页 — 精选产品、分类导航
├── /plugins/ — 插件市场
├── /themes/ — 主题市场
├── /services/ — 服务市场（定制开发、技术支持等）
├── /vendors/ — 商户列表
├── /vendor/{name}/ — 商户店铺（Dokan）
├── /my-account/ — 用户中心
│   ├── 订单管理
│   ├── 许可证管理
│   ├── 下载中心
│   └── 订阅管理
└── /vendor-dashboard/ — 供应商后台（MarketKing）
    ├── 产品管理
    ├── 订单管理
    ├── 许可证查看
    ├── 收入统计
    └── 提现管理
```

### 5.2 WooCommerce 扩展清单

| 扩展 | 用途 | 优先级 |
|------|------|--------|
| WooCommerce Subscriptions | 订阅/续费管理 | P0 |
| MarketKing Pro | 多供应商市场 | P1（Phase 2） |
| 自建许可证管理插件 | 密钥生成/验证/管理 | P0 |
| 自建更新服务插件 | 更新 API + 版本管理 | P0 |
| 微信/支付宝支付网关 | 国内支付 | P0 |
| Stripe Connect | 国际支付 + 自动分账 | P1 |

### 5.3 供应商入驻流程

```
开发者申请入驻
    ↓
审核（代码质量、安全检查）
    ↓
开通供应商账号（MarketKing vendor）
    ↓
上传产品（zip + 描述 + 截图 + 定价）
    ↓
产品审核
    ↓
上架销售
    ↓
用户购买 → 自动生成许可证 → 用户下载/激活
    ↓
Stripe Connect 自动分账 / 手动佣金结算
```

### 5.4 佣金模型

| 商户类型 | 平台佣金 | 说明 |
|----------|----------|------|
| 普通商户 | 30% | 标准佣金 |
| 认证商户 | 20% | 通过代码审核认证 |
| 战略合作 | 15% | 长期合作伙伴 |
| 自有产品 | 0% | 文派自有产品（如 WPMind） |

---

## 6. feicode.com 集成规划

### 6.1 Forgejo 配置

| 配置项 | 说明 |
|--------|------|
| Webhook | 推送到更新服务器的 webhook |
| Forgejo Actions | CI/CD 自动构建 |
| Release 管理 | 自动创建 Release + 附件 |
| 组织管理 | 文派开源组织、商户组织 |

### 6.2 CI/CD Pipeline 示例

```yaml
# .forgejo/workflows/release.yml
name: Release
on:
  push:
    tags: ['v*']

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Build zip
        run: |
          composer install --no-dev
          zip -r ${{ github.event.repository.name }}.zip . \
            -x ".git/*" ".forgejo/*" "tests/*" "node_modules/*"

      - name: Upload to update server
        run: |
          curl -X POST https://update.wenpai.net/api/v1/release \
            -H "Authorization: Bearer ${{ secrets.UPDATE_API_TOKEN }}" \
            -F "slug=${{ github.event.repository.name }}" \
            -F "version=${{ github.ref_name }}" \
            -F "package=@${{ github.event.repository.name }}.zip" \
            -F "channel=stable"
```

---

## 7. WPMind 接入方案

### 7.1 插件内架构

```
wpmind/
├── includes/
│   └── License/
│       ├── LicenseManager.php    — 许可证管理核心
│       ├── LicenseClient.php     — 与 wenpai.net API 通信
│       ├── UpdateChecker.php     — WordPress 更新集成
│       └── ActivationUI.php      — 后台许可证激活界面
```

### 7.2 功能门控

```
未激活（无密钥）= Free 功能
激活 Pro Lite 密钥 = Pro Lite 功能
激活 Pro/Business 密钥 = 全部功能
许可证过期 = 停止更新，保留已有功能（GPL 合规）
```

### 7.3 分发策略

| 版本 | 分发渠道 | 更新通道 |
|------|----------|----------|
| Free | WENPAI.ORG | 免费通道（无需密钥） |
| Pro Lite | WENPAI.NET | 商业通道（许可证验证） |
| Business | WENPAI.NET | 商业通道（许可证验证） |
| Enterprise | 直接交付 | 商业通道 + 专属支持 |

---

## 8. 实施路径

### Phase 1: MVP — WPMind 先行

**目标**: 验证授权+更新+销售全流程

| 任务 | 说明 |
|------|------|
| 搭建 wenpai.net | WordPress + WooCommerce + Subscriptions |
| 自建许可证管理插件 | 密钥生成/验证/激活 API |
| 自建更新服务插件 | 基于 WP Update Server |
| WPMind 接入 | LicenseManager + UpdateChecker |
| 支付接入 | 微信/支付宝 |
| **单商户模式** | 先不开放多商户 |

### Phase 2: 多商户 + 自动化

**目标**: 开放第三方开发者入驻

| 任务 | 说明 |
|------|------|
| 接入 MarketKing Pro | 多供应商市场功能 |
| 供应商许可证管理 | 平台中心化，供应商通过 scoped token 查看 |
| feicode.com CI/CD | Forgejo Actions 自动构建发布 |
| WENPAI.ORG 更新 API | 免费产品更新服务 |
| 开发者 SDK | 一行代码接入更新+授权 |

### Phase 3: 生态完善

**目标**: 完整的中文 WordPress 生态

| 任务 | 说明 |
|------|------|
| 插件/主题自动审核 | 代码扫描、安全检查、兼容性测试 |
| 数据分析平台 | 下载量、激活量、留存率、ARPU |
| 开发者文档 | API 文档、集成指南、最佳实践 |
| Stripe 国际支付 | 面向海外开发者和用户 |
| 发票系统 | 企业客户需求 |
| 退款/Dunning | 扣款失败追缴、退款流程 |

---

## 9. 项目实况（2026-02-10 确认）

### 团队与资源

| 项目 | 现状 |
|------|------|
| **团队规模** | 1 人 + AI 助手（Claude + Codex + Gemini） |
| **WENPAI.ORG** | WordPress 站点，运营中，独立目录 + WordPress.org 镜像 |
| **feicode.com** | Forgejo，仅代码托管，CI/CD (Forgejo Actions) 尚未配置 |
| **WENPAI.NET** | 域名已备案，站点待搭建 |
| **支付经验** | 有微信/支付宝支付网关集成经验 |

### SSO 方案

| 项目 | 决策 |
|------|------|
| **IdP 选择** | Casdoor 为主 |
| **协议** | OAuth2 / OIDC |
| **覆盖范围** | WENPAI.ORG (WordPress) + WENPAI.NET (WordPress) + feicode.com (Forgejo) + Discourse + 其他开源平台 |

### 自有产品线

| 产品 | 类型 | 说明 |
|------|------|------|
| **WPMind** | WordPress 插件 | AI 自定义端点扩展（当前项目） |
| **WPCY (文派叶子)** | WordPress 插件 | 中国本地化插件 |
| **主题产品** | WordPress 主题 | 自有品牌主题 |
| **其他产品** | 插件/服务/源码 | 开源软件产品和服务 |

### 文派集市产品范围

不仅限于 WordPress 插件/主题，还包括：
- 开源软件产品和服务
- 源代码/代码片段
- 技术服务（定制开发、技术支持等）
- 第三方供应商自行开发的软件产品

### 对 Phase 1 MVP 的影响

> **关键约束**: 1 人 + AI 团队，必须极度聚焦。

Phase 1 应进一步收敛为：
1. **wenpai.net 基础搭建** — WordPress + WooCommerce + Subscriptions（单商户）
2. **Casdoor SSO 接入** — 至少 ORG + NET 两站打通
3. **许可证 + 更新 MVP** — 仅服务 WPMind 一个产品
4. **单支付通道** — 微信/支付宝（已有经验，最快上线）
5. **MarketKing Pro 延后** — Phase 2 再接入多供应商

---

## 10. 关键决策记录

| 日期 | 决策 | 理由 |
|------|------|------|
| 2026-02-10 | WENPAI.ORG 和 WENPAI.NET 独立站点 | 免费/商业定位清晰，不混淆 |
| 2026-02-10 | 许可证管理自建而非用现有插件 | 多商户+更新 API 集成需求超出现有插件能力 |
| 2026-02-10 | 多供应商选择 MarketKing Pro | 137+ 功能、现代 UX、Stripe Connect 内置分账、性价比高（$299 vs Dokan $999） |
| 2026-02-10 | 对标 Envato Market (ThemeForest/CodeCanyon) | 中文 WordPress 生态缺少集中化数字产品市场 |
| 2026-02-10 | 更新服务初期与集市同站 | 降低初期复杂度，后期可拆分 |
| 2026-02-10 | SSO 选择 Casdoor | 已部署，支持 OAuth2/OIDC，覆盖 WordPress + Discourse + Forgejo |
| 2026-02-10 | 集市产品范围不限于 WordPress | 包括开源软件、源码、服务、第三方供应商产品 |
| 2026-02-10 | Phase 1 极度聚焦 | 1 人 + AI 团队，MVP 仅服务 WPMind 单产品 |
| 2026-02-10 | WPMind 作为第一个接入产品 | 验证全流程后再开放多商户 |

---

## 10. 待讨论事项

- [ ] WENPAI.NET 服务器部署方案（与 WENPAI.ORG 同服务器？独立？）
- [ ] 许可证管理插件是否开源（可作为生态工具吸引开发者）
- [ ] 开发者 SDK 的分发方式（Composer 包？WordPress 插件？）
- [ ] 佣金比例最终确认
- [ ] 商户入驻审核标准
- [ ] 更新 API 的 SLA 和灾备方案
- [ ] 数据合规（个人信息保护法、GDPR）
- [ ] WPMind Free 版是否同时发布到 WordPress.org
- [ ] 文派集市的 SEO 和推广策略

---

## 11. Codex 评审意见 (2026-02-10)

> 评审模型: gpt-5.3-codex | 评审模式: read-only sandbox + web search

### 总体评价

方向正确，"先单商户 MVP，再扩多商户"的节奏务实。但当前最大问题是**边界不够硬、授权安全链路不够闭环、CI/CD 缺发布安全门禁**。如果按现稿直接落地，后续会在"吊销不及时、跨平台数据对不上、商户纠纷"上付出高成本。

### P0 关键发现

| # | 问题 | 说明 |
|---|------|------|
| 1 | **职责边界重叠且故障域耦合** | 统一更新 API、WENPAI.ORG、WENPAI.NET 都在承担更新职责，"更新服务初期与集市同站"会把交易流量与更新流量绑定，存在级联故障风险 |
| 2 | **许可证吊销"即时生效"缺失** | 本地 JWT 缓存 24h + 宽限 7 天，被盗/被吊销许可证存在较长可用窗口 |
| 3 | **更新包供应链安全未闭环** | 只有临时下载链接，没有包签名/校验和/来源证明字段和验证流程 |
| 4 | **多商户许可证域模型未定义** | Phase 2 提到"每商户独立许可证管理"，但未定义租户隔离、跨商户订单拆分、退款回滚一致性 |

### P1 关键发现

| # | 问题 | 说明 |
|---|------|------|
| 5 | **JWT 密钥轮换协议不完整** | 有 `kid` 但没定义 JWKS 分发、轮换周期、旧 key 失效策略 |
| 6 | **API 设计存在泄露面** | `license_key` 在请求体/头中，日志脱敏不到位易泄露；`plugin-info` 含 `download_link` 需区分公开元数据与受保护下载 |
| 7 | **CI/CD 流程过于简化** | 没有测试矩阵、安全扫描、签名发布、灰度/回滚、人工审批门 |
| 8 | **Phase 顺序有依赖倒置** | 免费更新 API 放在 Phase 2，容易导致 Free/Pro 发布策略分裂 |

### 1) 架构合理性

**结论**: 分层思路正确，但职责边界要"单一归口"。

建议：
- 统一外部更新入口为 `update.wenpai.net`（免费/商业都走它），org/net 只做目录与交易
- 新增"产品主数据服务"（Product Registry）：统一 `product_id/slug/vendor_id/channel`，避免三套映射
- 明确事件驱动主线：`order.paid`、`subscription.renewed`、`refund.created`、`release.published`、`license.revoked`
- 身份体系补位：用户与开发者 SSO（至少统一 account_id），否则后续统计和风控会断裂

### 2) 许可证体系

**结论**: 基础框架可用，但缺"状态精细化 + 即时失效机制"。

建议：
- 状态机补充：`pending_activation`、`cancel_at_period_end`、`chargeback_hold`、`fraud_review`，写清触发事件与可逆性
- JWT 采用非对称签名（RS256/EdDSA）+ JWKS；定义 key 轮换（如 90 天）与双 key 过渡窗口
- 缩短离线信任窗口：access token 1–4h，离线宽限 24–48h；`revoked/suspended` 应支持分钟级生效（jti 黑名单或短轮询）
- 站点绑定增加：规范化域名、安装指纹、激活次数阈值和异常检测

### 3) 更新服务

**结论**: 双通道策略合理；WP Update Server + PUC 适合 MVP，不是终局。

建议：
- MVP 继续用 WP Update Server + PUC，但补充返回字段：`checksum_sha256`、`signature`、`request_id`、`retry_after`
- 下载链路改成"元数据接口 + 一次性短时下载 token（5 分钟）+ 可撤销"
- 将"版本发布"和"更新可见"拆开：支持 staged rollout（5%/20%/100%）与紧急回滚
- 示例代码 `addQueryArgHeader` 需确认库 API 是否支持，建议提供官方 SDK 封装层

### 4) 文派集市技术栈

**结论**: 选型匹配 WordPress 生态，商业可行；核心难点在多供应商授权结算一致性。

> 注：原评审针对 Dokan Pro，现已改为 MarketKing Pro。MarketKing 内置 Stripe Connect 分账，
> 降低了结算复杂度，但许可证集成仍需自建。

建议：
- 许可证服务保持"平台中心化"，商户通过 scoped token 调用，避免"每商户一套逻辑"
- 数据模型强制落地：`license_id` 绑定 `order_item_id + subscription_id + vendor_id + product_id`，所有变更幂等
- 明确退款/拒付规则：立即停更还是宽限、佣金何时冻结、争议期如何回滚许可证
- 商户隔离：后台权限、API 权限、报表权限都按 vendor scope 做行级隔离

### 5) CI/CD 集成

**结论**: 当前仅"能发包"，离"可审计、可回滚、可追责"还差一层。

建议发布流水线至少 8 步：
```
lint → static analysis → unit/integration → vulnerability scan
→ build → sign → upload staging → promote prod
```

- 建立测试矩阵：PHP 8.1/8.2/8.3 × WP 6.4/6.5/6.6/6.7+
- 产物不可变：每个 release 保存 manifest（版本、commit、checksum、签名、构建时间）
- 加入发布闸门：商业产品人工审批后 promote，支持一键回滚

### 6) 实施路径

**结论**: 三期结构合理，但 Phase 1 仍偏大，需再收敛。

建议：
- Phase 1 MVP 只做：单产品（WPMind）+ 单商户 + 单支付通道 + 授权/更新闭环 + 基础监控
- 将"SLA、备份恢复、告警、审计日志"前置到 Phase 1（不是"待讨论"）
- 把"免费通道最小可用能力"提前到 Phase 1，避免后续双轨迁移
- 为每个阶段定义退出指标：激活成功率、更新成功率、吊销生效时延、支付成功率

### 7) 重大风险与遗漏

| 风险类型 | 说明 |
|----------|------|
| **供应链风险** | 缺包签名与来源证明 |
| **合规风险** | PIPL/GDPR 仅在待办，需明确数据分级、留存周期、删除流程 |
| **运营风险** | 更新与商城同站导致峰值互相拖垮 |
| **商业风险** | 退款/拒付与许可证状态联动策略未定，易引发商户争议 |
| **生态风险** | 未定义开发者支持体系（文档版本化、SDK 兼容策略、弃用策略） |

---

## 12. Codex 最终评审 (2026-02-10 第二轮)

> 评审模型: gpt-5.3-codex | 基于更新后的完整文档（含项目实况、Casdoor SSO、MarketKing Pro、1人+AI 约束）

### 总体评价

文档已接近"可执行蓝图"。主干完整，但还缺 6 个上线前必须落地的基础层。

### 1) 整体完整性 — 6 个缺口

| # | 缺口 | 说明 |
|---|------|------|
| 1 | **SLO/SLA 未量化** | 仅在待讨论中提到，未进入执行基线 |
| 2 | **统一数据模型未固化** | account_id/product_id/vendor_id/license_id/order_item_id 的主键与约束 |
| 3 | **退款/拒付/佣金回滚规则** | 缺正式状态流，多商户会出纠纷 |
| 4 | **发布与运维基线** | 监控、告警、备份、演练、回滚 Runbook |
| 5 | **安全基线未闭环** | 包签名、密钥轮换、审计日志、脱敏规范 |
| 6 | **合规与法律文档集** | 商户协议、隐私政策、数据留存与删除流程 |

建议新增"MVP 上线门槛（Go-Live Checklist）"章节，把以上 6 项变成可验收条目。

### 2) Phase 1 MVP — 建议再收敛一刀

**当前偏大的点**: 同时要做 Woo、订阅、支付、许可证、更新、双站 SSO。

**建议 Phase 1 只保留 4 件事**:
1. WPMind 单产品、单商户、**单套餐**（先不做多层套餐）
2. 单支付通道（微信/支付宝，已有经验）
3. 许可证最小闭环（激活/校验/吊销）
4. 更新最小闭环（可检查、可下载、可回滚）

**建议后移到 Phase 1.5/2**:
- ORG/NET 双站 SSO 全量打通
- Subscriptions 自动续费复杂逻辑
- 多商户与分账（MarketKing）

**判断标准**: 如果不能快速端到端跑通"购买→激活→更新"，范围就还太大。

### 3) Casdoor SSO 技术风险

**结论**: 可行，但风险在账号映射、故障域、权限同步。

| 风险 | 缓解措施 |
|------|----------|
| sub/邮箱映射不稳导致重复/错绑账号 | 强制以不可变 `external_sub` 作为主映射键 |
| Casdoor 单点故障导致全平台登录受阻 | 保留每站点"本地应急管理员"与 break-glass 登录 |
| 各系统角色模型不同（WP/Forgejo/Discourse） | 固化 claims 合同（sub,email,groups,roles）+ 回归测试 |
| 单点登出（SLO）体验不一致 | 先做 NET 单站试点，稳定后再扩展 |

### 4) MarketKing Pro 评估

**结论**: 选型合理，尤其考虑 1 人团队成本；但要规避生态锁定风险。

**优势**: 成本低上手快、Vendor UX 好、Stripe Connect 内置
**劣势**: 生态与第三方扩展不如 Dokan、纯数字产品场景有功能冗余、许可证/更新仍需自建

建议 Phase 2 前做 2 个 PoC：
- 数字下载 + 退款 + 许可证回滚
- 商户结算 + 对账 + 争议处理

### 5) MVP 许可证系统 — API Key 优先，JWT 后置

**结论**: MVP 建议 API Key + 服务端实时校验，不做 JWT。

**原因**:
- 单产品单商户，JWT（JWKS、轮换、黑名单）显著拉高复杂度
- API Key + 服务端校验更容易做"吊销即时生效"

**MVP 最小 API**:
```
POST /license/activate   — 激活许可证，绑定站点
POST /license/check      — 校验许可证状态和功能权限
POST /update/token       — 签发 5 分钟一次性下载 token
```

- 客户端缓存 6-12h
- 吊销后 5-15 分钟内失效
- API 响应结构按 "entitlements" 设计，后续可无痛升级到 JWT

### 6) 最关键的下一步

> **如果只能做一件事：WPMind 单产品"购买→激活→更新→吊销"端到端闭环。**

验收标准（必须同时满足）：
- [ ] 购买后 1 分钟内自动出 license
- [ ] 插件端可激活并保存授权状态
- [ ] 有新版本时可被检测并成功更新
- [ ] 吊销后在设定窗口内失效
- [ ] 全链路有审计日志可追踪

---

*最后更新: 2026-02-10*
