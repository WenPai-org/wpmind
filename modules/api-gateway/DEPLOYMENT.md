# API Gateway - Deployment Guide

WPMind API Gateway 模块部署指南。

## Prerequisites

| 要求 | 最低版本 |
|------|----------|
| PHP | 8.1+ |
| WordPress | 6.0+ |
| WPMind 插件 | 3.6.0+ (已激活) |
| MySQL/MariaDB | 5.7+ / 10.3+ |

可选依赖:
- **Redis** - 用于高性能速率限制 (推荐生产环境)
- **OpenSSL** - API Key 哈希 (PHP 默认已包含)

## Database Setup

数据库表在模块激活时由 `SchemaManager` 自动创建，无需手动操作。

自动创建的表:
- `{prefix}wpmind_api_keys` - API 密钥存储
- `{prefix}wpmind_gateway_logs` - 请求审计日志

如需手动触发升级:
```php
\WPMind\Modules\ApiGateway\SchemaManager::maybe_upgrade();
```

## Nginx Configuration

在站点 Nginx 配置中添加以下规则，确保 SSE 流式输出正常工作:

```nginx
# API Gateway - SSE streaming support
location ~ ^/wp-json/mind/v1/ {
    proxy_buffering off;
    proxy_cache off;

    proxy_read_timeout 300s;
    proxy_send_timeout 300s;

    # SSE headers
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding on;

    # Allow large request bodies (embeddings)
    client_max_body_size 10m;

    # Pass to PHP-FPM
    try_files $uri $uri/ /index.php?$args;
}
```

> **宝塔面板**: 在站点设置 > 配置文件中添加上述 location 块，放在其他 location 规则之前。

## Apache Configuration

在 WordPress 根目录的 `.htaccess` 中添加:

```apache
# API Gateway - SSE support
<IfModule mod_headers.c>
    <LocationMatch "^/wp-json/mind/v1/">
        Header set Cache-Control "no-cache, no-store"
        Header set X-Accel-Buffering "no"
    </LocationMatch>
</IfModule>

# Increase timeout for streaming endpoints
<IfModule mod_reqtimeout.c>
    RequestReadTimeout body=300
</IfModule>

# Allow large request bodies
LimitRequestBody 10485760
```

## Cloudflare Notes

Cloudflare 默认会缓冲 SSE 响应，需要特殊配置:

1. **Page Rules** (推荐): 为 `your-site.com/wp-json/mind/v1/*` 创建规则:
   - Cache Level: Bypass
   - Disable Performance (关闭 Rocket Loader, Minification)

2. **Response Header**: 模块已自动发送 `X-Accel-Buffering: no`，Cloudflare 会识别此头部

3. **Timeout**: Cloudflare Free 计划最大超时 100 秒。如果流式响应超过此限制:
   - 升级到 Pro 计划 (300 秒)
   - 或在客户端设置 `stream: false` 使用非流式模式

## Redis (Optional)

速率限制默认使用 WordPress Transients (数据库)。生产环境推荐使用 Redis:

```php
// wp-config.php
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
```

模块会自动检测 Redis 可用性:
- Redis 可用 -> `RedisRateStore` (高性能，原子操作)
- Redis 不可用 -> `TransientRateStore` (兼容模式，使用数据库)

## First Steps After Deployment

### 1. 启用网关

进入 WordPress 后台 > WPMind > API Gateway 设置页面，开启网关。

### 2. 创建 API Key

在设置页面点击「创建 API Key」，记录生成的密钥 (仅显示一次)。

### 3. 测试连接

```bash
# 非流式请求
curl -X POST https://your-site.com/wp-json/mind/v1/chat/completions \
  -H "Authorization: Bearer sk_mind_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o",
    "messages": [{"role": "user", "content": "Hello"}]
  }'

# 流式请求 (SSE)
curl -X POST https://your-site.com/wp-json/mind/v1/chat/completions \
  -H "Authorization: Bearer sk_mind_xxx" \
  -H "Content-Type: application/json" \
  -N \
  -d '{
    "model": "gpt-4o",
    "messages": [{"role": "user", "content": "Hello"}],
    "stream": true
  }'

# 查看可用模型
curl https://your-site.com/wp-json/mind/v1/models \
  -H "Authorization: Bearer sk_mind_xxx"

# 网关状态
curl https://your-site.com/wp-json/mind/v1/status \
  -H "Authorization: Bearer sk_mind_xxx"
```

## Troubleshooting

| 问题 | 原因 | 解决方案 |
|------|------|----------|
| 404 on endpoints | 固定链接未刷新 | 后台 > 设置 > 固定链接，点击保存 |
| 413 Request Entity Too Large | Nginx body size 限制 | 添加 `client_max_body_size 10m;` |
| SSE 不工作 (一次性返回) | 代理缓冲未关闭 | 添加 `proxy_buffering off;` |
| 504 Gateway Timeout | 上游响应超时 | 增加 `proxy_read_timeout 300s;` |
| 401 Unauthorized | API Key 无效或已撤销 | 检查 Key 状态，重新创建 |
| 429 Too Many Requests | 触发速率限制 | 等待窗口重置或调整限制 |
| 数据库表不存在 | 模块未正确激活 | 停用后重新激活模块 |
| Redis 连接失败 | Redis 未运行 | 检查 Redis 服务，或忽略 (自动降级到 Transients) |
