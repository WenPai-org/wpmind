# WPMind 后台重构方案（v2）

> 目标：先修复已知 Bug，再完成编码规范化，最后按需进行结构重构。每阶段独立可验收、可回退。

## 1. 背景与教训

### 1.1 当前基线

- 稳定版本：`f5a11cf`（回滚 7 次 camelCase→snake_case 提交）
- 基线代码：`b457532`（重构前最后稳定版）

### 1.2 上次失败复盘

| 问题 | 原因 |
|------|------|
| 方法重命名不完整 | 并行 agent 只改了部分定义，漏掉 ~50 个私有方法 |
| 调用点遗漏 | 模板中的静态调用、跨文件调用未被搜索到 |
| Chart.js 级联故障 | CDN 被墙 + 作为 admin.js 硬依赖 → 全部 JS 失效 |
| Tab 切换失效 | CSS class 名不匹配（`active` vs `wpmind-tab-pane-active`） |
| 无法验证 | 没有测试手段，只能部署后人工发现问题 |

**核心教训：做得太多，验证太少。**

### 1.3 当前代码规模

| 文件 | 规模 | 说明 |
|------|------|------|
| `admin.js` | 1,791 行 / 105 函数 / 52 AJAX | 前端全部逻辑 |
| `wpmind.php` | 1,685 行 / 13 AJAX handler | 35% 是 admin 逻辑 |
| 内嵌脚本模板 | 2 个 | modules.php, cost-control/settings.php |
| Chart.js | CDN-only | cloudflare，国内不可用（需本地化） |

### 1.4 范围

- 后台管理页面（`admin.php?page=wpmind`）相关前端与后台逻辑
- 不含：前台公共 API、Provider 逻辑、路由核心算法

---

## 2. 重构目标（优先级排序）

1. **修复已知 Bug**：Chart.js CDN 被墙、Tab class 不匹配
2. **编码规范化**：camelCase → snake_case 方法重命名（WordPress 标准）
3. **稳定性**：任意模块 JS 失败不影响其他模块
4. **可维护性**：后台 JS/PHP 职责清晰，减少耦合
5. **可回滚性**：每阶段可独立验收与回退

---

## 3. 决策项（已确定）

| 决策 | 选择 | 理由 |
|------|------|------|
| 构建工具 | **不引入** | WordPress 插件生态标准做法，多文件 enqueue 即可 |
| Chart.js | **保留 + 本地优先** | 图表有实际价值，本地文件优先加载，CDN 作为 fallback |
| 版本策略 | **每 Phase 升版本** | 确保可精确回退到任意阶段 |

---

## 4. 分阶段重构计划

### Phase 0：修复已知 Bug + 可观测性

**目标**：修复当前代码中已确认的 Bug，建立诊断能力。

**Bug 修复：**

1. **Chart.js 本地化**
   - 下载 Chart.js 4.5.0 到 `assets/js/vendor/chartjs/chart.umd.min.js`（推荐，避免 `.gitignore` 忽略 `vendor/`）
   - 若坚持 `assets/vendor/`，需同步调整 `.gitignore`，确保文件可提交
   - 修改 `wpmind.php` enqueue：本地文件优先，CDN fallback
   - **移除 `chartjs` 从 admin.js 依赖列表**（关键！防止级联故障）
   - admin.js 中图表初始化前动态检测 `window.Chart` 是否可用，并支持“延迟可用后重试初始化”

2. **Tab CSS class 修复**
   - `admin.js` 中 `$('#dashboard').hasClass('active')` → `hasClass('wpmind-tab-pane-active')`

**可观测性：**

3. admin.js 加载成功后在 `<body>` 添加 `wpmind-js-loaded` class
4. `wpmindData` 注入 `version` / `debug` 字段
5. 控制台输出 `[WPMind] admin.js v{version} loaded` 便于诊断

**验收标准：**
- Chart.js CDN 不可用时，图表仍正常渲染（使用本地文件）
- Chart.js 完全不可用时，Tab/按钮/AJAX 不受影响
- Chart.js 晚于 admin.js 加载时，图表最终仍会初始化
- 浏览器控制台可确认 JS 加载状态

---

### Phase 1：camelCase → snake_case 方法重命名

**目标**：完成 WordPress 编码规范化，所有 PHP 方法名统一为 snake_case。

**执行策略（吸取上次教训）：**

按模块逐个进行，每个模块一次 commit，部署验证后再进入下一个：

| 批次 | 模块 | 涉及文件 | 说明 |
|------|------|----------|------|
| 1 | Routing | `IntelligentRouter.php`, `CompositeStrategy.php`, `RoutingContext.php` 等 | 先改定义 + 同文件调用 |
| 2 | Failover | `CircuitBreaker.php`, `ProviderHealthTracker.php` | 含私有方法 |
| 3 | CostControl | `UsageTracker.php`, `BudgetManager.php` 等 | 模块内部 + 兼容层 |
| 4 | Analytics | `AnalyticsModule.php` | 依赖 cost-control |
| 5 | 调用点 | `wpmind.php`, 所有 `templates/`, `modules/*/templates/` | 最后统一改调用点 |

**关键改进（vs 上次）：**

1. **先定义后调用**：每批先改方法定义（含 public + private + protected），再改同文件内调用，最后改跨文件调用
2. **全量搜索**：每个旧方法名必须 `grep -r` 确认零残留，包括模板、注释、字符串
3. **兼容层同步**：`includes/Usage/UsageTracker.php` 等 `__callStatic` 代理层必须同步更新映射
4. **单模块验证**：每批 commit 后部署，访问后台确认无 Fatal Error

**验收标准：**
- 仅检查方法定义/调用：  
  - 定义：`rg -n "function\\s+[a-zA-Z0-9_]*[A-Z][a-zA-Z0-9_]*\\s*\\(" includes/ modules/`  
  - 调用：`rg -n "->\\s*[a-zA-Z0-9_]*[A-Z][a-zA-Z0-9_]*\\s*\\(" includes/ modules/ templates/`  
  （排除第三方与 WordPress 原生）
- 后台所有 Tab 可正常切换
- 所有 AJAX 功能正常响应

---

### Phase 2：前端隔离与模块化（可选）

> 此阶段在 Phase 0-1 稳定运行后再决定是否执行。

**目标**：拆分 admin.js，模块独立运行，单模块故障不影响全局。

**文件结构：**

```
assets/js/
├── admin-boot.js          # 入口：Tab 初始化、健康检查
├── admin-endpoints.js     # 端点管理（表单、测试连接）
├── admin-routing.js       # 路由策略管理
├── admin-analytics.js     # 图表渲染（依赖 Chart.js）
├── admin-budget.js        # 预算与用量
├── admin-geo.js           # GEO 设置
├── admin-modules.js       # 模块开关
└── admin-ui.js            # Toast、Dialog 等公共 UI
```

**规则：**
- 每个文件自检 DOM：目标元素不存在时不初始化
- 每个文件用 try/catch 包裹初始化，错误只 console.warn 不阻断
- `admin-boot.js` 是唯一入口，其他文件通过 wp_enqueue_script 依赖它
- Chart.js 只被 `admin-analytics.js` 依赖，其他文件不受影响

**验收标准：**
- 禁用 Chart.js 后，Tab/按钮/AJAX 全部正常
- 单个模块 JS 报错不影响其他模块
- 网络面板确认各文件独立加载

---

### Phase 3：模板去内嵌脚本（可选）

> 依赖 Phase 2 完成。

**目标**：模板只负责渲染 HTML 结构，行为交给 JS 模块。

**涉及文件：**
- `templates/tabs/modules.php`：183 行内联 CSS + 44 行内联 JS
- `modules/cost-control/templates/settings.php`：内联脚本

**迁移方式：**
- 内联 CSS → `assets/css/modules.css`
- 内联 JS → 对应的 `admin-modules.js`
- 动态数据通过 `data-*` 属性传递

**验收标准：**
- 模板文件中无 `<script>` 和 `<style>` 标签
- 功能与迁移前一致

---

### Phase 4：后台 PHP 拆分（可选）

> 依赖 Phase 1 完成。仅在 wpmind.php 维护成本明显过高时执行。

**目标**：将 admin 相关逻辑从 `wpmind.php`（1,685 行）拆出。

**建议结构：**
- `includes/Admin/AdminAssets.php` — 资源加载 / 版本控制
- `includes/Admin/AdminPage.php` — 渲染后台模板
- `includes/Admin/AjaxController.php` — 统一 AJAX 管理（13 个 handler）
- `includes/Admin/AdminBoot.php` — hook 注册入口

**验收标准：**
- `wpmind.php` 只保留 bootstrap + hook 注册，admin 逻辑全部迁移
- 所有 AJAX handler 响应正常
- 后台页面渲染正常

---

## 5. 风险与回滚策略

### 风险矩阵

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| snake_case 重命名遗漏调用点 | Fatal Error | 每批 commit 后 grep 全量搜索 + 部署验证 |
| JS 拆分后加载顺序错误 | 功能失效 | wp_enqueue_script 依赖声明 + 浏览器网络面板验证 |
| 内嵌脚本迁移遗漏 | 部分功能断链 | 迁移前记录所有内嵌脚本功能清单 |
| Chart.js 本地文件版本不匹配 | 图表异常 | 使用与 CDN 完全相同的版本（4.5.0） |

### 回滚策略

- **每 Phase 独立 commit**，确保可 `git revert` 回退到前一阶段
- Phase 0：版本号提升到 `3.2.1`，打 tag `v3.2.1-phase0`
- Phase 1：版本号提升到 `3.3.0`，打 tag `v3.3.0-phase1`
- Phase 2-4 为可选阶段，不执行不影响稳定性

---

## 6. 验收标准（整体）

- [ ] Chart.js CDN 不可用时，后台功能完全正常
- [ ] 所有 PHP 方法名符合 snake_case 规范
- [ ] 后台所有 Tab 可正常切换
- [ ] 所有 AJAX 功能正常（13 个 handler）
- [ ] 图表在 Chart.js 可用时正常渲染
- [ ] 浏览器控制台无 JS 错误
- [ ] `grep -rn 'camelCase方法名' includes/ modules/` 返回零结果

---

## 7. 执行顺序总结

```
Phase 0（必做）→ Phase 1（必做）→ 验收稳定 → Phase 2-4（按需）
   修复 Bug        snake_case         确认无问题      结构优化
```

**Phase 0 和 Phase 1 是必做项**，解决已知 Bug 和编码规范问题。
**Phase 2-4 是可选项**，在前两个阶段稳定后根据实际需要决定是否执行。

---

## 8. 下一步

1. 确认本方案无异议
2. 从 Phase 0 开始实施（修复 Chart.js + Tab Bug + 可观测性）
3. Phase 0 部署验证通过后，进入 Phase 1（snake_case 重命名）
