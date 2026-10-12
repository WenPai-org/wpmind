# WPMind HANDOFF

## 2026-06-08 - linuxjoy 接管工作

- [CX] 新增 `docs/ai-context/elementor-ai-bridge.md`，整理 WPMind × WPCY Connector × Elementor AI 转接完整方案。
- 文档结论：WPMind 作为 AI provider/router 核心，wpcy-template-connector 作为 Elementor 专用 adapter，CF Worker 作为可选边缘鉴权/限流/路由层。
- 未修改 PHP 代码，未运行代码测试；本次仅文档落地。

## 2026-06-08 - linuxjoy 接管工作

- [CX] 查 wenpai VM Claude Code 配置，确认 DeepSeek 使用 Anthropic-compatible endpoint：`https://api.deepseek.com/anthropic`。
- [CX] 用 `deepseek-v4-flash` 成功调用 `/v1/messages`，并生成 Elementor AI layout 响应。
- 第一次生成 widget 漏 `elements: []`；加强 prompt 后通过基础结构校验，输出保存在 `/tmp/deepseek_elementor_ai_response_strict.json`。
- 结论：DeepSeek 可用于 layout 生成，但必须加 schema validator 和 normalizer。

## 2026-08-09 [CX] WPMind 0.12 单插件稳定化第一阶段

- 分支：`feat/v012-single-plugin-stabilization`，隔离 worktree：`/home/parallels/Projects/wpmind-cx-012`。
- 产品边界：保持一个 WPMind 插件；本地功能不再拆 Free/Pro/Add-on，高级能力作为插件内模块按需启用。
- 版本统一为 0.12.0；新增 `readme.txt`，重写 `WPMIND-ROADMAP.md` 为 0.12→1.0 单插件路线。
- 新安装默认启用 cost-control、exact-cache；API Gateway、GEO、Auto Meta、Media Intelligence、Analytics 默认关闭。旧站点首次升级会保留原来的隐式启用行为，避免升级后功能突然中断。
- 修复 API Gateway 总开关：关闭时不初始化表、不注册 REST 路由和 SSE 审计；REST 注册处有二次检查。
- Transient 限流锁失败改为拒绝请求；Connector 密钥副本会同步更新或在清空时删除；卸载覆盖网关、GEO、模块和 Connector 数据。
- 移除无功能约束的授权页、授权 AJAX 和 `WenPai_License`；删除重复 CI 与两个引用不存在脚本的定时工作流。
- `deploy.sh` 默认 dry-run，实际写入必须传 `--apply`，并排除 vendor/node_modules/tests 等开发文件。
- 验证：Composer check 通过（PHP 语法 176 文件、PHPUnit 48 tests/76 assertions、composer audit 无已知漏洞）；npm test 通过；composer validate --strict 通过；deploy.sh bash 语法通过；git diff --check 通过。
- 未执行：未部署到 wpcy.com，未推送远端，未运行浏览器 E2E，未建立 PHPCS/PHPStan 基线。

## 2026-08-09 [CX] WordPress 7.1 / Gutenberg 23.6 全插件审计

- 审计报告：`docs/audits/wpmind-wp71-gutenberg236-audit-2026-08-09.md`，覆盖全部模块、WordPress 7.0/7.1、Gutenberg 23.6、AI Client、Connectors、Abilities/MCP、媒体处理和协作保存。
- 发现三个 P0：全局 `wp_ai_client_prevent_prompt` 会阻断第三方 Provider；MCP `create_server()` 参数与当前 Adapter 不匹配且改名共享默认服务器；Provider Registry 与手工 Connector 双重注册/密钥同步会覆盖 Core 凭据来源。
- Gutenberg 不是大量既有 block 冲突，而是插件几乎没有编辑器集成；GEO 仍以传统 meta box 为主。建议先做 Editor Sidebar、Command Palette 和任务状态，不先做自定义 AI block。
- 产品边界保持单插件、无 Pro/Add-on；Provider、路由、预算/审计、内容与媒体能力保留，重复后台、手工 Connector、默认 MCP 改名和静默 HTTP 旁路应删除或合并。
- 验证：`composer check` 通过（PHP lint 176 文件、PHPUnit 48 tests/76 assertions、composer audit 无已知漏洞）。PHPStan 未安装；项目 wp-env 包装脚本损坏，直接镜像启动未完成，因此未声称 WordPress 7.1/Gutenberg 真实运行通过。
