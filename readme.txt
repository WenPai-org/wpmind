=== WPMind ===
Contributors: wenpai
Tags: ai, deepseek, qwen, wordpress-ai, artificial-intelligence
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.12.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

面向国内外 AI Provider 的 WordPress 集成插件。一个插件内提供 Provider、成本、缓存、内容、媒体、GEO 与可选 API Gateway。

== Description ==

WPMind 统一管理 WordPress 使用的 AI Provider，并支持 BYOK、预算、缓存和故障转移。
高级模块包含在同一插件中，但默认关闭，由管理员按需启用。

新安装默认启用：

* Provider 接入与 WordPress AI Client 兼容
* 成本控制与预算
* 精确缓存

内置但默认关闭：

* API Gateway
* GEO
* Auto Meta
* Media Intelligence
* Analytics

== Installation ==

1. 上传 `wpmind` 到 `/wp-content/plugins/`，或在 WordPress 后台安装。
2. 启用 WPMind。
3. 在 WPMind 设置中配置 Provider API Key 并测试连接。
4. 在“模块管理”中按需开启高级功能。

== Changelog ==

= 0.12.0 =

* 单插件内部模块化，高级模块改为明确启用。
* 修复 API Gateway 关闭后仍注册 REST 路由的问题。
* 限流锁失败时拒绝请求。
* 移除 Pro/Enterprise 授权入口。
* 加固密钥清理、卸载、测试和部署流程。
