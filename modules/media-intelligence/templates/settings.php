<?php
/**
 * Media Intelligence settings template.
 *
 * Uses pill sub-navigation pattern (same as GEO module).
 *
 * @package WPMind\Modules\MediaIntelligence
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$auto_alt     = get_option( 'wpmind_media_auto_alt', '1' );
$auto_title   = get_option( 'wpmind_media_auto_title', '1' );
$nsfw_enabled = get_option( 'wpmind_media_nsfw_enabled', '0' );
$language     = get_option( 'wpmind_media_language', 'auto' );

$stats     = get_option( 'wpmind_media_stats', [] );
$month_key = 'month_' . gmdate( 'Y_m' );
$total_gen = (int) ( $stats['generated'] ?? 0 );
$month_gen = (int) ( $stats[ $month_key ] ?? 0 );
?>

<!-- Header -->
<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-image-ai-line"></span>
		<?php esc_html_e( '媒体智能', 'wpmind' ); ?>
	</h2>
	<span class="wpmind-module-badge">v1.0</span>
</div>

<div class="wpmind-tab-pane-body">
<div class="wpmind-media-panel">

	<p class="wpmind-module-desc">
		<?php esc_html_e( 'AI 驱动的图片 Alt Text、标题、描述自动生成，提升 SEO 和无障碍访问。', 'wpmind' ); ?>
	</p>

	<!-- Stats Cards -->
	<div class="wpmind-module-stats">
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon">
				<span class="dashicons ri-image-line"></span>
			</div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-media-total-gen">
					<?php echo esc_html( (string) $total_gen ); ?>
				</span>
				<span class="wpmind-stat-label">
					<?php esc_html_e( '已处理', 'wpmind' ); ?>
				</span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon">
				<span class="dashicons ri-search-line"></span>
			</div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-media-missing">--</span>
				<span class="wpmind-stat-label">
					<?php esc_html_e( '待处理', 'wpmind' ); ?>
				</span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon">
				<span class="dashicons ri-calendar-line"></span>
			</div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-media-month-gen">
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
		<button type="button" class="wpmind-module-subtab active" data-tab="settings">
			<span class="dashicons ri-settings-3-line"></span>
			<?php esc_html_e( '基本设置', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="batch">
			<span class="dashicons ri-stack-line"></span>
			<?php esc_html_e( '批量处理', 'wpmind' ); ?>
		</button>
		<button type="button" class="wpmind-module-subtab" data-tab="safety">
			<span class="dashicons ri-shield-check-line"></span>
			<?php esc_html_e( '内容安全', 'wpmind' ); ?>
		</button>
	</div>

	<!-- ========== Tab: Basic Settings ========== -->
	<div class="wpmind-module-tab-panel active" data-panel="settings">
		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_media_auto_alt" value="1"
					<?php checked( $auto_alt, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '上传时自动生成 Alt Text', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '新图片上传后自动通过 AI 生成描述性 alt text，已有 alt text 的图片不会被覆盖。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_media_auto_title" value="1"
					<?php checked( $auto_title, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( '自动生成标题和描述', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '同时自动填充图片标题（post_title）和说明（caption）。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-module-option wpmind-media-language-row">
			<label for="wpmind-media-language">
				<strong><?php esc_html_e( '生成语言', 'wpmind' ); ?></strong>
			</label>
			<select name="wpmind_media_language" id="wpmind-media-language">
				<option value="auto" <?php selected( $language, 'auto' ); ?>>
					<?php esc_html_e( '自动检测', 'wpmind' ); ?>
				</option>
				<option value="zh" <?php selected( $language, 'zh' ); ?>>
					<?php esc_html_e( '中文', 'wpmind' ); ?>
				</option>
				<option value="en" <?php selected( $language, 'en' ); ?>>
					<?php esc_html_e( 'English', 'wpmind' ); ?>
				</option>
			</select>
		</div>

		<div class="wpmind-module-actions">
			<button type="button" id="wpmind-save-media-settings" class="button button-primary">
				<span class="dashicons ri-save-line"></span>
				<?php esc_html_e( '保存设置', 'wpmind' ); ?>
			</button>
		</div>
	</div>

	<!-- ========== Tab: Batch Processing ========== -->
	<div class="wpmind-module-tab-panel" data-panel="batch">
		<p class="wpmind-media-bulk-info">
			<?php esc_html_e( '扫描媒体库中缺少 Alt Text 的图片，并批量通过 AI 生成。每次处理 5 张，避免超时。', 'wpmind' ); ?>
		</p>

		<div class="wpmind-media-bulk-actions">
			<button type="button" id="wpmind-media-scan" class="button">
				<span class="dashicons ri-search-eye-line"></span>
				<?php esc_html_e( '扫描', 'wpmind' ); ?>
			</button>
			<button type="button" id="wpmind-media-bulk-start" class="button button-primary" disabled>
				<span class="dashicons ri-play-line"></span>
				<?php esc_html_e( '开始批量生成', 'wpmind' ); ?>
			</button>
		</div>

		<div class="wpmind-media-progress" style="display:none;">
			<div class="wpmind-media-progress-bar">
				<div class="wpmind-media-progress-fill" style="width:0%"></div>
			</div>
			<span class="wpmind-media-progress-text">0%</span>
		</div>
	</div>

	<!-- ========== Tab: Content Safety ========== -->
	<div class="wpmind-module-tab-panel" data-panel="safety">
		<div class="wpmind-module-option">
			<label class="wpmind-toggle">
				<input type="checkbox" name="wpmind_media_nsfw_enabled" value="1"
					<?php checked( $nsfw_enabled, '1' ); ?>>
				<span class="wpmind-toggle-slider"></span>
			</label>
			<div class="wpmind-module-option-text">
				<strong><?php esc_html_e( 'NSFW 内容检测', 'wpmind' ); ?></strong>
				<p><?php esc_html_e( '上传时自动检测不当内容并标记，管理员可在媒体库中筛选已标记的图片。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-mi-safety-note">
			<span class="dashicons ri-information-line"></span>
			<p><?php esc_html_e( '启用后，每张新上传的图片会额外消耗一次 Vision API 调用进行安全检测。检测结果保存在图片元数据中，不会阻止上传。', 'wpmind' ); ?></p>
		</div>

		<div class="wpmind-module-actions">
			<button type="button" id="wpmind-save-media-safety" class="button button-primary">
				<span class="dashicons ri-save-line"></span>
				<?php esc_html_e( '保存设置', 'wpmind' ); ?>
			</button>
		</div>
	</div>
</div>
</div>
