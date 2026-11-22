<?php
/**
 * 分析内容 partial：基础用量 +（模块启用时）URL subnav 趋势图表/预算管理；（模块未启用时）empty_state 一键开启。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;
use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\UsageTracker;

( static function (): void {
	$analytics_enabled = class_exists( ModuleLoader::class )
	? ModuleLoader::instance()->is_module_enabled( 'analytics' )
	: true;

	$usage_stats  = class_exists( UsageTracker::class ) ? UsageTracker::get_stats() : [];
	$today_stats  = class_exists( UsageTracker::class ) ? UsageTracker::get_today_stats() : [];
	$week_stats   = class_exists( UsageTracker::class ) ? UsageTracker::get_week_stats() : [];
	$month_stats  = class_exists( UsageTracker::class ) ? UsageTracker::get_month_stats() : [];
	$last_updated = (int) ( $usage_stats['last_updated'] ?? 0 );

	$budget_settings = class_exists( BudgetManager::class ) ? BudgetManager::instance()->get_settings() : [];
	$budget_enabled  = ! empty( $budget_settings['enabled'] );
	$budget_global   = is_array( $budget_settings['global'] ?? null ) ? $budget_settings['global'] : [];
	$budget_notify   = is_array( $budget_settings['notifications'] ?? null ) ? $budget_settings['notifications'] : [];

	$wpmind_mark = static function ( string $pid ): string {
		$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
		if ( file_exists( $svg ) ) {
			return '<img class="wpmind-logo" src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg' ) . '" alt="" width="14" height="14">';
		}
		return AdminIcons::svg( 'wenpai_cloud' === $pid ? 'cloud' : 'robot', 'wpmind-logo' );
	};

	$active_providers = 0;
	if ( ! empty( $usage_stats['providers'] ) && is_array( $usage_stats['providers'] ) ) {
		foreach ( $usage_stats['providers'] as $provider_row ) {
			if ( (int) ( $provider_row['request_count'] ?? 0 ) > 0 ) {
				++$active_providers;
			}
		}
	}

	$month_limit_usd = (float) ( $budget_global['monthly_limit_usd'] ?? 0 );
	$month_limit_cny = (float) ( $budget_global['monthly_limit_cny'] ?? 0 );
	$month_used_usd  = (float) ( $month_stats['cost_usd'] ?? 0 );
	$month_used_cny  = (float) ( $month_stats['cost_cny'] ?? 0 );
	if ( $month_limit_usd > 0 ) {
		$budget_note = sprintf(
		/* translators: 1: limit, 2: percent */
			__( '预算 $%1$s · %2$d%%', 'wpmind' ),
			rtrim( rtrim( number_format( $month_limit_usd, 2 ), '0' ), '.' ),
			min( 100, (int) round( $month_used_usd / $month_limit_usd * 100 ) )
		);
	} elseif ( $month_limit_cny > 0 ) {
		$budget_note = sprintf(
		/* translators: 1: limit, 2: percent */
			__( '预算 ¥%1$s · %2$d%%', 'wpmind' ),
			rtrim( rtrim( number_format( $month_limit_cny, 2 ), '0' ), '.' ),
			min( 100, (int) round( $month_used_cny / $month_limit_cny * 100 ) )
		);
	} else {
		$budget_note = sprintf(
		/* translators: %d: monthly request count */
			__( '%d 次请求', 'wpmind' ),
			(int) ( $month_stats['requests'] ?? 0 )
		);
	}

	$current_view = AdminPage::current_view();
	$base_query   = 'module' === $current_view
	? [
		'tab'    => 'module',
		'module' => 'analytics',
	]
	: [ 'tab' => 'analytics' ];

	$sections = [
		'charts' => __( '趋势图表', 'wpmind' ),
		'budget' => __( '预算管理', 'wpmind' ),
	];
	$section  = Wenpai_Admin_UI::section( $sections, 'charts' );

	$usage_head_actions = '<span class="wenpai-actions">';
	if ( $last_updated > 0 ) {
		$usage_head_actions .= '<span class="meta">' . sprintf(
		/* translators: %s: relative time */
			esc_html__( '更新于 %s前', 'wpmind' ),
			esc_html( human_time_diff( $last_updated, time() ) )
		) . '</span>';
	}
	$usage_head_actions .= Wenpai_Admin_UI::button_html(
		[
			'label'   => __( '刷新统计', 'wpmind' ),
			'variant' => 'ghost',
			'size'    => 'sm',
			'class'   => 'wpmind-refresh-usage',
		]
	);
	$usage_head_actions .= '<button type="button" class="btn btn-sm btn-ghost wpmind-clear-usage"'
	. wpmind_confirm_attrs(
		__( '确定要清除全部用量统计记录？此操作不可恢复。', 'wpmind' ),
		[
			'title'    => __( '清除用量统计', 'wpmind' ),
			'danger'   => true,
			'ok_label' => __( '确认清除', 'wpmind' ),
		]
	)
	. '>' . esc_html__( '清除统计', 'wpmind' ) . '</button>';
	$usage_head_actions .= '</span>';
	?>

<div class="wenpai-stack">
	<?php
	// ---- 基础用量概览条（始终可见） ----
	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label' => __( '今日 Tokens', 'wpmind' ),
					'value' => UsageTracker::format_tokens( (int) ( $today_stats['input_tokens'] ?? 0 ) + (int) ( $today_stats['output_tokens'] ?? 0 ) ),
					'unit'  => sprintf( '%d %s', (int) ( $today_stats['requests'] ?? 0 ), __( '次', 'wpmind' ) ),
				],
				[
					'label' => __( '本周费用', 'wpmind' ),
					'value' => UsageTracker::format_cost_by_currency( $week_stats['cost_usd'] ?? 0, $week_stats['cost_cny'] ?? 0 ),
					'unit'  => sprintf( '%d %s', (int) ( $week_stats['requests'] ?? 0 ), __( '次', 'wpmind' ) ),
				],
				[
					'label' => __( '本月费用', 'wpmind' ),
					'value' => UsageTracker::format_cost_by_currency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ),
					'unit'  => $budget_note,
				],
				[
					'label' => __( '活跃服务商', 'wpmind' ),
					'value' => (string) $active_providers,
					'unit'  => sprintf( /* translators: %d: total requests */ __( '累计 %d 次', 'wpmind' ), (int) ( $usage_stats['total']['requests'] ?? 0 ) ),
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 基础用量周期表 ----
	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '用量统计', 'wpmind' ),
			'tile'              => false,
			'head_control_html' => $usage_head_actions,
		]
	);
	?>
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
					<td class="num" id="today-tokens"><?php echo esc_html( UsageTracker::format_tokens( (int) ( $today_stats['input_tokens'] ?? 0 ) + (int) ( $today_stats['output_tokens'] ?? 0 ) ) ); ?></td>
					<td class="num" id="today-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $today_stats['cost_usd'] ?? 0, $today_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="today-requests"><?php echo esc_html( (string) ( $today_stats['requests'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '本周', 'wpmind' ); ?></div></td>
					<td class="num" id="week-tokens"><?php echo esc_html( UsageTracker::format_tokens( (int) ( $week_stats['input_tokens'] ?? 0 ) + (int) ( $week_stats['output_tokens'] ?? 0 ) ) ); ?></td>
					<td class="num" id="week-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $week_stats['cost_usd'] ?? 0, $week_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="week-requests"><?php echo esc_html( (string) ( $week_stats['requests'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '本月', 'wpmind' ); ?></div></td>
					<td class="num" id="month-tokens"><?php echo esc_html( UsageTracker::format_tokens( (int) ( $month_stats['input_tokens'] ?? 0 ) + (int) ( $month_stats['output_tokens'] ?? 0 ) ) ); ?></td>
					<td class="num" id="month-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="month-requests"><?php echo esc_html( (string) ( $month_stats['requests'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<td><div class="t"><?php esc_html_e( '总计', 'wpmind' ); ?></div></td>
					<td class="num" id="total-tokens"><?php echo esc_html( UsageTracker::format_tokens( (int) ( $usage_stats['total']['input_tokens'] ?? 0 ) + (int) ( $usage_stats['total']['output_tokens'] ?? 0 ) ) ); ?></td>
					<td class="num" id="total-cost"><?php echo esc_html( UsageTracker::format_cost_by_currency( $usage_stats['total']['cost_usd'] ?? 0, $usage_stats['total']['cost_cny'] ?? 0 ) ); ?></td>
					<td class="num" id="total-requests"><?php echo esc_html( (string) ( $usage_stats['total']['requests'] ?? 0 ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
	if ( (int) ( $usage_stats['total']['requests'] ?? 0 ) <= 0 ) {
		Wenpai_Admin_UI::card_foot(
			[
				'start' => true,
				'meta'  => __( 'AI 服务产生调用后，统计会自动汇总到这里。', 'wpmind' ),
			]
		);
	}
	Wenpai_Admin_UI::card_close();

	if ( ! $analytics_enabled ) :
		Wenpai_Admin_UI::card_open();
		Wenpai_Admin_UI::empty_state(
			[
				'icon'   => 'equalizer',
				'text'   => __( '数据分析模块未开启', 'wpmind' ),
				'meta'   => __( '开启后可查看 7/30 日趋势图、模型分布与多维费用分析。', 'wpmind' ),
				'action' => [
					'label'   => __( '一键开启分析模块', 'wpmind' ),
					'variant' => 'primary',
					'class'   => 'wpmind-enable-module-btn',
					'attrs'   => [ 'data-module-id' => 'analytics' ],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
	else :
		$subnav_items = [];
		foreach ( $sections as $sec_id => $sec_label ) {
			$subnav_items[] = [
				'id'      => $sec_id,
				'label'   => $sec_label,
				'url'     => AdminPage::url( array_merge( $base_query, [ 'section' => $sec_id ] ) ),
				'current' => $section === $sec_id,
			];
		}
		Wenpai_Admin_UI::subnav(
			[
				'items'   => $subnav_items,
				'current' => $section,
				'label'   => __( '分析视图切换', 'wpmind' ),
			]
		);
		?>

		<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'charts', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="wenpai-row">
				<select id="wpmind-analytics-range" class="wpmind-num" aria-label="<?php esc_attr_e( '图表范围', 'wpmind' ); ?>">
					<option value="7d"><?php esc_html_e( '最近 7 天', 'wpmind' ); ?></option>
					<option value="30d"><?php esc_html_e( '最近 30 天', 'wpmind' ); ?></option>
				</select>
				<?php
				Wenpai_Admin_UI::button(
					[
						'label'   => __( '刷新图表', 'wpmind' ),
						'variant' => 'ghost',
						'size'    => 'sm',
						'class'   => 'wpmind-refresh-analytics',
					]
				);
				?>
				<span class="meta"><?php esc_html_e( '按日汇总展示', 'wpmind' ); ?></span>
			</div>

			<div class="wenpai-grid-2">
				<?php
				$chart_cards = [
					[
						'id'    => 'wpmind-usage-trend-chart',
						'title' => __( '用量趋势', 'wpmind' ),
						'sub'   => __( '按日聚合的请求与 Token', 'wpmind' ),
					],
					[
						'id'    => 'wpmind-provider-chart',
						'title' => __( '服务商对比', 'wpmind' ),
						'sub'   => __( '各服务商调用占比', 'wpmind' ),
					],
					[
						'id'    => 'wpmind-cost-chart',
						'title' => __( '成本趋势', 'wpmind' ),
						'sub'   => __( '按日估算费用走势', 'wpmind' ),
					],
					[
						'id'    => 'wpmind-model-chart',
						'title' => __( '模型使用排行', 'wpmind' ),
						'sub'   => __( '调用量最高的模型', 'wpmind' ),
					],
				];
				foreach ( $chart_cards as $cc ) {
					Wenpai_Admin_UI::card_open(
						[
							'title' => $cc['title'],
							'sub'   => $cc['sub'],
							'tile'  => false,
							'class' => 'wpmind-chart',
						]
					);
					Wenpai_Admin_UI::chart_frame(
						[
							'plot_html'  => '<canvas id="' . esc_attr( $cc['id'] ) . '"></canvas>',
							'aria_label' => $cc['title'],
						]
					);
					Wenpai_Admin_UI::card_close();
				}
				?>
			</div>

			<?php if ( ! empty( $usage_stats['providers'] ) && is_array( $usage_stats['providers'] ) ) : ?>
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( '各渠道用量', 'wpmind' ),
						'sub'   => __( '按各服务商结算币种汇总', 'wpmind' ),
						'tile'  => false,
					]
				);
				?>
				<div class="wenpai-table-wrap">
					<table class="tbl">
						<thead>
							<tr>
								<th><?php esc_html_e( '服务商', 'wpmind' ); ?></th>
								<th class="num"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></th>
								<th class="num"><?php esc_html_e( '费用', 'wpmind' ); ?></th>
								<th class="num"><?php esc_html_e( '请求', 'wpmind' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $usage_stats['providers'] as $provider_id => $provider_stats ) :
								$provider_id   = (string) $provider_id;
								$is_cloud_row  = 'wenpai_cloud' === $provider_id;
								$row_currency  = $is_cloud_row ? __( '心思点数', 'wpmind' ) : UsageTracker::get_currency( $provider_id );
								$row_credits   = (int) ( $provider_stats['total_credits'] ?? 0 );
								$row_cost_text = $is_cloud_row
									? ( $row_credits > 0
										? sprintf(
											/* translators: %s: Mind Credits consumed */
											__( '%s 点数', 'wpmind' ),
											number_format_i18n( $row_credits )
										)
										: __( '点数计费', 'wpmind' ) )
									: UsageTracker::format_cost( (float) ( $provider_stats['total_cost'] ?? 0 ), $row_currency );
								?>
								<tr>
									<td>
										<div class="t"><?php echo $wpmind_mark( $provider_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( UsageTracker::get_provider_display_name( $provider_id ) ); ?></div>
										<div class="d"><?php echo esc_html( $row_currency ); ?></div>
									</td>
									<td class="num"><?php echo esc_html( UsageTracker::format_tokens( (int) ( $provider_stats['total_input_tokens'] ?? 0 ) + (int) ( $provider_stats['total_output_tokens'] ?? 0 ) ) ); ?></td>
									<td class="num"><?php echo esc_html( $row_cost_text ); ?></td>
									<td class="num"><?php echo esc_html( (string) ( $provider_stats['request_count'] ?? 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php Wenpai_Admin_UI::card_close(); ?>
			<?php endif; ?>
		</div>

		<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'budget', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			Wenpai_Admin_UI::card_open(
				[
					'title'             => __( '预算管理', 'wpmind' ),
					'sub'               => __( '设置每日与每月费用限额，0 表示不限制。', 'wpmind' ),
					'tile'              => false,
					'head_actions_html' => $budget_enabled
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
							'checked' => $budget_enabled,
							'on'      => __( '已开启', 'wpmind' ),
							'off'     => __( '未开启', 'wpmind' ),
						]
					);
					echo $wpmind_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<p class="wenpai-field__desc"><?php esc_html_e( '超限时触发告警或执行降级拦截策略。', 'wpmind' ); ?></p>
				</div>
			</div>

			<div id="wpmind-budget-settings"<?php echo $budget_enabled ? '' : ' hidden'; ?>>
				<div class="wenpai-field wenpai-field--row field">
					<div class="field-label"><label class="wenpai-field__label" for="budget_daily_usd"><?php esc_html_e( '每日限额', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="field-ctl-row">
							<input type="number" class="wpmind-num" id="budget_daily_usd" name="daily_limit_usd" value="<?php echo esc_attr( (string) ( $budget_global['daily_limit_usd'] ?? 0 ) ); ?>" min="0" step="0.01" aria-label="<?php esc_attr_e( '每日 USD', 'wpmind' ); ?>">
							<span class="meta">USD</span>
							<input type="number" class="wpmind-num" id="budget_daily_cny" name="daily_limit_cny" value="<?php echo esc_attr( (string) ( $budget_global['daily_limit_cny'] ?? 0 ) ); ?>" min="0" step="0.01" aria-label="<?php esc_attr_e( '每日 CNY', 'wpmind' ); ?>">
							<span class="meta">CNY</span>
						</div>
					</div>
				</div>

				<div class="wenpai-field wenpai-field--row field">
					<div class="field-label"><label class="wenpai-field__label" for="budget_monthly_usd"><?php esc_html_e( '每月限额', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="field-ctl-row">
							<input type="number" class="wpmind-num" id="budget_monthly_usd" name="monthly_limit_usd" value="<?php echo esc_attr( (string) ( $budget_global['monthly_limit_usd'] ?? 0 ) ); ?>" min="0" step="0.01" aria-label="<?php esc_attr_e( '每月 USD', 'wpmind' ); ?>">
							<span class="meta">USD</span>
							<input type="number" class="wpmind-num" id="budget_monthly_cny" name="monthly_limit_cny" value="<?php echo esc_attr( (string) ( $budget_global['monthly_limit_cny'] ?? 0 ) ); ?>" min="0" step="0.01" aria-label="<?php esc_attr_e( '每月 CNY', 'wpmind' ); ?>">
							<span class="meta">CNY</span>
						</div>
					</div>
				</div>

				<div class="wenpai-field wenpai-field--row field">
					<div class="field-label"><label class="wenpai-field__label" for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="field-ctl-row">
							<input type="number" class="wpmind-num" id="budget_alert_threshold" name="alert_threshold" value="<?php echo esc_attr( (string) ( $budget_global['alert_threshold'] ?? 80 ) ); ?>" min="1" max="100" step="1">
							<span class="meta">%</span>
						</div>
					</div>
				</div>

				<div class="wenpai-field wenpai-field--row field">
					<div class="field-label"><label class="wenpai-field__label" for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<select id="budget_enforcement_mode" name="enforcement_mode">
							<?php
							$mode_options = class_exists( BudgetManager::class ) ? BudgetManager::get_mode_options() : [ 'alert' => __( '仅告警', 'wpmind' ) ];
							foreach ( $mode_options as $b_mode => $b_label ) :
								?>
								<option value="<?php echo esc_attr( (string) $b_mode ); ?>" <?php selected( $budget_settings['enforcement_mode'] ?? 'alert', $b_mode ); ?>>
									<?php echo esc_html( (string) $b_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="wenpai-field wenpai-field--row field">
					<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '通知方式', 'wpmind' ); ?></span></div>
					<div class="field-ctl">
						<div class="opts">
							<label><input type="checkbox" name="admin_notice" value="1" <?php checked( ! empty( $budget_notify['admin_notice'] ) ); ?>> <?php esc_html_e( '在心思后台页面提示告警', 'wpmind' ); ?></label>
							<label><input type="checkbox" name="email_alert" value="1" <?php checked( ! empty( $budget_notify['email_alert'] ) ); ?>> <?php esc_html_e( '发送告警邮件', 'wpmind' ); ?></label>
						</div>
					</div>
				</div>

				<div class="wpmind-budget-email-field"<?php echo ! empty( $budget_notify['email_alert'] ) ? '' : ' hidden'; ?>>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="budget_email_address"><?php esc_html_e( '告警邮箱', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="email" id="budget_email_address" name="email_address" value="<?php echo esc_attr( (string) ( $budget_notify['email_address'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>">
						</div>
					</div>
				</div>
			</div>
			<?php
			Wenpai_Admin_UI::card_foot(
				[
					'actions' => [
						[
							'label'   => __( '保存预算设置', 'wpmind' ),
							'variant' => 'primary',
							'attrs'   => [ 'id' => 'wpmind-save-budget' ],
						],
					],
				]
			);
			Wenpai_Admin_UI::card_close();
			?>
		</div>
	<?php endif; ?>
</div>
	<?php
} )();
