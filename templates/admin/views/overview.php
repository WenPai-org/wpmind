<?php
/**
 * 概览视图：next 提示 + statbar + AI 服务动态 + 模块健康 fold。
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
$cache_enabled = ! empty( $cache_stats['enabled'] );
$cache_hit_rate = isset( $cache_stats['hit_rate'] ) ? (float) $cache_stats['hit_rate'] : 0.0;
$cache_total    = (int) ( $cache_stats['hits'] ?? 0 ) + (int) ( $cache_stats['misses'] ?? 0 );

// Provider 本地图标：assets/images/providers/<id>.svg，缺失退回通用图标。
$wpmind_provider_mark = static function ( string $pid ): string {
	$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
	if ( file_exists( $svg ) ) {
		return '<img class="wpmind-avatar-img" src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg' ) . '" alt="" width="16" height="16">';
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
		'tone'   => 'bad',
		'icon'   => 'alert',
		'title'  => __( '还没有配置 AI Provider', 'wpmind' ),
		'meta'   => __( '添加 Provider 并填入 API Key 后，即可使用智能路由与全部 AI 模块。', 'wpmind' ),
		'primary' => [ AdminPage::url( [ 'tab' => 'services' ] ), __( '去配置', 'wpmind' ) ],
		'ghost'   => null,
	];
} elseif ( $down > 0 ) {
	$wpmind_next = [
		'tone'   => 'warn',
		'icon'   => 'alert',
		'title'  => sprintf( /* translators: %d: open breakers */ __( '%d 个 Provider 熔断中', 'wpmind' ), $down ),
		'meta'   => __( '路由已自动切换到可用备选；恢复后自动回切，也可在服务页手动重置。', 'wpmind' ),
		'primary' => [ AdminPage::url( [ 'tab' => 'services' ] ), __( '查看 AI 服务', 'wpmind' ) ],
		'ghost'   => [ AdminPage::url( [ 'tab' => 'workflow' ] ), __( '任务记录', 'wpmind' ) ],
	];
} elseif ( $testing > 0 ) {
	$wpmind_next = [
		'tone'   => 'warn',
		'icon'   => 'refresh',
		'title'  => sprintf( /* translators: %d: half-open */ __( '%d 个 Provider 恢复测试中', 'wpmind' ), $testing ),
		'meta'   => __( '熔断器半开状态：试探性请求恢复中，成功后自动闭合。', 'wpmind' ),
		'primary' => [ AdminPage::url( [ 'tab' => 'services' ] ), __( '查看 AI 服务', 'wpmind' ) ],
		'ghost'   => null,
	];
}

wpmind_page_head(
	__( '概览', 'wpmind' ),
	__( 'AI 服务中枢一页看状态：熔断、用量、缓存命中、模块健康。需要处理的事会出现在顶部提示里。', 'wpmind' )
);
?>

<div class="wenpai-stack">

	<?php if ( $wpmind_next ) : ?>
	<div class="next">
		<span class="tile <?php echo esc_attr( $wpmind_next['tone'] ); ?>"><?php echo AdminIcons::svg( $wpmind_next['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong><?php echo esc_html( $wpmind_next['title'] ); ?></strong>
			<div class="meta"><?php echo esc_html( $wpmind_next['meta'] ); ?></div>
		</div>
		<div class="next-actions">
			<a class="btn btn-primary" href="<?php echo esc_url( $wpmind_next['primary'][0] ); ?>"><?php echo esc_html( $wpmind_next['primary'][1] ); ?></a>
			<?php if ( $wpmind_next['ghost'] ) : ?>
			<a class="btn btn-ghost" href="<?php echo esc_url( $wpmind_next['ghost'][0] ); ?>"><?php echo esc_html( $wpmind_next['ghost'][1] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<section class="card">
		<div class="wpmind-statbar">
			<div class="wpmind-statbar-item">
				<?php echo AdminIcons::svg( 'services', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="wpmind-statbar-text">
					<div class="k"><?php esc_html_e( '今日请求', 'wpmind' ); ?></div>
					<div class="v"><?php echo esc_html( number_format_i18n( (int) ( $today['requests'] ?? 0 ) ) ); ?> <small><?php echo esc_html( UsageTracker::format_tokens( (int) ( $today['input_tokens'] ?? 0 ) + (int) ( $today['output_tokens'] ?? 0 ) ) . ' tok' ); ?></small></div>
				</div>
			</div>
			<div class="wpmind-statbar-item">
				<?php echo AdminIcons::svg( 'services', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="wpmind-statbar-text">
					<div class="k"><?php esc_html_e( 'AI 服务健康度', 'wpmind' ); ?></div>
					<div class="v"><?php echo esc_html( $healthy . '/' . count( $providers ) ); ?> <small><?php echo esc_html( $down > 0 ? sprintf( /* translators: %d: breakers */ __( '%d 熔断', 'wpmind' ), $down ) : __( '全部在线', 'wpmind' ) ); ?></small></div>
				</div>
			</div>
			<div class="wpmind-statbar-item">
				<?php echo AdminIcons::svg( 'wallet', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="wpmind-statbar-text">
					<div class="k"><?php esc_html_e( '本月费用', 'wpmind' ); ?></div>
					<div class="v"><?php echo esc_html( UsageTracker::format_cost_by_currency( $month['cost_usd'] ?? 0, $month['cost_cny'] ?? 0 ) ); ?></div>
				</div>
			</div>
			<div class="wpmind-statbar-item">
				<?php echo AdminIcons::svg( 'modules', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="wpmind-statbar-text">
					<div class="k"><?php esc_html_e( '缓存命中率', 'wpmind' ); ?></div>
					<div class="v"><?php echo $cache_enabled ? esc_html( number_format_i18n( $cache_hit_rate, 1 ) . '%' ) : '—'; ?> <small><?php echo $cache_enabled ? esc_html( number_format_i18n( $cache_total ) . ' ' . __( '查询', 'wpmind' ) ) : esc_html__( '未启用', 'wpmind' ); ?></small></div>
				</div>
			</div>
		</div>
	</section>

	<section class="card">
		<div class="wpmind-card-head">
			<span class="wpmind-card-head-ico"><?php echo AdminIcons::svg( 'robot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="wpmind-card-head-text">
				<h2 class="section-title"><?php esc_html_e( 'AI 服务动态', 'wpmind' ); ?></h2>
				<div class="meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: provider count, 2: active module count, 3: total module count */
							__( '%1$d 个 Provider · %2$d/%3$d 模块启用', 'wpmind' ),
							count( $providers ),
							$active_count,
							count( $modules )
						)
					);
					?>
				</div>
			</div>
			<a class="btn btn-ghost" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'services' ] ) ); ?>"><?php esc_html_e( '管理服务', 'wpmind' ); ?></a>
		</div>
		<div class="wpmind-split">
			<div class="wpmind-col">
				<div class="wpmind-col-title"><?php echo AdminIcons::svg( 'services' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Provider 健康', 'wpmind' ); ?></div>
				<?php if ( empty( $providers ) ) : ?>
					<div class="wpmind-feed-item">
						<div class="t"><?php esc_html_e( '还没有配置 Provider', 'wpmind' ); ?></div>
						<div class="d"><a href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'services' ] ) ); ?>"><?php esc_html_e( '去配置 →', 'wpmind' ); ?></a></div>
					</div>
				<?php else : ?>
					<div class="wpmind-health">
						<?php foreach ( $providers as $pid => $p ) : ?>
							<?php
							$state = (string) ( $p['state'] ?? 'unknown' );
							[ $pill_text, $pill_state ] = $wpmind_state_pill( $state );
							$desc   = (string) ( $p['state_label'] ?? $state );
							if ( ! empty( $p['recovery_in'] ) && 'half_open' === $state ) {
								$desc .= ' · ' . sprintf( /* translators: %s: time */ __( '%s 后恢复测试', 'wpmind' ), (string) $p['recovery_in'] );
							}
							if ( isset( $p['success_rate'] ) && 'healthy' === $state ) {
								$desc .= ' · ' . sprintf( /* translators: %d: rate */ __( '成功率 %d%%', 'wpmind' ), (int) $p['success_rate'] );
							}
							?>
						<div class="wpmind-health-row">
							<span class="wpmind-avatar"><?php echo $wpmind_provider_mark( (string) $pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div class="wpmind-health-main"><div class="t"><?php echo esc_html( (string) ( $p['display_name'] ?? UsageTracker::get_provider_display_name( (string) $pid ) ) ); ?></div><div class="d"><?php echo esc_html( $desc ); ?></div></div>
							<span class="pill <?php echo esc_attr( $pill_state ); ?>"><?php echo esc_html( $pill_text ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="wpmind-col wpmind-col-right">
				<div class="wpmind-col-title"><?php echo AdminIcons::svg( 'workflow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( '最近活动', 'wpmind' ); ?><a class="wpmind-col-more" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'analytics' ] ) ); ?>"><?php esc_html_e( '查看全部', 'wpmind' ); ?></a></div>
				<?php if ( empty( $recent_history ) ) : ?>
					<div class="meta"><?php esc_html_e( '暂无 API 调用记录，使用 AI 功能后显示在这里。', 'wpmind' ); ?></div>
				<?php else : ?>
					<div class="wpmind-feed">
						<?php foreach ( $recent_history as $record ) : ?>
							<?php
							$ts      = (int) ( $record['timestamp'] ?? 0 );
							$ago     = $ts > 0 ? human_time_diff( $ts, time() ) . __( '前', 'wpmind' ) : '—';
							$pid     = (string) ( $record['provider'] ?? '' );
							$pname   = UsageTracker::get_provider_display_name( $pid );
							$model   = (string) ( $record['model'] ?? '—' );
							$tokens  = (int) ( $record['input_tokens'] ?? 0 ) + (int) ( $record['output_tokens'] ?? 0 );
							$latency = (int) ( $record['latency_ms'] ?? 0 );
							?>
						<div class="wpmind-feed-item">
							<span class="wpmind-feed-time"><?php echo esc_html( $ago ); ?></span>
							<span class="wpmind-feed-main"><span class="wpmind-feed-provider"><?php echo $wpmind_provider_mark( $pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $pname ); ?></span><span class="wpmind-feed-model"><?php echo esc_html( $model ); ?></span></span>
							<span class="wpmind-feed-meta"><span><?php echo esc_html( UsageTracker::format_tokens( $tokens ) ); ?></span><?php if ( $latency > 0 ) : ?><span><?php echo esc_html( $latency . 'ms' ); ?></span><?php endif; ?></span>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<details class="card wpmind-fold">
		<summary>
			<span class="wpmind-fold-chevron"><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="section-title"><?php esc_html_e( '模块健康', 'wpmind' ); ?></span>
			<span class="meta"><?php echo esc_html( sprintf( /* translators: 1: active, 2: total */ __( '%1$d / %2$d 已启用', 'wpmind' ), $active_count, count( $modules ) ) ); ?></span>
		</summary>
		<div class="wpmind-fold-body">
			<div>
				<?php
				$wpmind_module_icons = [
					'cost-control'       => 'wallet',
					'analytics'          => 'analytics',
					'exact-cache'        => 'zap',
					'geo'                => 'globe',
					'api-gateway'        => 'route',
					'media-intelligence' => 'image',
					'auto-meta'          => 'doc',
				];
				foreach ( $modules as $mid => $m ) :
					$enabled = (bool) $m['enabled'];
					?>
				<div class="simple-row">
					<span class="tile <?php echo $enabled ? 'ok' : ''; ?>"><?php echo AdminIcons::svg( $wpmind_module_icons[ $mid ] ?? 'modules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<div class="t"><?php echo esc_html( (string) $m['name'] ); ?><?php if ( empty( $m['can_disable'] ) ) : ?> <span class="pill mute"><?php esc_html_e( '核心', 'wpmind' ); ?></span><?php endif; ?></div>
						<div class="d"><?php echo esc_html( (string) ( $m['description'] ?? '' ) ); ?></div>
					</div>
					<div class="r"><span class="pill <?php echo $enabled ? 'ok' : 'mute'; ?>"><?php echo $enabled ? esc_html__( '已启用', 'wpmind' ) : esc_html__( '未启用', 'wpmind' ); ?></span></div>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="card-foot start">
				<span class="meta"><?php esc_html_e( '模块开关与设置在「模块」页。', 'wpmind' ); ?></span>
				<span class="wenpai-actions">
					<a class="btn btn-secondary" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'modules' ] ) ); ?>"><?php esc_html_e( '模块管理', 'wpmind' ); ?></a>
				</span>
			</div>
		</div>
	</details>
</div>
