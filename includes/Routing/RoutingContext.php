<?php
/**
 * Routing Context - 路由上下文
 *
 * 封装路由决策所需的所有上下文信息
 *
 * @package WPMind
 * @since 1.9.0
 */

declare(strict_types=1);

namespace WPMind\Routing;

use WPMind\Failover\ProviderHealthTracker;
use WPMind\Usage\UsageTracker;

class RoutingContext
{
    /** @var string|null 请求的模型类型 (chat, completion, embedding) */
    private ?string $modelType = null;

    /** @var int 预估的输入 token 数 */
    private int $estimatedInputTokens = 0;

    /** @var int 预估的输出 token 数 */
    private int $estimatedOutputTokens = 0;

    /** @var string|null 用户首选的 Provider */
    private ?string $preferredProvider = null;

    /** @var array<string> 排除的 Provider 列表 */
    private array $excludedProviders = [];

    /** @var array<string, mixed> 额外的上下文数据 */
    private array $metadata = [];

    /** @var array|null 缓存的健康数据 */
    private ?array $healthData = null;

    /** @var array|null 缓存的使用统计 */
    private ?array $usageStats = null;

    /**
     * 创建新的路由上下文
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 设置模型类型
     */
    public function withModelType(string $type): self
    {
        $this->modelType = $type;
        return $this;
    }

    /**
     * 设置预估 token 数
     */
    public function withEstimatedTokens(int $input, int $output = 0): self
    {
        $this->estimatedInputTokens = max(0, $input);
        $this->estimatedOutputTokens = max(0, $output);
        return $this;
    }

    /**
     * 设置首选 Provider
     */
    public function withPreferredProvider(?string $providerId): self
    {
        $this->preferredProvider = $providerId;
        return $this;
    }

    /**
     * 添加排除的 Provider
     */
    public function withExcludedProvider(string $providerId): self
    {
        if (!in_array($providerId, $this->excludedProviders, true)) {
            $this->excludedProviders[] = $providerId;
        }
        return $this;
    }

    /**
     * 设置排除的 Provider 列表
     */
    public function withExcludedProviders(array $providerIds): self
    {
        $this->excludedProviders = array_values(array_unique($providerIds));
        return $this;
    }

    /**
     * 添加元数据
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    // Getters

    public function getModelType(): ?string
    {
        return $this->modelType;
    }

    public function getEstimatedInputTokens(): int
    {
        return $this->estimatedInputTokens;
    }

    public function getEstimatedOutputTokens(): int
    {
        return $this->estimatedOutputTokens;
    }

    public function getEstimatedTotalTokens(): int
    {
        return $this->estimatedInputTokens + $this->estimatedOutputTokens;
    }

    public function getPreferredProvider(): ?string
    {
        return $this->preferredProvider;
    }

    public function getExcludedProviders(): array
    {
        return $this->excludedProviders;
    }

    public function isExcluded(string $providerId): bool
    {
        return in_array($providerId, $this->excludedProviders, true);
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 获取 Provider 健康数据（带缓存）
     */
    public function getHealthData(): array
    {
        if ($this->healthData === null) {
            $this->healthData = ProviderHealthTracker::getAllHealth();
        }
        return $this->healthData;
    }

    /**
     * 获取指定 Provider 的健康分数（使用缓存）
     */
    public function getHealthScore(string $providerId): int
    {
        $healthData = $this->getHealthData();
        if (!isset($healthData[$providerId]) || empty($healthData[$providerId]['history'])) {
            return 100;
        }
        $history = $healthData[$providerId]['history'];
        $recentSuccesses = array_filter($history, fn($h) => $h['success']);
        return (int) round((count($recentSuccesses) / count($history)) * 100);
    }

    /**
     * 获取指定 Provider 的平均延迟（使用缓存）
     */
    public function getAverageLatency(string $providerId): int
    {
        $healthData = $this->getHealthData();
        return $healthData[$providerId]['avg_latency'] ?? 0;
    }

    /**
     * 获取使用统计（带缓存）
     */
    public function getUsageStats(): array
    {
        if ($this->usageStats === null) {
            $this->usageStats = UsageTracker::getStats();
        }
        return $this->usageStats;
    }

    /**
     * 获取指定 Provider 的使用统计
     */
    public function getProviderUsageStats(string $providerId): array
    {
        $stats = $this->getUsageStats();
        return $stats['providers'][$providerId] ?? [
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cost' => 0,
            'request_count' => 0,
        ];
    }

    /**
     * 计算指定 Provider 的预估成本
     */
    public function estimateCost(string $providerId, string $model = 'default'): float
    {
        return UsageTracker::calculateCost(
            $providerId,
            $model,
            $this->estimatedInputTokens,
            $this->estimatedOutputTokens
        );
    }
}
