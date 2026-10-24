# WPMind AI Client 兼容矩阵（0.13）

运行时检测见 `WPMind\Provider\Compat\AiClientEnvironment`（Site Health 只读展示，不含密钥）。

| 环境 | SDK 来源 | WPMind 行为 |
|---|---|---|
| WP 6.4–6.9，没有 SDK | 无 | 只走原生 HTTP 路径；不注册 SDK Provider；Site Health：「AI Client 不可用，仅 WPMind 内部调用」 |
| WP 6.4–6.9 + 旧 AI 插件（`WordPress\AI_Client\`） | 插件自带 | 保留 Discovery 初始化与 `pre_option_wp_ai_client_provider_credentials` 桥接 |
| WP 7.0.x | Core 内置 **1.3.1**（7.0.7 实测） | 注册国产 Provider；**不写** `connectors_ai_*`；不挂 `wp_ai_client_prevent_prompt` |
| WP 7.1.x | Core 内置 **1.3.1**（7.1.3 实测） | 同 7.0；不依赖 embeddings/streaming 的 SDK API |
| 任意 WP + 无作用域的 php-ai-client 冲突 | 冲突 | Site Health 警告；WPMind 不随包携带 SDK |
| PHP | 8.1 / 8.2 / 8.3 / 8.4 | 最低 8.1；CI unit job 固定 PHP 8.3 |

## 0.13 行为变更摘要

- `AuthenticatedProviderAvailability::isConfigured()` **不发网络请求**（有本地 key/认证即 true）。
- 不再全局挂载 `wp_ai_client_prevent_prompt`。
- 不再把 WPMind key 双写到 `connectors_ai_{id}_api_key`；升级时仅删除**与 WPMind key 完全相同**的镜像。
- 升级后：Core「设置 → 连接器」不再显示 WPMind 帮忙填的 key；若其他插件要走 Core 路径调用这些 provider，请在连接器中自行填写。

事实核实来源：`v013-sdk-facts.md`（2026-10-19）。
