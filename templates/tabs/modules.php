<?php
/**
 * Modules Management Tab
 *
 * @package WPMind
 * @since 3.2.0
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Core\ModuleLoader;

$module_loader = ModuleLoader::instance();
$modules       = $module_loader->get_modules();
?>

<div class="wpmind-modules-header">
	<h2><span class="dashicons ri-puzzle-line"></span> <?php esc_html_e( '模块管理', 'wpmind' ); ?></h2>
</div>
<div class="wpmind-tab-pane-body">
<div class="wpmind-modules-container">
	<p class="description">
		<?php esc_html_e( '启用或禁用 WPMind 功能模块。禁用不需要的模块可以提升性能。', 'wpmind' ); ?>
	</p>

	<div class="wpmind-modules-grid">
		<?php foreach ( $modules as $module_id => $module ) : ?>
		<div class="wpmind-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-disabled'; ?>" data-module-id="<?php echo esc_attr( $module_id ); ?>">
			<div class="wpmind-module-header">
				<span class="wpmind-module-icon dashicons <?php echo esc_attr( $module['icon'] ); ?>"></span>
				<div class="wpmind-module-info">
					<h3 class="wpmind-module-name">
						<?php echo esc_html( $module['name'] ); ?>
						<?php if ( ! $module['can_disable'] ) : ?>
							<span class="wpmind-module-badge-core" title="<?php esc_attr_e( '核心模块，不可禁用', 'wpmind' ); ?>"><?php esc_html_e( '核心', 'wpmind' ); ?></span>
						<?php endif; ?>
					</h3>
					<span class="wpmind-module-version">v<?php echo esc_html( $module['version'] ); ?></span>
				</div>
				<div class="wpmind-module-toggle">
					<?php if ( ! $module['can_disable'] ) : ?>
					<span class="wpmind-toggle-locked" title="<?php esc_attr_e( '此模块为核心依赖，路由策略和 API 需要它保持启用', 'wpmind' ); ?>">
						<span class="dashicons ri-lock-line"></span>
					</span>
					<?php else : ?>
					<label class="wpmind-switch">
						<input type="checkbox"
								class="wpmind-module-switch"
								data-module-id="<?php echo esc_attr( $module_id ); ?>"
								<?php checked( $module['enabled'] ); ?>>
						<span class="wpmind-switch-slider"></span>
					</label>
					<?php endif; ?>
				</div>
			</div>
			<div class="wpmind-module-body">
				<p class="wpmind-module-description"><?php echo esc_html( $module['description'] ); ?></p>
			</div>
			<div class="wpmind-module-footer">
				<span class="wpmind-module-status">
					<?php if ( ! $module['can_disable'] ) : ?>
						<span class="status-core"><span class="dashicons ri-shield-check-line"></span> <?php esc_html_e( '核心模块', 'wpmind' ); ?></span>
					<?php elseif ( $module['enabled'] ) : ?>
						<span class="status-enabled"><span class="dashicons ri-checkbox-circle-line"></span> <?php esc_html_e( '已启用', 'wpmind' ); ?></span>
					<?php else : ?>
						<span class="status-disabled"><span class="dashicons ri-close-circle-line"></span> <?php esc_html_e( '已禁用', 'wpmind' ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( $module['enabled'] && ! empty( $module['settings_tab'] ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmind&tab=' . $module['settings_tab'] ) ); ?>" class="wpmind-module-settings-link">
						<span class="dashicons ri-settings-3-line"></span> <?php esc_html_e( '设置', 'wpmind' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>

		<?php if ( empty( $modules ) ) : ?>
		<div class="wpmind-no-modules">
			<span class="dashicons ri-puzzle-line"></span>
			<p><?php esc_html_e( '暂无可用模块', 'wpmind' ); ?></p>
		</div>
		<?php endif; ?>
	</div>
</div>
</div>
