<?php
/**
 * 分析视图：用量统计 + 趋势图表 + 预算管理。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit;

wpmind_page_head(
	__( '分析', 'wpmind' ),
	__( '追踪 Token 消耗、费用估算与预算。', 'wpmind' )
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/admin/partials/analytics.php';
