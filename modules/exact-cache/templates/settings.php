<?php
/**
 * Exact Cache settings template.
 *
 * @package WPMind\Modules\ExactCache
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$cache_enabled = get_option( 'wpmind_exact_cache_enabled', '1' );
$default_ttl   = (int) get_option( 'wpmind_exact_cache_default_ttl', 900 );
$max_entries   = (int) get_option( 'wpmind_exact_cache_max_entries', 500 );
$scope_mode    = get_option( 'wpmind_exact_cache_scope_mode', 'role' );

$cache_stats   = \WPMind\Cache\ExactCache::instance()->get_stats();
$hits          = (int) ( $cache_stats['hits'] ?? 0 );
$misses        = (int) ( $cache_stats['misses'] ?? 0 );
$total_req     = $hits + $misses;
$hit_rate      = $total_req > 0 ? round( $hits / $total_req * 100, 1 ) : 0;
$entries       = (int) ( $cache_stats['entries'] ?? 0 );

$savings = \WPMind\Modules\ExactCache\CostEstimator::get_estimated_savings();
?>

<!-- Header -->
<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-database-2-line"></span>
		<?php esc_html_e( '精确缓存', 'wpmind' ); ?>
	</h2>
	<span class="wpmind-module-badge">v1.0</span>
	<button type="button" id="wpmind-flush-cache" class="button button-small" title="<?php esc_attr_e( '清空缓存', 'wpmind' ); ?>">
		<span class="dashicons ri-delete-bin-line"></span>
		<?php esc_html_e( '清空缓存', 'wpmind' ); ?>
	</button>
	<button type="button" id="wpmind-reset-cache-stats" class="button button-small" title="<?php esc_attr_e( '重置统计', 'wpmind' ); ?>">
		<span class="dashicons ri-restart-line"></span>
		<?php esc_html_e( '重置统计', 'wpmind' ); ?>
	</button>
</div>

<div class="wpmind-tab-pane-body">
<div class="wpmind-cache-panel">

	<p class="wpmind-module-desc">
		<?php esc_html_e( 'AI 请求精确缓存 - 相同请求直接返回缓存结果，降低 API 成本、加速响应。', 'wpmind' ); ?>
	</p>

	<!-- Stats Cards -->
	<div class="wpmind-module-stats">
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon"><span class="dashicons ri-percent-line"></span></div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-cache-hit-rate"><?php echo esc_html( $hit_rate . '%' ); ?></span>
				<span class="wpmind-stat-label"><?php esc_html_e( '命中率', 'wpmind' ); ?></span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon"><span class="dashicons ri-stack-line"></span></div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-cache-entries"><?php echo esc_html( $entries ); ?></span>
				<span class="wpmind-stat-label"><?php esc_html_e( '缓存条目', 'wpmind' ); ?></span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon"><span class="dashicons ri-money-cny-circle-line"></span></div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-cache-savings" title="<?php esc_attr_e( '估算值 = 缓存命中次数 x 平均每次请求成本', 'wpmind' ); ?>">
					<?php echo esc_html( '$' . $savings['total_usd'] ); ?>
				</span>
				<span class="wpmind-stat-label"><?php esc_html_e( '节省成本 (估算)', 'wpmind' ); ?></span>
			</div>
		</div>
		<div class="wpmind-stat-card">
			<div class="wpmind-stat-icon"><span class="dashicons ri-bar-chart-box-line"></span></div>
			<div class="wpmind-stat-content">
				<span class="wpmind-stat-value" id="wpmind-cache-total-req"><?php echo esc_html( $total_req ); ?></span>
				<span class="wpmind-stat-label"><?php esc_html_e( '总请求', 'wpmind' ); ?></span>
			</div>
		</div>
	</div>

	<!-- 7-Day Trend Chart -->
	<div class="wpmind-cache-trend-chart">
		<h3>
			<span class="dashicons ri-line-chart-line"></span>
			<?php esc_html_e( '7 天趋势', 'wpmind' ); ?>
			<button type="button" class="button button-small wpmind-refresh-cache-stats" title="<?php esc_attr_e( '刷新统计', 'wpmind' ); ?>">
				<span class="dashicons ri-refresh-line"></span>
			</button>
		</h3>
		<div class="wpmind-chart-wrapper">
			<canvas id="wpmind-cache-trend-canvas"></canvas>
		</div>
	</div>

	<!-- Settings Form -->
	<h3>
		<span class="dashicons ri-settings-3-line"></span>
		<?php esc_html_e( '缓存设置', 'wpmind' ); ?>
	</h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( '启用缓存', 'wpmind' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="wpmind_cache_enabled" value="1" <?php checked( $cache_enabled, '1' ); ?>>
					<?php esc_html_e( '启用 AI 请求精确缓存', 'wpmind' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '默认 TTL (秒)', 'wpmind' ); ?></th>
			<td>
				<input type="number" name="wpmind_cache_default_ttl" value="<?php echo esc_attr( $default_ttl ); ?>" min="0" max="86400" step="60" class="small-text">
				<p class="description"><?php esc_html_e( '缓存过期时间，0 表示永不过期，最大 86400 (24小时)。', 'wpmind' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '最大条目数', 'wpmind' ); ?></th>
			<td>
				<input type="number" name="wpmind_cache_max_entries" value="<?php echo esc_attr( $max_entries ); ?>" min="10" max="5000" step="10" class="small-text">
				<p class="description"><?php esc_html_e( '超出上限时自动淘汰最久未访问的条目 (LRU)。范围: 10-5000。', 'wpmind' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '隔离模式', 'wpmind' ); ?></th>
			<td>
				<select name="wpmind_cache_scope_mode">
					<option value="role" <?php selected( $scope_mode, 'role' ); ?>><?php esc_html_e( '按角色隔离 (推荐)', 'wpmind' ); ?></option>
					<option value="user" <?php selected( $scope_mode, 'user' ); ?>><?php esc_html_e( '按用户隔离', 'wpmind' ); ?></option>
					<option value="none" <?php selected( $scope_mode, 'none' ); ?>><?php esc_html_e( '不隔离 (全局共享)', 'wpmind' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( '决定缓存 key 的隔离粒度。角色隔离是最安全的默认值。', 'wpmind' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="button" id="wpmind-save-cache-settings" class="button button-primary">
			<?php esc_html_e( '保存设置', 'wpmind' ); ?>
		</button>
	</p>

</div>
</div>