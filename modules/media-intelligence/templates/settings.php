<?php
/**
 * Media Intelligence 模块设置（kit 化）：URL 驱动 subnav + statbar + progressbar + field--row。
 *
 * @package WPMind\Modules\MediaIntelligence
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminPage;

( static function (): void {
	$auto_alt     = (string) get_option( 'wpmind_media_auto_alt', '1' );
	$auto_title   = (string) get_option( 'wpmind_media_auto_title', '1' );
	$nsfw_enabled = (string) get_option( 'wpmind_media_nsfw_enabled', '0' );
	$language     = (string) get_option( 'wpmind_media_language', 'auto' );

	$stats     = get_option( 'wpmind_media_stats', [] );
	$month_key = 'month_' . gmdate( 'Y_m' );
	$total_gen = (int) ( $stats['generated'] ?? 0 );
	$month_gen = (int) ( $stats[ $month_key ] ?? 0 );

	$sections = [
		'settings' => __( '基本设置', 'wpmind' ),
		'batch'    => __( '批量处理', 'wpmind' ),
		'safety'   => __( '内容安全', 'wpmind' ),
	];
	$section  = Wenpai_Admin_UI::section( $sections, 'settings' );

	$wpmind_switch = static function ( string $id, string $name, bool $checked, string $label, string $desc = '' ): void {
		$switch_html = Wenpai_Admin_Settings::switch_html(
			[
				'id'      => $id,
				'name'    => $name,
				'checked' => $checked,
				'on'      => __( '已开启', 'wpmind' ),
				'off'     => __( '未开启', 'wpmind' ),
			]
		);
		echo '<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl">'
		. $switch_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. ( '' !== $desc ? '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>' : '' )
		. '</div></div>';
	};

	$subnav_items = [];
	foreach ( $sections as $sec_id => $sec_label ) {
		$subnav_items[] = [
			'id'      => $sec_id,
			'label'   => $sec_label,
			'url'     => AdminPage::url(
				[
					'tab'     => 'module',
					'module'  => 'media-intelligence',
					'section' => $sec_id,
				]
			),
			'current' => $section === $sec_id,
		];
	}
	?>

<div class="wpmind-media-panel wenpai-stack">
	<?php
	Wenpai_Admin_UI::subnav(
		[
			'items'   => $subnav_items,
			'current' => $section,
			'label'   => __( '媒体智能分区', 'wpmind' ),
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label'       => __( '累计处理', 'wpmind' ),
					'value'       => (string) $total_gen,
					'value_attrs' => [ 'id' => 'wpmind-media-total-gen' ],
				],
				[
					'label'       => __( '待处理', 'wpmind' ),
					'value'       => '—',
					'value_attrs' => [ 'id' => 'wpmind-media-missing' ],
				],
				[
					'label'       => __( '本月生成', 'wpmind' ),
					'value'       => (string) $month_gen,
					'value_attrs' => [ 'id' => 'wpmind-media-month-gen' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'settings', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '自动生成', 'wpmind' ),
				'sub'   => __( '上传图片时自动生成 Alt 文本与标题。', 'wpmind' ),
				'tile'  => false,
			]
		);
		$wpmind_switch( 'wpmind-media-auto-alt', 'wpmind_media_auto_alt', '1' === $auto_alt, __( '自动生成 Alt 文本', 'wpmind' ), __( '不覆盖已有 Alt 文本的图片。', 'wpmind' ) );
		$wpmind_switch( 'wpmind-media-auto-title', 'wpmind_media_auto_title', '1' === $auto_title, __( '自动填充标题和说明', 'wpmind' ), __( '同步填充附件标题与说明字段。', 'wpmind' ) );
		?>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind-media-language"><?php esc_html_e( '生成语言', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<select id="wpmind-media-language" name="wpmind_media_language">
					<option value="auto" <?php selected( $language, 'auto' ); ?>><?php esc_html_e( '自动检测', 'wpmind' ); ?></option>
					<option value="zh" <?php selected( $language, 'zh' ); ?>><?php esc_html_e( '中文', 'wpmind' ); ?></option>
					<option value="en" <?php selected( $language, 'en' ); ?>>English</option>
				</select>
			</div>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			[
				'actions' => [
					[
						'label'   => __( '保存设置', 'wpmind' ),
						'variant' => 'primary',
						'attrs'   => [ 'id' => 'wpmind-save-media-settings' ],
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'batch', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '批量生成 Alt', 'wpmind' ),
				'sub'               => __( '扫描缺少 Alt 的图片并每批处理 5 张。', 'wpmind' ),
				'tile'              => false,
				'head_control_html' => '<span class="wenpai-actions">'
					. Wenpai_Admin_UI::button_html(
						[
							'label' => __( '扫描媒体库', 'wpmind' ),
							'size'  => 'sm',
							'attrs' => [ 'id' => 'wpmind-media-scan' ],
						]
					)
					. Wenpai_Admin_UI::button_html(
						[
							'label'    => __( '开始批量生成', 'wpmind' ),
							'variant'  => 'primary',
							'size'     => 'sm',
							'disabled' => true,
							'attrs'    => [ 'id' => 'wpmind-media-bulk-start' ],
						]
					)
					. '</span>',
			]
		);
		?>
		<div class="wpmind-media-progress" hidden>
			<?php
			Wenpai_Admin_UI::progressbar(
				[
					'value' => 0,
					'max'   => 100,
					'label' => __( '批量生成进度', 'wpmind' ),
					'attrs' => [ 'id' => 'wpmind-media-progressbar' ],
				]
			);
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();

		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '工作流预演', 'wpmind' ),
				'sub'               => __( '先预演后审核写入，单任务上限 50 张。', 'wpmind' ),
				'tile'              => false,
				'head_control_html' => '<span class="wenpai-actions">'
					. Wenpai_Admin_UI::button_html(
						[
							'label' => __( '创建预览任务', 'wpmind' ),
							'size'  => 'sm',
							'attrs' => [ 'id' => 'wpmind-media-workflow-create' ],
						]
					)
					. Wenpai_Admin_UI::button_html(
						[
							'label'    => __( '运行预演', 'wpmind' ),
							'size'     => 'sm',
							'disabled' => true,
							'attrs'    => [ 'id' => 'wpmind-media-workflow-dryrun' ],
						]
					)
					. Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '打开任务页', 'wpmind' ),
							'href'    => AdminPage::url( [ 'tab' => 'workflow' ] ),
							'variant' => 'ghost',
							'size'    => 'sm',
						]
					)
					. '</span>',
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'safety', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '内容安全', 'wpmind' ),
				'sub'   => __( '上传图片后异步检测并标记风险内容。', 'wpmind' ),
				'tile'  => false,
			]
		);
		$wpmind_switch( 'wpmind-media-nsfw', 'wpmind_media_nsfw_enabled', '1' === $nsfw_enabled, __( '启用 NSFW 异步检测', 'wpmind' ), __( '在后台异步调用视觉接口标记，不阻塞上传。', 'wpmind' ) );
		Wenpai_Admin_UI::card_foot(
			[
				'actions' => [
					[
						'label'   => __( '保存安全设置', 'wpmind' ),
						'variant' => 'primary',
						'attrs'   => [ 'id' => 'wpmind-save-media-safety' ],
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>
</div>
	<?php
} )();
