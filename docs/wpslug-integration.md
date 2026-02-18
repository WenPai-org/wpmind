# WPSlug 与 WPMind 集成文档

## 概述

WPSlug 是一个 WordPress 插件，用于将中文标题自动转换为 SEO 友好的 slug。通过集成 WPMind，WPSlug 可以利用 AI 实现：

1. **智能翻译**：将中文翻译为任意语言作为 slug
2. **语义化拼音**：按词语分隔而非按字分隔的拼音转换

## 集成架构

```
┌─────────────────────────────────────────────────────────────┐
│                      WordPress 文章编辑器                      │
│                  （创建/编辑文章时触发）                        │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     WPSlug_Core                              │
│          processSanitizeTitle / processPostData              │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   WPSlug_Converter                           │
│              （根据设置选择转换方式）                          │
└────┬────────────┬───────────────────┬───────────────────────┘
     │            │                   │
     ▼            ▼                   ▼
┌─────────┐ ┌───────────────┐ ┌──────────────────┐
│  拼音   │ │  语义化拼音   │ │      翻译        │
│ Pinyin  │ │Semantic Pinyin│ │  Translation     │
└─────────┘ └───────┬───────┘ └────────┬─────────┘
                    │                  │
                    ▼                  ▼
            ┌──────────────────────────────┐
            │         WPMind AI            │
            │   wpmind_pinyin()            │
            │   wpmind_translate()         │
            └──────────────────────────────┘
```

## 支持的转换模式

| 模式 | 说明 | 示例 |
|------|------|------|
| **普通拼音** | 按字分隔 | 你好世界 → `ni-hao-shi-jie` |
| **语义化拼音** | 按词分隔（WPMind AI） | 你好世界 → `nihao-shijie` |
| **翻译** | AI 翻译为目标语言 | 你好世界 → `hello-world` |

## 修改的文件

### WPMind 端

| 文件 | 修改内容 |
|------|---------|
| `includes/API/PublicAPI.php` | 添加 `format=pinyin` 支持；`is_available()` 静态缓存优化；`translate()` 使用 `sanitize_title_with_dashes()` |
| `includes/API/functions.php` | 新增 `wpmind_pinyin()` 公共函数 |

### WPSlug 端

| 文件 | 修改内容 |
|------|---------|
| `includes/class-wpslug-converter.php` | 添加 `convertSemanticPinyin()` 方法 |
| `includes/class-wpslug-translator.php` | 添加 `translateWPMind()` 方法和循环调用保护 |
| `includes/class-wpslug-settings.php` | 动态检测 WPMind 可用性，添加语义化拼音模式 |
| `includes/class-wpslug-validator.php` | 添加 `wpmind` 和 `semantic_pinyin` 验证 |
| `includes/class-wpslug-admin.php` | 添加 WPMind 设置面板 UI |

## 已解决的问题

### 1. 无限循环问题

**问题**：`sanitize_title()` 触发 filter，导致 WPMind 和 WPSlug 循环调用。

**解决方案**：
- WPMind：使用 `sanitize_title_with_dashes()` 不触发 filter
- WPSlug：静态标志 `$is_translating` 防止递归

### 2. 设置保存失败

**问题**：`validateTranslationService()` 缺少 `wpmind` 选项。

**解决方案**：添加到有效服务列表。

### 3. 缓存语言冲突

**问题**：不同目标语言使用相同缓存。

**解决方案**：缓存键包含语言设置。

## 使用方法

### 语义化拼音

1. 进入 **设置** → **WPSlug**
2. **转换模式** 选择 **Semantic Pinyin (WPMind AI)**
3. 保存设置

### 翻译模式

1. **转换模式** 选择 **Multi-language Translation**
2. **翻译服务** 选择 **WPMind AI (Recommended)**
3. 设置 **目标语言**
4. 保存设置

## 技术细节

### API 调用示例

```php
// 语义化拼音
$pinyin = wpmind_pinyin('你好世界');
// 返回: "nihao-shijie"

// 翻译为 slug
$slug = wpmind_translate('你好世界', 'zh', 'en', [
    'format' => 'slug',
]);
// 返回: "hello-world"
```

### 保护机制

1. **双重循环保护**
2. **双重缓存**（WPSlug 7天 + WPMind 1天）
3. **文本长度限制**（> 200字符回退）
4. **错误回退**（失败时使用普通拼音）

## 调试

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

日志前缀：`[WPSlug]` / `[WPMind]`

## 版本历史

| 日期 | 变更 |
|------|------|
| 2026-02-02 | 添加语义化拼音功能 |
| 2026-02-02 | 初始集成，修复无限循环问题 |
