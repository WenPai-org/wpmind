<?php
/**
 * 概览视图（kit v0.13.7 四段式）：drawer_toggle + stat_hub + 等高双栏 view_tabs/view_panes + fold_list。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Core\ModuleLoader;
use WPMind\Failover\FailoverManager;
use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\UsageTracker;

( static function (): void {
	// ---- 数据准备（纯本地读，render 期零远程请求） ----
	$today        = class_exists( UsageTracker::class ) ? UsageTracker::get_today_stats() : [];
	$month        = class_exists( UsageTracker::class ) ? UsageTracker::get_month_stats() : [];
	$providers    = class_exists( FailoverManager::class ) ? FailoverManager::instance()->get_status_summary() : [];
	$modules      = class_exists( ModuleLoader::class ) ? ModuleLoader::instance()->get_modules() : [];
	$active_count = count( array_filter( $modules, static fn( $m ) => ! empty( $m['enabled'] ) ) );
	$cloud        = SuiteIntegration::cloud_status();
	$cloud_on     = ! empty( $cloud['enabled'] );
	$cloud_ready  = $cloud_on && ! empty( $cloud['bound'] );

	$services_url  = AdminPage::url( [ 'tab' => 'services' ] );
	$routing_url   = AdminPage::url( [ 'tab' => 'routing' ] );
	$analytics_url = AdminPage::url( [ 'tab' => 'analytics' ] );
	$modules_url   = AdminPage::url( [ 'tab' => 'modules' ] );

	$healthy = 0;
	$down    = 0;
	$testing = 0;
	foreach ( $providers as $p ) {
		$state = (string) ( $p['state'] ?? 'unknown' );
		if ( 'healthy' === $state || 'closed' === $state ) {
			++$healthy;
		} elseif ( 'half_open' === $state ) {
			++$testing;
		} else {
			++$down;
		}
	}
	$total_providers = count( $providers );

	$recent_history = class_exists( UsageTracker::class ) ? UsageTracker::get_history( 20 ) : [];

	// 统计近 20 次请求中各服务商平均延迟（ms）.
	$latency_acc = [];
	foreach ( $recent_history as $rec ) {
		$r_pid = (string) ( $rec['provider'] ?? '' );
		$r_ms  = (int) ( $rec['latency_ms'] ?? 0 );
		if ( '' !== $r_pid && $r_ms > 0 ) {
			if ( ! isset( $latency_acc[ $r_pid ] ) ) {
				$latency_acc[ $r_pid ] = [
					'sum' => 0,
					'cnt' => 0,
				];
			}
			$latency_acc[ $r_pid ]['sum'] += $r_ms;
			++$latency_acc[ $r_pid ]['cnt'];
		}
	}

	$cache_stats    = function_exists( 'wpmind_get_cache_stats' )
	? wpmind_get_cache_stats()
	: [
		'enabled'  => false,
		'hits'     => 0,
		'misses'   => 0,
		'hit_rate' => 0,
	];
	$cache_enabled  = ! empty( $cache_stats['enabled'] );
	$cache_hit_rate = isset( $cache_stats['hit_rate'] ) ? (float) $cache_stats['hit_rate'] : 0.0;
	$cache_total    = (int) ( $cache_stats['hits'] ?? 0 ) + (int) ( $cache_stats['misses'] ?? 0 );

	$unconfigured = 0 === $total_providers && ! $cloud_on;
	$has_issue    = $unconfigured || $down > 0 || $testing > 0;

	// 页头 lede（≤ 24 字）.
	if ( $unconfigured ) {
		$lede = __( '尚未配置服务，请先连接算力或密钥。', 'wpmind' );
	} elseif ( $down > 0 ) {
		$lede = sprintf(
		/* translators: %d: circuit-open provider count */
			__( '%d 项服务熔断中，已自动切换备选。', 'wpmind' ),
			$down
		);
	} elseif ( $testing > 0 ) {
		$lede = sprintf(
		/* translators: %d: half-open provider count */
			__( '%d 项服务正在自动恢复探测。', 'wpmind' ),
			$testing
		);
	} else {
		$online_cnt = max( 1, $healthy + ( $cloud_ready ? 1 : 0 ) );
		$lede       = sprintf(
		/* translators: 1: online service count, 2: active module count */
			__( '%1$d 项服务在线 · %2$d 个模块运行中', 'wpmind' ),
			$online_cnt,
			$active_count
		);
	}

	$head_actions = '<div class="wenpai-row wenpai-row--tight">'
	. Wenpai_Admin_UI::button_html(
		[
			'label'   => __( '智能路由', 'wpmind' ),
			'href'    => $routing_url,
			'variant' => 'ghost',
		]
	)
	. Wenpai_Admin_UI::button_html(
		[
			'label'   => $unconfigured ? __( '配置 AI 服务', 'wpmind' ) : __( '管理服务', 'wpmind' ),
			'href'    => $services_url,
			'variant' => $unconfigured ? 'primary' : 'secondary',
		]
	)
	. '</div>';

	wpmind_page_head(
		__( '概览', 'wpmind' ),
		$lede,
		$head_actions,
		Wenpai_Admin_UI::drawer_toggle_html(
			[
				'target'     => 'wpmind-provider-drawer',
				'label'      => __( '通道状态', 'wpmind' ),
				'open_label' => __( '收起通道', 'wpmind' ),
			]
		)
	);

	// ---- Row 1: stat_hub 统计与通道抽屉 ----
	$today_req    = (int) ( $today['requests'] ?? 0 );
	$today_tokens = (int) ( $today['input_tokens'] ?? 0 ) + (int) ( $today['output_tokens'] ?? 0 );
	$unit_label   = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );

	$stat_third = $cloud_on
	? [
		'label' => $unit_label,
		'value' => null !== $cloud['credits_balance'] ? number_format_i18n( (int) $cloud['credits_balance'] ) : '—',
		'unit'  => ! empty( $cloud['bound'] ) ? __( '剩余', 'wpmind' ) : __( '待绑定', 'wpmind' ),
	]
	: [
		'label' => __( '本月费用', 'wpmind' ),
		'value' => class_exists( UsageTracker::class )
			? UsageTracker::format_cost_by_currency( $month['cost_usd'] ?? 0, $month['cost_cny'] ?? 0 )
			: '$0.00',
	];

	$channel_cells = [];
	if ( $cloud_on ) {
		$channel_cells[] = [
			'label' => __( '文派官方算力', 'wpmind' ),
			'value' => $cloud_ready ? __( '已绑定 · 运行正常', 'wpmind' ) : __( '已开启 · 待绑定站点', 'wpmind' ),
			'tone'  => $cloud_ready ? '' : 'warn',
		];
	}
	foreach ( $providers as $pid => $p ) {
		$state           = (string) ( $p['state'] ?? 'unknown' );
		$pname           = (string) ( $p['display_name'] ?? ( class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( (string) $pid ) : $pid ) );
		$tone            = 'open' === $state ? 'bad' : ( 'half_open' === $state ? 'warn' : '' );
		$val             = 'open' === $state
		? __( '熔断中 · 自动转移', 'wpmind' )
		: ( 'half_open' === $state
			? __( '半开恢复探测中', 'wpmind' )
			: ( isset( $p['success_rate'] )
				? sprintf( /* translators: %d: success rate */ __( '正常 · 成功率 %d%%', 'wpmind' ), (int) $p['success_rate'] )
				: __( '正常在线', 'wpmind' ) ) );
		$channel_cells[] = [
			'label' => $pname,
			'value' => $val,
			'tone'  => $tone,
		];
		if ( count( $channel_cells ) >= 4 ) {
			break;
		}
	}
	if ( empty( $channel_cells ) ) {
		$channel_cells[] = [
			'label' => __( 'AI 服务通道', 'wpmind' ),
			'value' => __( '未配置 · 前往 AI 服务页添加', 'wpmind' ),
			'tone'  => 'warn',
		];
	}

	$alert_text = '';
	if ( $unconfigured ) {
		$alert_text = __( '尚未配置 AI 服务商或官方算力，当前无法发起调用', 'wpmind' );
	} elseif ( $down > 0 ) {
		$alert_text = sprintf(
		/* translators: %d: circuit-open provider count */
			__( '%d 项 AI 服务熔断中，系统已自动转移至备选通道', 'wpmind' ),
			$down
		);
	} elseif ( $testing > 0 ) {
		$alert_text = sprintf(
		/* translators: %d: half-open provider count */
			__( '%d 项 AI 服务处于半开恢复探测中', 'wpmind' ),
			$testing
		);
	}

	// ---- Row 2 左卡：各插件调用 / 近期活动 ----
	$caller_labels = [
		'wpslug'      => __( '文派素格', 'wpmind' ),
		'bisheng'     => __( '毕昇写作', 'wpmind' ),
		'wpicp'       => __( '文派备案', 'wpmind' ),
		'wpdocs'      => __( '文派文档', 'wpmind' ),
		'wpsss'       => __( '文派智储', 'wpmind' ),
		'api-gateway' => __( 'API 网关', 'wpmind' ),
		'wpmind'      => __( '文派心思', 'wpmind' ),
	];

	// @phpstan-ignore function.alreadyNarrowedType
	$context_raw  = ( class_exists( UsageTracker::class ) && method_exists( UsageTracker::class, 'get_context_stats' ) )
	? UsageTracker::get_context_stats()
	: [];
	$plugin_items = [];
	foreach ( $context_raw as $ctx_key => $ctx_row ) {
		$caller = sanitize_key( (string) ( $ctx_row['caller_plugin'] ?? $ctx_key ) );
		$tag    = $caller_labels[ $caller ] ?? ( '' !== $caller ? $caller : __( '站内调用', 'wpmind' ) );
		$reqs   = (int) ( $ctx_row['requests'] ?? $ctx_row['request_count'] ?? 0 );
		$toks   = (int) ( $ctx_row['input_tokens'] ?? 0 ) + (int) ( $ctx_row['output_tokens'] ?? 0 );
		if ( 0 === $toks && isset( $ctx_row['total_tokens'] ) ) {
			$toks = (int) $ctx_row['total_tokens'];
		}
		$c_usd          = (float) ( $ctx_row['cost_usd'] ?? 0 );
		$c_cny          = (float) ( $ctx_row['cost_cny'] ?? 0 );
		$when           = class_exists( UsageTracker::class ) && ( $c_usd > 0 || $c_cny > 0 )
		? UsageTracker::format_cost_by_currency( $c_usd, $c_cny )
		: sprintf( /* translators: %d: request count */ __( '%d 次', 'wpmind' ), $reqs );
		$plugin_items[] = [
			'tag'   => $tag,
			'tone'  => 'accent',
			'title' => sprintf(
				/* translators: 1: request count, 2: formatted token count */
				__( '%1$d 次调用 · %2$s Tokens', 'wpmind' ),
				$reqs,
				class_exists( UsageTracker::class ) ? UsageTracker::format_tokens( $toks ) : (string) $toks
			),
			'when'  => $when,
		];
	}

	$recent_items = [];
	foreach ( array_slice( $recent_history, 0, 10 ) as $record ) {
		$ts      = (int) ( $record['timestamp'] ?? 0 );
		$ago     = $ts > 0 ? human_time_diff( $ts, time() ) . __( '前', 'wpmind' ) : '—';
		$pid     = (string) ( $record['provider'] ?? '' );
		$pname   = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $pid ) : $pid;
		$model   = (string) ( $record['model'] ?? '—' );
		$tokens  = (int) ( $record['input_tokens'] ?? 0 ) + (int) ( $record['output_tokens'] ?? 0 );
		$latency = (int) ( $record['latency_ms'] ?? 0 );
		$note    = ( class_exists( UsageTracker::class ) ? UsageTracker::format_tokens( $tokens ) : (string) $tokens ) . ' tok'
		. ( $latency > 0 ? ' · ' . $latency . 'ms' : '' );

		$recent_items[] = [
			'tag'   => $pname,
			'tone'  => 'ok',
			'title' => $model,
			'note'  => $note,
			'when'  => $ago,
		];
	}

	// ---- Row 2 右卡：测速 metric_tiles + 额度 quota_list ----
	$speed_tiles = [];
	foreach ( $providers as $pid => $p ) {
		$state         = (string) ( $p['state'] ?? 'unknown' );
		$pname         = (string) ( $p['display_name'] ?? ( class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( (string) $pid ) : $pid ) );
		$avg           = isset( $latency_acc[ $pid ] )
		? (string) (int) round( $latency_acc[ $pid ]['sum'] / $latency_acc[ $pid ]['cnt'] )
		: '—';
		$tone          = 'open' === $state ? 'bad' : ( 'half_open' === $state ? 'warn' : '' );
		$sub           = 'open' === $state
		? __( '熔断暂停', 'wpmind' )
		: ( 'half_open' === $state ? __( '恢复探测', 'wpmind' ) : ( '—' === $avg ? __( '待调用采样', 'wpmind' ) : __( '近期平均', 'wpmind' ) ) );
		$speed_tiles[] = [
			'label' => $pname,
			'badge' => 'open' === $state ? __( '熔断', 'wpmind' ) : __( '在线', 'wpmind' ),
			'value' => $avg,
			'unit'  => '—' === $avg ? '' : 'ms',
			'sub'   => $sub,
			'tone'  => $tone,
		];
		if ( count( $speed_tiles ) >= 4 ) {
			break;
		}
	}
	$speed_count = count( $speed_tiles );
	for ( $idx = $speed_count + 1; $idx <= 4; $idx++ ) {
		$speed_tiles[] = [
			'label' => sprintf( /* translators: %d: slot number */ __( '备选通道 %d', 'wpmind' ), $idx ),
			'value' => '—',
			'sub'   => __( '未配置', 'wpmind' ),
		];
	}

	$quota_items = [ SuiteIntegration::build_quota_item() ];
	if ( class_exists( BudgetManager::class ) ) {
		$b_settings = BudgetManager::instance()->get_settings();
		$b_global   = is_array( $b_settings['global'] ?? null ) ? $b_settings['global'] : [];
		$d_usd      = (float) ( $b_global['daily_limit_usd'] ?? 0 );
		$d_cny      = (float) ( $b_global['daily_limit_cny'] ?? 0 );
		if ( $d_usd > 0 ) {
			$used_today    = (float) ( $today['cost_usd'] ?? 0 );
			$quota_items[] = [
				'name'         => __( '今日预算（USD）', 'wpmind' ),
				'usage'        => sprintf( '$%s / $%s', number_format( $used_today, 2 ), rtrim( rtrim( number_format( $d_usd, 2 ), '0' ), '.' ) ),
				'percent'      => min( 100, max( 0, (int) round( $used_today / $d_usd * 100 ) ) ),
				'action_label' => __( '调整限额', 'wpmind' ),
				'action_url'   => $analytics_url,
			];
		} elseif ( $d_cny > 0 ) {
			$used_today    = (float) ( $today['cost_cny'] ?? 0 );
			$quota_items[] = [
				'name'         => __( '今日预算（CNY）', 'wpmind' ),
				'usage'        => sprintf( '¥%s / ¥%s', number_format( $used_today, 2 ), rtrim( rtrim( number_format( $d_cny, 2 ), '0' ), '.' ) ),
				'percent'      => min( 100, max( 0, (int) round( $used_today / $d_cny * 100 ) ) ),
				'action_label' => __( '调整限额', 'wpmind' ),
				'action_url'   => $analytics_url,
			];
		}
	}
	$quota_items[] = [
		'name'  => __( '精确缓存命中', 'wpmind' ),
		'usage' => $cache_enabled
			? sprintf( /* translators: %s: hit rate percentage */ __( '命中率 %s%%', 'wpmind' ), number_format_i18n( $cache_hit_rate, 1 ) )
			: __( '未开启', 'wpmind' ),
	];
	?>

<div class="wenpai-stack">
	<?php
	// Row 1: Stat Hub + Anomaly Alert + Provider Drawer.
	Wenpai_Admin_UI::stat_hub(
		[
			'drawer_id' => 'wpmind-provider-drawer',
			'stats'     => [
				[
					'label' => __( '今日请求', 'wpmind' ),
					'value' => number_format_i18n( $today_req ),
					'unit'  => class_exists( UsageTracker::class ) ? UsageTracker::format_tokens( $today_tokens ) . ' tok' : __( '次', 'wpmind' ),
				],
				[
					'label' => __( '服务健康', 'wpmind' ),
					'value' => $healthy . '/' . $total_providers,
					'unit'  => $down > 0
						? sprintf( /* translators: %d: breakers */ __( '%d 熔断', 'wpmind' ), $down )
						: ( $total_providers > 0 || $cloud_ready ? __( '正常', 'wpmind' ) : __( '待配置', 'wpmind' ) ),
				],
				$stat_third,
				[
					'label' => __( '缓存命中', 'wpmind' ),
					'value' => $cache_enabled ? number_format_i18n( $cache_hit_rate, 1 ) . '%' : '—',
					'unit'  => $cache_enabled ? number_format_i18n( $cache_total ) . ' ' . __( '次', 'wpmind' ) : __( '未开启', 'wpmind' ),
				],
			],
			'alert'     => [
				'visible'      => $has_issue,
				'text'         => $alert_text,
				'action_label' => __( '展开查看', 'wpmind' ),
			],
			'channels'  => $channel_cells,
		]
	);

	// Row 2: Equal-height Split — Left Plugin Calls / Recent Activity | Right Speed / Quota.
	$left_tabs = Wenpai_Admin_UI::view_tabs_html(
		[
			'group'      => 'wpmind-ov-activity',
			'aria_label' => __( '各插件调用与近期活动切换', 'wpmind' ),
			'items'      => [
				[
					'id'         => 'plugins',
					'label'      => __( '各插件调用', 'wpmind' ),
					'active'     => true,
					'card_title' => __( '各插件调用', 'wpmind' ),
					'foot_meta'  => __( '按站内调用来源归因统计', 'wpmind' ),
					'foot_label' => __( '查看分析', 'wpmind' ),
					'foot_href'  => $analytics_url,
				],
				[
					'id'         => 'recent',
					'label'      => __( '近期活动', 'wpmind' ),
					'card_title' => __( '近期活动', 'wpmind' ),
					'foot_meta'  => __( '最近 10 次模型请求记录', 'wpmind' ),
					'foot_label' => __( '全部统计', 'wpmind' ),
					'foot_href'  => $analytics_url,
				],
			],
		]
	);

	$right_tabs = Wenpai_Admin_UI::view_tabs_html(
		[
			'group'      => 'wpmind-ov-side',
			'aria_label' => __( '测速与额度切换', 'wpmind' ),
			'items'      => [
				[
					'id'         => 'speed',
					'label'      => __( '测速', 'wpmind' ),
					'active'     => true,
					'card_title' => __( '通道响应延迟', 'wpmind' ),
					'foot_meta'  => __( '按近期实际调用平均耗时统计', 'wpmind' ),
					'foot_label' => __( '路由状态', 'wpmind' ),
					'foot_href'  => $routing_url,
				],
				[
					'id'         => 'quota',
					'label'      => __( '额度', 'wpmind' ),
					'card_title' => __( '算力与预算额度', 'wpmind' ),
					'foot_meta'  => __( '官方算力与月度预算实时汇总', 'wpmind' ),
					'foot_label' => __( '管理服务', 'wpmind' ),
					'foot_href'  => $services_url,
				],
			],
		]
	);
	?>
	<div class="wenpai-split wenpai-split--equal">
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '各插件调用', 'wpmind' ),
				'tile'              => false,
				'class'             => 'wenpai-card--equal',
				'head_control_html' => $left_tabs,
			]
		);
		?>
		<div class="wenpai-card__body">
			<?php
			Wenpai_Admin_UI::view_panes(
				[
					'group' => 'wpmind-ov-activity',
					'panes' => [
						[
							'id'     => 'plugins',
							'active' => true,
							'html'   => Wenpai_Admin_UI::tag_feed_html(
								[
									'scrollable' => true,
									'items'      => $plugin_items,
									'empty'      => [
										'text'   => __( '还没有各插件调用记录', 'wpmind' ),
										'meta'   => __( '站内模块或文派系列插件发起 AI 请求后，会在此按来源汇总。', 'wpmind' ),
										'action' => [
											'label'   => __( '管理服务', 'wpmind' ),
											'href'    => $services_url,
											'variant' => 'secondary',
										],
									],
								]
							),
						],
						[
							'id'   => 'recent',
							'html' => Wenpai_Admin_UI::tag_feed_html(
								[
									'scrollable' => true,
									'items'      => $recent_items,
									'empty'      => [
										'text' => __( '还没有 API 调用记录', 'wpmind' ),
										'meta' => __( '配置并启用 AI 服务后，近期请求会按时间倒序显示在这里。', 'wpmind' ),
									],
								]
							),
						],
					],
				]
			);
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			[
				'start'   => true,
				'meta'    => __( '按站内调用来源归因统计', 'wpmind' ),
				'actions' => [
					[
						'label'   => __( '查看分析', 'wpmind' ),
						'href'    => $analytics_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();

		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '通道响应延迟', 'wpmind' ),
				'tile'              => false,
				'class'             => 'wenpai-card--equal',
				'head_control_html' => $right_tabs,
			]
		);
		?>
		<div class="wenpai-card__body">
			<?php
			Wenpai_Admin_UI::view_panes(
				[
					'group' => 'wpmind-ov-side',
					'panes' => [
						[
							'id'     => 'speed',
							'active' => true,
							'html'   => Wenpai_Admin_UI::metric_tiles_html( [ 'items' => $speed_tiles ] ),
						],
						[
							'id'   => 'quota',
							'html' => Wenpai_Admin_UI::quota_list_html( [ 'items' => $quota_items ] ),
						],
					],
				]
			);
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			[
				'start'   => true,
				'meta'    => __( '按近期实际调用平均耗时统计', 'wpmind' ),
				'actions' => [
					[
						'label'   => __( '路由状态', 'wpmind' ),
						'href'    => $routing_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<?php
	// Row 3: Fold List — 模块一览.
	$module_rows_html = '';
	foreach ( $modules as $mid => $m ) {
		$enabled    = ! empty( $m['enabled'] );
		$title_html = esc_html( (string) ( $m['name'] ?? $mid ) );
		if ( empty( $m['can_disable'] ) ) {
			$title_html .= ' ' . Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '核心', 'wpmind' ),
					'state' => 'mute',
				]
			);
		}
		$module_rows_html .= Wenpai_Admin_UI::row_html(
			[
				'icon_html'  => AdminIcons::svg( (string) ( $m['icon'] ?? 'modules' ) ) ?: AdminIcons::svg( 'modules' ),
				'state'      => $enabled ? 'ok' : '',
				'title_html' => $title_html,
				'desc'       => (string) ( $m['description'] ?? '' ),
				'pill'       => [
					'text'  => $enabled ? __( '已开启', 'wpmind' ) : __( '未开启', 'wpmind' ),
					'state' => $enabled ? 'ok' : 'mute',
				],
			]
		);
	}
	$module_rows_html .= Wenpai_Admin_UI::card_foot_html(
		[
			'start'   => true,
			'meta'    => __( '前往模块页启停或配置各能力模块。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '管理模块', 'wpmind' ),
					'href'    => $modules_url,
					'variant' => 'secondary',
				],
			],
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::fold_list(
		[
			'items' => [
				[
					'title'     => __( '模块一览', 'wpmind' ),
					'desc'      => sprintf(
						/* translators: 1: active module count, 2: total module count */
						__( '%1$d / %2$d 个模块已开启', 'wpmind' ),
						$active_count,
						count( $modules )
					),
					'pill'      => [
						'text'  => sprintf( '%d/%d', $active_count, count( $modules ) ),
						'state' => $active_count > 0 ? 'ok' : 'mute',
					],
					'body_html' => $module_rows_html,
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
