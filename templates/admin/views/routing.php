<?php
/**
 * 智能路由视图（按 2026-11-16 过审原型 routing.html：页头 + subnav + 策略条 + 路由参数 + 运行状态 + 优先级与排名）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Failover\FailoverManager;
use WPMind\Modules\CostControl\UsageTracker;
use WPMind\Provider\EndpointRepository;

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

	$wpmind_instance  = \WPMind\WPMind::instance();
	$endpoints        = $wpmind_instance->get_custom_endpoints();
	$request_timeout  = (int) get_option( 'wpmind_request_timeout', 60 );
	$default_provider = (string) get_option( 'wpmind_default_provider', '' );
	$endpoints_map    = class_exists( EndpointRepository::class ) ? ( new EndpointRepository() )->all() : [];
	$breaker_summary  = class_exists( FailoverManager::class ) ? FailoverManager::instance()->get_status_summary() : [];

	$plugin_dir = defined( 'WPMIND_PLUGIN_DIR' ) ? (string) WPMIND_PLUGIN_DIR : dirname( __DIR__, 3 ) . '/';
	$plugin_url = defined( 'WPMIND_PLUGIN_URL' ) ? (string) WPMIND_PLUGIN_URL : '';

	$provider_logo = static function ( string $pid ) use ( $plugin_dir, $plugin_url ): string {
		$safe = sanitize_key( $pid );
		if ( '' !== $safe && '' !== $plugin_url && file_exists( $plugin_dir . 'assets/images/providers/' . $safe . '.svg' ) ) {
			return '<img class="wpmind-logo" src="' . esc_url( $plugin_url . 'assets/images/providers/' . $safe . '.svg' ) . '" alt="" width="14" height="14">';
		}
		return AdminIcons::svg( 'wenpai_cloud' === $pid ? 'cloud' : 'robot' );
	};

	$provider_name = static function ( string $pid ) use ( $provider_scores ): string {
		if ( class_exists( UsageTracker::class ) ) {
			$loc = UsageTracker::get_provider_display_name( $pid );
			if ( $loc !== $pid ) {
				return $loc;
			}
		}
		return isset( $provider_scores[ $pid ]['name'] ) ? (string) $provider_scores[ $pid ]['name'] : $pid;
	};

	$recommended_name  = '' !== $recommended ? $provider_name( $recommended ) : '';
	$recommended_score = '' !== $recommended && isset( $provider_scores[ $recommended ]['score'] )
		? (float) $provider_scores[ $recommended ]['score']
		: 0.0;
	$recommended_model = '';
	if ( '' !== $recommended && isset( $endpoints_map[ $recommended ] ) ) {
		$ep                = $endpoints_map[ $recommended ];
		$recommended_model = '' !== $ep->default_model ? $ep->default_model : (string) ( $ep->models[0] ?? '' );
	}

	$strategy_items = [];
	$strategy_desc  = '';
	foreach ( $available_strategies as $strategy_name => $strategy_info ) {
		$sname                    = (string) $strategy_name;
		$strategy_items[ $sname ] = (string) ( $strategy_info['display_name'] ?? $sname );
		if ( $sname === $current_strategy && ! empty( $strategy_info['description'] ) ) {
			$strategy_desc = (string) $strategy_info['description'];
		}
	}
	if ( '' === $strategy_desc ) {
		$strategy_desc = __( '按当前策略自动计算权重；熔断中的服务会自动跳过。', 'wpmind' );
	}

	$status_pill = Wenpai_Admin_UI::pill_html(
		[
			'text'  => '' !== $recommended_name ? __( '运行中', 'wpmind' ) : __( '待配置', 'wpmind' ),
			'state' => '' !== $recommended_name ? 'ok' : 'warn',
		]
	);

	wpmind_page_head(
		__( '智能路由', 'wpmind' ),
		__( '策略、优先级、故障转移一页管。状态自动更新，熔断 Provider 自动跳过。', 'wpmind' ),
		$status_pill
	);

	Wenpai_Admin_UI::subnav(
		[
			'label' => __( 'AI 服务子导航', 'wpmind' ),
			'items' => [
				[
					'label'   => __( 'Provider 服务', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'services' ] ),
					'current' => false,
				],
				[
					'label'   => __( '智能路由', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'routing' ] ),
					'current' => true,
				],
			],
		]
	);
	?>

<div class="wpmind-routing-panel">
	<div class="wpmind-rt__bar">
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
		<span class="wpmind-rt__bar-label"><?php esc_html_e( '路由策略', 'wpmind' ); ?></span>
		<span class="meta"><?php echo esc_html( $strategy_desc ); ?></span>
	</div>

	<div class="wenpai-stack">
		<?php // ---- 卡 1：路由参数 ---- ?>
		<form method="post" action="options.php" id="wpmind-routing-params-form">
			<?php
			settings_fields( 'wpmind_routing_settings' );
			Wenpai_Admin_UI::card_open(
				[
					'title' => __( '路由参数', 'wpmind' ),
					'sub'   => __( '超时时间与默认兜底服务', 'wpmind' ),
					'tile'  => false,
				]
			);
			?>
			<div class="wenpai-field wenpai-field--row field">
				<div class="field-label"><label class="wenpai-field__label" for="wpmind_request_timeout"><?php esc_html_e( '请求超时', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<input type="number" class="wpmind-num" id="wpmind_request_timeout" name="wpmind_request_timeout" value="<?php echo esc_attr( (string) $request_timeout ); ?>" min="10" max="300" step="1">
					<p class="wenpai-field__desc"><?php esc_html_e( '单次上游请求超时秒数（建议 60–120），超时自动故障转移。', 'wpmind' ); ?></p>
				</div>
			</div>

			<div class="wenpai-field wenpai-field--row field">
				<div class="field-label"><label class="wenpai-field__label" for="wpmind_default_provider"><?php esc_html_e( '默认服务', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<select id="wpmind_default_provider" name="wpmind_default_provider">
						<option value=""><?php esc_html_e( '— 自动按路由选择 —', 'wpmind' ); ?></option>
						<?php foreach ( $endpoints as $ep_key => $ep_info ) : ?>
							<option value="<?php echo esc_attr( (string) $ep_key ); ?>" <?php selected( $default_provider, $ep_key ); ?>><?php echo esc_html( (string) ( $ep_info['name'] ?? $ep_key ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="wenpai-field__desc"><?php esc_html_e( '未命中策略排序时的兜底 Provider。', 'wpmind' ); ?></p>
				</div>
			</div>
			<?php
			Wenpai_Admin_UI::card_foot(
				[
					'start'        => true,
					'meta'         => __( '熔断器阈值：连续失败 5 次 / 恢复探测 60 秒。', 'wpmind' ),
					'actions_html' => '<span class="wenpai-actions">'
						. Wenpai_Admin_UI::button_html(
							[
								'label'   => __( '刷新路由状态', 'wpmind' ),
								'variant' => 'secondary',
								'class'   => 'wpmind-refresh-routing',
							]
						)
						. Wenpai_Admin_UI::button_html(
							[
								'label'   => __( '保存参数', 'wpmind' ),
								'type'    => 'submit',
								'name'    => 'submit',
								'variant' => 'primary',
							]
						)
						. '</span>',
				]
			);
			Wenpai_Admin_UI::card_close();
			?>
		</form>

		<?php
		// ---- 卡 2：运行状态（当前路由 + 故障转移链） ----
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '运行状态', 'wpmind' ),
				'sub'   => __( '当前命中路由与故障转移链', 'wpmind' ),
				'tile'  => false,
			]
		);
		?>
		<h3 class="wpmind-rt__sub"><?php esc_html_e( '当前路由', 'wpmind' ); ?></h3>
		<?php
		if ( '' !== $recommended_name ) {
			$rec_title_html = $provider_logo( $recommended )
				. '<span id="wpmind-recommended-provider">' . esc_html( $recommended_name ) . '</span>';
			if ( '' !== $recommended_model ) {
				$rec_title_html .= ' ' . Wenpai_Admin_UI::pill_html(
					[
						'text'  => $recommended_model,
						'state' => 'mute',
					]
				);
			}
			Wenpai_Admin_UI::row(
				[
					'icon_html'  => AdminIcons::svg( 'route' ),
					'state'      => 'ok',
					'title_html' => $rec_title_html,
					'desc_html'  => esc_html__( '综合得分 ', 'wpmind' )
						. '<span class="wpmind-routing-status-score-value">' . esc_html( number_format( $recommended_score, 1 ) ) . '</span>',
					'pill'       => [
						'text'  => __( '主路由', 'wpmind' ),
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

		if ( ! empty( $failover_chain ) ) :
			?>
			<h3 class="wpmind-rt__sub wpmind-rt__sub--gap"><?php esc_html_e( '故障转移链', 'wpmind' ); ?></h3>
			<div class="wpmind-rt__chain" id="wpmind-failover-chain">
				<?php foreach ( $failover_chain as $idx => $provider ) : ?>
					<?php
					$pid      = (string) $provider;
					$pname    = $provider_name( $pid );
					$cb_state = (string) ( $breaker_summary[ $pid ]['state'] ?? 'closed' );
					$side_str = '';
					if ( 'open' === $cb_state ) {
						$side_str = Wenpai_Admin_UI::pill_html(
							[
								'text'  => __( '熔断中', 'wpmind' ),
								'state' => 'bad',
							]
						);
					} elseif ( 'half_open' === $cb_state ) {
						$side_str = Wenpai_Admin_UI::pill_html(
							[
								'text'  => __( '恢复中', 'wpmind' ),
								'state' => 'warn',
							]
						);
					} elseif ( 0 === $idx ) {
						$side_str = Wenpai_Admin_UI::pill_html(
							[
								'text'  => __( '主', 'wpmind' ),
								'state' => 'ok',
							]
						);
					}
					?>
					<div class="wpmind-rt__citem">
						<span class="wpmind-rt__cnum"><?php echo esc_html( (string) ( $idx + 1 ) ); ?></span>
						<span class="wpmind-rt__cname"><?php echo $provider_logo( $pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped img/SVG. ?><?php echo esc_html( $pname ); ?></span>
						<?php if ( '' !== $side_str ) : ?>
							<span class="wpmind-rt__cside"><?php echo $side_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped. ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		endif;

		Wenpai_Admin_UI::card_foot(
			[
				'start' => true,
				'meta'  => __( '熔断中的服务商会自动从转移链跳过，恢复后自动回切。', 'wpmind' ),
			]
		);
		Wenpai_Admin_UI::card_close();

		// ---- 卡 3：优先级与排名 ----
		$priority_head_pill = $has_manual
			? Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '手动优先级已启用', 'wpmind' ),
					'state' => 'mute',
					'class' => 'wpmind-priority-badge',
				]
			)
			: '';

		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '优先级与排名', 'wpmind' ),
				'sub'               => __( '拖拽调整手动优先级；条形为策略得分，越高越优先', 'wpmind' ),
				'tile'              => false,
				'class'             => 'wpmind-routing-priority',
				'head_actions_html' => $priority_head_pill,
			]
		);

	if ( ! empty( $provider_scores ) ) {
		$display_order  = $has_manual ? $manual_priority : array_keys( $provider_scores );
		$sortable_items = [];
		foreach ( $display_order as $provider_id ) {
			$pid        = (string) $provider_id;
			$score_data = $provider_scores[ $pid ] ?? null;
			if ( ! is_array( $score_data ) ) {
				continue;
			}
			$sortable_items[] = [
				'id'    => $pid,
				'title' => $provider_name( $pid ),
				'desc'  => sprintf(
					/* translators: %s: formatted provider score */
					__( '策略得分 %s', 'wpmind' ),
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

		$meter_items = [];
		foreach ( $provider_scores as $provider_id => $score_data ) {
			$pid           = (string) $provider_id;
			$score         = (float) ( $score_data['score'] ?? 0 );
			$meter_items[] = [
				'name'    => $provider_name( $pid ),
				'value'   => number_format( $score, 1 ),
				'percent' => $score,
				'top'     => 1 === (int) ( $score_data['rank'] ?? 0 ),
			];
		}
		echo '<div class="wpmind-rt__scores">';
		Wenpai_Admin_UI::meter(
			[
				'ranked' => true,
				'items'  => $meter_items,
				'attrs'  => [ 'id' => 'wpmind-routing-scores' ],
			]
		);
		echo '</div>';
	} else {
		Wenpai_Admin_UI::empty_state(
			[
				'text' => __( '还没有服务商评分记录', 'wpmind' ),
				'meta' => __( '配置并产生 AI 请求后，这里会自动展示各通道得分排名。', 'wpmind' ),
			]
		);
	}

		$priority_foot_actions = '<span class="wenpai-actions wpmind-routing-priority-actions">';
	if ( $has_manual ) {
		$priority_foot_actions .= '<button type="button" class="btn btn-ghost wpmind-clear-priority"'
			. wpmind_confirm_attrs(
				__( '清除后将恢复由当前路由策略自动排序。', 'wpmind' ),
				[
					'title'    => __( '清除手动优先级', 'wpmind' ),
					'ok_label' => __( '确认清除', 'wpmind' ),
				]
			)
			. '>' . esc_html__( '清除优先级', 'wpmind' ) . '</button>';
	}
		$priority_foot_actions .= Wenpai_Admin_UI::button_html(
			[
				'label'   => __( '保存设置', 'wpmind' ),
				'variant' => 'primary',
				'class'   => 'wpmind-save-priority',
			]
		);
		$priority_foot_actions .= '</span>';

		Wenpai_Admin_UI::card_foot(
			[
				'start'        => true,
				'meta'         => __( '未配置 Key 的 Provider 不参与排序。', 'wpmind' ),
				'actions_html' => $priority_foot_actions,
			]
		);
		Wenpai_Admin_UI::card_close();
	?>
	</div>
</div>
	<?php
} )();
