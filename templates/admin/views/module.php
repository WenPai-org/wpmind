<?php
/**
 * 模块设置视图 —— 过渡期：按 ?module=<id> 包含 modules/<id>/templates/settings.php（legacy 资产）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;

$wpmind_module_id = AdminPage::current_module();
$wpmind_tpl       = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/settings.php';

if ( '' === $wpmind_module_id || ! file_exists( $wpmind_tpl ) ) {
	// analytics 模块的设置模板叫 dashboard.php。
	$wpmind_tpl = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/dashboard.php';
}

if ( file_exists( $wpmind_tpl ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	include $wpmind_tpl;
} else {
	echo '<p>' . esc_html__( '该模块没有设置页。', 'wpmind' ) . '</p>';
}
