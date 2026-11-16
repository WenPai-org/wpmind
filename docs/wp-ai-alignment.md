# WPMind × WordPress 官方 AI 方向对齐计划

日期：2026-11-17。依据：WP core 7.1.3 源码实测（`/tmp/wp-core`）+ wpmind 仓审计。

## 官方机制（已从 core 源码核实）

- `init:5` wpmind `register_wpmind_providers()` 把启用的国产 provider 注册进 `AiClient::defaultRegistry()`，并直接 `setProviderRequestAuthentication` 注入 wpmind 自存 key。
- `init:15` `_wp_connectors_init` → `_wp_connectors_register_default_ai_providers`：**自动发现 registry 里所有 provider 生成 connector**，`setting_name = connectors_ai_<id>_api_key`，constant/env 为 `<ID>_API_KEY`。
- `init:20` `_wp_register_default_connector_settings`：把 `connectors_ai_*` 注册进 Settings API（`connectors` 组，show_in_rest）→ Connectors 设置页可填。
- `init:20` `_wp_connectors_pass_default_keys_to_ai_client`：connector option 非空时 `setProviderRequestAuthentication` —— **connector key 优先级高于 wpmind 注入的 key**（后写覆盖）。
- SDK 默认认证解析顺序：env var → PHP constant →（WP 层）connector option → provider 自带 auth。
- `AuthenticatedProviderAvailability::isConfigured()` 只查本地 auth 存在性，不发付费探测——无 key 注册 provider 是安全的。

## 结论：原判断的「断层」实际比预想小

wpmind 在 init:5 已把自家 key 注进 SDK registry，WP7 下 SDK 路径本来就能用 wpmind key。真正的问题是：

1. **Connectors 可发现性**：provider 只在「wpmind 里 enabled 且有 key」时才进 registry → Connectors 页看不到未配置的 DeepSeek 等。
2. **凭证单向**：在 Connectors UI 填的 key 只服务 SDK 路径；wpmind 自有 HTTP 路径（`ProviderClient`）只读 wpmind option → 两边要各填一次。
3. **custom_base_url 盲区**：SDK provider 类用各自硬编码 base URL，wpmind 的 `custom_base_url` 只对原生 HTTP 路径生效。

## 实施项

### A. 凭证收敛（本次做）

- 新增 `includes/Provider/ConnectorCredentials.php`：`key_for($providerId)` 读 `connectors_ai_<id>_api_key`（仅 WP_Connector_Registry 存在时）。
- `ProviderRegistrar::registerProviders`：enabled 即注册 provider 类（不再要求 api_key）；key 解析顺序 = wpmind option → connector option；`custom_base_url` 非空时跳过 SDK 注册（避免 SDK 打错端点，原生路径不受影响）。
- `register.php get_wpmind_credentials()`：wpmind key 缺失时回落 connector option。
- `ProviderClient`：endpoint api_key 为空时回落 connector option（wpmind 自有路径也认 Connectors 凭证）。
- 效果：key 填在 wpmind 或 Connectors 任一处，两条路径都通；Connectors 页始终能看到 8 家国产 provider。

### B. Abilities 扩展（本次做）

- auto-meta 模块注册 `wpmind/auto-meta-preview`（post_id → `MetaGenerator::preview_meta`，只读预览不写库）。
- media-intelligence 注册 `wpmind/media-alt-text-preview`（attachment_id → `AltTextGenerator::generate_proposed_alt`，只读预览）。
- permission `manage_options`；`meta.mcp.public=true` 让官方 MCP Adapter 自动暴露；annotations readonly。
- 写路径（apply/写 meta）不注册为 ability——写操作仍走后台人工审核流（workflow dry-run→review→apply）。

### C. mind/chat schema 对齐（本次做）

- input_schema 增加 `model`（string，映射到 `wpmind_chat` options.model）；`messages`/`prompt`/`options` 保持兼容。

### D. 管线收敛（本次只做决策，不动默认）

- `wpmind_sdk_providers` 默认仍 `['anthropic','google']`。国产 provider 切 SDK 路径是行为变更（structured/json_mode、vision、tools 兼容性未逐一验证），需真 key 回归后再议；站点可用 `wpmind_sdk_providers` filter 提前启用。记入本文档「后续」。

### E. 后续预案（不写码）

- **Secrets API（7.1/7.2）**：connector option 落地后，wpmind 自有 key 存储是否换轨 Secrets API，等 API 稳定再评估；`pre_option` 桥（WP<7）届时可退役。
- **OAuth agent 授权（7.3）**：`mind/v1` Bearer key 与官方 OAuth 共存评估，等 7.3 方案落地。
- **PSR-16 cache**：`AiClient::getCache()` 与 ExactCache 语义不同（ExactCache 是请求级精确匹配+成本账本），保留；SDK 路径调用注意不双重缓存。
- **wp-ai/v1 REST（7.1+）**：官方入站 generate 端点落地后，评估 wpmind PublicAPI 是否挂官方端点或保持 `mind/v1` 自有网关。

## 验证

- `php -l` 改动文件；`vendor/bin/phpunit`（RegisterTest 等可能需同步新行为）；phpcs/phpstan 基线。
- wp-env 7.1.3：Connectors 页能列出国产 provider；connector option → SDK auth 生效（`_wp_connectors_pass_default_keys_to_ai_client` 路径）。
- abilities：`wp_abilities_api_init` 注册的两个 preview ability 在 abilities REST 可见。
