<?php
/**
 * Intelligent Router - 智能路由器
 *
 * 基于策略的 Provider 路由选择
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing;

use WPMind\Routing\Strategies\AvailabilityStrategy;
use WPMind\Routing\Strategies\CompositeStrategy;
use WPMind\Routing\Strategies\CostStrategy;
use WPMind\Routing\Strategies\LatencyStrategy;
use WPMind\Routing\Strategies\LoadBalancedStrategy;

class IntelligentRouter
{
    private static ?IntelligentRouter $instance = null;

    /** @var array<string, RoutingStrategyInterface> 已注册的策略 */
    private array $strategies = [];

    /** @var string 当前激活的策略名称 */
    private string $activeStrategy = 'balanced';

    /** @var array Provider 配置 */
    private array $providers = [];

    /**
     * 获取单例实例
     */
    public static function instance(): IntelligentRouter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->registerDefaultStrategies();
        $this->loadProviders();
        $this->loadSettings();
    }

    /**
     * 注册默认策略
     */
    private function registerDefaultStrategies(): void
    {
        // 复合策略（推荐）
        $this->registerStrategy(CompositeStrategy::createBalanced());      // 平衡策略
        $this->registerStrategy(CompositeStrategy::createPerformance());   // 性能优先
        $this->registerStrategy(CompositeStrategy::createEconomic());      // 经济策略
        
        // 基础策略
        $this->registerStrategy(new LoadBalancedStrategy());               // 负载均衡
    }

    /**
     * 加载 Provider 配置
     */
    private function loadProviders(): void
    {
        if (function_exists('WPMind\\wpmind')) {
            $endpoints = \WPMind\wpmind()->get_custom_endpoints();
            foreach ($endpoints as $id => $config) {
                if (!empty($config['enabled']) && !empty($config['api_key'])) {
                    $this->providers[$id] = $config;
                }
            }
        }
    }

    /**
     * 加载路由设置
     */
    private function loadSettings(): void
    {
        $settings = get_option('wpmind_routing_settings', array());
        $strategy = $settings['strategy'] ?? 'balanced';
        
        // 验证策略是否存在，否则回退到默认
        if ( ! isset( $this->strategies[ $strategy ] ) ) {
            $strategy = 'balanced';
            // 自动修复无效配置
            if ( isset( $settings['strategy'] ) ) {
                $settings['strategy'] = 'balanced';
                update_option('wpmind_routing_settings', $settings);
            }
        }
        
        $this->activeStrategy = $strategy;
    }

    /**
     * 注册路由策略
     *
     * @param RoutingStrategyInterface $strategy 策略实例
     */
    public function registerStrategy(RoutingStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    /**
     * 获取所有已注册的策略
     *
     * @return array<string, array{name: string, display_name: string, description: string}>
     */
    public function getAvailableStrategies(): array
    {
        $result = [];
        foreach ($this->strategies as $name => $strategy) {
            $result[$name] = [
                'name' => $strategy->getName(),
                'display_name' => $strategy->getDisplayName(),
                'description' => $strategy->getDescription(),
            ];
        }
        return $result;
    }

    /**
     * 设置当前策略
     *
     * @param string $strategyName 策略名称
     * @return bool 是否设置成功
     */
    public function setStrategy(string $strategyName): bool
    {
        if (!isset($this->strategies[$strategyName])) {
            return false;
        }

        $this->activeStrategy = $strategyName;

        // 保存设置
        $settings = get_option('wpmind_routing_settings', []);
        $settings['strategy'] = $strategyName;
        update_option('wpmind_routing_settings', $settings);

        return true;
    }

    /**
     * 获取当前策略名称
     */
    public function getCurrentStrategy(): string
    {
        return $this->activeStrategy;
    }

    /**
     * 获取当前策略实例
     */
    public function getStrategy(?string $name = null): ?RoutingStrategyInterface
    {
        $name = $name ?? $this->activeStrategy;
        
        // 如果请求的策略不存在，尝试回退到 'balanced'
        if ( ! isset( $this->strategies[ $name ] ) && $name !== 'balanced' ) {
            // 如果 balanced 也不存在，返回第一个可用策略
            if ( isset( $this->strategies['balanced'] ) ) {
                $name = 'balanced';
            } else {
                $name = array_key_first( $this->strategies );
            }
        }
        
        return $this->strategies[ $name ] ?? null;
    }

    /**
     * 路由请求到最佳 Provider
     *
     * @param RoutingContext|null $context 路由上下文
     * @return string|null 选中的 Provider ID
     */
    public function route(?RoutingContext $context = null): ?string
    {
        $context = $context ?? RoutingContext::create();
        $strategy = $this->getStrategy();

        if ($strategy === null) {
            // 无策略时，返回第一个可用的 Provider
            return array_key_first($this->providers);
        }

        // 如果有首选 Provider 且可用，优先使用
        $preferred = $context->getPreferredProvider();
        if ($preferred !== null && isset($this->providers[$preferred])) {
            if (!$context->isExcluded($preferred)) {
                return $preferred;
            }
        }

        return $strategy->selectProvider($context, $this->providers);
    }

    /**
     * 获取故障转移链
     *
     * 返回按策略排序的 Provider 列表
     *
     * @param RoutingContext|null $context 路由上下文
     * @return array<string> Provider ID 列表
     */
    public function getFailoverChain(?RoutingContext $context = null): array
    {
        $context = $context ?? RoutingContext::create();
        $strategy = $this->getStrategy();

        if ($strategy === null) {
            return array_keys($this->providers);
        }

        $ranked = $strategy->rankProviders($context, $this->providers);

        // 如果有首选 Provider，放在最前面
        $preferred = $context->getPreferredProvider();
        if ($preferred !== null && in_array($preferred, $ranked, true)) {
            $ranked = array_values(array_diff($ranked, [$preferred]));
            array_unshift($ranked, $preferred);
        }

        return $ranked;
    }

    /**
     * 获取所有 Provider 的路由得分
     *
     * @param RoutingContext|null $context 路由上下文
     * @return array<string, array{score: float, rank: int}>
     */
    public function getProviderScores(?RoutingContext $context = null): array
    {
        $context = $context ?? RoutingContext::create();
        $strategy = $this->getStrategy();

        if ($strategy === null) {
            return [];
        }

        $scores = [];
        foreach ($this->providers as $providerId => $config) {
            $scores[$providerId] = [
                'score' => $strategy->calculateScore($providerId, $context),
                'name' => $config['display_name'] ?? $providerId,
            ];
        }

        // 按得分排序并添加排名
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        $rank = 1;
        foreach ($scores as $providerId => &$data) {
            $data['rank'] = $rank++;
        }

        return $scores;
    }

    /**
     * 获取路由状态摘要
     *
     * @return array 状态信息
     */
    public function getStatusSummary(): array
    {
        $context = RoutingContext::create();
        $strategy = $this->getStrategy();

        return [
            'active_strategy' => [
                'name' => $this->activeStrategy,
                'display_name' => $strategy?->getDisplayName() ?? '未知',
                'description' => $strategy?->getDescription() ?? '',
            ],
            'available_strategies' => $this->getAvailableStrategies(),
            'provider_count' => count($this->providers),
            'provider_scores' => $this->getProviderScores($context),
            'recommended' => $this->route($context),
            'failover_chain' => $this->getFailoverChain($context),
        ];
    }

    /**
     * 刷新路由器状态
     */
    public function refresh(): void
    {
        self::$instance = null;
        self::instance();
    }

    /**
     * 获取手动设置的 Provider 优先级
     *
     * @return array<string> Provider ID 列表，按优先级排序
     */
    public function getManualPriority(): array
    {
        $settings = get_option('wpmind_routing_settings', []);
        return $settings['provider_priority'] ?? [];
    }

    /**
     * 设置手动 Provider 优先级
     *
     * @param array<string> $priority Provider ID 列表，按优先级排序
     * @return bool 是否设置成功
     */
    public function setManualPriority(array $priority): bool
    {
        // 验证所有 Provider ID 都有效
        $valid_providers = array_keys($this->providers);
        $priority = array_filter($priority, fn($id) => in_array($id, $valid_providers, true));

        $settings = get_option('wpmind_routing_settings', []);
        $settings['provider_priority'] = array_values($priority);

        return update_option('wpmind_routing_settings', $settings);
    }

    /**
     * 清除手动优先级设置
     *
     * @return bool 是否清除成功
     */
    public function clearManualPriority(): bool
    {
        $settings = get_option('wpmind_routing_settings', []);
        unset($settings['provider_priority']);

        return update_option('wpmind_routing_settings', $settings);
    }

    /**
     * 检查是否启用了手动优先级
     *
     * @return bool
     */
    public function hasManualPriority(): bool
    {
        $priority = $this->getManualPriority();
        return !empty($priority);
    }
}
