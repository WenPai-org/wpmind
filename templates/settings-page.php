<?php
/**
 * WPMind 设置页面模板
 *
 * @package WPMind
 * @since 1.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取数据
$wpmind_instance  = \WPMind\wpmind();
$endpoints        = $wpmind_instance->get_custom_endpoints();
$default_provider = get_option( 'wpmind_default_provider', '' );
$request_timeout  = get_option( 'wpmind_request_timeout', 60 );

// 获取故障转移状态
$failover_manager = \WPMind\Failover\FailoverManager::instance();
$provider_status  = $failover_manager->getStatusSummary();

// 获取用量统计
$usage_stats = \WPMind\Usage\UsageTracker::getStats();
$today_stats = \WPMind\Usage\UsageTracker::getTodayStats();
$week_stats  = \WPMind\Usage\UsageTracker::getWeekStats();
$month_stats = \WPMind\Usage\UsageTracker::getMonthStats();
$last_updated = $usage_stats['last_updated'] ?? 0;
$has_usage_data = ( $usage_stats['total']['requests'] ?? 0 ) > 0;

// 获取预算设置
$budget_manager = \WPMind\Budget\BudgetManager::instance();
$budget_settings = $budget_manager->getSettings();
$budget_checker = \WPMind\Budget\BudgetChecker::instance();
$budget_summary = $budget_checker->getSummary();

// 获取路由设置
$router = \WPMind\Routing\IntelligentRouter::instance();
$routing_status = $router->getStatusSummary();
$current_strategy = $router->getCurrentStrategy();
$available_strategies = $router->getAvailableStrategies();
?>

<div class="wrap wpmind-settings">
    <h1><?php esc_html_e( '文派心思设置', 'wpmind' ); ?></h1>
    <p class="description">
        <?php esc_html_e( '配置自定义 AI 服务端点，支持国内外多种 AI 服务。', 'wpmind' ); ?>
    </p>

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
                <span class="dashicons dashicons-update"></span>
            </button>
            <button type="button" class="button button-small wpmind-clear-usage" title="<?php esc_attr_e( '清除统计', 'wpmind' ); ?>" aria-label="<?php esc_attr_e( '清除所有用量统计数据', 'wpmind' ); ?>">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e( '清除', 'wpmind' ); ?>
            </button>
        </h2>

        <?php if ( ! $has_usage_data ) : ?>
        <!-- 空状态提示 -->
        <div class="wpmind-usage-empty">
            <span class="dashicons dashicons-chart-bar"></span>
            <p><?php esc_html_e( '暂无用量数据', 'wpmind' ); ?></p>
            <p class="description"><?php esc_html_e( '当 AI 服务被调用时，用量统计将自动记录在这里。', 'wpmind' ); ?></p>
        </div>
        <?php else : ?>

        <p class="wpmind-usage-note">
            <?php esc_html_e( '费用为估算值，按各服务商官方定价计算（每百万 tokens）。实际费用以服务商账单为准。', 'wpmind' ); ?>
        </p>

        <div class="wpmind-usage-cards">
            <div class="wpmind-usage-card">
                <div class="wpmind-usage-card-header"><?php esc_html_e( '今日', 'wpmind' ); ?></div>
                <div class="wpmind-usage-card-body">
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="today-tokens"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatTokens( $today_stats['input_tokens'] + $today_stats['output_tokens'] ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value wpmind-usage-cost" id="today-cost"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatCostByCurrency( $today_stats['cost_usd'] ?? 0, $today_stats['cost_cny'] ?? 0 ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="today-requests"><?php echo esc_html( $today_stats['requests'] ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
                    </div>
                </div>
            </div>
            <div class="wpmind-usage-card">
                <div class="wpmind-usage-card-header"><?php esc_html_e( '本周', 'wpmind' ); ?></div>
                <div class="wpmind-usage-card-body">
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="week-tokens"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatTokens( $week_stats['input_tokens'] + $week_stats['output_tokens'] ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value wpmind-usage-cost" id="week-cost"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatCostByCurrency( $week_stats['cost_usd'] ?? 0, $week_stats['cost_cny'] ?? 0 ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="week-requests"><?php echo esc_html( $week_stats['requests'] ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
                    </div>
                </div>
            </div>
            <div class="wpmind-usage-card">
                <div class="wpmind-usage-card-header"><?php esc_html_e( '本月', 'wpmind' ); ?></div>
                <div class="wpmind-usage-card-body">
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="month-tokens"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatTokens( $month_stats['input_tokens'] + $month_stats['output_tokens'] ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value wpmind-usage-cost" id="month-cost"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatCostByCurrency( $month_stats['cost_usd'] ?? 0, $month_stats['cost_cny'] ?? 0 ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="month-requests"><?php echo esc_html( $month_stats['requests'] ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( '请求', 'wpmind' ); ?></span>
                    </div>
                </div>
            </div>
            <div class="wpmind-usage-card">
                <div class="wpmind-usage-card-header"><?php esc_html_e( '总计', 'wpmind' ); ?></div>
                <div class="wpmind-usage-card-body">
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value" id="total-tokens"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatTokens( ($usage_stats['total']['input_tokens'] ?? 0) + ($usage_stats['total']['output_tokens'] ?? 0) ) ); ?></span>
                        <span class="wpmind-usage-label"><?php esc_html_e( 'Tokens', 'wpmind' ); ?></span>
                    </div>
                    <div class="wpmind-usage-stat">
                        <span class="wpmind-usage-value wpmind-usage-cost" id="total-cost"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatCostByCurrency( $usage_stats['total']['cost_usd'] ?? 0, $usage_stats['total']['cost_cny'] ?? 0 ) ); ?></span>
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
                $currency = \WPMind\Usage\UsageTracker::getCurrency( $provider_id );
                $display_name = \WPMind\Usage\UsageTracker::getProviderDisplayName( $provider_id );
                $icon_class = \WPMind\Usage\UsageTracker::getProviderIcon( $provider_id );
                $icon_color = \WPMind\Usage\UsageTracker::getProviderColor( $provider_id );
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
                        <span class="wpmind-provider-usage-value"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatTokens( $provider_stats['total_input_tokens'] + $provider_stats['total_output_tokens'] ) ); ?></span>
                    </div>
                    <div class="wpmind-provider-usage-row">
                        <span class="wpmind-provider-usage-label"><?php esc_html_e( '费用', 'wpmind' ); ?></span>
                        <span class="wpmind-provider-usage-value"><?php echo esc_html( \WPMind\Usage\UsageTracker::formatCost( $provider_stats['total_cost'], $currency ) ); ?></span>
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
            <span class="dashicons dashicons-chart-area"></span>
            <?php esc_html_e( '分析仪表板', 'wpmind' ); ?>
            <select id="wpmind-analytics-range" class="wpmind-analytics-range-select">
                <option value="7d"><?php esc_html_e( '最近 7 天', 'wpmind' ); ?></option>
                <option value="30d"><?php esc_html_e( '最近 30 天', 'wpmind' ); ?></option>
            </select>
            <button type="button" class="button button-small wpmind-refresh-analytics" title="<?php esc_attr_e( '刷新图表', 'wpmind' ); ?>">
                <span class="dashicons dashicons-update"></span>
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

    <!-- 预算设置面板 -->
    <div class="wpmind-budget-panel">
        <h2 class="title">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e( '预算限额', 'wpmind' ); ?>
            <?php if ( $budget_settings['enabled'] ) : ?>
            <span class="wpmind-budget-status-badge wpmind-budget-enabled"><?php esc_html_e( '已启用', 'wpmind' ); ?></span>
            <?php endif; ?>
        </h2>

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
                    <h3><?php esc_html_e( '全局限额', 'wpmind' ); ?></h3>
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
                    <h3><?php esc_html_e( '告警设置', 'wpmind' ); ?></h3>
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
                                <?php foreach ( \WPMind\Budget\BudgetManager::getModeOptions() as $mode => $label ) : ?>
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
                    <h3><?php esc_html_e( '通知方式', 'wpmind' ); ?></h3>
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

    <!-- 智能路由面板 -->
    <div class="wpmind-routing-panel">
        <h2 class="title">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e( '智能路由', 'wpmind' ); ?>
            <button type="button" class="button button-small wpmind-refresh-routing" title="<?php esc_attr_e( '刷新路由状态', 'wpmind' ); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
        </h2>

        <div class="wpmind-routing-content">
            <!-- 策略选择 -->
            <div class="wpmind-routing-section">
                <h3><?php esc_html_e( '路由策略', 'wpmind' ); ?></h3>
                <div class="wpmind-routing-strategies">
                    <?php foreach ( $available_strategies as $strategy_name => $strategy_info ) : ?>
                    <label class="wpmind-routing-strategy-option <?php echo $current_strategy === $strategy_name ? 'is-active' : ''; ?>">
                        <input type="radio" name="routing_strategy" value="<?php echo esc_attr( $strategy_name ); ?>"
                               <?php checked( $current_strategy, $strategy_name ); ?>>
                        <span class="wpmind-routing-strategy-name"><?php echo esc_html( $strategy_info['display_name'] ); ?></span>
                        <span class="wpmind-routing-strategy-desc"><?php echo esc_html( $strategy_info['description'] ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Provider 得分排名 -->
            <?php if ( ! empty( $routing_status['provider_scores'] ) ) : ?>
            <div class="wpmind-routing-section">
                <h3><?php esc_html_e( 'Provider 排名', 'wpmind' ); ?></h3>
                <p class="description"><?php esc_html_e( '基于当前策略的 Provider 得分排名，得分越高越优先被选中。', 'wpmind' ); ?></p>
                <div class="wpmind-routing-scores" id="wpmind-routing-scores">
                    <?php foreach ( $routing_status['provider_scores'] as $provider_id => $score_data ) : ?>
                    <div class="wpmind-routing-score-item">
                        <span class="wpmind-routing-rank">#<?php echo esc_html( $score_data['rank'] ); ?></span>
                        <span class="wpmind-routing-provider-name"><?php echo esc_html( $score_data['name'] ); ?></span>
                        <div class="wpmind-routing-score-bar">
                            <div class="wpmind-routing-score-fill" style="width: <?php echo esc_attr( $score_data['score'] ); ?>%;"></div>
                        </div>
                        <span class="wpmind-routing-score-value"><?php echo esc_html( number_format( $score_data['score'], 1 ) ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 推荐 Provider -->
            <?php if ( ! empty( $routing_status['recommended'] ) ) : ?>
            <div class="wpmind-routing-section">
                <h3><?php esc_html_e( '当前推荐', 'wpmind' ); ?></h3>
                <div class="wpmind-routing-recommended">
                    <span class="wpmind-routing-recommended-label"><?php esc_html_e( '推荐 Provider:', 'wpmind' ); ?></span>
                    <strong class="wpmind-routing-recommended-value" id="wpmind-recommended-provider">
                        <?php echo esc_html( $routing_status['provider_scores'][$routing_status['recommended']]['name'] ?? $routing_status['recommended'] ); ?>
                    </strong>
                </div>
                <?php if ( ! empty( $routing_status['failover_chain'] ) ) : ?>
                <div class="wpmind-routing-failover">
                    <span class="wpmind-routing-failover-label"><?php esc_html_e( '故障转移链:', 'wpmind' ); ?></span>
                    <span class="wpmind-routing-failover-chain" id="wpmind-failover-chain">
                        <?php echo esc_html( implode( ' → ', $routing_status['failover_chain'] ) ); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Provider 状态面板 -->
    <?php if ( ! empty( $provider_status ) ) : ?>
    <div class="wpmind-status-panel">
        <h2 class="title">
            <?php esc_html_e( '服务状态', 'wpmind' ); ?>
            <button type="button" class="button button-small wpmind-refresh-status" title="<?php esc_attr_e( '刷新状态', 'wpmind' ); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
            <button type="button" class="button button-small wpmind-reset-all-breakers" title="<?php esc_attr_e( '重置所有熔断器', 'wpmind' ); ?>">
                <span class="dashicons dashicons-image-rotate"></span>
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

    <form method="post" action="options.php" id="wpmind-settings-form">
        <?php settings_fields( 'wpmind_settings' ); ?>

        <h2 class="title"><?php esc_html_e( '全局设置', 'wpmind' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="wpmind_request_timeout">
                        <?php esc_html_e( '请求超时', 'wpmind' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number"
                           id="wpmind_request_timeout"
                           name="wpmind_request_timeout"
                           value="<?php echo esc_attr( $request_timeout ); ?>"
                           min="10"
                           max="300"
                           step="1"
                           class="small-text">
                    <span class="description">
                        <?php esc_html_e( '秒 (建议 60-120)', 'wpmind' ); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wpmind_default_provider">
                        <?php esc_html_e( '默认服务', 'wpmind' ); ?>
                    </label>
                </th>
                <td>
                    <select id="wpmind_default_provider" name="wpmind_default_provider">
                        <option value="">
                            <?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?>
                        </option>
                        <?php foreach ( $endpoints as $key => $endpoint ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"
                                <?php selected( $default_provider, $key ); ?>>
                                <?php echo esc_html( $endpoint['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'AI 服务端点', 'wpmind' ); ?></h2>

        <div class="wpmind-endpoints-grid">
            <?php foreach ( $endpoints as $key => $endpoint ) :
                $has_api_key = $wpmind_instance->has_api_key( $key );
                $icon_class  = \WPMind\Usage\UsageTracker::getProviderIcon( $key );
                $icon_color  = \WPMind\Usage\UsageTracker::getProviderColor( $key );
            ?>
            <div class="wpmind-endpoint-card" id="endpoint-<?php echo esc_attr( $key ); ?>">
                <div class="wpmind-endpoint-header">
                    <i class="<?php echo esc_attr( $icon_class ); ?> wpmind-provider-icon" style="color: <?php echo esc_attr( $icon_color ); ?>;"></i>
                    <span class="wpmind-endpoint-name">
                        <?php echo esc_html( $endpoint['name'] ); ?>
                    </span>
                    <code class="wpmind-endpoint-key"><?php echo esc_html( $endpoint['display_name'] ?? $key ); ?></code>
                    <?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
                        <span class="wpmind-status wpmind-status-official">
                            <?php esc_html_e( '官方服务', 'wpmind' ); ?>
                        </span>
                    <?php elseif ( ! empty( $endpoint['enabled'] ) && $has_api_key ) : ?>
                        <span class="wpmind-status wpmind-status-active">
                            <?php esc_html_e( '已启用', 'wpmind' ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( '启用', 'wpmind' ); ?></th>
                        <td>
                            <label class="wpmind-toggle">
                                <input type="checkbox"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][enabled]"
                                       value="1"
                                       <?php checked( ! empty( $endpoint['enabled'] ) ); ?>>
                                <span class="wpmind-toggle-slider"></span>
                                <span class="wpmind-toggle-label">
                                    <?php esc_html_e( '启用此服务', 'wpmind' ); ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key_<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( 'API Key', 'wpmind' ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wpmind-api-key-field">
                                <input type="password"
                                       id="api_key_<?php echo esc_attr( $key ); ?>"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][api_key]"
                                       value=""
                                       class="regular-text"
                                       autocomplete="new-password"
                                       placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
                                <button type="button"
                                        class="button wpmind-toggle-key"
                                        data-target="api_key_<?php echo esc_attr( $key ); ?>"
                                        aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <?php if ( $has_api_key ) : ?>
                            <label class="wpmind-clear-key">
                                <input type="checkbox"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]"
                                       value="1"
                                       class="wpmind-clear-checkbox">
                                <?php esc_html_e( '清除 API Key', 'wpmind' ); ?>
                            </label>
                            <?php endif; ?>
                            <?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
                            <p class="description">
                                <?php esc_html_e( '此 API Key 将同步到 WordPress AI Client', 'wpmind' ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '连接测试', 'wpmind' ); ?></th>
                        <td>
                            <button type="button"
                                    class="button wpmind-test-connection"
                                    data-provider="<?php echo esc_attr( $key ); ?>"
                                    <?php echo empty( $endpoint['enabled'] ) ? 'disabled' : ''; ?>>
                                <?php esc_html_e( '测试连接', 'wpmind' ); ?>
                            </button>
                            <span class="wpmind-test-result"></span>
                        </td>
                    </tr>
                    <tr class="wpmind-advanced-row">
                        <td colspan="2">
                            <button type="button" class="button button-link wpmind-toggle-advanced">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                <?php esc_html_e( '高级设置', 'wpmind' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>

                <!-- 高级设置（默认隐藏） -->
                <table class="form-table wpmind-advanced-settings" style="display: none;">
                    <tr>
                        <th scope="row">
                            <label for="custom_base_url_<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url"
                                   id="custom_base_url_<?php echo esc_attr( $key ); ?>"
                                   name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]"
                                   value="<?php echo esc_attr( $endpoint['custom_base_url'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr( $endpoint['base_url'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></th>
                        <td class="wpmind-models">
                            <?php foreach ( (array) $endpoint['models'] as $model ) : ?>
                                <code><?php echo esc_html( $model ); ?></code>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <?php submit_button( __( '保存设置', 'wpmind' ) ); ?>
    </form>
</div>
