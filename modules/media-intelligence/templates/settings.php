<?php
/**
 * Media Intelligence 模块设置（kit 化）。
 * 保留 JS 契约：.wpmind-media-panel / .wpmind-module-subtab[data-tab] /
 * .wpmind-module-tab-panel[data-panel] / #wpmind-media-total-gen /
 * -missing / -month-gen / #wpmind-save-media-settings /
 * #wpmind-save-media-safety / #wpmind-media-scan / #wpmind-media-bulk-start /
 * #wpmind-media-workflow-create / #wpmind-media-workflow-dryrun /
 * .wpmind-media-progress* / input[name=wpmind_media_*]。
 *
 * @package WPMind\Modules\MediaIntelligence
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

$auto_alt     = get_option( 'wpmind_media_auto_alt', '1' );
$auto_title   = get_option( 'wpmind_media_auto_title', '1' );
$nsfw_enabled = get_option( 'wpmind_media_nsfw_enabled', '0' );
$language     = get_option( 'wpmind_media_language', 'auto' );

$stats     = get_option( 'wpmind_media_stats', [] );
$month_key = 'month_' . gmdate( 'Y_m' );
$total_gen = (int) ( $stats['generated'] ?? 0 );
$month_gen = (int) ( $stats[ $month_key ] ?? 0 );

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

<div class="wpmind-media-panel">

	<div class="wpmind-statbar" style="margin-bottom:var(--wenpai-space-5);">
		<div class="wpmind-statbar-item">
			<?php echo AdminIcons::svg( 'image', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wpmind-statbar-text">
				<div class="k"><?php esc_html_e( '已处理', 'wpmind' ); ?></div>
				<div class="v" id="wpmind-media-total-gen"><?php echo esc_html( (string) $total_gen ); ?></div>
			</div>
		</div>
		<div class="wpmind-statbar-item">
			<?php echo AdminIcons::svg( 'eye', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wpmind-statbar-text">
				<div class="k"><?php esc_html_e( '待处理', 'wpmind' ); ?></div>
				<div class="v" id="wpmind-media-missing">--</div>
			</div>
		</div>
		<div class="wpmind-statbar-item">
			<?php echo AdminIcons::svg( 'analytics', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wpmind-statbar-text">
				<div class="k"><?php esc_html_e( '本月生成', 'wpmind' ); ?></div>
				<div class="v" id="wpmind-media-month-gen"><?php echo esc_html( (string) $month_gen ); ?></div>
			</div>
		</div>
	</div>

	<div class="wenpai-subnav">
		<div class="seg wpmind-module-subtabs">
			<button type="button" class="wpmind-module-subtab active" data-tab="settings"><?php esc_html_e( '基本设置', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="batch"><?php esc_html_e( '批量处理', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-module-subtab" data-tab="safety"><?php esc_html_e( '内容安全', 'wpmind' ); ?></button>
		</div>
	</div>

	<div class="wenpai-stack">

		<div class="wpmind-module-tab-panel active" data-panel="settings">
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '自动生成', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( 'AI 驱动的图片 Alt Text、标题、描述自动生成，提升 SEO 和无障碍访问。', 'wpmind' ); ?></p>
					</div>
				</div>
				<?php
				$wpmind_switch( 'wpmind-media-auto-alt', 'wpmind_media_auto_alt', '1' === $auto_alt, __( '上传时自动生成 Alt Text', 'wpmind' ), __( '新图片上传后自动通过 AI 生成描述性 alt text，已有 alt text 的图片不会被覆盖。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-media-auto-title', 'wpmind_media_auto_title', '1' === $auto_title, __( '自动生成标题和描述', 'wpmind' ), __( '同时自动填充图片标题（post_title）和说明（caption）。', 'wpmind' ) );
				?>
				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-media-language"><?php esc_html_e( '生成语言', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<select id="wpmind-media-language" name="wpmind_media_language" style="max-width:280px">
							<option value="auto" <?php selected( $language, 'auto' ); ?>><?php esc_html_e( '自动检测', 'wpmind' ); ?></option>
							<option value="zh" <?php selected( $language, 'zh' ); ?>><?php esc_html_e( '中文', 'wpmind' ); ?></option>
							<option value="en" <?php selected( $language, 'en' ); ?>><?php esc_html_e( 'English', 'wpmind' ); ?></option>
						</select>
					</div>
				</div>
				<div class="card-foot">
					<span class="wenpai-actions">
						<button type="button" class="btn btn-primary" id="wpmind-save-media-settings"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
					</span>
				</div>
			</section>
		</div>

		<div class="wpmind-module-tab-panel" data-panel="batch">
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'modules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '批量处理', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( '扫描媒体库中缺少 Alt Text 的图片，并批量通过 AI 生成。每次处理 5 张，避免超时。', 'wpmind' ); ?></p>
					</div>
					<span class="wenpai-actions">
						<button type="button" class="btn btn-sm" id="wpmind-media-scan"><?php esc_html_e( '扫描', 'wpmind' ); ?></button>
						<button type="button" class="btn btn-sm btn-primary" id="wpmind-media-bulk-start" disabled><?php esc_html_e( '开始批量生成', 'wpmind' ); ?></button>
					</span>
				</div>
				<div class="wpmind-media-progress" style="display:none;">
					<div class="wpmind-media-progress-bar"><div class="wpmind-media-progress-fill" style="width:0%"></div></div>
					<span class="wpmind-media-progress-text">0%</span>
				</div>
			</section>
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'workflow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '工作流 Dry-run', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( '扫描缺 Alt 图片并创建统一任务，Vision 只写入任务预览，不会改附件 Alt / 标题 / 说明。单任务最多 50 张。', 'wpmind' ); ?></p>
					</div>
					<span class="wenpai-actions">
						<button type="button" class="btn btn-sm" id="wpmind-media-workflow-create"><?php esc_html_e( '创建 Alt 预览任务', 'wpmind' ); ?></button>
						<button type="button" class="btn btn-sm" id="wpmind-media-workflow-dryrun" disabled><?php esc_html_e( 'Dry-run 预览', 'wpmind' ); ?></button>
						<a class="btn btn-sm btn-ghost" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'workflow' ] ) ); ?>"><?php esc_html_e( '打开任务页', 'wpmind' ); ?></a>
					</span>
				</div>
			</section>
		</div>

		<div class="wpmind-module-tab-panel" data-panel="safety">
			<section class="card">
				<div class="card-head">
					<span class="tile"><?php echo AdminIcons::svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
					<div>
						<h2 class="card-title"><?php esc_html_e( '内容安全', 'wpmind' ); ?></h2>
						<p class="card-sub"><?php esc_html_e( 'NSFW 内容检测与标记。', 'wpmind' ); ?></p>
					</div>
				</div>
				<?php $wpmind_switch( 'wpmind-media-nsfw', 'wpmind_media_nsfw_enabled', '1' === $nsfw_enabled, __( 'NSFW 内容检测', 'wpmind' ), __( '上传时自动检测不当内容并标记，管理员可在媒体库中筛选已标记的图片。', 'wpmind' ) ); ?>
				<p class="meta" style="margin-top:var(--wenpai-space-3);"><?php esc_html_e( '启用后，每张新上传的图片会额外消耗一次 Vision API 调用进行安全检测。检测结果保存在图片元数据中，不会阻止上传。', 'wpmind' ); ?></p>
				<div class="card-foot">
					<span class="wenpai-actions">
						<button type="button" class="btn btn-primary" id="wpmind-save-media-safety"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
					</span>
				</div>
			</section>
		</div>

	</div>
</div>
