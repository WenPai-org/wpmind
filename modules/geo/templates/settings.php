<?php
/**
 * GEO 优化模块设置（kit 化）。
 * 保留 JS 契约：.wpmind-geo-panel / .wpmind-module-subtab[data-tab] /
 * .wpmind-module-tab-panel[data-panel] / #wpmind-save-geo /
 * .wpmind-schema-preview-tabs .wpmind-schema-tab / .wpmind-schema-preview-panel。
 * input name 全部不变（admin-geo.js saveSettings 逐字段收集）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;

$is_enabled = static function ( $option, $default = '1' ) {
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
$ai_sitemap_enabled   = $is_enabled( 'wpmind_ai_sitemap_enabled', '0' );
$ai_sitemap_max       = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );
$robots_ai_enabled    = $is_enabled( 'wpmind_robots_ai_enabled', '0' );
$robots_ai_rules      = get_option( 'wpmind_robots_ai_rules', [] );
if ( ! is_array( $robots_ai_rules ) ) {
	$robots_ai_rules = [];
}
$ai_summary_enabled    = $is_enabled( 'wpmind_ai_summary_enabled', '0' );
$ai_summary_fallback   = get_option( 'wpmind_ai_summary_fallback', 'excerpt' );
$entity_linker_enabled = $is_enabled( 'wpmind_entity_linker_enabled', '0' );
$brand_entity_enabled  = $is_enabled( 'wpmind_brand_entity_enabled', '0' );
$brand_org_type        = get_option( 'wpmind_brand_org_type', 'Organization' );

$official_installed = class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' );

$crawler_tracker = new \WPMind\Modules\Geo\CrawlerTracker();
$crawler_stats   = $crawler_tracker->get_stats();
$ai_summary_data = $crawler_tracker->get_ai_summary();

$learn_more_url = 'https://wpcy.com/c/wpmind';

// kit 字段行小工具：switch 行。
$wpmind_switch = static function ( string $id, string $name, bool $checked, string $label, string $desc = '', bool $disabled = false ): void {
	echo '<div class="wenpai-field wenpai-field--toggle field wenpai-field--row">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl">'
		. '<span class="wenpai-switch">'
		. '<input type="checkbox" role="switch" class="wenpai-switch__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked' : '' ) . ( $disabled ? ' disabled' : '' ) . '>'
		. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
		. '<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on">' . esc_html__( '已开启', 'wpmind' ) . '</span><span class="wenpai-switch__off">' . esc_html__( '未开启', 'wpmind' ) . '</span></span>'
		. '</span>'
		. ( '' !== $desc ? '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>' : '' )
		. '</div></div>';
};

// 信息卡小工具。
$wpmind_info_card = static function ( string $icon, string $title, string $body_html ): void {
	echo '<section class="card wpmind-geo-info"><div class="card-head"><span class="tile">' . AdminIcons::svg( $icon ) . '</span><div><h2 class="card-title">' . esc_html( $title ) . '</h2></div></div><div class="wpmind-geo-info-body">' . $body_html . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-built trusted HTML
};

$wpmind_info_list = static function ( array $items, string $more_url = '' ): string {
	$html = '<p>' . esc_html__( '面向 AI 搜索引擎的优化说明。', 'wpmind' ) . '</p><ul>';
	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item ) . '</li>';
	}
	$html .= '</ul>';
	if ( '' !== $more_url ) {
		$html .= '<p><a href="' . esc_url( $more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>';
	}
	return $html;
};
?>

<div class="wpmind-geo-panel">

	<div class="wenpai-subnav">
		<div class="seg wpmind-module-subtabs">
			<button type="button" class="wpmind-module-subtab active" data-tab="basics"><?php esc_html_e( '基础设置', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="content"><?php esc_html_e( '内容输出', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="schema"><?php esc_html_e( '结构化数据', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="control"><?php esc_html_e( 'AI 索引', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="crawlers"><?php esc_html_e( '爬虫管理', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="brand"><?php esc_html_e( '品牌实体', 'wpmind' ); ?></button>
		</div>
	</div>

	<div class="wpmind-geo-content">

		<section class="card">
			<div class="wpmind-statbar">
				<div class="wpmind-statbar-item">
					<?php echo AdminIcons::svg( 'robot', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wpmind-statbar-text">
						<div class="k"><?php esc_html_e( 'AI 爬虫访问', 'wpmind' ); ?></div>
						<div class="v"><?php echo esc_html( number_format_i18n( (int) ( $ai_summary_data['total_ai_hits'] ?? 0 ) ) ); ?></div>
					</div>
				</div>
				<div class="wpmind-statbar-item">
					<?php echo AdminIcons::svg( 'search', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wpmind-statbar-text">
						<div class="k"><?php esc_html_e( '搜索引擎访问', 'wpmind' ); ?></div>
						<div class="v"><?php echo esc_html( number_format_i18n( (int) ( $ai_summary_data['total_search_hits'] ?? 0 ) ) ); ?></div>
					</div>
				</div>
				<div class="wpmind-statbar-item">
					<?php echo AdminIcons::svg( 'target', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wpmind-statbar-text">
						<div class="k"><?php esc_html_e( '已识别爬虫', 'wpmind' ); ?></div>
						<div class="v"><?php echo esc_html( (string) count( (array) ( $ai_summary_data['ai_crawlers'] ?? [] ) ) ); ?></div>
					</div>
				</div>
				<div class="wpmind-statbar-item">
					<?php echo AdminIcons::svg( 'doc', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wpmind-statbar-text">
						<div class="k"><?php esc_html_e( 'Markdown Feed', 'wpmind' ); ?></div>
						<div class="v"><?php echo $standalone_feed || get_option( 'ai_experiments_markdown_feed_enabled', false ) ? esc_html__( '已启用', 'wpmind' ) : esc_html__( '未启用', 'wpmind' ); ?></div>
					</div>
				</div>
			</div>
		</section>

		<!-- ========== 基础设置 ========== -->
		<div class="wpmind-module-tab-panel active" data-panel="basics">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'robot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'GEO 增强', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '优化内容结构，提高 AI 引用率。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php
						$wpmind_switch( 'wpmind-geo-enabled', 'wpmind_geo_enabled', $geo_enabled, __( '启用 GEO 增强', 'wpmind' ), __( '总开关，控制所有 GEO 优化功能。', 'wpmind' ) );
						$wpmind_switch( 'wpmind-geo-zh', 'wpmind_chinese_optimize', $chinese_optimize, __( '中文内容优化', 'wpmind' ), __( '优化中英文混排、标点符号、段落结构。', 'wpmind' ) );
						$wpmind_switch( 'wpmind-geo-signals', 'wpmind_geo_signals', $geo_signals, __( 'GEO 信号注入', 'wpmind' ), __( '添加作者信息、发布日期、引用格式等权威性信号。', 'wpmind' ) );
						$wpmind_switch( 'wpmind-geo-crawler', 'wpmind_crawler_tracking', $crawler_tracking, __( 'AI 爬虫追踪', 'wpmind' ), __( '记录 GPTBot、ClaudeBot 等 AI 爬虫的访问。', 'wpmind' ) );
						?>
					</section>
				</div>
				<div class="wpmind-geo-side">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'analytics' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'AI 爬虫统计', 'wpmind' ); ?></h2>
							</div>
						</div>
						<?php if ( empty( $crawler_stats ) ) : ?>
						<p class="meta"><?php esc_html_e( '暂无爬虫访问记录。启用追踪后，AI 爬虫的访问将记录在这里。', 'wpmind' ); ?></p>
						<?php else : ?>
						<div class="wpmind-crawler-list">
							<?php foreach ( $crawler_stats as $crawler => $data ) : ?>
							<div class="simple-row is-static">
								<div class="tile<?php echo $data['is_ai'] ? ' ok' : ''; ?>"><?php echo AdminIcons::svg( 'robot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></div>
								<div>
									<div class="t"><?php echo esc_html( $crawler ); ?> <?php if ( $data['is_ai'] ) : ?><span class="pill"><?php esc_html_e( 'AI', 'wpmind' ); ?></span><?php endif; ?></div>
									<div class="d"><?php echo esc_html( $data['company'] ); ?></div>
								</div>
								<div class="r"><span class="meta"><?php echo esc_html( number_format( $data['total_hits'] ) ); ?> <?php esc_html_e( '次', 'wpmind' ); ?></span></div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</section>
					<?php
					$wpmind_info_card(
						'info',
						__( '什么是 GEO？', 'wpmind' ),
						'<p>' . esc_html__( 'GEO (Generative Engine Optimization) 是针对 AI 搜索引擎的优化策略，帮助内容在 ChatGPT、Claude、Perplexity 等 AI 助手中获得更好的引用和展示。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( '提供结构化 Markdown 格式，便于 AI 解析', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '注入权威性信号，提高内容可信度', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '关联 Wikidata 实体，消除语义歧义', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '控制 AI 爬虫访问，保护敏感内容', 'wpmind' ) . '</li></ul>'
						. '<p><a href="' . esc_url( $learn_more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>'
					);
					?>
				</div>
			</div>
		</div>

		<!-- ========== 内容输出 ========== -->
		<div class="wpmind-module-tab-panel" data-panel="content">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'Markdown Feed', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '为 AI 爬虫提供结构化的 Markdown 格式内容。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php if ( $official_installed ) : ?>
						<p class="meta"><?php esc_html_e( '检测到官方 AI Experiments 插件，WPMind 将自动增强其 Markdown Feed。', 'wpmind' ); ?></p>
						<?php endif; ?>
						<?php $wpmind_switch( 'wpmind-md-feed', 'wpmind_standalone_markdown_feed', $standalone_feed, __( '启用独立 Markdown Feed', 'wpmind' ), __( '提供 /?feed=markdown 端点和 .md 后缀访问。', 'wpmind' ), $official_installed ); ?>
						<?php if ( $standalone_feed ) : ?>
						<p class="wenpai-field__desc" style="margin-top:8px;"><?php esc_html_e( '访问方式：', 'wpmind' ); ?></p>
						<p>
							<?php echo wpmind_code_inline_html( [ 'code' => home_url( '/?feed=markdown' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit helper escapes internally ?>
							<?php echo wpmind_code_inline_html( [ 'code' => home_url( '/your-post-slug.md' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit helper escapes internally ?>
						</p>
						<?php endif; ?>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title">llms.txt</h2>
								<p class="card-sub"><?php esc_html_e( '为 AI 爬虫提供站点导航和内容索引。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-llms-txt', 'wpmind_llms_txt_enabled', $llms_txt_enabled, __( '启用 llms.txt', 'wpmind' ), __( '在 /llms.txt 提供站点内容导航。', 'wpmind' ) ); ?>
						<?php if ( $llms_txt_enabled ) : ?>
						<p class="wenpai-field__desc" style="margin-top:8px;"><?php esc_html_e( '访问地址：', 'wpmind' ); ?></p>
						<p><?php echo wpmind_code_inline_html( [ 'code' => \WPMind\Modules\Geo\LlmsTxtGenerator::get_public_url() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit helper escapes internally ?></p>
						<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
						<p class="meta"><?php esc_html_e( '当前是朴素固定链接，只能用上面这个带参数的地址。启用固定链接后才有 /llms.txt。', 'wpmind' ); ?></p>
						<?php endif; ?>
						<?php foreach ( \WPMind\Modules\Geo\LlmsTxtGenerator::detect_conflicts() as $wpmind_llms_conflict ) : ?>
						<p class="meta"><?php echo esc_html( $wpmind_llms_conflict ); ?></p>
						<?php endforeach; ?>
						<?php endif; ?>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'AI Sitemap', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '为 AI 爬虫提供专属 XML Sitemap，包含内容声明和摘要等元数据。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-ai-sitemap', 'wpmind_ai_sitemap_enabled', $ai_sitemap_enabled, __( '启用 AI Sitemap', 'wpmind' ), __( '在 /ai-sitemap.xml 提供 AI 专属站点地图。', 'wpmind' ) ); ?>
						<?php if ( $ai_sitemap_enabled ) : ?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-sitemap-max"><?php esc_html_e( '最大条目数', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<div class="field-ctl-row">
									<input type="number" id="wpmind-ai-sitemap-max" name="wpmind_ai_sitemap_max_entries" value="<?php echo esc_attr( (string) $ai_sitemap_max ); ?>" min="10" max="5000" step="10" style="max-width:7rem">
									<span class="meta">(10–5000)</span>
								</div>
								<p class="wenpai-field__desc"><?php esc_html_e( '条目数过多会拖慢生成。', 'wpmind' ); ?></p>
							</div>
						</div>
						<p class="wenpai-field__desc" style="margin-top:8px;"><?php esc_html_e( '访问地址：', 'wpmind' ); ?></p>
						<p><?php echo wpmind_code_inline_html( [ 'code' => \WPMind\Modules\Geo\AiSitemapGenerator::get_public_url() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit helper escapes internally ?></p>
						<?php endif; ?>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'AI 摘要', 'wpmind' ); ?> <span class="pill"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span></h2>
								<p class="card-sub"><?php esc_html_e( '控制 AI 如何描述您的文章，而非让 AI 自行猜测。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-ai-summary', 'wpmind_ai_summary_enabled', $ai_summary_enabled, __( '启用 AI 摘要', 'wpmind' ), __( '在编辑器中添加 AI 摘要字段，输出为 meta 标签和 Schema.org abstract。', 'wpmind' ) ); ?>
						<?php if ( $ai_summary_enabled ) : ?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-summary-fallback"><?php esc_html_e( '无摘要时的回退', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<select id="wpmind-ai-summary-fallback" name="wpmind_ai_summary_fallback" style="max-width:420px">
									<option value="excerpt" <?php selected( $ai_summary_fallback, 'excerpt' ); ?>><?php esc_html_e( '使用文章摘要 (excerpt)', 'wpmind' ); ?></option>
									<option value="none" <?php selected( $ai_summary_fallback, 'none' ); ?>><?php esc_html_e( '不输出', 'wpmind' ); ?></option>
								</select>
								<p class="wenpai-field__desc"><?php esc_html_e( '在编辑器侧边栏的「AI 摘要」面板中为每篇文章编写专属摘要。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php endif; ?>
					</section>
				</div>
				<div class="wpmind-geo-side">
					<?php
					$wpmind_info_card(
						'info',
						__( 'AI 内容分发', 'wpmind' ),
						'<p>' . esc_html__( 'AI 搜索引擎通过 Markdown Feed、llms.txt 和 AI Sitemap 等专用通道获取内容。这些格式去除了导航、广告等噪音，让 AI 能直接解析核心内容。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( 'Markdown Feed 提供纯净的结构化内容', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'llms.txt 是 AI 时代的 robots.txt，引导 AI 发现内容', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'AI Sitemap 包含内容声明等 AI 专属元数据', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'AI 摘要让您主动控制 AI 如何描述文章', 'wpmind' ) . '</li></ul>'
						. '<p><a href="' . esc_url( $learn_more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>'
					);
					?>
				</div>
			</div>
		</div>

		<!-- ========== 结构化数据 ========== -->
		<div class="wpmind-module-tab-panel" data-panel="schema">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'Schema.org', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '为内容添加结构化数据，帮助 AI 理解语义。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-schema-enabled', 'wpmind_schema_enabled', $schema_enabled, __( '启用 Schema.org', 'wpmind' ), __( '自动为文章添加 Article 结构化数据。', 'wpmind' ) ); ?>
						<?php if ( $schema_enabled ) : ?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-schema-mode"><?php esc_html_e( '兼容模式', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<select id="wpmind-schema-mode" name="wpmind_schema_mode" style="max-width:420px">
									<option value="auto" <?php selected( $schema_mode, 'auto' ); ?>><?php esc_html_e( '自动 - 检测到 SEO 插件时不输出', 'wpmind' ); ?></option>
									<option value="merge" <?php selected( $schema_mode, 'merge' ); ?> disabled><?php esc_html_e( '合并 - 与现有 Schema 合并 (即将推出)', 'wpmind' ); ?></option>
									<option value="force" <?php selected( $schema_mode, 'force' ); ?>><?php esc_html_e( '强制 - 始终输出（可能重复）', 'wpmind' ); ?></option>
								</select>
							</div>
						</div>
						<?php endif; ?>
					</section>

					<?php if ( $schema_enabled ) : ?>
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( '输出属性一览', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '每篇文章自动输出的 JSON-LD 属性。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-table-wrap">
						<table class="tbl">
							<tbody>
								<tr><td class="mono">@type</td><td><?php esc_html_e( '48h 内 NewsArticle，之后 BlogPosting，页面 Article', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">headline</td><td><?php esc_html_e( '文章标题', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">author</td><td><?php esc_html_e( 'Person: name + url + sameAs (社交档案)', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">publisher</td><td><?php esc_html_e( 'Organization (品牌实体增强)', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">image</td><td><?php esc_html_e( '特色图片或正文首图', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">description</td><td><?php esc_html_e( '文章摘要', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">wordCount</td><td><?php esc_html_e( '字数统计', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">articleSection</td><td><?php esc_html_e( '所属分类', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">keywords</td><td><?php esc_html_e( '文章标签', 'wpmind' ); ?></td></tr>
								<tr><td class="mono">inLanguage</td><td><?php esc_html_e( '内容语言', 'wpmind' ); ?></td></tr>
								<tr><td class="mono" colspan="2"><strong><?php esc_html_e( 'BreadcrumbList (每篇文章)', 'wpmind' ); ?></strong></td></tr>
								<tr><td class="mono">itemListElement</td><td><?php esc_html_e( '首页 > 分类层级 > 当前页', 'wpmind' ); ?></td></tr>
								<tr><td class="mono" colspan="2"><strong><?php esc_html_e( 'WebSite (仅首页)', 'wpmind' ); ?></strong></td></tr>
								<tr><td class="mono">potentialAction</td><td><?php esc_html_e( 'SearchAction 站内搜索', 'wpmind' ); ?></td></tr>
							</tbody>
						</table>
						</div>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'JSON-LD 预览', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '基于当前设置生成的示例输出。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php
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

						$preview_breadcrumb = [
							'@context'        => 'https://schema.org',
							'@type'           => 'BreadcrumbList',
							'itemListElement' => [
								[ '@type' => 'ListItem', 'position' => 1, 'name' => get_bloginfo( 'name' ), 'item' => home_url( '/' ) ],
								[ '@type' => 'ListItem', 'position' => 2, 'name' => __( '示例分类', 'wpmind' ), 'item' => home_url( '/category/example/' ) ],
								[ '@type' => 'ListItem', 'position' => 3, 'name' => __( '示例文章标题', 'wpmind' ) ],
							],
						];
						$preview_breadcrumb_json = wp_json_encode( $preview_breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

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
						<div class="seg wpmind-schema-preview-tabs" style="margin-bottom:var(--wenpai-space-3);">
							<button type="button" class="wpmind-module-subtab wpmind-schema-tab active" data-preview="article"><?php esc_html_e( 'Article', 'wpmind' ); ?></button>
							<button type="button" class="wpmind-module-subtab wpmind-schema-tab" data-preview="breadcrumb"><?php esc_html_e( 'Breadcrumb', 'wpmind' ); ?></button>
							<button type="button" class="wpmind-module-subtab wpmind-schema-tab" data-preview="website"><?php esc_html_e( 'WebSite', 'wpmind' ); ?></button>
						</div>
						<div class="wpmind-schema-preview-panel" data-preview-panel="article">
							<pre class="wenpai-code"><code><?php echo esc_html( $preview_json ); ?></code></pre>
							<p class="meta" style="margin-top:8px;"><?php esc_html_e( '每篇文章/页面自动输出。品牌实体启用后 publisher 将自动增强。', 'wpmind' ); ?></p>
						</div>
						<div class="wpmind-schema-preview-panel" data-preview-panel="breadcrumb" style="display:none;">
							<pre class="wenpai-code"><code><?php echo esc_html( $preview_breadcrumb_json ); ?></code></pre>
							<p class="meta" style="margin-top:8px;"><?php esc_html_e( '每篇文章/页面自动输出面包屑导航。支持分类层级和页面父子关系。', 'wpmind' ); ?></p>
						</div>
						<div class="wpmind-schema-preview-panel" data-preview-panel="website" style="display:none;">
							<pre class="wenpai-code"><code><?php echo esc_html( $preview_website_json ); ?></code></pre>
							<p class="meta" style="margin-top:8px;"><?php esc_html_e( '仅在首页输出。包含站内搜索 SearchAction，帮助搜索引擎展示站内搜索框。', 'wpmind' ); ?></p>
						</div>
					</section>
					<?php endif; ?>
				</div>
				<div class="wpmind-geo-side">
					<?php
					$wpmind_info_card(
						'info',
						__( 'Schema.org 与 AI 搜索', 'wpmind' ),
						'<p>' . esc_html__( 'Schema.org JSON-LD 是 AI 理解页面语义的核心通道。结构化数据直接告诉 AI「这是什么类型的内容、谁写的、什么时候发布的」。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( 'Article/BlogPosting/NewsArticle 类型自动识别', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'headline, author, datePublished 等核心属性', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'publisher 由品牌实体自动增强', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'wordCount, articleSection, keywords 辅助 AI 分类', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'inLanguage 帮助多语言内容正确归类', 'wpmind' ) . '</li></ul>'
					);
					?>
				</div>
			</div>
		</div>

		<!-- ========== AI 索引 ========== -->
		<div class="wpmind-module-tab-panel" data-panel="control">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'AI 索引指令', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '控制 AI 爬虫对内容的索引和训练权限。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-ai-indexing', 'wpmind_ai_indexing_enabled', $ai_indexing, __( '启用 AI 索引指令', 'wpmind' ), __( '输出 noai/nollm meta 标签和 X-Robots-Tag HTTP 头。', 'wpmind' ) ); ?>
						<?php if ( $ai_indexing ) : ?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-declaration"><?php esc_html_e( '默认内容声明', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<select id="wpmind-ai-declaration" name="wpmind_ai_default_declaration" style="max-width:420px">
									<option value="original" <?php selected( $ai_declaration, 'original' ); ?>><?php esc_html_e( '原创内容 (original)', 'wpmind' ); ?></option>
									<option value="ai-assisted" <?php selected( $ai_declaration, 'ai-assisted' ); ?>><?php esc_html_e( 'AI 辅助创作 (ai-assisted)', 'wpmind' ); ?></option>
									<option value="ai-generated" <?php selected( $ai_declaration, 'ai-generated' ); ?>><?php esc_html_e( 'AI 生成内容 (ai-generated)', 'wpmind' ); ?></option>
								</select>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '排除的内容类型', 'wpmind' ); ?></span></div>
							<div class="field-ctl">
								<p class="wenpai-field__desc" style="margin-bottom:8px;"><?php esc_html_e( '勾选的内容类型将输出 noai, nollm 指令，禁止 AI 索引和训练。', 'wpmind' ); ?></p>
								<div class="opts">
									<?php
									$public_types = get_post_types( [ 'public' => true ], 'objects' );
									foreach ( $public_types as $type ) :
										?>
									<label><input type="checkbox" name="wpmind_ai_excluded_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $ai_excluded_types, true ) ); ?>> <?php echo esc_html( $type->labels->name ); ?> <code>(<?php echo esc_html( $type->name ); ?>)</code></label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<p class="meta" style="margin-top:8px;"><?php esc_html_e( '单篇文章可在编辑器侧边栏的「AI 索引指令」面板中覆盖全局设置。', 'wpmind' ); ?></p>
						<?php endif; ?>
					</section>
				</div>
				<div class="wpmind-geo-side">
					<?php
					$wpmind_info_card(
						'info',
						__( 'AI 索引权限控制', 'wpmind' ),
						'<p>' . esc_html__( '随着 AI 爬虫大量抓取网站内容用于训练和索引，内容创作者需要明确声明权限意愿。noai 和 nollm 是新兴的 meta robots 指令标准。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( 'noai — 禁止 AI 搜索引擎索引内容', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'nollm — 禁止将内容用于 LLM 训练', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '内容声明标注 AI 参与程度（原创/辅助/生成）', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'X-Robots-Tag HTTP 头对非 HTML 资源生效', 'wpmind' ) . '</li></ul>'
						. '<p><a href="' . esc_url( $learn_more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>'
					);
					?>
				</div>
			</div>
		</div>

		<!-- ========== 爬虫管理 ========== -->
		<div class="wpmind-module-tab-panel" data-panel="crawlers">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'robot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'robots.txt AI 管理', 'wpmind' ); ?> <span class="pill"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span></h2>
								<p class="card-sub"><?php esc_html_e( '通过 robots.txt 控制 AI 爬虫的访问权限，不修改物理文件。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-robots-ai', 'wpmind_robots_ai_enabled', $robots_ai_enabled, __( '启用 robots.txt AI 管理', 'wpmind' ), __( '在 robots.txt 中注入 AI 爬虫的 Allow/Disallow 规则。', 'wpmind' ) ); ?>
						<?php if ( $robots_ai_enabled ) : ?>
						<div class="wenpai-table-wrap" style="margin-top:var(--wenpai-space-3);">
						<table class="tbl">
							<thead>
								<tr>
									<th><?php esc_html_e( '爬虫', 'wpmind' ); ?></th>
									<th><?php esc_html_e( '公司', 'wpmind' ); ?></th>
									<th><?php esc_html_e( '规则', 'wpmind' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( \WPMind\Modules\Geo\RobotsTxtManager::AI_CRAWLERS as $bot => $info ) : ?>
								<tr>
									<td><div class="t"><span class="mono"><?php echo esc_html( $bot ); ?></span></div><div class="d"><?php echo esc_html( $info['description'] ); ?></div></td>
									<td><?php echo esc_html( $info['company'] ); ?></td>
									<td>
										<select name="wpmind_robots_ai_rules[<?php echo esc_attr( $bot ); ?>]" style="width:auto;min-width:120px">
											<option value="" <?php selected( $robots_ai_rules[ $bot ] ?? '', '' ); ?>><?php esc_html_e( '不管理', 'wpmind' ); ?></option>
											<option value="allow" <?php selected( $robots_ai_rules[ $bot ] ?? '', 'allow' ); ?>><?php esc_html_e( 'Allow', 'wpmind' ); ?></option>
											<option value="disallow" <?php selected( $robots_ai_rules[ $bot ] ?? '', 'disallow' ); ?>><?php esc_html_e( 'Disallow', 'wpmind' ); ?></option>
										</select>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<p class="wenpai-field__desc" style="margin-top:var(--wenpai-space-3);"><?php esc_html_e( '预览地址：', 'wpmind' ); ?> <?php echo wpmind_code_inline_html( [ 'code' => home_url( '/robots.txt' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit helper escapes internally ?></p>
						<?php endif; ?>
					</section>
				</div>
				<div class="wpmind-geo-side">
					<?php
					$wpmind_info_card(
						'info',
						__( 'AI 爬虫生态', 'wpmind' ),
						'<p>' . esc_html__( '目前活跃的 AI 爬虫超过 20 种，来自 OpenAI、Anthropic、Google、百度等公司。通过 robots.txt 可以精细控制每个爬虫的访问权限。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( 'GPTBot、ClaudeBot 是最活跃的 AI 爬虫', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '中国 AI 爬虫：百度蜘蛛、搜狗、360、神马', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '规则通过 WordPress 过滤器注入，不修改物理文件', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'Allow 允许爬取，Disallow 禁止爬取', 'wpmind' ) . '</li></ul>'
						. '<p><a href="' . esc_url( $learn_more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>'
					);
					?>
				</div>
			</div>
		</div>

		<!-- ========== 品牌实体 ========== -->
		<div class="wpmind-module-tab-panel" data-panel="brand">
			<div class="wpmind-geo-grid">
				<div class="wpmind-geo-main">
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'services' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( '组织基础', 'wpmind' ); ?> <span class="pill"><?php esc_html_e( 'NEW', 'wpmind' ); ?></span></h2>
								<p class="card-sub"><?php esc_html_e( '定义品牌实体信息，增强 publisher Schema 并在首页输出 Organization JSON-LD。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-brand-entity', 'wpmind_brand_entity_enabled', $brand_entity_enabled, __( '启用品牌实体', 'wpmind' ), __( '丰富文章 publisher Schema，并在首页输出独立 Organization JSON-LD。', 'wpmind' ) ); ?>
						<?php if ( $brand_entity_enabled ) : ?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-org-type"><?php esc_html_e( '组织类型', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<select id="wpmind-brand-org-type" name="wpmind_brand_org_type" style="max-width:420px">
									<option value="Organization" <?php selected( $brand_org_type, 'Organization' ); ?>><?php esc_html_e( 'Organization (通用)', 'wpmind' ); ?></option>
									<option value="Corporation" <?php selected( $brand_org_type, 'Corporation' ); ?>><?php esc_html_e( 'Corporation (公司)', 'wpmind' ); ?></option>
									<option value="LocalBusiness" <?php selected( $brand_org_type, 'LocalBusiness' ); ?>><?php esc_html_e( 'LocalBusiness (本地商户)', 'wpmind' ); ?></option>
									<option value="OnlineBusiness" <?php selected( $brand_org_type, 'OnlineBusiness' ); ?>><?php esc_html_e( 'OnlineBusiness (在线业务)', 'wpmind' ); ?></option>
									<option value="NewsMediaOrganization" <?php selected( $brand_org_type, 'NewsMediaOrganization' ); ?>><?php esc_html_e( 'NewsMediaOrganization (新闻媒体)', 'wpmind' ); ?></option>
								</select>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-name"><?php esc_html_e( '品牌名称', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="text" id="wpmind-brand-name" name="wpmind_brand_name" value="<?php echo esc_attr( get_option( 'wpmind_brand_name', '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
								<p class="wenpai-field__desc"><?php esc_html_e( '留空则使用站点名称。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-desc"><?php esc_html_e( '品牌描述', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<textarea id="wpmind-brand-desc" name="wpmind_brand_description" rows="3" placeholder="<?php esc_attr_e( '简要描述您的品牌/组织', 'wpmind' ); ?>"><?php echo esc_textarea( get_option( 'wpmind_brand_description', '' ) ); ?></textarea>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-url"><?php esc_html_e( '品牌 URL', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="wpmind-brand-url" name="wpmind_brand_url" value="<?php echo esc_attr( get_option( 'wpmind_brand_url', '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
								<p class="wenpai-field__desc"><?php esc_html_e( '留空则使用站点首页地址。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-founding"><?php esc_html_e( '创立日期', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="text" id="wpmind-brand-founding" name="wpmind_brand_founding_date" value="<?php echo esc_attr( get_option( 'wpmind_brand_founding_date', '' ) ); ?>" placeholder="<?php esc_attr_e( '例如：2020 或 2020-01-01', 'wpmind' ); ?>" style="max-width:280px">
							</div>
						</div>
						<?php endif; ?>
					</section>

					<?php if ( $brand_entity_enabled ) : ?>
					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( '社交档案', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '社交平台主页 URL，将作为 Schema.org sameAs 输出。', 'wpmind' ); ?></p>
							</div>
						</div>
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
							$field_id = 'wpmind-brand-' . sanitize_html_class( str_replace( 'wpmind_brand_social_', '', $field_name ) );
							?>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( get_option( $field_name, '' ) ); ?>" placeholder="https://">
							</div>
						</div>
						<?php endforeach; ?>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( '联系方式', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '可选的联系信息，将作为 Schema.org contactPoint 输出。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-contact-email"><?php esc_html_e( '联系邮箱', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="email" id="wpmind-brand-contact-email" name="wpmind_brand_contact_email" value="<?php echo esc_attr( get_option( 'wpmind_brand_contact_email', '' ) ); ?>" placeholder="hello@example.com">
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-contact-phone"><?php esc_html_e( '联系电话', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="tel" id="wpmind-brand-contact-phone" name="wpmind_brand_contact_phone" value="<?php echo esc_attr( get_option( 'wpmind_brand_contact_phone', '' ) ); ?>" placeholder="+86-xxx-xxxx-xxxx">
							</div>
						</div>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( 'Knowledge Graph', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '关联品牌的 Wikidata/Wikipedia 页面，强化知识图谱信号。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-wikidata"><?php esc_html_e( 'Wikidata URL', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="wpmind-brand-wikidata" name="wpmind_brand_wikidata_url" value="<?php echo esc_attr( get_option( 'wpmind_brand_wikidata_url', '' ) ); ?>" placeholder="https://www.wikidata.org/wiki/Q...">
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-wikipedia"><?php esc_html_e( 'Wikipedia URL', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="wpmind-brand-wikipedia" name="wpmind_brand_wikipedia_url" value="<?php echo esc_attr( get_option( 'wpmind_brand_wikipedia_url', '' ) ); ?>" placeholder="https://en.wikipedia.org/wiki/...">
							</div>
						</div>
					</section>

					<section class="card">
						<div class="card-head">
							<span class="tile"><?php echo AdminIcons::svg( 'route' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
							<div>
								<h2 class="card-title"><?php esc_html_e( '文章实体关联', 'wpmind' ); ?></h2>
								<p class="card-sub"><?php esc_html_e( '将每篇文章关联到 Wikidata/Wikipedia 实体，帮助 AI 消除歧义并建立权威性。', 'wpmind' ); ?></p>
							</div>
						</div>
						<?php $wpmind_switch( 'wpmind-entity-linker', 'wpmind_entity_linker_enabled', $entity_linker_enabled, __( '启用文章实体关联', 'wpmind' ), __( '在编辑器中添加实体关联字段，输出为 Schema.org about.sameAs。', 'wpmind' ) ); ?>
						<?php if ( $entity_linker_enabled ) : ?>
						<p class="meta" style="margin-top:8px;"><?php esc_html_e( '在编辑器侧边栏的「实体关联」面板中为每篇文章关联 Wikidata 实体。', 'wpmind' ); ?></p>
						<?php endif; ?>
					</section>
					<?php endif; ?>
				</div>
				<div class="wpmind-geo-side">
					<?php
					$wpmind_info_card(
						'info',
						__( '实体与 AI 搜索', 'wpmind' ),
						'<p>' . esc_html__( '品牌实体是 GEO 的基础层。AI 搜索引擎必须先「理解」品牌身份，才能信任并推荐其内容。', 'wpmind' ) . '</p><ul>'
						. '<li>' . esc_html__( 'Organization JSON-LD 在首页输出，是知识图谱的主要信号源', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'sameAs 关联社交档案，帮助 AI 验证品牌真实性', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( 'Wikidata/Wikipedia 链接强化实体消歧', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '文章 publisher 自动增强，无需逐篇配置', 'wpmind' ) . '</li>'
						. '<li>' . esc_html__( '文章实体关联通过 about.sameAs 消除语义歧义', 'wpmind' ) . '</li></ul>'
						. '<p><a href="' . esc_url( $learn_more_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '了解更多 →', 'wpmind' ) . '</a></p>'
					);
					?>
				</div>
			</div>
		</div>

	</div><!-- /wpmind-geo-content -->

	<div class="card-foot" style="margin-top:var(--wenpai-space-5);">
		<span class="wenpai-actions">
			<button type="button" class="btn btn-primary" id="wpmind-save-geo"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
		</span>
	</div>

</div><!-- /wpmind-geo-panel -->
