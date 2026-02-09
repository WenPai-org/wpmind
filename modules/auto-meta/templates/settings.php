<?php
/**
 * Auto-Meta settings template.
 *
 * Uses pill sub-navigation pattern (same as Media Intelligence module).
 *
 * @package WPMind\Modules\AutoMeta
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$am_enabled     = get_option( 'wpmind_auto_meta_enabled', '1' );
$auto_excerpt   = get_option( 'wpmind_auto_meta_excerpt', '1' );
$auto_tags      = get_option( 'wpmind_auto_meta_tags', '1' );
$auto_category  = get_option( 'wpmind_auto_meta_category', '0' );
$auto_faq       = get_option( 'wpmind_auto_meta_faq', '1' );
$auto_seo_desc  = get_option( 'wpmind_auto_meta_seo_desc', '1' );
$post_types     = get_option( 'wpmind_auto_meta_post_types', [ 'post', 'page' ] );
if ( ! is_array( $post_types ) ) {
	$post_types = [ 'post', 'page' ];
}

$stats     = get_option( 'wpmind_auto_meta_stats', [] );
$month_key = 'month_' . gmdate( 'Y_m' );
$total_gen = (int) ( $stats['generated'] ?? 0 );
$month_gen = (int) ( $stats[ $month_key ] ?? 0 );

$public_types = get_post_types( [ 'public' => true ], 'objects' );
?>

<div class="wpmind-auto-meta-panel">
	<!-- Header -->
	<div class="wpmind-module-header">
		<h2 class="wpmind-module-title">
			<span class="dashicons ri-magic-line"></span>
			<?php esc_html_e( 'Auto-Meta', 'wpmind' ); ?>
		</h2>
		<span class="wpmind-module-badge">v1.0</span>
	</div>

	<p class="wpmind-module-desc">
		<?php esc_html_e( 'AI 驱动的文章元数据自动生成：摘要、标签、分类、FAQ Schema、SEO 描述。', 'wpmind' ); ?>
	</p>

	<!-- Stats Cards -->
	<div class="wpmind-module-stats">
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon">
				<span class="dashicons ri-file-text-line"></span>
			</div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-am-total-gen">
					<?php echo esc_html( (string) $total_gen ); ?>
				</span>
				<span class="wpmind-stat-label">
					<?php esc_html_e( '已生成', 'wpmind' ); ?>
				</span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon">
				<span class="dashicons ri-calendar-line"></span>
			</div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-am-month-gen">
					<?php echo esc_html( (string) $month_gen ); ?>
				</span>
				<span class="wpmind-stat-label">
					<?php esc_html_e( '本月生成', 'wpmind' ); ?>
				</span>
			</div>
		</div>
	</div>

	<!-- Sub-tab Navigation -->
	<div class="wpmind-module-subtabs">
		<button type="button" class="wpmind-module-subtab active" data-tab="am-settings">
			<span class="dashicons ri-settings-3-line"></span>
			<?php esc_html_e( '功能开关', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="am-manual">
			<span class="dashicons ri-edit-line"></span>
			<?php esc_html_e( '手动生成', 'wpmind' ); ?>
		</button>
	</div>

	<!-- ========== Tab: Settings ========== -->
	<div class="wpmind-module-tab-panel active" data-panel="am-settings">
		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_auto_meta_excerpt" value="1"
					<?php checked( $auto_excerpt, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动生成摘要', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '发布文章时自动生成 100-150 字摘要，仅在摘要为空时填充。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_auto_meta_tags" value="1"
					<?php checked( $auto_tags, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动生成标签', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '自动提取 3-5 个关键词作为文章标签，仅在无标签时添加。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_auto_meta_category" value="1"
					<?php checked( $auto_category, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动匹配分类', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '从已有分类中选择最匹配的分类，仅在只有默认分类时替换。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_auto_meta_faq" value="1"
					<?php checked( $auto_faq, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动生成 FAQ Schema', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '生成 3 个常见问题及回答，自动注入 GEO 模块的 Schema 输出。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_auto_meta_seo_desc" value="1"
					<?php checked( $auto_seo_desc, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动生成 SEO 描述', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '生成 120-160 字符的 SEO 描述，存储在 post meta 中。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option wpmind-am-post-types-row">
			<label><strong><?php esc_html_e( '支持的文章类型', 'wpmind' ); ?></strong></label>
			<div class="wpmind-am-post-types">
				<?php foreach ( $public_types as $type_obj ) : ?>
				<label class="wpmind-am-type-label">
					<input type="checkbox" name="wpmind_auto_meta_post_types[]"
						value="<?php echo esc_attr( $type_obj->name ); ?>"
						<?php checked( in_array( $type_obj->name, $post_types, true ) ); ?>>
					<?php echo esc_html( $type_obj->labels->singular_name ); ?>
				</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="wpmind-module-actions">
			<button type="button" id="wpmind-save-am-settings" class="button button-primary">
				<span class="dashicons ri-save-line"></span>
				<?php esc_html_e( '保存设置', 'wpmind' ); ?>
			</button>
		</div>
	</div>

	<!-- ========== Tab: Manual Generate ========== -->
	<div class="wpmind-module-tab-panel" data-panel="am-manual">
		<p class="wpmind-am-manual-info">
			<?php esc_html_e( '输入文章 ID 手动触发元数据生成。生成结果将直接写入文章。', 'wpmind' ); ?>
		</p>

		<div class="wpmind-am-manual-form">
			<input type="number" id="wpmind-am-post-id" min="1" placeholder="<?php esc_attr_e( '文章 ID', 'wpmind' ); ?>">
			<button type="button" id="wpmind-am-generate" class="button button-primary">
				<span class="dashicons ri-magic-line"></span>
				<?php esc_html_e( '生成', 'wpmind' ); ?>
			</button>
		</div>

		<div class="wpmind-am-result" style="display:none;">
			<h4><?php esc_html_e( '生成结果', 'wpmind' ); ?></h4>
			<table class="wpmind-am-result-table">
				<tr>
					<th><?php esc_html_e( '摘要', 'wpmind' ); ?></th>
					<td id="wpmind-am-result-excerpt">--</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '标签', 'wpmind' ); ?></th>
					<td id="wpmind-am-result-tags">--</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '分类', 'wpmind' ); ?></th>
					<td id="wpmind-am-result-categories">--</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SEO 描述', 'wpmind' ); ?></th>
					<td id="wpmind-am-result-seo">--</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'FAQ', 'wpmind' ); ?></th>
					<td id="wpmind-am-result-faq">--</td>
				</tr>
			</table>
		</div>
	</div>
</div>
