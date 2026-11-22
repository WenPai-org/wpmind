<?php
/**
 * 概览视图（WPMind 审定对称双栏版）：next 提示 + statbar + AI 服务动态双栏 + 模块与生态调用折叠卡。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Core\ModuleLoader;
use WPMind\Failover\FailoverManager;
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
	$cloud_bound  = ! empty( $cloud['bound'] );
	$cloud_ready  = $cloud_on && $cloud_bound;

	$services_url  = AdminPage::url( [ 'tab' => 'services' ] );
	$routing_url   = AdminPage::url( [ 'tab' => 'routing' ] );
	$analytics_url = AdminPage::url( [ 'tab' => 'analytics' ] );
	$modules_url   = AdminPage::url( [ 'tab' => 'modules' ] );
	$workflow_url  = AdminPage::url( [ 'tab' => 'workflow' ] );

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
	$cache_hit_rate = isset( $cache_stats['hit_rate'] ) ? (float) $cache_stats['hit_rate'] : 0.0;

	$plugin_dir = defined( 'WPMIND_PLUGIN_DIR' ) ? (string) WPMIND_PLUGIN_DIR : dirname( __DIR__, 3 ) . '/';
	$plugin_url = defined( 'WPMIND_PLUGIN_URL' ) ? (string) WPMIND_PLUGIN_URL : '';

	$provider_logo = static function ( string $pid ) use ( $plugin_dir, $plugin_url ): string {
		$safe = sanitize_key( $pid );
		if ( '' !== $safe && '' !== $plugin_url && file_exists( $plugin_dir . 'assets/images/providers/' . $safe . '.svg' ) ) {
			return '<img src="' . esc_url( $plugin_url . 'assets/images/providers/' . $safe . '.svg' ) . '" alt="" width="20" height="20">';
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
					'label'   => __( '去配置', 'wpmind' ),
					'href'    => $services_url,
					'variant' => 'primary',
				],
			],
		];
	} elseif ( $down > 0 ) {
		$next = [
			'state'   => 'warn',
			'icon'    => 'alert',
			'title'   => sprintf(
				/* translators: %d: open circuit breakers */
				__( '%d 个 AI 服务熔断中', 'wpmind' ),
				$down
			),
			'desc'    => __( '路由已自动切换到可用备选；恢复后自动回切，也可在服务页手动重置。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '查看 AI 服务', 'wpmind' ),
					'href'    => $services_url,
					'variant' => 'primary',
				],
				[
					'label'   => __( '智能路由', 'wpmind' ),
					'href'    => $routing_url,
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
		__( 'AI 服务健康、用量与心思点数一页查看。', 'wpmind' ),
		$head_actions
	);
	?>

	<div class="wenpai-stack">
		<?php
		if ( null !== $next ) {
			Wenpai_Admin_UI::next( $next );
		}

		// ---- 1. 顶部四格核心指标卡（kit statbar） ----
		$today_tokens = (int) ( $today['input_tokens'] ?? 0 ) + (int) ( $today['output_tokens'] ?? 0 );
		$today_tok_s  = class_exists( UsageTracker::class )
			? UsageTracker::format_tokens( $today_tokens ) . ' tok'
			: number_format_i18n( $today_tokens ) . ' tok';

		$health_total = $total_providers + ( $cloud_on ? 1 : 0 );
		$health_ok    = $healthy + ( $cloud_ready ? 1 : 0 );
		$health_unit  = $unconfigured
			? __( '待配置', 'wpmind' )
			: ( $down > 0
				? sprintf(
					/* translators: %d: breaker count */
					__( '%d 熔断', 'wpmind' ),
					$down
				)
				: __( '全部在线', 'wpmind' ) );

		$month_cost = class_exists( UsageTracker::class )
			? UsageTracker::format_cost_by_currency( (float) ( $month['cost_usd'] ?? 0 ), (float) ( $month['cost_cny'] ?? 0 ) )
			: '¥0.00';

		$unit_label = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );
		if ( $cloud_on && null !== $cloud['credits_balance'] ) {
			$credits_value = number_format_i18n( (int) $cloud['credits_balance'] );
			$credits_unit  = sprintf(
				/* translators: %s: used credits this month */
				__( '本月已用 %s', 'wpmind' ),
				number_format_i18n( (int) ( $cloud['credits_used_month'] ?? 0 ) )
			);
		} elseif ( $cloud_on && $cloud_bound ) {
			$credits_value = __( '待同步', 'wpmind' );
			$credits_unit  = __( '已绑定通行证', 'wpmind' );
		} elseif ( $cloud_on ) {
			$credits_value = __( '待绑定', 'wpmind' );
			$credits_unit  = __( '需绑定文派云', 'wpmind' );
		} else {
			$credits_value = '—';
			$credits_unit  = __( '未开启', 'wpmind' );
		}

		Wenpai_Admin_UI::card_open();
		Wenpai_Admin_UI::statbar(
			[
				'items' => [
					[
						'label' => __( '今日请求', 'wpmind' ),
						'value' => number_format_i18n( (int) ( $today['requests'] ?? 0 ) ),
						'unit'  => $today_tok_s,
					],
					[
						'label' => __( 'AI 服务健康度', 'wpmind' ),
						'value' => $health_ok . '/' . $health_total,
						'unit'  => $health_unit,
					],
					[
						'label' => __( '本月费用', 'wpmind' ),
						'value' => $month_cost,
						'unit'  => sprintf(
							/* translators: %s: monthly requests */
							__( '%s 次调用', 'wpmind' ),
							number_format_i18n( (int) ( $month['requests'] ?? 0 ) )
						),
					],
					[
						'label' => $unit_label,
						'value' => $credits_value,
						'unit'  => $credits_unit,
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();

		// ---- 2. 主卡：AI 服务动态（对称双栏 .wenpai-grid-2） ----
		$activity_sub = sprintf(
			/* translators: 1: provider count, 2: active module count, 3: total module count */
			__( '%1$d 个服务 · %2$d/%3$d 模块已开启', 'wpmind' ),
			$health_total,
			$active_count,
			count( $modules )
		);
		if ( $cache_enabled ) {
			$activity_sub .= ' · ' . sprintf(
				/* translators: %s: cache hit rate percentage */
				__( '缓存命中 %s%%', 'wpmind' ),
				number_format_i18n( $cache_hit_rate, 1 )
			);
		}
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( 'AI 服务动态', 'wpmind' ),
				'sub'               => $activity_sub,
				'icon'              => 'sparkle',
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
		<div class="wenpai-grid-2">
			<div class="wenpai-stack wenpai-stack--sm">
				<?php
				Wenpai_Admin_UI::section_title( [ 'title' => __( '服务健康', 'wpmind' ) ] );
				if ( $unconfigured ) {
					Wenpai_Admin_UI::empty_state(
						[
							'title'   => __( '还没有配置 AI 服务', 'wpmind' ),
							'desc'    => __( '开启官方算力或填入任意服务商 API Key 后，这里会显示实时健康状态。', 'wpmind' ),
							'actions' => [
								[
									'label'   => __( '去配置', 'wpmind' ),
									'href'    => $services_url,
									'variant' => 'primary',
								],
							],
						]
					);
				} else {
					if ( $cloud_on ) {
						$cloud_desc = $cloud_bound
							? ( null !== $cloud['credits_balance']
								? sprintf(
									/* translators: 1: balance, 2: unit */
									__( '剩余 %1$s %2$s · 免密钥调用', 'wpmind' ),
									number_format_i18n( (int) $cloud['credits_balance'] ),
									$unit_label
								)
								: __( '站点已绑定 · 等待首次同步余额', 'wpmind' ) )
							: __( '尚未绑定文派云通行证', 'wpmind' );
						Wenpai_Admin_UI::row(
							[
								'logo_html' => $provider_logo( 'wenpai_cloud' ),
								'title'     => __( '文派心思官方算力', 'wpmind' ),
								'desc'      => $cloud_desc,
								'pill'      => [
									'text'  => $cloud_bound ? __( '已连接', 'wpmind' ) : __( '待绑定', 'wpmind' ),
									'state' => $cloud_bound ? 'ok' : 'warn',
								],
							]
						);
					}
					foreach ( $providers as $pid => $p ) {
						$pid                       = (string) $pid;
						$state                     = (string) ( $p['state'] ?? 'unknown' );
						[ $pill_text, $pill_state ] = $state_pill( $state );
						$desc                      = (string) ( $p['state_label'] ?? $state );
						if ( ! empty( $p['recovery_in'] ) && 'half_open' === $state ) {
							$desc .= ' · ' . sprintf(
								/* translators: %s: recovery time */
								__( '%s 后恢复测试', 'wpmind' ),
								(string) $p['recovery_in']
							);
						}
						if ( isset( $p['success_rate'] ) && ( 'healthy' === $state || 'closed' === $state ) ) {
							$desc .= ' · ' . sprintf(
								/* translators: %d: rate */
								__( '成功率 %d%%', 'wpmind' ),
								(int) $p['success_rate']
							);
						}
						$localized = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $pid ) : $pid;
						$pname     = $localized !== $pid ? $localized : (string) ( $p['display_name'] ?? $pid );
						Wenpai_Admin_UI::row(
							[
								'logo_html' => $provider_logo( $pid ),
								'title'     => $pname,
								'desc'      => $desc,
								'pill'      => [
									'text'  => $pill_text,
									'state' => $pill_state,
								],
							]
						);
					}
				}
				?>
			</div>
			<div class="wenpai-stack wenpai-stack--sm">
				<div class="wenpai-row">
					<?php
					Wenpai_Admin_UI::section_title( [ 'title' => __( '最近活动', 'wpmind' ) ] );
					$recent_all_btn = Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '查看全部', 'wpmind' ),
							'href'    => $analytics_url,
							'variant' => 'ghost',
							'class'   => 'wenpai-push',
						]
					);
					echo $recent_all_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
					?>
				</div>
				<?php
				if ( empty( $recent_history ) ) {
					echo '<p class="meta">' . esc_html__( '还没有 API 调用记录，使用 AI 功能后显示在这里。', 'wpmind' ) . '</p>';
				} else {
					$log_items = [];
					foreach ( $recent_history as $record ) {
						$ts      = (int) ( $record['timestamp'] ?? 0 );
						$ago     = $ts > 0 ? human_time_diff( $ts, time() ) . __( '前', 'wpmind' ) : '—';
						$pid     = (string) ( $record['provider'] ?? '' );
						$pname   = class_exists( UsageTracker::class ) ? UsageTracker::get_provider_display_name( $pid ) : $pid;
						$model   = (string) ( $record['model'] ?? '—' );
						$tokens  = (int) ( $record['input_tokens'] ?? 0 ) + (int) ( $record['output_tokens'] ?? 0 );
						$latency = (int) ( $record['latency_ms'] ?? 0 );
						$tok_str = class_exists( UsageTracker::class )
							? UsageTracker::format_tokens( $tokens )
							: number_format_i18n( $tokens );
						$aside   = $tok_str . ' tok' . ( $latency > 0 ? ' · ' . $latency . 'ms' : '' );

						$log_items[] = [
							'when'      => $ago,
							'logo_html' => $provider_logo( $pid ),
							'label'     => $pname,
							'pill'      => false,
							'detail'    => $model,
							'aside'     => $aside,
						];
					}
					Wenpai_Admin_UI::log_list( [ 'items' => $log_items ] );
				}
				?>
			</div>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();

		// ---- 3. 底部折叠卡：模块健康 + 文派生态插件调用 ----
		$module_rows_html = '';
		foreach ( $modules as $m ) {
			$enabled    = ! empty( $m['enabled'] );
			$title_html = esc_html( (string) ( $m['name'] ?? '' ) );
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
				'meta'    => __( '模块开关与详细配置在「模块」页。', 'wpmind' ),
				'actions' => [
					[
						'label'   => __( '模块管理', 'wpmind' ),
						'href'    => $modules_url,
						'variant' => 'secondary',
					],
				],
			]
		);

		$plugin_labels   = [
			'wpslug'      => __( '文派别名（WPSlug）', 'wpmind' ),
			'bisheng'     => __( '笔笙（Bisheng）', 'wpmind' ),
			'wpicp'       => __( '文派合规（WPICP）', 'wpmind' ),
			'wpdocs'      => __( '文派文档（WPDocs）', 'wpmind' ),
			'wpsss'       => __( '文派智储（WPSSS）', 'wpmind' ),
			'wpcomments'  => __( '文派评论（WPComments）', 'wpmind' ),
			'api-gateway' => __( '外部 API 网关', 'wpmind' ),
			'wpmind'      => __( '文派心思内置模块', 'wpmind' ),
		];
		$caller_rows_html = '';
		$caller_total_req = 0;
		$caller_count     = 0;
		if ( ! empty( $context_stats ) ) {
			$by_caller = [];
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
				$by_caller[ $caller ]['requests'] += (int) ( $ctx_row['requests'] ?? 0 );
				$by_caller[ $caller ]['tokens']   += (int) ( $ctx_row['input_tokens'] ?? 0 ) + (int) ( $ctx_row['output_tokens'] ?? 0 );
				$by_caller[ $caller ]['credits']  += (int) ( $ctx_row['credits'] ?? 0 );
			}
			uasort( $by_caller, static fn( $a, $b ) => $b['requests'] <=> $a['requests'] );
			$caller_count = count( $by_caller );
			foreach ( array_slice( $by_caller, 0, 8, true ) as $caller_slug => $agg ) {
				$caller_total_req += (int) $agg['requests'];
				$tok_fmt           = class_exists( UsageTracker::class )
					? UsageTracker::format_tokens( (int) $agg['tokens'] )
					: number_format_i18n( (int) $agg['tokens'] );
				$desc              = sprintf(
					/* translators: 1: tokens, 2: credits */
					__( '%1$s tok%2$s', 'wpmind' ),
					$tok_fmt,
					(int) $agg['credits'] > 0
						? ' · ' . number_format_i18n( (int) $agg['credits'] ) . ' ' . $unit_label
						: ''
				);
				$caller_rows_html .= Wenpai_Admin_UI::row_html(
					[
						'icon_html' => AdminIcons::svg( 'modules' ),
						'title'     => $plugin_labels[ $caller_slug ] ?? $caller_slug,
						'desc'      => $desc,
						'pill'      => [
							'text'  => sprintf(
								/* translators: %s: call count */
								__( '%s 次', 'wpmind' ),
								number_format_i18n( (int) $agg['requests'] )
							),
							'state' => 'ok',
						],
					]
				);
			}
		} else {
			$caller_rows_html = '<p class="meta">' . esc_html__( '文派系列插件或站内模块发起 AI 调用后，会按来源自动归因统计。', 'wpmind' ) . '</p>';
		}
		$caller_rows_html .= Wenpai_Admin_UI::card_foot_html(
			[
				'start'   => true,
				'meta'    => __( '全站插件统一经 WPMind PHP 接口调用，共用路由与预算。', 'wpmind' ),
				'actions' => [
					[
						'label'   => __( '查看分析', 'wpmind' ),
						'href'    => $analytics_url,
						'variant' => 'ghost',
					],
					[
						'label'   => __( '任务记录', 'wpmind' ),
						'href'    => $workflow_url,
						'variant' => 'ghost',
					],
				],
			]
		);

		Wenpai_Admin_UI::card_open();
		Wenpai_Admin_UI::fold_list(
			[
				'items' => [
					[
						'title'     => __( '模块健康', 'wpmind' ),
						'desc'      => sprintf(
							/* translators: 1: active, 2: total */
							__( '%1$d / %2$d 已开启', 'wpmind' ),
							$active_count,
							count( $modules )
						),
						'body_html' => $module_rows_html,
					],
					[
						'title'     => __( '生态插件调用', 'wpmind' ),
						'desc'      => $caller_count > 0
							? sprintf(
								/* translators: 1: caller count, 2: requests */
								__( '%1$d 个来源 · 累计 %2$s 次', 'wpmind' ),
								$caller_count,
								number_format_i18n( $caller_total_req )
							)
							: __( '暂无跨插件调用记录', 'wpmind' ),
						'body_html' => $caller_rows_html,
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>
	<?php
} )();
