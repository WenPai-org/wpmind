# WPMind GEO: Markdown Feeds 实现方案

> 为 AI 搜索引擎提供结构化内容输出，提升 GEO 可见性

*文档版本: 1.1.0 | 创建日期: 2026-02-05 | Codex 评审: 2026-02-05*

---

## 背景

### 官方 PR #194 分析

WordPress/ai 仓库的 PR #194 正在开发 Markdown Feeds 功能：

| 功能 | 实现方式 |
|------|----------|
| Feed 路径 | `/?feed=markdown` |
| 单篇访问 | `.md` 后缀 (如 `/hello-world.md`) |
| 内容协商 | `Accept: text/markdown` Header |
| 转换器 | `WP_HTML_Processor` + `WP_HTML_Tag_Processor` |
| 扩展点 | `ai_experiments_markdown_feed_post_sections` Filter |

**里程碑**: Future Release（发布时间不确定）

### 官方 Filter Hooks

```php
// 可用于扩展的 Filter
ai_experiments_markdown_feed_post_sections
ai_experiments_markdown_singular_post_sections
```

---

## 设计目标

### 核心原则

1. **兼容优先**: 与官方实现零冲突
2. **增强而非替代**: 通过 Filter Hook 增强官方输出
3. **独立可选**: 官方未发布时提供独立实现

### 差异化功能

| 功能 | 官方 PR #194 | WPMind GEO |
|------|-------------|------------|
| 基础 Markdown 输出 | ✅ | ✅ |
| 中文内容优化 | ❌ | ✅ |
| SEO 元数据注入 | ❌ | ✅ |
| AI 引用格式 | ❌ | ✅ |
| 多语言支持 | ❌ | ✅ |
| 自定义字段映射 | 基础 | ✅ ACF/Meta Box |
| 缓存优化 | ❌ | ✅ |
| 访问统计 | ❌ | ✅ |

---

## 技术架构

### 目录结构

```
includes/GEO/
├── MarkdownEnhancer.php      # 核心增强器（检测官方插件）
├── MarkdownFeed.php          # 独立 Feed 实现（备用）
├── HtmlToMarkdown.php        # HTML 转 Markdown 转换器
├── ChineseOptimizer.php      # 中文内容优化
├── GeoSignalInjector.php     # GEO 信号注入
└── CrawlerTracker.php        # AI 爬虫追踪
```

### 运行模式

```
WPMind GEO 启动
       ↓
检测官方 AI Experiments 插件
       ↓
┌──────────────────────────────────────┐
│ 官方插件已安装且启用 Markdown Feeds？ │
└──────────────────────────────────────┘
       ↓                    ↓
      是                   否
       ↓                    ↓
  模式 A: 增强模式      模式 B: 独立模式
  (Filter Hook)         (可选启用)
```

---

## 模式 A: 增强模式

### 实现方式

通过官方提供的 Filter Hook 增强 Markdown 输出：

```php
// includes/GEO/MarkdownEnhancer.php
namespace WPMind\GEO;

class MarkdownEnhancer {

    public function __construct() {
        // 仅在官方插件激活时启用
        add_action( 'plugins_loaded', [ $this, 'maybe_init' ] );
    }

    public function maybe_init() {
        if ( ! $this->is_official_markdown_feeds_active() ) {
            return;
        }

        // 增强 Feed 输出
        add_filter(
            'ai_experiments_markdown_feed_post_sections',
            [ $this, 'enhance_feed_sections' ],
            10, 2
        );

        // 增强单篇输出
        add_filter(
            'ai_experiments_markdown_singular_post_sections',
            [ $this, 'enhance_singular_sections' ],
            10, 2
        );
    }

    private function is_official_markdown_feeds_active(): bool {
        return class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' )
            && get_option( 'ai_experiments_markdown_feeds_enabled', false );
    }

    public function enhance_feed_sections( array $sections, \WP_Post $post ): array {
        // 1. 中文内容优化
        $sections = ( new ChineseOptimizer() )->optimize( $sections );

        // 2. 注入 GEO 信号
        $sections = ( new GeoSignalInjector() )->inject( $sections, $post );

        // 3. 添加元数据
        $sections = $this->add_metadata_section( $sections, $post );

        return $sections;
    }
}
```

### 增强内容

#### 1. 中文内容优化

```php
// includes/GEO/ChineseOptimizer.php
namespace WPMind\GEO;

class ChineseOptimizer {

    public function optimize( array $sections ): array {
        foreach ( $sections as $key => $content ) {
            if ( is_string( $content ) ) {
                $sections[ $key ] = $this->optimize_text( $content );
            }
        }
        return $sections;
    }

    private function optimize_text( string $text ): string {
        // 中英文之间添加空格
        $text = preg_replace( '/([\\x{4e00}-\\x{9fa5}])([a-zA-Z0-9])/u', '$1 $2', $text );
        $text = preg_replace( '/([a-zA-Z0-9])([\\x{4e00}-\\x{9fa5}])/u', '$1 $2', $text );

        // 标点规范化
        $text = str_replace(
            [ '，', '。', '！', '？', '：', '；' ],
            [ ', ', '. ', '! ', '? ', ': ', '; ' ],
            $text
        );

        return $text;
    }
}
```

#### 2. GEO 信号注入

```php
// includes/GEO/GeoSignalInjector.php
namespace WPMind\GEO;

class GeoSignalInjector {

    public function inject( array $sections, \WP_Post $post ): array {
        // 在内容前添加权威性声明
        $authority = $this->generate_authority_signal( $post );
        array_unshift( $sections, $authority );

        // 在内容后添加引用格式
        $citation = $this->generate_citation_format( $post );
        $sections[] = $citation;

        return $sections;
    }

    private function generate_authority_signal( \WP_Post $post ): string {
        $author = get_the_author_meta( 'display_name', $post->post_author );
        $date = get_the_date( 'Y-m-d', $post );
        $modified = get_the_modified_date( 'Y-m-d', $post );

        return sprintf(
            "---\n作者: %s\n发布日期: %s\n最后更新: %s\n---\n",
            $author,
            $date,
            $modified
        );
    }

    private function generate_citation_format( \WP_Post $post ): string {
        $title = get_the_title( $post );
        $url = get_permalink( $post );
        $site = get_bloginfo( 'name' );
        $date = get_the_date( 'Y-m-d', $post );

        return sprintf(
            "\n---\n## 引用格式\n\n%s. \"%s\". %s, %s. %s\n",
            get_the_author_meta( 'display_name', $post->post_author ),
            $title,
            $site,
            $date,
            $url
        );
    }
}
```

---

## 模式 B: 独立模式

### 启用条件

- 官方 AI Experiments 插件未安装
- 用户手动启用独立模式

### 实现方式

```php
// includes/GEO/MarkdownFeed.php
namespace WPMind\GEO;

class MarkdownFeed {

    public function __construct() {
        if ( ! get_option( 'wpmind_standalone_markdown_feed', false ) ) {
            return;
        }

        add_action( 'init', [ $this, 'register_feed' ] );
        add_filter( 'request', [ $this, 'handle_md_suffix' ] );
    }

    public function register_feed() {
        add_feed( 'markdown', [ $this, 'render_feed' ] );
    }

    public function render_feed() {
        header( 'Content-Type: text/markdown; charset=utf-8' );

        $posts = get_posts( [
            'numberposts' => 10,
            'post_status' => 'publish',
        ] );

        foreach ( $posts as $post ) {
            echo $this->post_to_markdown( $post );
            echo "\n\n---\n\n";
        }
    }

    public function handle_md_suffix( array $query_vars ): array {
        if ( isset( $query_vars['name'] ) && str_ends_with( $query_vars['name'], '.md' ) ) {
            $query_vars['name'] = substr( $query_vars['name'], 0, -3 );
            $query_vars['wpmind_markdown'] = true;
        }
        return $query_vars;
    }

    private function post_to_markdown( \WP_Post $post ): string {
        $converter = new HtmlToMarkdown();

        $markdown = "# " . get_the_title( $post ) . "\n\n";
        $markdown .= $converter->convert( $post->post_content );

        return $markdown;
    }
}
```

---

## 设置界面

### 新增选项

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `wpmind_geo_enabled` | bool | true | 启用 GEO 增强 |
| `wpmind_chinese_optimize` | bool | true | 中文内容优化 |
| `wpmind_geo_signals` | bool | true | GEO 信号注入 |
| `wpmind_standalone_markdown_feed` | bool | false | 独立 Markdown Feed |
| `wpmind_crawler_tracking` | bool | true | AI 爬虫追踪 |

---

## 测试计划

### 单元测试

```php
// tests/GEO/MarkdownEnhancerTest.php
class MarkdownEnhancerTest extends WP_UnitTestCase {

    public function test_chinese_optimizer() {
        $optimizer = new ChineseOptimizer();
        $input = '这是WordPress插件';
        $expected = '这是 WordPress 插件';
        $this->assertEquals( $expected, $optimizer->optimize_text( $input ) );
    }

    public function test_geo_signal_injection() {
        $post = $this->factory->post->create_and_get();
        $injector = new GeoSignalInjector();
        $sections = [ 'content' => 'Test content' ];
        $result = $injector->inject( $sections, $post );

        $this->assertStringContainsString( '作者:', $result[0] );
        $this->assertStringContainsString( '引用格式', end( $result ) );
    }
}
```

### 集成测试

1. 安装官方 AI Experiments 插件
2. 启用 Markdown Feeds 实验
3. 访问 `/?feed=markdown`
4. 验证 WPMind 增强内容存在

---

## 风险评估

### 低风险

- **Filter Hook 稳定性**: 官方已在 PR 中定义，不太可能变更
- **命名空间隔离**: WPMind 使用独立命名空间

### 中风险

- **官方 PR 未合并**: 可能需要长期维护独立模式
- **Filter 参数变更**: 需要跟踪官方更新

### 缓解措施

1. 每周检查 PR #194 状态
2. 独立模式作为备用方案
3. 版本兼容性检测

---

## Codex 评审意见 (2026-02-05)

### 架构问题 (需修复)

| 问题 | 严重级别 | 解决方案 |
|------|----------|----------|
| `.md` 后缀缺少重写规则和渲染处理 | **高** | 添加 `add_rewrite_rule` + `template_redirect` |
| `add_metadata_section` 方法未定义 | 中 | 补充方法实现 |
| `array_unshift` 破坏关联数组结构 | 中 | 改用 `array_merge` 保持键名 |
| 未检测 `wpmind_geo_enabled` 配置 | 低 | 在 `enhance_feed_sections` 开头添加配置检查 |

### 兼容性问题 (需修复)

| 问题 | 严重级别 | 解决方案 |
|------|----------|----------|
| `str_ends_with` 需 PHP 8.0+ | **高** | 使用 `substr($str, -3) === '.md'` 兼容 PHP 7.4 |
| 不支持 `Accept: text/markdown` | 中 | 添加 `parse_request` 钩子检测 Accept 头 |
| `ChineseOptimizer` 假设纯字符串 | 中 | 递归处理数组，仅优化字符串值 |
| 测试调用私有方法 `optimize_text` | 低 | 改为 `protected` 或通过公共方法测试 |

### 评审结论

架构设计整体合理，双模式策略（增强/独立）符合兼容性优先原则。主要问题集中在：
1. 独立模式的 URL 路由实现不完整
2. PHP 版本兼容性
3. 数组结构处理

建议在开发时优先解决高严重级别问题。

---

## 开发任务

### Phase 1: 核心实现

- [ ] 创建 `includes/GEO/` 目录结构
- [ ] 实现 `MarkdownEnhancer.php` 核心类
- [ ] 实现 `ChineseOptimizer.php` 中文优化
- [ ] 实现 `GeoSignalInjector.php` GEO 信号

### Phase 2: 独立模式

- [ ] 实现 `MarkdownFeed.php` 独立 Feed
- [ ] 实现 `HtmlToMarkdown.php` 转换器
- [ ] 添加设置界面选项

### Phase 3: 追踪与分析

- [ ] 实现 `CrawlerTracker.php` 爬虫追踪
- [ ] 集成到 Analytics 仪表板
- [ ] 添加 AI 爬虫识别

### Phase 4: 测试与文档

- [ ] 编写单元测试
- [ ] 编写集成测试
- [ ] 更新用户文档

---

## 参考资源

- [WordPress/ai PR #194](https://github.com/WordPress/ai/pull/194)
- [WP_HTML_Processor 文档](https://developer.wordpress.org/reference/classes/wp_html_processor/)
- [GEO 优化最佳实践](https://searchengineland.com/generative-engine-optimization-geo-guide)

---

*最后更新: 2026-02-05*
