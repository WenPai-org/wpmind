# WPMind 故障转移机制设计文档

> 版本: 1.0.0 | 创建日期: 2026-01-31

## 研究来源

- [Salesforce Agentforce Failover Design](https://www.salesforce.com/blog/failover-design/) - 99.99% 可用性实现
- [Circuit Breaker Pattern - Dev.to](https://dev.to/apisix/implementing-resilient-applications-with-api-gateway-circuit-breaker-ggk)
- [Ganesha - PHP Circuit Breaker](https://github.com/ackintosh/ganesha)
- [PrestaShop Circuit Breaker](http://packagist.org/packages/prestashop/circuit-breaker)

---

## 核心概念

### Circuit Breaker 三状态模型

```
┌─────────┐    失败次数 > 阈值    ┌─────────┐
│  Closed │ ──────────────────→ │  Open   │
│ (正常)  │                      │ (熔断)  │
└─────────┘                      └─────────┘
     ↑                                │
     │                                │ 超时后
     │                                ↓
     │         成功次数 > 阈值   ┌──────────┐
     └─────────────────────────│Half-Open│
                               │ (半开)   │
                               └──────────┘
```

| 状态 | 说明 | 行为 |
|------|------|------|
| **Closed** | 正常状态 | 请求正常发送，记录失败次数 |
| **Open** | 熔断状态 | 直接返回错误或切换到备用服务 |
| **Half-Open** | 恢复探测 | 允许少量请求测试服务是否恢复 |

---

## 推荐方案：双层故障转移

### 架构设计

```
用户请求
    ↓
┌─────────────────────────────────────────┐
│  Layer 1: 软故障转移 (Soft Failover)     │
│  - 单次请求失败时自动重试到备用 Provider  │
│  - 适用于偶发性错误                       │
└─────────────────────────────────────────┘
    ↓ 失败率 > 40% (60秒窗口)
┌─────────────────────────────────────────┐
│  Layer 2: 熔断器 (Circuit Breaker)       │
│  - 跳过主 Provider，直接使用备用          │
│  - 20分钟后自动恢复探测                   │
└─────────────────────────────────────────┘
```

### Salesforce 实践参考

| 策略 | 参数 | 说明 |
|------|------|------|
| 软故障转移触发 | 4xx/5xx 错误 | 单次请求失败时重试 |
| 熔断器触发 | 40% 失败率 / 60秒 | 持续故障时熔断 |
| 熔断恢复时间 | 20 分钟 | 自动恢复探测 |
| 延迟并行重试 | 超时后并行请求 | 提高响应速度 |

---

## WPMind 实现方案

### 文件结构

```
includes/
├── Failover/
│   ├── CircuitBreaker.php          # 熔断器核心类
│   ├── ProviderHealthTracker.php   # Provider 健康状态追踪
│   ├── FailoverManager.php         # 故障转移管理器
│   └── FailoverConfig.php          # 配置类
```

### 核心类设计

#### 1. CircuitBreaker.php

```php
<?php
namespace WPMind\Failover;

class CircuitBreaker
{
    // 状态常量
    const STATE_CLOSED    = 'closed';
    const STATE_OPEN      = 'open';
    const STATE_HALF_OPEN = 'half_open';

    // 默认配置
    const DEFAULT_FAILURE_THRESHOLD = 5;      // 失败次数阈值
    const DEFAULT_FAILURE_RATE      = 0.4;    // 失败率阈值 (40%)
    const DEFAULT_WINDOW_SIZE       = 60;     // 统计窗口 (秒)
    const DEFAULT_RECOVERY_TIME     = 1200;   // 恢复时间 (20分钟)
    const DEFAULT_HALF_OPEN_REQUESTS = 3;     // 半开状态允许的请求数

    private string $providerId;
    private string $transientKey;

    public function __construct(string $providerId)
    {
        $this->providerId = $providerId;
        $this->transientKey = 'wpmind_cb_' . $providerId;
    }

    /**
     * 获取当前状态
     */
    public function getState(): string
    {
        $data = get_transient($this->transientKey);
        if (!$data) {
            return self::STATE_CLOSED;
        }
        return $data['state'] ?? self::STATE_CLOSED;
    }

    /**
     * 检查是否允许请求
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // 检查是否应该进入半开状态
            if ($this->shouldTransitionToHalfOpen()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
                return true;
            }
            return false;
        }

        // 半开状态：允许有限请求
        return $this->canAllowHalfOpenRequest();
    }

    /**
     * 记录成功
     */
    public function recordSuccess(): void
    {
        $data = $this->getData();
        $data['successes'] = ($data['successes'] ?? 0) + 1;
        $data['last_success'] = time();

        // 半开状态下成功次数达标，恢复到关闭状态
        if ($data['state'] === self::STATE_HALF_OPEN) {
            if ($data['successes'] >= self::DEFAULT_HALF_OPEN_REQUESTS) {
                $this->transitionTo(self::STATE_CLOSED);
                return;
            }
        }

        $this->saveData($data);
    }

    /**
     * 记录失败
     */
    public function recordFailure(): void
    {
        $data = $this->getData();
        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $data['last_failure'] = time();

        // 检查是否应该熔断
        if ($this->shouldTrip($data)) {
            $this->transitionTo(self::STATE_OPEN);
            return;
        }

        $this->saveData($data);
    }

    /**
     * 检查是否应该熔断
     */
    private function shouldTrip(array $data): bool
    {
        $failures = $data['failures'] ?? 0;
        $total = $failures + ($data['successes'] ?? 0);

        // 失败次数超过阈值
        if ($failures >= self::DEFAULT_FAILURE_THRESHOLD) {
            return true;
        }

        // 失败率超过阈值 (至少有10次请求)
        if ($total >= 10 && ($failures / $total) >= self::DEFAULT_FAILURE_RATE) {
            return true;
        }

        return false;
    }

    /**
     * 状态转换
     */
    private function transitionTo(string $newState): void
    {
        $data = [
            'state'        => $newState,
            'failures'     => 0,
            'successes'    => 0,
            'transitioned' => time(),
        ];
        $this->saveData($data);
    }

    /**
     * 检查是否应该从开启转为半开
     */
    private function shouldTransitionToHalfOpen(): bool
    {
        $data = $this->getData();
        $transitioned = $data['transitioned'] ?? 0;
        return (time() - $transitioned) >= self::DEFAULT_RECOVERY_TIME;
    }

    private function getData(): array
    {
        return get_transient($this->transientKey) ?: [];
    }

    private function saveData(array $data): void
    {
        set_transient($this->transientKey, $data, self::DEFAULT_WINDOW_SIZE * 2);
    }
}
```

#### 2. ProviderHealthTracker.php

```php
<?php
namespace WPMind\Failover;

class ProviderHealthTracker
{
    private const TRANSIENT_KEY = 'wpmind_provider_health';
    private const HISTORY_SIZE = 20;  // 保留最近20次请求记录

    /**
     * 记录请求结果
     */
    public static function record(string $providerId, bool $success, int $latencyMs): void
    {
        $health = get_transient(self::TRANSIENT_KEY) ?: [];

        if (!isset($health[$providerId])) {
            $health[$providerId] = [
                'history'      => [],
                'total'        => 0,
                'failures'     => 0,
                'avg_latency'  => 0,
            ];
        }

        $provider = &$health[$providerId];

        // 添加到历史记录
        $provider['history'][] = [
            'success'  => $success,
            'latency'  => $latencyMs,
            'time'     => time(),
        ];

        // 保持历史记录大小
        if (count($provider['history']) > self::HISTORY_SIZE) {
            array_shift($provider['history']);
        }

        // 更新统计
        $provider['total']++;
        if (!$success) {
            $provider['failures']++;
        }

        // 计算平均延迟
        $latencies = array_column($provider['history'], 'latency');
        $provider['avg_latency'] = array_sum($latencies) / count($latencies);

        set_transient(self::TRANSIENT_KEY, $health, 3600);
    }

    /**
     * 获取 Provider 健康分数 (0-100)
     */
    public static function getHealthScore(string $providerId): int
    {
        $health = get_transient(self::TRANSIENT_KEY) ?: [];

        if (!isset($health[$providerId]) || empty($health[$providerId]['history'])) {
            return 100; // 无数据时假设健康
        }

        $provider = $health[$providerId];
        $history = $provider['history'];

        // 计算最近的成功率
        $recentSuccesses = array_filter($history, fn($h) => $h['success']);
        $successRate = count($recentSuccesses) / count($history);

        // 分数 = 成功率 * 100
        return (int) round($successRate * 100);
    }

    /**
     * 获取所有 Provider 的健康状态
     */
    public static function getAllHealth(): array
    {
        return get_transient(self::TRANSIENT_KEY) ?: [];
    }
}
```

#### 3. FailoverManager.php

```php
<?php
namespace WPMind\Failover;

class FailoverManager
{
    private array $providers;
    private array $circuitBreakers = [];

    public function __construct(array $enabledProviders)
    {
        $this->providers = $enabledProviders;

        foreach ($enabledProviders as $providerId => $config) {
            $this->circuitBreakers[$providerId] = new CircuitBreaker($providerId);
        }
    }

    /**
     * 选择最佳可用 Provider
     *
     * 优先级：
     * 1. 用户设置的默认 Provider（如果可用）
     * 2. 健康分数最高的 Provider
     */
    public function selectProvider(?string $preferredProvider = null): ?string
    {
        $available = $this->getAvailableProviders();

        if (empty($available)) {
            return null;
        }

        // 优先使用用户首选
        if ($preferredProvider && in_array($preferredProvider, $available)) {
            return $preferredProvider;
        }

        // 按健康分数排序
        usort($available, function($a, $b) {
            $scoreA = ProviderHealthTracker::getHealthScore($a);
            $scoreB = ProviderHealthTracker::getHealthScore($b);
            return $scoreB - $scoreA;
        });

        return $available[0];
    }

    /**
     * 获取所有可用的 Provider
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
     */
    public function getFailoverChain(?string $preferredProvider = null): array
    {
        $available = $this->getAvailableProviders();

        if (empty($available)) {
            return [];
        }

        // 首选 Provider 放在最前面
        if ($preferredProvider && in_array($preferredProvider, $available)) {
            $available = array_diff($available, [$preferredProvider]);
            array_unshift($available, $preferredProvider);
        }

        return array_values($available);
    }

    /**
     * 记录请求结果
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
     */
    public function getStatusSummary(): array
    {
        $summary = [];

        foreach ($this->circuitBreakers as $providerId => $breaker) {
            $summary[$providerId] = [
                'state'        => $breaker->getState(),
                'available'    => $breaker->isAvailable(),
                'health_score' => ProviderHealthTracker::getHealthScore($providerId),
            ];
        }

        return $summary;
    }
}
```

---

## 集成方案

### 与现有架构的集成点

```
WPMind 主插件
    ↓
FailoverManager (新增)
    ↓
ProviderRegistrar (现有)
    ↓
WordPress AI Client
```

### 需要修改的文件

| 文件 | 修改内容 |
|------|----------|
| `wpmind.php` | 添加 FailoverManager 初始化 |
| `register.php` | 在 Provider 注册时集成故障转移 |
| `settings-page.php` | 添加默认 Provider 选择和状态显示 |

### 设置页面新增选项

```php
// 默认 Provider 选择
'wpmind_default_provider' => [
    'type'    => 'select',
    'label'   => '首选 AI 服务',
    'options' => $enabled_providers,
]

// 故障转移开关
'wpmind_failover_enabled' => [
    'type'    => 'checkbox',
    'label'   => '启用自动故障转移',
    'default' => true,
]
```

---

## 配置参数

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `failure_threshold` | 5 | 触发熔断的连续失败次数 |
| `failure_rate` | 0.4 (40%) | 触发熔断的失败率 |
| `window_size` | 60秒 | 统计窗口大小 |
| `recovery_time` | 1200秒 (20分钟) | 熔断恢复时间 |
| `half_open_requests` | 3 | 半开状态允许的测试请求数 |

---

## 实施计划

### Phase 1: 基础实现
1. 创建 `includes/Failover/` 目录
2. 实现 `CircuitBreaker.php`
3. 实现 `ProviderHealthTracker.php`
4. 实现 `FailoverManager.php`

### Phase 2: 集成
1. 修改 `wpmind.php` 初始化 FailoverManager
2. 修改 `register.php` 集成故障转移逻辑
3. 添加设置页面选项

### Phase 3: UI 和监控
1. 设置页面显示 Provider 状态
2. 添加健康状态指示器
3. 日志记录故障转移事件

---

## 风险评估

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| 与 WordPress AI Client 冲突 | 中 | 只在 WPMind 层面实现，不修改核心 |
| 状态存储性能 | 低 | 使用 transient，自动过期 |
| 误判导致不必要的切换 | 中 | 保守的阈值设置，可配置 |
| 复杂度增加 | 中 | 模块化设计，可独立禁用 |

---

## 待讨论事项

- [ ] 是否需要在设置页面显示实时状态？
- [ ] 故障转移事件是否需要通知管理员？
- [ ] 是否支持用户自定义阈值参数？
- [ ] 是否需要 WP-CLI 命令查看/重置状态？

---

*最后更新: 2026-01-31*
