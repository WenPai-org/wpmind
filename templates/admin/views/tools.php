<?php
/**
 * 工具视图：诊断包 / 端点配置导出导入 / 版本信息。
 * 按钮 ID 与 admin-endpoints.js 契约一致。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

Wenpai_Admin_Shell::page_head(
	__( '工具', 'wpmind' ),
	__( '诊断与迁移：导出诊断包排障、导出/导入端点配置（JSON，不含 API Key）。', 'wpmind' )
);
?>

<div class="wenpai-stack">
	<section class="card">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '导出诊断包', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '环境、模块状态、用量统计的 JSON 快照；已脱敏，不含 API Key 与密钥指纹。反馈问题时附上它更快定位。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="card-foot">
			<span class="wenpai-actions">
				<button type="button" class="btn btn-primary" id="wpmind-export-diagnostics"><?php esc_html_e( '下载诊断包', 'wpmind' ); ?></button>
			</span>
		</div>
	</section>

	<section class="card" id="wpmind-tools-import-config">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '端点配置导出 / 导入', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '用之前导出的 JSON 恢复端点与模块配置。导入为两段确认：先校验预览差异，再确认才写入。导入会保留本站已保存的 API Key。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label"><?php esc_html_e( '配置文件', 'wpmind' ); ?></label></div>
			<div class="field-ctl wenpai-actions">
				<button type="button" class="btn" id="wpmind-export-config"><?php esc_html_e( '导出端点配置', 'wpmind' ); ?></button>
				<button type="button" class="btn" id="wpmind-import-config"><?php esc_html_e( '选择文件导入…', 'wpmind' ); ?></button>
				<input type="file" id="wpmind-import-config-file" accept="application/json,.json" hidden>
			</div>
		</div>
	</section>

	<section class="card">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '版本信息', 'wpmind' ); ?></h2>
			</div>
		</div>
		<table class="tbl">
			<tbody>
				<tr><td class="wpmind-k"><?php esc_html_e( '插件版本', 'wpmind' ); ?></td><td class="mono">v<?php echo esc_html( WPMIND_VERSION ); ?></td></tr>
				<tr><td class="wpmind-k"><?php esc_html_e( '设计系统', 'wpmind' ); ?></td><td class="mono">wenpai-admin-ui <?php echo esc_html( class_exists( 'Wenpai_Admin_Loader', false ) && function_exists( 'wenpai_admin_ui_info' ) ? ( wenpai_admin_ui_info()['version'] ?? '' ) : '' ); ?></td></tr>
				<tr><td class="wpmind-k"><?php esc_html_e( '文档', 'wpmind' ); ?></td><td><a href="https://wpcy.com/mind/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'wpcy.com/mind', 'wpmind' ); ?><?php echo AdminIcons::svg( 'external', 'wenpai-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></td></tr>
				<tr><td class="wpmind-k"><?php esc_html_e( '问题反馈', 'wpmind' ); ?></td><td><a href="https://github.com/WenPai-org/wpmind/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub Issues', 'wpmind' ); ?><?php echo AdminIcons::svg( 'external', 'wenpai-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></td></tr>
			</tbody>
		</table>
	</section>
</div>
