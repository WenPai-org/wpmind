<?php
/**
 * WPMind 设置页面模板
 *
 * 参考 Gutenberg 风格设计
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap wpmind-wrap">
	<!-- 标题栏 -->
	<h1 class="wpmind-title">
		<span class="wpmind-title-left">
			<img src="https://wpcy.com/wp-content/uploads/2025/07/wpmind-logo.webp" alt="WPMind" class="wpmind-logo">
			<?php esc_html_e( '文派心思', 'wpmind' ); ?>
			<span class="wpmind-version">v<?php echo esc_html( WPMIND_VERSION ); ?></span>
		</span>

		<span class="wpmind-title-right">
			<a href="https://wpcy.com/mind/" target="_blank" class="wpmind-title-link">
				<span class="dashicons ri-book-2-line"></span>
				<?php esc_html_e( '文档', 'wpmind' ); ?>
			</a>
			<a href="https://github.com/WenPai-org/wpmind" target="_blank" class="wpmind-title-link">
				<span class="dashicons ri-github-line"></span>
				<?php esc_html_e( 'GitHub', 'wpmind' ); ?>
			</a>
			<a href="https://wpcy.com/c/wpmind" target="_blank" class="wpmind-title-link">
				<span class="dashicons ri-team-line"></span>
				<?php esc_html_e( '支持', 'wpmind' ); ?>
			</a>
		</span>
	</h1>

	<!-- 主内容区 -->
	<div class="wpmind-content">
		<?php
		// Get module loader instance for checking module status.
		$module_loader = \WPMind\Core\ModuleLoader::instance();
		$geo_module = $module_loader->get_module( 'geo' );
		$geo_enabled = $geo_module && $geo_module['enabled'];
		$cost_control_module = $module_loader->get_module( 'cost-control' );
		$cost_control_enabled = $cost_control_module && $cost_control_module['enabled'];
		$analytics_module = $module_loader->get_module( 'analytics' );
		$analytics_enabled = $analytics_module && $analytics_module['enabled'];
		?>
		<!-- Tab 卡片 -->
		<div class="wpmind-tabs-card">
			<!-- Tab 导航 -->
			<nav class="wpmind-tab-list">
				<a href="#overview" class="wpmind-tab wpmind-tab-active" data-tab="overview">
					<?php esc_html_e( '概览', 'wpmind' ); ?>
				</a>
				<?php if ( $analytics_enabled ) : ?>
				<a href="#dashboard" class="wpmind-tab" data-tab="dashboard">
					<?php esc_html_e( '仪表板', 'wpmind' ); ?>
				</a>
				<?php endif; ?>
				<a href="#services" class="wpmind-tab" data-tab="services">
					<?php esc_html_e( '文本服务', 'wpmind' ); ?>
				</a>
				<a href="#images" class="wpmind-tab" data-tab="images">
					<?php esc_html_e( '图像服务', 'wpmind' ); ?>
				</a>
				<a href="#routing" class="wpmind-tab" data-tab="routing">
					<?php esc_html_e( '智能路由', 'wpmind' ); ?>
				</a>
				<?php if ( $cost_control_enabled ) : ?>
				<a href="#budget" class="wpmind-tab" data-tab="budget">
					<?php esc_html_e( '预算管理', 'wpmind' ); ?>
				</a>
				<?php endif; ?>
				<?php if ( $geo_enabled ) : ?>
				<a href="#geo" class="wpmind-tab" data-tab="geo">
					<?php esc_html_e( 'GEO 优化', 'wpmind' ); ?>
				</a>
				<?php endif; ?>
				<a href="#modules" class="wpmind-tab" data-tab="modules">
					<?php esc_html_e( '模块', 'wpmind' ); ?>
				</a>
			</nav>

			<!-- Tab 内容 -->
			<div id="overview" class="wpmind-tab-pane wpmind-tab-pane-active">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/overview.php'; ?>
			</div>
			<?php if ( $analytics_enabled ) : ?>
			<div id="dashboard" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/dashboard.php'; ?>
			</div>
			<?php endif; ?>
			<div id="services" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/services.php'; ?>
			</div>
			<div id="images" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/images.php'; ?>
			</div>
			<div id="routing" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/routing.php'; ?>
			</div>
			<?php if ( $cost_control_enabled ) : ?>
			<div id="budget" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/budget.php'; ?>
			</div>
			<?php endif; ?>
			<?php if ( $geo_enabled ) : ?>
			<div id="geo" class="wpmind-tab-pane">
				<?php
				$geo_settings = WPMIND_PATH . 'modules/geo/templates/settings.php';
				if ( file_exists( $geo_settings ) ) {
					include $geo_settings;
				}
				?>
			</div>
			<?php endif; ?>
			<div id="modules" class="wpmind-tab-pane">
				<?php include WPMIND_PLUGIN_DIR . 'templates/tabs/modules.php'; ?>
			</div>
		</div>
	</div>
</div>
