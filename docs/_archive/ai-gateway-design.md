# WPMind AI Gateway 技术方案

> 版本：2.0
> 更新日期：2026-02-01
> 状态：深度讨论中

---

## 1. 核心理念

### 1.1 方案定位

**不是"拦截"，而是"覆盖转发"**

| 概念 | 描述 | 特点 |
|-----|------|------|
| **拦截 (Intercept)** | 阻止原请求，完全替代 | 侵入性强，易被检测 |
| **覆盖转发 (Override & Forward)** | 保持协议格式，替换后端 | 透明无感，兼容性好 |

### 1.2 WPMind 作为"AI 桥接器"

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress 站点                            │
│  ┌────────┐  ┌────────┐  ┌────────┐  ┌────────┐           │
│  │ Yoast  │  │Elementor│  │Rank Math│  │AI Engine│          │
│  │SEO Pro │  │   AI   │  │Content AI│ │(BYOK)  │          │
│  └───┬────┘  └───┬────┘  └───┬────┘  └───┬────┘          │
│      │           │           │           │                  │
│      └───────────┴───────────┴───────────┘                  │
│                         │                                    │
│                         ▼                                    │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              WPMind AI Gateway                        │  │
│  │  ═══════════════════════════════════════════════════ │  │
│  │                                                       │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │  │
│  │  │  Layer 1    │  │  Layer 2    │  │  Layer 3    │  │  │
│  │  │  OpenAI     │  │  覆盖转发   │  │  UI 注入    │  │  │
│  │  │  兼容层     │  │  适配器     │  │  层         │  │  │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  │  │
│  │         │                │                │          │  │
│  └─────────┼────────────────┼────────────────┼──────────┘  │
│            │                │                │              │
└────────────┼────────────────┼────────────────┼──────────────┘
             │                │                │
             ▼                ▼                ▼
      ┌──────────────────────────────────────────────┐
      │           WPMind 服务商路由器                 │
      │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐│
      │  │DeepSeek│ │通义千问│ │ 混元   │ │ 豆包  ││
      │  └────────┘ └────────┘ └────────┘ └────────┘│
      └──────────────────────────────────────────────┘
```

---

## 2. 三层架构设计

### 2.1 Layer 1：OpenAI 兼容层（优先级最高）

#### 目标插件
- AI Engine (Meow Apps)
- AI Power
- GPT3 AI Content Generator
- 所有直接调用 `api.openai.com` 的插件

#### 技术实现

```php
// 拦截所有发往 OpenAI 的请求
add_filter('pre_http_request', function($preempt, $args, $url) {
    // 只处理 OpenAI API 请求
    if (strpos($url, 'api.openai.com') === false) {
        return $preempt;
    }
    
    // 检查 Gateway 是否启用
    if (!WPMind\Gateway::isEnabled()) {
        return $preempt;
    }
    
    // 解析 OpenAI 格式请求
    $body = json_decode($args['body'], true);
    $messages = $body['messages'] ?? [];
    $model = $body['model'] ?? 'gpt-4';
    
    // 提取 prompt
    $prompt = '';
    foreach ($messages as $msg) {
        if ($msg['role'] === 'user') {
            $prompt .= $msg['content'] . "\n";
        }
    }
    
    // 路由到 WPMind 配置的服务商
    $response = WPMind\Router::chat($prompt, [
        'model' => $model,
        'max_tokens' => $body['max_tokens'] ?? 4096,
    ]);
    
    // 转换为 OpenAI 响应格式
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body' => json_encode([
            'id' => 'wpmind-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'wpmind-' . $response['provider'],
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $response['content'],
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => $response['usage']['input'] ?? 0,
                'completion_tokens' => $response['usage']['output'] ?? 0,
                'total_tokens' => $response['usage']['total'] ?? 0,
            ],
        ]),
    ];
}, 1, 3);
```

#### 可行性评估
- **技术难度**：🟢 低
- **兼容性**：🟢 极高（OpenAI 协议是标准化的）
- **维护成本**：🟢 低

---

### 2.2 Layer 2：覆盖转发适配器

#### 目标插件
- Yoast SEO Pro
- Rank Math Content AI
- (Elementor AI 风险较高)

#### 技术挑战

| 挑战 | 描述 | 解决方案 |
|-----|------|---------|
| **响应格式未知** | 需要完美模拟厂商响应 | 嗅探模式收集样本 |
| **积分同步** | 插件可能检查积分余额 | 只覆盖生成请求，不覆盖状态请求 |
| **签名验证** | 部分插件验证响应来源 | 需要逆向分析 |
| **格式变化** | 厂商更新 API 会导致失效 | 版本化适配器 + 社区维护 |

#### 嗅探模式实现

```php
/**
 * WPMind 嗅探模式
 * 
 * 记录所有 AI 相关的 HTTP 请求和响应，用于分析格式
 */
class AISniffer {
    
    private const LOG_OPTION = 'wpmind_sniffer_logs';
    private const MAX_LOGS = 100;
    
    // 目标域名
    private const WATCH_DOMAINS = [
        'api.yoast.com',
        'my.elementor.com', 
        'api.rankmath.com',
        'connect.rankmath.com',
    ];
    
    public function __construct() {
        if (get_option('wpmind_sniffer_enabled', false)) {
            add_action('http_api_debug', [$this, 'capture'], 10, 5);
        }
    }
    
    public function capture($response, $context, $class, $args, $url) {
        if ($context !== 'response') {
            return;
        }
        
        // 检查是否是监控的域名
        $matched = false;
        foreach (self::WATCH_DOMAINS as $domain) {
            if (strpos($url, $domain) !== false) {
                $matched = true;
                break;
            }
        }
        
        if (!$matched) {
            return;
        }
        
        // 记录请求和响应
        $logs = get_option(self::LOG_OPTION, []);
        
        $logs[] = [
            'timestamp' => time(),
            'url' => $url,
            'request_body' => $args['body'] ?? '',
            'request_headers' => $args['headers'] ?? [],
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_body' => wp_remote_retrieve_body($response),
        ];
        
        // 保持日志大小
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }
        
        update_option(self::LOG_OPTION, $logs);
    }
}
```

#### 覆盖转发实现（以 Yoast 为例）

```php
/**
 * Yoast SEO Pro AI 覆盖适配器
 */
class YoastAIAdapter {
    
    public function __construct() {
        add_filter('pre_http_request', [$this, 'override'], 1, 3);
    }
    
    public function override($preempt, $args, $url) {
        // 只处理 Yoast AI 请求
        if (strpos($url, 'api.yoast.com') === false) {
            return $preempt;
        }
        
        // 检查是否是 AI 生成请求（非状态检查）
        if (strpos($url, '/ai/generate') === false) {
            return $preempt; // 放行状态检查请求
        }
        
        // 解析请求
        $body = json_decode($args['body'], true);
        $prompt = $this->extractPrompt($body);
        $context = $body['context'] ?? [];
        
        // 构建增强的 prompt
        $enhanced_prompt = $this->buildPrompt($prompt, $context);
        
        // 调用 WPMind
        $response = WPMind\Router::chat($enhanced_prompt, [
            'max_tokens' => 500,
        ]);
        
        // 模拟 Yoast 响应格式
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode([
                'success' => true,
                'suggestions' => [
                    [
                        'title' => $response['content'],
                        'score' => 85,
                    ]
                ],
            ]),
        ];
    }
    
    private function extractPrompt($body) {
        // 从 Yoast 请求体中提取 prompt
        return $body['prompt'] ?? $body['content'] ?? '';
    }
    
    private function buildPrompt($prompt, $context) {
        $enhanced = "为以下内容生成 SEO 优化的标题和描述：\n\n";
        $enhanced .= "内容：{$prompt}\n";
        
        if (!empty($context['keywords'])) {
            $enhanced .= "关键词：" . implode(', ', $context['keywords']) . "\n";
        }
        
        return $enhanced;
    }
}
```

#### 可行性评估

| 插件 | 可行性 | 难度 | 备注 |
|-----|-------|------|------|
| **Yoast SEO Pro** | 🟡 中等 | 中 | 输入输出相对简单 |
| **Rank Math** | 🟡 中等 | 中 | 积分系统需要绕过 |
| **Elementor AI** | 🔴 低 | 高 | 响应格式极其复杂 |

---

### 2.3 Layer 3：UI 注入层（备选方案）

#### 适用场景
- 无法通过 HTTP 层覆盖的插件
- 用户希望明确选择使用 WPMind

#### 技术实现

```javascript
// 在 Yoast Meta Box 旁边注入 WPMind 按钮
jQuery(document).ready(function($) {
    // 找到 Yoast 的 AI 按钮
    const yoastAiBtn = $('.yoast-ai-generate-btn');
    
    if (yoastAiBtn.length) {
        // 在旁边添加 WPMind 按钮
        const wpmindBtn = $('<button>')
            .addClass('button wpmind-generate-btn')
            .text('使用 WPMind 生成')
            .insertAfter(yoastAiBtn);
        
        wpmindBtn.on('click', function(e) {
            e.preventDefault();
            
            // 获取当前内容
            const content = $('#content').val() || wp.data.select('core/editor').getEditedPostContent();
            
            // 调用 WPMind API
            $.ajax({
                url: wpmind.ajax_url,
                method: 'POST',
                data: {
                    action: 'wpmind_generate_seo',
                    content: content,
                    nonce: wpmind.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        // 填入 Yoast 输入框
                        $('#yoast_wpseo_title').val(response.data.title);
                        $('#yoast_wpseo_metadesc').val(response.data.description);
                    }
                }
            });
        });
    }
});
```

---

## 3. 商业插件请求格式调研

### 3.1 调研方法

1. **嗅探模式**：启用后记录所有 AI 相关请求
2. **代码审计**：分析插件 PHP 源码
3. **社区贡献**：收集用户反馈

### 3.2 已知信息（通过 Gemini CLI 调研）

#### Yoast SEO Pro
- **请求格式**：标准 JSON，包含 prompt 和 context
- **响应格式**：`{ suggestions: [{ title, score }] }`
- **验证机制**：检查账户连接状态，可能有 License 验证

#### Rank Math Content AI
- **请求格式**：标准 JSON，包含关键词、上下文
- **响应格式**：复杂的结构化数据（关键词、大纲、问题等）
- **验证机制**：积分系统，本地 UI 显示余额

#### Elementor AI
- **请求格式**：复杂，包含 DOM 结构和样式数据
- **响应格式**：HTML/CSS 片段或 Elementor JSON
- **验证机制**：Elementor Connect 深度集成

---

## 4. 实现路线图

### Phase 1：基础设施（第 1 周）

| 任务 | 产出 |
|-----|------|
| 实现嗅探模式 | `AISniffer.php` |
| 嗅探数据查看界面 | 管理后台页面 |
| 收集 Yoast/Rank Math 样本 | 格式文档 |

### Phase 2：OpenAI 兼容层（第 2 周）

| 任务 | 产出 |
|-----|------|
| OpenAI Chat API 转发 | `OpenAICompatible.php` |
| OpenAI Images API 转发 | 图像生成支持 |
| 测试 AI Engine 兼容性 | 测试报告 |

### Phase 3：覆盖转发适配器（第 3-4 周）

| 任务 | 产出 |
|-----|------|
| Yoast SEO 适配器 | `YoastAdapter.php` |
| Rank Math 适配器 | `RankMathAdapter.php` |
| 兼容性测试 | 测试报告 |

### Phase 4：UI 注入层（第 5 周）

| 任务 | 产出 |
|-----|------|
| Yoast 按钮注入 | JS 模块 |
| Rank Math 按钮注入 | JS 模块 |
| 用户体验优化 | |

---

## 5. 风险与应对

| 风险 | 影响 | 应对策略 |
|-----|------|---------|
| 厂商更新 API 格式 | 适配器失效 | 版本化适配器 + 快速响应 |
| 插件检测到覆盖 | 功能被禁用 | 可配置的"透明模式" |
| 法律/ToS 风险 | 潜在纠纷 | 明确告知用户风险 |
| 维护成本高 | 资源消耗 | 社区贡献机制 |

---

## 6. 竞争优势

### 6.1 与其他方案的对比

| 方案 | WPMind Gateway | OpenRouter | 本地 Ollama |
|-----|---------------|------------|------------|
| 定位 | WordPress 专用 | 通用网关 | 本地部署 |
| 商业插件支持 | ✅ 覆盖转发 | ❌ 需手动配置 | ❌ 需手动配置 |
| 中国服务商 | ✅ 原生支持 | ❌ 有限 | ✅ 可接入 |
| 安装难度 | 🟢 低 | 🟡 中 | 🔴 高 |

### 6.2 核心价值主张

> **"让每个 WordPress 插件的 AI 功能，都能使用您选择的服务商"**

---

*文档结束*
