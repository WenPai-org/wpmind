<?php
/**
 * 工具视图：诊断包 / 端点配置导出 / 端点配置导入 / 运行环境信息。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\SuiteIntegration;

( static function (): void {
	$wpmind_display     = SuiteIntegration::display();
	$wpmind_kit_version = '';
	if ( class_exists( 'Wenpai_Admin_Loader', false ) && function_exists( 'wenpai_admin_ui_info' ) ) {
		$wpmind_kit_info    = wenpai_admin_ui_info();
		$wpmind_kit_version = (string) $wpmind_kit_info['version'];
	}

	wpmind_page_head(
		__( '工具', 'wpmind' ),
		__( '导出诊断包与迁移端点配置。', 'wpmind' )
	);
	?>

<div class="wenpai-stack">
	<?php
	// ---- 诊断包 ----
	Wenpai_Admin_UI::card_open(
		[
			'title'     => __( '诊断包', 'wpmind' ),
			'sub'       => __( '导出脱敏环境、模块与用量快照，不含密钥与指纹。', 'wpmind' ),
			'icon_html' => AdminIcons::svg( 'tray' ),
			'state'     => 'accent',
		]
	);
	Wenpai_Admin_UI::card_foot(
		[
			'start'   => true,
			'meta'    => __( '排查连接或模块异常时附上诊断包更快定位。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '导出诊断包', 'wpmind' ),
					'variant' => 'secondary',
					'attrs'   => [ 'id' => 'wpmind-export-diagnostics' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 导出端点配置 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '导出端点配置', 'wpmind' ),
			'sub'   => __( '下载端点与模块配置 JSON（不含密钥），用于迁移备份。', 'wpmind' ),
			'icon'  => 'download',
		]
	);
	Wenpai_Admin_UI::card_foot(
		[
			'start'   => true,
			'meta'    => __( '导出文件仅包含结构配置，不会泄露 API Key。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '导出端点配置', 'wpmind' ),
					'variant' => 'secondary',
					'attrs'   => [ 'id' => 'wpmind-export-config' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 导入端点配置 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '导入端点配置', 'wpmind' ),
			'sub'   => __( '先校验差异再确认写入，自动保留本站已保存的密钥。', 'wpmind' ),
			'icon'  => 'upload',
			'attrs' => [ 'id' => 'wpmind-tools-import-config' ],
		]
	);
	?>
	<div class="wenpai-field wenpai-field--row field">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind-import-config-file"><?php esc_html_e( '配置文件', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<span class="wenpai-file" data-wenpai-file data-wenpai-file-local data-multiple="0" data-name="wpmind_import_config_file">
				<span class="wenpai-file__drop" role="button" tabindex="0" aria-label="<?php esc_attr_e( '选择配置文件', 'wpmind' ); ?>">
					<?php echo AdminIcons::svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( '拖入 JSON 文件，或点击选择', 'wpmind' ); ?></span>
					<input type="file" class="wenpai-file__input" id="wpmind-import-config-file" name="wpmind_import_config_file" accept="application/json,.json" hidden>
				</span>
				<span class="wenpai-file__list"></span>
			</span>
			<p class="wenpai-field__desc"><?php esc_html_e( '选择 JSON 后先预检差异，确认后写入。', 'wpmind' ); ?></p>
		</div>
	</div>
	<div id="wpmind-import-confirm-box" class="wenpai-stack wenpai-stack--xs" hidden>
		<p class="meta" id="wpmind-import-confirm-msg"></p>
		<div class="wenpai-actions">
			<?php
			$import_confirm = wpmind_confirm_attrs(
				__( '确认将校验通过的配置写入本站？（本站 API Key 保持不变）', 'wpmind' ),
				[
					'title'    => __( '确认导入配置', 'wpmind' ),
					'primary'  => true,
					'ok_label' => __( '写入配置', 'wpmind' ),
				]
			);
			?>
			<button type="button" class="btn btn-primary btn-sm" id="wpmind-import-confirm-apply"<?php echo $import_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '写入配置', 'wpmind' ); ?></button>
			<button type="button" class="btn btn-ghost btn-sm" id="wpmind-import-confirm-cancel"><?php esc_html_e( '取消', 'wpmind' ); ?></button>
		</div>
	</div>
	<?php
	Wenpai_Admin_UI::card_foot(
		[
			'start' => true,
			'meta'  => __( '两步确认机制防止误覆盖现有端点设置。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 版本与环境信息 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '版本信息', 'wpmind' ),
			'tile'  => false,
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html' => AdminIcons::svg( 'package' ),
			'title'     => $wpmind_display['brand'],
			'desc'      => sprintf(
				/* translators: 1: plugin version, 2: design system version */
				__( '插件 v%1$s · 组件库 %2$s', 'wpmind' ),
				WPMIND_VERSION,
				'' !== $wpmind_kit_version ? 'wenpai-admin-ui ' . $wpmind_kit_version : 'wenpai-admin-ui'
			),
			'pill'      => [
				'text'  => __( '正常', 'wpmind' ),
				'state' => 'ok',
			],
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html' => AdminIcons::svg( 'layers' ),
			'title'     => __( '运行环境', 'wpmind' ),
			'desc'      => sprintf(
				/* translators: 1: WordPress version, 2: PHP version */
				__( 'WordPress %1$s · PHP %2$s', 'wpmind' ),
				(string) get_bloginfo( 'version' ),
				PHP_VERSION
			),
			'pill'      => [
				'text'  => __( '就绪', 'wpmind' ),
				'state' => 'ok',
			],
		]
	);
	$resource_actions = '';
	if ( '' !== $wpmind_display['docs_url'] ) {
		$resource_actions .= Wenpai_Admin_UI::button_html(
			[
				'label'   => __( '文档', 'wpmind' ),
				'href'    => $wpmind_display['docs_url'],
				'variant' => 'ghost',
				'size'    => 'sm',
				'target'  => '_blank',
			]
		);
	}
	if ( '' !== $wpmind_display['feedback_url'] ) {
		$resource_actions .= Wenpai_Admin_UI::button_html(
			[
				'label'   => __( '反馈', 'wpmind' ),
				'href'    => $wpmind_display['feedback_url'],
				'variant' => 'ghost',
				'size'    => 'sm',
				'target'  => '_blank',
			]
		);
	}
	if ( '' !== $resource_actions ) {
		Wenpai_Admin_UI::row(
			[
				'icon_html'  => AdminIcons::svg( 'doc' ),
				'title'      => __( '文档与支持', 'wpmind' ),
				'desc'       => __( '查阅使用指南或提交排查反馈。', 'wpmind' ),
				'right_html' => '<span class="wenpai-actions">' . $resource_actions . '</span>',
			]
		);
	}
	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
