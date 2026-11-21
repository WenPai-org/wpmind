<?php
/**
 * Auto-Meta 模块设置（kit 化）：URL 驱动 subnav + statbar + field--row。
 *
 * @package WPMind\Modules\AutoMeta
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminPage;

( static function (): void {
	$auto_excerpt  = (string) get_option( 'wpmind_auto_meta_excerpt', '1' );
	$auto_tags     = (string) get_option( 'wpmind_auto_meta_tags', '1' );
	$auto_category = (string) get_option( 'wpmind_auto_meta_category', '0' );
	$auto_faq      = (string) get_option( 'wpmind_auto_meta_faq', '1' );
	$auto_seo_desc = (string) get_option( 'wpmind_auto_meta_seo_desc', '1' );
	$post_types    = get_option( 'wpmind_auto_meta_post_types', [ 'post', 'page' ] );
	if ( ! is_array( $post_types ) ) {
		$post_types = [ 'post', 'page' ];
	}

	$stats     = get_option( 'wpmind_auto_meta_stats', [] );
	$month_key = 'month_' . gmdate( 'Y_m' );
	$total_gen = (int) ( $stats['generated'] ?? 0 );
	$month_gen = (int) ( $stats[ $month_key ] ?? 0 );

	$public_types = get_post_types( [ 'public' => true ], 'objects' );

	$sections = [
		'am-settings' => __( '功能开关', 'wpmind' ),
		'am-manual'   => __( '手动生成', 'wpmind' ),
	];
	$section  = Wenpai_Admin_UI::section( $sections, 'am-settings' );

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
					'module'  => 'auto-meta',
					'section' => $sec_id,
				]
			),
			'current' => $section === $sec_id,
		];
	}
	?>

<div class="wpmind-auto-meta-panel wenpai-stack">
	<?php
	Wenpai_Admin_UI::subnav(
		[
			'items'   => $subnav_items,
			'current' => $section,
			'label'   => __( '自动元数据分区', 'wpmind' ),
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label'       => __( '累计生成', 'wpmind' ),
					'value'       => (string) $total_gen,
					'value_attrs' => [ 'id' => 'wpmind-am-total-gen' ],
				],
				[
					'label'       => __( '本月生成', 'wpmind' ),
					'value'       => (string) $month_gen,
					'value_attrs' => [ 'id' => 'wpmind-am-month-gen' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'am-settings', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '元数据自动生成', 'wpmind' ),
				'sub'   => __( '发布文章时自动补充摘要、标签与 FAQ 结构化数据。', 'wpmind' ),
				'tile'  => false,
			]
		);
		$wpmind_switch( 'wpmind-am-excerpt', 'wpmind_auto_meta_excerpt', '1' === $auto_excerpt, __( '自动生成摘要', 'wpmind' ), __( '仅在摘要为空时补充 100–150 字摘要。', 'wpmind' ) );
		$wpmind_switch( 'wpmind-am-tags', 'wpmind_auto_meta_tags', '1' === $auto_tags, __( '自动生成标签', 'wpmind' ), __( '仅在无标签时提取 3–5 个关键词。', 'wpmind' ) );
		$wpmind_switch( 'wpmind-am-category', 'wpmind_auto_meta_category', '1' === $auto_category, __( '自动匹配分类', 'wpmind' ), __( '仅在只有默认分类时自动匹配。', 'wpmind' ) );
		$wpmind_switch( 'wpmind-am-faq', 'wpmind_auto_meta_faq', '1' === $auto_faq, __( '自动生成 FAQ Schema', 'wpmind' ), __( '生成 3 条问答并注入 GEO 结构化输出。', 'wpmind' ) );
		$wpmind_switch( 'wpmind-am-seo', 'wpmind_auto_meta_seo_desc', '1' === $auto_seo_desc, __( '自动生成 SEO 描述', 'wpmind' ), __( '生成 120–160 字符 SEO 描述存入文章元数据。', 'wpmind' ) );
		?>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '支持的内容类型', 'wpmind' ); ?></span></div>
			<div class="field-ctl">
				<ul class="wenpai-checkgroup__list">
					<?php foreach ( $public_types as $type_obj ) : ?>
						<li><label class="chk">
							<input type="checkbox" name="wpmind_auto_meta_post_types[]" value="<?php echo esc_attr( $type_obj->name ); ?>" <?php checked( in_array( $type_obj->name, $post_types, true ) ); ?>>
							<span class="wenpai-checkgroup__text"><?php echo esc_html( $type_obj->labels->singular_name ); ?></span>
						</label></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			[
				'actions' => [
					[
						'label'   => __( '保存设置', 'wpmind' ),
						'variant' => 'primary',
						'attrs'   => [ 'id' => 'wpmind-save-am-settings' ],
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'am-manual', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '手动生成', 'wpmind' ),
				'sub'   => __( '输入文章 ID 立即触发并写入元数据。', 'wpmind' ),
				'tile'  => false,
			]
		);
		?>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind-am-post-id"><?php esc_html_e( '文章 ID', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<div class="wenpai-actions">
					<input type="number" class="wpmind-num" id="wpmind-am-post-id" min="1" placeholder="<?php esc_attr_e( '文章 ID', 'wpmind' ); ?>">
					<button type="button" class="btn btn-primary" id="wpmind-am-generate"><?php esc_html_e( '开始生成', 'wpmind' ); ?></button>
				</div>
			</div>
		</div>
		<div class="wpmind-am-result wenpai-stack wenpai-stack--sm" hidden>
			<?php Wenpai_Admin_UI::section_title( [ 'title' => __( '生成结果', 'wpmind' ) ] ); ?>
			<div class="wenpai-table-wrap">
				<table class="tbl">
					<tbody>
						<tr><th><?php esc_html_e( '摘要', 'wpmind' ); ?></th><td id="wpmind-am-result-excerpt">—</td></tr>
						<tr><th><?php esc_html_e( '标签', 'wpmind' ); ?></th><td id="wpmind-am-result-tags">—</td></tr>
						<tr><th><?php esc_html_e( '分类', 'wpmind' ); ?></th><td id="wpmind-am-result-categories">—</td></tr>
						<tr><th><?php esc_html_e( 'SEO 描述', 'wpmind' ); ?></th><td id="wpmind-am-result-seo">—</td></tr>
						<tr><th><?php esc_html_e( 'FAQ', 'wpmind' ); ?></th><td id="wpmind-am-result-faq">—</td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php Wenpai_Admin_UI::card_close(); ?>
	</div>
</div>
	<?php
} )();
