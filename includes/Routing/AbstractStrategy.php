<?php
/**
 * Abstract Strategy - 路由策略基类
 *
 * 提供路由策略的通用实现
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing;

abstract class AbstractStrategy implements RoutingStrategyInterface
{
    /**
     * 选择最佳 Provider
     *
     * 默认实现：返回排名第一的 Provider
     */
    public function selectProvider(RoutingContext $context, array $providers): ?string
    {
        $ranked = $this->rankProviders($context, $providers);
        return $ranked[0] ?? null;
    }

    /**
     * 对 Provider 列表进行排序
     *
     * 默认实现：按得分降序排列
     */
    public function rankProviders(RoutingContext $context, array $providers): array
    {
        // 过滤掉被排除的 Provider
        $available = array_filter(
            array_keys($providers),
            fn($id) => !$context->isExcluded($id)
        );

        if (empty($available)) {
            return [];
        }

        // 计算每个 Provider 的得分
        $scores = [];
        foreach ($available as $providerId) {
            $scores[$providerId] = $this->calculateScore($providerId, $context);
        }

        // 按得分降序排序
        arsort($scores);

        return array_keys($scores);
    }

    /**
     * 过滤可用的 Provider
     *
     * @param RoutingContext $context 路由上下文
     * @param array<string, array> $providers Provider 列表
     * @return array<string> 可用的 Provider ID 列表
     */
    protected function filterAvailable(RoutingContext $context, array $providers): array
    {
        return array_filter(
            array_keys($providers),
            fn($id) => !$context->isExcluded($id)
        );
    }

    /**
     * 归一化得分到 0-100 范围
     *
     * @param float $value 原始值
     * @param float $min 最小值
     * @param float $max 最大值
     * @param bool $inverse 是否反转（值越小得分越高）
     * @return float 归一化后的得分
     */
    protected function normalizeScore(float $value, float $min, float $max, bool $inverse = false): float
    {
        if ($max <= $min) {
            return 50.0;
        }

        $normalized = (($value - $min) / ($max - $min)) * 100;
        $normalized = max(0, min(100, $normalized));

        return $inverse ? (100 - $normalized) : $normalized;
    }
}
