<?php
/**
 * Auto-Meta 模块设置（kit 化）。
 * 保留 JS 契约：.wpmind-auto-meta-panel / .wpmind-module-subtab[data-tab] /
 * .wpmind-module-tab-panel[data-panel] / #wpmind-am-total-gen /
 * -month-gen / #wpmind-save-am-settings / #wpmind-am-post-id /
 * #wpmind-am-generate / .wpmind-am-result* / #wpmind-am-result-* /
 * input[name=wpmind_auto_meta_*]。
 *
 * @package WPMind\Modules\AutoMeta
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;

$auto_excerpt  = get_option( 'wpmind_auto_meta_excerpt', '1' );
$auto_tags     = get_option( 'wpmind_auto_meta_tags', '1' );
$auto_category = get_option( 'wpmind_auto_meta_category', '0' );
$auto_faq      = get_option( 'wpmind_auto_meta_faq', '1' );
$auto_seo_desc = get_option( 'wpmind_auto_meta_seo_desc', '1' );
$post_types    = get_option( 'wpmind_auto_meta_post_types', [ 'post', 'page' ] );
if ( ! is_array( $post_types ) ) {
	$post_types = [ 'post', 'page' ];
}

$stats     = get_option( 'wpmind_auto_meta_stats', [] );
$month_key = 'month_' . gmdate( 'Y_m' );
$total_gen = (int) ( $stats['generated'] ?? 0 );
$month_gen = (int) ( $stats[ $month_key ] ?? 0 );

$public_types = get_post_types( [ 'public' => true ], 'objects' );

$wpmind_switch = static function ( string $id, string $name, bool $checked, string $label, string $desc = '' ): void {
	echo '<div class="wenpai-field wenpai-field--toggle field wenpai-field--row">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl">'
		. '<span class="wenpai-switch">'
		. '<input type="checkbox" role="switch" class="wenpai-switch__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked' : '' ) . '>'
		. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
		. '<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on">' . esc_html__( '已开启', 'wpmind' ) . '</span><span class="wenpai-switch__off">' . esc_html__( '未开启', 'wpmind' ) . '</span></span>'
		. '</span>'
		. ( '' !== $desc ? '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>' : '' )
		. '</div></div>';
};
?>

<div class="wpmind-auto-meta-panel">

		<section class="card">
		<div class="wpmind-statbar">
		<div class="wpmind-statbar-item">
			<?php echo AdminIcons::svg( 'doc', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wpmind-statbar-text">
				<div class="k"><?php esc_html_e( '已生成', 'wpmind' ); ?></div>
				<div class="v" id="wpmind-am-total-gen"><?php echo esc_html( (string) $total_gen ); ?></div>
			</div>
		</div>
		<div class="wpmind-statbar-item">
			<?php echo AdminIcons::svg( 'analytics', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wpmind-statbar-text">
				<div class="k"><?php esc_html_e( '本月生成', 'wpmind' ); ?></div>
				<div class="v" id="wpmind-am-month-gen"><?php echo esc_html( (string) $month_gen ); ?></div>
			</div>
		</div>
		</div>
	</section>

	<div class="wenpai-subnav">
		<div class="seg wpmind-module-subtabs">
			<button type="button" class="wpmind-module-subtab active" data-tab="am-settings"><?php esc_html_e( '功能开关', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="am-manual"><?php esc_html_e( '手动生成', 'wpmind' ); ?></button>
		</div>
	</div>

	<div class="wenpai-stack">

		<div class="wpmind-module-tab-panel active" data-panel="am-settings">
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '元数据自动生成', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( 'AI 驱动的文章元数据自动生成：摘要、标签、分类、FAQ Schema、SEO 描述。', 'wpmind' ); ?></p>
					</div>
				</div>
				<?php
				$wpmind_switch( 'wpmind-am-excerpt', 'wpmind_auto_meta_excerpt', '1' === $auto_excerpt, __( '自动生成摘要', 'wpmind' ), __( '发布文章时自动生成 100-150 字摘要，仅在摘要为空时填充。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-am-tags', 'wpmind_auto_meta_tags', '1' === $auto_tags, __( '自动生成标签', 'wpmind' ), __( '自动提取 3-5 个关键词作为文章标签，仅在无标签时添加。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-am-category', 'wpmind_auto_meta_category', '1' === $auto_category, __( '自动匹配分类', 'wpmind' ), __( '从已有分类中选择最匹配的分类，仅在只有默认分类时替换。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-am-faq', 'wpmind_auto_meta_faq', '1' === $auto_faq, __( '自动生成 FAQ Schema', 'wpmind' ), __( '生成 3 个常见问题及回答，自动注入 GEO 模块的 Schema 输出。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-am-seo', 'wpmind_auto_meta_seo_desc', '1' === $auto_seo_desc, __( '自动生成 SEO 描述', 'wpmind' ), __( '生成 120-160 字符的 SEO 描述，存储在 post meta 中。', 'wpmind' ) );
				?>
				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '支持的文章类型', 'wpmind' ); ?></span></div>
					<div class="field-ctl">
						<ul class="wenpai-checkgroup__list">
							<?php foreach ( $public_types as $type_obj ) : ?>
							<li><label class="chk">
								<input type="checkbox" name="wpmind_auto_meta_post_types[]"
									value="<?php echo esc_attr( $type_obj->name ); ?>"
									<?php checked( in_array( $type_obj->name, $post_types, true ) ); ?>>
								<span class="wenpai-checkgroup__text"><?php echo esc_html( $type_obj->labels->singular_name ); ?></span>
							</label></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<div class="card-foot">
					<span class="wenpai-actions">
						<button type="button" class="btn btn-primary" id="wpmind-save-am-settings"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
					</span>
				</div>
			</section>
		</div>

		<div class="wpmind-module-tab-panel" data-panel="am-manual">
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'workflow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '手动生成', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( '输入文章 ID 手动触发元数据生成。生成结果将直接写入文章。', 'wpmind' ); ?></p>
					</div>
				</div>
				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-am-post-id"><?php esc_html_e( '文章 ID', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="wenpai-actions">
							<input type="number" id="wpmind-am-post-id" min="1" placeholder="<?php esc_attr_e( '文章 ID', 'wpmind' ); ?>" style="max-width:10rem">
							<button type="button" class="btn btn-primary" id="wpmind-am-generate"><?php esc_html_e( '生成', 'wpmind' ); ?></button>
						</div>
					</div>
				</div>
				<div class="wpmind-am-result" style="display:none;">
					<h3 class="card-title" style="margin:var(--wenpai-space-4) 0 var(--wenpai-space-2);"><?php esc_html_e( '生成结果', 'wpmind' ); ?></h3>
					<div class="wenpai-table-wrap">
						<table class="tbl wpmind-am-result-table">
							<tbody>
								<tr><th><?php esc_html_e( '摘要', 'wpmind' ); ?></th><td id="wpmind-am-result-excerpt">--</td></tr>
								<tr><th><?php esc_html_e( '标签', 'wpmind' ); ?></th><td id="wpmind-am-result-tags">--</td></tr>
								<tr><th><?php esc_html_e( '分类', 'wpmind' ); ?></th><td id="wpmind-am-result-categories">--</td></tr>
								<tr><th><?php esc_html_e( 'SEO 描述', 'wpmind' ); ?></th><td id="wpmind-am-result-seo">--</td></tr>
								<tr><th><?php esc_html_e( 'FAQ', 'wpmind' ); ?></th><td id="wpmind-am-result-faq">--</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		</div>

	</div>
</div>
