<?php
/**
 * Failover Manager - 故障转移管理器
 *
 * 管理多个 Provider 的故障转移逻辑
 *
 * @package WPMind
 * @since 1.5.0
 */

declare(strict_types=1);

namespace WPMind\Failover;

class FailoverManager
{
    private static ?FailoverManager $instance = null;

    /** @var array<string, CircuitBreaker> */
    private array $circuitBreakers = [];

    /** @var array Provider 配置 */
    private array $providers = [];

    /**
     * 获取单例实例
     */
    public static function instance(): FailoverManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 从 WPMind 获取已启用的 Provider
        if (function_exists('WPMind\\wpmind')) {
            $endpoints = \WPMind\wpmind()->get_custom_endpoints();
            foreach ($endpoints as $id => $config) {
                if (!empty($config['enabled']) && !empty($config['api_key'])) {
                    $this->providers[$id] = $config;
                    $this->circuitBreakers[$id] = new CircuitBreaker($id);
                }
            }
        }
    }

    /**
     * 选择最佳可用 Provider
     *
     * 优先级：
     * 1. 用户设置的首选 Provider（如果可用）
     * 2. 健康分数最高的 Provider
     *
     * @param string|null $preferredProvider 首选 Provider ID
     * @return string|null 选中的 Provider ID
     */
    public function selectProvider(?string $preferredProvider = null): ?string
    {
        $available = $this->getAvailableProviders();

        if (empty($available)) {
            return null;
        }

        // 优先使用用户首选
        if ($preferredProvider && in_array($preferredProvider, $available, true)) {
            return $preferredProvider;
        }

        // 按健康分数排序
        usort($available, function ($a, $b) {
            $scoreA = ProviderHealthTracker::getHealthScore($a);
            $scoreB = ProviderHealthTracker::getHealthScore($b);

            // 分数相同时，按延迟排序
            if ($scoreA === $scoreB) {
                $latencyA = ProviderHealthTracker::getAverageLatency($a);
                $latencyB = ProviderHealthTracker::getAverageLatency($b);
                return $latencyA - $latencyB;
            }

            return $scoreB - $scoreA;
        });

        return $available[0];
    }

    /**
     * 获取所有可用的 Provider
     *
     * @return array<string> 可用的 Provider ID 列表
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->circuitBreakers as $providerId => $breaker) {
            if ($breaker->isAvailable()) {
                $available[] = $providerId;
            }
        }

        return $available;
    }

    /**
     * 获取故障转移链
     *
     * 返回按优先级排序的 Provider 列表，用于依次尝试
     *
     * @param string|null $preferredProvider 首选 Provider ID
     * @return array<string> Provider ID 列表
     */
    public function getFailoverChain(?string $preferredProvider = null): array
    {
        $available = $this->getAvailableProviders();

        if (empty($available)) {
            return [];
        }

        // 检查是否有手动优先级设置
        $manual_priority = [];
        if (class_exists('\\WPMind\\Routing\\IntelligentRouter')) {
            $router = \WPMind\Routing\IntelligentRouter::instance();
            $manual_priority = $router->getManualPriority();
        }

        if (!empty($manual_priority)) {
            // 使用手动优先级排序
            $sorted = [];
            foreach ($manual_priority as $providerId) {
                if (in_array($providerId, $available, true)) {
                    $sorted[] = $providerId;
                }
            }
            // 添加未在手动列表中的可用 Provider
            foreach ($available as $providerId) {
                if (!in_array($providerId, $sorted, true)) {
                    $sorted[] = $providerId;
                }
            }
            $available = $sorted;
        } else {
            // 按健康分数排序
            usort($available, function ($a, $b) {
                return ProviderHealthTracker::getHealthScore($b) - ProviderHealthTracker::getHealthScore($a);
            });
        }

        // 首选 Provider 放在最前面
        if ($preferredProvider && in_array($preferredProvider, $available, true)) {
            $available = array_values(array_diff($available, [$preferredProvider]));
            array_unshift($available, $preferredProvider);
        }

        return $available;
    }

    /**
     * 记录请求结果
     *
     * @param string $providerId Provider ID
     * @param bool   $success    是否成功
     * @param int    $latencyMs  延迟（毫秒）
     */
    public function recordResult(string $providerId, bool $success, int $latencyMs = 0): void
    {
        // 更新熔断器状态
        if (isset($this->circuitBreakers[$providerId])) {
            if ($success) {
                $this->circuitBreakers[$providerId]->recordSuccess();
            } else {
                $this->circuitBreakers[$providerId]->recordFailure();
            }
        }

        // 记录健康追踪
        ProviderHealthTracker::record($providerId, $success, $latencyMs);
    }

    /**
     * 获取 Provider 状态摘要
     *
     * @return array Provider 状态信息
     */
    public function getStatusSummary(): array
    {
        $summary = [];

        foreach ($this->circuitBreakers as $providerId => $breaker) {
            $cbStatus = $breaker->getStatusDetails();
            $healthStatus = ProviderHealthTracker::getProviderStatus($providerId);

            $summary[$providerId] = [
                'name'          => $this->providers[$providerId]['name'] ?? $providerId,
                'display_name'  => $this->providers[$providerId]['display_name'] ?? $providerId,
                'state'         => $cbStatus['state'],
                'state_label'   => $cbStatus['state_label'],
                'available'     => $breaker->isAvailableReadOnly(),
                'health_score'  => $healthStatus['health_score'],
                'avg_latency'   => $healthStatus['avg_latency'],
                'success_rate'  => $healthStatus['success_rate'],
                'recovery_in'   => $cbStatus['recovery_in'],
            ];
        }

        return $summary;
    }

    /**
     * 检查是否有可用的 Provider
     *
     * @return bool
     */
    public function hasAvailableProvider(): bool
    {
        return !empty($this->getAvailableProviders());
    }

    /**
     * 重置指定 Provider 的熔断器
     *
     * @param string $providerId Provider ID
     */
    public function resetProvider(string $providerId): void
    {
        if (isset($this->circuitBreakers[$providerId])) {
            $this->circuitBreakers[$providerId]->reset();
        }
        ProviderHealthTracker::clearProvider($providerId);
    }

    /**
     * 重置所有熔断器
     */
    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $breaker) {
            $breaker->reset();
        }
        ProviderHealthTracker::clearAll();
    }

    /**
     * 获取熔断器实例
     *
     * @param string $providerId Provider ID
     * @return CircuitBreaker|null
     */
    public function getCircuitBreaker(string $providerId): ?CircuitBreaker
    {
        return $this->circuitBreakers[$providerId] ?? null;
    }

    /**
     * 刷新 Provider 列表
     *
     * 当 Provider 配置变更时调用
     */
    public function refresh(): void
    {
        self::$instance = null;
        self::instance();
    }
}
