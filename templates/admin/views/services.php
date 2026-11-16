<?php
/**
 * AI 服务视图 —— 过渡期：文本 + 图像 + 路由入口（旧模板堆叠，legacy 资产）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/tabs/services.php';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
include WPMIND_PLUGIN_DIR . 'templates/tabs/images.php';
