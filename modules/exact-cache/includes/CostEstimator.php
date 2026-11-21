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
	 * 计算公式：分别按 USD 与 CNY 汇总节省金额 = 缓存命中次数 × 对应币种平均每次请求成本。
	 *
	 * @return array{saved_usd: float, saved_cny: float, total_usd: float, total_cny: float, avg_cost_per_request: float, avg_cost_cny: float}
	 */
	public static function get_estimated_savings(): array {
		$cache_stats = \WPMind\Cache\ExactCache::instance()->get_stats();
		$hits        = (int) ( $cache_stats['hits'] ?? 0 );

		if ( 0 === $hits ) {
			return [
				'saved_usd'            => 0.0,
				'saved_cny'            => 0.0,
				'total_usd'            => 0.0,
				'total_cny'            => 0.0,
				'avg_cost_per_request' => 0.0,
				'avg_cost_cny'         => 0.0,
			];
		}

		// 从 UsageTracker 获取平均请求成本.
		$usage_stats = [];
		if ( class_exists( '\WPMind\Modules\CostControl\UsageTracker' ) ) {
			$usage_stats = \WPMind\Modules\CostControl\UsageTracker::get_stats();
		}

		$total_cost_usd = (float) ( $usage_stats['total']['cost_usd'] ?? 0.0 );
		$total_cost_cny = (float) ( $usage_stats['total']['cost_cny'] ?? 0.0 );
		$total_requests = (int) ( $usage_stats['total']['requests'] ?? 0 );

		if ( 0 === $total_requests ) {
			return [
				'saved_usd'            => 0.0,
				'saved_cny'            => 0.0,
				'total_usd'            => 0.0,
				'total_cny'            => 0.0,
				'avg_cost_per_request' => 0.0,
				'avg_cost_cny'         => 0.0,
			];
		}

		$avg_cost_usd = $total_cost_usd / $total_requests;
		$avg_cost_cny = $total_cost_cny / $total_requests;
		$saved_usd    = round( $hits * $avg_cost_usd, 4 );
		$saved_cny    = round( $hits * $avg_cost_cny, 2 );

		return [
			'saved_usd'            => $saved_usd,
			'saved_cny'            => $saved_cny,
			'total_usd'            => $saved_usd,
			'total_cny'            => $saved_cny,
			'avg_cost_per_request' => round( $avg_cost_usd, 6 ),
			'avg_cost_cny'         => round( $avg_cost_cny, 6 ),
		];
	}
}
