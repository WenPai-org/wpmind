# 文派集市 (wenpai.net) 搭建计划

> 文档版本: 1.0 | 创建日期: 2026-02-15 | 状态: 规划中
> 关联文档: [WENPAI-GLOBAL-STRATEGY.md](./WENPAI-GLOBAL-STRATEGY.md), [WENPAI-ECOSYSTEM-PLAN.md](./WENPAI-ECOSYSTEM-PLAN.md)

---

## 1. 平台定位

文派集市是一个**开源软件数字资产交易平台**，以 WordPress 产品为入口，逐步扩展到其他开源项目。

- 对标: Envato Market (ThemeForest/CodeCanyon) + 淘宝/京东式商户自营
- 商品范围: 主题、插件、模板、代码、开源软件产品、技术服务
- 模式: 商户入驻，平台提供基础设施，开箱即用
- 前期市场: 中国用户为主，后续扩展国际市场

---

## 2. 技术架构

### 2.1 WordPress Multisite（子目录模式）

```
wenpai.net/                    <- 主站: 商城 + 商户入驻 (MarketKing Pro)
wenpai.net/docs/               <- 子站: 开发者文档 (SDK, API, 集成指南)
wenpai.net/blog/               <- 子站: 博客/资讯/公告
```

- 子目录形式，非子域名，减少维护成本
- 主站安装多商户插件，商户通过 Vendor Dashboard 管理产品
- 子站是平台服务性站点，不开放给商户

### 2.2 需要子域名的场景

| 子域名 | 用途 | 说明 |
|--------|------|------|
| updates.wenpai.net | 更新 API 服务 | 已存在，WPBridge 代理 |
| cdn.wenpai.net | 下载 CDN | 后续按需配置 |
| languages.wenpai.net | 翻译服务 | 已存在 |

### 2.3 服务器环境

- 服务器: 45.117.8.70
- Web root: /www/wwwroot/wenpai.net/
- PHP: 8.3 (已就绪)
- MySQL: 5.7.44 (可用，后续考虑升级 8.0)
- Nginx: SSL 已配置，需添加 WordPress Multisite rewrite
- 宝塔面板管理

---

## 3. 核心插件清单

### 3.1 网络激活（所有站点共享）

| 插件 | 用途 | 来源 |
|------|------|------|
| WooCommerce | 核心电商 | 免费 |
| WooCommerce Subscriptions | 订阅/续费管理 | 付费 |
| MarketKing Pro | 多商户市场 | $299/年 |
| lmfwc (License Manager for WC) | 许可证生成/管理 | Pro $130/年 |
| wooalipay | 支付宝支付网关 | 自研 |
| woowechatpay | 微信支付网关 | 自研 |

### 3.2 主站激活

| 插件 | 用途 |
|------|------|
| 主题 (待定) | 商城前台 |
| SEO 插件 | 搜索引擎优化 |
| 安全插件 | 基础安全防护 |

---

## 4. 与现有平台对接方案

### 4.1 集市 -> WPBridge（许可证 + 更新）

```
用户购买 -> WC hook: order_status_completed
         -> lmfwc 生成许可证
         -> lmfwc hook -> 同步到 WPBridge License Gateway
         -> WPBridge 对外提供 /api/v1/licenses/verify
         -> 用户站点插件调用验证 API
```

### 4.2 飞码 -> 集市（自动发布）

```
开发者 push tag -> Forgejo Actions CI/CD
               -> 构建 ZIP + 安全扫描
               -> Webhook 通知集市更新版本
               -> 同步到 WPBridge 更新 API
               -> 用户站点检测到更新
```

### 4.3 叶子 -> 集市（交叉销售）

```
叶子 Bridge 客户端每日遥测
    -> WPBridge 返回推荐数据（集市产品）
    -> 叶子设置页展示推荐卡片
    -> 用户点击跳转到 wenpai.net 产品页
```

### 4.4 集市 <-> 文派开源（双版本分发）

```
商户上传产品时选择分发策略:
- 仅商业版 -> 只在集市销售
- Free + Pro -> Free 版同步到 wenpai.org，Pro 版在集市
- 仅免费 -> 只在 wenpai.org
```

---

## 5. WPMind 商业闭环（第一个跑通的流程）

```
1. 发现: 叶子推荐 / wenpai.org 免费版 / 搜索引擎
2. 产品页: wenpai.net/plugins/wpmind/ (功能+定价+演示+评价)
3. 购买: 选套餐 -> 购物车 -> 微信/支付宝支付
4. 交付: lmfwc 生成许可证 -> 邮件发送密钥+下载链接 -> 同步 WPBridge
5. 激活: 用户输入密钥 -> WPMind -> WPBridge /licenses/activate
6. 更新: feicode CI/CD -> 构建 -> 通知集市 -> WPBridge 更新 API -> 用户升级
7. 续费: WC Subscriptions 到期提醒 -> 自动/手动续费 -> 延长许可证
```

---

## 6. 搭建执行步骤

### Step 1: WordPress Multisite 基础环境
- wp-cli 安装 WordPress
- 配置 wp-config.php 启用 Multisite (子目录模式)
- nginx 添加 WordPress + Multisite rewrite 规则
- 创建数据库

### Step 2: 核心插件安装
- WooCommerce + Subscriptions + MarketKing Pro + lmfwc
- wooalipay + woowechatpay (自研支付网关)
- 主题安装和配置

### Step 3: 集市核心配置
- MarketKing 商户入驻流程
- 产品分类体系
- 佣金规则
- 支付网关配置
- 邮件模板
- 基础页面

### Step 4: WPBridge 对接
- lmfwc -> WPBridge 许可证同步
- WPBridge License Gateway API
- WPBridge 更新 API
- WPMind 客户端集成

### Step 5: WPMind 上架
- 创建产品页 (Free/Pro/Business/Enterprise)
- 配置 WC Subscriptions 订阅计划
- 配置 lmfwc 许可证模板
- 测试完整购买->激活->更新流程
- Free 版同步到 wenpai.org

---

## 7. Multisite 子站规划

| 路径 | 用途 | 优先级 | 说明 |
|------|------|--------|------|
| / (主站) | 商城 + 商户入驻 | P0 | MarketKing, WooCommerce |
| /docs/ | 开发者文档 | P1 | SDK, API 参考, 集成指南 |
| /blog/ | 博客/资讯 | P1 | 公告, 教程, 行业资讯 |
| 帮助中心 | 主站页面 | P0 | 初期用页面，不开子站 |
| 支持工单 | 主站插件或 Chatwoot | P1 | 对接现有 Chatwoot |

---

## 8. 关键决策记录

| 日期 | 决策 | 理由 |
|------|------|------|
| 2026-02-15 | Multisite 子目录模式 | 减少维护成本，统一域名 |
| 2026-02-15 | 主站装多商户插件，子站为服务性站点 | 商户不需要独立子站 |
| 2026-02-15 | 以 WordPress 产品为入口 | 先验证再扩展品类 |
| 2026-02-15 | 前期中国用户为主 | 聚焦，后续再国际化 |
| 2026-02-15 | 支付网关用自研 wooalipay + woowechatpay | 已有成熟方案 |
| 2026-02-15 | 平台定位为开源软件数字资产交易平台 | 不限于 WordPress |

---

*最后更新: 2026-02-15*
