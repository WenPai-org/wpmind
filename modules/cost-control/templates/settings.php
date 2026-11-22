<?php
/**
 * Cost Control 模块设置（kit 化）：statbar + field--row + confirm_attrs。
 *
 * @package WPMind\Modules\CostControl
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Modules\CostControl\BudgetChecker;
use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\UsageTracker;

( static function (): void {
	if ( ! class_exists( BudgetManager::class ) || ! class_exists( BudgetChecker::class ) || ! class_exists( UsageTracker::class ) ) {
		wpmind_notice(
			[
				'text'  => __( '费用控制模块未加载，请到「模块」页重新开启。', 'wpmind' ),
				'level' => 'bad',
			]
		);
		return;
	}

	$budget_manager  = BudgetManager::instance();
	$budget_settings = $budget_manager->get_settings();
	$today_stats     = UsageTracker::get_today_stats();
	$month_stats     = UsageTracker::get_month_stats();
	$total_stats     = UsageTracker::get_stats();

	$wpmind_enabled   = ! empty( $budget_settings['enabled'] );
	$wpmind_global    = is_array( $budget_settings['global'] ?? null ) ? $budget_settings['global'] : [];
	$wpmind_notices   = is_array( $budget_settings['notifications'] ?? null ) ? $budget_settings['notifications'] : [];
	$wpmind_providers = is_array( $total_stats['providers'] ?? null ) ? $total_stats['providers'] : [];

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label' => __( '今日费用', 'wpmind' ),
					'value' => UsageTracker::format_cost_by_currency( $today_stats['cost_usd'] ?? 0, $today_stats['cost_cny'] ?? 0 ),
				],
				[
					'label' => __( '本月费用', 'wpmind' ),
					'value' => UsageTracker::format_cost_by_currency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ),
				],
				[
					'label' => __( '今日请求', 'wpmind' ),
					'value' => number_format( (int) ( $today_stats['requests'] ?? 0 ) ),
				],
				[
					'label' => __( '今日 Tokens', 'wpmind' ),
					'value' => UsageTracker::format_tokens( (int) ( $today_stats['input_tokens'] ?? 0 ) + (int) ( $today_stats['output_tokens'] ?? 0 ) ),
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>

<div class="wenpai-grid-2">
	<?php
	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '预算限额', 'wpmind' ),
			'sub'               => __( '设置每日与每月费用上限，0 表示不限制。', 'wpmind' ),
			'tile'              => false,
			'head_actions_html' => $wpmind_enabled
				? Wenpai_Admin_UI::pill_html(
					[
						'text'  => __( '已开启', 'wpmind' ),
						'state' => 'ok',
					]
				)
				: '',
		]
	);
	?>
	<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind_budget_enabled"><?php esc_html_e( '启用预算限额', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<?php
			$wpmind_sw = Wenpai_Admin_Settings::switch_html(
				[
					'id'      => 'wpmind_budget_enabled',
					'name'    => 'budget_enabled',
					'checked' => $wpmind_enabled,
					'on'      => __( '已开启', 'wpmind' ),
					'off'     => __( '未开启', 'wpmind' ),
				]
			);
			echo $wpmind_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<p class="wenpai-field__desc"><?php esc_html_e( '超限时按设定策略告警、降级或拦截。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div id="wpmind-budget-settings"<?php echo $wpmind_enabled ? '' : ' hidden'; ?>>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '每日限额', 'wpmind' ); ?></span></div>
			<div class="field-ctl">
				<div class="field-ctl-row">
					<span class="meta">USD</span>
					<input type="number" class="wpmind-num" id="budget_daily_usd" name="daily_limit_usd" value="<?php echo esc_attr( (string) ( $wpmind_global['daily_limit_usd'] ?? 0 ) ); ?>" min="0" step="0.01">
					<span class="meta">CNY</span>
					<input type="number" class="wpmind-num" id="budget_daily_cny" name="daily_limit_cny" value="<?php echo esc_attr( (string) ( $wpmind_global['daily_limit_cny'] ?? 0 ) ); ?>" min="0" step="0.01">
				</div>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '每月限额', 'wpmind' ); ?></span></div>
			<div class="field-ctl">
				<div class="field-ctl-row">
					<span class="meta">USD</span>
					<input type="number" class="wpmind-num" id="budget_monthly_usd" name="monthly_limit_usd" value="<?php echo esc_attr( (string) ( $wpmind_global['monthly_limit_usd'] ?? 0 ) ); ?>" min="0" step="0.01">
					<span class="meta">CNY</span>
					<input type="number" class="wpmind-num" id="budget_monthly_cny" name="monthly_limit_cny" value="<?php echo esc_attr( (string) ( $wpmind_global['monthly_limit_cny'] ?? 0 ) ); ?>" min="0" step="0.01">
				</div>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<div class="field-ctl-row">
					<input type="number" class="wpmind-num" id="budget_alert_threshold" name="alert_threshold" value="<?php echo esc_attr( (string) ( $wpmind_global['alert_threshold'] ?? 80 ) ); ?>" min="1" max="100" step="1">
					<span class="meta">%</span>
				</div>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<select id="budget_enforcement_mode" name="enforcement_mode">
					<?php foreach ( BudgetManager::get_mode_options() as $mode => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $mode ); ?>" <?php selected( $budget_settings['enforcement_mode'] ?? 'alert', $mode ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '通知方式', 'wpmind' ); ?></span></div>
			<div class="field-ctl">
				<ul class="wenpai-checkgroup__list">
					<li><label class="chk"><input type="checkbox" name="admin_notice" value="1" <?php checked( ! empty( $wpmind_notices['admin_notice'] ) ); ?>><span class="wenpai-checkgroup__text"><?php esc_html_e( '在心思后台页面显示告警', 'wpmind' ); ?></span></label></li>
					<li><label class="chk"><input type="checkbox" name="email_alert" value="1" <?php checked( ! empty( $wpmind_notices['email_alert'] ) ); ?>><span class="wenpai-checkgroup__text"><?php esc_html_e( '发送告警邮件', 'wpmind' ); ?></span></label></li>
				</ul>
				<div class="wpmind-budget-email-field"<?php echo ! empty( $wpmind_notices['email_alert'] ) ? '' : ' hidden'; ?>>
					<input type="email" name="email_address" value="<?php echo esc_attr( (string) ( $wpmind_notices['email_address'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>">
				</div>
			</div>
		</div>
	</div>

	<div class="card-foot">
		<span class="wenpai-actions">
			<button type="button" class="btn btn-primary" id="wpmind-save-cost-control"><?php esc_html_e( '保存预算设置', 'wpmind' ); ?></button>
			<button type="button" class="btn btn-ghost" id="wpmind-clear-usage-stats"
			<?php
			$clear_confirm = wpmind_confirm_attrs(
				__( '确定要清除所有用量统计记录？此操作不可恢复。', 'wpmind' ),
				[
					'title'    => __( '清除统计', 'wpmind' ),
					'danger'   => true,
					'ok_label' => __( '确认清除', 'wpmind' ),
				]
			);
			echo $clear_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			><?php esc_html_e( '清除统计', 'wpmind' ); ?></button>
			<span class="spinner"></span>
		</span>
	</div>
	<?php Wenpai_Admin_UI::card_close(); ?>

	<div class="wenpai-stack">
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '服务商用量', 'wpmind' ),
				'tile'  => false,
			]
		);
		if ( empty( $wpmind_providers ) ) {
			Wenpai_Admin_UI::empty_state(
				[
					'text' => __( '还没有服务商用量记录', 'wpmind' ),
					'meta' => __( '产生 AI 调用后，各渠道用量会汇总到这里。', 'wpmind' ),
				]
			);
		} else {
			?>
			<div class="wenpai-table-wrap">
				<table class="tbl">
					<thead>
						<tr>
							<th><?php esc_html_e( '服务商', 'wpmind' ); ?></th>
							<th class="num"><?php esc_html_e( '费用', 'wpmind' ); ?></th>
							<th class="num"><?php esc_html_e( '请求', 'wpmind' ); ?></th>
							<th class="num">Tokens</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $wpmind_providers as $provider => $data ) :
							$provider      = (string) $provider;
							$currency      = UsageTracker::get_currency( $provider );
							$total_cost    = (float) ( $data['total_cost'] ?? 0 );
							$request_count = (int) ( $data['request_count'] ?? 0 );
							$total_tokens  = (int) ( $data['total_input_tokens'] ?? 0 ) + (int) ( $data['total_output_tokens'] ?? 0 );
							$logo          = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $provider . '.svg';
							?>
							<tr>
								<td>
									<div class="t">
										<?php if ( file_exists( $logo ) ) : ?>
											<img class="wpmind-logo" src="<?php echo esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $provider . '.svg' ); ?>" alt="" width="14" height="14">
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
			<?php
		}
		Wenpai_Admin_UI::card_close();

		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '费用说明', 'wpmind' ),
				'tile'  => false,
			]
		);
		Wenpai_Admin_UI::status_list(
			[
				'items' => [
					[
						'label' => __( '计量方式', 'wpmind' ),
						'value' => __( '按每次调用 Token 实耗统计', 'wpmind' ),
					],
					[
						'label' => __( '定价来源', 'wpmind' ),
						'value' => __( '服务商官方模型目录价目（USD/CNY）', 'wpmind' ),
					],
					[
						'label' => __( '超限保护', 'wpmind' ),
						'value' => __( '支持告警、自动降级模型与拦截', 'wpmind' ),
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
	?>
	</div>
</div>
	<?php
} )();
