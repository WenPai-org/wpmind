<?php
/**
 * WPMind GEO 优化 Tab
 *
 * GEO (Generative Engine Optimization) 设置界面
 *
 * @package WPMind
 * @since 3.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取当前设置
$standalone_feed   = get_option( 'wpmind_standalone_markdown_feed', false );
$geo_enabled       = get_option( 'wpmind_geo_enabled', true );
$chinese_optimize  = get_option( 'wpmind_chinese_optimize', true );
$geo_signals       = get_option( 'wpmind_geo_signals', true );
$crawler_tracking  = get_option( 'wpmind_crawler_tracking', true );
$llms_txt_enabled  = get_option( 'wpmind_llms_txt_enabled', true );
$schema_enabled    = get_option( 'wpmind_schema_enabled', true );
$schema_mode       = get_option( 'wpmind_schema_mode', 'auto' );

// 检查官方插件是否安装
$official_installed = class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' );

// 获取爬虫统计
$crawler_tracker = new \WPMind\GEO\CrawlerTracker();
$crawler_stats   = $crawler_tracker->get_stats();
$ai_summary      = $crawler_tracker->get_ai_summary();
?>

<div class="wpmind-geo-panel">
    <div class="wpmind-geo-header">
        <h2 class="wpmind-geo-title">
            <span class="dashicons ri-robot-2-line"></span>
            <?php esc_html_e( 'GEO 优化', 'wpmind' ); ?>
        </h2>
        <span class="wpmind-geo-badge">v3.1</span>
    </div>

    <p class="wpmind-geo-desc">
        <?php esc_html_e( 'GEO (Generative Engine Optimization) 帮助 AI 搜索引擎更好地理解和引用您的内容。', 'wpmind' ); ?>
    </p>

    <!-- 状态概览 -->
    <div class="wpmind-geo-stats">
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-robot-2-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $ai_summary['total_ai_hits'] ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( 'AI 爬虫访问', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-search-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $ai_summary['total_search_hits'] ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '搜索引擎访问', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-file-text-line"></span>
            </div>
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

    <div class="wpmind-geo-grid">
        <!-- 左栏：设置 -->
        <div class="wpmind-geo-left">
            <!-- Markdown Feed 设置 -->
            <div class="wpmind-geo-section">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-markdown-line"></span>
                    <?php esc_html_e( 'Markdown Feed', 'wpmind' ); ?>
                </h3>
                <p class="wpmind-geo-section-desc">
                    <?php esc_html_e( '为 AI 爬虫提供结构化的 Markdown 格式内容。', 'wpmind' ); ?>
                </p>

                <?php if ( $official_installed ) : ?>
                <div class="wpmind-geo-notice wpmind-geo-notice-info">
                    <span class="dashicons ri-information-line"></span>
                    <?php esc_html_e( '检测到官方 AI Experiments 插件，WPMind 将自动增强其 Markdown Feed。', 'wpmind' ); ?>
                </div>
                <?php endif; ?>

                <div class="wpmind-geo-options">
                    <label class="wpmind-geo-option">
                        <input type="checkbox" name="wpmind_standalone_markdown_feed" value="1"
                               <?php checked( $standalone_feed ); ?>
                               <?php disabled( $official_installed ); ?>>
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
                    <code class="wpmind-geo-url">Accept: text/markdown</code>
                </div>
                <?php endif; ?>
            </div>

            <!-- GEO 增强设置 -->
            <div class="wpmind-geo-section">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-magic-line"></span>
                    <?php esc_html_e( 'GEO 增强', 'wpmind' ); ?>
                </h3>
                <p class="wpmind-geo-section-desc">
                    <?php esc_html_e( '优化内容结构，提高 AI 引用率。', 'wpmind' ); ?>
                </p>

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

            <!-- llms.txt 设置 -->
            <div class="wpmind-geo-section">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-file-text-line"></span>
                    <?php esc_html_e( 'llms.txt', 'wpmind' ); ?>
                    <span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
                </h3>
                <p class="wpmind-geo-section-desc">
                    <?php esc_html_e( '为 AI 爬虫提供站点导航和内容索引。', 'wpmind' ); ?>
                </p>

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

            <!-- Schema.org 设置 -->
            <div class="wpmind-geo-section">
                <h3 class="wpmind-geo-section-title">
                    <span class="dashicons ri-code-s-slash-line"></span>
                    <?php esc_html_e( 'Schema.org', 'wpmind' ); ?>
                    <span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
                </h3>
                <p class="wpmind-geo-section-desc">
                    <?php esc_html_e( '为内容添加结构化数据，帮助 AI 理解语义。', 'wpmind' ); ?>
                </p>

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

            <!-- 保存按钮 -->
            <div class="wpmind-geo-actions">
                <button type="button" class="button button-primary wpmind-save-geo" id="wpmind-save-geo">
                    <span class="dashicons ri-save-line"></span>
                    <?php esc_html_e( '保存设置', 'wpmind' ); ?>
                </button>
            </div>
        </div>

        <!-- 右栏：爬虫统计 -->
        <div class="wpmind-geo-right">
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
                        <li><?php esc_html_e( '优化中文内容，提升本土化体验', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '追踪 AI 爬虫，了解内容被索引情况', 'wpmind' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>