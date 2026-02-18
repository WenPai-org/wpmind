# WPMind - 文派心思

WordPress AI 自定义端点扩展插件，支持国内外多种 AI 服务。

## 项目信息

| 项目 | 值 |
|------|-----|
| 版本 | 3.6.0 |
| 开发目录 | `~/Projects/wpmind/` |
| 部署目录 | `/www/wwwroot/wpcy.com/wp-content/plugins/wpmind/` |
| 测试站点 | https://wpcy.com |

## 开发工作流

### 1. 开发
在 `~/Projects/wpmind/` 目录进行开发

### 2. 测试
```bash
# 部署到测试站点
./deploy.sh

# 刷新 WordPress 后台测试
```

### 3. 提交
```bash
git add -A
git commit -m "feat: 功能描述"
```

### 4. 部署
```bash
./deploy.sh
```

> **必须遵守**：每次执行 `./deploy.sh` 前，必须同步更新 `wpmind.php` 中的版本号（`Version:` 头部注释和 `WPMIND_VERSION` 常量），递增 patch 版本。

## 目录结构

```
wpmind/
├── wpmind.php              # 主插件文件
├── uninstall.php           # 卸载脚本
├── CHANGELOG.md            # 更新日志
├── WPMIND-ROADMAP.md       # 战略规划
├── deploy.sh               # 部署脚本
├── assets/
│   ├── css/                # 管理样式
│   └── js/                 # 管理脚本（模块化）
│       ├── admin-boot.js
│       ├── admin-ui.js
│       ├── admin-endpoints.js
│       ├── admin-routing.js
│       ├── admin-analytics.js
│       ├── admin-budget.js
│       ├── admin-geo.js
│       └── admin-modules.js
├── includes/
│   ├── Core/               # 模块系统 (ModuleLoader)
│   ├── Providers/          # AI Provider 实现 (8个国内 + Image)
│   ├── Routing/            # 智能路由系统 (5种策略)
│   ├── Failover/           # 熔断器和故障转移
│   ├── Usage/              # 用量追踪 (兼容层)
│   ├── Budget/             # 预算管理 (兼容层)
│   ├── Analytics/          # 分析面板 (兼容层)
│   └── API/                # 公共 API
├── modules/
│   ├── analytics/          # 分析面板模块
│   ├── cost-control/       # 成本控制模块
│   └── geo/                # GEO 优化模块
├── templates/              # 管理页面模板
├── tests/                  # 测试文件
└── languages/              # 翻译文件
```

## 支持的 AI 服务

### 官方服务
- OpenAI (ChatGPT)
- Anthropic (Claude)
- Google AI (Gemini)

### 国内服务
- DeepSeek
- 通义千问 (Qwen)
- 智谱 AI (智谱清言)
- Moonshot (Kimi)
- 豆包 (字节)
- 硅基流动
- 百度文心 (Baidu)
- MiniMax

## 常用命令

```bash
# 部署到测试站点
./deploy.sh

# 查看 git 状态
git status

# 提交更改
git add -A && git commit -m "feat: 功能描述"

# 查看日志
git log --oneline -10
```

## 相关文档

- [CHANGELOG.md](CHANGELOG.md) - 更新日志
- [WPMIND-ROADMAP.md](WPMIND-ROADMAP.md) - 战略规划
