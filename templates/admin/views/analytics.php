<?php
/**
 * 分析视图 —— 过渡期：数据分析 + 预算管理（旧模板堆叠，legacy 资产）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/tabs/dashboard.php';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/tabs/budget.php';
