<?php
/**
 * Cost Control 模块设置（kit 化）。
 * 保留 JS 契约（admin-budget.js）：#wpmind_budget_enabled /
 * #wpmind-budget-settings / input[name=admin_notice|email_alert|email_address] /
 * #budget_daily_usd / #budget_monthly_usd / #budget_daily_cny /
 * #budget_monthly_cny / #budget_alert_threshold / #budget_enforcement_mode /
 * .wpmind-budget-email-field / #wpmind-save-cost-control / #wpmind-clear-usage-stats
 * + 兄弟节点 .spinner。
 *
 * @package WPMind\Modules\CostControl
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;
use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\BudgetChecker;
use WPMind\Modules\CostControl\UsageTracker;

if ( ! class_exists( BudgetManager::class ) || ! class_exists( BudgetChecker::class ) || ! class_exists( UsageTracker::class ) ) {
	wpmind_notice( [ 'text' => __( '费用控制模块未加载，请到「模块」页重新启用。', 'wpmind' ), 'level' => 'bad' ] );
	return;
}

$budget_manager  = BudgetManager::instance();
$budget_settings = $budget_manager->get_settings();
$budget_checker  = BudgetChecker::instance();
$budget_summary  = $budget_checker->get_summary();

$today_stats = UsageTracker::get_today_stats();
$month_stats = UsageTracker::get_month_stats();
$total_stats = UsageTracker::get_stats();

$get_value = static function ( $array, $key, $default = 0 ) {
	return $array[ $key ] ?? $default;
};

$wpmind_enabled  = (bool) $get_value( $budget_settings, 'enabled', false );
$wpmind_global   = (array) $get_value( $budget_settings, 'global', [] );
$wpmind_notices  = (array) $get_value( $budget_settings, 'notifications', [] );
$wpmind_providers = (array) $get_value( $total_stats, 'providers', [] );

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

<section class="card">
	<div class="wpmind-statbar">
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'trend-up', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '今日费用', 'wpmind' ); ?></div>
			<div class="v"><?php echo esc_html( UsageTracker::format_cost_by_currency( $get_value( $today_stats, 'cost_usd', 0 ), $get_value( $today_stats, 'cost_cny', 0 ) ) ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'dollar', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '本月费用', 'wpmind' ); ?></div>
			<div class="v"><?php echo esc_html( UsageTracker::format_cost_by_currency( $get_value( $month_stats, 'cost_usd', 0 ), $get_value( $month_stats, 'cost_cny', 0 ) ) ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'route', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '今日请求', 'wpmind' ); ?></div>
			<div class="v"><?php echo esc_html( number_format( $get_value( $today_stats, 'requests', 0 ) ) ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'pulse', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '今日 Tokens', 'wpmind' ); ?></div>
			<div class="v"><?php echo esc_html( UsageTracker::format_tokens( $get_value( $today_stats, 'input_tokens', 0 ) + $get_value( $today_stats, 'output_tokens', 0 ) ) ); ?></div>
		</div>
	</div>
</div>
</section>

<div class="wenpai-grid-2">

	<section class="card">
		<div class="card-head">
			<span class="tile"><?php echo AdminIcons::svg( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
			<div>
				<h2 class="card-title"><?php esc_html_e( '预算限额', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '监控 AI 服务费用，设置预算限额，接收告警通知。', 'wpmind' ); ?></p>
			</div>
			<?php if ( $wpmind_enabled ) : ?>
			<span class="pill ok"><?php esc_html_e( '已启用', 'wpmind' ); ?></span>
			<?php endif; ?>
		</div>

		<?php $wpmind_switch( 'wpmind_budget_enabled', 'budget_enabled', $wpmind_enabled, __( '启用预算限额', 'wpmind' ), __( '启用后，当费用达到设定限额时将触发告警或自动限制服务。', 'wpmind' ) ); ?>

		<div id="wpmind-budget-settings" <?php echo $wpmind_enabled ? '' : 'style="display:none;"'; ?>>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '每日限额', 'wpmind' ); ?></span></div>
				<div class="field-ctl">
					<div class="wenpai-actions">
						<span class="meta">USD</span>
						<input type="number" id="budget_daily_usd" name="daily_limit_usd" value="<?php echo esc_attr( $get_value( $wpmind_global, 'daily_limit_usd', 0 ) ); ?>" min="0" step="0.01" style="max-width:9rem">
						<span class="meta">CNY</span>
						<input type="number" id="budget_daily_cny" name="daily_limit_cny" value="<?php echo esc_attr( $get_value( $wpmind_global, 'daily_limit_cny', 0 ) ); ?>" min="0" step="0.01" style="max-width:9rem">
					</div>
					<p class="wenpai-field__desc"><?php esc_html_e( '设置为 0 表示不限制。', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '每月限额', 'wpmind' ); ?></span></div>
				<div class="field-ctl">
					<div class="wenpai-actions">
						<span class="meta">USD</span>
						<input type="number" id="budget_monthly_usd" name="monthly_limit_usd" value="<?php echo esc_attr( $get_value( $wpmind_global, 'monthly_limit_usd', 0 ) ); ?>" min="0" step="0.01" style="max-width:9rem">
						<span class="meta">CNY</span>
						<input type="number" id="budget_monthly_cny" name="monthly_limit_cny" value="<?php echo esc_attr( $get_value( $wpmind_global, 'monthly_limit_cny', 0 ) ); ?>" min="0" step="0.01" style="max-width:9rem">
					</div>
				</div>
			</div>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<div class="wenpai-actions">
						<input type="number" id="budget_alert_threshold" name="alert_threshold" value="<?php echo esc_attr( $get_value( $wpmind_global, 'alert_threshold', 80 ) ); ?>" min="1" max="100" step="1" style="max-width:6rem">
						<span class="meta">%</span>
					</div>
					<p class="wenpai-field__desc"><?php esc_html_e( '当费用达到限额的此百分比时发送告警。', 'wpmind' ); ?></p>
				</div>
			</div>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><label class="wenpai-field__label" for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<select id="budget_enforcement_mode" name="enforcement_mode" style="max-width:280px">
						<?php foreach ( BudgetManager::get_mode_options() as $mode => $label ) : ?>
						<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $get_value( $budget_settings, 'enforcement_mode', 'alert' ), $mode ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wenpai-field field wenpai-field--row">
				<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '通知方式', 'wpmind' ); ?></span></div>
				<div class="field-ctl">
					<ul class="wenpai-checkgroup__list">
						<li><label class="chk">
							<input type="checkbox" name="admin_notice" value="1" <?php checked( $get_value( $wpmind_notices, 'admin_notice', true ) ); ?>>
							<span class="wenpai-checkgroup__text"><?php esc_html_e( '后台通知 - 在 WordPress 后台显示告警', 'wpmind' ); ?></span>
						</label></li>
						<li><label class="chk">
							<input type="checkbox" name="email_alert" value="1" <?php checked( $get_value( $wpmind_notices, 'email_alert', false ) ); ?>>
							<span class="wenpai-checkgroup__text"><?php esc_html_e( '邮件通知 - 发送告警邮件', 'wpmind' ); ?></span>
						</label></li>
					</ul>
					<div class="wpmind-budget-email-field" style="margin-top:var(--wenpai-space-3);<?php echo $get_value( $wpmind_notices, 'email_alert', false ) ? '' : 'display:none;'; ?>">
						<input type="email" name="email_address" value="<?php echo esc_attr( $get_value( $wpmind_notices, 'email_address', '' ) ); ?>" placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>" style="max-width:22rem">
					</div>
				</div>
			</div>
		</div>

		<div class="card-foot">
			<span class="wenpai-actions">
				<button type="button" class="btn btn-primary" id="wpmind-save-cost-control"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
				<button type="button" class="btn btn-ghost" id="wpmind-clear-usage-stats"><?php esc_html_e( '清除统计', 'wpmind' ); ?></button>
				<span class="spinner"></span>
			</span>
		</div>
	</section>

	<div class="wenpai-stack">
		<section class="card">
			<div class="card-head">
				<span class="tile"><?php echo AdminIcons::svg( 'analytics' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
				<div>
					<h2 class="card-title"><?php esc_html_e( '服务商用量', 'wpmind' ); ?></h2>
				</div>
			</div>
			<?php if ( empty( $wpmind_providers ) ) : ?>
			<p class="meta" style="padding:var(--wenpai-space-4) 0;"><?php esc_html_e( '暂无用量数据。使用 AI 服务后，用量数据将显示在这里。', 'wpmind' ); ?></p>
			<?php else : ?>
			<div class="wenpai-table-wrap">
				<table class="tbl">
					<thead>
						<tr><th><?php esc_html_e( 'Provider', 'wpmind' ); ?></th><th class="num"><?php esc_html_e( '费用', 'wpmind' ); ?></th><th class="num"><?php esc_html_e( '请求', 'wpmind' ); ?></th><th class="num">Tokens</th></tr>
					</thead>
					<tbody>
						<?php
						foreach ( $wpmind_providers as $provider => $data ) :
							$currency      = UsageTracker::get_currency( $provider );
							$total_cost    = $get_value( $data, 'total_cost', 0 );
							$request_count = $get_value( $data, 'request_count', 0 );
							$total_tokens  = $get_value( $data, 'total_input_tokens', 0 ) + $get_value( $data, 'total_output_tokens', 0 );
							$logo          = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $provider . '.svg';
							?>
						<tr>
							<td>
								<div class="t">
									<?php if ( file_exists( $logo ) ) : ?>
									<img class="wpmind-mini-logo" src="<?php echo esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $provider . '.svg' ); ?>" alt="" width="14" height="14">
									<?php endif; ?>
									<?php echo esc_html( UsageTracker::get_provider_display_name( $provider ) ); ?>
								</div>
								<div class="d"><?php echo esc_html( $currency ); ?></div>
							</td>
							<td class="num"><?php echo esc_html( UsageTracker::format_cost( $total_cost, $currency ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format( $request_count ) ); ?></td>
							<td class="num"><?php echo esc_html( UsageTracker::format_tokens( $total_tokens ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head">
				<span class="tile"><?php echo AdminIcons::svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
				<div>
					<h2 class="card-title"><?php esc_html_e( '费用说明', 'wpmind' ); ?></h2>
				</div>
			</div>
			<ul class="meta" style="margin:0;padding-inline-start:var(--wenpai-space-5);line-height:1.9;">
				<li><?php esc_html_e( '自动追踪每次 API 调用的 Token 用量', 'wpmind' ); ?></li>
				<li><?php esc_html_e( '根据各服务商定价计算费用（USD/CNY）', 'wpmind' ); ?></li>
				<li><?php esc_html_e( '设置每日/每月预算限额', 'wpmind' ); ?></li>
				<li><?php esc_html_e( '接近或超出限额时发送告警通知', 'wpmind' ); ?></li>
			</ul>
		</section>
	</div>

</div>
