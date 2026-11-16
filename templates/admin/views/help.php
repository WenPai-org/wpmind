<?php
/**
 * 帮助视图：站内快速开始 + 外部资源。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

wpmind_page_head(
	__( '帮助', 'wpmind' ),
	__( '先按顺序把 AI 服务配好，再按需开模块。', 'wpmind' )
);
?>

<div class="wenpai-stack">
	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '快速开始', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '推荐顺序：服务 → 路由 → 模块 → 分析', 'wpmind' ); ?></p>
			</div>
		</div>
		<a class="simple-row is-action" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'services' ] ) ); ?>">
			<span class="tile accent"><?php echo AdminIcons::svg( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '配置 AI 服务', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '填 Provider 的 API Key，测一遍连接', 'wpmind' ); ?></div></div>
			<span class="r"><?php echo AdminIcons::svg( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
		<a class="simple-row is-action" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'routing' ] ) ); ?>">
			<span class="tile accent"><?php echo AdminIcons::svg( 'route' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '智能路由', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '选策略、排 Provider 优先级', 'wpmind' ); ?></div></div>
			<span class="r"><?php echo AdminIcons::svg( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
		<a class="simple-row is-action" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'modules' ] ) ); ?>">
			<span class="tile accent"><?php echo AdminIcons::svg( 'modules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '启用模块', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '按需打开 GEO / 缓存 / 网关等能力', 'wpmind' ); ?></div></div>
			<span class="r"><?php echo AdminIcons::svg( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
		<a class="simple-row is-action" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'analytics' ] ) ); ?>">
			<span class="tile accent"><?php echo AdminIcons::svg( 'analytics' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '用量与预算', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '看消耗、设限额和告警', 'wpmind' ); ?></div></div>
			<span class="r"><?php echo AdminIcons::svg( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
	</section>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '外部资源', 'wpmind' ); ?></h2>
			</div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '使用文档', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( 'wpcy.com/mind —— 配置、模块、工作流说明', 'wpmind' ); ?></div></div>
			<div class="r"><a class="btn btn-ghost btn-sm" href="https://wpcy.com/mind/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '打开 ↗', 'wpmind' ); ?></a></div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'feedback' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( 'GitHub 仓库', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '源码、更新日志、问题反馈（附诊断包更快定位）', 'wpmind' ); ?></div></div>
			<div class="r"><a class="btn btn-ghost btn-sm" href="https://github.com/WenPai-org/wpmind" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '打开 ↗', 'wpmind' ); ?></a></div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><div class="t"><?php esc_html_e( '版本发布', 'wpmind' ); ?></div><div class="d"><?php esc_html_e( '各版本变更与下载', 'wpmind' ); ?></div></div>
			<div class="r"><a class="btn btn-ghost btn-sm" href="https://github.com/WenPai-org/wpmind/releases" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '打开 ↗', 'wpmind' ); ?></a></div>
		</div>
	</section>
</div>
