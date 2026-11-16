<?php
/**
 * 概览视图 —— 迁移过渡期：渲染旧概览模板（legacy CSS/JS 由 AdminAssets 按 view 加载）。
 * kit 化改版在后续提交进行。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 旧模板内部自行转义。
include WPMIND_PLUGIN_DIR . 'templates/tabs/overview.php';
