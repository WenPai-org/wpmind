<?php
/**
 * Analytics Manager - 分析数据管理器
 *
 * 提供用量数据的聚合和分析功能
 *
 * @package WPMind\Modules\Analytics
 * @since 1.8.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Analytics;

use WPMind\Modules\CostControl\UsageTracker;

class AnalyticsManager {

	/**
	 * 单例实例
	 */
	private static ?AnalyticsManager $instance = null;

	/**
	 * 获取单例实例
	 */
	public static function instance(): AnalyticsManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 私有构造函数
	 */
	private function __construct() {}

	/**
	 * 获取用量趋势数据（按日）
	 *
	 * @param int        $days 天数（默认 7 天）
	 * @param array|null $stats 预加载的统计数据
	 * @return array
	 */
	public function get_usage_trend( int $days = 7, ?array $stats = null ): array {
		if ( $stats === null ) {
			$stats = UsageTracker::get_stats();
		}
		$daily = $stats['daily'] ?? [];

		$labels   = [];
		$tokens   = [];
		$costUsd  = [];
		$costCny  = [];
		$requests = [];

		// 生成日期范围
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date        = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$displayDate = wp_date( 'm/d', strtotime( "-{$i} days" ) );

			$labels[] = $displayDate;

			$dayData = $daily[ $date ] ?? [
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'cost_usd'      => 0,
				'cost_cny'      => 0,
				'requests'      => 0,
			];

			$tokens[]   = ( $dayData['input_tokens'] ?? 0 ) + ( $dayData['output_tokens'] ?? 0 );
			$costUsd[]  = round( $dayData['cost_usd'] ?? 0, 4 );
			$costCny[]  = round( $dayData['cost_cny'] ?? 0, 4 );
			$requests[] = $dayData['requests'] ?? 0;
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				'tokens'   => $tokens,
				'cost_usd' => $costUsd,
				'cost_cny' => $costCny,
				'requests' => $requests,
			],
		];
	}

	/**
	 * 获取服务商对比数据
	 *
	 * @param array|null $stats 预加载的统计数据
	 * @return array
	 */
	public function get_provider_comparison( ?array $stats = null ): array {
		if ( $stats === null ) {
			$stats = UsageTracker::get_stats();
		}
		$providers = $stats['providers'] ?? [];

		$labels   = [];
		$tokens   = [];
		$costs    = [];
		$requests = [];
		$colors   = [];

		foreach ( $providers as $providerId => $data ) {
			$labels[]   = UsageTracker::get_provider_display_name( $providerId );
			$tokens[]   = ( $data['total_input_tokens'] ?? 0 ) + ( $data['total_output_tokens'] ?? 0 );
			$costs[]    = round( $data['total_cost'] ?? 0, 4 );
			$requests[] = $data['request_count'] ?? 0;
			$colors[]   = $this->get_provider_chart_color( $providerId );
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				'tokens'   => $tokens,
				'costs'    => $costs,
				'requests' => $requests,
			],
			'colors'   => $colors,
		];
	}

	/**
	 * 获取成本分析数据
	 *
	 * @param int        $months 月数（默认 6 个月）
	 * @param array|null $stats 预加载的统计数据
	 * @return array
	 */
	public function get_cost_analysis( int $months = 6, ?array $stats = null ): array {
		if ( $stats === null ) {
			$stats = UsageTracker::get_stats();
		}
		$monthly = $stats['monthly'] ?? [];

		$labels  = [];
		$costUsd = [];
		$costCny = [];

		// 生成月份范围
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$month        = wp_date( 'Y-m', strtotime( "-{$i} months" ) );
			$displayMonth = wp_date( 'Y年n月', strtotime( "-{$i} months" ) );

			$labels[] = $displayMonth;

			$monthData = $monthly[ $month ] ?? [
				'cost_usd' => 0,
				'cost_cny' => 0,
			];

			$costUsd[] = round( $monthData['cost_usd'] ?? 0, 2 );
			$costCny[] = round( $monthData['cost_cny'] ?? 0, 2 );
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				'cost_usd' => $costUsd,
				'cost_cny' => $costCny,
			],
		];
	}

	/**
	 * 获取模型使用分布
	 *
	 * @param array|null $stats 预加载的统计数据
	 * @return array
	 */
	public function get_model_distribution( ?array $stats = null ): array {
		if ( $stats === null ) {
			$stats = UsageTracker::get_stats();
		}
		$providers = $stats['providers'] ?? [];

		$models = [];

		foreach ( $providers as $providerId => $providerData ) {
			$providerModels = $providerData['models'] ?? [];
			foreach ( $providerModels as $modelName => $modelData ) {
				$models[] = [
					'provider'      => $providerId,
					'provider_name' => UsageTracker::get_provider_display_name( $providerId ),
					'model'         => $modelName,
					'tokens'        => ( $modelData['input_tokens'] ?? 0 ) + ( $modelData['output_tokens'] ?? 0 ),
					'cost'          => $modelData['cost'] ?? 0,
					'requests'      => $modelData['requests'] ?? 0,
				];
			}
		}

		// 按请求数排序
		usort(
			$models,
			function ( $a, $b ) {
				return $b['requests'] - $a['requests'];
			}
		);

		// 取前 10 个
		$topModels = array_slice( $models, 0, 10 );

		$labels   = [];
		$requests = [];
		$tokens   = [];

		foreach ( $topModels as $model ) {
			$labels[]   = $model['model'];
			$requests[] = $model['requests'];
			$tokens[]   = $model['tokens'];
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				'requests' => $requests,
				'tokens'   => $tokens,
			],
			'details'  => $topModels,
		];
	}

	/**
	 * 获取性能指标（延迟分析）
	 *
	 * @param int $limit 记录数
	 * @return array
	 */
	public function get_latency_metrics( int $limit = 100 ): array {
		$history = UsageTracker::get_history( $limit );

		$providerLatency = [];

		foreach ( $history as $record ) {
			$provider = $record['provider'] ?? 'unknown';
			$latency  = $record['latency_ms'] ?? 0;

			if ( $latency > 0 ) {
				if ( ! isset( $providerLatency[ $provider ] ) ) {
					$providerLatency[ $provider ] = [
						'total' => 0,
						'count' => 0,
						'min'   => PHP_INT_MAX,
						'max'   => 0,
					];
				}

				$providerLatency[ $provider ]['total'] += $latency;
				++$providerLatency[ $provider ]['count'];
				$providerLatency[ $provider ]['min'] = min( $providerLatency[ $provider ]['min'], $latency );
				$providerLatency[ $provider ]['max'] = max( $providerLatency[ $provider ]['max'], $latency );
			}
		}

		$result = [];
		foreach ( $providerLatency as $provider => $data ) {
			if ( $data['count'] > 0 ) {
				$result[] = [
					'provider'      => $provider,
					'provider_name' => UsageTracker::get_provider_display_name( $provider ),
					'avg_latency'   => round( $data['total'] / $data['count'] ),
					'min_latency'   => $data['min'] === PHP_INT_MAX ? 0 : $data['min'],
					'max_latency'   => $data['max'],
					'sample_count'  => $data['count'],
				];
			}
		}

		// 按平均延迟排序
		usort(
			$result,
			function ( $a, $b ) {
				return $a['avg_latency'] - $b['avg_latency'];
			}
		);

		return $result;
	}

	/**
	 * 获取仪表板摘要数据
	 *
	 * @return array
	 */
	public function get_dashboard_summary(): array {
		$today = UsageTracker::get_today_stats();
		$week  = UsageTracker::get_week_stats();
		$month = UsageTracker::get_month_stats();
		$stats = UsageTracker::get_stats();
		$total = $stats['total'] ?? [];

		return [
			'today'        => [
				'tokens'   => ( $today['input_tokens'] ?? 0 ) + ( $today['output_tokens'] ?? 0 ),
				'cost_usd' => $today['cost_usd'] ?? 0,
				'cost_cny' => $today['cost_cny'] ?? 0,
				'requests' => $today['requests'] ?? 0,
			],
			'week'         => [
				'tokens'   => ( $week['input_tokens'] ?? 0 ) + ( $week['output_tokens'] ?? 0 ),
				'cost_usd' => $week['cost_usd'] ?? 0,
				'cost_cny' => $week['cost_cny'] ?? 0,
				'requests' => $week['requests'] ?? 0,
			],
			'month'        => [
				'tokens'   => ( $month['input_tokens'] ?? 0 ) + ( $month['output_tokens'] ?? 0 ),
				'cost_usd' => $month['cost_usd'] ?? 0,
				'cost_cny' => $month['cost_cny'] ?? 0,
				'requests' => $month['requests'] ?? 0,
			],
			'total'        => [
				'tokens'   => ( $total['input_tokens'] ?? 0 ) + ( $total['output_tokens'] ?? 0 ),
				'cost_usd' => $total['cost_usd'] ?? 0,
				'cost_cny' => $total['cost_cny'] ?? 0,
				'requests' => $total['requests'] ?? 0,
			],
			'last_updated' => $stats['last_updated'] ?? null,
		];
	}

	/**
	 * 获取完整的分析数据（用于 AJAX）
	 *
	 * @param string $range 时间范围 (7d, 30d, 6m)
	 * @return array
	 */
	public function get_analytics_data( string $range = '7d' ): array {
		// 白名单验证
		$allowed_ranges = [ '7d', '30d', '6m' ];
		if ( ! in_array( $range, $allowed_ranges, true ) ) {
			$range = '7d';
		}

		$days   = 7;
		$months = 6;

		switch ( $range ) {
			case '30d':
				$days = 30;
				break;
			case '6m':
				$months = 6;
				break;
			case '7d':
			default:
				$days = 7;
				break;
		}

		// 一次性获取统计数据，避免重复调用
		$stats = UsageTracker::get_stats();

		return [
			'summary'   => $this->get_dashboard_summary(),
			'trend'     => $this->get_usage_trend( $days, $stats ),
			'providers' => $this->get_provider_comparison( $stats ),
			'cost'      => $this->get_cost_analysis( $months, $stats ),
			'models'    => $this->get_model_distribution( $stats ),
			'latency'   => $this->get_latency_metrics(),
		];
	}

	/**
	 * 获取服务商的图表颜色
	 *
	 * @param string $provider Provider ID
	 * @return string 十六进制颜色值
	 */
	private function get_provider_chart_color( string $provider ): string {
		$colors = [
			'openai'      => '#10a37f',
			'anthropic'   => '#d4a27f',
			'google'      => '#4285f4',
			'deepseek'    => '#0066ff',
			'qwen'        => '#6366f1',
			'zhipu'       => '#1e40af',
			'moonshot'    => '#6b7280',
			'doubao'      => '#ef4444',
			'siliconflow' => '#8b5cf6',
			'baidu'       => '#2932e1',
			'minimax'     => '#f59e0b',
		];
		return $colors[ $provider ] ?? '#6b7280';
	}
}
