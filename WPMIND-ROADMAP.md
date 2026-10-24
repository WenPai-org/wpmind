# WPMind 产品路线图

> 一个插件连接国内外 AI，并在同一设置中心提供内容、媒体、GEO、成本与网关功能。

*路线图版本：0.14 | 更新日期：2026-10-24*

## 产品规则

1. 用户只安装一个 WPMind，不再发布 Free/Pro/Add-on 多套插件。
2. 所有本地功能进入同一代码库；高级功能以内置模块形式按需启用。
3. 新安装只默认开启 Provider、成本控制和基础缓存，外部路由与内容自动化必须显式开启。
4. 本地功能不通过 Pro/Enterprise 授权锁定；商业收入来自托管调用、多站点服务、交付和支持。
5. 优先兼容 WordPress AI Client SDK、Abilities 与 MCP Adapter，不重复维护通用协议层。

## 核心定位

WPMind 是面向中国使用环境的 WordPress AI Provider Bridge：

- 统一接入 DeepSeek、通义、智谱、Moonshot、豆包、SiliconFlow 等 Provider；
- 支持 BYOK、预算、缓存、故障转移和调用记录；
- 在同一插件内提供 Meta、Alt Text、GEO、图片与 API Gateway；
- 自动操作默认关闭，并提供预览、审核、撤销和成本提示。

## 功能分层

### 默认启用

- Provider 接入与连接测试
- Public API / WordPress AI Client 兼容
- 成本控制、预算和基础用量
- 精确缓存与简单顺序故障转移

### 内置、默认关闭

- API Gateway
- GEO
- Auto Meta
- Media Intelligence 与图片生成
- Analytics
- MCP / Abilities

内部模块只用于控制加载、权限和数据迁移，不形成独立安装包。

## 版本计划

### 0.12 稳定化

- [x] 统一插件、npm 与测试版本号
- [x] 新安装高级模块默认关闭，旧站点保持原有启用状态
- [x] API Gateway 关闭时不注册公共 REST 路由
- [x] 限流锁失败时拒绝请求
- [x] Connector 密钥同步与卸载清理
- [x] 删除无实际功能约束的 Pro/Enterprise 授权入口
- [x] 部署脚本默认 dry-run，排除开发依赖
- [x] 补齐后台模块开关与升级提示的浏览器验收（2026-10-24，8941 Playwright modules-toggle+upgrade 11/11；验收 tip `7983ebf`，main 已至 `83c02e0`）
- [x] 完成完整 PHPCS/PHPStan 基线

### 0.13 Provider 兼容

- [x] 动态模型能力目录，移除退役模型硬编码（#42，main `a077071`）
- [x] WordPress AI Client SDK 兼容矩阵（#43，main `2a62b6c`；含 compat-matrix / Site Health）
- [x] Provider 健康检查和明确的顺序回退（#45，main `f7f508e`）
- [x] API Key 安全轮换（#47，main `6977ece`）
- [x] 旧端点配置导入（#50，main `72e3401`；无密钥迁移）
- [x] 错误诊断（#50，main `72e3401`；ErrorCatalog / 诊断包脱敏 / Site Health）

### 0.14 可见工作流

- [x] 批量 Meta：预估成本（薄）、dry-run、人工审核、写入、撤销（#54/#55，main `54fec5e`）
- [x] 批量 Alt Text：筛选、预览、人工审核、写入、撤销（#52/#53，main `54fec5e`）
- [x] 统一任务记录，不为每个模块单独建设统计页面（#52，CPT `wpmind_task`；main `54fec5e`）

### 1.0

满足以下条件后发布：

- 至少 3–5 个真实站点完成安装、升级和回滚验证；
- Provider 失效、额度不足、超时和密钥轮换均有明确恢复路径；
- Gateway、GEO 和自动化功能默认关闭且可彻底停止运行；
- 发布包、升级包和卸载路径有自动化验收记录。

## 商业化

WPMind 不销售功能残缺的免费版，也不维护另一套 Pro 插件。

- **BYOK 与本地功能**：同一插件内完整提供。
- **托管 AI**：预付额度或按量结算，不承诺无限调用。
- **多站点服务**：集中预算、审计、模型策略和故障告警。
- **企业交付**：私有部署、迁移、合规配置、SLA 与技术支持。
- **WenPai 会员**：更新支持、服务额度和交付优惠，不锁定插件本地功能。

## 验证指标

- 安装后首次成功调用率
- 七日仍有成功调用的站点比例
- 自动故障转移成功率
- 批量任务人工审核通过率与撤销率
- 每个活跃站点的支持工单量
- 托管服务付费转化和毛利

“支持 Provider 数量”只作为兼容范围，不作为核心成功指标。

## 当前假设

- 用户更愿意维护一个插件，而不是安装多个扩展：已由产品负责人确认。
- 国内 Provider 兼容、成本控制和故障恢复具有付费价值：尚需客户和使用数据验证。
- 托管调用、多站点服务与交付支持可以覆盖持续维护成本：尚需定价实验验证。
