<?php
/**
 * Circuit Breaker - 熔断器
 *
 * 实现三状态熔断器模式：Closed -> Open -> Half-Open -> Closed
 *
 * @package WPMind
 * @since 1.5.0
 */

declare(strict_types=1);

namespace WPMind\Failover;

class CircuitBreaker
{
    // 状态常量
    public const STATE_CLOSED    = 'closed';
    public const STATE_OPEN      = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    // 默认配置 (基于 Salesforce Agentforce 实践)
    private const FAILURE_THRESHOLD    = 5;      // 触发熔断的连续失败次数
    private const FAILURE_RATE         = 0.4;    // 触发熔断的失败率 (40%)
    private const WINDOW_SIZE          = 60;     // 统计窗口 (秒)
    private const RECOVERY_TIME        = 1200;   // 恢复时间 (20分钟)
    private const HALF_OPEN_REQUESTS   = 3;      // 半开状态允许的测试请求数
    private const MIN_REQUESTS         = 10;     // 计算失败率的最小请求数

    private string $providerId;
    private string $transientKey;

    public function __construct(string $providerId)
    {
        $this->providerId = $providerId;
        $this->transientKey = 'wpmind_cb_' . sanitize_key($providerId);
    }

    /**
     * 获取当前状态
     */
    public function get_state(): string
    {
        $data = $this->get_data();
        return $data['state'] ?? self::STATE_CLOSED;
    }

    /**
     * 检查是否允许请求通过
     *
     * @param bool $allowTransition 是否允许状态转换（默认 true）
     */
    public function is_available(bool $allowTransition = true): bool
    {
        $state = $this->get_state();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            if ($this->shouldTransitionToHalfOpen()) {
                if ($allowTransition) {
                    $this->transition_to(self::STATE_HALF_OPEN);
                }
                return true;
            }
            return false;
        }

        // 半开状态：检查是否还有测试配额
        return $this->canAllowHalfOpenRequest();
    }

    /**
     * 检查是否可用（只读，不触发状态转换）
     *
     * 用于状态查询，不会修改熔断器状态
     */
    public function is_available_read_only(): bool
    {
        return $this->is_available(false);
    }

    /**
     * 记录成功请求
     */
    public function record_success(): void
    {
        $data = $this->get_data();
        $now = time();
        $state = $data['state'] ?? self::STATE_CLOSED;

        // 开启状态下，如果恢复时间已过，先转换到半开状态
        if ($state === self::STATE_OPEN && $this->shouldTransitionToHalfOpen()) {
            $this->transition_to(self::STATE_HALF_OPEN);
            $data = $this->get_data(); // 重新获取转换后的数据
            $state = self::STATE_HALF_OPEN;
        }

        // 记录带时间戳的请求
        $data['requests'][] = ['success' => true, 'time' => $now];
        $data['requests'] = $this->filterRecentRequests($data['requests'] ?? [], $now);

        $data['successes'] = ($data['successes'] ?? 0) + 1;
        $data['last_success'] = $now;
        $data['consecutive_failures'] = 0;

        // 半开状态下成功次数达标，恢复到关闭状态
        if ($state === self::STATE_HALF_OPEN) {
            $data['half_open_successes'] = ($data['half_open_successes'] ?? 0) + 1;
            if ($data['half_open_successes'] >= self::HALF_OPEN_REQUESTS) {
                $this->transition_to(self::STATE_CLOSED);
                return;
            }
        }

        $this->save_data($data);
    }

    /**
     * 记录失败请求
     */
    public function record_failure(): void
    {
        $data = $this->get_data();
        $now = time();
        $state = $data['state'] ?? self::STATE_CLOSED;

        // 开启状态下，如果恢复时间已过，先转换到半开状态
        if ($state === self::STATE_OPEN && $this->shouldTransitionToHalfOpen()) {
            $this->transition_to(self::STATE_HALF_OPEN);
            $data = $this->get_data();
            $state = self::STATE_HALF_OPEN;
        }

        // 记录带时间戳的请求
        $data['requests'][] = ['success' => false, 'time' => $now];
        $data['requests'] = $this->filterRecentRequests($data['requests'] ?? [], $now);

        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $data['consecutive_failures'] = ($data['consecutive_failures'] ?? 0) + 1;
        $data['last_failure'] = $now;

        // 半开状态下失败，立即回到开启状态
        if ($state === self::STATE_HALF_OPEN) {
            $data['half_open_failures'] = ($data['half_open_failures'] ?? 0) + 1;
            $this->save_data($data);
            $this->transition_to(self::STATE_OPEN);
            return;
        }

        // 检查是否应该熔断
        if ($this->should_trip($data)) {
            $this->transition_to(self::STATE_OPEN);
            return;
        }

        $this->save_data($data);
    }

    /**
     * 重置熔断器状态
     */
    public function reset(): void
    {
        delete_transient($this->transientKey);
    }

    /**
     * 获取状态详情
     */
    public function get_status_details(): array
    {
        $data = $this->get_data();
        $state = $data['state'] ?? self::STATE_CLOSED;

        return [
            'provider_id'          => $this->providerId,
            'state'                => $state,
            'state_label'          => $this->getStateLabel($state),
            'failures'             => $data['failures'] ?? 0,
            'successes'            => $data['successes'] ?? 0,
            'consecutive_failures' => $data['consecutive_failures'] ?? 0,
            'last_failure'         => $data['last_failure'] ?? null,
            'last_success'         => $data['last_success'] ?? null,
            'transitioned_at'      => $data['transitioned'] ?? null,
            'recovery_in'          => $this->getRecoveryTimeRemaining($data),
        ];
    }

    /**
     * 检查是否应该触发熔断
     */
    private function should_trip(array $data): bool
    {
        // 连续失败次数超过阈值
        $consecutiveFailures = $data['consecutive_failures'] ?? 0;
        if ($consecutiveFailures >= self::FAILURE_THRESHOLD) {
            return true;
        }

        // 基于时间窗口内的失败率判断
        $requests = $data['requests'] ?? [];
        if (count($requests) >= self::MIN_REQUESTS) {
            $failures = count(array_filter($requests, fn($r) => !$r['success']));
            $failureRate = $failures / count($requests);
            if ($failureRate >= self::FAILURE_RATE) {
                return true;
            }
        }

        return false;
    }

    /**
     * 过滤出时间窗口内的请求
     */
    private function filterRecentRequests(array $requests, int $now): array
    {
        $cutoff = $now - self::WINDOW_SIZE;
        return array_values(array_filter(
            $requests,
            fn($r) => ($r['time'] ?? 0) >= $cutoff
        ));
    }

    /**
     * 检查是否应该从开启转为半开
     */
    private function shouldTransitionToHalfOpen(): bool
    {
        $data = $this->get_data();
        $transitioned = $data['transitioned'] ?? 0;
        return (time() - $transitioned) >= self::RECOVERY_TIME;
    }

    /**
     * 检查半开状态是否还能接受请求
     */
    private function canAllowHalfOpenRequest(): bool
    {
        $data = $this->get_data();
        $halfOpenRequests = ($data['half_open_successes'] ?? 0) + ($data['half_open_failures'] ?? 0);
        return $halfOpenRequests < self::HALF_OPEN_REQUESTS;
    }

    /**
     * 状态转换
     */
    private function transition_to(string $newState): void
    {
        $data = [
            'state'                => $newState,
            'failures'             => 0,
            'successes'            => 0,
            'consecutive_failures' => 0,
            'transitioned'         => time(),
        ];

        if ($newState === self::STATE_HALF_OPEN) {
            $data['half_open_successes'] = 0;
            $data['half_open_failures'] = 0;
        }

        $this->save_data($data);
    }

    /**
     * 获取剩余恢复时间
     */
    private function getRecoveryTimeRemaining(array $data): ?int
    {
        if (($data['state'] ?? self::STATE_CLOSED) !== self::STATE_OPEN) {
            return null;
        }

        $transitioned = $data['transitioned'] ?? time();
        $remaining = self::RECOVERY_TIME - (time() - $transitioned);
        return max(0, $remaining);
    }

    /**
     * 获取状态标签
     */
    private function getStateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_CLOSED    => __('正常', 'wpmind'),
            self::STATE_OPEN      => __('熔断中', 'wpmind'),
            self::STATE_HALF_OPEN => __('恢复测试', 'wpmind'),
            default               => __('未知', 'wpmind'),
        };
    }

    private function get_data(): array
    {
        $data = get_transient($this->transientKey);
        return is_array($data) ? $data : [];
    }

    private function save_data(array $data): void
    {
        // TTL 必须大于 RECOVERY_TIME，否则状态会过早重置
        set_transient($this->transientKey, $data, self::RECOVERY_TIME * 2);
    }
}
