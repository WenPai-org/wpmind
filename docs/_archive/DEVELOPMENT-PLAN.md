# WPMind 开发计划

> 更新日期：2026-02-01
> 当前版本：2.4.x
> 规划版本：2.5.0 - 3.0.0

---

## 版本路线图

```
2.4.x (当前)
   │
   ├── 图像服务集成 ✅
   └── 用量统计增强 ✅
   
2.5.0 (下一版本) ← 公共 API
   │
   ├── 公共 API 函数
   ├── WordPress Hooks
   └── wpslug 集成示例
   
2.6.0 ← AI Gateway Layer 1
   │
   ├── OpenAI 兼容端点
   ├── HTTP 请求拦截
   └── AI Engine 兼容
   
2.7.0 ← AI Gateway Layer 2
   │
   ├── 嗅探模式
   ├── Yoast 适配器
   └── Rank Math 适配器
   
3.0.0 ← 翻译桥接器
   │
   ├── Google Translate 桥接
   ├── DeepL 桥接
   └── TranslatePress 兼容
```

---

## v2.5.0 - 公共 API

**目标**：为文派生态插件提供统一的 AI 调用接口

### 核心功能

| 功能 | 优先级 | 预估工时 |
|-----|-------|---------|
| `wpmind_translate()` 函数 | 🔴 P0 | 4h |
| `wpmind_chat()` 函数 | 🔴 P0 | 2h |
| `wpmind_generate_image()` 函数 | 🟡 P1 | 2h |
| `wpmind_is_available()` 函数 | 🔴 P0 | 1h |
| WordPress Hooks 注册 | 🔴 P0 | 2h |
| 翻译结果缓存 | 🟡 P1 | 3h |
| PHPDoc 文档 | 🟡 P1 | 2h |

### 文件变更

```
wpmind/
├── includes/
│   └── API/
│       ├── PublicAPI.php      # 新增：公共 API 类
│       └── Translator.php     # 新增：翻译封装类
├── wpmind.php                 # 修改：注册全局函数
└── docs/
    └── public-api-design.md   # ✅ 已创建
```

### 测试计划

- [ ] 单元测试：各函数返回值
- [ ] 集成测试：wpslug 插件调用
- [ ] 降级测试：WPMind 未配置时的行为

---

## v2.6.0 - AI Gateway Layer 1

**目标**：拦截 OpenAI API 请求，实现透明代理

### 核心功能

| 功能 | 优先级 | 预估工时 |
|-----|-------|---------|
| `pre_http_request` 拦截器 | 🔴 P0 | 4h |
| OpenAI 请求解析 | 🔴 P0 | 4h |
| OpenAI 响应格式化 | 🔴 P0 | 4h |
| Anthropic 请求支持 | 🟡 P1 | 4h |
| Gateway 开关设置 | 🔴 P0 | 2h |
| 拦截统计 | 🟡 P1 | 3h |

### 文件变更

```
wpmind/
├── includes/
│   └── Gateway/
│       ├── AIGateway.php           # 新增：主控制器
│       ├── OpenAIInterceptor.php   # 新增：OpenAI 拦截
│       ├── AnthropicInterceptor.php # 新增：Anthropic 拦截
│       └── ResponseFormatter.php   # 新增：响应格式化
└── templates/
    └── tabs/
        └── gateway.php             # 新增：Gateway 设置页
```

### 兼容性目标

- [ ] AI Engine 插件
- [ ] AI Power 插件
- [ ] GPT3 AI Content Generator

---

## v2.7.0 - AI Gateway Layer 2

**目标**：覆盖商业插件的 AI 请求

### 核心功能

| 功能 | 优先级 | 预估工时 |
|-----|-------|---------|
| 嗅探模式 | 🔴 P0 | 4h |
| 嗅探数据查看界面 | 🟡 P1 | 4h |
| Yoast SEO 适配器 | 🟡 P1 | 8h |
| Rank Math 适配器 | 🟡 P1 | 8h |
| 适配器版本管理 | 🟢 P2 | 4h |

### 文件变更

```
wpmind/
├── includes/
│   └── Gateway/
│       ├── Sniffer.php             # 新增：嗅探模式
│       └── Adapters/
│           ├── YoastAdapter.php    # 新增
│           └── RankMathAdapter.php # 新增
└── templates/
    └── tabs/
        └── sniffer.php             # 新增：嗅探日志页
```

---

## v3.0.0 - 翻译桥接器

**目标**：将翻译 API 请求桥接到大模型

### 核心功能

| 功能 | 优先级 | 预估工时 |
|-----|-------|---------|
| Google Translate API 桥接 | 🔴 P0 | 8h |
| DeepL API 桥接 | 🔴 P0 | 8h |
| TranslatePress 兼容测试 | 🟡 P1 | 4h |
| 翻译质量优化 | 🟡 P1 | 8h |

### 技术挑战

- Google Translate 请求格式解析
- 大模型翻译 Prompt 优化
- 响应速度优化（流式？）

---

## 当前迭代任务

### Sprint 1（本周）

- [x] 图像服务配置核实
- [x] Midjourney 移除
- [x] AI Gateway 设计文档
- [x] 公共 API 设计文档
- [ ] 公共 API 实现

### Sprint 2（下周）

- [ ] wpslug 集成测试
- [ ] OpenAI 兼容端点
- [ ] Gateway 设置界面

---

## 技术债务

| 项目 | 优先级 | 说明 |
|-----|-------|------|
| 单元测试覆盖 | 🟡 中 | 目前无测试 |
| 代码文档 | 🟡 中 | PHPDoc 不完整 |
| 性能优化 | 🟢 低 | 大量请求时的并发处理 |

---

## 依赖关系

```
wpslug 翻译功能
    │
    └── 依赖 WPMind 公共 API (v2.5.0)
             │
             └── 依赖 WPMind 核心 (v2.4.x) ✅
             
AI Gateway Layer 2
    │
    └── 依赖 AI Gateway Layer 1 (v2.6.0)
             │
             └── 依赖 公共 API (v2.5.0)
```

---

*计划文档结束*
