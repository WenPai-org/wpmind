# WPMind 目标插件兼容性清单

> 更新日期：2026-02-01
> 调研来源：Gemini CLI + Web Search
> 状态：已验证

---

## 1. 完全兼容的 BYOK 插件（Tier 1）

这些插件支持用户自带 API Key，是 WPMind 的核心目标用户。

### 1.1 AI Power: Complete AI Pack ⭐⭐⭐⭐⭐

**最推荐！对自定义端点支持最友好。**

| 属性 | 详情 |
|-----|------|
| 活跃安装量 | 10,000+ |
| BYOK 支持 | ✅ 完美支持 |
| 自定义 API 端点 | ✅ 有 "Custom Base URL" 选项 |
| 原生支持的服务商 | OpenAI, Google Gemini, Azure, Anthropic, **DeepSeek**, Ollama, Replicate, Mistral |
| 国产模型支持 | ✅ 通过 "Custom OpenAI" 模式可连接任意兼容接口 |
| WPMind 兼容策略 | 直接拦截 + 作为自定义端点 |

**关键发现**：AI Power 已原生支持 DeepSeek！说明国产模型在 WordPress 生态中的需求是真实存在的。

---

### 1.2 AI Engine (Meow Apps) ⭐⭐⭐⭐⭐

**开发者最爱，架构最灵活。**

| 属性 | 详情 |
|-----|------|
| 活跃安装量 | 100,000+ |
| BYOK 支持 | ✅ 支持 |
| 自定义 API 端点 | ✅ 通过 "Environment" 配置 |
| 原生支持的服务商 | OpenAI, Anthropic, Google Gemini, Mistral, Perplexity, OpenRouter |
| 国产模型支持 | ✅ 推荐使用 OpenRouter 作为中转 |
| WPMind 兼容策略 | 直接拦截 + 提供 WPMind 作为 Environment |

**关键发现**：AI Engine 推荐 OpenRouter 作为中转，这与 WPMind 的定位非常相似！

---

### 1.3 其他 BYOK 插件

| 插件 | 安装量 | BYOK | 自定义端点 | 备注 |
|-----|-------|------|-----------|------|
| Auto Featured Image | 80,000+ | ✅ | ⚠️ 有限 | 主要支持 DALL-E |
| ContentBot AI | 10,000+ | ✅ | ✅ | BYOK 模式 |
| Jetwp/Jetpack AI | 5,000,000+ | ❌ | ❌ | **不支持！** 订阅制 |

---

## 2. WPMind 定位策略调整

### 2.1 与 AI Power / AI Engine 的关系

| 维度 | AI Power | AI Engine | WPMind |
|-----|----------|-----------|--------|
| 定位 | 内容生成插件 | 内容生成插件 | **基础设施层** |
| 功能 | 文章、图片、聊天 | 文章、聊天、API | 路由、监控、故障转移 |
| 服务商管理 | 分散配置 | 分散配置 | **统一管理** |
| 成本控制 | ❌ 无 | ❌ 无 | ✅ 预算管理 |
| 智能路由 | ❌ 无 | ❌ 无 | ✅ 多服务商路由 |
| 故障转移 | ❌ 无 | ❌ 无 | ✅ 自动熔断 |

### 2.2 WPMind 的差异化价值

```
用户 A 使用 AI Engine
用户 B 使用 AI Power
用户 C 使用其他插件
           │
           ▼
    ┌─────────────────┐
    │   WPMind        │
    │  ─────────────  │
    │  • 统一服务商配置│
    │  • 成本监控      │
    │  • 智能路由      │
    │  • 故障转移      │
    │  • 预算告警      │
    └────────┬────────┘
             │
   ┌─────────┼─────────┐
   ▼         ▼         ▼
DeepSeek  通义千问   豆包
```

**结论**：WPMind 不是要替代 AI Engine/AI Power，而是作为它们的**后端基础设施**。

---

## 3. 两种集成模式

### 模式 A：HTTP 拦截模式

```php
// 拦截所有发往 OpenAI 的请求
add_filter('pre_http_request', function($pre, $args, $url) {
    if (strpos($url, 'api.openai.com') !== false) {
        return WPMind\Gateway\AIGateway::handle($url, $args);
    }
    return $pre;
}, 1, 3);
```

**优点**：用户无需修改任何配置
**缺点**：可能与插件自身的错误处理冲突

### 模式 B：OpenAI 兼容端点模式

```
WPMind 提供：
https://your-site.com/wp-json/wpmind/v1/chat/completions

用户在 AI Power 中配置：
Custom Base URL: https://your-site.com/wp-json/wpmind/v1/
```

**优点**：完全透明，用户有控制权
**缺点**：需要用户手动配置

### 建议：**两种模式都支持**

- 提供 HTTP 拦截作为"懒人模式"
- 提供兼容端点作为"专业模式"

---

## 4. 优先开发计划

### Phase 1：核心功能

| 任务 | 优先级 | 说明 |
|-----|-------|------|
| OpenAI 兼容 REST API | 🔴 P0 | `/wp-json/wpmind/v1/chat/completions` |
| HTTP 拦截器 | 🔴 P0 | 拦截 `api.openai.com` |
| AI Engine 集成指南 | 🟡 P1 | 文档 + 视频教程 |
| AI Power 集成指南 | 🟡 P1 | 文档 + 视频教程 |

### Phase 2：高级功能

| 任务 | 优先级 | 说明 |
|-----|-------|------|
| 翻译 API 桥接 | 🟡 P1 | Google/DeepL → 大模型翻译 |
| Anthropic 格式支持 | 🟡 P1 | 支持 Claude API 格式 |
| 流式响应 | 🟢 P2 | SSE 支持 |

---

## 5. 市场机会总结

### 5.1 直接目标用户

| 来源 | 用户量 | 预估可转化 |
|-----|-------|-----------|
| AI Engine 用户 | 100,000 | 2,000 |
| AI Power 用户 | 10,000 | 500 |
| 其他 BYOK 插件 | 50,000 | 1,000 |
| TranslatePress 用户 | 300,000 | 1,500 |
| **总计** | **460,000** | **5,000** |

### 5.2 商业模式

```
免费版
├── 3 个服务商
├── 基础路由
└── 1000 次/月

专业版 ¥99/年
├── 无限服务商
├── 智能路由
├── 预算管理
└── 优先支持

企业版 ¥299/年
├── 所有专业功能
├── 翻译 API 桥接
├── SLA 保障
└── 私有部署支持
```

---

*文档结束*
