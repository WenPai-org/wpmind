<?php
/**
 * 分析视图（kit 化）：用量统计 + 图表 + 各渠道用量 + 预算管理。
 * 内容在 templates/admin/partials/analytics.php，与 analytics 模块页共用。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit;

wpmind_page_head(
	__( '分析', 'wpmind' ),
	__( 'Token 用量、费用与预算一页看。费用为估算值，按各服务商官方定价计算。', 'wpmind' )
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/admin/partials/analytics.php';
