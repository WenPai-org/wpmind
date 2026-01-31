# WPMind 更新日志

## [1.4.0] - 2026-01-31

### ✨ 新功能
- **新增百度文心供应商**: 支持 ERNIE-4.0、ERNIE-3.5 等模型
- **新增 MiniMax 供应商**: 支持 abab6.5s-chat、abab6.5-chat 等模型
- **集成 lobe-icons**: 使用 LobeHub 提供的 AI 供应商图标
- 为所有供应商添加 `icon` 配置字段

### 🎨 UI 改进
- 使用 CDN 加载供应商图标 (npmmirror.com)
- 图标加载失败时自动回退到 dashicons
- 优化图标显示样式

### 🔧 技术改进
- 添加百度和 MiniMax 的 API Key 验证规则
- 更新供应商配置结构，支持自定义图标

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
