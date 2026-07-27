<?php
/**
 * Load Balanced Strategy - 负载均衡路由策略
 *
 * 在多个 Provider 之间分散请求
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing\Strategies;

use WPMind\Routing\AbstractStrategy;
use WPMind\Routing\RoutingContext;

/**
 * 负载均衡路由策略类。
 *
 * 在多个 Provider 之间分散请求，避免单点过载。
 *
 * @package WPMind
 * @since   1.9.0
 */
class LoadBalancedStrategy extends AbstractStrategy {

	/**
	 * 负载均衡算法，如 round_robin、weighted、random。
	 *
	 * @var string
	 */
	private string $algorithm;

	/**
	 * Provider 权重配置，以 Provider ID 为键、权重值为值。
	 *
	 * @var array<string, int>
	 */
	private array $weights;

	/**
	 * 构造函数。
	 *
	 * @param string             $algorithm 算法: round_robin, weighted, random.
	 * @param array<string, int> $weights   Provider 权重配置.
	 */
	public function __construct( string $algorithm = 'weighted', array $weights = [] ) {
		$this->algorithm = $algorithm;
		$this->weights   = $weights;
	}

	/**
	 * 获取策略名称。
	 *
	 * @return string 策略标识符。
	 */
	public function get_name(): string {
		return 'load_balanced';
	}

	/**
	 * 获取策略显示名称。
	 *
	 * @return string 用于 UI 显示的名称。
	 */
	public function get_display_name(): string {
		return '负载均衡';
	}

	/**
	 * 获取策略描述。
	 *
	 * @return string 策略的详细描述。
	 */
	public function get_description(): string {
		return '在多个 Provider 之间分散请求，避免单点过载';
	}

	/**
	 * 选择 Provider。
	 *
	 * 根据配置的负载均衡算法选择下一个 Provider。
	 *
	 * @param RoutingContext       $context   路由上下文.
	 * @param array<string, array> $providers 可用的 Provider 列表.
	 * @return string|null 选中的 Provider ID，无可用时返回 null。
	 */
	public function select_provider( RoutingContext $context, array $providers ): ?string {
		$available = $this->filter_available( $context, $providers );

		if ( empty( $available ) ) {
			return null;
		}

		// 过滤掉健康分数太低的 Provider.
		$healthy = array_filter(
			$available,
			fn( $id ) => $context->get_health_score( $id ) >= 50
		);

		// 如果没有健康的 Provider，使用所有可用的.
		if ( empty( $healthy ) ) {
			$healthy = $available;
		}

		return match ( $this->algorithm ) {
			'round_robin' => $this->round_robin( $healthy ),
			'random' => $this->random( $healthy ),
			default => $this->weighted( $healthy, $context ),
		};
	}

	/**
	 * 计算 Provider 的得分。
	 *
	 * 综合考虑权重、健康分数和使用量。
	 *
	 * @param string         $provider_id Provider ID.
	 * @param RoutingContext $context    路由上下文.
	 * @return float 得分 (0-100)。
	 */
	public function calculate_score( string $provider_id, RoutingContext $context ): float {
		$health_score = $context->get_health_score( $provider_id );
		$weight       = $this->weights[ $provider_id ] ?? 1;

		// 获取使用统计.
		$usage_stats   = $context->get_provider_usage_stats( $provider_id );
		$request_count = $usage_stats['request_count'] ?? 0;

		// 使用量越少，得分越高（鼓励分散）.
		$usage_score = max( 0, 100 - ( $request_count / 10 ) );

		// 综合得分.
		return ( $health_score * 0.4 ) + ( $usage_score * 0.3 ) + ( $weight * 10 * 0.3 );
	}

	/**
	 * 轮询算法。
	 *
	 * 按顺序循环选择 Provider。
	 *
	 * @param array<string, array> $providers 可用的 Provider 列表.
	 * @return string 选中的 Provider ID.
	 */
	private function round_robin( array $providers ): string {
		$providers = array_values( $providers );
		$index     = (int) get_transient( 'wpmind_round_robin_index' ) ?: 0;
		$selected  = $providers[ $index % count( $providers ) ];
		set_transient( 'wpmind_round_robin_index', $index + 1, 3600 );
		return $selected;
	}

	/**
	 * 随机算法。
	 *
	 * 随机选择一个 Provider。
	 *
	 * @param array<string, array> $providers 可用的 Provider 列表.
	 * @return string 选中的 Provider ID.
	 */
	private function random( array $providers ): string {
		$providers = array_values( $providers );
		return $providers[ array_rand( $providers ) ];
	}

	/**
	 * 加权算法。
	 *
	 * 根据权重和健康分数加权随机选择 Provider。
	 *
	 * @param array<string, array> $providers 可用的 Provider 列表.
	 * @param RoutingContext       $context   路由上下文.
	 * @return string 选中的 Provider ID.
	 */
	private function weighted( array $providers, RoutingContext $context ): string {
		$weighted_providers = [];

		foreach ( $providers as $provider_id ) {
			$weight       = $this->weights[ $provider_id ] ?? 1;
			$health_score = $context->get_health_score( $provider_id );

			// 健康分数作为权重修正因子.
			$effective_weight                   = $weight * ( $health_score / 100 );
			$weighted_providers[ $provider_id ] = max( 1, (int) $effective_weight );
		}

		// 加权随机选择.
		$total_weight = array_sum( $weighted_providers );
		$random       = wp_rand( 1, $total_weight );

		$cumulative = 0;
		foreach ( $weighted_providers as $provider_id => $weight ) {
			$cumulative += $weight;
			if ( $random <= $cumulative ) {
				return $provider_id;
			}
		}

		// 兜底返回第一个.
		return array_key_first( $weighted_providers );
	}
}
