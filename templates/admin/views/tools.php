<?php
/**
 * 工具视图：诊断包 / 端点配置导出 / 端点配置导入 / 版本信息。
 * 按钮 ID 与 admin-endpoints.js 契约一致。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

$wpmind_kit_version = '';
if ( class_exists( 'Wenpai_Admin_Loader', false ) && function_exists( 'wenpai_admin_ui_info' ) ) {
	$wpmind_kit_info    = wenpai_admin_ui_info();
	$wpmind_kit_version = (string) ( $wpmind_kit_info['version'] ?? '' );
}

wpmind_page_head(
	__( '工具', 'wpmind' ),
	__( '诊断与迁移：导出诊断包排障、导出/导入端点配置（JSON，不含 API Key）。', 'wpmind' )
);
?>

<div class="wenpai-stack">
	<section class="card">
		<div class="card-head">
			<span class="tile accent"><?php echo AdminIcons::svg( 'tray' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '诊断包', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '环境、模块状态、用量统计的 JSON 快照；已脱敏，不含 API Key 与密钥指纹。反馈问题时附上它更快定位。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="card-foot">
			<button class="btn btn-secondary" type="button" id="wpmind-export-diagnostics"><?php esc_html_e( '导出诊断包', 'wpmind' ); ?></button>
		</div>
	</section>

	<section class="card">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '导出端点配置', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '下载端点与模块配置 JSON（不含密钥），用于迁移或备份。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="card-foot">
			<button class="btn btn-secondary" type="button" id="wpmind-export-config"><?php esc_html_e( '导出端点配置', 'wpmind' ); ?></button>
		</div>
	</section>

	<section class="card" id="wpmind-tools-import-config">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '导入端点配置', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '用之前导出的 JSON 恢复端点与模块配置。导入为两段确认：先校验预览差异，再确认才写入。导入会保留本站已保存的 API Key。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="wenpai-field">
			<label class="wenpai-field__label" for="wpmind-import-config-file"><?php esc_html_e( '配置文件', 'wpmind' ); ?></label>
			<span class="wenpai-file" data-wenpai-file data-wenpai-file-local data-multiple="0" data-name="wpmind_import_config_file">
				<span class="wenpai-file__drop" role="button" tabindex="0" aria-label="<?php esc_attr_e( '选择配置文件', 'wpmind' ); ?>">
					<?php echo AdminIcons::svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( '拖入 JSON 文件，或点击选择', 'wpmind' ); ?></span>
					<input type="file" class="wenpai-file__input" id="wpmind-import-config-file" name="wpmind_import_config_file" accept="application/json,.json" hidden>
				</span>
				<span class="wenpai-file__list"></span>
			</span>
			<p class="wenpai-field__desc"><?php esc_html_e( '选择 .json 文件后自动校验差异，确认无误才写入。', 'wpmind' ); ?></p>
		</div>
		<div class="card-foot">
			<span class="meta"><?php esc_html_e( '导入分两步：先校验并预览差异，确认后写入。', 'wpmind' ); ?></span>
		</div>
	</section>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '版本信息', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '插件与运行环境状态', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'package' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<div class="t"><?php echo esc_html( 'WPMind' ); ?></div>
				<div class="d"><?php echo esc_html( sprintf( /* translators: 1: plugin version, 2: design system version */ __( '插件 v%1$s · 设计系统 %2$s', 'wpmind' ), WPMIND_VERSION, '' !== $wpmind_kit_version ? 'wenpai-admin-ui ' . $wpmind_kit_version : 'wenpai-admin-ui' ) ); ?></div>
			</div>
			<div class="r"><span class="pill ok"><?php esc_html_e( '正常', 'wpmind' ); ?></span></div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<div class="t"><?php esc_html_e( '运行环境', 'wpmind' ); ?></div>
				<div class="d"><?php echo esc_html( sprintf( /* translators: 1: WordPress version, 2: PHP version */ __( 'WordPress %1$s · PHP %2$s', 'wpmind' ), get_bloginfo( 'version' ), PHP_VERSION ) ); ?></div>
			</div>
			<div class="r"><span class="pill"><?php esc_html_e( '就绪', 'wpmind' ); ?></span></div>
		</div>
		<div class="simple-row is-static">
			<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<div class="t"><?php esc_html_e( '文档与反馈', 'wpmind' ); ?></div>
				<div class="d"><?php esc_html_e( '使用文档 wpcy.com/mind · 问题反馈 GitHub Issues', 'wpmind' ); ?></div>
			</div>
			<div class="r wenpai-actions">
				<a class="btn btn-ghost btn-sm" href="https://wpcy.com/mind/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '文档', 'wpmind' ); ?></a>
				<a class="btn btn-ghost btn-sm" href="https://github.com/WenPai-org/wpmind/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '反馈', 'wpmind' ); ?></a>
			</div>
		</div>
	</section>
</div>
