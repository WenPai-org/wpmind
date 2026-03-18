<?php
/**
 * Cost Strategy - 成本优先路由策略
 *
 * 选择成本最低的 Provider
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing\Strategies;

use WPMind\Routing\AbstractStrategy;
use WPMind\Routing\RoutingContext;
use WPMind\Modules\CostControl\UsageTracker;

class CostStrategy extends AbstractStrategy {

	public function get_name(): string {
		return 'cost';
	}

	public function get_display_name(): string {
		return '成本优先';
	}

	public function get_description(): string {
		return '选择成本最低的 Provider，适合预算敏感的场景';
	}

	/**
	 * 计算 Provider 的得分
	 *
	 * 成本越低，得分越高
	 */
	public function calculate_score( string $providerId, RoutingContext $context ): float {
		// 获取预估成本
		$estimatedCost = $context->estimate_cost( $providerId );

		// 获取健康分数作为权重因子
		$healthScore = $context->get_health_score( $providerId );

		// 如果健康分数太低，大幅降低得分
		if ( $healthScore < 50 ) {
			return $healthScore * 0.5;
		}

		// 成本归一化（假设最大成本为 $1 per request）
		// 成本越低，得分越高
		$costScore = $this->normalize_score( $estimatedCost, 0, 1.0, true );

		// 综合得分：成本权重 80%，健康权重 20%
		return ( $costScore * 0.8 ) + ( $healthScore * 0.2 );
	}

	/**
	 * 对 Provider 列表进行排序
	 *
	 * 按成本升序排列，同成本时按健康分数降序
	 */
	public function rank_providers( RoutingContext $context, array $providers ): array {
		$available = $this->filter_available( $context, $providers );

		if ( empty( $available ) ) {
			return [];
		}

		// 计算每个 Provider 的成本和健康分数
		$providerData = [];
		foreach ( $available as $providerId ) {
			$providerData[ $providerId ] = [
				'cost'   => $context->estimate_cost( $providerId ),
				'health' => $context->get_health_score( $providerId ),
			];
		}

		// 按成本升序排序，同成本时按健康分数降序
		uasort(
			$providerData,
			function ( $a, $b ) {
				// 健康分数太低的排在后面
				if ( $a['health'] < 50 && $b['health'] >= 50 ) {
					return 1;
				}
				if ( $b['health'] < 50 && $a['health'] >= 50 ) {
					return -1;
				}

				// 成本比较
				$costDiff = $a['cost'] - $b['cost'];
				if ( abs( $costDiff ) > 0.0001 ) {
					return $costDiff <=> 0;
				}

				// 成本相同时，健康分数高的优先
				return $b['health'] <=> $a['health'];
			}
		);

		return array_keys( $providerData );
	}
}
