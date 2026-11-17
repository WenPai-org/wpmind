<?php
/**
 * 智能路由视图（kit 化，AI 服务子页）。
 * 保留全部 JS 契约：input[name=routing_strategy] / .wpmind-strategy-item /
 * .wpmind-refresh-routing / #wpmind-priority-list / .wpmind-save-priority /
 * .wpmind-clear-priority / #wpmind-routing-scores / #wpmind-recommended-provider /
 * #wpmind-failover-chain / .wpmind-routing-priority-actions / .wpmind-priority-badge。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

$router               = \WPMind\Routing\IntelligentRouter::instance();
$routing_status       = $router->get_status_summary();
$current_strategy     = $router->get_current_strategy();
$available_strategies = $router->get_available_strategies();

$provider_scores  = $routing_status['provider_scores'] ?? [];
$failover_chain   = $routing_status['failover_chain'] ?? [];
$recommended      = $routing_status['recommended'] ?? '';
$manual_priority  = $router->get_manual_priority();
$has_manual       = ! empty( $manual_priority );

$recommended_name  = $recommended && isset( $provider_scores[ $recommended ] )
	? (string) $provider_scores[ $recommended ]['name']
	: (string) $recommended;
$recommended_score = $recommended && isset( $provider_scores[ $recommended ]['score'] )
	? (float) $provider_scores[ $recommended ]['score']
	: 0.0;

// Provider 本地图标。
$wpmind_mark = static function ( string $pid ): string {
	$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
	if ( file_exists( $svg ) ) {
		return '<img class="wpmind-mini-logo" src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg' ) . '" alt="" width="14" height="14">';
	}
	return AdminIcons::svg( 'robot' );
};

wpmind_page_head(
	__( '智能路由', 'wpmind' ),
	__( '策略、优先级、故障转移一页管。状态随操作实时刷新，熔断中的 Provider 自动跳过。', 'wpmind' )
);
?>

<div class="wenpai-subnav">
	<div class="seg">
		<a href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'services' ] ) ); ?>"><?php esc_html_e( 'Provider 服务', 'wpmind' ); ?></a>
		<a class="on" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'routing' ] ) ); ?>"><?php esc_html_e( '智能路由', 'wpmind' ); ?></a>
	</div>
</div>

<div class="wenpai-stack wpmind-routing-panel">

	<div class="wpmind-route-bar">
		<span class="wpmind-route-bar-label"><?php esc_html_e( '路由策略', 'wpmind' ); ?></span>
		<div class="seg wpmind-strategy-seg" role="radiogroup" aria-label="<?php esc_attr_e( '路由策略', 'wpmind' ); ?>">
			<?php foreach ( $available_strategies as $strategy_name => $strategy_info ) : ?>
			<label class="wpmind-strategy-item<?php echo $current_strategy === $strategy_name ? ' is-active' : ''; ?>"
					title="<?php echo esc_attr( $strategy_info['description'] ?? '' ); ?>">
				<input type="radio" name="routing_strategy" value="<?php echo esc_attr( $strategy_name ); ?>"
					<?php checked( $current_strategy, $strategy_name ); ?>>
				<?php echo esc_html( $strategy_info['display_name'] ?? $strategy_name ); ?>
			</label>
			<?php endforeach; ?>
		</div>
		<button type="button" class="btn btn-sm btn-ghost wpmind-refresh-routing">
			<?php echo AdminIcons::svg( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?>
			<?php esc_html_e( '刷新路由状态', 'wpmind' ); ?>
		</button>
		<span class="meta"><?php esc_html_e( '点击即保存并实时生效', 'wpmind' ); ?></span>
	</div>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '运行状态', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '当前命中路由与故障转移链', 'wpmind' ); ?></p>
			</div>
		</div>

		<?php if ( '' !== $recommended_name ) : ?>
		<div class="simple-row is-static">
			<div class="tile ok"><?php echo AdminIcons::svg( 'route' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></div>
			<div>
				<div class="t"><?php echo $wpmind_mark( $recommended ); ?><?php echo esc_html( $recommended_name ); ?></div>
				<div class="d"><?php esc_html_e( '综合得分', 'wpmind' ); ?> <span class="wpmind-routing-status-score-value"><?php echo esc_html( number_format( $recommended_score, 1 ) ); ?></span></div>
			</div>
			<div class="r"><span class="pill ok"><?php esc_html_e( '正在使用', 'wpmind' ); ?></span></div>
		</div>
		<?php else : ?>
		<p class="meta"><?php esc_html_e( '暂无可用 Provider，先到「Provider 服务」配置端点。', 'wpmind' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $failover_chain ) ) : ?>
		<h3 class="wpmind-sub"><?php esc_html_e( '故障转移链', 'wpmind' ); ?></h3>
		<div class="wpmind-feed" id="wpmind-failover-chain">
			<?php foreach ( $failover_chain as $index => $provider ) : ?>
			<?php
			$wpmind_pid   = (string) $provider;
			$wpmind_pname = isset( $provider_scores[ $wpmind_pid ]['name'] )
				? (string) $provider_scores[ $wpmind_pid ]['name']
				: $wpmind_pid;
			?>
			<div class="wpmind-feed-item">
				<span class="wpmind-feed-time"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
				<span class="wpmind-feed-main">
					<span class="wpmind-feed-provider"><?php echo $wpmind_mark( $wpmind_pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $wpmind_pname ); ?></span>
				</span>
				<span class="wpmind-feed-meta">
					<?php if ( 0 === $index ) : ?><span class="pill ok"><?php esc_html_e( '主', 'wpmind' ); ?></span><?php endif; ?>
				</span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="card-foot">
			<span class="meta"><?php esc_html_e( '请求超时与默认服务在「Provider 服务」页配置。', 'wpmind' ); ?></span>
		</div>
	</section>

	<section class="card wpmind-routing-priority">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '优先级与排名', 'wpmind' ); ?></h2>
				<p class="card-sub wpmind-routing-section-desc">
					<?php esc_html_e( '拖拽调整 Provider 顺序，排在前面的优先使用；下方为策略实时得分排名。', 'wpmind' ); ?>
					<?php if ( $has_manual ) : ?>
					<span class="wpmind-priority-badge pill mute"><?php esc_html_e( '手动优先级已启用', 'wpmind' ); ?></span>
					<?php endif; ?>
				</p>
			</div>
			<span class="wenpai-actions wpmind-routing-priority-actions">
				<?php if ( $has_manual ) : ?>
				<button type="button" class="btn btn-sm btn-ghost wpmind-clear-priority"><?php esc_html_e( '清除优先级', 'wpmind' ); ?></button>
				<?php endif; ?>
				<button type="button" class="btn btn-sm btn-primary wpmind-save-priority"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
			</span>
		</div>

		<?php if ( ! empty( $provider_scores ) ) : ?>
		<div class="wpmind-priority" id="wpmind-priority-list">
			<?php
			$display_order = $has_manual ? $manual_priority : array_keys( $provider_scores );
			$index         = 1;
			foreach ( $display_order as $provider_id ) :
				$score_data = $provider_scores[ $provider_id ] ?? null;
				if ( ! $score_data ) {
					continue;
				}
				?>
			<div class="wpmind-priority-item" data-provider="<?php echo esc_attr( $provider_id ); ?>">
				<span class="wpmind-priority-handle"><?php echo AdminIcons::svg( 'grip' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
				<span class="wpmind-priority-index"><?php echo esc_html( (string) $index++ ); ?></span>
				<span class="wpmind-priority-name"><?php echo esc_html( $score_data['name'] ); ?></span>
				<span class="wpmind-priority-score"><?php echo esc_html( number_format( (float) $score_data['score'], 1 ) ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>

		<h3 class="wpmind-sub"><?php esc_html_e( '实时排名', 'wpmind' ); ?></h3>
		<div class="wpmind-score" id="wpmind-routing-scores">
			<?php foreach ( $provider_scores as $provider_id => $score_data ) : ?>
			<div class="wpmind-score-row<?php echo 1 === (int) ( $score_data['rank'] ?? 0 ) ? ' is-top' : ''; ?>">
				<span class="wpmind-score-rank"><?php echo esc_html( (string) ( $score_data['rank'] ?? '–' ) ); ?></span>
				<span class="wpmind-score-name"><?php echo esc_html( $score_data['name'] ); ?></span>
				<span class="wpmind-score-bar"><span class="wpmind-score-fill" style="width: <?php echo esc_attr( (string) ( $score_data['score'] ?? 0 ) ); ?>%;"></span></span>
				<span class="wpmind-score-value"><?php echo esc_html( number_format( (float) ( $score_data['score'] ?? 0 ), 1 ) ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
		<p class="meta"><?php esc_html_e( '暂无 Provider 得分数据，产生路由请求后按得分显示排名。', 'wpmind' ); ?></p>
		<?php endif; ?>
	</section>

</div>
