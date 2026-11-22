<?php
/**
 * Exact Cache 模块设置（kit 化）：statbar + chart_frame + field--row + confirm_attrs。
 *
 * @package WPMind\Modules\ExactCache
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$cache_enabled = (string) get_option( 'wpmind_exact_cache_enabled', '1' );
	$default_ttl   = (int) get_option( 'wpmind_exact_cache_default_ttl', 900 );
	$max_entries   = (int) get_option( 'wpmind_exact_cache_max_entries', 500 );
	$scope_mode    = (string) get_option( 'wpmind_exact_cache_scope_mode', 'role' );

	$cache_stats = \WPMind\Cache\ExactCache::instance()->get_stats();
	$hits        = (int) ( $cache_stats['hits'] ?? 0 );
	$misses      = (int) ( $cache_stats['misses'] ?? 0 );
	$total_req   = $hits + $misses;
	$hit_rate    = $total_req > 0 ? round( $hits / $total_req * 100, 1 ) : 0;
	$entries     = (int) ( $cache_stats['entries'] ?? 0 );

	$savings     = \WPMind\Modules\ExactCache\CostEstimator::get_estimated_savings();
	$saved_usd   = (float) $savings['saved_usd'];
	$saved_cny   = (float) $savings['saved_cny'];
	$saved_label = class_exists( \WPMind\Modules\CostControl\UsageTracker::class )
		? \WPMind\Modules\CostControl\UsageTracker::format_cost_by_currency( $saved_usd, $saved_cny )
		: '¥0.00';

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label'       => __( '命中率', 'wpmind' ),
					'value'       => $hit_rate . '%',
					'value_attrs' => [ 'id' => 'wpmind-cache-hit-rate' ],
				],
				[
					'label'       => __( '缓存条目', 'wpmind' ),
					'value'       => (string) $entries,
					'value_attrs' => [ 'id' => 'wpmind-cache-entries' ],
				],
				[
					'label'       => __( '估算节省', 'wpmind' ),
					'value'       => $saved_label,
					'value_attrs' => [ 'id' => 'wpmind-cache-savings' ],
				],
				[
					'label'       => __( '总请求', 'wpmind' ),
					'value'       => (string) $total_req,
					'value_attrs' => [ 'id' => 'wpmind-cache-total-req' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '7 天缓存趋势', 'wpmind' ),
			'sub'               => __( '缓存命中与未命中走势', 'wpmind' ),
			'tile'              => false,
			'class'             => 'wpmind-chart',
			'head_control_html' => Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '刷新统计', 'wpmind' ),
					'variant' => 'ghost',
					'size'    => 'sm',
					'class'   => 'wpmind-refresh-cache-stats',
				]
			),
		]
	);
	Wenpai_Admin_UI::chart_frame(
		[
			'plot_html'  => '<canvas id="wpmind-cache-trend-canvas"></canvas>',
			'aria_label' => __( '7 天缓存命中趋势', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '缓存设置', 'wpmind' ),
			'sub'   => __( '相同请求直接复用缓存，降低 API 成本。', 'wpmind' ),
			'tile'  => false,
		]
	);
	?>
<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">
	<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-enabled"><?php esc_html_e( '启用缓存', 'wpmind' ); ?></label></div>
	<div class="field-ctl">
		<?php
		$wpmind_sw = Wenpai_Admin_Settings::switch_html(
			[
				'id'      => 'wpmind-cache-enabled',
				'name'    => 'wpmind_cache_enabled',
				'checked' => '1' === $cache_enabled,
				'on'      => __( '已开启', 'wpmind' ),
				'off'     => __( '未开启', 'wpmind' ),
			]
		);
		echo $wpmind_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<p class="wenpai-field__desc"><?php esc_html_e( '启用 AI 请求精确缓存。', 'wpmind' ); ?></p>
	</div>
</div>

<div class="wenpai-field wenpai-field--row field">
	<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-ttl"><?php esc_html_e( '默认 TTL（秒）', 'wpmind' ); ?></label></div>
	<div class="field-ctl">
		<input type="number" class="wpmind-num" id="wpmind-cache-ttl" name="wpmind_cache_default_ttl" value="<?php echo esc_attr( (string) $default_ttl ); ?>" min="0" max="86400" step="60">
		<p class="wenpai-field__desc"><?php esc_html_e( '0 表示永不过期，上限 86400 秒。', 'wpmind' ); ?></p>
	</div>
</div>

<div class="wenpai-field wenpai-field--row field">
	<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-max"><?php esc_html_e( '最大条目数', 'wpmind' ); ?></label></div>
	<div class="field-ctl">
		<input type="number" class="wpmind-num" id="wpmind-cache-max" name="wpmind_cache_max_entries" value="<?php echo esc_attr( (string) $max_entries ); ?>" min="10" max="5000" step="10">
		<p class="wenpai-field__desc"><?php esc_html_e( '超限时按 LRU 自动淘汰，范围 10–5000。', 'wpmind' ); ?></p>
	</div>
</div>

<div class="wenpai-field wenpai-field--row field">
	<div class="field-label"><label class="wenpai-field__label" for="wpmind-cache-scope"><?php esc_html_e( '隔离模式', 'wpmind' ); ?></label></div>
	<div class="field-ctl">
		<select id="wpmind-cache-scope" name="wpmind_cache_scope_mode">
			<option value="role" <?php selected( $scope_mode, 'role' ); ?>><?php esc_html_e( '按角色隔离（推荐）', 'wpmind' ); ?></option>
			<option value="user" <?php selected( $scope_mode, 'user' ); ?>><?php esc_html_e( '按用户隔离', 'wpmind' ); ?></option>
			<option value="none" <?php selected( $scope_mode, 'none' ); ?>><?php esc_html_e( '不隔离（全局共享）', 'wpmind' ); ?></option>
		</select>
		<p class="wenpai-field__desc"><?php esc_html_e( '决定缓存键的隔离粒度。', 'wpmind' ); ?></p>
	</div>
</div>

<div class="card-foot">
	<span class="wenpai-actions">
		<?php
		$flush_confirm = wpmind_confirm_attrs(
			__( '确定要清空所有缓存条目？此操作不可撤销。', 'wpmind' ),
			[
				'title'    => __( '清空缓存', 'wpmind' ),
				'danger'   => true,
				'ok_label' => __( '确认清空', 'wpmind' ),
			]
		);
		$reset_confirm = wpmind_confirm_attrs(
			__( '确定要重置全部缓存统计？此操作不可撤销。', 'wpmind' ),
			[
				'title'    => __( '重置缓存统计', 'wpmind' ),
				'danger'   => true,
				'ok_label' => __( '确认重置', 'wpmind' ),
			]
		);
		?>
		<button type="button" class="btn btn-ghost" id="wpmind-flush-cache"<?php echo $flush_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '清空缓存', 'wpmind' ); ?></button>
		<button type="button" class="btn btn-ghost" id="wpmind-reset-cache-stats"<?php echo $reset_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '重置统计', 'wpmind' ); ?></button>
		<button type="button" class="btn btn-primary" id="wpmind-save-cache-settings"><?php esc_html_e( '保存缓存设置', 'wpmind' ); ?></button>
	</span>
</div>
	<?php
	Wenpai_Admin_UI::card_close();
} )();
