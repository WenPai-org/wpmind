<?php
/**
 * WPMind 智能路由 Tab
 *
 * 包含：路由策略 + Provider 排名
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取路由设置
$router = \WPMind\Routing\IntelligentRouter::instance();
$routing_status = $router->get_status_summary();
$current_strategy = $router->get_current_strategy();
$available_strategies = $router->get_available_strategies();

// 获取仪表板统计数据
$analytics = \WPMind\Analytics\AnalyticsManager::instance();
$dashboard = $analytics->get_dashboard_summary();
$latency_metrics = $analytics->get_latency_metrics();

// 构建 Provider 延迟映射
$provider_latency = array();
foreach ( $latency_metrics as $metric ) {
    $provider_latency[ $metric['provider'] ] = $metric;
}

// 策略图标映射（使用 Remixicon 类名，不含 dashicons 前缀）
$strategy_icons = array(
    'balanced'      => 'ri-equalizer-line',      // 平衡策略
    'performance'   => 'ri-speed-up-line',       // 性能优先
    'economic'      => 'ri-money-cny-circle-line', // 经济策略
    'load_balanced' => 'ri-loop-left-line',      // 负载均衡
);
?>

<div class="wpmind-routing-panel">
    <div class="wpmind-routing-header">
        <h2 class="wpmind-routing-title">
            <span class="dashicons ri-shuffle-line"></span>
            <?php esc_html_e( '智能路由', 'wpmind' ); ?>
        </h2>
        <button type="button" class="button button-small wpmind-refresh-routing" title="<?php esc_attr_e( '刷新路由状态', 'wpmind' ); ?>">
            <span class="dashicons ri-refresh-line"></span>
            <?php esc_html_e( '刷新', 'wpmind' ); ?>
        </button>
    </div>

    <!-- 路由统计仪表板 -->
    <div class="wpmind-routing-stats">
        <?php
        $today_requests = $dashboard['today']['requests'] ?? 0;
        $total_latency = 0;
        $latency_count = 0;
        foreach ( $latency_metrics as $metric ) {
            $total_latency += $metric['avg_latency'] * $metric['sample_count'];
            $latency_count += $metric['sample_count'];
        }
        $avg_latency = $latency_count > 0 ? round( $total_latency / $latency_count ) : 0;
        $provider_count = count( $routing_status['provider_scores'] ?? [] );
        ?>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-bar-chart-box-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( number_format( $today_requests ) ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '今日请求', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-time-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $avg_latency ); ?><small>ms</small></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '平均延迟', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-cloud-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value"><?php echo esc_html( $provider_count ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '活跃 Provider', 'wpmind' ); ?></span>
            </div>
        </div>
        <div class="wpmind-stat-card">
            <div class="wpmind-stat-icon">
                <span class="dashicons ri-money-cny-circle-line"></span>
            </div>
            <div class="wpmind-stat-content">
                <span class="wpmind-stat-value">¥<?php echo esc_html( number_format( $dashboard['today']['cost_cny'] ?? 0, 2 ) ); ?></span>
                <span class="wpmind-stat-label"><?php esc_html_e( '今日成本', 'wpmind' ); ?></span>
            </div>
        </div>
    </div>

    <div class="wpmind-routing-grid">
        <!-- 左栏：策略选择 -->
        <div class="wpmind-routing-left">
            <div class="wpmind-routing-section">
                <h3 class="wpmind-routing-section-title"><?php esc_html_e( '路由策略', 'wpmind' ); ?></h3>
                <p class="wpmind-routing-section-desc"><?php esc_html_e( '选择 AI 服务的路由策略', 'wpmind' ); ?></p>
                <div class="wpmind-strategy-list">
                    <?php foreach ( $available_strategies as $strategy_name => $strategy_info ) :
                        $icon = $strategy_icons[$strategy_name] ?? 'admin-generic';
                        $is_active = $current_strategy === $strategy_name;
                    ?>
                    <label class="wpmind-strategy-item <?php echo $is_active ? 'is-active' : ''; ?>">
                        <input type="radio" name="routing_strategy" value="<?php echo esc_attr( $strategy_name ); ?>"
                               <?php checked( $current_strategy, $strategy_name ); ?>>
                        
                        <div class="wpmind-strategy-item-icon">
                            <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                        </div>
                        
                        <div class="wpmind-strategy-item-content">
                            <span class="wpmind-strategy-item-title"><?php echo esc_html( $strategy_info['display_name'] ); ?></span>
                            <span class="wpmind-strategy-item-desc"><?php echo esc_html( $strategy_info['description'] ); ?></span>
                        </div>
                        
                        <div class="wpmind-strategy-item-check">
                            <span class="dashicons ri-check-line"></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 右栏：当前状态 -->
        <div class="wpmind-routing-right">
            <!-- 推荐 Provider 卡片 -->
            <?php if ( ! empty( $routing_status['recommended'] ) ) :
                $recommended_name = $routing_status['provider_scores'][$routing_status['recommended']]['name'] ?? $routing_status['recommended'];
                $recommended_score = $routing_status['provider_scores'][$routing_status['recommended']]['score'] ?? 0;
            ?>
            <div class="wpmind-routing-status-card">
                <div class="wpmind-routing-status-header">
                    <span class="wpmind-routing-status-badge"><?php esc_html_e( '正在使用', 'wpmind' ); ?></span>
                </div>
                <div class="wpmind-routing-status-main">
                    <span class="wpmind-routing-status-icon">
                        <span class="dashicons ri-check-line"></span>
                    </span>
                    <span class="wpmind-routing-status-provider" id="wpmind-recommended-provider">
                        <?php echo esc_html( $recommended_name ); ?>
                    </span>
                </div>
                <div class="wpmind-routing-status-score">
                    <span class="wpmind-routing-status-score-label"><?php esc_html_e( '综合得分', 'wpmind' ); ?></span>
                    <span class="wpmind-routing-status-score-value"><?php echo esc_html( number_format( $recommended_score, 1 ) ); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- 故障转移链 -->
            <?php if ( ! empty( $routing_status['failover_chain'] ) ) : ?>
            <div class="wpmind-routing-section">
                <h3 class="wpmind-routing-section-title"><?php esc_html_e( '故障转移链', 'wpmind' ); ?></h3>
                <div class="wpmind-routing-failover-flow" id="wpmind-failover-chain">
                    <?php
                    $chain = $routing_status['failover_chain'];
                    $total = count( $chain );
                    foreach ( $chain as $index => $provider ) :
                        $is_first = $index === 0;
                    ?>
                    <div class="wpmind-routing-failover-node <?php echo $is_first ? 'is-active' : ''; ?>">
                        <span class="wpmind-routing-failover-dot"></span>
                        <span class="wpmind-routing-failover-name"><?php echo esc_html( $provider ); ?></span>
                        <?php if ( $is_first ) : ?>
                        <span class="wpmind-routing-failover-badge"><?php esc_html_e( '主', 'wpmind' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $index < $total - 1 ) : ?>
                    <div class="wpmind-routing-failover-line"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 手动优先级设置 -->
    <?php
    $manual_priority = $router->get_manual_priority();
    $has_manual_priority = ! empty( $manual_priority );
    ?>
    <div class="wpmind-routing-section wpmind-routing-priority">
        <div class="wpmind-routing-section-header">
            <h3 class="wpmind-routing-section-title"><?php esc_html_e( '手动优先级', 'wpmind' ); ?></h3>
            <div class="wpmind-routing-priority-actions">
                <?php if ( $has_manual_priority ) : ?>
                <button type="button" class="button button-small wpmind-clear-priority" title="<?php esc_attr_e( '清除手动优先级', 'wpmind' ); ?>">
                    <span class="dashicons ri-delete-bin-line"></span>
                    <?php esc_html_e( '清除', 'wpmind' ); ?>
                </button>
                <?php endif; ?>
                <button type="button" class="button button-primary button-small wpmind-save-priority" title="<?php esc_attr_e( '保存优先级', 'wpmind' ); ?>">
                    <span class="dashicons ri-save-line"></span>
                    <?php esc_html_e( '保存', 'wpmind' ); ?>
                </button>
            </div>
        </div>
        <p class="wpmind-routing-section-desc">
            <?php esc_html_e( '拖拽调整 Provider 顺序，设置故障转移优先级。排在前面的 Provider 会优先使用。', 'wpmind' ); ?>
            <?php if ( $has_manual_priority ) : ?>
            <span class="wpmind-priority-badge"><?php esc_html_e( '已启用手动优先级', 'wpmind' ); ?></span>
            <?php endif; ?>
        </p>
        <div class="wpmind-priority-list" id="wpmind-priority-list">
            <?php
            // 如果有手动优先级，按手动顺序显示；否则按得分排序
            $display_order = $has_manual_priority ? $manual_priority : array_keys( $routing_status['provider_scores'] ?? [] );
            $index = 1;
            foreach ( $display_order as $provider_id ) :
                $score_data = $routing_status['provider_scores'][ $provider_id ] ?? null;
                if ( ! $score_data ) continue;
            ?>
            <div class="wpmind-priority-item" data-provider="<?php echo esc_attr( $provider_id ); ?>">
                <span class="wpmind-priority-handle">
                    <span class="dashicons ri-draggable"></span>
                </span>
                <span class="wpmind-priority-index"><?php echo esc_html( $index++ ); ?></span>
                <span class="wpmind-priority-name"><?php echo esc_html( $score_data['name'] ); ?></span>
                <span class="wpmind-priority-score"><?php echo esc_html( number_format( $score_data['score'], 1 ) ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Provider 排名 -->
    <?php if ( ! empty( $routing_status['provider_scores'] ) ) : ?>
    <div class="wpmind-routing-section wpmind-routing-ranking">
        <h3 class="wpmind-routing-section-title"><?php esc_html_e( 'Provider 排名', 'wpmind' ); ?></h3>
        <p class="wpmind-routing-section-desc"><?php esc_html_e( '基于当前策略的 Provider 得分排名，得分越高越优先被选中', 'wpmind' ); ?></p>
        <div class="wpmind-routing-scores" id="wpmind-routing-scores">
            <?php foreach ( $routing_status['provider_scores'] as $provider_id => $score_data ) :
                $is_top = $score_data['rank'] === 1;
                $latency_info = $provider_latency[ $provider_id ] ?? null;
                $avg_latency = $latency_info ? $latency_info['avg_latency'] : null;
            ?>
            <div class="wpmind-routing-score-item <?php echo $is_top ? 'is-top' : ''; ?>">
                <span class="wpmind-routing-rank"><?php echo esc_html( $score_data['rank'] ); ?></span>
                <span class="wpmind-routing-provider-name"><?php echo esc_html( $score_data['name'] ); ?></span>
                <?php if ( $avg_latency !== null ) : ?>
                <span class="wpmind-routing-latency"><?php echo esc_html( $avg_latency ); ?>ms</span>
                <?php endif; ?>
                <div class="wpmind-routing-score-bar">
                    <div class="wpmind-routing-score-fill" style="width: <?php echo esc_attr( $score_data['score'] ); ?>%;"></div>
                </div>
                <span class="wpmind-routing-score-value"><?php echo esc_html( number_format( $score_data['score'], 1 ) ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
