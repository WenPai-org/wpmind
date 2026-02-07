<?php
/**
 * WPMind Overview Tab — 始终可用的轻量首页
 *
 * @package WPMind
 * @since 3.9.0
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Modules\CostControl\UsageTracker;
use WPMind\Failover\FailoverManager;
use WPMind\Core\ModuleLoader;

// 数据准备
$today       = UsageTracker::get_today_stats();
$month       = UsageTracker::get_month_stats();
$failover    = FailoverManager::instance();
$providers   = $failover->get_status_summary();
$modules     = ModuleLoader::instance()->get_modules();
$active_count = count( array_filter( $modules, fn( $m ) => $m['enabled'] ) );

// Provider 状态统计
$healthy  = 0;
$degraded = 0;
$down     = 0;
foreach ( $providers as $p ) {
	$state = $p['state'] ?? 'unknown';
	if ( 'healthy' === $state || 'closed' === $state ) {
		++$healthy;
	} elseif ( 'half_open' === $state ) {
		++$degraded;
	} else {
		++$down;
	}
}
?>

<div class="wpmind-overview">

	<!-- 统计卡片 -->
	<div class="wpmind-overview-stats">
		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon" style="background: var(--wpmind-primary-light); color: var(--wpmind-primary);">
				<span class="dashicons ri-chat-ai-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html( number_format_i18n( $today['requests'] ?? 0 ) ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e( '今日请求', 'wpmind' ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon" style="background: var(--wpmind-success-light); color: var(--wpmind-success);">
				<span class="dashicons ri-token-swap-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html( UsageTracker::format_tokens( $today['tokens'] ?? 0 ) ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e( '今日 Tokens', 'wpmind' ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon" style="background: var(--wpmind-warning-light); color: var(--wpmind-warning);">
				<span class="dashicons ri-money-cny-circle-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html( UsageTracker::format_cost_by_currency( $month['cost_usd'] ?? 0, $month['cost_cny'] ?? 0 ) ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e( '本月费用', 'wpmind' ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon" style="background: #f3e8ff; color: #7c3aed;">
				<span class="dashicons ri-server-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html( $healthy ); ?><span class="wpmind-overview-stat-sub">/<?php echo esc_html( count( $providers ) ); ?></span></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e( 'Provider 在线', 'wpmind' ); ?></span>
			</div>
		</div>
	</div>

	<!-- 双栏布局 -->
	<div class="wpmind-overview-grid">

		<!-- 左栏：Provider 状态 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( 'Provider 状态', 'wpmind' ); ?></h3>
				<a href="#services" class="wpmind-overview-card-link" data-tab-link="services"><?php esc_html_e( '管理', 'wpmind' ); ?> →</a>
			</div>
			<div class="wpmind-overview-card-body">
				<?php if ( empty( $providers ) ) : ?>
					<p class="wpmind-overview-empty"><?php esc_html_e( '暂无已配置的 Provider', 'wpmind' ); ?></p>
				<?php else : ?>
					<div class="wpmind-overview-provider-list">
						<?php foreach ( $providers as $key => $p ) :
							$state = $p['state'] ?? 'unknown';
							$is_ok = in_array( $state, [ 'healthy', 'closed' ], true );
							$name  = UsageTracker::get_provider_display_name( $key );
							$color = $is_ok ? 'var(--wpmind-success)' : 'var(--wpmind-error)';
							$icon  = $is_ok ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill';
						?>
						<div class="wpmind-overview-provider-item">
							<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color: <?php echo esc_attr( $color ); ?>"></span>
							<span class="wpmind-overview-provider-name"><?php echo esc_html( $name ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- 右栏：模块状态 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( '模块状态', 'wpmind' ); ?></h3>
				<a href="#modules" class="wpmind-overview-card-link" data-tab-link="modules"><?php esc_html_e( '管理', 'wpmind' ); ?> →</a>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-module-list">
					<?php foreach ( $modules as $mid => $m ) :
						$enabled = $m['enabled'];
						$color   = $enabled ? 'var(--wpmind-success)' : 'var(--wpmind-gray-400)';
						$icon    = $enabled ? 'ri-checkbox-circle-fill' : 'ri-close-circle-line';
						$badge   = ! $m['can_disable'] ? ' <span class="wpmind-overview-badge-core">' . esc_html__( '核心', 'wpmind' ) . '</span>' : '';
					?>
					<div class="wpmind-overview-module-item">
						<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color: <?php echo esc_attr( $color ); ?>"></span>
						<span class="wpmind-overview-module-name"><?php echo esc_html( $m['name'] ); ?><?php echo $badge; // phpcs:ignore ?></span>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

	</div>

	<!-- 快捷入口 -->
	<div class="wpmind-overview-card">
		<div class="wpmind-overview-card-header">
			<h3><?php esc_html_e( '快捷入口', 'wpmind' ); ?></h3>
		</div>
		<div class="wpmind-overview-card-body">
			<div class="wpmind-overview-shortcuts">
				<a href="#services" class="wpmind-overview-shortcut" data-tab-link="services">
					<span class="dashicons ri-settings-3-line"></span>
					<span><?php esc_html_e( '文本服务', 'wpmind' ); ?></span>
				</a>
				<a href="#images" class="wpmind-overview-shortcut" data-tab-link="images">
					<span class="dashicons ri-image-line"></span>
					<span><?php esc_html_e( '图像服务', 'wpmind' ); ?></span>
				</a>
				<a href="#routing" class="wpmind-overview-shortcut" data-tab-link="routing">
					<span class="dashicons ri-route-line"></span>
					<span><?php esc_html_e( '智能路由', 'wpmind' ); ?></span>
				</a>
				<a href="#budget" class="wpmind-overview-shortcut" data-tab-link="budget">
					<span class="dashicons ri-wallet-3-line"></span>
					<span><?php esc_html_e( '预算管理', 'wpmind' ); ?></span>
				</a>
				<a href="#modules" class="wpmind-overview-shortcut" data-tab-link="modules">
					<span class="dashicons ri-puzzle-line"></span>
					<span><?php esc_html_e( '模块管理', 'wpmind' ); ?></span>
				</a>
			</div>
		</div>
	</div>

</div>
