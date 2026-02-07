<?php
/**
 * WPMind 仪表板 Tab
 *
 * 包含：用量统计 + 分析图表 + 服务状态
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取数据
$failover_manager = \WPMind\Failover\FailoverManager::instance();
$provider_status  = $failover_manager->get_status_summary();

$usage_stats = \WPMind\Modules\CostControl\UsageTracker::get_stats();
$today_stats = \WPMind\Modules\CostControl\UsageTracker::get_today_stats();
$week_stats  = \WPMind\Modules\CostControl\UsageTracker::get_week_stats();
$month_stats = \WPMind\Modules\CostControl\UsageTracker::get_month_stats();
$last_updated = $usage_stats['last_updated'] ?? 0;
$has_usage_data = ( $usage_stats['total']['requests'] ?? 0 ) > 0;
?>

<!-- Token 用量统计面板 -->
<div class="wpmind-usage-panel">
	<h2 class="title">
		<?php esc_html_e( 'Token 用量统计', 'wpmind' ); ?>
		<?php if ( $last_updated > 0 ) : ?>
		<span class="wpmind-last-updated" title="<?php esc_attr_e( '上次更新时间', 'wpmind' ); ?>">
			<?php
			printf(
				/* translators: %s: relative time */
				esc_html__( '更新于 %s', 'wpmind' ),
				esc_html( human_time_diff( $last_updated, time() ) . __( '前', 'wpmind' ) )
			);
			?>
		</span>
		<?php endif; ?>
		<button type="button" class="button button-small wpmind-refresh-usage" title="<?php esc_attr_e( '刷新统计', 'wpmind' ); ?>" aria-label="<?php esc_attr_e( '刷新用量统计', 'wpmind' ); ?>">
			<span class="dashicons ri-refresh-line"></span>
		</button>
		<button type="button" class="button button-small wpmind-clear-usage" title="<?php esc_attr_e( '清除统计', 'wpmind' ); ?>" aria-label="<?php esc_attr_e( '清除所有用量统计数据', 'wpmind' ); ?>">
			<span class="dashicons ri-delete-bin-line"></span>
			<?php esc_html_e( '清除', 'wpmind' ); ?>
		</button>
	</h2>

	<?php if ( ! $has_usage_data ) : ?>
	<!-- 空状态提示 -->
	<div class="wpmind-usage-empty">
		<span class="dashicons ri-bar-chart-box-line"></span>
		<p><?php esc_html_e( '暂无用量数据', 'wpmind' ); ?></p>
		<p class="description"><?php esc_html_e( '当 AI 服务被调用时，用量统计将自动记录在这里。', 'wpmind' ); ?></p>
	</div>
	<?php else : ?>

	<p class="wpmind-usage-note">
		<?php esc_html_e( '费用为估算值，按各服务商官方定价计算（每百万 tokens）。实际费用以服务商账单为准。', 'wpmind' ); ?>
	</p>

	<div class="wpmind-usage-cards">
		<div class="wpmind-usage-card">
			<div class="wpmind-usage-card-header">
				<span class="dashicons ri-calendar-line"></span>
				<?php esc_html_e( '今日', 'wpmind' ); ?>
			</div>
			<div class="wpmind-usage-card-body">
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="today-tokens"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_tokens( $today_stats['input_tokens'] + $today_stats['output_tokens'] ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value wpmind-usage-cost" id="today-cost"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_cost_by_currency( $today_stats['cost_usd'] ?? 0, $today_stats['cost_cny'] ?? 0 ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="today-requests"><?php echo esc_html( $today_stats['requests'] ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
				</div>
			</div>
		</div>
		<div class="wpmind-usage-card">
			<div class="wpmind-usage-card-header">
				<span class="dashicons ri-history-line"></span>
				<?php esc_html_e( '本周', 'wpmind' ); ?>
			</div>
			<div class="wpmind-usage-card-body">
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="week-tokens"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_tokens( $week_stats['input_tokens'] + $week_stats['output_tokens'] ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value wpmind-usage-cost" id="week-cost"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_cost_by_currency( $week_stats['cost_usd'] ?? 0, $week_stats['cost_cny'] ?? 0 ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="week-requests"><?php echo esc_html( $week_stats['requests'] ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
				</div>
			</div>
		</div>
		<div class="wpmind-usage-card">
			<div class="wpmind-usage-card-header">
				<span class="dashicons ri-calendar-2-line"></span>
				<?php esc_html_e( '本月', 'wpmind' ); ?>
			</div>
			<div class="wpmind-usage-card-body">
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="month-tokens"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_tokens( $month_stats['input_tokens'] + $month_stats['output_tokens'] ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value wpmind-usage-cost" id="month-cost"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_cost_by_currency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="month-requests"><?php echo esc_html( $month_stats['requests'] ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
				</div>
			</div>
		</div>
		<div class="wpmind-usage-card">
			<div class="wpmind-usage-card-header">
				<span class="dashicons ri-bar-chart-box-line"></span>
				<?php esc_html_e( '总计', 'wpmind' ); ?>
			</div>
			<div class="wpmind-usage-card-body">
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="total-tokens"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_tokens( ($usage_stats['total']['input_tokens'] ?? 0) + ($usage_stats['total']['output_tokens'] ?? 0) ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value wpmind-usage-cost" id="total-cost"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_cost_by_currency( $usage_stats['total']['cost_usd'] ?? 0, $usage_stats['total']['cost_cny'] ?? 0 ) ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
				</div>
				<div class="wpmind-usage-stat">
					<span class="wpmind-usage-value" id="total-requests"><?php echo esc_html( $usage_stats['total']['requests'] ?? 0 ); ?></span>
					<span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- 各渠道用量统计 -->
	<?php if ( ! empty( $usage_stats['providers'] ) ) : ?>
	<h3 class="wpmind-usage-section-title"><?php esc_html_e( '各渠道用量', 'wpmind' ); ?></h3>
	<div class="wpmind-provider-usage-grid">
		<?php foreach ( $usage_stats['providers'] as $provider_id => $provider_stats ) :
			$currency = \WPMind\Modules\CostControl\UsageTracker::get_currency( $provider_id );
			$display_name = \WPMind\Modules\CostControl\UsageTracker::get_provider_display_name( $provider_id );
			$icon_class = \WPMind\Modules\CostControl\UsageTracker::get_provider_icon( $provider_id );
			$icon_color = \WPMind\Modules\CostControl\UsageTracker::get_provider_color( $provider_id );
		?>
		<div class="wpmind-provider-usage-item">
			<div class="wpmind-provider-usage-header">
				<i class="<?php echo esc_attr( $icon_class ); ?> wpmind-provider-usage-icon" style="color: <?php echo esc_attr( $icon_color ); ?>;"></i>
				<span class="wpmind-provider-usage-name"><?php echo esc_html( $display_name ); ?></span>
				<span class="wpmind-provider-usage-currency"><?php echo esc_html( $currency ); ?></span>
			</div>
			<div class="wpmind-provider-usage-body">
				<div class="wpmind-provider-usage-row">
					<span class="wpmind-provider-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
					<span class="wpmind-provider-usage-value"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_tokens( $provider_stats['total_input_tokens'] + $provider_stats['total_output_tokens'] ) ); ?></span>
				</div>
				<div class="wpmind-provider-usage-row">
					<span class="wpmind-provider-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
					<span class="wpmind-provider-usage-value"><?php echo esc_html( \WPMind\Modules\CostControl\UsageTracker::format_cost( $provider_stats['total_cost'], $currency ) ); ?></span>
				</div>
				<div class="wpmind-provider-usage-row">
					<span class="wpmind-provider-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
					<span class="wpmind-provider-usage-value"><?php echo esc_html( $provider_stats['request_count'] ); ?></span>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php endif; // end has_usage_data ?>
</div>

<!-- 分析仪表板 -->
<?php if ( $has_usage_data ) : ?>
<div class="wpmind-analytics-panel">
	<h2 class="title">
		<span class="dashicons ri-line-chart-line"></span>
		<?php esc_html_e( '分析仪表板', 'wpmind' ); ?>
		<select id="wpmind-analytics-range" class="wpmind-analytics-range-select">
			<option value="7d"><?php esc_html_e( '最近 7 天', 'wpmind' ); ?></option>
			<option value="30d"><?php esc_html_e( '最近 30 天', 'wpmind' ); ?></option>
		</select>
		<button type="button" class="button button-small wpmind-refresh-analytics" title="<?php esc_attr_e( '刷新图表', 'wpmind' ); ?>">
			<span class="dashicons ri-refresh-line"></span>
		</button>
	</h2>

	<div class="wpmind-analytics-content">
		<!-- 用量趋势图 -->
		<div class="wpmind-chart-container">
			<h3><?php esc_html_e( '用量趋势', 'wpmind' ); ?></h3>
			<div class="wpmind-chart-wrapper">
				<canvas id="wpmind-usage-trend-chart"></canvas>
			</div>
		</div>

		<!-- 服务商对比图 -->
		<div class="wpmind-chart-container">
			<h3><?php esc_html_e( '服务商对比', 'wpmind' ); ?></h3>
			<div class="wpmind-chart-wrapper">
				<canvas id="wpmind-provider-chart"></canvas>
			</div>
		</div>

		<!-- 成本分析图 -->
		<div class="wpmind-chart-container">
			<h3><?php esc_html_e( '成本趋势', 'wpmind' ); ?></h3>
			<div class="wpmind-chart-wrapper">
				<canvas id="wpmind-cost-chart"></canvas>
			</div>
		</div>

		<!-- 模型使用分布 -->
		<div class="wpmind-chart-container">
			<h3><?php esc_html_e( '模型使用排行', 'wpmind' ); ?></h3>
			<div class="wpmind-chart-wrapper">
				<canvas id="wpmind-model-chart"></canvas>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- Provider 状态面板 -->
<?php if ( ! empty( $provider_status ) ) : ?>
<div class="wpmind-status-panel">
	<h2 class="title">
		<?php esc_html_e( '服务状态', 'wpmind' ); ?>
		<button type="button" class="button button-small wpmind-refresh-status" title="<?php esc_attr_e( '刷新状态', 'wpmind' ); ?>">
			<span class="dashicons ri-refresh-line"></span>
		</button>
		<button type="button" class="button button-small wpmind-reset-all-breakers" title="<?php esc_attr_e( '重置所有熔断器', 'wpmind' ); ?>">
			<span class="dashicons ri-restart-line"></span>
			<?php esc_html_e( '重置', 'wpmind' ); ?>
		</button>
	</h2>
	<div class="wpmind-status-grid" id="wpmind-status-grid">
		<?php foreach ( $provider_status as $provider_id => $status ) : ?>
		<div class="wpmind-status-item" data-provider="<?php echo esc_attr( $provider_id ); ?>">
			<span class="wpmind-status-indicator wpmind-status-<?php echo esc_attr( $status['state'] ); ?>"></span>
			<span class="wpmind-status-name"><?php echo esc_html( $status['display_name'] ); ?></span>
			<span class="wpmind-status-label"><?php echo esc_html( $status['state_label'] ); ?></span>
			<span class="wpmind-status-score" title="<?php esc_attr_e( '健康分数', 'wpmind' ); ?>">
				<?php echo esc_html( $status['health_score'] ); ?>%
			</span>
			<?php if ( $status['state'] === 'open' && $status['recovery_in'] ) : ?>
			<span class="wpmind-status-recovery">
				<?php printf( esc_html__( '%d分钟后恢复', 'wpmind' ), ceil( $status['recovery_in'] / 60 ) ); ?>
			</span>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
