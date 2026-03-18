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
$is_enabled = function ( $option, $default = '1' ) {
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
$ai_indexing       = $is_enabled( 'wpmind_ai_indexing_enabled', '0' );
$ai_declaration    = get_option( 'wpmind_ai_default_declaration', 'original' );
$ai_excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
if ( ! is_array( $ai_excluded_types ) ) {
	$ai_excluded_types = [];
}
$ai_sitemap_enabled = $is_enabled( 'wpmind_ai_sitemap_enabled', '0' );
$ai_sitemap_max     = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );
$robots_ai_enabled  = $is_enabled( 'wpmind_robots_ai_enabled', '0' );
$robots_ai_rules    = get_option( 'wpmind_robots_ai_rules', [] );
if ( ! is_array( $robots_ai_rules ) ) {
	$robots_ai_rules = [];
}
$ai_summary_enabled    = $is_enabled( 'wpmind_ai_summary_enabled', '0' );
$ai_summary_fallback   = get_option( 'wpmind_ai_summary_fallback', 'excerpt' );
$entity_linker_enabled = $is_enabled( 'wpmind_entity_linker_enabled', '0' );
$brand_entity_enabled  = $is_enabled( 'wpmind_brand_entity_enabled', '0' );
$brand_org_type        = get_option( 'wpmind_brand_org_type', 'Organization' );

// 检查官方插件是否安装
$official_installed = class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' );

// 获取爬虫统计
$crawler_tracker = new \WPMind\Modules\Geo\CrawlerTracker();
$crawler_stats   = $crawler_tracker->get_stats();
$ai_summary_data = $crawler_tracker->get_ai_summary();

// 知识库链接
$learn_more_url = 'https://wpcy.com/c/wpmind';
?>

<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-robot-2-line"></span>
		<?php esc_html_e( 'GEO 优化', 'wpmind' ); ?>
	</h2>
	<span class="wpmind-module-badge">v3.10</span>
</div>

<div class="wpmind-tab-pane-body">
<div class="wpmind-geo-panel">

	<p class="wpmind-module-desc">
		<?php esc_html_e( 'GEO (Generative Engine Optimization) 帮助 AI 搜索引擎更好地理解和引用您的内容。', 'wpmind' ); ?>
	</p>

	<!-- 状态概览 -->
	<div class="wpmind-module-stats">
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
	<div class="wpmind-module-subtabs">
		<button type="button" class="wpmind-module-subtab active" data-tab="basics">
			<span class="dashicons ri-settings-3-line"></span>
			<?php esc_html_e( '基础设置', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="content">
			<span class="dashicons ri-file-text-line"></span>
			<?php esc_html_e( '内容输出', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="schema">
			<span class="dashicons ri-code-s-slash-line"></span>
			<?php esc_html_e( '结构化数据', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="control">
			<span class="dashicons ri-shield-check-line"></span>
			<?php esc_html_e( 'AI 索引', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="crawlers">
			<span class="dashicons ri-robot-line"></span>
			<?php esc_html_e( '爬虫管理', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="brand">
			<span class="dashicons ri-building-line"></span>
			<?php esc_html_e( '品牌实体', 'wpmind' ); ?>
		</button>
	</div>

	<div class="wpmind-geo-content">

			<!-- ========== 基础设置 Tab ========== -->
			<div class="wpmind-module-tab-panel active" data-panel="basics">
				<div class="wpmind-geo-grid">
					<div class="wpmind-geo-left">
						<div class="wpmind-geo-section">
							<h3 class="wpmind-geo-section-title">
								<span class="dashicons ri-magic-line"></span>
								<?php esc_html_e( 'GEO 增强', 'wpmind' ); ?>
							</h3>
							<p class="wpmind-geo-section-desc"><?php esc_html_e( '优化内容结构，提高 AI 引用率。', 'wpmind' ); ?></p>
							<div class="wpmind-geo-options">
								<label class="wpmind-module-option">
									<input type="checkbox" name="wpmind_geo_enabled" value="1" <?php checked( $geo_enabled ); ?>>
									<span class="wpmind-module-option-content">
										<span class="wpmind-module-option-title"><?php esc_html_e( '启用 GEO 增强', 'wpmind' ); ?></span>
										<span class="wpmind-module-option-desc"><?php esc_html_e( '总开关，控制所有 GEO 优化功能', 'wpmind' ); ?></span>
									</span>
								</label>
								<label class="wpmind-module-option">
									<input type="checkbox" name="wpmind_chinese_optimize" value="1" <?php checked( $chinese_optimize ); ?>>
									<span class="wpmind-module-option-content">
										<span class="wpmind-module-option-title"><?php esc_html_e( '中文内容优化', 'wpmind' ); ?></span>
										<span class="wpmind-module-option-desc"><?php esc_html_e( '优化中英文混排、标点符号、段落结构', 'wpmind' ); ?></span>
									</span>
								</label>
								<label class="wpmind-module-option">
									<input type="checkbox" name="wpmind_geo_signals" value="1" <?php checked( $geo_signals ); ?>>
									<span class="wpmind-module-option-content">
										<span class="wpmind-module-option-title"><?php esc_html_e( 'GEO 信号注入', 'wpmind' ); ?></span>
										<span class="wpmind-module-option-desc"><?php esc_html_e( '添加作者信息、发布日期、引用格式等权威性信号', 'wpmind' ); ?></span>
									</span>
								</label>
								<label class="wpmind-module-option">
									<input type="checkbox" name="wpmind_crawler_tracking" value="1" <?php checked( $crawler_tracking ); ?>>
									<span class="wpmind-module-option-content">
										<span class="wpmind-module-option-title"><?php esc_html_e( 'AI 爬虫追踪', 'wpmind' ); ?></span>
										<span class="wpmind-module-option-desc"><?php esc_html_e( '记录 GPTBot、ClaudeBot 等 AI 爬虫的访问', 'wpmind' ); ?></span>
									</span>
								</label>
							</div>
						</div>
					</div><!-- /left -->

					<!-- 右栏：爬虫统计 + GEO 说明 -->
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
								<?php
								foreach ( $crawler_stats as $crawler => $data ) :
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
								<p><a href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '了解更多 →', 'wpmind' ); ?></a></p>
							</div>
						</div>
					</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /basics -->
			<div class="wpmind-module-tab-panel" data-panel="content">
				<div class="wpmind-geo-grid">
				<div class="wpmind-geo-left">
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
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_standalone_markdown_feed" value="1"
									<?php checked( $standalone_feed ); ?> <?php disabled( $official_installed ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用独立 Markdown Feed', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '提供 /?feed=markdown 端点和 .md 后缀访问', 'wpmind' ); ?></span>
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
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_llms_txt_enabled" value="1" <?php checked( $llms_txt_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 llms.txt', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '在 /llms.txt 提供站点内容导航', 'wpmind' ); ?></span>
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
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_ai_sitemap_enabled" value="1" <?php checked( $ai_sitemap_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 AI Sitemap', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '在 /ai-sitemap.xml 提供 AI 专属站点地图', 'wpmind' ); ?></span>
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
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_ai_summary_enabled" value="1" <?php checked( $ai_summary_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 AI 摘要', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '在编辑器中添加 AI 摘要字段，输出为 meta 标签和 Schema.org abstract', 'wpmind' ); ?></span>
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
				</div><!-- /left -->
				<div class="wpmind-geo-right">
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-lightbulb-line"></span>
							<?php esc_html_e( 'AI 内容分发', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( 'AI 搜索引擎通过 Markdown Feed、llms.txt 和 AI Sitemap 等专用通道获取内容。与传统 HTML 不同，这些格式去除了导航、广告等噪音，让 AI 能直接解析核心内容。', 'wpmind' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Markdown Feed 提供纯净的结构化内容', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'llms.txt 是 AI 时代的 robots.txt，引导 AI 发现内容', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'AI Sitemap 包含内容声明等 AI 专属元数据', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'AI 摘要让您主动控制 AI 如何描述文章', 'wpmind' ); ?></li>
							</ul>
							<p><a href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '了解更多 →', 'wpmind' ); ?></a></p>
						</div>
					</div>
				</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /content -->

			<!-- ========== 结构化数据 Tab ========== -->
			<div class="wpmind-module-tab-panel" data-panel="schema">
				<div class="wpmind-geo-grid">
				<div class="wpmind-geo-left">
				<!-- Schema.org -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-code-s-slash-line"></span>
						<?php esc_html_e( 'Schema.org', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '为内容添加结构化数据，帮助 AI 理解语义。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-options">
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_schema_enabled" value="1" <?php checked( $schema_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 Schema.org', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '自动为文章添加 Article 结构化数据', 'wpmind' ); ?></span>
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
				</div><!-- /left -->
				<div class="wpmind-geo-right">
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-lightbulb-line"></span>
							<?php esc_html_e( 'Schema.org 与 AI 搜索', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( 'Schema.org JSON-LD 是 AI 理解页面语义的核心通道。与 HTML 不同，结构化数据直接告诉 AI "这是什么类型的内容、谁写的、什么时候发布的"。', 'wpmind' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Article/BlogPosting/NewsArticle 类型自动识别', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'headline, author, datePublished 等核心属性', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'publisher 由品牌实体自动增强', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'wordCount, articleSection, keywords 辅助 AI 分类', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'inLanguage 帮助多语言内容正确归类', 'wpmind' ); ?></li>
							</ul>
						</div>
					</div>
					<?php if ( $schema_enabled ) : ?>
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-code-s-slash-line"></span>
							<?php esc_html_e( '输出属性一览', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( '每篇文章自动输出以下 JSON-LD 属性：', 'wpmind' ); ?></p>
							<table class="widefat striped" style="font-size:12px;">
								<tbody>
									<tr><td><code>@type</code></td><td><?php esc_html_e( '48h 内 NewsArticle，之后 BlogPosting，页面 Article', 'wpmind' ); ?></td></tr>
									<tr><td><code>headline</code></td><td><?php esc_html_e( '文章标题', 'wpmind' ); ?></td></tr>
									<tr><td><code>author</code></td><td><?php esc_html_e( 'Person: name + url + sameAs (社交档案)', 'wpmind' ); ?></td></tr>
									<tr><td><code>publisher</code></td><td><?php esc_html_e( 'Organization (品牌实体增强)', 'wpmind' ); ?></td></tr>
									<tr><td><code>image</code></td><td><?php esc_html_e( '特色图片或正文首图', 'wpmind' ); ?></td></tr>
									<tr><td><code>description</code></td><td><?php esc_html_e( '文章摘要', 'wpmind' ); ?></td></tr>
									<tr><td><code>wordCount</code></td><td><?php esc_html_e( '字数统计', 'wpmind' ); ?></td></tr>
									<tr><td><code>articleSection</code></td><td><?php esc_html_e( '所属分类', 'wpmind' ); ?></td></tr>
									<tr><td><code>keywords</code></td><td><?php esc_html_e( '文章标签', 'wpmind' ); ?></td></tr>
									<tr><td><code>inLanguage</code></td><td><?php esc_html_e( '内容语言', 'wpmind' ); ?></td></tr>
									<tr><td colspan="2" style="font-weight:600;padding-top:8px;"><?php esc_html_e( 'BreadcrumbList (每篇文章)', 'wpmind' ); ?></td></tr>
									<tr><td><code>itemListElement</code></td><td><?php esc_html_e( '首页 > 分类层级 > 当前页', 'wpmind' ); ?></td></tr>
									<tr><td colspan="2" style="font-weight:600;padding-top:8px;"><?php esc_html_e( 'WebSite (仅首页)', 'wpmind' ); ?></td></tr>
									<tr><td><code>potentialAction</code></td><td><?php esc_html_e( 'SearchAction 站内搜索', 'wpmind' ); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-eye-line"></span>
							<?php esc_html_e( 'JSON-LD 预览', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( '基于当前设置生成的示例输出：', 'wpmind' ); ?></p>
							<?php
							// Generate sample schema preview.
							$preview_brand_name = get_option( 'wpmind_brand_name', '' ) ?: get_bloginfo( 'name' );
							$preview_org_type   = get_option( 'wpmind_brand_org_type', 'Organization' );
							$preview_brand_url  = get_option( 'wpmind_brand_url', '' ) ?: home_url( '/' );
							$preview_author     = [
								'@type' => 'Person',
								'name'  => wp_get_current_user()->display_name ?: 'Author',
								'url'   => home_url( '/author/example/' ),
							];
							$preview_schema     = [
								'@context'         => 'https://schema.org',
								'@type'            => 'BlogPosting',
								'headline'         => __( '示例文章标题', 'wpmind' ),
								'author'           => $preview_author,
								'datePublished'    => wp_date( 'c' ),
								'publisher'        => [
									'@type' => $preview_org_type,
									'name'  => $preview_brand_name,
									'url'   => $preview_brand_url,
								],
								'description'      => __( '文章摘要内容...', 'wpmind' ),
								'mainEntityOfPage' => [
									'@type' => 'WebPage',
									'@id'   => home_url( '/example-post/' ),
								],
								'inLanguage'       => get_locale(),
							];
							$preview_json       = wp_json_encode( $preview_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

							// BreadcrumbList preview.
							$preview_breadcrumb      = [
								'@context'        => 'https://schema.org',
								'@type'           => 'BreadcrumbList',
								'itemListElement' => [
									[
										'@type'    => 'ListItem',
										'position' => 1,
										'name'     => get_bloginfo( 'name' ),
										'item'     => home_url( '/' ),
									],
									[
										'@type'    => 'ListItem',
										'position' => 2,
										'name'     => __( '示例分类', 'wpmind' ),
										'item'     => home_url( '/category/example/' ),
									],
									[
										'@type'    => 'ListItem',
										'position' => 3,
										'name'     => __( '示例文章标题', 'wpmind' ),
									],
								],
							];
							$preview_breadcrumb_json = wp_json_encode( $preview_breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

							// WebSite preview.
							$preview_website = [
								'@context'        => 'https://schema.org',
								'@type'           => 'WebSite',
								'name'            => $preview_brand_name,
								'url'             => home_url( '/' ),
								'potentialAction' => [
									'@type'       => 'SearchAction',
									'target'      => home_url( '/?s={search_term_string}' ),
									'query-input' => 'required name=search_term_string',
								],
								'inLanguage'      => get_locale(),
							];
							$site_desc       = get_bloginfo( 'description' );
							if ( ! empty( $site_desc ) ) {
								$preview_website['description'] = $site_desc;
							}
							$preview_website_json = wp_json_encode( $preview_website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
							?>

							<!-- Schema type tabs -->
							<div class="wpmind-schema-preview-tabs" style="display:flex;gap:4px;margin-bottom:8px;">
								<button type="button" class="button button-small wpmind-schema-tab active" data-preview="article"><?php esc_html_e( 'Article', 'wpmind' ); ?></button>
								<button type="button" class="button button-small wpmind-schema-tab" data-preview="breadcrumb"><?php esc_html_e( 'Breadcrumb', 'wpmind' ); ?></button>
								<button type="button" class="button button-small wpmind-schema-tab" data-preview="website"><?php esc_html_e( 'WebSite', 'wpmind' ); ?></button>
							</div>

							<div class="wpmind-schema-preview-panel" data-preview-panel="article">
								<pre style="background:var(--wpmind-gray-100);padding:12px;border-radius:6px;font-size:11px;line-height:1.5;overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;"><code><?php echo esc_html( $preview_json ); ?></code></pre>
								<p class="description" style="margin-top:8px;"><?php esc_html_e( '每篇文章/页面自动输出。品牌实体启用后 publisher 将自动增强。', 'wpmind' ); ?></p>
							</div>
							<div class="wpmind-schema-preview-panel" data-preview-panel="breadcrumb" style="display:none;">
								<pre style="background:var(--wpmind-gray-100);padding:12px;border-radius:6px;font-size:11px;line-height:1.5;overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;"><code><?php echo esc_html( $preview_breadcrumb_json ); ?></code></pre>
								<p class="description" style="margin-top:8px;"><?php esc_html_e( '每篇文章/页面自动输出面包屑导航。支持分类层级和页面父子关系。', 'wpmind' ); ?></p>
							</div>
							<div class="wpmind-schema-preview-panel" data-preview-panel="website" style="display:none;">
								<pre style="background:var(--wpmind-gray-100);padding:12px;border-radius:6px;font-size:11px;line-height:1.5;overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;"><code><?php echo esc_html( $preview_website_json ); ?></code></pre>
								<p class="description" style="margin-top:8px;"><?php esc_html_e( '仅在首页输出。包含站内搜索 SearchAction，帮助搜索引擎展示站内搜索框。', 'wpmind' ); ?></p>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /schema -->

			<!-- ========== AI 控制 Tab ========== -->
			<div class="wpmind-module-tab-panel" data-panel="control">
				<div class="wpmind-geo-grid">
				<div class="wpmind-geo-left">
				<!-- AI 索引指令 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-shield-check-line"></span>
						<?php esc_html_e( 'AI 索引指令', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '控制 AI 爬虫对内容的索引和训练权限。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-options">
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_ai_indexing_enabled" value="1" <?php checked( $ai_indexing ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 AI 索引指令', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '输出 noai/nollm meta 标签和 X-Robots-Tag HTTP 头', 'wpmind' ); ?></span>
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
				</div><!-- /left -->
				<div class="wpmind-geo-right">
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-lightbulb-line"></span>
							<?php esc_html_e( 'AI 索引权限控制', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( '随着 AI 爬虫大量抓取网站内容用于训练和索引，内容创作者需要明确声明权限意愿。noai 和 nollm 是新兴的 meta robots 指令标准。', 'wpmind' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'noai — 禁止 AI 搜索引擎索引内容', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'nollm — 禁止将内容用于 LLM 训练', 'wpmind' ); ?></li>
								<li><?php esc_html_e( '内容声明标注 AI 参与程度（原创/辅助/生成）', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'X-Robots-Tag HTTP 头对非 HTML 资源生效', 'wpmind' ); ?></li>
							</ul>
							<p><a href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '了解更多 →', 'wpmind' ); ?></a></p>
						</div>
					</div>
				</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /control -->

			<!-- ========== 爬虫管理 Tab ========== -->
			<div class="wpmind-module-tab-panel" data-panel="crawlers">
				<div class="wpmind-geo-grid">
				<div class="wpmind-geo-left">
				<!-- robots.txt AI 管理 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-robot-line"></span>
						<?php esc_html_e( 'robots.txt AI 管理', 'wpmind' ); ?>
						<span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '通过 robots.txt 控制 AI 爬虫的访问权限，不修改物理文件。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-options">
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_robots_ai_enabled" value="1" <?php checked( $robots_ai_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用 robots.txt AI 管理', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '在 robots.txt 中注入 AI 爬虫的 Allow/Disallow 规则', 'wpmind' ); ?></span>
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
								<?php
								foreach ( \WPMind\Modules\Geo\RobotsTxtManager::AI_CRAWLERS as $bot => $info ) :
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
				</div><!-- /left -->
				<div class="wpmind-geo-right">
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-lightbulb-line"></span>
							<?php esc_html_e( 'AI 爬虫生态', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( '目前活跃的 AI 爬虫超过 20 种，来自 OpenAI、Anthropic、Google、百度等公司。通过 robots.txt 可以精细控制每个爬虫的访问权限。', 'wpmind' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'GPTBot、ClaudeBot 是最活跃的 AI 爬虫', 'wpmind' ); ?></li>
								<li><?php esc_html_e( '中国 AI 爬虫：百度蜘蛛、搜狗、360、神马', 'wpmind' ); ?></li>
								<li><?php esc_html_e( '规则通过 WordPress 过滤器注入，不修改物理文件', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'Allow 允许爬取，Disallow 禁止爬取', 'wpmind' ); ?></li>
							</ul>
							<p><a href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '了解更多 →', 'wpmind' ); ?></a></p>
						</div>
					</div>
				</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /crawlers -->

			<!-- ========== 品牌实体 Tab ========== -->
			<div class="wpmind-module-tab-panel" data-panel="brand">
				<div class="wpmind-geo-grid">
				<div class="wpmind-geo-left">
				<!-- 组织基础 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-building-line"></span>
						<?php esc_html_e( '组织基础', 'wpmind' ); ?>
						<span class="wpmind-geo-new-badge"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '定义品牌实体信息，增强 publisher Schema 并在首页输出 Organization JSON-LD。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-options">
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_brand_entity_enabled" value="1" <?php checked( $brand_entity_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用品牌实体', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '丰富文章 publisher Schema，并在首页输出独立 Organization JSON-LD', 'wpmind' ); ?></span>
							</span>
						</label>
					</div>
					<?php if ( $brand_entity_enabled ) : ?>
					<div class="wpmind-geo-select-group wpmind-brand-fields">
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '组织类型', 'wpmind' ); ?></label>
							<select name="wpmind_brand_org_type" class="wpmind-geo-select">
								<option value="Organization" <?php selected( $brand_org_type, 'Organization' ); ?>><?php esc_html_e( 'Organization (通用)', 'wpmind' ); ?></option>
								<option value="Corporation" <?php selected( $brand_org_type, 'Corporation' ); ?>><?php esc_html_e( 'Corporation (公司)', 'wpmind' ); ?></option>
								<option value="LocalBusiness" <?php selected( $brand_org_type, 'LocalBusiness' ); ?>><?php esc_html_e( 'LocalBusiness (本地商户)', 'wpmind' ); ?></option>
								<option value="OnlineBusiness" <?php selected( $brand_org_type, 'OnlineBusiness' ); ?>><?php esc_html_e( 'OnlineBusiness (在线业务)', 'wpmind' ); ?></option>
								<option value="NewsMediaOrganization" <?php selected( $brand_org_type, 'NewsMediaOrganization' ); ?>><?php esc_html_e( 'NewsMediaOrganization (新闻媒体)', 'wpmind' ); ?></option>
							</select>
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '品牌名称', 'wpmind' ); ?></label>
							<input type="text" name="wpmind_brand_name" value="<?php echo esc_attr( get_option( 'wpmind_brand_name', '' ) ); ?>"
									placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description"><?php esc_html_e( '留空则使用站点名称', 'wpmind' ); ?></p>
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '品牌描述', 'wpmind' ); ?></label>
							<textarea name="wpmind_brand_description" rows="3"
										placeholder="<?php esc_attr_e( '简要描述您的品牌/组织', 'wpmind' ); ?>"><?php echo esc_textarea( get_option( 'wpmind_brand_description', '' ) ); ?></textarea>
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '品牌 URL', 'wpmind' ); ?></label>
							<input type="url" name="wpmind_brand_url" value="<?php echo esc_attr( get_option( 'wpmind_brand_url', '' ) ); ?>"
									placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
							<p class="description"><?php esc_html_e( '留空则使用站点首页地址', 'wpmind' ); ?></p>
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '创立日期', 'wpmind' ); ?></label>
							<input type="text" name="wpmind_brand_founding_date" value="<?php echo esc_attr( get_option( 'wpmind_brand_founding_date', '' ) ); ?>"
									placeholder="<?php esc_attr_e( '例如：2020 或 2020-01-01', 'wpmind' ); ?>">
						</div>
					</div>
					<?php endif; ?>
				</div>

				<?php if ( $brand_entity_enabled ) : ?>
				<!-- 社交档案 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-share-line"></span>
						<?php esc_html_e( '社交档案', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '社交平台主页 URL，将作为 Schema.org sameAs 输出。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-select-group wpmind-brand-fields">
					<?php
					$social_fields = [
						'wpmind_brand_social_facebook' => 'Facebook',
						'wpmind_brand_social_twitter'  => 'X (Twitter)',
						'wpmind_brand_social_linkedin' => 'LinkedIn',
						'wpmind_brand_social_youtube'  => 'YouTube',
						'wpmind_brand_social_github'   => 'GitHub',
						'wpmind_brand_social_weibo'    => __( '微博', 'wpmind' ),
						'wpmind_brand_social_zhihu'    => __( '知乎', 'wpmind' ),
						'wpmind_brand_social_wechat'   => __( '微信公众号', 'wpmind' ),
					];
					foreach ( $social_fields as $field_name => $label ) :
						?>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php echo esc_html( $label ); ?></label>
							<input type="url" name="<?php echo esc_attr( $field_name ); ?>"
									value="<?php echo esc_attr( get_option( $field_name, '' ) ); ?>"
									placeholder="https://">
						</div>
					<?php endforeach; ?>
					</div>
				</div>

				<!-- 联系方式 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-mail-line"></span>
						<?php esc_html_e( '联系方式', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '可选的联系信息，将作为 Schema.org contactPoint 输出。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-select-group wpmind-brand-fields">
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '联系邮箱', 'wpmind' ); ?></label>
							<input type="email" name="wpmind_brand_contact_email"
									value="<?php echo esc_attr( get_option( 'wpmind_brand_contact_email', '' ) ); ?>"
									placeholder="hello@example.com">
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( '联系电话', 'wpmind' ); ?></label>
							<input type="tel" name="wpmind_brand_contact_phone"
									value="<?php echo esc_attr( get_option( 'wpmind_brand_contact_phone', '' ) ); ?>"
									placeholder="+86-xxx-xxxx-xxxx">
						</div>
					</div>
				</div>

				<!-- Knowledge Graph -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-global-line"></span>
						<?php esc_html_e( 'Knowledge Graph', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '关联品牌的 Wikidata/Wikipedia 页面，强化知识图谱信号。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-select-group wpmind-brand-fields">
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( 'Wikidata URL', 'wpmind' ); ?></label>
							<input type="url" name="wpmind_brand_wikidata_url"
									value="<?php echo esc_attr( get_option( 'wpmind_brand_wikidata_url', '' ) ); ?>"
									placeholder="https://www.wikidata.org/wiki/Q...">
						</div>
						<div class="wpmind-brand-field">
							<label class="wpmind-brand-label"><?php esc_html_e( 'Wikipedia URL', 'wpmind' ); ?></label>
							<input type="url" name="wpmind_brand_wikipedia_url"
									value="<?php echo esc_attr( get_option( 'wpmind_brand_wikipedia_url', '' ) ); ?>"
									placeholder="https://en.wikipedia.org/wiki/...">
						</div>
					</div>
				</div>

				<!-- 文章实体关联 -->
				<div class="wpmind-geo-section">
					<h3 class="wpmind-geo-section-title">
						<span class="dashicons ri-links-line"></span>
						<?php esc_html_e( '文章实体关联', 'wpmind' ); ?>
					</h3>
					<p class="wpmind-geo-section-desc"><?php esc_html_e( '将每篇文章关联到 Wikidata/Wikipedia 实体，帮助 AI 消除歧义并建立权威性。', 'wpmind' ); ?></p>
					<div class="wpmind-geo-options">
						<label class="wpmind-module-option">
							<input type="checkbox" name="wpmind_entity_linker_enabled" value="1" <?php checked( $entity_linker_enabled ); ?>>
							<span class="wpmind-module-option-content">
								<span class="wpmind-module-option-title"><?php esc_html_e( '启用文章实体关联', 'wpmind' ); ?></span>
								<span class="wpmind-module-option-desc"><?php esc_html_e( '在编辑器中添加实体关联字段，输出为 Schema.org about.sameAs', 'wpmind' ); ?></span>
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
				<?php endif; ?>
				</div><!-- /left -->
				<div class="wpmind-geo-right">
					<div class="wpmind-geo-section wpmind-geo-info">
						<h3 class="wpmind-geo-section-title">
							<span class="dashicons ri-lightbulb-line"></span>
							<?php esc_html_e( '实体与 AI 搜索', 'wpmind' ); ?>
						</h3>
						<div class="wpmind-geo-info-content">
							<p><?php esc_html_e( '品牌实体是 GEO 的基础层。AI 搜索引擎必须先"理解"品牌身份，才能信任并推荐其内容。', 'wpmind' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Organization JSON-LD 在首页输出，是知识图谱的主要信号源', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'sameAs 关联社交档案，帮助 AI 验证品牌真实性', 'wpmind' ); ?></li>
								<li><?php esc_html_e( 'Wikidata/Wikipedia 链接强化实体消歧', 'wpmind' ); ?></li>
								<li><?php esc_html_e( '文章 publisher 自动增强，无需逐篇配置', 'wpmind' ); ?></li>
								<li><?php esc_html_e( '文章实体关联通过 about.sameAs 消除语义歧义', 'wpmind' ); ?></li>
							</ul>
							<p><a href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '了解更多 →', 'wpmind' ); ?></a></p>
						</div>
					</div>
				</div><!-- /right -->
				</div><!-- /grid -->
			</div><!-- /brand -->

			<!-- 保存按钮 -->
			<div class="wpmind-module-actions">
				<button type="button" class="button button-primary wpmind-save-geo" id="wpmind-save-geo">
					<span class="dashicons ri-save-line"></span>
					<?php esc_html_e( '保存设置', 'wpmind' ); ?>
				</button>
			</div>

	</div><!-- /wpmind-geo-content -->
</div><!-- /wpmind-geo-panel -->
</div><!-- /wpmind-tab-pane-body -->
