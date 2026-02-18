# 文派集市 (wenpai.net) 实施指南

> 文档版本: 2.0 | 创建日期: 2026-02-15 | 状态: 实施中
> 替代文档: WENPAI-MARKETPLACE-PLAN.md (已合并至此)

---

## 概览

本文档是文派集市从零到上线的完整操作手册。按 Phase 顺序执行，每个 Phase 内的 Step 按序完成。

**服务器环境：**
- 服务器: 45.117.8.70 (SSH root)
- Web root: `/www/wwwroot/wenpai.net/`
- PHP 8.3 + Nginx + SSL 已就绪
- MySQL 5.7.44（注意：服务曾因 OOM 停止，建议配置自动重启）
- WP-CLI 2.12.0 已安装
- 宝塔面板: `https://45.117.8.70:35103/7ab5eb1d`

**PHP 扩展（已确认可用）：** curl, gd, imagick, intl, mysqli, pdo_mysql, redis, SimpleXML, soap, xml, zip, OPcache

---

## Phase 1: 基础环境搭建

### Step 1.1: 修复 MySQL OOM 问题

MySQL 曾被 OOM Killer 终止。建议先优化内存配置：

```bash
# 编辑 MySQL 配置
vi /etc/my.cnf

# 在 [mysqld] 段添加/修改（根据服务器总内存调整）
innodb_buffer_pool_size = 2G        # 服务器内存的 25-50%
innodb_log_file_size = 256M
max_connections = 200
table_open_cache = 400
tmp_table_size = 64M
max_heap_table_size = 64M

# 配置 systemd 自动重启
mkdir -p /etc/systemd/system/mysqld.service.d
cat > /etc/systemd/system/mysqld.service.d/restart.conf << 'EOF'
[Service]
Restart=on-failure
RestartSec=5s
EOF

systemctl daemon-reload
/etc/init.d/mysqld restart
```

### Step 1.2: 创建数据库

通过宝塔面板创建，或命令行：

```bash
# 先确认 MySQL root 密码（宝塔面板 -> 数据库 -> root 密码）
mysql -uroot -p'你的root密码' << 'SQL'
CREATE DATABASE wenpai_net DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wenpai_net'@'localhost' IDENTIFIED BY '替换为强密码';
GRANT ALL PRIVILEGES ON wenpai_net.* TO 'wenpai_net'@'localhost';
FLUSH PRIVILEGES;
SQL
```

> 记录数据库凭据，后续 wp-config.php 需要用到。

### Step 1.3: 安装 WordPress

```bash
cd /www/wwwroot/wenpai.net

# 清理默认文件
rm -f 404.html index.html .htaccess

# 下载 WordPress（使用中文版）
wp core download --locale=zh_CN --allow-root

# 生成配置文件
wp config create \
  --dbname=wenpai_net \
  --dbuser=wenpai_net \
  --dbpass='你的数据库密码' \
  --dbhost=localhost \
  --dbcharset=utf8mb4 \
  --dbcollate=utf8mb4_unicode_ci \
  --locale=zh_CN \
  --allow-root

# 安装 WordPress
wp core install \
  --url="https://wenpai.net" \
  --title="文派集市" \
  --admin_user=admin \
  --admin_password='替换为管理员强密码' \
  --admin_email=admin@wenpai.net \
  --allow-root

# 修复文件权限
chown -R www:www /www/wwwroot/wenpai.net
find /www/wwwroot/wenpai.net -type d -exec chmod 755 {} \;
find /www/wwwroot/wenpai.net -type f -exec chmod 644 {} \;
```

### Step 1.4: 配置 Nginx

编辑 `/www/server/panel/vhost/nginx/wenpai.net.conf`，在 `#SSL-END` 之后添加 WordPress location 规则：

```nginx
    # WordPress 基础规则（后续启用 Multisite 后会替换）
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include enable-php-83.conf;
    }

    # 禁止访问敏感文件
    location ~ /\.ht {
        deny all;
    }
    location ~ /wp-config.php {
        deny all;
    }
    location = /xmlrpc.php {
        deny all;
    }
```

同时在 rewrite 文件中添加基础规则：

```bash
# 编辑 /www/server/panel/vhost/rewrite/wenpai.net.conf
# 暂时留空，Step 1.6 启用 Multisite 后再写入
```

```bash
# 重载 Nginx
nginx -t && nginx -s reload
```

### Step 1.5: 验证 WordPress 安装

```bash
# 浏览器访问
https://wenpai.net
https://wenpai.net/wp-admin/

# 命令行验证
wp option get siteurl --allow-root --path=/www/wwwroot/wenpai.net
wp option get home --allow-root --path=/www/wwwroot/wenpai.net
```

### Step 1.6: 启用 Multisite

```bash
cd /www/wwwroot/wenpai.net

# 第一步：在 wp-config.php 中添加（在 "That's all" 注释之前）
wp config set WP_ALLOW_MULTISITE true --raw --allow-root
```

然后登录后台 `工具 -> 网络设置`，选择 **子目录** 模式，点击安装。

WordPress 会给出需要添加的代码，通常是：

```bash
# wp-config.php 中添加（在 WP_ALLOW_MULTISITE 之后）
wp config set MULTISITE true --raw --allow-root
wp config set SUBDOMAIN_INSTALL false --raw --allow-root
wp config set DOMAIN_CURRENT_SITE "'wenpai.net'" --raw --allow-root
wp config set PATH_CURRENT_SITE "'/'" --raw --allow-root
wp config set SITE_ID_CURRENT_SITE 1 --raw --allow-root
wp config set BLOG_ID_CURRENT_SITE 1 --raw --allow-root
```

### Step 1.7: 配置 Nginx Multisite Rewrite

启用 Multisite 后，更新 rewrite 规则。编辑 `/www/server/panel/vhost/rewrite/wenpai.net.conf`：

```nginx
# WordPress Multisite 子目录模式 (WP 3.5+)
if (!-e $request_filename) {
    rewrite /wp-admin$ $scheme://$host$request_uri/ permanent;
    rewrite ^(/[^/]+)?(/wp-.*) $2 last;
    rewrite ^(/[^/]+)?(/.*\.php) $2 last;
}
```

同时确认主 nginx 配置中的 location 规则：

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

```bash
nginx -t && nginx -s reload
```

### Step 1.8: 创建子站点

```bash
cd /www/wwwroot/wenpai.net

# 创建文档子站
wp site create --slug=docs --title="开发者文档" --allow-root

# 创建博客子站
wp site create --slug=blog --title="文派博客" --allow-root

# 验证
wp site list --allow-root
```

**子站规划：**

| 路径 | 用途 | 优先级 |
|------|------|--------|
| `/` (主站) | 商城 + 商户入驻 | P0 |
| `/docs/` | 开发者文档 (SDK, API) | P1 |
| `/blog/` | 博客/资讯/公告 | P1 |

---

## Phase 2: 核心插件安装与配置

### Step 2.1: 安装基础插件

```bash
cd /www/wwwroot/wenpai.net

# WooCommerce（网络激活）
wp plugin install woocommerce --activate-network --allow-root

# License Manager for WooCommerce（免费版先装，后续升级 Pro）
wp plugin install license-manager-for-woocommerce --activate-network --allow-root

# 常用工具插件
wp plugin install redis-cache --activate-network --allow-root
wp plugin install wp-mail-smtp --activate-network --allow-root
```

### Step 2.2: 手动安装的插件

以下插件需要手动上传：

| 插件 | 获取方式 | 安装位置 |
|------|----------|----------|
| MarketKing Pro | 购买后下载 ZIP | 主站激活 |
| WooCommerce Subscriptions | 购买后下载 ZIP | 网络激活 |
| lmfwc Pro | 购买后升级 | 网络激活 |
| wooalipay | 自研，从 Forgejo 拉取 | 主站激活 |
| woowechatpay | 自研，从 Forgejo 拉取 | 主站激活 |

```bash
# 手动安装示例（以 MarketKing 为例）
# 1. 将 ZIP 上传到服务器
scp marketking-pro.zip root@45.117.8.70:/tmp/

# 2. 安装
wp plugin install /tmp/marketking-pro.zip --allow-root
wp plugin activate marketking-multivendor-marketplace-for-woocommerce --allow-root

# 自研支付插件（从 Forgejo 克隆）
cd /www/wwwroot/wenpai.net/wp-content/plugins/
git clone https://git.wenpai.net/feibisi/wooalipay.git
git clone https://git.wenpai.net/feibisi/woowechatpay.git
chown -R www:www wooalipay woowechatpay

wp plugin activate wooalipay --allow-root
wp plugin activate woowechatpay --allow-root
```

### Step 2.3: WooCommerce 基础配置

登录后台 `WooCommerce -> 设置`：

| 配置项 | 值 | 说明 |
|--------|-----|------|
| 商店地址 | 中国 | 基础设置 |
| 货币 | 人民币 (¥) | 主要货币 |
| 产品类型 | 启用"虚拟"和"可下载" | 数字商品 |
| 税率 | 根据实际配置 | 中国增值税 |
| 邮件 | 配置 SMTP | 订单通知 |

```bash
# 命令行快速配置
wp option update woocommerce_currency 'CNY' --allow-root
wp option update woocommerce_currency_pos 'left' --allow-root
wp option update woocommerce_default_country 'CN' --allow-root
```

### Step 2.4: 支付网关配置

登录后台 `WooCommerce -> 设置 -> 支付`：

1. **支付宝 (wooalipay)**：填入 App ID、私钥、支付宝公钥
2. **微信支付 (woowechatpay)**：填入商户号、API 密钥、AppID
3. 禁用不需要的支付方式（PayPal、银行转账等）

### Step 2.5: 主题选择与安装

推荐方案（按优先级）：

| 方案 | 主题 | 理由 | 成本 |
|------|------|------|------|
| A | Flavor (flavor theme) | 专为 MarketKing 设计的多商户主题 | $59 |
| B | flavor + Flavor 子主题 | 定制化更强 | $59 |
| C | flavor + flavor 子主题 | 定制化更强 | $59 |
| D | flavor + flavor 子主题 | 定制化更强 | $59 |

> MarketKing 官方推荐 flavor 主题，兼容性最好。也可以用 flavor 或其他 WooCommerce 兼容主题。

---

## Phase 3: 集市核心配置

### Step 3.1: MarketKing 商户入驻配置

登录后台 `MarketKing -> 设置`：

**注册设置：**
- 启用商户注册
- 注册方式：需要管理员审核（前期）
- 商户注册表单：公司名称、联系方式、经营范围

**佣金设置：**
- 默认佣金比例：建议 15-20%（平台抽成）
- 支持按分类设置不同佣金
- 支持按商户等级设置佣金

**商户面板：**
- 启用 Vendor Dashboard
- 允许商户上传产品
- 允许商户管理订单
- 允许商户查看销售报表

### Step 3.2: 产品分类体系

```bash
cd /www/wwwroot/wenpai.net

# 创建顶级分类
wp term create product_cat "WordPress 插件" --allow-root
wp term create product_cat "WordPress 主题" --allow-root
wp term create product_cat "代码与脚本" --allow-root
wp term create product_cat "模板" --allow-root
wp term create product_cat "技术服务" --allow-root

# 创建 WordPress 插件子分类
PARENT_ID=$(wp term list product_cat --name="WordPress 插件" --field=term_id --allow-root)
wp term create product_cat "电商增强" --parent=$PARENT_ID --allow-root
wp term create product_cat "SEO 工具" --parent=$PARENT_ID --allow-root
wp term create product_cat "安全防护" --parent=$PARENT_ID --allow-root
wp term create product_cat "性能优化" --parent=$PARENT_ID --allow-root
wp term create product_cat "表单与交互" --parent=$PARENT_ID --allow-root
wp term create product_cat "多语言与翻译" --parent=$PARENT_ID --allow-root
wp term create product_cat "管理工具" --parent=$PARENT_ID --allow-root

# 创建 WordPress 主题子分类
PARENT_ID=$(wp term list product_cat --name="WordPress 主题" --field=term_id --allow-root)
wp term create product_cat "企业官网" --parent=$PARENT_ID --allow-root
wp term create product_cat "电商商城" --parent=$PARENT_ID --allow-root
wp term create product_cat "博客杂志" --parent=$PARENT_ID --allow-root
wp term create product_cat "作品集" --parent=$PARENT_ID --allow-root
```

### Step 3.3: 基础页面创建

```bash
cd /www/wwwroot/wenpai.net

# 商城必要页面（WooCommerce 安装时会自动创建部分）
wp post create --post_type=page --post_title="商户入驻" --post_status=publish --allow-root
wp post create --post_type=page --post_title="关于我们" --post_status=publish --allow-root
wp post create --post_type=page --post_title="帮助中心" --post_status=publish --allow-root
wp post create --post_type=page --post_title="服务条款" --post_status=publish --allow-root
wp post create --post_type=page --post_title="隐私政策" --post_status=publish --allow-root
wp post create --post_type=page --post_title="佣金说明" --post_status=publish --allow-root
```

### Step 3.4: 邮件模板配置

`WooCommerce -> 设置 -> 邮件` 中需要配置的模板：

| 邮件 | 触发时机 | 收件人 |
|------|----------|--------|
| 新订单 | 用户下单 | 管理员 + 商户 |
| 订单完成 | 支付成功 | 用户 |
| 许可证发放 | 支付成功 | 用户 |
| 商户注册申请 | 商户提交注册 | 管理员 |
| 商户审核通过 | 管理员审核 | 商户 |

---

## Phase 4: WPBridge 对接

### Step 4.1: 许可证同步（集市 → WPBridge）

**原理：** 用户在集市购买后，lmfwc 生成许可证，通过 Webhook 同步到 WPBridge License Gateway。

**集市端（WordPress）：**

在 `wp-content/mu-plugins/` 创建 `wenpai-bridge-sync.php`：

```php
<?php
/**
 * 文派集市 -> WPBridge 许可证同步
 *
 * 当 lmfwc 生成许可证时，自动同步到 WPBridge License Gateway。
 */

add_action('lmfwc_event_license_created', function($license) {
    $bridge_url = 'http://127.0.0.1:8090/api/v1/licenses/sync';
    $admin_key  = defined('WPBRIDGE_ADMIN_KEY') ? WPBRIDGE_ADMIN_KEY : '';

    if (empty($admin_key)) return;

    $payload = [
        'license_key' => $license->getDecryptedLicenseKey(),
        'product_id'  => $license->getProductId(),
        'order_id'    => $license->getOrderId(),
        'status'      => $license->getStatus(),
        'expires_at'  => $license->getExpiresAt(),
        'activations_limit' => $license->getTimesActivatedMax(),
    ];

    wp_remote_post($bridge_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $admin_key,
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 10,
    ]);
});
```

**wp-config.php 中添加：**

```php
define('WPBRIDGE_ADMIN_KEY', 'ee03c6e5ecf850cc9578c119b2aed7d6fdc637805751818cf4326be7238c3efc');
```

**WPBridge 端（Go）：** 需要新增 `/api/v1/licenses/sync` 和 `/api/v1/licenses/verify` 端点（Phase 4 的开发任务，后续实现）。

### Step 4.2: 更新 API 对接（飞码 → 集市 → WPBridge）

**流程：**

```
开发者 push tag
  → Forgejo Actions 构建 ZIP
  → Webhook POST 到集市 /wp-json/wenpai/v1/release
  → 集市更新产品版本 + 下载链接
  → 同步到 WPBridge /api/v1/packages/sync
  → 用户站点检测到更新
```

**集市端需要开发的 REST API：**

| 端点 | 方法 | 用途 |
|------|------|------|
| `/wp-json/wenpai/v1/release` | POST | 接收飞码 CI/CD 发布通知 |
| `/wp-json/wenpai/v1/products/{slug}/version` | GET | 查询产品最新版本 |

### Step 4.3: 交叉销售对接（叶子 → 集市）

**原理：** 叶子 Bridge 客户端每日遥测时，WPBridge 返回推荐数据（集市产品链接）。

这部分已在 `WPCY-CROSS-SELL-STRATEGY.md` 中详细设计，实现时需要：

1. WPBridge `handler_telemetry.go` 改 204 → 200 JSON 响应
2. Bridge 客户端 `class-site-health.php` 改 `blocking => true`
3. 新增 `class-recommendations.php` 解析并展示推荐

---

## Phase 5: WPMind 上架（第一个跑通的商业闭环）

### Step 5.1: 创建 WPMind 产品

在集市后台 `产品 -> 新增`：

**产品类型：** 可变产品（Variable Product）

| 变体 | 价格 | 许可证 | 更新 |
|------|------|--------|------|
| Free | ¥0 | 无 | 社区版 |
| Pro | ¥299/年 | 1 站点 | 1 年更新 |
| Business | ¥599/年 | 5 站点 | 1 年更新 |
| Enterprise | ¥1299/年 | 无限站点 | 1 年更新 + 优先支持 |

### Step 5.2: 配置 WC Subscriptions

每个付费变体设置为订阅产品：
- 订阅周期：1 年
- 到期提醒：到期前 30 天、7 天、1 天
- 自动续费：启用（支付宝/微信支持的话）
- 宽限期：到期后 14 天

### Step 5.3: 配置 lmfwc 许可证模板

| 套餐 | 激活次数上限 | 有效期 | 许可证格式 |
|------|-------------|--------|-----------|
| Pro | 1 | 365 天 | XXXX-XXXX-XXXX-XXXX |
| Business | 5 | 365 天 | XXXX-XXXX-XXXX-XXXX |
| Enterprise | 999 | 365 天 | XXXX-XXXX-XXXX-XXXX |

### Step 5.4: 端到端测试清单

```
□ 访问 wenpai.net/plugins/wpmind/ 产品页正常显示
□ 选择 Pro 套餐加入购物车
□ 结算页面支付宝/微信支付正常
□ 支付成功后收到邮件（含许可证密钥 + 下载链接）
□ lmfwc 后台可见新许可证
□ 许可证同步到 WPBridge（检查 WPBridge 日志）
□ 在测试站点安装 WPMind，输入许可证密钥
□ WPMind 调用 WPBridge /licenses/activate 成功
□ WPMind 检测到更新并可升级
□ 订阅到期后许可证自动失效
□ 续费后许可证自动恢复
```

---

## Phase 6: 安全加固

### Step 6.1: WordPress 安全配置

```bash
# wp-config.php 中添加
define('DISALLOW_FILE_EDIT', true);     # 禁止后台编辑文件
define('FORCE_SSL_ADMIN', true);        # 强制 HTTPS 后台
define('WP_AUTO_UPDATE_CORE', false);   # 禁止自动更新核心
```

### Step 6.2: Nginx 安全规则

```nginx
# 限制上传文件大小
client_max_body_size 100M;

# 防止目录遍历
autoindex off;

# 限制 wp-login 暴力破解
location = /wp-login.php {
    limit_req zone=login burst=3 nodelay;
    include enable-php-83.conf;
}

# 在 http 块中定义 zone
# limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;
```

### Step 6.3: 数据库备份

```bash
# 创建每日备份脚本
cat > /www/backup/backup_wenpai.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/www/backup/wenpai_net"
mkdir -p $BACKUP_DIR

# 数据库备份
mysqldump -uroot -p'你的密码' wenpai_net | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# 保留最近 30 天
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete
EOF

chmod +x /www/backup/backup_wenpai.sh

# 添加 crontab（每天凌晨 3 点）
echo "0 3 * * * /www/backup/backup_wenpai.sh" >> /var/spool/cron/root
```

---

## 执行优先级与时间线

| Phase | 内容 | 预估时间 | 依赖 |
|-------|------|----------|------|
| Phase 1 | 基础环境 (MySQL + WP + Multisite + Nginx) | 1-2 小时 | 无 |
| Phase 2 | 核心插件安装 | 2-3 小时 | Phase 1 |
| Phase 3 | 集市配置 (MarketKing + 分类 + 页面) | 1-2 天 | Phase 2 |
| Phase 4 | WPBridge 对接开发 | 3-5 天 | Phase 2 |
| Phase 5 | WPMind 上架 + 端到端测试 | 1-2 天 | Phase 3 + 4 |
| Phase 6 | 安全加固 | 半天 | Phase 1 |

**建议执行顺序：** Phase 1 → Phase 6 → Phase 2 → Phase 3 → Phase 4 → Phase 5

（安全加固提前到 Phase 2 之前，避免裸奔状态暴露在公网）

---

## 关键决策记录

| 日期 | 决策 | 理由 |
|------|------|------|
| 2026-02-15 | Multisite 子目录模式 | 减少维护成本，统一域名 |
| 2026-02-15 | 主站装多商户插件，子站为服务性站点 | 商户不需要独立子站 |
| 2026-02-15 | 以 WordPress 产品为入口 | 先验证再扩展品类 |
| 2026-02-15 | 前期中国用户为主 | 聚焦，后续再国际化 |
| 2026-02-15 | 支付网关用自研 wooalipay + woowechatpay | 已有成熟方案 |
| 2026-02-15 | 平台定位为开源软件数字资产交易平台 | 不限于 WordPress |
| 2026-02-15 | MarketKing Pro 作为多商户方案 | 功能完整，与 WC 深度集成 |
| 2026-02-15 | lmfwc + WPBridge 混合许可证方案 | Method E，兼顾灵活性和控制力 |

---

## 与现有平台对接总览

```
┌─────────────┐     许可证同步      ┌──────────────┐
│  文派集市     │ ──────────────────→ │  WPBridge    │
│  wenpai.net  │ ←────────────────── │  (Go 服务)    │
│  (WC+lmfwc)  │     更新包同步      │  port 8090   │
└──────┬───────┘                     └──────┬───────┘
       │                                     │
       │ Webhook                    验证/激活/更新 API
       │                                     │
┌──────┴───────┐                     ┌───────┴──────┐
│  飞码 CI/CD   │                     │  用户站点     │
│  Forgejo      │                     │  (WPMind/    │
│  Actions      │                     │   叶子客户端)  │
└──────────────┘                     └──────────────┘
```

---

*最后更新: 2026-02-15*
