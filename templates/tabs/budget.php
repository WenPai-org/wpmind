<?php
/**
 * WPMind 预算管理 Tab
 *
 * 包含：预算限额 + 告警设置
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取预算设置
$budget_manager  = \WPMind\Modules\CostControl\BudgetManager::instance();
$budget_settings = $budget_manager->get_settings();
$budget_checker  = \WPMind\Modules\CostControl\BudgetChecker::instance();
$budget_summary  = $budget_checker->get_summary();
?>

<div class="wpmind-budget-panel">
	<div class="wpmind-budget-header">
		<h2 class="wpmind-budget-title">
			<span class="dashicons ri-money-cny-circle-line"></span>
			<?php esc_html_e( '预算管理', 'wpmind' ); ?>
		</h2>
		<?php if ( $budget_settings['enabled'] ) : ?>
		<span class="wpmind-budget-status-badge wpmind-budget-enabled"><?php esc_html_e( '已启用', 'wpmind' ); ?></span>
		<?php endif; ?>
	</div>

	<p class="wpmind-budget-desc">
		<?php esc_html_e( '设置 AI 服务的费用限额和告警规则，控制成本支出。', 'wpmind' ); ?>
	</p>

	<div class="wpmind-budget-content">
		<div class="wpmind-budget-toggle-row">
			<label class="wpmind-toggle">
				<input type="checkbox" id="wpmind_budget_enabled" name="budget_enabled" value="1" <?php checked( $budget_settings['enabled'] ); ?>>
				<span class="wpmind-toggle-slider"></span>
				<span class="wpmind-toggle-label"><?php esc_html_e( '启用预算限额', 'wpmind' ); ?></span>
			</label>
			<p class="description"><?php esc_html_e( '启用后，当费用达到设定限额时将触发告警或自动限制服务。', 'wpmind' ); ?></p>
		</div>

		<div class="wpmind-budget-settings" id="wpmind-budget-settings" style="<?php echo $budget_settings['enabled'] ? '' : 'display:none;'; ?>">
			<!-- 全局限额 -->
			<div class="wpmind-budget-section">
				<h3>
					<span class="dashicons ri-wallet-3-line"></span>
					<?php esc_html_e( '全局限额', 'wpmind' ); ?>
				</h3>
				<div class="wpmind-budget-grid">
					<div class="wpmind-budget-field">
						<label for="budget_daily_usd"><?php esc_html_e( '每日 USD', 'wpmind' ); ?></label>
						<div class="wpmind-budget-input-group">
							<span class="wpmind-budget-currency">$</span>
							<input type="number" id="budget_daily_usd" name="daily_limit_usd"
									value="<?php echo esc_attr( $budget_settings['global']['daily_limit_usd'] ?? 0 ); ?>"
									min="0" step="0.01" class="small-text">
						</div>
					</div>
					<div class="wpmind-budget-field">
						<label for="budget_monthly_usd"><?php esc_html_e( '每月 USD', 'wpmind' ); ?></label>
						<div class="wpmind-budget-input-group">
							<span class="wpmind-budget-currency">$</span>
							<input type="number" id="budget_monthly_usd" name="monthly_limit_usd"
									value="<?php echo esc_attr( $budget_settings['global']['monthly_limit_usd'] ?? 0 ); ?>"
									min="0" step="0.01" class="small-text">
						</div>
					</div>
					<div class="wpmind-budget-field">
						<label for="budget_daily_cny"><?php esc_html_e( '每日 CNY', 'wpmind' ); ?></label>
						<div class="wpmind-budget-input-group">
							<span class="wpmind-budget-currency">¥</span>
							<input type="number" id="budget_daily_cny" name="daily_limit_cny"
									value="<?php echo esc_attr( $budget_settings['global']['daily_limit_cny'] ?? 0 ); ?>"
									min="0" step="0.01" class="small-text">
						</div>
					</div>
					<div class="wpmind-budget-field">
						<label for="budget_monthly_cny"><?php esc_html_e( '每月 CNY', 'wpmind' ); ?></label>
						<div class="wpmind-budget-input-group">
							<span class="wpmind-budget-currency">¥</span>
							<input type="number" id="budget_monthly_cny" name="monthly_limit_cny"
									value="<?php echo esc_attr( $budget_settings['global']['monthly_limit_cny'] ?? 0 ); ?>"
									min="0" step="0.01" class="small-text">
						</div>
					</div>
				</div>
				<p class="description"><?php esc_html_e( '设置为 0 表示不限制', 'wpmind' ); ?></p>
			</div>

			<!-- 告警设置 -->
			<div class="wpmind-budget-section">
				<h3>
					<span class="dashicons ri-alarm-warning-line"></span>
					<?php esc_html_e( '告警设置', 'wpmind' ); ?>
				</h3>
				<div class="wpmind-budget-row">
					<div class="wpmind-budget-field">
						<label for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label>
						<div class="wpmind-budget-input-group">
							<input type="number" id="budget_alert_threshold" name="alert_threshold"
									value="<?php echo esc_attr( $budget_settings['global']['alert_threshold'] ); ?>"
									min="1" max="100" step="1" class="small-text">
							<span class="wpmind-budget-suffix">%</span>
						</div>
						<p class="description"><?php esc_html_e( '当费用达到限额的此百分比时发送告警', 'wpmind' ); ?></p>
					</div>
					<div class="wpmind-budget-field">
						<label for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label>
						<select id="budget_enforcement_mode" name="enforcement_mode">
							<?php foreach ( \WPMind\Modules\CostControl\BudgetManager::get_mode_options() as $mode => $label ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $budget_settings['enforcement_mode'] ?? 'alert', $mode ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>

			<!-- 通知设置 -->
			<div class="wpmind-budget-section">
				<h3>
					<span class="dashicons ri-notification-3-line"></span>
					<?php esc_html_e( '通知方式', 'wpmind' ); ?>
				</h3>
				<div class="wpmind-budget-checkboxes">
					<label>
						<input type="checkbox" name="admin_notice" value="1" <?php checked( $budget_settings['notifications']['admin_notice'] ); ?>>
						<?php esc_html_e( '后台通知 - 在 WordPress 后台显示告警', 'wpmind' ); ?>
					</label>
					<label>
						<input type="checkbox" name="email_alert" value="1" <?php checked( $budget_settings['notifications']['email_alert'] ); ?>>
						<?php esc_html_e( '邮件通知 - 发送告警邮件', 'wpmind' ); ?>
					</label>
					<div class="wpmind-budget-email-field" style="<?php echo $budget_settings['notifications']['email_alert'] ? '' : 'display:none;'; ?>">
						<input type="email" name="email_address"
								value="<?php echo esc_attr( $budget_settings['notifications']['email_address'] ); ?>"
								placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>"
								class="regular-text">
					</div>
				</div>
			</div>

			<!-- 保存按钮 -->
			<div class="wpmind-budget-actions">
				<button type="button" class="button button-primary" id="wpmind-save-budget">
					<?php esc_html_e( '保存预算设置', 'wpmind' ); ?>
				</button>
				<span class="spinner"></span>
			</div>
		</div>
	</div>
</div>
