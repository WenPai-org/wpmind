<?php
/**
 * 模块设置视图：外层 Shell::subpage_head（1120px 满宽）+ 模块模板。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;

$wpmind_module_id = AdminPage::current_module();
$wpmind_modules   = \WPMind\Core\ModuleLoader::instance()->get_modules();
$wpmind_module    = $wpmind_modules[ $wpmind_module_id ] ?? null;

$wpmind_tpl = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/settings.php';
if ( ! file_exists( $wpmind_tpl ) ) {
	$wpmind_tpl = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/dashboard.php';
}

if ( ! file_exists( $wpmind_tpl ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	include WPMIND_PLUGIN_DIR . 'templates/admin/views/modules.php';
	return;
}

$wpmind_name = (string) ( $wpmind_module['name'] ?? $wpmind_module_id );
$wpmind_desc = (string) ( $wpmind_module['description'] ?? '' );
$wpmind_on   = ! empty( $wpmind_module['enabled'] );

// 二级详情页头统一放在 .wenpai-stack 外侧，保持 1120px 满宽与顶栏、页脚对齐。
Wenpai_Admin_Shell::subpage_head(
	[
		'title'      => $wpmind_name,
		'back_url'   => AdminPage::url( [ 'tab' => 'modules' ] ),
		'back_label' => __( '返回模块列表', 'wpmind' ),
		'lede'       => $wpmind_desc,
		'aside_html' => Wenpai_Admin_UI::pill_html(
			[
				'text'  => $wpmind_on ? __( '已开启', 'wpmind' ) : __( '未开启', 'wpmind' ),
				'state' => $wpmind_on ? 'ok' : 'mute',
			]
		),
	]
);
?>

<div class="wenpai-stack">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	include $wpmind_tpl;
	?>
</div>
