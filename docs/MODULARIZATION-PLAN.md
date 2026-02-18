# WPMind 模块化方案

> 基于 Codex 代码分析的模块化评估报告

## 一、核心 vs 模块化划分

### 保持核心（始终加载）

| 功能 | 代码位置 | 理由 |
|------|---------|------|
| Provider 管理与注册 | `wpmind.php:82`, `includes/Providers/register.php` | 插件最小可用集，决定 AI 能力与 WP AI Client 对接 |
| 公共 API 调用链路 | `includes/API/PublicAPI.php:260` | 核心请求处理流程 |
| 故障转移与健康跟踪 | `includes/Failover/FailoverManager.php` | 运行时可靠性核心 |
| 模块加载框架 | `includes/Core/ModuleLoader.php`, `ModuleInterface.php` | 模块化基础设施 |

### 建议模块化（可开关/可拆包）

| 模块 | 代码位置 | 理由 |
|------|---------|------|
| **用量追踪 + 成本估算** | `wpmind.php:876`, `includes/Usage/UsageTracker.php` | 非必需但影响性能/隐私，适合做 Telemetry 模块 |
| **预算管理** | `includes/Budget/BudgetChecker.php:192`, `templates/tabs/budget.php` | 与运行核心解耦，应独立为 Cost Control 模块 |
| **分析面板** | `includes/Analytics/AnalyticsManager.php`, `templates/tabs/dashboard.php` | 报表与可视化，依赖 Usage 数据 |
| **智能路由** | `includes/Routing/IntelligentRouter.php`, `templates/tabs/routing.php` | 目前仅后台展示，未进入运行时 provider 选择链路 |

### 已模块化

| 模块 | 状态 | 备注 |
|------|------|------|
| **GEO 优化** | ✅ 已完成 | `modules/geo/` |
| **Cost Control** | ✅ 已完成 | `modules/cost-control/` - 用量追踪 + 预算管理 |
| **Analytics** | ✅ 已完成 | `modules/analytics/` - 分析仪表板 |

## 二、新功能候选（不与官方 AI 插件冲突）

WordPress 官方 AI 插件专注于：**内容生成、图像生成、摘要、翻译**

### 优先建议

| 功能 | 价值 | 冲突风险 | 说明 |
|------|------|---------|------|
| **AI 内容审核** | ⭐⭐⭐ | 低 | 评论垃圾/敏感内容检测，提升站点安全与质量 |
| **AI 图像 Alt** | ⭐⭐⭐ | 低 | 自动生成 alt 文本，提升可访问性与 SEO |
| **AI 可读性分析** | ⭐⭐ | 低 | Flesch 评分、阅读时间，本地算法 + AI 解释 |

### 可选但需谨慎

| 功能 | 风险 | 建议 |
|------|------|------|
| **AI SEO 助手** | 与现有 SEO 插件竞争 | 只做"建议/草稿"，避免自动写入 |
| **AI 安全监控** | 误报成本高 | 先做"审计/提示"而非拦截 |

## 三、模块化优先级

```
P0: Cost Control（用量追踪 + 预算管理）
    ↓ 其他模块依赖的底座，当前在核心链路直接调用

P1: 分析面板模块
    ↓ 依赖 Usage 数据，完成后可单独启用/禁用 UI

P2: 高级路由模块
    ↓ 需接入 wpmind_select_provider filter，保留 Failover 兜底

并行: 清理 GEO 双实现（避免模块与旧实现并存）
```

## 四、架构改进建议

### 1. 模块依赖标准化
- ModuleLoader 应读取 `settings_tab`、`requires`、`dependencies`
- 做依赖校验/禁用提示
- 见 `includes/Core/ModuleLoader.php:103`, `modules/geo/module.json`

### 2. 动态设置页
- 核心 tabs + 模块 tabs 合并渲染，避免硬编码
- GeoModule 已注册 `wpmind_settings_tabs`，但设置页仍是静态列表
- 见 `modules/geo/GeoModule.php:110`, `templates/settings-page.php:51`

### 3. 事件化解耦
- `track_token_usage` 改为触发 `wpmind_usage_recorded` action
- Usage/Budget/Analytics 模块订阅事件
- 避免核心直接依赖预算/用量类
- 见 `wpmind.php:876`

### 4. 路由接入真实请求
- IntelligentRouter 挂到 `wpmind_select_provider` filter
- 保留 Failover 链路作为兜底
- 见 `includes/API/PublicAPI.php:281`, `includes/Routing/IntelligentRouter.php:186`

### 5. 按需加载资源
- Chart.js、routing CSS 仅在相关模块启用时加载
- 当前在 `wpmind.php:356` 全局加载

### 6. 清理 GEO 双实现
- 保留 `modules/geo` 作为唯一实现
- 旧 `includes/GEO` 改为兼容别名或移除
- 同步更新测试与模板引用

## 五、目标目录结构

```
wpmind/
├── wpmind.php                 # 核心入口
├── includes/
│   ├── Core/                  # 核心框架（不可禁用）
│   │   ├── ModuleInterface.php
│   │   └── ModuleLoader.php
│   ├── API/                   # API 层（不可禁用）
│   ├── Providers/             # Provider 管理（不可禁用）
│   └── Failover/              # 故障转移（不可禁用）
└── modules/                   # 可选模块
    ├── geo/                   # ✅ 已完成
    ├── cost-control/          # 📋 P0 - 用量追踪 + 预算管理
    ├── analytics/             # 📋 P1 - 分析面板
    ├── advanced-routing/      # 📋 P2 - 智能路由
    ├── content-moderation/    # 📋 新功能 - AI 内容审核
    └── image-alt/             # 📋 新功能 - AI 图像 Alt
```

## 六、实施计划

### 阶段 1：清理与基础 ✅ 已完成
- [x] GEO 模块化完成
- [x] 清理 GEO 双实现
- [x] 完善 ModuleLoader 依赖管理

### 阶段 2：Cost Control 模块 ✅ 已完成
- [x] 抽离 UsageTracker 到模块
- [x] 抽离 Budget 相关类到模块
- [x] 核心通过事件触发用量记录

### 阶段 3：分析与路由模块 ✅ 已完成
- [x] 分析面板模块化
- [x] 智能路由接入真实请求（RoutingHooks 集成 wpmind_select_provider filter）

### 阶段 4：新功能模块
- [ ] AI 内容审核模块
- [ ] AI 图像 Alt 模块

---

*文档生成时间: 2026-02-05*
*基于 Codex 代码分析 (202,206 tokens)*
