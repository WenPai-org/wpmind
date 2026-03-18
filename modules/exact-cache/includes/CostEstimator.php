<?php
/**
 * Cost Estimator - 缓存成本节省估算
 *
 * 基于缓存命中次数和平均请求成本估算节省金额。
 *
 * @package WPMind\Modules\ExactCache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

final class CostEstimator {

	/**
	 * 获取估算的成本节省
	 *
	 * 计算公式：节省金额 = 缓存命中次数 × 平均每次请求成本
	 *
	 * @return array{total_usd: float, total_cny: float, avg_cost_per_request: float}
	 */
	public static function get_estimated_savings(): array {
		$cache_stats = \WPMind\Cache\ExactCache::instance()->get_stats();
		$hits        = (int) ( $cache_stats['hits'] ?? 0 );

		if ( $hits === 0 ) {
			return [
				'total_usd'            => 0.0,
				'total_cny'            => 0.0,
				'avg_cost_per_request' => 0.0,
			];
		}

		// 从 UsageTracker 获取平均请求成本
		$usage_stats = [];
		if ( class_exists( '\WPMind\Modules\CostControl\UsageTracker' ) ) {
			$usage_stats = \WPMind\Modules\CostControl\UsageTracker::get_stats();
		}

		$total_cost     = (float) ( $usage_stats['total']['cost_usd'] ?? 0.0 );
		$total_requests = (int) ( $usage_stats['total']['requests'] ?? 0 );

		if ( $total_requests === 0 ) {
			return [
				'total_usd'            => 0.0,
				'total_cny'            => 0.0,
				'avg_cost_per_request' => 0.0,
			];
		}

		$avg_cost  = $total_cost / $total_requests;
		$saved_usd = $hits * $avg_cost;
		$saved_cny = $saved_usd * 7.2; // 近似汇率

		return [
			'total_usd'            => round( $saved_usd, 4 ),
			'total_cny'            => round( $saved_cny, 2 ),
			'avg_cost_per_request' => round( $avg_cost, 6 ),
		];
	}
}
