# WPMind - 文派心思

WordPress AI 服务集成插件，为 WordPress 提供国内 AI 服务桥接能力。

## 项目信息

| 项目 | 值 |
|------|-----|
| 插件名称 | WPMind |
| 中文名称 | 文派心思 |
| 开发目录 | `~/Projects/wpmind/` |

## 治理规则

- 本文件上限 200 行，超出须归档到 `docs/ai-context/`
- 交接记录：`docs/ai-context/HANDOFF.md`

## 核心定位

> WordPress AI 服务集成层 - 让国内用户顺畅使用 AI 能力

### 目标

- 国内 AI 服务（DeepSeek/通义千问/文心一言等）统一接入
- AI 内容生成与增强
- 插件 AI 功能桥接（Yoast/Rank Math 等）
- 开发者 API 集成框架

### 非目标

- AI 模型训练或微调
- 官方 AI 源加速（文派叶子的职责）
- 自定义更新源管理（WPBridge 的职责）

## 技术栈

- PHP 7.4+
- WordPress 5.9+
- AI Provider API（DeepSeek/OpenAI-compatible）

## 远程仓库

- feicode: ssh://git@feicode.com:2222/WenPai-org/wpmind.git
- GitHub: https://github.com/WenPai-org/wpmind.git
