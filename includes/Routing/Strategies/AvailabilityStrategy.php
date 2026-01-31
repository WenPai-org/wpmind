<?php
/**
 * Availability Strategy - 可用性优先路由策略
 *
 * 选择健康分数最高的 Provider
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing\Strategies;

use WPMind\Routing\AbstractStrategy;
use WPMind\Routing\RoutingContext;

class AvailabilityStrategy extends AbstractStrategy
{
    public function getName(): string
    {
        return 'availability';
    }

    public function getDisplayName(): string
    {
        return '可用性优先';
    }

    public function getDescription(): string
    {
        return '选择健康分数最高的 Provider，适合对稳定性要求高的场景';
    }

    /**
     * 计算 Provider 的得分
     *
     * 直接使用健康分数
     */
    public function calculateScore(string $providerId, RoutingContext $context): float
    {
        $healthScore = $context->getHealthScore($providerId);
        $latency = $context->getAverageLatency($providerId);

        // 延迟作为次要因素（延迟越低加分越多）
        $latencyBonus = 0;
        if ($latency > 0 && $latency < 5000) {
            $latencyBonus = (5000 - $latency) / 5000 * 10; // 最多加 10 分
        }

        return min(100, $healthScore + $latencyBonus);
    }

    /**
     * 对 Provider 列表进行排序
     *
     * 按健康分数降序排列
     */
    public function rankProviders(RoutingContext $context, array $providers): array
    {
        $available = $this->filterAvailable($context, $providers);

        if (empty($available)) {
            return [];
        }

        // 计算每个 Provider 的健康分数和延迟
        $providerData = [];
        foreach ($available as $providerId) {
            $providerData[$providerId] = [
                'health' => $context->getHealthScore($providerId),
                'latency' => $context->getAverageLatency($providerId),
            ];
        }

        // 按健康分数降序排序
        uasort($providerData, function ($a, $b) {
            // 健康分数比较
            $healthDiff = $b['health'] - $a['health'];
            if ($healthDiff !== 0) {
                return $healthDiff;
            }

            // 健康分数相同时，延迟低的优先
            return $a['latency'] - $b['latency'];
        });

        return array_keys($providerData);
    }
}
