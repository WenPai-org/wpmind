# API Gateway 应用场景

> 本文档用于 WPMind 官网文案、产品介绍和营销材料的素材参考。

## 产品定位

WPMind API Gateway 将任何 WordPress 站点变为 **OpenAI 兼容的 AI API 网关**，让站长零后端开发地把 AI 能力暴露为标准 API。

**甜蜜点**：中小规模、WordPress 生态内的 AI 服务中转。

---

## 核心场景

### 1. 给前端应用提供 AI API

WordPress 站长有前端应用（小程序、App、SPA），需要调用 AI 服务。

**痛点**：
- 直接把 AI 服务的 API Key 写在前端不安全
- 需要一个中转层处理鉴权和限流
- 不想为此单独搭建后端服务

**方案**：
- 前端调用 `yoursite.com/wp-json/mind/v1/chat/completions`
- Gateway 处理 Bearer Token 鉴权、RPM/TPM 限流、预算控制
- 后端自动转发到 DeepSeek / Qwen / Claude 等 AI 服务

**价值**：不暴露真实 AI 服务 Key，统一管理成本，零后端开发。

### 2. 小型 AI SaaS 的快速后端

创业者用 WordPress 搭建 AI 产品（写作助手、翻译工具、客服机器人）。

**痛点**：
- 搭建 API 后端需要服务器、框架、数据库、鉴权系统
- 需要用户管理、支付、内容管理等配套功能
- 开发周期长，成本高

**方案**：
- WordPress 本身处理用户注册、支付（WooCommerce）、内容管理
- Gateway 充当 API 层，用户注册后分发 API Key
- 按 Key 控制 RPM / TPM 和月预算上限
- OpenAI 兼容格式，前端可用任何 OpenAI SDK 直接对接
- 模型别名映射：用户调 `gpt-4`，实际走 DeepSeek（成本低 10 倍）

**价值**：零后端开发，WordPress 一站式搞定用户 + 支付 + API。

### 3. 多 AI 服务统一网关

企业或团队同时使用多个 AI 服务（OpenAI + DeepSeek + Qwen + Claude）。

**痛点**：
- 每个项目单独对接多个 AI 服务，重复工作
- 某个服务挂了没有自动切换
- 用量和成本分散在各个平台，难以统计

**方案**：
- 一个 API 地址，自动路由到最优 / 最便宜的模型
- 某个服务故障时自动 failover 到备选服务
- 统一的用量统计和成本报表
- 按团队 / 项目分发独立 API Key，独立计费

**价值**：一个入口管理所有 AI 服务，降低对接成本和运维负担。

---

## 延伸场景

### 4. WordPress 插件生态的 AI 中枢

其他 WordPress 插件（内容生成、SEO 优化、智能客服、表单处理）需要 AI 能力时，不用各自配置 API Key，统一调用本站 Gateway。

**典型用例**：
- AI 内容生成插件 → 调用 Gateway 的 chat/completions
- AI SEO 插件 → 调用 Gateway 的 embeddings 做语义分析
- AI 客服插件 → 调用 Gateway 的 streaming chat

**价值**：WPMind 成为 WordPress 站点的"AI 基础设施层"。

### 5. 教育 / 内部培训

学校或企业给学生 / 员工分发受限的 API Key，用于 AI 学习或内部工具开发。

**典型用例**：
- 编程课程：学生用 API Key 调用 AI 辅助编程
- 内部工具：员工用 API Key 开发部门级 AI 工具
- 培训平台：与 Moodle / LMS 集成，AI 辅助教学

**价值**：精细化的预算和用量控制，防止滥用。

---

## 技术亮点（文案素材）

| 特性 | 描述 |
|------|------|
| OpenAI 兼容 | 标准 `/v1/chat/completions` 格式，任何 OpenAI SDK 直接对接 |
| 双层鉴权 | REST permission_callback + Pipeline AuthMiddleware |
| 原子级限流 | Redis Lua 脚本 + WordPress Transient 双引擎 |
| SSE 流式输出 | 实时逐字输出，支持并发控制和心跳保活 |
| 预算管理 | 按 Key 设置月预算上限，超额自动拒绝 |
| 模型映射 | 用户调 gpt-4，实际走 DeepSeek，无缝切换 |
| 审计日志 | 每次请求记录 Key / 模型 / 耗时 / Token 用量 |
| 管理后台 | 4 子标签（设置 / Key 管理 / 接入文档 / 请求日志） |

## 局限性（内部参考，不对外）

- **并发上限**：WordPress + PHP-FPM 架构，适合中小规模（< 100 QPS）
- **非企业级**：大企业会选 Kong / Envoy 等专业网关
- **SSE 限制**：PHP 长连接受 max_execution_time 和 worker 数量约束
- **单点**：依赖 WordPress 站点可用性，无集群能力

---

## 竞品对比素材

| 对比维度 | WPMind Gateway | 专业 API 网关 (Kong) | 云厂商 API 管理 |
|----------|---------------|---------------------|----------------|
| 部署难度 | WordPress 插件一键安装 | 需要独立服务器 + 配置 | 云平台绑定 |
| 用户管理 | WordPress 原生 | 需要额外系统 | 云平台账号 |
| 支付集成 | WooCommerce | 需要自建 | 云平台计费 |
| AI 服务支持 | 12+ 国内外服务 | 需要自行对接 | 通常绑定自家服务 |
| 适用规模 | 中小型 | 中大型 | 中大型 |
| 成本 | 插件费用 | 服务器 + 运维 | 按量计费 |

---

*最后更新：2026-02-09*
