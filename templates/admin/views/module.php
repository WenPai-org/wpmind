<?php
/**
 * 模块设置视图：kit 壳（返回链接 + page_head）+ 模块模板。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

$wpmind_module_id = AdminPage::current_module();
$wpmind_modules   = \WPMind\Core\ModuleLoader::instance()->get_modules();
$wpmind_module    = $wpmind_modules[ $wpmind_module_id ] ?? null;

$wpmind_tpl = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/settings.php';
if ( ! file_exists( $wpmind_tpl ) ) {
	// analytics 模块的设置模板叫 dashboard.php。
	$wpmind_tpl = WPMIND_PLUGIN_DIR . 'modules/' . $wpmind_module_id . '/templates/dashboard.php';
}

if ( ! file_exists( $wpmind_tpl ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	include WPMIND_PLUGIN_DIR . 'templates/admin/views/modules.php';
	return;
}

// kit 化模块：统一壳。
$wpmind_name = $wpmind_module['name'] ?? $wpmind_module_id;
$wpmind_desc = $wpmind_module['description'] ?? '';
$wpmind_on   = ! empty( $wpmind_module['enabled'] );
?>
<p class="wpmind-back">
	<a href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'modules' ] ) ); ?>">
		<?php echo AdminIcons::svg( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?>
		<?php esc_html_e( '返回模块列表', 'wpmind' ); ?>
	</a>
</p>
<?php
wpmind_page_head(
	$wpmind_name,
	$wpmind_desc,
	'<span class="pill ' . ( $wpmind_on ? 'ok' : 'mute' ) . '">' . ( $wpmind_on ? esc_html__( '已启用', 'wpmind' ) : esc_html__( '未启用', 'wpmind' ) ) . '</span>'
);
?>

<div class="wenpai-stack">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	include $wpmind_tpl;
	?>
</div>
