<?php
/**
 * Exact Cache 模块设置（kit 化）。
 * 保留 JS 契约：#wpmind-flush-cache / #wpmind-reset-cache-stats /
 * .wpmind-refresh-cache-stats / #wpmind-cache-hit-rate / -entries /
 * -savings / -total-req / #wpmind-cache-trend-canvas /
 * input[name=wpmind_cache_*] / #wpmind-save-cache-settings。
 *
 * @package WPMind\Modules\ExactCache
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminIcons;

$cache_enabled = get_option( 'wpmind_exact_cache_enabled', '1' );
$default_ttl   = (int) get_option( 'wpmind_exact_cache_default_ttl', 900 );
$max_entries   = (int) get_option( 'wpmind_exact_cache_max_entries', 500 );
$scope_mode    = get_option( 'wpmind_exact_cache_scope_mode', 'role' );

$cache_stats = \WPMind\Cache\ExactCache::instance()->get_stats();
$hits        = (int) ( $cache_stats['hits'] ?? 0 );
$misses      = (int) ( $cache_stats['misses'] ?? 0 );
$total_req   = $hits + $misses;
$hit_rate    = $total_req > 0 ? round( $hits / $total_req * 100, 1 ) : 0;
$entries     = (int) ( $cache_stats['entries'] ?? 0 );

$savings = \WPMind\Modules\ExactCache\CostEstimator::get_estimated_savings();
?>

<div class="wpmind-statbar" style="margin-bottom:var(--wenpai-space-5);">
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'analytics', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '命中率', 'wpmind' ); ?></div>
			<div class="v" id="wpmind-cache-hit-rate"><?php echo esc_html( $hit_rate . '%' ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'modules', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '缓存条目', 'wpmind' ); ?></div>
			<div class="v" id="wpmind-cache-entries"><?php echo esc_html( (string) $entries ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'wallet', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '节省成本（估算）', 'wpmind' ); ?></div>
			<div class="v" id="wpmind-cache-savings" title="<?php esc_attr_e( '估算值 = 缓存命中次数 × 平均每次请求成本', 'wpmind' ); ?>">$<?php echo esc_html( (string) $savings['total_usd'] ); ?></div>
		</div>
	</div>
	<div class="wpmind-statbar-item">
		<?php echo AdminIcons::svg( 'route', 'wenpai-ico wpmind-statbar-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wpmind-statbar-text">
			<div class="k"><?php esc_html_e( '总请求', 'wpmind' ); ?></div>
			<div class="v" id="wpmind-cache-total-req"><?php echo esc_html( (string) $total_req ); ?></div>
		</div>
	</div>
</div>

<section class="card">
	<div class="card-head">
		<span class="tile"><?php echo AdminIcons::svg( 'analytics' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
		<div>
			<h2 class="card-title"><?php esc_html_e( '7 天趋势', 'wpmind' ); ?></h2>
			<p class="card-sub"><?php esc_html_e( '缓存命中与写入走势', 'wpmind' ); ?></p>
		</div>
		<button type="button" class="btn btn-sm btn-ghost wpmind-refresh-cache-stats"><?php echo AdminIcons::svg( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></button>
	</div>
	<div class="wpmind-chart is-live"><canvas id="wpmind-cache-trend-canvas"></canvas></div>
</section>

<section class="card">
	<div class="card-head">
		<span class="tile"><?php echo AdminIcons::svg( 'modules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit 静态 SVG。 ?></span>
		<div>
			<h2 class="card-title"><?php esc_html_e( '缓存设置', 'wpmind' ); ?></h2>
			<p class="card-sub"><?php esc_html_e( '相同请求直接返回缓存结果，降低 API 成本、加速响应。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div class="wenpai-field wenpai-field--toggle field wenpai-field--row">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-enabled"><?php esc_html_e( '启用缓存', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<span class="wenpai-switch">
				<input type="checkbox" role="switch" class="wenpai-switch__input" id="wpmind-cache-enabled" name="wpmind_cache_enabled" value="1" <?php checked( $cache_enabled, '1' ); ?>>
				<span class="wenpai-switch__track" aria-hidden="true"></span>
				<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已开启', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '未开启', 'wpmind' ); ?></span></span>
			</span>
			<p class="wenpai-field__desc"><?php esc_html_e( '启用 AI 请求精确缓存。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div class="wenpai-field field wenpai-field--row">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-ttl"><?php esc_html_e( '默认 TTL（秒）', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<input type="number" id="wpmind-cache-ttl" name="wpmind_cache_default_ttl" value="<?php echo esc_attr( (string) $default_ttl ); ?>" min="0" max="86400" step="60" style="max-width:10rem">
			<p class="wenpai-field__desc"><?php esc_html_e( '缓存过期时间，0 表示永不过期，最大 86400（24 小时）。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div class="wenpai-field field wenpai-field--row">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-max"><?php esc_html_e( '最大条目数', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<input type="number" id="wpmind-cache-max" name="wpmind_cache_max_entries" value="<?php echo esc_attr( (string) $max_entries ); ?>" min="10" max="5000" step="10" style="max-width:10rem">
			<p class="wenpai-field__desc"><?php esc_html_e( '超出上限时自动淘汰最久未访问的条目（LRU）。范围 10–5000。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div class="wenpai-field field wenpai-field--row">
		<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-scope"><?php esc_html_e( '隔离模式', 'wpmind' ); ?></label></div>
		<div class="field-ctl">
			<select id="wpmind-cache-scope" name="wpmind_cache_scope_mode" style="max-width:420px">
				<option value="role" <?php selected( $scope_mode, 'role' ); ?>><?php esc_html_e( '按角色隔离（推荐）', 'wpmind' ); ?></option>
				<option value="user" <?php selected( $scope_mode, 'user' ); ?>><?php esc_html_e( '按用户隔离', 'wpmind' ); ?></option>
				<option value="none" <?php selected( $scope_mode, 'none' ); ?>><?php esc_html_e( '不隔离（全局共享）', 'wpmind' ); ?></option>
			</select>
			<p class="wenpai-field__desc"><?php esc_html_e( '决定缓存 key 的隔离粒度。角色隔离是最安全的默认值。', 'wpmind' ); ?></p>
		</div>
	</div>

	<div class="card-foot">
		<span class="wenpai-actions">
			<button type="button" class="btn btn-ghost" id="wpmind-flush-cache"><?php esc_html_e( '清空缓存', 'wpmind' ); ?></button>
			<button type="button" class="btn btn-ghost" id="wpmind-reset-cache-stats"><?php esc_html_e( '重置统计', 'wpmind' ); ?></button>
			<button type="button" class="btn btn-primary" id="wpmind-save-cache-settings"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
		</span>
	</div>
</section>
