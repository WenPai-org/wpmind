<?php
/**
 * WPMind Overview Tab — 始终可用的轻量首页
 *
 * @package WPMind
 * @since 3.9.0
 */

defined("ABSPATH") || exit();

use WPMind\Modules\CostControl\UsageTracker;
use WPMind\Failover\FailoverManager;
use WPMind\Core\ModuleLoader;

// 数据准备
$today = UsageTracker::get_today_stats();
$month = UsageTracker::get_month_stats();
$failover = FailoverManager::instance();
$providers = $failover->get_status_summary();
$modules = ModuleLoader::instance()->get_modules();
$active_count = count(array_filter($modules, fn($m) => $m["enabled"]));

// Provider 状态统计
$healthy = 0;
$degraded = 0;
$down = 0;
foreach ($providers as $p) {
    $state = $p["state"] ?? "unknown";
    if ("healthy" === $state || "closed" === $state) {
        ++$healthy;
    } elseif ("half_open" === $state) {
        ++$degraded;
    } else {
        ++$down;
    }
}

// 价值摘要数据
$all_stats = UsageTracker::get_stats();
$provider_stats = $all_stats["providers"] ?? [];
$top_model = "—";
$top_model_reqs = 0;
foreach ($provider_stats as $pid => $pdata) {
    foreach ($pdata["models"] ?? [] as $mname => $mdata) {
        if (($mdata["requests"] ?? 0) > $top_model_reqs) {
            $top_model_reqs = $mdata["requests"];
            $top_model = $mname;
        }
    }
}

$recent_history = UsageTracker::get_history(5);
$avg_latency = 0;
if (!empty($recent_history)) {
    $latency_sum = array_sum(array_column($recent_history, "latency_ms"));
    $avg_latency = (int) round($latency_sum / count($recent_history));
}

// 服务可用率
$total_success_rate = 0;
$rate_count = 0;
foreach ($providers as $p) {
    if (isset($p["success_rate"])) {
        $total_success_rate += $p["success_rate"];
        ++$rate_count;
    }
}
$avg_success_rate =
    $rate_count > 0 ? (int) round($total_success_rate / $rate_count) : 100;

$cache_stats = function_exists("wpmind_get_cache_stats")
    ? wpmind_get_cache_stats()
    : [
        "enabled" => false,
        "hits" => 0,
        "misses" => 0,
        "writes" => 0,
        "hit_rate" => 0,
        "entries" => 0,
        "max_entries" => 0,
    ];

$cache_hit_rate = isset($cache_stats["hit_rate"]) ? (float) $cache_stats["hit_rate"] : 0.0;
$cache_enabled = !empty($cache_stats["enabled"]);
$cache_hits = (int) ($cache_stats["hits"] ?? 0);
$cache_entries = (int) ($cache_stats["entries"] ?? 0);
$cache_max_entries = (int) ($cache_stats["max_entries"] ?? 0);
?>

<div class="wpmind-tab-pane-body">
<div class="wpmind-overview">

	<!-- Hero -->
	<div class="wpmind-overview-hero">
		<span class="wpmind-overview-hero-icon"><span class="dashicons ri-brain-line"></span></span>
		<h2 class="wpmind-overview-hero-title"><?php esc_html_e(
      "文派心思",
      "wpmind",
  ); ?></h2>
		<p class="wpmind-overview-hero-subtitle"><?php esc_html_e(
      "WordPress AI 智能路由引擎 — 多 Provider 智能路由、熔断降级、用量追踪，一站式管理您的 AI 服务。",
      "wpmind",
  ); ?></p>
		<p class="wpmind-overview-hero-meta">
			v<?php echo esc_html(WPMIND_VERSION); ?>
			&middot; <?php echo esc_html(count($providers)); ?> <?php esc_html_e(
     "个 Provider",
     "wpmind",
 ); ?>
			&middot; <?php echo esc_html($active_count); ?>/<?php echo esc_html(
    count($modules),
); ?> <?php esc_html_e("模块启用", "wpmind"); ?>
			&middot; <?php echo $cache_enabled
     ? esc_html(number_format_i18n($cache_hit_rate, 1) . "%")
     : "—"; ?> <?php esc_html_e("缓存命中率", "wpmind"); ?>
		</p>
		<div class="wpmind-overview-hero-actions">
			<a href="#services" class="wpmind-overview-hero-btn wpmind-overview-hero-btn--primary wpmind-tab-link" data-tab-link="services"><?php esc_html_e(
       "添加 Provider",
       "wpmind",
   ); ?></a>
			<a href="#analytics" class="wpmind-overview-hero-btn wpmind-overview-hero-btn--secondary wpmind-tab-link" data-tab-link="analytics"><?php esc_html_e(
       "Token 统计",
       "wpmind",
   ); ?> &nearr;</a>
		</div>
	</div>

	<!-- 统计卡片 -->
	<div class="wpmind-overview-stats">
		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon">
				<span class="dashicons ri-chat-ai-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html(
        number_format_i18n($today["requests"] ?? 0),
    ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e(
        "今日请求",
        "wpmind",
    ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon">
				<span class="dashicons ri-token-swap-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html(
        UsageTracker::format_tokens(
            ($today["input_tokens"] ?? 0) + ($today["output_tokens"] ?? 0),
        ),
    ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e(
        "今日 Tokens",
        "wpmind",
    ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon">
				<span class="dashicons ri-money-cny-circle-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html(
        UsageTracker::format_cost_by_currency(
            $month["cost_usd"] ?? 0,
            $month["cost_cny"] ?? 0,
        ),
    ); ?></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e(
        "本月费用",
        "wpmind",
    ); ?></span>
			</div>
		</div>

		<div class="wpmind-overview-stat">
			<span class="wpmind-overview-stat-icon">
				<span class="dashicons ri-server-line"></span>
			</span>
			<div class="wpmind-overview-stat-body">
				<span class="wpmind-overview-stat-value"><?php echo esc_html(
        $healthy,
    ); ?><span class="wpmind-overview-stat-sub">/<?php echo esc_html(
    count($providers),
); ?></span></span>
				<span class="wpmind-overview-stat-label"><?php esc_html_e(
        "Provider 在线",
        "wpmind",
    ); ?></span>
			</div>
		</div>
	</div>

	<!-- 双栏布局 -->
	<div class="wpmind-overview-grid">

		<!-- 左栏：Provider 状态 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-server-line"></span><?php esc_html_e(
        "Provider 状态",
        "wpmind",
    ); ?></h3>
				<a href="#services" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="services"><?php esc_html_e(
        "管理",
        "wpmind",
    ); ?> &rarr;</a>
			</div>
			<div class="wpmind-overview-card-body">
				<?php if (empty($providers)): ?>
					<div class="wpmind-overview-empty-state">
						<span class="wpmind-overview-empty-icon dashicons ri-server-line"></span>
						<p class="wpmind-overview-empty-text"><?php esc_html_e(
          "还没有配置 Provider",
          "wpmind",
      ); ?></p>
						<a href="#services" class="wpmind-overview-empty-action wpmind-tab-link" data-tab-link="services"><?php esc_html_e(
          "去配置",
          "wpmind",
      ); ?> &rarr;</a>
					</div>
				<?php else: ?>
					<div class="wpmind-overview-provider-list">
						<?php foreach ($providers as $key => $p):

          $state = $p["state"] ?? "unknown";
          $is_ok = in_array($state, ["healthy", "closed"], true);
          $name = UsageTracker::get_provider_display_name($key);
          $color = $is_ok ? "var(--wpmind-success)" : "var(--wpmind-error)";
          $icon = $is_ok ? "ri-checkbox-circle-fill" : "ri-error-warning-fill";
          ?>
						<div class="wpmind-overview-provider-item">
							<span class="dashicons <?php echo esc_attr(
           $icon,
       ); ?>" style="color: <?php echo esc_attr($color); ?>"></span>
							<span class="wpmind-overview-provider-name"><?php echo esc_html(
           $name,
       ); ?></span>
						</div>
						<?php
      endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- 右栏：模块状态 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-puzzle-line"></span><?php esc_html_e(
        "模块状态",
        "wpmind",
    ); ?></h3>
				<a href="#modules" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="modules"><?php esc_html_e(
        "管理",
        "wpmind",
    ); ?> &rarr;</a>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-module-list">
					<?php foreach ($modules as $mid => $m):

         $enabled = $m["enabled"];
         $color = $enabled ? "var(--wpmind-success)" : "var(--wpmind-gray-400)";
         $icon = $enabled ? "ri-checkbox-circle-fill" : "ri-close-circle-line";
         $badge = !$m["can_disable"]
             ? ' <span class="wpmind-overview-badge-core">' .
                 esc_html__("核心", "wpmind") .
                 "</span>"
             : "";
         ?>
					<div class="wpmind-overview-module-item">
						<span class="dashicons <?php echo esc_attr(
          $icon,
      ); ?>" style="color: <?php echo esc_attr($color); ?>"></span>
						<span class="wpmind-overview-module-name"><?php
      echo esc_html($m["name"]);
      echo wp_kses_post($badge);

         // phpcs:ignore
         ?></span>
					</div>
					<?php
     endforeach; ?>
				</div>
			</div>
		</div>

	</div>

	<!-- 价值摘要 + 最近活动 -->
	<div class="wpmind-overview-grid">

		<!-- 左栏：本月摘要 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-bar-chart-grouped-line"></span><?php esc_html_e(
        "本月摘要",
        "wpmind",
    ); ?></h3>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-summary-grid">
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-robot-2-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html(
           $top_model,
       ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "最常用模型",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-timer-flash-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo $avg_latency > 0
           ? esc_html(number_format_i18n($avg_latency) . "ms")
           : "—"; ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "平均响应时间",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-shield-check-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html(
           $avg_success_rate . "%",
       ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "服务可用率",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-token-swap-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html(
           UsageTracker::format_tokens(
               ($month["input_tokens"] ?? 0) + ($month["output_tokens"] ?? 0),
           ),
       ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "本月总 Tokens",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-database-2-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo $cache_enabled
           ? esc_html(number_format_i18n($cache_hit_rate, 1) . "%")
           : "—"; ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "缓存命中率",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-stack-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html(
           number_format_i18n($cache_entries),
       ); ?><span class="wpmind-overview-stat-sub">/<?php echo esc_html(
    number_format_i18n($cache_max_entries),
); ?></span></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "缓存占用",
           "wpmind",
       ); ?></span>
						</div>
					</div>
					<div class="wpmind-overview-summary-cell">
						<span class="wpmind-overview-summary-icon">
							<span class="dashicons ri-flashlight-line"></span>
						</span>
						<div class="wpmind-overview-summary-text">
							<span class="wpmind-overview-summary-value"><?php echo esc_html(
           number_format_i18n($cache_hits),
       ); ?></span>
							<span class="wpmind-overview-summary-label"><?php esc_html_e(
           "缓存命中次数",
           "wpmind",
       ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 右栏：最近活动 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-pulse-line"></span><?php esc_html_e(
        "最近活动",
        "wpmind",
    ); ?></h3>
				<?php if (class_exists("WPMind\\Modules\\Analytics\\AnalyticsManager")): ?>
					<a href="#analytics" class="wpmind-overview-card-link wpmind-tab-link" data-tab-link="analytics"><?php esc_html_e(
         "查看全部",
         "wpmind",
     ); ?> →</a>
				<?php endif; ?>
			</div>
			<div class="wpmind-overview-card-body">
				<?php if (empty($recent_history)): ?>
					<div class="wpmind-overview-empty-state">
						<span class="wpmind-overview-empty-icon dashicons ri-pulse-line"></span>
						<p class="wpmind-overview-empty-text"><?php esc_html_e(
          "暂无 API 调用记录",
          "wpmind",
      ); ?></p>
						<p class="wpmind-overview-empty-hint"><?php esc_html_e(
          "使用 AI 功能后，调用记录将显示在这里",
          "wpmind",
      ); ?></p>
					</div>
				<?php else: ?>
					<div class="wpmind-overview-activity-list">
						<?php foreach ($recent_history as $record):

          $ts = $record["timestamp"] ?? 0;
          $time_ago =
              $ts > 0 ? human_time_diff($ts, time()) . __("前", "wpmind") : "—";
          $pname = UsageTracker::get_provider_display_name(
              $record["provider"] ?? "",
          );
          $model = $record["model"] ?? "—";
          $tokens =
              ($record["input_tokens"] ?? 0) + ($record["output_tokens"] ?? 0);
          $latency = $record["latency_ms"] ?? 0;
          $picon = UsageTracker::get_provider_icon($record["provider"] ?? "");
          ?>
						<div class="wpmind-overview-activity-item">
							<span class="wpmind-overview-activity-time"><?php echo esc_html(
           $time_ago,
       ); ?></span>
							<span class="wpmind-overview-activity-provider">
								<span class="dashicons <?php echo esc_attr($picon); ?>"></span>
								<?php echo esc_html($pname); ?>
							</span>
							<span class="wpmind-overview-activity-model"><?php echo esc_html(
           $model,
       ); ?></span>
							<span class="wpmind-overview-activity-meta">
								<span class="wpmind-overview-activity-tokens"><?php echo esc_html(
            UsageTracker::format_tokens($tokens),
        ); ?></span>
								<?php if ($latency > 0): ?>
									<span class="wpmind-overview-activity-latency"><?php echo esc_html(
             $latency . "ms",
         ); ?></span>
								<?php endif; ?>
							</span>
						</div>
						<?php
      endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<!-- 底部双栏 -->
	<div class="wpmind-overview-grid">

		<!-- 帮助文档 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-question-line"></span><?php esc_html_e(
        "帮助文档",
        "wpmind",
    ); ?></h3>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-link-list">
					<a href="https://wpcommunity.com/c/wpmind/" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-server-line"></span>
						<?php esc_html_e("如何配置 AI Provider", "wpmind"); ?>
					</a>
					<a href="https://wpcommunity.com/c/wpmind/" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-route-line"></span>
						<?php esc_html_e("如何设置路由策略", "wpmind"); ?>
					</a>
					<a href="https://wpcommunity.com/c/wpmind/" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-wallet-line"></span>
						<?php esc_html_e("预算与限额指南", "wpmind"); ?>
					</a>
					<a href="https://wpcommunity.com/c/wpmind/" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-line-chart-line"></span>
						<?php esc_html_e("Token 用量统计说明", "wpmind"); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- 浏览更多 -->
		<div class="wpmind-overview-card">
			<div class="wpmind-overview-card-header">
				<h3><span class="dashicons ri-links-line"></span><?php esc_html_e(
        "浏览更多",
        "wpmind",
    ); ?></h3>
			</div>
			<div class="wpmind-overview-card-body">
				<div class="wpmind-overview-link-list">
					<a href="https://wpcy.com/mind/" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-book-open-line"></span>
						<?php esc_html_e("使用文档", "wpmind"); ?>
					</a>
					<a href="https://github.com/WenPai-org/wpmind" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-github-line"></span>
						<?php esc_html_e("GitHub", "wpmind"); ?>
					</a>
					<a href="https://github.com/WenPai-org/wpmind/issues" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-bug-line"></span>
						<?php esc_html_e("问题反馈", "wpmind"); ?>
					</a>
					<a href="https://feicode.com/WenPai-org/wpmind/releases" target="_blank" rel="noopener" class="wpmind-overview-link-item">
						<span class="dashicons ri-download-line"></span>
						<?php esc_html_e("版本发布", "wpmind"); ?>
					</a>
				</div>
			</div>
		</div>

	</div>

	<p class="wpmind-overview-footer">WPMind v<?php echo esc_html(
     WPMIND_VERSION,
 ); ?></p>

</div>
</div>
