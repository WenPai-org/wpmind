<?php
/**
 * Routing Strategy Interface - 路由策略接口
 *
 * 定义所有路由策略必须实现的方法
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing;

interface RoutingStrategyInterface {

	/**
	 * 获取策略名称
	 *
	 * @return string 策略标识符
	 */
	public function get_name(): string;

	/**
	 * 获取策略显示名称
	 *
	 * @return string 用于 UI 显示的名称
	 */
	public function get_display_name(): string;

	/**
	 * 获取策略描述
	 *
	 * @return string 策略的详细描述
	 */
	public function get_description(): string;

	/**
	 * 选择最佳 Provider
	 *
	 * @param RoutingContext       $context 路由上下文
	 * @param array<string, array> $providers 可用的 Provider 列表
	 * @return string|null 选中的 Provider ID，无可用时返回 null
	 */
	public function select_provider( RoutingContext $context, array $providers ): ?string;

	/**
	 * 对 Provider 列表进行排序
	 *
	 * @param RoutingContext       $context 路由上下文
	 * @param array<string, array> $providers 可用的 Provider 列表
	 * @return array<string> 排序后的 Provider ID 列表
	 */
	public function rank_providers( RoutingContext $context, array $providers ): array;

	/**
	 * 计算 Provider 的得分
	 *
	 * @param string         $providerId Provider ID
	 * @param RoutingContext $context 路由上下文
	 * @return float 得分 (0-100)
	 */
	public function calculate_score( string $providerId, RoutingContext $context ): float;
}
