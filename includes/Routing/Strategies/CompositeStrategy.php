<?php
/**
 * Composite Strategy - 复合路由策略
 *
 * 组合多个策略，按权重计算综合得分
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing\Strategies;

use WPMind\Routing\AbstractStrategy;
use WPMind\Routing\RoutingContext;
use WPMind\Routing\RoutingStrategyInterface;

class CompositeStrategy extends AbstractStrategy
{
    /** @var array<array{strategy: RoutingStrategyInterface, weight: float}> */
    private array $strategies = [];

    /** @var string 策略名称 */
    private string $name;

    /** @var string 显示名称 */
    private string $displayName;

    /** @var string 描述 */
    private string $description;

    /**
     * @param string $name 策略名称
     * @param string $displayName 显示名称
     * @param string $description 描述
     */
    public function __construct(
        string $name = 'composite',
        string $displayName = '综合策略',
        string $description = '综合多个因素进行路由决策'
    ) {
        $this->name = $name;
        $this->displayName = $displayName;
        $this->description = $description;
    }

    /**
     * 添加子策略
     *
     * @param RoutingStrategyInterface $strategy 策略实例
     * @param float $weight 权重 (0-1)
     * @return self
     */
    public function addStrategy(RoutingStrategyInterface $strategy, float $weight): self
    {
        $this->strategies[] = [
            'strategy' => $strategy,
            'weight' => max(0, min(1, $weight)),
        ];
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 计算 Provider 的综合得分
     *
     * 按权重汇总各子策略的得分
     */
    public function calculateScore(string $providerId, RoutingContext $context): float
    {
        if (empty($this->strategies)) {
            return 50.0;
        }

        $totalWeight = array_sum(array_column($this->strategies, 'weight'));
        if ($totalWeight <= 0) {
            return 50.0;
        }

        $weightedScore = 0;
        foreach ($this->strategies as $item) {
            $score = $item['strategy']->calculateScore($providerId, $context);
            $normalizedWeight = $item['weight'] / $totalWeight;
            $weightedScore += $score * $normalizedWeight;
        }

        return $weightedScore;
    }

    /**
     * 获取子策略列表
     *
     * @return array<array{name: string, weight: float}>
     */
    public function getStrategies(): array
    {
        return array_map(fn($item) => [
            'name' => $item['strategy']->getName(),
            'display_name' => $item['strategy']->getDisplayName(),
            'weight' => $item['weight'],
        ], $this->strategies);
    }

    /**
     * 创建预设的平衡策略
     *
     * 成本、延迟、可用性各占 1/3
     */
    public static function createBalanced(): self
    {
        return (new self('balanced', '平衡策略', '平衡考虑成本、延迟和可用性'))
            ->addStrategy(new CostStrategy(), 0.33)
            ->addStrategy(new LatencyStrategy(), 0.33)
            ->addStrategy(new AvailabilityStrategy(), 0.34);
    }

    /**
     * 创建预设的性能优先策略
     *
     * 延迟 50%，可用性 30%，成本 20%
     */
    public static function createPerformance(): self
    {
        return (new self('performance', '性能优先', '优先考虑响应速度和稳定性'))
            ->addStrategy(new LatencyStrategy(), 0.50)
            ->addStrategy(new AvailabilityStrategy(), 0.30)
            ->addStrategy(new CostStrategy(), 0.20);
    }

    /**
     * 创建预设的经济策略
     *
     * 成本 60%，可用性 30%，延迟 10%
     */
    public static function createEconomic(): self
    {
        return (new self('economic', '经济策略', '优先考虑成本，兼顾稳定性'))
            ->addStrategy(new CostStrategy(), 0.60)
            ->addStrategy(new AvailabilityStrategy(), 0.30)
            ->addStrategy(new LatencyStrategy(), 0.10);
    }
}
