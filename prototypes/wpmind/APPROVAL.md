# WPMind（文派心思）4.0 后台原型审批单

- **状态**：已批准（2026-11-22）
- **组件库**：`wenpai-admin-ui` `v0.13.7`
- **生成方式**：`php prototypes/wpmind/build.php`（单一真源：直接复用 `templates/admin/page.php`、各视图模板与模块模板）
- **一致性校验**：`php prototypes/wpmind/build.php --check`（13 份 HTML 与 PHP 模板一致）
- **静态检查**：`wenpai-admin-lint.py --strict`（插件 0 findings，原型 13 HTML 0 findings）；`wenpai-admin-inventory.py --check`（`PASS wpmind`，`own_families=8`，`raw_spacing=0`，`raw_hex=0`，`inline_style=0`）

## 生成文件清单（13 页）

| 文件 | 对应界面 | 审查重点 |
|---|---|---|
| `index.html` | 原型索引页 | 全部 12 个核心与模块视图的离线导航目录 |
| `overview.html` | 概览 | 内缩四格带蓝图标统计条 + AI 服务动态对称双栏（Provider 健康 + 单行最近活动）+ 底部卡级模块健康折叠 + 条件化生态插件调用折叠 |
| `cloud.html` | AI 服务 · 心思 AI（默认首屏） | 四格点数状态条 + 官方算力开关与三档混用策略（`全托管优先` / `自备优先 · 心思备援` / `按能力补充`）+ 方案 A 双路径绑定（WPCY 优先 / 独立站网页授权回跳）与网站充值入口 + 两张折叠式换算与生态托管卡 |
| `services.html` | AI 服务 · Provider 服务 | 渐进式披露：已配置文本/图像 Provider 置顶，未配置服务默认收纳折叠，支持简单/高级模式与底部 `savebar` |
| `routing.html` | AI 服务 · 智能路由 | 顶部 `seg_radio` 策略切换 + 独立 `wpmind_routing_settings` 路由参数卡 + 运行状态（当前路由与故障转移链）+ `sortable` 优先级排序与 `meter` 排名 |
| `modules.html` | 模块 | 上方内置模块三列 `module_grid` 卡片 + 未启用模块清单 + 下方「生态联动插件」列表卡 |
| `module-geo.html` | 模块：GEO 优化 | URL 驱动 6 分区 `subnav` + 爬虫 `statbar` + `llms.txt` / AI Sitemap / Schema / 品牌实体 / 爬虫管理 |
| `module-gateway.html` | 模块：API 网关 | URL 驱动 4 分区 `subnav` + `statbar` + 密钥管理 + OpenAI 兼容端点文档 + 审计日志 |
| `module-cache.html` | 模块：精确缓存 | `statbar` + 7 天趋势 `chart_frame` + 参数设置 + kit 确认门清空/重置 |
| `analytics.html` | 分析 | 基础用量统计 + URL 驱动 `charts`/`budget` `subnav` + 4 张 `chart_frame` |
| `workflow.html` | 任务 | `steps` 5 步流 + `progressbar` + `statbar` + 任务列表与审核明细确认门 |
| `tools.html` | 工具 | 诊断包、端点导出、两步确认导入与版本信息 |
| `help.html` | 帮助 | 快捷文档入口与折叠式常见问题排查步骤 |

## 审阅记录

- **2026-11-22**：审阅人（feibisi）于本地 `:8894` 实机验收并通过全部核心视图与交互结构，明确拍板以下决策：
  1. 商业计费单位定名为「心思点数」，官方商业网关域名为 `mind-api.wenpai.net`。
  2. 概览页与智能路由页按 2026-11-16 过审原型布局定型；模块页保留内置 `module_grid` 并新增「生态联动插件」列表卡；Provider 服务页对未配置服务默认折叠收纳。
  3. AI 服务子导航优先级调整为 `心思 AI | Provider 服务 | 智能路由`，顶栏「AI 服务」默认进入「心思 AI」；心思 AI 首屏精简为四格状态条 + 主配置卡 + 两张折叠参考卡。
  4. 绑定与充值采用方案 A：已安装 WPCY 4.0 时统一由 WPCY 托管绑定；独立 WPMind 站点通过 `wenpai.net/connect/wpmind` 网页授权回跳签发令牌（高级模式支持手工粘贴令牌）；点数充值、买包与国内发票统一跳转 `wenpai.net/mind-credits`。
- **结论**：状态更新为 **已批准**，允许合并 `feat/admin-4.0-shell` 至 `main`。
