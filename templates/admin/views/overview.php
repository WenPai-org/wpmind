<?php
/**
 * 概览视图（1:1 对齐 2026-11-16 过审原型 overview.html：顶部 next 提示 + 内缩四格带图标统计条 + AI 服务动态双栏 + 卡级模块健康折叠）。
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
use WPMind\Provider\EndpointRepository;
use WPMind\Routing\IntelligentRouter;

( static function (): void {
	$cloud_url     = AdminPage::url( [ 'tab' => 'cloud' ] );
	$services_url  = AdminPage::url( [ 'tab' => 'services' ] );
	$modules_url   = AdminPage::url( [ 'tab' => 'modules' ] );
	$analytics_url = AdminPage::url( [ 'tab' => 'analytics' ] );
	$workflow_url  = AdminPage::url( [ 'tab' => 'workflow' ] );

	$today = class_exists( UsageTracker::class ) ? UsageTracker::get_today_stats() : [];
	$month = class_exists( UsageTracker::class ) ? UsageTracker::get_month_stats() : [];

	$cloud       = SuiteIntegration::cloud_status();
	$cloud_on    = ! empty( $cloud['enabled'] );
	$cloud_bound = ! empty( $cloud['bound'] );
	$cloud_ready = $cloud_on && $cloud_bound;
	$unit_label  = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );

	$modules      = class_exists( ModuleLoader::class ) ? ModuleLoader::instance()->get_modules() : [];
	$active_count = count( array_filter( $modules, static fn( $m ) => ! empty( $m['enabled'] ) ) );

	$providers = class_exists( FailoverManager::class ) ? FailoverManager::instance()->get_status_summary() : [];
	// 避免官方算力同时出现在 FailoverManager 与 SuiteIntegration 时重复渲染与计数.
	if ( $cloud_on && isset( $providers['wenpai_cloud'] ) ) {
		unset( $providers['wenpai_cloud'] );
	}

	$healthy = 0;
	$testing = 0;
	$down    = 0;
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

	// 端点默认模型映射（用于 Provider 健康副文案显示当前模型）.
	$endpoints_map = [];
	if ( class_exists( EndpointRepository::class ) ) {
		$endpoints_map = ( new EndpointRepository() )->all();
	}
	$default_provider = (string) get_option( 'wpmind_default_provider', '' );
	if ( '' === $default_provider && class_exists( IntelligentRouter::class ) ) {
		$routed_primary = IntelligentRouter::instance()->route();
		if ( is_string( $routed_primary ) && '' !== $routed_primary ) {
			$default_provider = $routed_primary;
		}
	}
	if ( '' === $default_provider && ! empty( $providers ) ) {
		$default_provider = (string) array_key_first( $providers );
	}
	$default_provider_name = '' !== $default_provider
		? ( class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $default_provider ) : $default_provider )
		: '';

	// 智能路由策略名.
	$strategy_label = '';
	if ( class_exists( IntelligentRouter::class ) ) {
		$router     = IntelligentRouter::instance();
		$cur_strat  = (string) $router->get_current_strategy();
		$avail_str  = $router->get_available_strategies();
		$strat_info = $avail_str[ $cur_strat ] ?? null;
		if ( is_array( $strat_info ) && ! empty( $strat_info['display_name'] ) ) {
			$strategy_label = (string) $strat_info['display_name'];
		} elseif ( '' !== $cur_strat ) {
			$strategy_label = $cur_strat;
		}
	}

	// 月度预算使用率（仅在启用预算且设有上限时显示）.
	$budget_pct_label = '';
	if ( class_exists( BudgetManager::class ) ) {
		$bs = BudgetManager::instance()->get_settings();
		if ( ! empty( $bs['enabled'] ) ) {
			$gb        = is_array( $bs['global'] ?? null ) ? $bs['global'] : [];
			$lim_cny   = (float) ( $gb['monthly_limit_cny'] ?? 0 );
			$lim_usd   = (float) ( $gb['monthly_limit_usd'] ?? 0 );
			$spent_cny = (float) ( $month['cost_cny'] ?? 0 );
			$spent_usd = (float) ( $month['cost_usd'] ?? 0 );
			$pct       = 0.0;
			if ( $lim_cny > 0 && $spent_cny >= 0 ) {
				$pct = min( 999.9, ( $spent_cny / $lim_cny ) * 100 );
			} elseif ( $lim_usd > 0 && $spent_usd >= 0 ) {
				$pct = min( 999.9, ( $spent_usd / $lim_usd ) * 100 );
			}
			if ( $lim_cny > 0 || $lim_usd > 0 ) {
				$budget_pct_label = sprintf(
					/* translators: %s: monthly budget usage percentage */
					__( '预算 %s%%', 'wpmind' ),
					number_format_i18n( $pct, 1 )
				);
			}
		}
	}

	$recent_history = class_exists( UsageTracker::class ) ? UsageTracker::get_history( 5 ) : [];
	$context_stats  = class_exists( UsageTracker::class ) ? UsageTracker::get_context_stats() : [];

	$cache_stats    = function_exists( 'wpmind_get_cache_stats' )
		? wpmind_get_cache_stats()
		: [
			'enabled'  => false,
			'hits'     => 0,
			'misses'   => 0,
			'hit_rate' => 0,
		];
	$cache_enabled  = ! empty( $cache_stats['enabled'] );
	$cache_hits     = (int) ( $cache_stats['hits'] ?? 0 );
	$cache_total    = $cache_hits + (int) ( $cache_stats['misses'] ?? 0 );
	$cache_hit_rate = isset( $cache_stats['hit_rate'] ) ? (float) $cache_stats['hit_rate'] : 0.0;

	$plugin_dir = defined( 'WPMIND_PLUGIN_DIR' ) ? (string) WPMIND_PLUGIN_DIR : dirname( __DIR__, 3 ) . '/';
	$plugin_url = defined( 'WPMIND_PLUGIN_URL' ) ? (string) WPMIND_PLUGIN_URL : '';

	$provider_logo = static function ( string $pid, int $size = 16 ) use ( $plugin_dir, $plugin_url ): string {
		$safe = sanitize_key( $pid );
		if ( '' !== $safe && '' !== $plugin_url && file_exists( $plugin_dir . 'assets/images/providers/' . $safe . '.svg' ) ) {
			return '<img src="' . esc_url( $plugin_url . 'assets/images/providers/' . $safe . '.svg' ) . '" alt="" width="' . $size . '" height="' . $size . '">';
		}
		return AdminIcons::svg( 'wenpai_cloud' === $pid ? 'cloud' : 'robot' );
	};

	$state_pill = static function ( string $state ): array {
		$map = [
			'healthy'   => [ __( '正常', 'wpmind' ), 'ok' ],
			'closed'    => [ __( '正常', 'wpmind' ), 'ok' ],
			'half_open' => [ __( '恢复中', 'wpmind' ), 'warn' ],
			'open'      => [ __( '熔断中', 'wpmind' ), 'bad' ],
		];
		return $map[ $state ] ?? [ $state, 'mute' ];
	};

	$unconfigured = 0 === $total_providers && ! $cloud_on;

	// ---- next 提示条：仅异常或未配置时出现 ----
	$next = null;
	if ( $unconfigured ) {
		$next = [
			'state'   => 'bad',
			'icon'    => 'alert',
			'title'   => __( '还没有配置 AI 服务', 'wpmind' ),
			'desc'    => __( '开启文派心思官方算力（心思点数）或填入任意服务商 API Key 后即可使用。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '开启心思 AI', 'wpmind' ),
					'href'    => $cloud_url,
					'variant' => 'primary',
				],
				[
					'label'   => __( '自备 Key', 'wpmind' ),
					'href'    => $services_url,
					'variant' => 'ghost',
				],
			],
		];
	} elseif ( $down > 0 ) {
		$next = [
			'state'   => 'bad',
			'icon'    => 'alert',
			'title'   => sprintf(
				/* translators: %d: open circuit breakers */
				__( '%d 个 AI 服务熔断器已打开', 'wpmind' ),
				$down
			),
			'desc'    => __( '路由已自动切换到可用备选，待冷却后自动进入恢复测试。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '查看 AI 服务', 'wpmind' ),
					'href'    => $services_url,
					'variant' => 'primary',
				],
				[
					'label'   => __( '任务记录', 'wpmind' ),
					'href'    => $workflow_url,
					'variant' => 'ghost',
				],
			],
		];
	} elseif ( $testing > 0 ) {
		$next = [
			'state'   => 'warn',
			'icon'    => 'refresh',
			'title'   => sprintf(
				/* translators: %d: half-open circuit breakers */
				__( '%d 个 AI 服务恢复探测中', 'wpmind' ),
				$testing
			),
			'desc'    => __( '熔断器半开状态：正在试探性请求恢复，成功后自动闭合。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '查看 AI 服务', 'wpmind' ),
					'href'    => $services_url,
					'variant' => 'primary',
				],
			],
		];
	}

	// 按 2026-11-16 原型：页头保持纯净，不放多余动作按钮.
	wpmind_page_head(
		__( '概览', 'wpmind' ),
		__( 'AI 服务中枢：一页查看熔断、预算、用量与缓存命中。需要处理的事会出现在顶部提示里。', 'wpmind' )
	);
	?>

	<div class="wenpai-stack">
		<?php
		if ( null !== $next ) {
			Wenpai_Admin_UI::next( $next );
		}

		// ---- 1. 顶部四格带蓝图标统计条（内缩分隔线，1:1 还原 2026-11-16 原型） ----
		$today_tokens   = (int) ( $today['input_tokens'] ?? 0 ) + (int) ( $today['output_tokens'] ?? 0 );
		$today_tok_s    = class_exists( UsageTracker::class )
			? UsageTracker::format_tokens( $today_tokens ) . ' tok'
			: number_format_i18n( $today_tokens ) . ' tok';
		$today_cost_usd = (float) ( $today['cost_usd'] ?? 0 );
		$today_cost_cny = (float) ( $today['cost_cny'] ?? 0 );
		if ( ( $today_cost_usd > 0 || $today_cost_cny > 0 ) && class_exists( UsageTracker::class ) ) {
			$today_tok_s .= ' · ' . UsageTracker::format_cost_by_currency( $today_cost_usd, $today_cost_cny );
		}

		$health_total = $total_providers + ( $cloud_on ? 1 : 0 );
		$health_ok    = $healthy + ( $cloud_ready ? 1 : 0 );
		$health_unit  = $unconfigured
			? __( '待配置', 'wpmind' )
			: ( $down > 0
				? sprintf(
					/* translators: %d: breaker count */
					__( '在线 · %d 熔断', 'wpmind' ),
					$down
				)
				: __( '全部在线', 'wpmind' ) );

		$month_cost = class_exists( UsageTracker::class )
			? UsageTracker::format_cost_by_currency( (float) ( $month['cost_usd'] ?? 0 ), (float) ( $month['cost_cny'] ?? 0 ) )
			: '¥0.00';
		$month_unit = '' !== $budget_pct_label
			? $budget_pct_label
			: sprintf(
				/* translators: %s: monthly requests */
				__( '%s 次调用', 'wpmind' ),
				number_format_i18n( (int) ( $month['requests'] ?? 0 ) )
			);

		if ( $cloud_on && null !== $cloud['credits_balance'] ) {
			$fourth_icon  = 'sparkle';
			$fourth_label = $unit_label;
			$fourth_value = number_format_i18n( (int) $cloud['credits_balance'] );
			$fourth_unit  = sprintf(
				/* translators: %s: used credits this month */
				__( '本月已用 %s', 'wpmind' ),
				number_format_i18n( (int) ( $cloud['credits_used_month'] ?? 0 ) )
			);
		} else {
			$fourth_icon  = 'database';
			$fourth_label = __( '缓存命中率', 'wpmind' );
			$fourth_value = $cache_enabled ? number_format_i18n( $cache_hit_rate, 1 ) . '%' : '—';
			$fourth_unit  = $cache_enabled
				? sprintf(
					/* translators: 1: hit count, 2: total queries */
					__( '%1$s / %2$s 查询', 'wpmind' ),
					number_format_i18n( $cache_hits ),
					number_format_i18n( $cache_total )
				)
				: __( '未开启', 'wpmind' );
		}

		$stat_cells = [
			[
				'icon'  => 'trend-up',
				'label' => __( '今日请求', 'wpmind' ),
				'value' => number_format_i18n( (int) ( $today['requests'] ?? 0 ) ),
				'unit'  => $today_tok_s,
			],
			[
				'icon'  => 'pulse',
				'label' => __( 'AI 服务健康度', 'wpmind' ),
				'value' => $health_ok . '/' . $health_total,
				'unit'  => $health_unit,
			],
			[
				'icon'  => 'dollar',
				'label' => __( '本月费用', 'wpmind' ),
				'value' => $month_cost,
				'unit'  => $month_unit,
			],
			[
				'icon'  => $fourth_icon,
				'label' => $fourth_label,
				'value' => $fourth_value,
				'unit'  => $fourth_unit,
			],
		];

		Wenpai_Admin_UI::card_open();
		?>
		<div class="wpmind-ov__stats">
			<?php foreach ( $stat_cells as $cell ) : ?>
				<div class="wpmind-ov__stat">
					<span class="wpmind-ov__stat-ico"><?php echo AdminIcons::svg( $cell['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div class="wpmind-ov__stat-text">
						<div class="wpmind-ov__stat-k"><?php echo esc_html( $cell['label'] ); ?></div>
						<div class="wpmind-ov__stat-v"><?php echo esc_html( $cell['value'] ); ?><small><?php echo esc_html( $cell['unit'] ); ?></small></div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();

		// ---- 2. 主卡：AI 服务动态（蓝底 sparkle 图标 + 路由摘要 + 竖分隔双栏） ----
		$sub_parts = [];
		if ( '' !== $default_provider_name ) {
			$sub_parts[] = sprintf(
				/* translators: %s: default provider name */
				__( '默认：%s', 'wpmind' ),
				$default_provider_name
			);
		}
		if ( '' !== $strategy_label ) {
			$sub_parts[] = sprintf(
				/* translators: %s: routing strategy name */
				__( '智能路由：%s', 'wpmind' ),
				$strategy_label
			);
		}
		$sub_parts[] = sprintf(
			/* translators: 1: healthy providers, 2: total providers */
			__( '%1$d/%2$d Provider 在线', 'wpmind' ),
			$health_ok,
			$health_total
		);

		Wenpai_Admin_UI::card_open(
			[
				'class'             => 'wpmind-ov__card',
				'title'             => __( 'AI 服务动态', 'wpmind' ),
				'sub'               => implode( ' · ', $sub_parts ),
				'icon'              => 'sparkle',
				'state'             => 'accent',
				'head_actions_html' => Wenpai_Admin_UI::button_html(
					[
						'label'   => __( '管理服务', 'wpmind' ),
						'href'    => $services_url,
						'variant' => 'ghost',
					]
				),
			]
		);
		?>
		<div class="wpmind-ov__split">
			<div class="wpmind-ov__col">
				<div class="wpmind-ov__colhead">
					<?php echo AdminIcons::svg( 'pulse' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php esc_html_e( 'Provider 健康', 'wpmind' ); ?></span>
				</div>
				<?php
				if ( $unconfigured ) {
					Wenpai_Admin_UI::empty_state(
						[
							'title'   => __( '还没有配置 AI 服务', 'wpmind' ),
							'desc'    => __( '开启官方算力或填入任意服务商 API Key 后，这里会显示实时健康状态。', 'wpmind' ),
							'actions' => [
								[
									'label'   => __( '去配置', 'wpmind' ),
									'href'    => $cloud_url,
									'variant' => 'primary',
								],
							],
						]
					);
				} else {
					echo '<div class="wpmind-ov__health">';
					$shown_pids = [];
					if ( $cloud_on ) {
						$shown_pids[] = 'wenpai_cloud';
						$cloud_desc   = $cloud_bound
							? ( null !== $cloud['credits_balance']
								? sprintf(
									/* translators: 1: balance, 2: unit */
									__( '剩余 %1$s %2$s · 免密钥调用', 'wpmind' ),
									number_format_i18n( (int) $cloud['credits_balance'] ),
									$unit_label
								)
								: __( '站点已绑定 · 等待首次同步余额', 'wpmind' ) )
							: __( '尚未绑定文派云通行证', 'wpmind' );
						$cloud_pill = Wenpai_Admin_UI::pill_html(
							[
								'text'  => $cloud_bound ? __( '已连接', 'wpmind' ) : __( '待绑定', 'wpmind' ),
								'state' => $cloud_bound ? 'ok' : 'warn',
							]
						);
						echo '<div class="wpmind-ov__hrow">';
						echo '<span class="wpmind-ov__avatar">' . $provider_logo( 'wenpai_cloud', 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped img/SVG.
						echo '<div class="wpmind-ov__hmain"><div class="wpmind-ov__htitle">' . esc_html__( '文派心思官方算力', 'wpmind' ) . '</div><div class="wpmind-ov__hdesc">' . esc_html( $cloud_desc ) . '</div></div>';
						echo $cloud_pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
						echo '</div>';
					}
					foreach ( $providers as $pid => $p ) {
						$pid                        = (string) $pid;
						$shown_pids[]               = $pid;
						$state                      = (string) ( $p['state'] ?? 'unknown' );
						[ $pill_text, $pill_state ] = $state_pill( $state );

						$ep_model = '';
						if ( isset( $endpoints_map[ $pid ] ) ) {
							$ep       = $endpoints_map[ $pid ];
							$ep_model = $ep->default_model;
							if ( '' === $ep_model && ! empty( $ep->models[0] ) ) {
								$ep_model = (string) $ep->models[0];
							}
						}

						$desc_bits = [];
						if ( '' !== $ep_model ) {
							$desc_bits[] = $ep_model;
						}
						if ( ! empty( $p['recovery_in'] ) && ( 'half_open' === $state || 'open' === $state ) ) {
							$desc_bits[] = sprintf(
								/* translators: %s: recovery time */
								__( '%s 后恢复测试', 'wpmind' ),
								(string) $p['recovery_in']
							);
						} elseif ( $pid === $default_provider && ( 'healthy' === $state || 'closed' === $state ) ) {
							$desc_bits[] = __( '默认路由首选', 'wpmind' );
						} elseif ( isset( $p['success_rate'] ) && ( 'healthy' === $state || 'closed' === $state ) ) {
							$desc_bits[] = sprintf(
								/* translators: %d: health score percentage */
								__( '健康分 %d%%', 'wpmind' ),
								(int) $p['success_rate']
							);
						} elseif ( '' === $ep_model ) {
							$desc_bits[] = $pill_text;
						}

						$localized = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $pid ) : $pid;
						$pname     = $localized !== $pid ? $localized : (string) ( $p['display_name'] ?? $pid );
						$row_pill  = Wenpai_Admin_UI::pill_html(
							[
								'text'  => $pill_text,
								'state' => $pill_state,
							]
						);
						echo '<div class="wpmind-ov__hrow">';
						echo '<span class="wpmind-ov__avatar">' . $provider_logo( $pid, 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped img/SVG.
						echo '<div class="wpmind-ov__hmain"><div class="wpmind-ov__htitle">' . esc_html( $pname ) . '</div><div class="wpmind-ov__hdesc">' . esc_html( implode( ' · ', $desc_bits ) ) . '</div></div>';
						echo $row_pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
						echo '</div>';
					}

					// 已启用端点少于 4 个时，按 2026-11-16 原型补齐常用未启用备选行以平衡双栏高度.
					if ( count( $shown_pids ) < 4 && ! empty( $endpoints_map ) ) {
						$popular_order = [ 'deepseek', 'qwen', 'openai', 'zhipu', 'anthropic', 'google' ];
						foreach ( $popular_order as $cand ) {
							if ( count( $shown_pids ) >= 4 ) {
								break;
							}
							if ( in_array( $cand, $shown_pids, true ) || ! isset( $endpoints_map[ $cand ] ) ) {
								continue;
							}
							$ep           = $endpoints_map[ $cand ];
							$shown_pids[] = $cand;
							$ep_model     = '' !== $ep->default_model ? $ep->default_model : (string) ( $ep->models[0] ?? '' );
							$desc_str     = '' !== $ep_model
								? $ep_model . ' · ' . __( '未配置 API Key', 'wpmind' )
								: __( '未配置 API Key', 'wpmind' );
							$pname        = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $cand ) : $cand;
							$off_pill     = Wenpai_Admin_UI::pill_html(
								[
									'text'  => __( '未启用', 'wpmind' ),
									'state' => 'warn',
								]
							);
							echo '<div class="wpmind-ov__hrow">';
							echo '<span class="wpmind-ov__avatar">' . $provider_logo( $cand, 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped img/SVG.
							echo '<div class="wpmind-ov__hmain"><div class="wpmind-ov__htitle">' . esc_html( $pname ) . '</div><div class="wpmind-ov__hdesc">' . esc_html( $desc_str ) . '</div></div>';
							echo $off_pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
							echo '</div>';
						}
					}
					echo '</div>';
				}
				?>
			</div>
			<div class="wpmind-ov__col wpmind-ov__col--right">
				<div class="wpmind-ov__colhead">
					<?php echo AdminIcons::svg( 'pulse' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php esc_html_e( '最近活动', 'wpmind' ); ?></span>
					<a class="wpmind-ov__more" href="<?php echo esc_url( $analytics_url ); ?>"><?php esc_html_e( '查看全部', 'wpmind' ); ?></a>
				</div>
				<?php
				if ( empty( $recent_history ) ) {
					echo '<p class="meta">' . esc_html__( '还没有 API 调用记录，使用 AI 功能后显示在这里。', 'wpmind' ) . '</p>';
				} else {
					echo '<div class="wpmind-ov__feed">';
					foreach ( $recent_history as $record ) {
						$ts       = (int) ( $record['timestamp'] ?? 0 );
						$ago      = $ts > 0 ? human_time_diff( $ts, time() ) . __( '前', 'wpmind' ) : '—';
						$pid      = (string) ( $record['provider'] ?? '' );
						$pname    = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $pid ) : $pid;
						$model    = (string) ( $record['model'] ?? '—' );
						$tokens   = (int) ( $record['input_tokens'] ?? 0 ) + (int) ( $record['output_tokens'] ?? 0 );
						$latency  = (int) ( $record['latency_ms'] ?? 0 );
						$cached   = ! empty( $record['cached'] ) || ! empty( $record['cache_hit'] );
						$is_error = isset( $record['success'] ) && false === $record['success'];
						$is_image = 'image' === (string) ( $record['type'] ?? '' )
							|| false !== stripos( $model, 'image' )
							|| false !== stripos( $model, 'wanx' )
							|| false !== stripos( $model, 'flux' )
							|| false !== stripos( $model, 'seedream' );

						$tok_str = class_exists( UsageTracker::class )
							? UsageTracker::format_tokens( $tokens )
							: number_format_i18n( $tokens );
						$lat_str = $latency >= 1000
							? number_format_i18n( $latency / 1000, 1 ) . 's'
							: ( $latency > 0 ? $latency . 'ms' : '' );

						echo '<div class="wpmind-ov__fitem">';
						echo '<span class="wpmind-ov__ftime">' . esc_html( $ago ) . '</span>';
						echo '<span class="wpmind-ov__fmain">';
						echo '<span class="wpmind-ov__fprov">' . $provider_logo( $pid, 14 ) . esc_html( $pname ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped img/SVG.
						echo '<span class="wpmind-ov__fmodel">' . esc_html( $model ) . '</span>';
						if ( $is_image ) {
							echo '<span class="wpmind-ov__ftag">' . esc_html__( '图像', 'wpmind' ) . '</span>';
						}
						echo '</span>';
						echo '<span class="wpmind-ov__fmeta">';
						if ( $is_error ) {
							$err_code = isset( $record['status_code'] ) && (int) $record['status_code'] > 0
								? __( '失败 · ', 'wpmind' ) . (int) $record['status_code']
								: __( '失败', 'wpmind' );
							$err_pill = Wenpai_Admin_UI::pill_html(
								[
									'text'  => $err_code,
									'state' => 'bad',
								]
							);
							echo $err_pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
						} elseif ( $cached ) {
							echo '<span>' . esc_html__( 'cache 命中', 'wpmind' ) . '</span>';
							if ( '' !== $lat_str ) {
								echo '<span>' . esc_html( $lat_str ) . '</span>';
							}
						} else {
							if ( ! $is_image || $tokens > 0 ) {
								echo '<span>' . esc_html( $tok_str . ' tok' ) . '</span>';
							}
							if ( '' !== $lat_str ) {
								echo '<span>' . esc_html( $lat_str ) . '</span>';
							}
						}
						echo '</span>';
						echo '</div>';
					}
					echo '</div>';
				}
				?>
			</div>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();

		// ---- 3. 底部单行卡级折叠：模块健康（1:1 还原 2026-11-16 原型 details.card） ----
		$chevron_svg = class_exists( 'Wenpai_Admin_Icons', false ) && \Wenpai_Admin_Icons::has( 'chevron-down' )
			? \Wenpai_Admin_Icons::svg( 'chevron-down', 'wenpai-ico' )
			: '';

		$mod_icon_map = [
			'cost-control'       => 'dollar',
			'analytics'          => 'analytics',
			'exact-cache'        => 'database',
			'geo'                => 'globe',
			'api-gateway'        => 'cloud',
			'media-intelligence' => 'image',
			'auto-meta'          => 'sparkle',
		];
		?>
		<details class="card wpmind-ov__fold">
			<summary>
				<span class="wpmind-ov__fold-ico"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit SVG. ?></span>
				<span class="section-title"><?php esc_html_e( '模块健康', 'wpmind' ); ?></span>
				<span class="meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: active count, 2: total count */
							__( '%1$d / %2$d 已启用 · 禁用可提升性能', 'wpmind' ),
							$active_count,
							count( $modules )
						)
					);
					?>
				</span>
			</summary>
			<div class="wpmind-ov__fold-body">
				<div>
					<?php
					foreach ( $modules as $mid => $m ) {
						$mid        = (string) $mid;
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
						$ikey = $mod_icon_map[ $mid ] ?? 'modules';
						Wenpai_Admin_UI::row(
							[
								'icon_html'  => AdminIcons::svg( $ikey ) ?: AdminIcons::svg( 'modules' ),
								'state'      => $enabled ? 'ok' : '',
								'title_html' => $title_html,
								'desc'       => (string) ( $m['description'] ?? '' ),
								'pill'       => [
									'text'  => $enabled ? __( '已启用', 'wpmind' ) : __( '未启用', 'wpmind' ),
									'state' => $enabled ? 'ok' : 'mute',
								],
							]
						);
					}
					?>
				</div>
				<?php
				Wenpai_Admin_UI::card_foot(
					[
						'start'        => true,
						'meta'         => __( '模块开关在「模块」页。', 'wpmind' ),
						'actions_html' => Wenpai_Admin_UI::button_html(
							[
								'label'   => __( '模块管理', 'wpmind' ),
								'href'    => $modules_url,
								'variant' => 'secondary',
							]
						),
					]
				);
				?>
			</div>
		</details>

		<?php
		// 有实际跨插件调用时，补充同形态折叠卡展示生态插件调用.
		if ( ! empty( $context_stats ) ) {
			$plugin_labels = [
				'wpslug'      => __( '文派别名（WPSlug）', 'wpmind' ),
				'bisheng'     => __( '笔笙（Bisheng）', 'wpmind' ),
				'wpicp'       => __( '文派合规（WPICP）', 'wpmind' ),
				'wpdocs'      => __( '文派文档（WPDocs）', 'wpmind' ),
				'wpsss'       => __( '文派智储（WPSSS）', 'wpmind' ),
				'wpcomments'  => __( '文派评论（WPComments）', 'wpmind' ),
				'api-gateway' => __( '外部 API 网关', 'wpmind' ),
				'wpmind'      => __( '文派心思内置模块', 'wpmind' ),
			];
			$by_caller     = [];
			$caller_total  = 0;
			foreach ( $context_stats as $ctx_key => $ctx_row ) {
				$caller = (string) ( $ctx_row['caller_plugin'] ?? '' );
				if ( '' === $caller ) {
					$caller = '' !== (string) $ctx_key ? (string) $ctx_key : 'wpmind';
				}
				if ( ! isset( $by_caller[ $caller ] ) ) {
					$by_caller[ $caller ] = [
						'requests' => 0,
						'tokens'   => 0,
						'credits'  => 0,
					];
				}
				$req                               = (int) ( $ctx_row['requests'] ?? 0 );
				$caller_total                     += $req;
				$by_caller[ $caller ]['requests'] += $req;
				$by_caller[ $caller ]['tokens']   += (int) ( $ctx_row['input_tokens'] ?? 0 ) + (int) ( $ctx_row['output_tokens'] ?? 0 );
				$by_caller[ $caller ]['credits']  += (int) ( $ctx_row['credits'] ?? 0 );
			}
			uasort( $by_caller, static fn( $a, $b ) => $b['requests'] <=> $a['requests'] );
			?>
			<details class="card wpmind-ov__fold">
				<summary>
					<span class="wpmind-ov__fold-ico"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit SVG. ?></span>
					<span class="section-title"><?php esc_html_e( '生态插件调用', 'wpmind' ); ?></span>
					<span class="meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: caller count, 2: total requests */
								__( '%1$d 个来源 · 累计 %2$s 次', 'wpmind' ),
								count( $by_caller ),
								number_format_i18n( $caller_total )
							)
						);
						?>
					</span>
				</summary>
				<div class="wpmind-ov__fold-body">
					<div>
						<?php
						foreach ( array_slice( $by_caller, 0, 8, true ) as $caller_slug => $agg ) {
							$tok_fmt    = class_exists( UsageTracker::class )
								? UsageTracker::format_tokens( (int) $agg['tokens'] )
								: number_format_i18n( (int) $agg['tokens'] );
							$desc_parts = [ $tok_fmt . ' tok' ];
							if ( (int) $agg['credits'] > 0 ) {
								$desc_parts[] = sprintf(
									/* translators: 1: credits count, 2: credits unit label */
									__( '%1$s %2$s', 'wpmind' ),
									number_format_i18n( (int) $agg['credits'] ),
									$unit_label
								);
							}
							Wenpai_Admin_UI::row(
								[
									'icon_html' => AdminIcons::svg( 'modules' ),
									'title'     => $plugin_labels[ $caller_slug ] ?? $caller_slug,
									'desc'      => implode( ' · ', $desc_parts ),
									'pill'      => [
										'text'  => sprintf(
											/* translators: %s: request count */
											__( '%s 次', 'wpmind' ),
											number_format_i18n( (int) $agg['requests'] )
										),
										'state' => 'ok',
									],
								]
							);
						}
						?>
					</div>
					<?php
					Wenpai_Admin_UI::card_foot(
						[
							'start'        => true,
							'meta'         => __( '全站插件统一经 WPMind PHP 接口调用，共用路由与预算。', 'wpmind' ),
							'actions_html' => Wenpai_Admin_UI::button_html(
								[
									'label'   => __( '查看分析', 'wpmind' ),
									'href'    => $analytics_url,
									'variant' => 'ghost',
								]
							) . Wenpai_Admin_UI::button_html(
								[
									'label'   => __( '任务记录', 'wpmind' ),
									'href'    => $workflow_url,
									'variant' => 'ghost',
								]
							),
						]
					);
					?>
				</div>
			</details>
		<?php } ?>
	</div>
	<?php
} )();
