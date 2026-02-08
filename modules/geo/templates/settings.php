<?php
/**
 * WPMind GEO 优化 Tab
 *
 * GEO (Generative Engine Optimization) 设置界面
 * 使用 pill 子导航分组管理功能
 *
 * @package WPMind
 * @since 3.10.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取当前设置
$is_enabled = function( $option, $default = '1' ) {
	$value = get_option( $option, $default );
	return $value === '1' || $value === true || $value === 1;
};

$standalone_feed   = $is_enabled( 'wpmind_standalone_markdown_feed', '0' );
$geo_enabled       = $is_enabled( 'wpmind_geo_enabled', '1' );
$chinese_optimize  = $is_enabled( 'wpmind_chinese_optimize', '1' );
$geo_signals       = $is_enabled( 'wpmind_geo_signals', '1' );
$crawler_tracking  = $is_enabled( 'wpmind_crawler_tracking', '1' );
$llms_txt_enabled  = $is_enabled( 'wpmind_llms_txt_enabled', '1' );
$schema_enabled    = $is_enabled( 'wpmind_schema_enabled', '1' );
$schema_mode       = get_option( 'wpmind_schema_mode', 'auto' );
$ai_indexing        = $is_enabled( 'wpmind_ai_indexing_enabled', '0' );
$ai_declaration     = get_option( 'wpmind_ai_default_declaration', 'original' );
$ai_excluded_types  = get_option( 'wpmind_ai_excluded_post_types', [] );
if ( ! is_array( $ai_excluded_types ) ) {
	$ai_excluded_types = [];
}
$ai_sitemap_enabled    = $is_enabled( 'wpmind_ai_sitemap_enabled', '0' );
$ai_sitemap_max        = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );
$robots_ai_enabled     = $is_enabled( 'wpmind_robots_ai_enabled', '0' );
$robots_ai_rules       = get_option( 'wpmind_robots_ai_rules', [] );
if ( ! is_array( $robots_ai_rules ) ) {
	$robots_ai_rules = [];
}
$ai_summary_enabled    = $is_enabled( 'wpmind_ai_summary_enabled', '0' );
$ai_summary_fallback   = get_option( 'wpmind_ai_summary_fallback', 'excerpt' );
$entity_linker_enabled = $is_enabled( 'wpmind_entity_linker_enabled', '0' );

// 检查官方插件是否安装
$official_installed = class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' );

// 获取爬虫统计
$crawler_tracker = new \WPMind\Modules\Geo\CrawlerTracker();
$crawler_stats   = $crawler_tracker->get_stats();
$ai_summary_data = $crawler_tracker->get_ai_summary();
?>

<div class="wpmind-geo-panel">
    <div class="wpmind-geo-header">
        <h2 class="wpmind-geo-title">
            <span class="dashicons ri-robot-2-line"></span>
            <?php esc_html_e( 'GEO 优化', 'wpmind' ); ?>
        </h2>
        <span class="wpmind-geo-badge">v3.10</span>
    </div>

    <p class="wpmind-geo-desc">
        <?php esc_html_e( 'GEO (Generative Engine Optimization) 帮助 AI 搜索引擎更好地理解和引用您的内容。', 'wpmind' ); ?>
    </p>

    <!-- 状态概览 -->
    <div class="wpmind-geo-stats">
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon"><span class="dashicons ri-robot-2-line"></span></div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $ai_summary_data['total_ai_hits'] ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( 'AI 爬虫访问', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon"><span class="dashicons ri-search-line"></span></div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $ai_summary_data['total_search_hits'] ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '搜索引擎访问', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon"><span class="dashicons ri-file-text-line"></span></div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( count( $crawler_stats ) ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '已识别爬虫', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons <?php echo $standalone_feed ? 'ri-checkbox-circle-line' : 'ri-close-circle-line'; ?>"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo $standalone_feed ? esc_html__( '已启用', 'wpmind' ) : esc_html__( '未启用', 'wpmind' ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( 'Markdown Feed', 'wpmind' ); ?></span>
            </div>
        </div>
    </div>

    <!-- 子导航 -->
    <div class="wpmind-geo-subtabs">
        <button type="button" class="wpmind-geo-subtab active" data-tab="basics">
            <span class="dashicons ri-settings-3-line"></span>
            <?php esc_html_e( '基础设置', 'wpmind' ); ?>
        </button>
        <button type="button" class="wpmind-geo-subtab" data-tab="content">
            <span class="dashicons ri-file-text-line"></span>
            <?php esc_html_e( '内容输出', 'wpmind' ); ?>
        </button>
        <button type="button" class="wpmind-geo-subtab" data-tab="schema">
            <span class="dashicons ri-code-s-slash-line"></span>
            <?php esc_html_e( '结构化数据', 'wpmind' ); ?>
        </button>
        <button type="button" class="wpmind-geo-subtab" data-tab="control">
            <span class="dashicons ri-shield-check-line"></span>
            <?php esc_html_e( 'AI 索引', 'wpmind' ); ?>
        </button>
        <button type="button" class="wpmind-geo-subtab" data-tab="crawlers">
            <span class="dashicons ri-robot-line"></span>
            <?php esc_html_e( '爬虫管理', 'wpmind' ); ?>
        </button>
    </div>

    <div class="wpmind-geo-grid">
        <!-- 左栏：设置面板 -->
        <div class="wpmind-geo-left">

            <!-- ========== 基础设置 Tab ========== -->
            <div class="wpmind-geo-tab-panel active" data-panel="basics">
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-magic-line"></span>
                        <?php esc_html_e( 'GEO 增强', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '优化内容结构，提高 AI 引用率。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_geo_enabled" value="1" <?php checked( $geo_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 GEO 增强', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '总开关，控制所有 GEO 优化功能', 'wpmind' ); ?></span>
                            </span>
                        </label>
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_chinese_optimize" value="1" <?php checked( $chinese_optimize ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '中文内容优化', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '优化中英文混排、标点符号、段落结构', 'wpmind' ); ?></span>
                            </span>
                        </label>
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_geo_signals" value="1" <?php checked( $geo_signals ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( 'GEO 信号注入', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '添加作者信息、发布日期、引用格式等权威性信号', 'wpmind' ); ?></span>
                            </span>
                        </label>
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_crawler_tracking" value="1" <?php checked( $crawler_tracking ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( 'AI 爬虫追踪', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '记录 GPTBot、ClaudeBot 等 AI 爬虫的访问', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div><!-- /basics -->

            <!-- ========== 内容输出 Tab ========== -->
            <div class="wpmind-geo-tab-panel" data-panel="content">
                <!-- Markdown Feed -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-markdown-line"></span>
                        <?php esc_html_e( 'Markdown Feed', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '为 AI 爬虫提供结构化的 Markdown 格式内容。', 'wpmind' ); ?></p>
                    <?php if ( $official_installed ) : ?>
                    <div class="wpmind-geo-notice wpmind-geo-notice-info">
                        <span class="dashicons ri-information-line"></span>
                        <?php esc_html_e( '检测到官方 AI Experiments 插件，WPMind 将自动增强其 Markdown Feed。', 'wpmind' ); ?>
                    </div>
                    <?php endif; ?>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_standalone_markdown_feed" value="1"
                                   <?php checked( $standalone_feed ); ?> <?php disabled( $official_installed ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用独立 Markdown Feed', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '提供 /?feed=markdown 端点和 .md 后缀访问', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $standalone_feed ) : ?>
                    <div class="wpmind-geo-urls">
                        <p class="wpmind-geo-url-title"><?php esc_html_e( '访问方式：', 'wpmind' ); ?></p>
                        <code class="wpmind-geo-url"><?php echo esc_url( home_url( '/?feed=markdown' ) ); ?></code>
                        <code class="wpmind-geo-url"><?php echo esc_url( home_url( '/your-post-slug.md' ) ); ?></code>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- llms.txt -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-file-text-line"></span>
                        <?php esc_html_e( 'llms.txt', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '为 AI 爬虫提供站点导航和内容索引。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_llms_txt_enabled" value="1" <?php checked( $llms_txt_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 llms.txt', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '在 /llms.txt 提供站点内容导航', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $llms_txt_enabled ) : ?>
                    <div class="wpmind-geo-urls">
                        <p class="wpmind-geo-url-title"><?php esc_html_e( '访问地址：', 'wpmind' ); ?></p>
                        <code class="wpmind-geo-url"><?php echo esc_url( home_url( '/llms.txt' ) ); ?></code>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- AI Sitemap -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-road-map-line"></span>
                        <?php esc_html_e( 'AI Sitemap', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '为 AI 爬虫提供专属 XML Sitemap，包含内容声明和摘要等元数据。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_ai_sitemap_enabled" value="1" <?php checked( $ai_sitemap_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 AI Sitemap', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '在 /ai-sitemap.xml 提供 AI 专属站点地图', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $ai_sitemap_enabled ) : ?>
                    <div class="wpmind-geo-select-group">
                        <label class="wpmind-geo-select-label"><?php esc_html_e( '最大条目数：', 'wpmind' ); ?></label>
                        <input type="number" name="wpmind_ai_sitemap_max_entries" value="<?php echo esc_attr( (string) $ai_sitemap_max ); ?>"
                               min="10" max="5000" step="10" class="small-text" style="width:80px;">
                        <span class="description"><?php esc_html_e( '(10-5000)', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-geo-urls" style="margin-top:12px;">
                        <p class="wpmind-geo-url-title"><?php esc_html_e( '访问地址：', 'wpmind' ); ?></p>
                        <code class="wpmind-geo-url"><?php echo esc_url( home_url( '/ai-sitemap.xml' ) ); ?></code>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- AI 摘要 -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-chat-quote-line"></span>
                        <?php esc_html_e( 'AI 摘要', 'wpmind' ); ?>
                        <span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '控制 AI 如何描述您的文章，而非让 AI 自行猜测。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_ai_summary_enabled" value="1" <?php checked( $ai_summary_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 AI 摘要', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '在编辑器中添加 AI 摘要字段，输出为 meta 标签和 Schema.org abstract', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $ai_summary_enabled ) : ?>
                    <div class="wpmind-geo-select-group">
                        <label class="wpmind-geo-select-label"><?php esc_html_e( '无摘要时的回退：', 'wpmind' ); ?></label>
                        <select name="wpmind_ai_summary_fallback" class="wpmind-geo-select">
                            <option value="excerpt" <?php selected( $ai_summary_fallback, 'excerpt' ); ?>><?php esc_html_e( '使用文章摘要 (excerpt)', 'wpmind' ); ?></option>
                            <option value="none" <?php selected( $ai_summary_fallback, 'none' ); ?>><?php esc_html_e( '不输出', 'wpmind' ); ?></option>
                        </select>
                    </div>
                    <div class="wpmind-geo-notice wpmind-geo-notice-info" style="margin-top:12px;">
                        <span class="dashicons ri-information-line"></span>
                        <?php esc_html_e( '在编辑器侧边栏的「AI 摘要」面板中为每篇文章编写专属摘要。', 'wpmind' ); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- /content -->

            <!-- ========== 结构化数据 Tab ========== -->
            <div class="wpmind-geo-tab-panel" data-panel="schema">
                <!-- Schema.org -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-code-s-slash-line"></span>
                        <?php esc_html_e( 'Schema.org', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '为内容添加结构化数据，帮助 AI 理解语义。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_schema_enabled" value="1" <?php checked( $schema_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 Schema.org', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '自动为文章添加 Article 结构化数据', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $schema_enabled ) : ?>
                    <div class="wpmind-geo-select-group">
                        <label class="wpmind-geo-select-label"><?php esc_html_e( '兼容模式：', 'wpmind' ); ?></label>
                        <select name="wpmind_schema_mode" class="wpmind-geo-select">
                            <option value="auto" <?php selected( $schema_mode, 'auto' ); ?>><?php esc_html_e( '自动 - 检测到 SEO 插件时不输出', 'wpmind' ); ?></option>
                            <option value="merge" <?php selected( $schema_mode, 'merge' ); ?> disabled><?php esc_html_e( '合并 - 与现有 Schema 合并 (即将推出)', 'wpmind' ); ?></option>
                            <option value="force" <?php selected( $schema_mode, 'force' ); ?>><?php esc_html_e( '强制 - 始终输出（可能重复）', 'wpmind' ); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 实体关联 -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-links-line"></span>
                        <?php esc_html_e( '实体关联', 'wpmind' ); ?>
                        <span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '将文章关联到 Wikidata/Wikipedia 实体，帮助 AI 消除歧义并建立权威性。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_entity_linker_enabled" value="1" <?php checked( $entity_linker_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用实体关联', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '在编辑器中添加实体关联字段，输出为 Schema.org about.sameAs', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $entity_linker_enabled ) : ?>
                    <div class="wpmind-geo-notice wpmind-geo-notice-info" style="margin-top:12px;">
                        <span class="dashicons ri-information-line"></span>
                        <?php esc_html_e( '在编辑器侧边栏的「实体关联」面板中为每篇文章关联 Wikidata 实体。', 'wpmind' ); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- /schema -->

            <!-- ========== AI 控制 Tab ========== -->
            <div class="wpmind-geo-tab-panel" data-panel="control">
                <!-- AI 索引指令 -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-shield-check-line"></span>
                        <?php esc_html_e( 'AI 索引指令', 'wpmind' ); ?>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '控制 AI 爬虫对内容的索引和训练权限。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_ai_indexing_enabled" value="1" <?php checked( $ai_indexing ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 AI 索引指令', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '输出 noai/nollm meta 标签和 X-Robots-Tag HTTP 头', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $ai_indexing ) : ?>
                    <div class="wpmind-geo-select-group">
                        <label class="wpmind-geo-select-label"><?php esc_html_e( '默认内容声明：', 'wpmind' ); ?></label>
                        <select name="wpmind_ai_default_declaration" class="wpmind-geo-select">
                            <option value="original" <?php selected( $ai_declaration, 'original' ); ?>><?php esc_html_e( '原创内容 (original)', 'wpmind' ); ?></option>
                            <option value="ai-assisted" <?php selected( $ai_declaration, 'ai-assisted' ); ?>><?php esc_html_e( 'AI 辅助创作 (ai-assisted)', 'wpmind' ); ?></option>
                            <option value="ai-generated" <?php selected( $ai_declaration, 'ai-generated' ); ?>><?php esc_html_e( 'AI 生成内容 (ai-generated)', 'wpmind' ); ?></option>
                        </select>
                    </div>
                    <div class="wpmind-geo-select-group" style="margin-top:12px;">
                        <label class="wpmind-geo-select-label"><?php esc_html_e( '排除的内容类型：', 'wpmind' ); ?></label>
                        <p class="description" style="margin-bottom:8px;"><?php esc_html_e( '勾选的内容类型将输出 noai, nollm 指令，禁止 AI 索引和训练。', 'wpmind' ); ?></p>
                        <?php
                        $public_types = get_post_types( [ 'public' => true ], 'objects' );
                        foreach ( $public_types as $type ) :
                        ?>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox" name="wpmind_ai_excluded_post_types[]" value="<?php echo esc_attr( $type->name ); ?>"
                                   <?php checked( in_array( $type->name, $ai_excluded_types, true ) ); ?>>
                            <?php echo esc_html( $type->labels->name ); ?> <code style="font-size:11px;">(<?php echo esc_html( $type->name ); ?>)</code>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="wpmind-geo-notice wpmind-geo-notice-info" style="margin-top:12px;">
                        <span class="dashicons ri-information-line"></span>
                        <?php esc_html_e( '单篇文章可在编辑器侧边栏的「AI 索引指令」面板中覆盖全局设置。', 'wpmind' ); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- /control -->

            <!-- ========== 爬虫管理 Tab ========== -->
            <div class="wpmind-geo-tab-panel" data-panel="crawlers">
                <!-- robots.txt AI 管理 -->
                <div class="wpmind-geo-section">
                    <h3 class="wpmind-geo-section-title">
                        <span class="dashicons ri-robot-line"></span>
                        <?php esc_html_e( 'robots.txt AI 管理', 'wpmind' ); ?>
                        <span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
                    </h3>
                    <p class="wpmind-geo-section-desc"><?php esc_html_e( '通过 robots.txt 控制 AI 爬虫的访问权限，不修改物理文件。', 'wpmind' ); ?></p>
                    <div class="wpmind-geo-options">
                        <label class="wpmind-geo-option">
                            <input type="checkbox" name="wpmind_robots_ai_enabled" value="1" <?php checked( $robots_ai_enabled ); ?>>
                            <span class="wpmind-geo-option-content">
                                <span class="wpmind-geo-option-title"><?php esc_html_e( '启用 robots.txt AI 管理', 'wpmind' ); ?></span>
                                <span class="wpmind-geo-option-desc"><?php esc_html_e( '在 robots.txt 中注入 AI 爬虫的 Allow/Disallow 规则', 'wpmind' ); ?></span>
                            </span>
                        </label>
                    </div>
                    <?php if ( $robots_ai_enabled ) : ?>
                    <div class="wpmind-robots-ai-table" style="margin-top:12px;">
                        <table class="widefat striped" style="max-width:100%;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( '爬虫', 'wpmind' ); ?></th>
                                    <th><?php esc_html_e( '公司', 'wpmind' ); ?></th>
                                    <th><?php esc_html_e( '规则', 'wpmind' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( \WPMind\Modules\Geo\RobotsTxtManager::AI_CRAWLERS as $bot => $info ) :
                                    $current_rule = $robots_ai_rules[ $bot ] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $bot ); ?></strong>
                                        <br><small class="description"><?php echo esc_html( $info['description'] ); ?></small>
                                    </td>
                                    <td><?php echo esc_html( $info['company'] ); ?></td>
                                    <td>
                                        <select name="wpmind_robots_ai_rules[<?php echo esc_attr( $bot ); ?>]" class="wpmind-geo-select" style="width:auto;">
                                            <option value="" <?php selected( $current_rule, '' ); ?>><?php esc_html_e( '不管理', 'wpmind' ); ?></option>
                                            <option value="allow" <?php selected( $current_rule, 'allow' ); ?>><?php esc_html_e( 'Allow', 'wpmind' ); ?></option>
                                            <option value="disallow" <?php selected( $current_rule, 'disallow' ); ?>><?php esc_html_e( 'Disallow', 'wpmind' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="wpmind-geo-urls" style="margin-top:12px;">
                        <p class="wpmind-geo-url-title"><?php esc_html_e( '预览地址：', 'wpmind' ); ?></p>
                        <code class="wpmind-geo-url"><?php echo esc_url( home_url( '/robots.txt' ) ); ?></code>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- /crawlers -->

            <!-- 保存按钮 -->
            <div class="wpmind-geo-actions">
                <button type="button" class="button button-primary wpmind-save-geo" id="wpmind-save-geo">
                    <span class="dashicons ri-save-line"></span>
                    <?php esc_html_e( '保存设置', 'wpmind' ); ?>
                </button>
            </div>
        </div><!-- /wpmind-geo-left -->

        <!-- 右栏：爬虫统计 (仅基础设置 tab 显示) -->
        <div class="wpmind-geo-right" data-sidebar-for="basics">
            <div class="wpmind-geo-section">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-bar-chart-2-line"></span>
                    <?php esc_html_e( 'AI 爬虫统计', 'wpmind' ); ?>
                </h3>
                <?php if ( empty( $crawler_stats ) ) : ?>
                <div class="wpmind-geo-empty">
                    <span class="dashicons ri-robot-2-line"></span>
                    <p><?php esc_html_e( '暂无爬虫访问记录', 'wpmind' ); ?></p>
                    <p class="wpmind-geo-empty-hint"><?php esc_html_e( '启用 Markdown Feed 后，AI 爬虫的访问将被记录在这里。', 'wpmind' ); ?></p>
                </div>
                <?php else : ?>
                <div class="wpmind-crawler-list">
                    <?php foreach ( $crawler_stats as $crawler => $data ) :
                        $is_ai = $data['is_ai'];
                    ?>
                    <div class="wpmind-crawler-item <?php echo $is_ai ? 'is-ai' : ''; ?>">
                        <div class="wpmind-crawler-info">
                            <span class="wpmind-crawler-name"><?php echo esc_html( $crawler ); ?></span>
                            <span class="wpmind-crawler-company"><?php echo esc_html( $data['company'] ); ?></span>
                            <?php if ( $is_ai ) : ?>
                            <span class="wpmind-crawler-badge"><?php esc_html_e( 'AI', 'wpmind' ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="wpmind-crawler-stats">
                            <span class="wpmind-crawler-hits"><?php echo esc_html( number_format( $data['total_hits'] ) ); ?></span>
                            <span class="wpmind-crawler-label"><?php esc_html_e( '次访问', 'wpmind' ); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- GEO 说明 -->
            <div class="wpmind-geo-section wpmind-geo-info">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-lightbulb-line"></span>
                    <?php esc_html_e( '什么是 GEO？', 'wpmind' ); ?>
                </h3>
                <div class="wpmind-geo-info-content">
                    <p><?php esc_html_e( 'GEO (Generative Engine Optimization) 是针对 AI 搜索引擎的优化策略，帮助您的内容在 ChatGPT、Claude、Perplexity 等 AI 助手中获得更好的引用和展示。', 'wpmind' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( '提供结构化 Markdown 格式，便于 AI 解析', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '注入权威性信号，提高内容可信度', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '关联 Wikidata 实体，消除语义歧义', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '控制 AI 爬虫访问，保护敏感内容', 'wpmind' ); ?></li>
                    </ul>
                </div>
            </div>
        </div><!-- /wpmind-geo-right -->
    </div><!-- /wpmind-geo-grid -->
</div><!-- /wpmind-geo-panel -->
