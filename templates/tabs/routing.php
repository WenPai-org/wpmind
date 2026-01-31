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
$routing_status = $router->getStatusSummary();
$current_strategy = $router->getCurrentStrategy();
$available_strategies = $router->getAvailableStrategies();
?>

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
            <p class="description"><?php esc_html_e( '选择 AI 服务的路由策略，系统将根据策略自动选择最优的 Provider。', 'wpmind' ); ?></p>
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
