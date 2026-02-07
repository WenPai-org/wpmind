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

// 价值摘要数据
$all_stats       = UsageTracker::get_stats();
$provider_stats  = $all_stats['providers'] ?? [];
$top_model       = '—';
$top_model_reqs  = 0;
foreach ( $provider_stats as $pid => $pdata ) {
	foreach ( ( $pdata['models'] ?? [] ) as $mname => $mdata ) {
		if ( ( $mdata['requests'] ?? 0 ) > $top_model_reqs ) {
			$top_model_reqs = $mdata['requests'];
			$top_model      = $mname;
		}
	}
}

$recent_history = UsageTracker::get_history( 5 );
$avg_latency    = 0;
if ( ! empty( $recent_history ) ) {
	$latency_sum = array_sum( array_column( $recent_history, 'latency_ms' ) );
	$avg_latency = (int) round( $latency_sum / count( $recent_history ) );
}

// 服务可用率
$total_success_rate = 0;
$rate_count         = 0;
foreach ( $providers as $p ) {
	if ( isset( $p['success_rate'] ) ) {
		$total_success_rate += $p['success_rate'];
		++$rate_count;
	}
}
$avg_success_rate = $rate_count > 0 ? (int) round( $total_success_rate / $rate_count ) : 100;
?>

<div class="wpmind-overview">

	<!-- Hero -->
	<div class="wpmind-overview-hero">
		<div class="wpmind-overview-hero-content">
			<h2 class="wpmind-overview-hero-title">
				<span class="dashicons ri-brain-line"></span>
				<?php esc_html_e( '文派心思', 'wpmind' ); ?>
			</h2>
			<p class="wpmind-overview-hero-desc"><?php esc_html_e( 'WordPress AI 智能路由引擎', 'wpmind' ); ?></p>
			<div class="wpmind-overview-hero-meta">
				<span>v<?php echo esc_html( WPMIND_VERSION ); ?></span>
				<span class="wpmind-overview-hero-sep">&middot;</span>
				<span><?php echo esc_html( count( $providers ) ); ?> <?php esc_html_e( '个 Provider', 'wpmind' ); ?></span>
				<span class="wpmind-overview-hero-sep">&middot;</span>
				<span><?php echo esc_html( $active_count ); ?>/<?php echo esc_html( count( $modules ) ); ?> <?php esc_html_e( '模块启用', 'wpmind' ); ?></span>
			</div>
		</div>
		<span class="wpmind-overview-hero-icon dashicons ri-robot-2-line"></span>
	</div>

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
				<span class="wpmind-overview-stat-value"><?php echo esc_html( UsageTracker::format_tokens( ( $today['input_tokens'] ?? 0 ) + ( $today['output_tokens'] ?? 0 ) ) ); ?></span>
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
		<div class="wpmind-overview-card wpmind-overview-card--bg-icon">
			<span class="wpmind-overview-card-bg dashicons ri-server-line"></span>
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( 'Provider 状态', 'wpmind' ); ?></h3>
				<a href="#services" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="services"><?php esc_html_e( '管理', 'wpmind' ); ?> →</a>
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
		<div class="wpmind-overview-card wpmind-overview-card--bg-icon">
			<span class="wpmind-overview-card-bg dashicons ri-puzzle-line"></span>
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( '模块状态', 'wpmind' ); ?></h3>
				<a href="#modules" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="modules"><?php esc_html_e( '管理', 'wpmind' ); ?> →</a>
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

	<!-- 价值摘要 + 最近活动 -->
	<div class="wpmind-overview-grid">

		<!-- 左栏：本月摘要 -->
		<div class="wpmind-overview-card wpmind-overview-card--accent-blue wpmind-overview-card--bg-icon">
			<span class="wpmind-overview-card-bg dashicons ri-bar-chart-grouped-line"></span>>
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( '本月摘要', 'wpmind' ); ?></h3>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-summary-grid">
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon" style="background: var(--wpmind-primary-light); color: var(--wpmind-primary);">
							<span class="dashicons ri-robot-2-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html( $top_model ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e( '最常用模型', 'wpmind' ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon" style="background: #fef3c7; color: #d97706;">
							<span class="dashicons ri-timer-flash-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo $avg_latency > 0 ? esc_html( number_format_i18n( $avg_latency ) . 'ms' ) : '—'; ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e( '平均响应时间', 'wpmind' ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon" style="background: var(--wpmind-success-light); color: var(--wpmind-success);">
							<span class="dashicons ri-shield-check-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html( $avg_success_rate . '%' ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e( '服务可用率', 'wpmind' ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon" style="background: #f3e8ff; color: #7c3aed;">
							<span class="dashicons ri-token-swap-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html( UsageTracker::format_tokens( ( $month['input_tokens'] ?? 0 ) + ( $month['output_tokens'] ?? 0 ) ) ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e( '本月总 Tokens', 'wpmind' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 右栏：最近活动 -->
		<div class="wpmind-overview-card wpmind-overview-card--accent-green wpmind-overview-card--bg-icon">
			<span class="wpmind-overview-card-bg dashicons ri-pulse-line"></span>>
			<div class="wpmind-overview-card-header">
				<h3><?php esc_html_e( '最近活动', 'wpmind' ); ?></h3>
				<?php if ( class_exists( 'WPMind\\Modules\\Analytics\\AnalyticsManager' ) ) : ?>
					<a href="#analytics" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="analytics"><?php esc_html_e( '查看全部', 'wpmind' ); ?> →</a>
				<?php endif; ?>
			</div>
			<div class="wpmind-overview-card-body">
				<?php if ( empty( $recent_history ) ) : ?>
					<p class="wpmind-overview-empty"><?php esc_html_e( '暂无 API 调用记录', 'wpmind' ); ?></p>
				<?php else : ?>
					<div class="wpmind-overview-activity-list">
						<?php foreach ( $recent_history as $record ) :
							$time     = isset( $record['timestamp'] ) ? wp_date( 'H:i', $record['timestamp'] ) : '—';
							$pname    = UsageTracker::get_provider_display_name( $record['provider'] ?? '' );
							$model    = $record['model'] ?? '—';
							$tokens   = ( $record['input_tokens'] ?? 0 ) + ( $record['output_tokens'] ?? 0 );
							$latency  = $record['latency_ms'] ?? 0;
							$picon    = UsageTracker::get_provider_icon( $record['provider'] ?? '' );
						?>
						<div class="wpmind-overview-activity-item">
							<span class="wpmind-overview-activity-time"><?php echo esc_html( $time ); ?></span>
							<span class="wpmind-overview-activity-provider">
								<span class="dashicons <?php echo esc_attr( $picon ); ?>"></span>
								<?php echo esc_html( $pname ); ?>
							</span>
							<span class="wpmind-overview-activity-model"><?php echo esc_html( $model ); ?></span>
							<span class="wpmind-overview-activity-meta">
								<span class="wpmind-overview-activity-tokens"><?php echo esc_html( UsageTracker::format_tokens( $tokens ) ); ?></span>
								<?php if ( $latency > 0 ) : ?>
									<span class="wpmind-overview-activity-latency"><?php echo esc_html( $latency . 'ms' ); ?></span>
								<?php endif; ?>
							</span>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
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
				<a href="#services" class="wpmind-overview-shortcut wpmind-tab-link" data-tab-link="services">
					<span class="dashicons ri-settings-3-line"></span>
					<span><?php esc_html_e( '文本服务', 'wpmind' ); ?></span>
				</a>
				<a href="#images" class="wpmind-overview-shortcut wpmind-tab-link" data-tab-link="images">
					<span class="dashicons ri-image-line"></span>
					<span><?php esc_html_e( '图像服务', 'wpmind' ); ?></span>
				</a>
				<a href="#routing" class="wpmind-overview-shortcut wpmind-tab-link" data-tab-link="routing">
					<span class="dashicons ri-route-line"></span>
					<span><?php esc_html_e( '智能路由', 'wpmind' ); ?></span>
				</a>
				<a href="#budget" class="wpmind-overview-shortcut wpmind-tab-link" data-tab-link="budget">
					<span class="dashicons ri-wallet-3-line"></span>
					<span><?php esc_html_e( '预算管理', 'wpmind' ); ?></span>
				</a>
				<a href="#modules" class="wpmind-overview-shortcut wpmind-tab-link" data-tab-link="modules">
					<span class="dashicons ri-puzzle-line"></span>
					<span><?php esc_html_e( '模块管理', 'wpmind' ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<!-- 底部链接 -->
	<div class="wpmind-overview-footer">
		<a href="https://wpcommunity.com/c/wpmind/" target="_blank" rel="noopener">
			<span class="dashicons ri-discuss-line"></span>
			<?php esc_html_e( '文派社区', 'wpmind' ); ?>
		</a>
		<span class="wpmind-overview-footer-sep">&middot;</span>
		<a href="https://wpmind.developer.cyberforums.com/" target="_blank" rel="noopener">
			<span class="dashicons ri-book-open-line"></span>
			<?php esc_html_e( '使用文档', 'wpmind' ); ?>
		</a>
		<span class="wpmind-overview-footer-sep">&middot;</span>
		<span class="wpmind-overview-footer-version">WPMind v<?php echo esc_html( WPMIND_VERSION ); ?></span>
	</div>

</div>
