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

class LoadBalancedStrategy extends AbstractStrategy
{
    /** @var string 负载均衡算法 */
    private string $algorithm;

    /** @var array<string, int> Provider 权重配置 */
    private array $weights;

    /**
     * @param string $algorithm 算法: round_robin, weighted, random
     * @param array<string, int> $weights Provider 权重
     */
    public function __construct(string $algorithm = 'weighted', array $weights = [])
    {
        $this->algorithm = $algorithm;
        $this->weights = $weights;
    }

    public function getName(): string
    {
        return 'load_balanced';
    }

    public function getDisplayName(): string
    {
        return '负载均衡';
    }

    public function getDescription(): string
    {
        return '在多个 Provider 之间分散请求，避免单点过载';
    }

    /**
     * 选择 Provider
     *
     * 根据算法选择下一个 Provider
     */
    public function selectProvider(RoutingContext $context, array $providers): ?string
    {
        $available = $this->filterAvailable($context, $providers);

        if (empty($available)) {
            return null;
        }

        // 过滤掉健康分数太低的 Provider
        $healthy = array_filter(
            $available,
            fn($id) => $context->getHealthScore($id) >= 50
        );

        // 如果没有健康的 Provider，使用所有可用的
        if (empty($healthy)) {
            $healthy = $available;
        }

        return match ($this->algorithm) {
            'round_robin' => $this->roundRobin($healthy),
            'random' => $this->random($healthy),
            default => $this->weighted($healthy, $context),
        };
    }

    /**
     * 计算 Provider 的得分
     *
     * 综合考虑权重、健康分数和使用量
     */
    public function calculateScore(string $providerId, RoutingContext $context): float
    {
        $healthScore = $context->getHealthScore($providerId);
        $weight = $this->weights[$providerId] ?? 1;

        // 获取使用统计
        $usageStats = $context->getProviderUsageStats($providerId);
        $requestCount = $usageStats['request_count'] ?? 0;

        // 使用量越少，得分越高（鼓励分散）
        $usageScore = max(0, 100 - ($requestCount / 10));

        // 综合得分
        return ($healthScore * 0.4) + ($usageScore * 0.3) + ($weight * 10 * 0.3);
    }

    /**
     * 轮询算法
     */
    private function roundRobin(array $providers): string
    {
        static $index = 0;
        $providers = array_values($providers);
        $selected = $providers[$index % count($providers)];
        $index++;
        return $selected;
    }

    /**
     * 随机算法
     */
    private function random(array $providers): string
    {
        $providers = array_values($providers);
        return $providers[array_rand($providers)];
    }

    /**
     * 加权算法
     */
    private function weighted(array $providers, RoutingContext $context): string
    {
        $weightedProviders = [];

        foreach ($providers as $providerId) {
            $weight = $this->weights[$providerId] ?? 1;
            $healthScore = $context->getHealthScore($providerId);

            // 健康分数作为权重修正因子
            $effectiveWeight = $weight * ($healthScore / 100);
            $weightedProviders[$providerId] = max(1, (int) $effectiveWeight);
        }

        // 加权随机选择
        $totalWeight = array_sum($weightedProviders);
        $random = mt_rand(1, $totalWeight);

        $cumulative = 0;
        foreach ($weightedProviders as $providerId => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $providerId;
            }
        }

        // 兜底返回第一个
        return array_key_first($weightedProviders);
    }
}
