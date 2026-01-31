# WPMind 更新日志

## [1.6.0] - 2026-01-31

### ✨ 新功能
- **Token 用量统计**: 自动追踪每次 AI 请求的 token 用量
  - 记录 input/output tokens
  - 自动计算费用估算（基于各服务商定价）
  - 今日/本月/总计统计
  - 按 Provider 和模型分类统计
- **用量统计面板**: 设置页面新增用量统计卡片
  - 实时显示 tokens、费用、请求数
  - 支持刷新和清除统计

### 📁 新增文件
```
includes/Usage/
└── UsageTracker.php    # 用量追踪核心类
```

### 🔧 技术改进
- 支持 11 个服务商的定价配置
- 自动清理 30 天前的日统计和 12 个月前的月统计
- 历史记录最多保留 1000 条

---

## [1.5.3] - 2026-01-31

### 🎨 UI 改进
- **WordPress 原生通知样式**: Toast 通知改用 WordPress 原生 `.notice` 类
- 支持 `notice-success/error/warning/info` 四种类型
- 使用 `slideDown/slideUp` 动画效果
- 移除自定义 Toast CSS 样式，保持与 WordPress 后台一致

---

## [1.5.2] - 2026-01-31

### 🐛 修复 (Codex 审查)
- **时间窗口过滤**: CircuitBreaker 现在只统计最近 60 秒内的请求计算失败率
- **类型安全**: `getData()` 和 `getAllHealth()` 添加 `is_array()` 类型检查
- **半开状态计数**: `recordFailure()` 在半开状态下正确递增 `half_open_failures`
- **延迟计算修复**: ProviderHealthTracker 使用 `foreach` 替代有问题的 `array_filter`
- **XSS 防护**: Toast 组件使用 `.text()` 方法防止 XSS 攻击

### 🎨 UI 改进
- **自定义 Dialog**: 使用自定义确认对话框替代浏览器原生 `confirm()`
- **事件委托**: 使用 `$(document).on()` 修复动态元素点击问题
- 移除所有 `alert()` 和 `confirm()` 调用

---

## [1.5.1] - 2026-01-31

### 🐛 修复
- **Transient TTL 修复**: 将熔断器状态存储时间从 600s 增加到 2400s，避免状态过早重置
- **状态查询优化**: 新增 `isAvailableReadOnly()` 方法，状态查询不再触发状态转换
- **实际请求集成**: 故障转移现在集成到实际 AI 请求流程，不仅限于测试连接
- **双重计数修复**: 测试连接请求添加 `_wpmind_skip_tracking` 标记，避免与 `http_api_debug` 钩子双重计数
- **自定义 URL 支持**: `identify_provider_from_url()` 现在支持用户自定义的 base_url

### 🔧 技术改进
- `filter_preferred_models` 现在会排除熔断中的 Provider（使用只读方法）
- 新增 `http_api_debug` 钩子追踪 AI 请求结果
- 新增 `identify_provider_from_url()` 方法识别请求目标，支持 11 个 Provider
- 添加百度和 MiniMax 的默认域名模式

---

## [1.5.0] - 2026-01-31

### ✨ 新功能
- **故障转移机制**: 实现双层故障转移架构
  - Layer 1: 软故障转移 - 单次请求失败时自动重试
  - Layer 2: 熔断器 - 持续故障时自动切换到备用服务
- **Circuit Breaker 熔断器**: 三状态模型 (Closed/Open/Half-Open)
  - 连续失败 5 次或失败率 > 40% 触发熔断
  - 20 分钟后自动恢复探测
- **Provider 健康追踪**: 记录每个服务的成功率和延迟
- **AJAX API**: 新增获取状态和重置熔断器接口

### 📁 新增文件
```
includes/Failover/
├── CircuitBreaker.php          # 熔断器核心类
├── ProviderHealthTracker.php   # 健康状态追踪
└── FailoverManager.php         # 故障转移管理器
```

### 🔧 技术改进
- 基于 Salesforce Agentforce 实践的参数配置
- 使用 WordPress transient 存储状态
- 模块化设计，可独立禁用

---

## [1.4.0] - 2026-01-31

### ✨ 新功能
- **新增百度文心供应商**: 支持 ERNIE-4.0、ERNIE-3.5 等模型
- **新增 MiniMax 供应商**: 支持 abab6.5s-chat、abab6.5-chat 等模型
- **集成 lobe-icons**: 使用 LobeHub 提供的 AI 供应商图标
- 为所有供应商添加 `icon` 配置字段
- **完整 Provider 类实现**: 为百度和 MiniMax 创建完整的 Provider 架构
  - `includes/Providers/Baidu/` - 百度 ERNIE Provider
  - `includes/Providers/MiniMax/` - MiniMax Provider

### 🎨 UI 改进
- 使用 CDN 加载供应商图标 (npmmirror.com)
- 图标加载失败时自动回退到 dashicons
- 优化图标显示样式
- 使用英文产品名称作为显示名称 (Qwen, ChatGLM, Doubao, SiliconFlow, ERNIE)

### 🔧 技术改进
- 添加百度和 MiniMax 的 API Key 验证规则
- 更新供应商配置结构，支持自定义图标
- 更新 ProviderRegistrar 支持 8 个国内 Provider

### 🛡️ 错误处理增强
- **新增 ErrorHandler 类**: 统一的错误消息映射和用户友好的错误反馈
- **HTTP 状态码映射**: 将 HTTP 错误码转换为用户友好的中文消息
- **Provider 特定错误提示**: 针对不同供应商提供具体的错误解决建议
- **自动重试机制**: 对可重试的错误（429、500、502、503、504）自动重试最多 2 次
- **指数退避**: 重试间隔采用指数退避策略（1s、2s、4s...）
- **详细错误信息**: 前端显示更详细的错误信息，支持 hover 查看详情

### 📝 供应商图标映射
| 供应商 | 图标 |
|--------|------|
| OpenAI | openai |
| Anthropic | claude |
| Google AI | gemini |
| DeepSeek | deepseek |
| 通义千问 | qwen |
| 智谱 AI | zhipu |
| Moonshot | kimi |
| 豆包 | doubao |
| 硅基流动 | siliconcloud |
| 百度文心 | wenxin |
| MiniMax | minimax |

---

## [1.3.1] - 2026-01-31

### 🐛 Bug 修复
- 修复高级设置按钮无响应问题(通过版本号更新强制缓存刷新)
- 修复 HTML 验证错误: label 的 `for` 属性指向不存在的 input ID
- **修复样式丢失问题**: 更新 hook suffix 从 `settings_page_wpmind` 到 `toplevel_page_wpmind`

### ✨ 功能改进
- **提升为一级菜单**: WPMind 现在是 WordPress 后台的一级菜单,名称为"心思"
- **优化服务显示名称**: 使用更友好的产品名称(如 ChatGPT、Claude、Gemini)替代技术 ID
- **统一 API Key 管理**: 所有 AI 服务(包括官方服务)现在都在 WPMind 中直接管理
- 移除"由 WordPress AI Client 管理"的跳转提示
- 官方服务的 API Key 会自动同步到 WordPress AI Client
- 简化连接测试按钮逻辑,所有服务统一处理

### 🎨 UI/UX 改进
- 菜单图标: 使用心形图标 (dashicons-heart)
- 菜单位置: 位于 WordPress 后台左侧菜单栏第 30 位
- 服务标识: 显示产品名称而非技术 ID
  - openai → ChatGPT
  - anthropic → Claude
  - google → Gemini
  - moonshot → Kimi
  - zhipu → 智谱清言
  - doubao → 豆包

### 🔧 技术改进
- 添加 JavaScript 调试日志,便于问题诊断
- 优化高级设置切换功能的控制台输出
- 改进代码注释和文档

### 📝 技术细节
- 更新插件版本号从 1.3.0 到 1.3.1
- 将 `add_options_page()` 改为 `add_menu_page()` 创建一级菜单
- 为每个服务添加 `display_name` 字段
- 在 `initAdvancedToggle()` 函数中添加调试信息
- 修改 `settings-page.php` 模板,移除官方服务的特殊处理
- 所有服务统一使用 API Key 输入界面

---

## [1.3.0] - 2026-01-26

### ✨ 新增功能
- 完整的 Provider 架构实现
- 6 个国内 AI 服务 Provider (DeepSeek、Qwen、Zhipu、Moonshot、Doubao、SiliconFlow)
- 3 个官方服务支持 (OpenAI、Anthropic、Google AI)
- WordPress AI 原生功能支持(标题生成已验证)
- API Key 格式验证(9 种服务的正则表达式验证)
- 连接测试功能
- 自定义 Base URL 支持
- 折叠式高级设置界面

### 🔧 技术改进
- AuthenticatedProviderAvailability - 解决 API Key 认证传递问题
- AbstractOpenAiCompatibleTextGenerationModel - 强制 n=1 适配国内 API
- pre_option_ filter - 合并 WPMind 凭据到 AI Client
- 实时 API Key 格式验证
- AJAX 连接测试

### 🐛 已解决的问题
- Provider 注册时机问题(改用 init 钩子)
- HTTP transporter 未初始化问题
- candidateCount 限制导致模型被过滤问题
- API n 参数不支持多候选问题
