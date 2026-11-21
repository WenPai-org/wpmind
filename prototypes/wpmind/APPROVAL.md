# WPMind（文派心思）4.0 后台原型审批单

- **状态**：待人审
- **组件库**：`wenpai-admin-ui` `v0.13.7`
- **生成方式**：`php prototypes/wpmind/build.php`（单一真源：直接复用 `templates/admin/page.php`、各视图模板与模块模板）
- **一致性校验**：`php prototypes/wpmind/build.php --check`
- **静态检查**：`python3 ~/Projects/linuxjoy/scripts/wenpai/wenpai-admin-lint.py --prototype-dir ~/Projects/wpmind/prototypes/wpmind`

## 生成文件清单（12 页）

| 文件 | 对应界面 | 审查重点 |
|---|---|---|
| `index.html` | 原型索引页 | 全部 11 个核心与模块视图的离线导航目录 |
| `overview.html` | 概览 | v0.13.7 四段式布局：`stat_hub` + 抽屉 + 双列 `view_tabs`（各插件调用 / 近期活动 + 测速 / 额度）+ 底部模块 `fold_list` |
| `services.html` | AI 服务 | 顶部「文派心思官方算力」卡、URL 驱动简化/高级模式、文本与图像 `fold_list`、站点锁定态、底部 `savebar` |
| `routing.html` | 智能路由（二级页） | `subpage_head`（1120px 满宽）+ `seg_radio` 策略切换 + 故障转移链 + `sortable` 优先级 + `meter` 排名 |
| `modules.html` | 模块 | 已开启 `module_grid` + 未开启 `row` 清单 + 授权徽标过滤口 |
| `module-geo.html` | 模块：GEO 优化 | URL 驱动 6 分区 `subnav` + 爬虫 `statbar` + `llms.txt` / AI Sitemap / Schema / 品牌实体 / 爬虫管理 |
| `module-gateway.html` | 模块：API 网关 | URL 驱动 4 分区 `subnav` + `statbar` + 密钥管理 + OpenAI 兼容端点文档 + 审计日志 |
| `module-cache.html` | 模块：精确缓存 | `statbar` + 7 天趋势 `chart_frame` + 参数设置 + kit 确认门清空/重置 |
| `analytics.html` | 分析 | 基础用量统计 + URL 驱动 `charts`/`budget` `subnav` + 4 张 `chart_frame` |
| `workflow.html` | 任务 | `steps` 5 步流 + `progressbar` + `statbar` + 任务列表与审核明细确认门 |
| `tools.html` | 工具 | 诊断包、端点导出、两步确认导入与版本信息 |
| `help.html` | 帮助 | 快捷文档入口与折叠式常见问题排查步骤 |

## 审阅记录

- 待审阅人确认视觉层级、信息密度、文案语气与 kit 组件选型后，更新本页状态为「已批准」。
