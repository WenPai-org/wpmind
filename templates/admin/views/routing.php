<?php
/**
 * 智能路由视图（kit 化，AI 服务二级页）：subpage_head + seg_radio + sortable + meter。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;

( static function (): void {
	$router               = \WPMind\Routing\IntelligentRouter::instance();
	$routing_status       = $router->get_status_summary();
	$current_strategy     = $router->get_current_strategy();
	$available_strategies = $router->get_available_strategies();

	$provider_scores = $routing_status['provider_scores'] ?? [];
	$failover_chain  = $routing_status['failover_chain'] ?? [];
	$recommended     = (string) ( $routing_status['recommended'] ?? '' );
	$manual_priority = $router->get_manual_priority();
	$has_manual      = ! empty( $manual_priority );

	$recommended_name  = '' !== $recommended && isset( $provider_scores[ $recommended ] )
	? (string) $provider_scores[ $recommended ]['name']
	: $recommended;
	$recommended_score = '' !== $recommended && isset( $provider_scores[ $recommended ]['score'] )
	? (float) $provider_scores[ $recommended ]['score']
	: 0.0;

	$strategy_items = [];
	foreach ( $available_strategies as $strategy_name => $strategy_info ) {
		$strategy_items[ (string) $strategy_name ] = (string) ( $strategy_info['display_name'] ?? $strategy_name );
	}

	// subpage_head 放在 .wenpai-stack 外侧，与 1120px 顶栏和页脚严格对齐.
	Wenpai_Admin_Shell::subpage_head(
		[
			'title'      => __( '智能路由', 'wpmind' ),
			'back_url'   => AdminPage::url( [ 'tab' => 'services' ] ),
			'back_label' => __( '返回 AI 服务', 'wpmind' ),
			'lede'       => __( '按策略与优先级分发并自动转移。', 'wpmind' ),
			'aside_html' => Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '刷新路由状态', 'wpmind' ),
					'variant' => 'ghost',
					'size'    => 'sm',
					'class'   => 'wpmind-refresh-routing',
				]
			),
		]
	);
	?>

<div class="wenpai-stack wpmind-routing-panel">
	<?php
	// ---- 卡 1：路由策略与故障转移链 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '路由策略与状态', 'wpmind' ),
			'tile'  => false,
		]
	);
	?>
	<div class="wenpai-field wenpai-field--row field">
		<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '路由策略', 'wpmind' ); ?></span></div>
		<div class="field-ctl">
			<?php
			Wenpai_Admin_UI::seg_radio(
				[
					'name'  => 'routing_strategy',
					'value' => $current_strategy,
					'items' => $strategy_items,
					'label' => __( '路由策略', 'wpmind' ),
				]
			);
			?>
			<p class="wenpai-field__desc"><?php esc_html_e( '切换策略后立即保存并按新规则计算评分。', 'wpmind' ); ?></p>
		</div>
	</div>

	<?php
	if ( '' !== $recommended_name ) {
		Wenpai_Admin_UI::row(
			[
				'icon'       => 'flash',
				'state'      => 'ok',
				'title_html' => '<span id="wpmind-recommended-provider">' . esc_html( $recommended_name ) . '</span>',
				'desc_html'  => esc_html__( '当前首选 · 综合得分 ', 'wpmind' )
					. '<span class="wpmind-routing-status-score-value">' . esc_html( number_format( $recommended_score, 1 ) ) . '</span>',
				'pill'       => [
					'text'  => __( '正在使用', 'wpmind' ),
					'state' => 'ok',
				],
			]
		);
	} else {
		Wenpai_Admin_UI::empty_state(
			[
				'text'   => __( '还没有可用的 AI 服务商', 'wpmind' ),
				'meta'   => __( '先到 AI 服务页开启官方算力或配置至少一个密钥。', 'wpmind' ),
				'action' => [
					'label'   => __( '配置 AI 服务', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'services' ] ),
					'variant' => 'primary',
				],
			]
		);
	}

	if ( ! empty( $failover_chain ) ) {
		Wenpai_Admin_UI::section_title( [ 'title' => __( '故障转移链', 'wpmind' ) ] );
		$chain_items = [];
		foreach ( $failover_chain as $idx => $provider ) {
			$pid           = (string) $provider;
			$pname         = isset( $provider_scores[ $pid ]['name'] ) ? (string) $provider_scores[ $pid ]['name'] : $pid;
			$chain_items[] = [
				'tag'   => 0 === $idx ? __( '主通道', 'wpmind' ) : sprintf( /* translators: %d: fallback order */ __( '备选 %d', 'wpmind' ), $idx ),
				'tone'  => 0 === $idx ? 'ok' : '',
				'title' => $pname,
				'when'  => '#' . ( $idx + 1 ),
			];
		}
		Wenpai_Admin_UI::tag_feed(
			[
				'items' => $chain_items,
				'attrs' => [ 'id' => 'wpmind-failover-chain' ],
			]
		);
	}

	Wenpai_Admin_UI::card_foot(
		[
			'start' => true,
			'meta'  => __( '熔断中的服务商会自动从转移链跳过，恢复后自动回切。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 卡 2：优先级排序与得分排名 ----
	$priority_actions_html = '<span class="wenpai-actions wpmind-routing-priority-actions">';
	if ( $has_manual ) {
		$priority_actions_html .= '<button type="button" class="btn btn-sm btn-ghost wpmind-clear-priority"'
			. wpmind_confirm_attrs(
				__( '清除后将恢复由当前路由策略自动排序。', 'wpmind' ),
				[
					'title'    => __( '清除手动优先级', 'wpmind' ),
					'ok_label' => __( '确认清除', 'wpmind' ),
				]
			)
			. '>' . esc_html__( '清除优先级', 'wpmind' ) . '</button>';
	}
	$priority_actions_html .= Wenpai_Admin_UI::button_html(
		[
			'label'   => __( '保存优先级', 'wpmind' ),
			'variant' => 'primary',
			'size'    => 'sm',
			'class'   => 'wpmind-save-priority',
		]
	);
	$priority_actions_html .= '</span>';

	$priority_sub_html = esc_html__( '调整上下顺序固定优先通道；留空时按策略得分排序。', 'wpmind' );
	if ( $has_manual ) {
		$priority_sub_html .= ' ' . Wenpai_Admin_UI::pill_html(
			[
				'text'  => __( '手动优先级生效中', 'wpmind' ),
				'state' => 'mute',
				'class' => 'wpmind-priority-badge',
			]
		);
	}

	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '优先级与得分排名', 'wpmind' ),
			'sub_html'          => $priority_sub_html,
			'tile'              => false,
			'class'             => 'wpmind-routing-priority',
			'head_control_html' => $priority_actions_html,
		]
	);

	if ( ! empty( $provider_scores ) ) {
		$display_order  = $has_manual ? $manual_priority : array_keys( $provider_scores );
		$sortable_items = [];
		foreach ( $display_order as $provider_id ) {
			$score_data = $provider_scores[ $provider_id ] ?? null;
			if ( ! is_array( $score_data ) ) {
				continue;
			}
			$sortable_items[] = [
				'id'    => (string) $provider_id,
				'title' => (string) ( $score_data['name'] ?? $provider_id ),
				'desc'  => sprintf(
					/* translators: %s: formatted provider score */
					__( '当前综合得分 %s', 'wpmind' ),
					number_format( (float) ( $score_data['score'] ?? 0 ), 1 )
				),
			];
		}

		Wenpai_Admin_UI::sortable(
			[
				'name'  => 'wpmind_provider_priority',
				'id'    => 'wpmind-priority-order',
				'items' => $sortable_items,
				'attrs' => [ 'id' => 'wpmind-priority-list' ],
			]
		);

		Wenpai_Admin_UI::section_title( [ 'title' => __( '实时得分排名', 'wpmind' ) ] );

		$meter_items = [];
		foreach ( $provider_scores as $provider_id => $score_data ) {
			$score         = (float) ( $score_data['score'] ?? 0 );
			$meter_items[] = [
				'name'    => (string) ( $score_data['name'] ?? $provider_id ),
				'value'   => number_format( $score, 1 ),
				'percent' => $score,
				'top'     => 1 === (int) ( $score_data['rank'] ?? 0 ),
			];
		}

		Wenpai_Admin_UI::meter(
			[
				'ranked' => true,
				'items'  => $meter_items,
				'attrs'  => [ 'id' => 'wpmind-routing-scores' ],
			]
		);
	} else {
		Wenpai_Admin_UI::empty_state(
			[
				'text' => __( '还没有服务商评分记录', 'wpmind' ),
				'meta' => __( '配置并产生 AI 请求后，这里会自动展示各通道得分排名。', 'wpmind' ),
			]
		);
	}

	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
