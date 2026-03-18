<?php
/**
 * Latency Strategy - 延迟优先路由策略
 *
 * 选择响应最快的 Provider
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing\Strategies;

use WPMind\Routing\AbstractStrategy;
use WPMind\Routing\RoutingContext;

class LatencyStrategy extends AbstractStrategy {

	/** @var int 最大可接受延迟（毫秒） */
	private const MAX_ACCEPTABLE_LATENCY = 10000;

	public function get_name(): string {
		return 'latency';
	}

	public function get_display_name(): string {
		return '延迟优先';
	}

	public function get_description(): string {
		return '选择响应最快的 Provider，适合对实时性要求高的场景';
	}

	/**
	 * 计算 Provider 的得分
	 *
	 * 延迟越低，得分越高
	 */
	public function calculate_score( string $providerId, RoutingContext $context ): float {
		$latency     = $context->get_average_latency( $providerId );
		$healthScore = $context->get_health_score( $providerId );

		// 如果健康分数太低，大幅降低得分
		if ( $healthScore < 50 ) {
			return $healthScore * 0.5;
		}

		// 无延迟数据时，给予中等分数
		if ( $latency === 0 ) {
			return 50.0 + ( $healthScore * 0.2 );
		}

		// 延迟归一化（0-10000ms 范围）
		// 延迟越低，得分越高
		$latencyScore = $this->normalize_score(
			(float) $latency,
			0,
			self::MAX_ACCEPTABLE_LATENCY,
			true
		);

		// 综合得分：延迟权重 70%，健康权重 30%
		return ( $latencyScore * 0.7 ) + ( $healthScore * 0.3 );
	}

	/**
	 * 对 Provider 列表进行排序
	 *
	 * 按延迟升序排列
	 */
	public function rank_providers( RoutingContext $context, array $providers ): array {
		$available = $this->filter_available( $context, $providers );

		if ( empty( $available ) ) {
			return [];
		}

		// 计算每个 Provider 的延迟和健康分数
		$providerData = [];
		foreach ( $available as $providerId ) {
			$latency                     = $context->get_average_latency( $providerId );
			$providerData[ $providerId ] = [
				'latency' => $latency ?: PHP_INT_MAX, // 无数据时排在最后
				'health'  => $context->get_health_score( $providerId ),
			];
		}

		// 按延迟升序排序
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

				// 延迟比较
				$latencyDiff = $a['latency'] - $b['latency'];
				if ( $latencyDiff !== 0 ) {
					return $latencyDiff <=> 0;
				}

				// 延迟相同时，健康分数高的优先
				return $b['health'] - $a['health'];
			}
		);

		return array_keys( $providerData );
	}
}
