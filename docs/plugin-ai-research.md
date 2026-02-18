# WordPress 热门插件 AI 功能调研报告

> 调研日期：2026-02-01
> 调研工具：Gemini CLI + Web Search
> 状态：已验证

---

## 1. 核心发现

### ⚠️ 重要结论

**原先假设"所有插件都使用标准 OpenAI API"是不准确的！**

大部分头部商业插件（Yoast、Elementor、Rank Math）的官方 AI 功能均采用 **SaaS 中转模式（Proxy Mode）**，而非用户侧直连 OpenAI（BYOK - Bring Your Own Key）。

这意味着：**简单的 `pre_http_request` 拦截 `api.openai.com` 对这些插件的官方原生 AI 功能无效。**

---

## 2. 逐个插件分析

### 2.1 Yoast SEO Pro (AI 生成功能)

| 项目 | 详情 |
|-----|------|
| **实现方式** | SaaS 中转模式 |
| **API 端点** | `api.yoast.com` 或类似微服务架构 |
| **认证方式** | Yoast Premium License Key，非 OpenAI Key |
| **拦截可行性** | ❌ **极低** |
| **原因** | 请求发往 Yoast 服务器，非 OpenAI。需破解私有协议。|

**替代方案**：存在第三方插件（如 *Yoast AI Autofill*）允许用户填入自己的 OpenAI Key，这类第三方插件可被 WPMind 拦截。

---

### 2.2 Elementor AI

| 项目 | 详情 |
|-----|------|
| **实现方式** | SaaS 中转 + Credit 点数系统 |
| **API 端点** | `my.elementor.com` 或 `api.elementor.com` |
| **认证方式** | Elementor 账户 OAuth/Token，不支持 BYOK |
| **拦截可行性** | ❌ **极低** |
| **原因** | 请求体格式完全自定义，非 OpenAI 标准格式 |

**建议**：只能针对第三方 Elementor AI 扩展插件进行支持。

---

### 2.3 WPML (Automatic Translation)

| 项目 | 详情 |
|-----|------|
| **实现方式** | SaaS 中转 + Credit 点数系统 |
| **API 端点** | WPML ATE (Advanced Translation Editor) 服务器 |
| **认证方式** | 站点与 WPML.org 账号绑定 |
| **拦截可行性** | ❌ **不可行** |
| **原因** | 流量全部经过 WPML 官方服务器中转以计费 |

**例外**：WPML 允许自定义 XML 配置接入第三方翻译服务，但需开发专门的翻译服务提供商插件。

---

### 2.4 TranslatePress AI ✅

| 项目 | 详情 |
|-----|------|
| **实现方式** | **API 直连模式** |
| **API 端点** | `translation.googleapis.com` / `api.deepl.com` |
| **认证方式** | 用户自己的 Google/DeepL API Key (BYOK) |
| **拦截可行性** | ✅ **高** |
| **原因** | 允许用户填入 API Key，直接调用翻译 API |

**WPMind 策略**：
- 拦截 `translation.googleapis.com` 和 `api.deepl.com`
- 将请求转换为大模型翻译请求
- 转换回 Google/DeepL API 格式返回

---

### 2.5 Rank Math Content AI

| 项目 | 详情 |
|-----|------|
| **实现方式** | SaaS 中转 + Credit 点数系统 |
| **API 端点** | Rank Math 服务器 |
| **认证方式** | Rank Math 账户 |
| **拦截可行性** | ❌ **极低** |
| **原因** | 属于封闭的 SaaS 服务 |

---

## 3. 汇总对比

| 插件 | AI 功能模式 | 官方直连 OpenAI | 能否拦截 | 推荐策略 |
|-----|-----------|----------------|---------|---------|
| **Yoast SEO Pro** | SaaS 中转 | ❌ 否 | ❌ 困难 | 支持第三方扩展 |
| **Elementor AI** | SaaS 中转 | ❌ 否 | ❌ 困难 | 支持第三方扩展 |
| **WPML** | SaaS 中转 | ❌ 否 | ❌ 困难 | 开发翻译提供商接口 |
| **TranslatePress** | API 直连 | ✅ 是 | ✅ 可行 | 拦截 Google/DeepL |
| **Rank Math** | SaaS 中转 | ❌ 否 | ❌ 困难 | 支持第三方扩展 |

---

## 4. 修正后的 WPMind 策略

### 4.1 支持级别分层

#### Tier 1：完全支持 ✅

**目标**：所有允许 BYOK (Bring Your Own Key) 的插件

| 插件类型 | 示例 |
|---------|------|
| 独立 AI 内容插件 | AI Engine (Jordy Meow) |
| 通用 AI 写作插件 | GPT3 AI Content Generator |
| 第三方 AI 扩展 | Yoast AI Autofill、AI for Elementor 等 |
| 开发者工具 | 任何直接调用 OpenAI API 的插件 |

**实现方式**：拦截 `api.openai.com` + `api.anthropic.com`

#### Tier 2：部分支持 ⚠️

**目标**：直连 Google/DeepL 的翻译类插件

| 插件 | 需拦截的域名 |
|-----|-------------|
| TranslatePress | `translation.googleapis.com` |
| 其他翻译插件 | `api.deepl.com` |

**实现方式**：
1. 拦截翻译 API 域名
2. 将请求转换为大模型翻译请求
3. 转换回原 API 格式返回

**这将是 WPMind 的差异化卖点！**

#### Tier 3：不支持 ❌

**目标**：官方原生 SaaS AI 功能

| 插件 | 原因 |
|-----|------|
| Yoast SEO Pro 官方 AI | 封闭 SaaS |
| Elementor AI 官方 | 封闭 SaaS + Credit 系统 |
| Rank Math Content AI | 封闭 SaaS + Credit 系统 |
| WPML 官方翻译 | 封闭 SaaS + Credit 系统 |

**策略**：明确告知用户不支持，推荐使用第三方扩展。

---

### 4.2 需要拦截的域名列表

```php
// Tier 1: AI 服务商直连
private const AI_DOMAINS = [
    'api.openai.com',
    'api.anthropic.com',
    'generativelanguage.googleapis.com',
    'api.cohere.ai',
    'api.mistral.ai',
    'api.perplexity.ai',
];

// Tier 2: 翻译服务直连
private const TRANSLATE_DOMAINS = [
    'translation.googleapis.com',
    'api.deepl.com',
    'api-free.deepl.com',
];
```

---

### 4.3 新增功能建议

#### 翻译 API 桥接器

**核心思路**：将 Google Translate / DeepL API 请求，转换为大模型翻译请求

```php
class TranslateAPIBridge {
    
    /**
     * 拦截 Google Translate 请求
     */
    public function interceptGoogleTranslate($request): array {
        $text = $request['q'];
        $source = $request['source'] ?? 'auto';
        $target = $request['target'];
        
        // 使用大模型翻译
        $translated = $this->translateWithLLM($text, $source, $target);
        
        // 返回 Google API 格式
        return [
            'data' => [
                'translations' => [
                    ['translatedText' => $translated]
                ]
            ]
        ];
    }
    
    /**
     * 使用大模型翻译
     */
    private function translateWithLLM(string $text, string $source, string $target): string {
        $prompt = "将以下文本从 {$source} 翻译成 {$target}，只返回翻译结果：\n\n{$text}";
        
        $router = \WPMind\Routing\IntelligentRouter::instance();
        $result = $router->route($prompt, 'text', ['max_tokens' => 4096]);
        
        return $result['content'] ?? $text;
    }
}
```

---

## 5. 真正的目标用户画像

### 5.1 核心用户

```
1. 使用 BYOK 插件的用户
   ├── AI Engine 用户
   ├── GPT3 AI Content Generator 用户
   └── 其他 OpenAI 直连插件用户

2. 使用第三方扩展的用户
   ├── Yoast + 第三方 AI 扩展
   ├── Elementor + 第三方 AI Widget
   └── Gutenberg + AI 写作扩展

3. TranslatePress 用户
   └── 希望用国产 AI 代替 Google/DeepL
```

### 5.2 用户规模估算

| 类型 | 预估用户量 | 转化率 | 潜在付费用户 |
|-----|-----------|-------|------------|
| BYOK 插件用户 | 50,000 | 5% | 2,500 |
| 第三方扩展用户 | 30,000 | 3% | 900 |
| TranslatePress 用户 | 300,000 | 1% | 3,000 |
| **总计** | **380,000** | - | **6,400** |

---

## 6. 下一步行动

1. **调整设计文档**：更新 `ai-gateway-design.md` 的兼容性矩阵
2. **优先实现 Tier 1**：拦截 OpenAI/Anthropic API
3. **重点开发**：翻译 API 桥接器（差异化功能）
4. **建立测试矩阵**：针对 AI Engine、TranslatePress 等进行验证
5. **文档和营销**：明确告知用户支持范围

---

*调研报告结束*
