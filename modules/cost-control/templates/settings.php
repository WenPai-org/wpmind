<?php
/**
 * WPMind Cost Control Settings Tab
 *
 * 费用控制设置界面 - 用量追踪、预算限额、告警通知
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 类存在性检查（关键修复）
if ( ! class_exists( 'WPMind\\Modules\\CostControl\\BudgetManager' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Cost Control 模块未正确加载', 'wpmind' ) . '</p></div>';
    return;
}

if ( ! class_exists( 'WPMind\\Modules\\CostControl\\BudgetChecker' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Cost Control 模块未正确加载', 'wpmind' ) . '</p></div>';
    return;
}

if ( ! class_exists( 'WPMind\\Modules\\CostControl\\UsageTracker' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Cost Control 模块未正确加载', 'wpmind' ) . '</p></div>';
    return;
}

use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\BudgetChecker;
use WPMind\Modules\CostControl\UsageTracker;

// 获取预算设置
$budget_manager = BudgetManager::instance();
$budget_settings = $budget_manager->get_settings();
$budget_checker = BudgetChecker::instance();
$budget_summary = $budget_checker->get_summary();

// 获取用量统计
$today_stats = UsageTracker::get_today_stats();
$month_stats = UsageTracker::get_month_stats();
$total_stats = UsageTracker::get_stats();

// 安全获取数组值的辅助函数
$get_value = function( $array, $key, $default = 0 ) {
    return $array[$key] ?? $default;
};
?>

<div class="wpmind-cost-control-panel">
    <div class="wpmind-cost-control-header">
        <h2 class="wpmind-cost-control-title">
            <span class="dashicons ri-money-cny-circle-line"></span>
            <?php esc_html_e( 'Cost Control', 'wpmind' ); ?>
        </h2>
        <span class="wpmind-cost-control-badge">v1.0</span>
    </div>

    <p class="wpmind-cost-control-desc">
        <?php esc_html_e( '监控 AI 服务费用，设置预算限额，接收告警通知。', 'wpmind' ); ?>
    </p>

    <!-- 用量概览 -->
    <div class="wpmind-cost-stats">
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-calendar-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value">
                    <?php echo esc_html( UsageTracker::format_cost_by_currency(
                        $get_value( $today_stats, 'cost_usd', 0 ),
                        $get_value( $today_stats, 'cost_cny', 0 )
                    ) ); ?>
                </span>
                <span class="wpmind-stat-label"><?php esc_html_e( '今日费用', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-calendar-check-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value">
                    <?php echo esc_html( UsageTracker::format_cost_by_currency(
                        $get_value( $month_stats, 'cost_usd', 0 ),
                        $get_value( $month_stats, 'cost_cny', 0 )
                    ) ); ?>
                </span>
                <span class="wpmind-stat-label"><?php esc_html_e( '本月费用', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-file-list-3-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value">
                    <?php echo esc_html( number_format( $get_value( $today_stats, 'requests', 0 ) ) ); ?>
                </span>
                <span class="wpmind-stat-label"><?php esc_html_e( '今日请求', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-coin-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value">
                    <?php echo esc_html( UsageTracker::format_tokens(
                        $get_value( $today_stats, 'input_tokens', 0 ) + $get_value( $today_stats, 'output_tokens', 0 )
                    ) ); ?>
                </span>
                <span class="wpmind-stat-label"><?php esc_html_e( '今日 Tokens', 'wpmind' ); ?></span>
            </div>
        </div>
    </div>

    <div class="wpmind-cost-grid">
        <!-- 左栏：预算设置 -->
        <div class="wpmind-cost-left">
            <div class="wpmind-cost-section">
                <h3 class="wpmind-cost-section-title">
                    <span class="dashicons ri-shield-check-line"></span>
                    <?php esc_html_e( '预算限额', 'wpmind' ); ?>
                    <?php if ( $get_value( $budget_settings, 'enabled', false ) ) : ?>
                    <span class="wpmind-budget-status-badge wpmind-budget-enabled"><?php esc_html_e( '已启用', 'wpmind' ); ?></span>
                    <?php endif; ?>
                </h3>

                <div class="wpmind-budget-toggle-row">
                    <label class="wpmind-toggle">
                        <input type="checkbox" id="wpmind_budget_enabled" name="budget_enabled" value="1" <?php checked( $get_value( $budget_settings, 'enabled', false ) ); ?>>
                        <span class="wpmind-toggle-slider"></span>
                        <span class="wpmind-toggle-label"><?php esc_html_e( '启用预算限额', 'wpmind' ); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e( '启用后，当费用达到设定限额时将触发告警或自动限制服务。', 'wpmind' ); ?></p>
                </div>

                <div class="wpmind-budget-settings" id="wpmind-budget-settings" style="<?php echo $get_value( $budget_settings, 'enabled', false ) ? '' : 'display:none;'; ?>">
                    <!-- 全局限额 -->
                    <div class="wpmind-budget-subsection">
                        <h4><?php esc_html_e( '全局限额', 'wpmind' ); ?></h4>
                        <?php
                        $global = $get_value( $budget_settings, 'global', [] );
                        ?>
                        <div class="wpmind-budget-grid">
                            <div class="wpmind-budget-field">
                                <label for="budget_daily_usd"><?php esc_html_e( '每日 USD', 'wpmind' ); ?></label>
                                <div class="wpmind-budget-input-group">
                                    <span class="wpmind-budget-currency">$</span>
                                    <input type="number" id="budget_daily_usd" name="daily_limit_usd"
                                           value="<?php echo esc_attr( $get_value( $global, 'daily_limit_usd', 0 ) ); ?>"
                                           min="0" step="0.01" class="small-text">
                                </div>
                            </div>
                            <div class="wpmind-budget-field">
                                <label for="budget_monthly_usd"><?php esc_html_e( '每月 USD', 'wpmind' ); ?></label>
                                <div class="wpmind-budget-input-group">
                                    <span class="wpmind-budget-currency">$</span>
                                    <input type="number" id="budget_monthly_usd" name="monthly_limit_usd"
                                           value="<?php echo esc_attr( $get_value( $global, 'monthly_limit_usd', 0 ) ); ?>"
                                           min="0" step="0.01" class="small-text">
                                </div>
                            </div>
                            <div class="wpmind-budget-field">
                                <label for="budget_daily_cny"><?php esc_html_e( '每日 CNY', 'wpmind' ); ?></label>
                                <div class="wpmind-budget-input-group">
                                    <span class="wpmind-budget-currency">¥</span>
                                    <input type="number" id="budget_daily_cny" name="daily_limit_cny"
                                           value="<?php echo esc_attr( $get_value( $global, 'daily_limit_cny', 0 ) ); ?>"
                                           min="0" step="0.01" class="small-text">
                                </div>
                            </div>
                            <div class="wpmind-budget-field">
                                <label for="budget_monthly_cny"><?php esc_html_e( '每月 CNY', 'wpmind' ); ?></label>
                                <div class="wpmind-budget-input-group">
                                    <span class="wpmind-budget-currency">¥</span>
                                    <input type="number" id="budget_monthly_cny" name="monthly_limit_cny"
                                           value="<?php echo esc_attr( $get_value( $global, 'monthly_limit_cny', 0 ) ); ?>"
                                           min="0" step="0.01" class="small-text">
                                </div>
                            </div>
                        </div>
                        <p class="description"><?php esc_html_e( '设置为 0 表示不限制', 'wpmind' ); ?></p>
                    </div>

                    <!-- 告警设置 -->
                    <div class="wpmind-budget-subsection">
                        <h4><?php esc_html_e( '告警设置', 'wpmind' ); ?></h4>
                        <div class="wpmind-budget-row">
                            <div class="wpmind-budget-field">
                                <label for="budget_alert_threshold"><?php esc_html_e( '告警阈值', 'wpmind' ); ?></label>
                                <div class="wpmind-budget-input-group">
                                    <input type="number" id="budget_alert_threshold" name="alert_threshold"
                                           value="<?php echo esc_attr( $get_value( $global, 'alert_threshold', 80 ) ); ?>"
                                           min="1" max="100" step="1" class="small-text">
                                    <span class="wpmind-budget-suffix">%</span>
                                </div>
                                <p class="description"><?php esc_html_e( '当费用达到限额的此百分比时发送告警', 'wpmind' ); ?></p>
                            </div>
                            <div class="wpmind-budget-field">
                                <label for="budget_enforcement_mode"><?php esc_html_e( '超限处理', 'wpmind' ); ?></label>
                                <select id="budget_enforcement_mode" name="enforcement_mode">
                                    <?php foreach ( BudgetManager::get_mode_options() as $mode => $label ) : ?>
                                    <option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $get_value( $budget_settings, 'enforcement_mode', 'alert' ), $mode ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 通知设置 -->
                    <div class="wpmind-budget-subsection">
                        <h4><?php esc_html_e( '通知方式', 'wpmind' ); ?></h4>
                        <?php
                        $notifications = $get_value( $budget_settings, 'notifications', [] );
                        ?>
                        <div class="wpmind-budget-checkboxes">
                            <label>
                                <input type="checkbox" name="admin_notice" value="1" <?php checked( $get_value( $notifications, 'admin_notice', true ) ); ?>>
                                <?php esc_html_e( '后台通知 - 在 WordPress 后台显示告警', 'wpmind' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="email_alert" value="1" <?php checked( $get_value( $notifications, 'email_alert', false ) ); ?>>
                                <?php esc_html_e( '邮件通知 - 发送告警邮件', 'wpmind' ); ?>
                            </label>
                            <div class="wpmind-budget-email-field" style="<?php echo $get_value( $notifications, 'email_alert', false ) ? '' : 'display:none;'; ?>">
                                <input type="email" name="email_address"
                                       value="<?php echo esc_attr( $get_value( $notifications, 'email_address', '' ) ); ?>"
                                       placeholder="<?php esc_attr_e( '告警邮箱地址', 'wpmind' ); ?>"
                                       class="regular-text">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 保存按钮 -->
                <div class="wpmind-cost-actions">
                    <button type="button" class="button button-primary" id="wpmind-save-cost-control">
                        <span class="dashicons ri-save-line"></span>
                        <?php esc_html_e( '保存设置', 'wpmind' ); ?>
                    </button>
                    <button type="button" class="button" id="wpmind-clear-usage-stats">
                        <span class="dashicons ri-delete-bin-line"></span>
                        <?php esc_html_e( '清除统计', 'wpmind' ); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>

        <!-- 右栏：用量详情 -->
        <div class="wpmind-cost-right">
            <div class="wpmind-cost-section">
                <h3 class="wpmind-cost-section-title">
                    <span class="dashicons ri-bar-chart-2-line"></span>
                    <?php esc_html_e( '服务商用量', 'wpmind' ); ?>
                </h3>

                <?php
                $providers = $get_value( $total_stats, 'providers', [] );
                if ( empty( $providers ) ) :
                ?>
                <div class="wpmind-cost-empty">
                    <span class="dashicons ri-pie-chart-line"></span>
                    <p><?php esc_html_e( '暂无用量数据', 'wpmind' ); ?></p>
                    <p class="wpmind-cost-empty-hint"><?php esc_html_e( '使用 AI 服务后，用量数据将显示在这里。', 'wpmind' ); ?></p>
                </div>
                <?php else : ?>
                <div class="wpmind-provider-list">
                    <?php foreach ( $providers as $provider => $data ) :
                        $currency = UsageTracker::get_currency( $provider );
                        $total_cost = $get_value( $data, 'total_cost', 0 );
                        $request_count = $get_value( $data, 'request_count', 0 );
                        $total_tokens = $get_value( $data, 'total_input_tokens', 0 ) + $get_value( $data, 'total_output_tokens', 0 );
                    ?>
                    <div class="wpmind-provider-item">
                        <div class="wpmind-provider-info">
                            <span class="wpmind-provider-icon <?php echo esc_attr( UsageTracker::get_provider_icon( $provider ) ); ?>"></span>
                            <span class="wpmind-provider-name"><?php echo esc_html( UsageTracker::get_provider_display_name( $provider ) ); ?></span>
                        </div>
                        <div class="wpmind-provider-stats">
                            <span class="wpmind-provider-cost"><?php echo esc_html( UsageTracker::format_cost( $total_cost, $currency ) ); ?></span>
                            <span class="wpmind-provider-meta">
                                <?php echo esc_html( number_format( $request_count ) ); ?> <?php esc_html_e( '请求', 'wpmind' ); ?> /
                                <?php echo esc_html( UsageTracker::format_tokens( $total_tokens ) ); ?> tokens
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- 说明 -->
            <div class="wpmind-cost-section wpmind-cost-info">
                <h3 class="wpmind-cost-section-title">
                    <span class="dashicons ri-lightbulb-line"></span>
                    <?php esc_html_e( '费用说明', 'wpmind' ); ?>
                </h3>
                <div class="wpmind-cost-info-content">
                    <p><?php esc_html_e( 'Cost Control 模块帮助您监控和控制 AI 服务费用：', 'wpmind' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( '自动追踪每次 API 调用的 Token 用量', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '根据各服务商定价计算费用（USD/CNY）', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '设置每日/每月预算限额', 'wpmind' ); ?></li>
                        <li><?php esc_html_e( '接近或超出限额时发送告警通知', 'wpmind' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 预算启用切换
    $('#wpmind_budget_enabled').on('change', function() {
        $('#wpmind-budget-settings').toggle(this.checked);
    });

    // 邮件告警切换
    $('input[name="email_alert"]').on('change', function() {
        $('.wpmind-budget-email-field').toggle(this.checked);
    });

    // 保存设置
    $('#wpmind-save-cost-control').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        var settings = {
            enabled: $('#wpmind_budget_enabled').is(':checked'),
            global: {
                daily_limit_usd: parseFloat($('#budget_daily_usd').val()) || 0,
                daily_limit_cny: parseFloat($('#budget_daily_cny').val()) || 0,
                monthly_limit_usd: parseFloat($('#budget_monthly_usd').val()) || 0,
                monthly_limit_cny: parseFloat($('#budget_monthly_cny').val()) || 0,
                alert_threshold: parseInt($('#budget_alert_threshold').val()) || 80
            },
            enforcement_mode: $('#budget_enforcement_mode').val(),
            notifications: {
                admin_notice: $('input[name="admin_notice"]').is(':checked'),
                email_alert: $('input[name="email_alert"]').is(':checked'),
                email_address: $('input[name="email_address"]').val()
            }
        };

        $.post(wpmindData.ajaxurl, {
            action: 'wpmind_save_cost_control_settings',
            nonce: wpmindData.nonce,
            settings: JSON.stringify(settings)
        }, function(response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                alert(response.data.message || '设置已保存');
            } else {
                alert(response.data.message || '保存失败');
            }
        });
    });

    // 清除统计
    $('#wpmind-clear-usage-stats').on('click', function() {
        if (!confirm('确定要清除所有用量统计数据吗？此操作不可恢复。')) {
            return;
        }

        var $btn = $(this);
        var $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(wpmindData.ajaxurl, {
            action: 'wpmind_clear_usage_stats',
            nonce: wpmindData.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                alert(response.data.message || '统计已清除');
                location.reload();
            } else {
                alert(response.data.message || '清除失败');
            }
        });
    });
});
</script>
