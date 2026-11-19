<?php
/**
 * 概览视图：next 提示 + statbar + AI 服务动态 + 模块健康 fold（基于 kit v0.11.0 helper）。
 * 数据源与旧 templates/tabs/overview.php 一致（UsageTracker / FailoverManager / ModuleLoader）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;
use WPMind\Failover\FailoverManager;
use WPMind\Modules\CostControl\UsageTracker;

// ---- 数据准备（与旧概览一致） ----
$today        = UsageTracker::get_today_stats();
$month        = UsageTracker::get_month_stats();
$failover     = FailoverManager::instance();
$providers    = $failover->get_status_summary();
$modules      = ModuleLoader::instance()->get_modules();
$active_count = count( array_filter( $modules, static fn( $m ) => $m['enabled'] ) );

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

$recent_history = UsageTracker::get_history( 5 );

$cache_stats = function_exists( 'wpmind_get_cache_stats' )
	? wpmind_get_cache_stats()
	: [ 'enabled' => false, 'hits' => 0, 'misses' => 0, 'hit_rate' => 0 ];
$cache_enabled  = ! empty( $cache_stats['enabled'] );
$cache_hit_rate = isset( $cache_stats['hit_rate'] ) ? (float) $cache_stats['hit_rate'] : 0.0;
$cache_total    = (int) ( $cache_stats['hits'] ?? 0 ) + (int) ( $cache_stats['misses'] ?? 0 );

// Provider 本地图标：使用 kit logo_html 插槽。
$wpmind_provider_logo = static function ( string $pid ): string {
	$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
	if ( file_exists( $svg ) ) {
		return Wenpai_Admin_UI::logo_html(
			[
				'src' => WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg',
				'alt' => $pid,
			]
		);
	}
	return AdminIcons::svg( 'robot' );
};

// 状态 → pill。
$wpmind_state_pill = static function ( string $state ): array {
	$map = [
		'healthy'   => [ '正常', 'ok' ],
		'closed'    => [ '正常', 'ok' ],
		'half_open' => [ '恢复中', 'warn' ],
		'open'      => [ '熔断中', 'bad' ],
	];
	return $map[ $state ] ?? [ $state, 'mute' ];
};

// ---- next 提示条：需要处理的事才出现 ----
$wpmind_next = null;
if ( empty( $providers ) ) {
	$wpmind_next = [
		'state'   => 'bad',
		'icon'    => 'alert',
		'title'   => __( '还没有配置 AI Provider', 'wpmind' ),
		'desc'    => __( '添加 Provider 并填入 API Key 后，即可使用智能路由与全部 AI 模块。', 'wpmind' ),
		'actions' => [
			[ 'label' => __( '去配置', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'services' ] ), 'variant' => 'primary' ],
		],
	];
} elseif ( $down > 0 ) {
	$wpmind_next = [
		'state'   => 'warn',
		'icon'    => 'alert',
		'title'   => sprintf( /* translators: %d: open breakers */ __( '%d 个 Provider 熔断中', 'wpmind' ), $down ),
		'desc'    => __( '路由已自动切换到可用备选；恢复后自动回切，也可在服务页手动重置。', 'wpmind' ),
		'actions' => [
			[ 'label' => __( '查看 AI 服务', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'services' ] ), 'variant' => 'primary' ],
			[ 'label' => __( '任务记录', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'workflow' ] ), 'variant' => 'ghost' ],
		],
	];
} elseif ( $testing > 0 ) {
	$wpmind_next = [
		'state'   => 'warn',
		'icon'    => 'refresh',
		'title'   => sprintf( /* translators: %d: half-open */ __( '%d 个 Provider 恢复测试中', 'wpmind' ), $testing ),
		'desc'    => __( '熔断器半开状态：试探性请求恢复中，成功后自动闭合。', 'wpmind' ),
		'actions' => [
			[ 'label' => __( '查看 AI 服务', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'services' ] ), 'variant' => 'primary' ],
		],
	];
}

wpmind_page_head(
	__( '概览', 'wpmind' ),
	__( 'AI 服务中枢一页看状态：熔断、用量、缓存命中、模块健康。需要处理的事会出现在顶部提示里。', 'wpmind' )
);
?>

<div class="wenpai-stack">
	<?php
	if ( $wpmind_next ) {
		Wenpai_Admin_UI::next( $wpmind_next );
	}

	// ---- 指标条（kit statbar） ----
	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label' => __( '今日请求', 'wpmind' ),
					'value' => number_format_i18n( (int) ( $today['requests'] ?? 0 ) ),
					'unit'  => UsageTracker::format_tokens( (int) ( $today['input_tokens'] ?? 0 ) + (int) ( $today['output_tokens'] ?? 0 ) ) . ' tok',
				],
				[
					'label' => __( 'AI 服务健康度', 'wpmind' ),
					'value' => $healthy . '/' . count( $providers ),
					'unit'  => $down > 0 ? sprintf( /* translators: %d: breakers */ __( '%d 熔断', 'wpmind' ), $down ) : __( '全部在线', 'wpmind' ),
				],
				[
					'label' => __( '本月费用', 'wpmind' ),
					'value' => UsageTracker::format_cost_by_currency( $month['cost_usd'] ?? 0, $month['cost_cny'] ?? 0 ),
				],
				[
					'label' => __( '缓存命中率', 'wpmind' ),
					'value' => $cache_enabled ? number_format_i18n( $cache_hit_rate, 1 ) . '%' : '—',
					'unit'  => $cache_enabled ? number_format_i18n( $cache_total ) . ' ' . __( '查询', 'wpmind' ) : __( '未启用', 'wpmind' ),
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- AI 服务动态（kit card + grid-2 + row + log_list） ----
	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( 'AI 服务动态', 'wpmind' ),
			'desc'              => sprintf(
				/* translators: 1: provider count, 2: active module count, 3: total module count */
				__( '%1$d 个 Provider · %2$d/%3$d 模块启用', 'wpmind' ),
				count( $providers ),
				$active_count,
				count( $modules )
			),
			'icon'              => 'sparkle',
			'head_control_html' => Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '管理服务', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'services' ] ),
					'variant' => 'ghost',
				]
			),
		]
	);
	?>
	<div class="wenpai-grid-2">
		<div class="wenpai-stack wenpai-stack--sm">
			<?php
			Wenpai_Admin_UI::section_title( [ 'title' => __( 'Provider 健康', 'wpmind' ) ] );
			if ( empty( $providers ) ) {
				Wenpai_Admin_UI::empty_state(
					[
						'title'   => __( '还没有配置 Provider', 'wpmind' ),
						'desc'    => __( '添加至少一个 AI 服务商后，这里会显示实时健康状态。', 'wpmind' ),
						'actions' => [
							[ 'label' => __( '去配置', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'services' ] ), 'variant' => 'primary' ],
						],
					]
				);
			} else {
				foreach ( $providers as $pid => $p ) {
					$state = (string) ( $p['state'] ?? 'unknown' );
					[ $pill_text, $pill_state ] = $wpmind_state_pill( $state );
					$desc = (string) ( $p['state_label'] ?? $state );
					if ( ! empty( $p['recovery_in'] ) && 'half_open' === $state ) {
						$desc .= ' · ' . sprintf( /* translators: %s: time */ __( '%s 后恢复测试', 'wpmind' ), (string) $p['recovery_in'] );
					}
					if ( isset( $p['success_rate'] ) && 'healthy' === $state ) {
						$desc .= ' · ' . sprintf( /* translators: %d: rate */ __( '成功率 %d%%', 'wpmind' ), (int) $p['success_rate'] );
					}
					Wenpai_Admin_UI::row(
						[
							'logo_html' => $wpmind_provider_logo( (string) $pid ),
							'title'     => (string) ( $p['display_name'] ?? UsageTracker::get_provider_display_name( (string) $pid ) ),
							'desc'      => $desc,
							'pill'      => [ 'text' => $pill_text, 'state' => $pill_state ],
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
				echo Wenpai_Admin_UI::button_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
					[
						'label'   => __( '查看全部', 'wpmind' ),
						'href'    => AdminPage::url( [ 'tab' => 'analytics' ] ),
						'variant' => 'ghost',
					]
				);
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
					$pname   = UsageTracker::get_provider_display_name( $pid );
					$model   = (string) ( $record['model'] ?? '—' );
					$tokens  = (int) ( $record['input_tokens'] ?? 0 ) + (int) ( $record['output_tokens'] ?? 0 );
					$latency = (int) ( $record['latency_ms'] ?? 0 );
					$aside   = UsageTracker::format_tokens( $tokens ) . ( $latency > 0 ? ' · ' . $latency . 'ms' : '' );

					$log_items[] = [
						'when'      => $ago,
						'logo_html' => $wpmind_provider_logo( $pid ),
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

	// ---- 模块健康（kit fold_list + row title_html + card_foot） ----
	$module_rows_html = '';
	foreach ( $modules as $mid => $m ) {
		$enabled    = (bool) $m['enabled'];
		$title_html = esc_html( (string) $m['name'] );
		if ( empty( $m['can_disable'] ) ) {
			$title_html .= ' ' . Wenpai_Admin_UI::pill_html( [ 'text' => __( '核心', 'wpmind' ), 'state' => 'mute' ] );
		}
		$module_rows_html .= Wenpai_Admin_UI::row_html(
			[
				'icon_html'  => AdminIcons::svg( $m['icon'] ?? 'modules' ) ?: AdminIcons::svg( 'modules' ),
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
	$module_rows_html .= Wenpai_Admin_UI::card_foot_html(
		[
			'start'   => true,
			'note'    => __( '模块开关与设置在「模块」页。', 'wpmind' ),
			'actions' => [
				[ 'label' => __( '模块管理', 'wpmind' ), 'href' => AdminPage::url( [ 'tab' => 'modules' ] ), 'variant' => 'secondary' ],
			],
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::fold_list(
		[
			'items' => [
				[
					'title'     => __( '模块健康', 'wpmind' ),
					'desc'      => sprintf( /* translators: 1: active, 2: total */ __( '%1$d / %2$d 已启用', 'wpmind' ), $active_count, count( $modules ) ),
					'body_html' => $module_rows_html,
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>
