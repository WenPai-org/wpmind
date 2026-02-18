# WPMind Provider 测试与排错指南

> 本文档记录各 AI Provider 的测试方法、已知问题和排错流程。

## 快速测试命令

```bash
# 测试指定 provider
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\$result = wpmind_chat('你好', ['provider' => 'PROVIDER_ID']);
if (is_wp_error(\$result)) {
    echo 'ERROR [' . \$result->get_error_code() . ']: ' . \$result->get_error_message();
} else {
    echo json_encode(\$result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
"

# 测试指定 provider + model
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\$result = wpmind_chat('你好', ['provider' => 'PROVIDER_ID', 'model' => 'MODEL_NAME']);
..."

# 查看所有 provider 状态
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\$api = \WPMind\API\PublicAPI::instance();
echo json_encode(\$api->get_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"

# 查看熔断器状态
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
echo json_encode(
    \WPMind\Failover\FailoverManager::instance()->get_status_summary(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
"

# 重置指定 provider 熔断器
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\WPMind\Failover\FailoverManager::instance()->reset_provider('PROVIDER_ID');
"

# 列出所有 provider 配置
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\$endpoints = \WPMind\wpmind()->get_custom_endpoints();
foreach (\$endpoints as \$id => \$ep) {
    \$enabled = !empty(\$ep['enabled']) ? 'ON' : 'OFF';
    \$has_key = !empty(\$ep['api_key']) ? 'YES' : 'NO';
    \$model = \$ep['models'][0] ?? '-';
    \$url = \$ep['custom_base_url'] ?? \$ep['base_url'] ?? '-';
    echo sprintf('%-15s %s  key:%s  model:%-30s  url:%s', \$id, \$enabled, \$has_key, \$model, \$url) . PHP_EOL;
}
"
```

## Provider 兼容性矩阵

> 最后更新: 2026-02-08

| Provider | ID | API 格式 | Base URL | 默认模型 | 状态 | 备注 |
|----------|-----|---------|----------|----------|------|------|
| DeepSeek | deepseek | OpenAI 兼容 | `https://api.deepseek.com/v1` | deepseek-chat | ✅ 正常 | 支持 reasoning_content |
| 通义千问 | qwen | OpenAI 兼容 | `https://dashscope.aliyuncs.com/compatible-mode/v1` | qwen-turbo | ✅ 正常 | |
| 豆包 | doubao | OpenAI 兼容 | `https://ark.cn-beijing.volces.com/api/v3` | doubao-seed-1-8-251228 | ✅ 正常 | 模型名必须用官方全名，不支持简写 |
| 智谱 AI | zhipu | OpenAI 兼容 | `https://open.bigmodel.cn/api/paas/v4` | glm-4 | ⚠️ 待测 | |
| Moonshot | moonshot | OpenAI 兼容 | `https://api.moonshot.cn/v1` | moonshot-v1-8k | ⬚ 未配置 | |
| 硅基流动 | siliconflow | OpenAI 兼容 | `https://api.siliconflow.cn/v1` | deepseek-ai/DeepSeek-V3 | ⬚ 未配置 | |
| 百度文心 | baidu | 自定义格式 | `https://aip.baidubce.com` | ernie-4.0-8k | ⬚ 未配置 | 需要 access_token |
| MiniMax | minimax | OpenAI 兼容 | `https://api.minimax.chat/v1` | abab6.5s-chat | ⬚ 未配置 | |
| OpenAI | openai | 原生 | `https://api.openai.com/v1` | gpt-4o | ⬚ 未配置 | |
| Anthropic | anthropic | 自定义格式 | `https://api.anthropic.com/v1` | claude-3-5-sonnet | ⬚ 未配置 | 需 SDK 适配 |
| Google AI | google | 自定义格式 | Vertex AI | gemini-2.0-flash | ⬚ 未配置 | 需 SDK 适配 |

## 已知问题与解决方案

### 1. 豆包模型名问题 (2026-02-08)

**现象**: 请求返回 404 `InvalidEndpointOrModel.NotFound`

**原因**: 豆包 API 不接受简写模型名（如 `doubao-pro-4k`），必须使用官方完整模型名（如 `doubao-seed-1-8-251228`）。

**解决**: 在后台将模型名改为火山引擎官方模型名。可在 [火山引擎模型列表](https://www.volcengine.com/docs/82379/1263482) 查询。

**注意**: 豆包同时支持 Chat Completions API (`/chat/completions`) 和新版 Responses API (`/responses`)，WPMind 使用前者。

### 2. 熔断器误触发

**现象**: Provider 配置正确但请求始终 failover 到其他 provider。

**原因**: 之前的连续失败触发了熔断器（Circuit Breaker），即使配置已修正，熔断器仍处于 open 状态。

**排查**:
```bash
# 检查熔断器状态
sudo -u www wp --path=/www/wwwroot/wpcy.com eval "
\$s = \WPMind\Failover\FailoverManager::instance()->get_status_summary();
foreach (\$s as \$id => \$p) {
    echo sprintf('%-15s %s  health:%d  success_rate:%d%%', \$id, \$p['state_label'], \$p['health_score'], \$p['success_rate']) . PHP_EOL;
}
"
```

**解决**: `reset_provider('provider_id')` 重置熔断器。

### 3. 默认模型不匹配

**现象**: 不传 `model` 参数时，请求使用默认 provider 的模型名去请求目标 provider，导致 404。

**原因**: `wpmind_chat($prompt, ['provider' => 'doubao'])` 不传 model 时，会使用全局默认 model（如 `qwen-turbo`），而不是 doubao 自己的默认 model。

**解决**: 显式传 `model` 参数，或确保路由逻辑正确选择目标 provider 的模型。这是一个待修复的 bug。

### 4. ExactCache 缓存干扰测试

**现象**: 修改了 provider 配置但返回结果不变。

**解决**: 测试时传 `cache_ttl => -1` 强制跳过缓存：
```php
wpmind_chat('测试', ['provider' => 'qwen', 'cache_ttl' => -1]);
```

## 新 Provider 接入检查清单

1. [ ] 确认 API 格式（OpenAI 兼容 / 自定义）
2. [ ] 获取 API Key 并配置到后台
3. [ ] 确认 Base URL（注意版本号路径，如 `/v1`、`/v3`、`/v4`）
4. [ ] 确认模型名（使用官方完整名称，不要简写）
5. [ ] 用 `wpmind_chat()` 测试基本对话
6. [ ] 检查熔断器状态确认无误触发
7. [ ] 测试 failover 链路（禁用该 provider 后是否正确切换）
8. [ ] 记录到本文档的兼容性矩阵
