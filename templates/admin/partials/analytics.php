<?php
/**
 * 分析内容 partial：用量统计 + 图表 + 各渠道用量 + 预算管理。
 * 供 analytics 视图与 analytics 模块设置页共用（模块壳已提供 page_head）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;
use WPMind\Modules\CostControl\UsageTracker;

$usage_stats    = UsageTracker::get_stats();
$today_stats    = UsageTracker::get_today_stats();
$week_stats     = UsageTracker::get_week_stats();
$month_stats    = UsageTracker::get_month_stats();
$last_updated   = $usage_stats['last_updated'] ?? 0;

$budget_manager  = \WPMind\Modules\CostControl\BudgetManager::instance();
$budget_settings = $budget_manager->get_settings();
$budget_enabled  = ! empty( $budget_settings['enabled'] );
$budget_global   = $budget_settings['global'] ?? [];
$budget_notify   = $budget_settings['notifications'] ?? [];

// Provider 本地图标。
$wpmind_mark = static function ( string $pid ): string {
	$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
	if ( file_exists( $svg ) ) {
		return '<img class="wpmind-mini-logo" src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg' ) . '" alt="" width="14" height="14">';
	}
	return AdminIcons::svg( 'robot' );
};
?>

<div class="wenpai-stack">

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '用量统计', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '追踪各 AI 服务的 Token 消耗与费用估算', 'wpmind' ); ?></p>
			</div>
			<span class="wenpai-actions">
				<?php if ( $last_updated > 0 ) : ?>
				<span class="meta"><?php
					printf(
						/* translators: %s: relative time */
						esc_html__( '更新于 %s前', 'wpmind' ),
						esc_html( human_time_diff( $last_updated, time() ) )
					);
				?></span>
				<?php endif; ?>
				<button type="button" class="btn btn-sm btn-ghost wpmind-refresh-usage"><?php echo AdminIcons::svg( 'refresh' ); ?><?php esc_html_e( '刷新', 'wpmind' ); ?></button>
				<button type="button" class="btn btn-sm wpmind-clear-usage"><?php esc_html_e( '清除', 'wpmind' ); ?></button>
			</span>
		</div>
		<div class="wenpai-table-wrap">
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( '周期', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( '费用', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( '请求', 'wpmind' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><div class="t"><?php esc_html_e( '今日', 'wpmind' ); ?></div></td>
					<td class="num" id="today-tokens"><?php echo esc_html( UsageTracker::format_tokens( $today_stats['input_tokens'] + $today_stats['output_tokens'] ) ); ?></td>
					<td class="num" id="today-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $today_stats['cost_usd'] ?? 0, $today_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="today-requests"><?php echo esc_html( (string) $today_stats['requests'] ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '本周', 'wpmind' ); ?></div></td>
					<td class="num" id="week-tokens"><?php echo esc_html( UsageTracker::format_tokens( $week_stats['input_tokens'] + $week_stats['output_tokens'] ) ); ?></td>
					<td class="num" id="week-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $week_stats['cost_usd'] ?? 0, $week_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="week-requests"><?php echo esc_html( (string) $week_stats['requests'] ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '本月', 'wpmind' ); ?></div></td>
					<td class="num" id="month-tokens"><?php echo esc_html( UsageTracker::format_tokens( $month_stats['input_tokens'] + $month_stats['output_tokens'] ) ); ?></td>
					<td class="num" id="month-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="month-requests"><?php echo esc_html( (string) $month_stats['requests'] ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '总计', 'wpmind' ); ?></div></td>
					<td class="num" id="total-tokens"><?php echo esc_html( UsageTracker::format_tokens( ( $usage_stats['total']['input_tokens'] ?? 0 ) + ( $usage_stats['total']['output_tokens'] ?? 0 ) ) ); ?></td>
					<td class="num" id="total-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $usage_stats['total']['cost_usd'] ?? 0, $usage_stats['total']['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="total-requests"><?php echo esc_html( (string) ( $usage_stats['total']['requests'] ?? 0 ) ); ?></td>
				</tr>
			</tbody>
		</table>
		</div>
		<?php if ( ( $usage_stats['total']['requests'] ?? 0 ) <= 0 ) : ?>
		<div class="card-foot"><span class="meta"><?php esc_html_e( '暂无用量数据，AI 服务被调用后自动记录。', 'wpmind' ); ?></span></div>
		<?php endif; ?>
	</section>

	<div class="wpmind-route-bar">
		<span class="wpmind-route-bar-label"><?php esc_html_e( '图表范围', 'wpmind' ); ?></span>
		<select id="wpmind-analytics-range" style="max-width:160px">
			<option value="7d"><?php esc_html_e( '最近 7 天', 'wpmind' ); ?></option>
			<option value="30d"><?php esc_html_e( '最近 30 天', 'wpmind' ); ?></option>
		</select>
		<button type="button" class="btn btn-sm btn-ghost wpmind-refresh-analytics"><?php echo AdminIcons::svg( 'refresh' ); ?><?php esc_html_e( '刷新图表', 'wpmind' ); ?></button>
		<span class="meta"><?php esc_html_e( '数据源：UsageTracker 聚合', 'wpmind' ); ?></span>
	</div>

	<div class="wpmind-chart-grid">
		<section class="card wpmind-chart-container">
			<div class="card-head">
				<div>
					<h2 class="card-title"><?php esc_html_e( '用量趋势', 'wpmind' ); ?></h2>
					<p class="card-sub"><?php esc_html_e( '按日聚合的请求与 Token', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wpmind-chart is-live"><canvas id="wpmind-usage-trend-chart"></canvas></div>
		</section>
		<section class="card wpmind-chart-container">
			<div class="card-head">
				<div>
					<h2 class="card-title"><?php esc_html_e( '服务商对比', 'wpmind' ); ?></h2>
					<p class="card-sub"><?php esc_html_e( 'Tokens 与费用占比', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wpmind-chart is-live"><canvas id="wpmind-provider-chart"></canvas></div>
		</section>
		<section class="card wpmind-chart-container">
			<div class="card-head">
				<div>
					<h2 class="card-title"><?php esc_html_e( '成本趋势', 'wpmind' ); ?></h2>
					<p class="card-sub"><?php esc_html_e( '按日费用走势', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wpmind-chart is-live"><canvas id="wpmind-cost-chart"></canvas></div>
		</section>
		<section class="card wpmind-chart-container">
			<div class="card-head">
				<div>
					<h2 class="card-title"><?php esc_html_e( '模型使用排行', 'wpmind' ); ?></h2>
					<p class="card-sub"><?php esc_html_e( '调用量最高的模型', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wpmind-chart is-live"><canvas id="wpmind-model-chart"></canvas></div>
		</section>
	</div>

	<?php if ( ! empty( $usage_stats['providers'] ) ) : ?>
	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '各渠道用量', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '费用按 Provider 结算币种显示', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="wenpai-table-wrap">
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( '费用', 'wpmind' ); ?></th>
					<th class="num"><?php esc_html_e( '请求', 'wpmind' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $usage_stats['providers'] as $provider_id => $provider_stats ) : ?>
				<tr>
					<td>
						<div class="t"><?php echo $wpmind_mark( $provider_id ); ?><?php echo esc_html( UsageTracker::get_provider_display_name( $provider_id ) ); ?></div>
						<div class="d"><?php echo esc_html( UsageTracker::get_currency( $provider_id ) ); ?></div>
					</td>
					<td class="num"><?php echo esc_html( UsageTracker::format_tokens( $provider_stats['total_input_tokens'] + $provider_stats['total_output_tokens'] ) ); ?></td>
					<td class="num"><?php echo esc_html( UsageTracker::format_cost( $provider_stats['total_cost'], UsageTracker::get_currency( $provider_id ) ) ); ?></td>
					<td class="num"><?php echo esc_html( (string) $provider_stats['request_count'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</section>
	<?php endif; ?>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '预算管理', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '超限告警与费用限额，0 表示不限制', 'wpmind' ); ?></p>
			</div>
			<?php if ( $budget_enabled ) : ?><span class="pill ok"><?php esc_html_e( '已启用', 'wpmind' ); ?></span><?php endif; ?>
		</div>

		<div class="wenpai-field wenpai-field--toggle field wenpai-field--row">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_budget_enabled"><?php esc_html_e( '启用预算限额', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<span class="wenpai-switch">
					<input type="checkbox" role="switch" class="wenpai-switch__input" id="wpmind_budget_enabled" name="budget_enabled" value="1" <?php checked( $budget_enabled ); ?>>
					<span class="wenpai-switch__track" aria-hidden="true"></span>
					<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已开启', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '未开启', 'wpmind' ); ?></span></span>
				</span>
				<p class="wenpai-field__desc"><?php esc_html_e( '超限时触发告警或按「超限处理」策略执行。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div id="wpmind-budget-settings"<?php echo $budget_enabled ? '' : ' style="display:none;"'; ?>>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_daily_usd"><?php esc_html_e( '每日限额', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<div class="field-ctl-row">
						<input type="number" id="budget_daily_usd" name="daily_limit_usd" value="<?php echo esc_attr( $budget_global['daily_limit_usd'] ?? 0 ); ?>" min="0" step="0.01" style="max-width:9rem" aria-label="<?php esc_attr_e( '每日 USD', 'wpmind' ); ?>">
						<span class="meta">USD</span>
						<input type="number" id="budget_daily_cny" name="daily_limit_cny" value="<?php echo esc_attr( $budget_global['daily_limit_cny'] ?? 0 ); ?>" min="0" step="0.01" style="max-width:9rem" aria-label="<?php esc_attr_e( '每日 CNY', 'wpmind' ); ?>">
						<span class="meta">CNY</span>
					</div>
					<p class="wenpai-field__desc"><?php esc_html_e( '按币种分别限制，0 表示不限制。', 'wpmind' ); ?></p>
				</div>
			</div>

			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_monthly_usd"><?php esc_html_e( '每月限额', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<div class="field-ctl-row">
						<input type="number" id="budget_monthly_usd" name="monthly_limit_usd" value="<?php echo esc_attr( $budget_global['monthly_limit_usd'] ?? 0 ); ?>" min="0" step="0.01" style="max-width:9rem" aria-label="<?php esc_attr_e( '每月 USD', 'wpmind' ); ?>">
						<span class="meta">USD</span>
						<input type="number" id="budget_monthly_cny" name="monthly_limit_cny" value="<?php echo esc_attr( $budget_global['monthly_limit_cny'] ?? 0 ); ?>" min="0" step="0.01" style="max-width:9rem" aria-label="<?php esc_attr_e( '每月 CNY', 'wpmind' ); ?>">
						<span class="meta">CNY</span>
					</div>
				</div>
			</div>

			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<div class="field-ctl-row">
						<input type="number" id="budget_alert_threshold" name="alert_threshold" value="<?php echo esc_attr( $budget_global['alert_threshold'] ?? 80 ); ?>" min="1" max="100" step="1" style="max-width:6rem">
						<span class="meta">%</span>
					</div>
					<p class="wenpai-field__desc"><?php esc_html_e( '用量达到限额的该百分比时触发告警。', 'wpmind' ); ?></p>
				</div>
			</div>

			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<select id="budget_enforcement_mode" name="enforcement_mode" style="max-width:420px">
						<?php foreach ( \WPMind\Modules\CostControl\BudgetManager::get_mode_options() as $mode => $label ) : ?>
						<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $budget_settings['enforcement_mode'] ?? 'alert', $mode ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '通知方式', 'wpmind' ); ?></span></div>
				<div class="field-ctl">
					<div class="opts">
						<label><input type="checkbox" name="admin_notice" value="1" <?php checked( $budget_notify['admin_notice'] ?? false ); ?>> <?php esc_html_e( '后台通知 - 在 WordPress 后台显示告警', 'wpmind' ); ?></label>
						<label><input type="checkbox" name="email_alert" value="1" <?php checked( $budget_notify['email_alert'] ?? false ); ?>> <?php esc_html_e( '邮件通知 - 发送告警邮件', 'wpmind' ); ?></label>
					</div>
				</div>
			</div>

			<div class="wpmind-budget-email-field" style="<?php echo ( $budget_notify['email_alert'] ?? false ) ? '' : 'display:none;'; ?>">
				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><label class="wenpai-field__label" for="budget_email_address"><?php esc_html_e( '告警邮箱', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<input type="email" id="budget_email_address" name="email_address" value="<?php echo esc_attr( $budget_notify['email_address'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>" style="max-width:320px">
					</div>
				</div>
			</div>
		</div>

		<div class="card-foot">
			<span class="wenpai-actions">
				<button type="button" class="btn btn-primary" id="wpmind-save-budget"><?php esc_html_e( '保存预算设置', 'wpmind' ); ?></button>
			</span>
		</div>
	</section>

</div>
