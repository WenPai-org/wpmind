<?php
/**
 * Usage Tracker - Token 用量追踪
 *
 * 记录每个 Provider 的 token 用量和成本估算
 *
 * @package WPMind
 * @since 1.6.0
 */

declare(strict_types=1);

namespace WPMind\Usage;

class UsageTracker
{
    private const OPTION_KEY = 'wpmind_usage_stats';
    private const HISTORY_KEY = 'wpmind_usage_history';
    private const MAX_HISTORY = 1000; // 最多保留 1000 条记录

    /**
     * 各服务商的定价 (USD per 1M tokens)
     * 数据来源: 各服务商官网 (2026-01)
     */
    private const PRICING = [
        'openai' => [
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            'default' => ['input' => 2.50, 'output' => 10.00],
        ],
        'anthropic' => [
            'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
            'default' => ['input' => 3.00, 'output' => 15.00],
        ],
        'google' => [
            'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
            'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
            'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
            'default' => ['input' => 0.075, 'output' => 0.30],
        ],
        'deepseek' => [
            'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
            'deepseek-reasoner' => ['input' => 0.55, 'output' => 2.19],
            'default' => ['input' => 0.14, 'output' => 0.28],
        ],
        'qwen' => [
            'qwen-turbo' => ['input' => 0.30, 'output' => 0.60],
            'qwen-plus' => ['input' => 0.80, 'output' => 2.00],
            'qwen-max' => ['input' => 2.40, 'output' => 9.60],
            'default' => ['input' => 0.30, 'output' => 0.60],
        ],
        'zhipu' => [
            'glm-4' => ['input' => 1.40, 'output' => 1.40],
            'glm-4-flash' => ['input' => 0.01, 'output' => 0.01],
            'glm-4-plus' => ['input' => 0.70, 'output' => 0.70],
            'default' => ['input' => 0.14, 'output' => 0.14],
        ],
        'moonshot' => [
            'moonshot-v1-8k' => ['input' => 1.70, 'output' => 1.70],
            'moonshot-v1-32k' => ['input' => 3.40, 'output' => 3.40],
            'moonshot-v1-128k' => ['input' => 8.50, 'output' => 8.50],
            'default' => ['input' => 1.70, 'output' => 1.70],
        ],
        'doubao' => [
            'doubao-pro-4k' => ['input' => 0.11, 'output' => 0.28],
            'doubao-pro-32k' => ['input' => 0.11, 'output' => 0.28],
            'doubao-pro-128k' => ['input' => 0.70, 'output' => 1.26],
            'default' => ['input' => 0.11, 'output' => 0.28],
        ],
        'siliconflow' => [
            'deepseek-ai/DeepSeek-V3' => ['input' => 0.14, 'output' => 0.28],
            'Qwen/Qwen2.5-72B-Instruct' => ['input' => 0.56, 'output' => 0.56],
            'default' => ['input' => 0.14, 'output' => 0.28],
        ],
        'baidu' => [
            'ernie-4.0' => ['input' => 4.20, 'output' => 8.40],
            'ernie-3.5' => ['input' => 0.17, 'output' => 0.17],
            'default' => ['input' => 0.17, 'output' => 0.17],
        ],
        'minimax' => [
            'abab6.5s-chat' => ['input' => 0.14, 'output' => 0.14],
            'abab6.5-chat' => ['input' => 4.20, 'output' => 4.20],
            'default' => ['input' => 0.14, 'output' => 0.14],
        ],
    ];

    /**
     * 记录一次 API 调用的 token 用量
     *
     * @param string $provider Provider ID
     * @param string $model 模型名称
     * @param int $inputTokens 输入 tokens
     * @param int $outputTokens 输出 tokens
     * @param int $latencyMs 延迟（毫秒）
     */
    public static function record(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs = 0
    ): void {
        // 计算成本
        $cost = self::calculateCost($provider, $model, $inputTokens, $outputTokens);

        // 更新汇总统计
        self::updateStats($provider, $model, $inputTokens, $outputTokens, $cost);

        // 添加到历史记录
        self::addToHistory($provider, $model, $inputTokens, $outputTokens, $cost, $latencyMs);
    }

    /**
     * 计算成本 (USD)
     */
    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens
    ): float {
        $pricing = self::PRICING[$provider] ?? [];
        $modelPricing = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($inputTokens / 1_000_000) * $modelPricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $modelPricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * 更新汇总统计
     */
    private static function updateStats(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost
    ): void {
        $stats = get_option(self::OPTION_KEY, []);

        $today = date('Y-m-d');
        $month = date('Y-m');

        // 初始化结构
        if (!isset($stats['providers'][$provider])) {
            $stats['providers'][$provider] = [
                'total_input_tokens' => 0,
                'total_output_tokens' => 0,
                'total_cost' => 0,
                'request_count' => 0,
                'models' => [],
            ];
        }

        if (!isset($stats['daily'][$today])) {
            $stats['daily'][$today] = [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost' => 0,
                'requests' => 0,
            ];
        }

        if (!isset($stats['monthly'][$month])) {
            $stats['monthly'][$month] = [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost' => 0,
                'requests' => 0,
            ];
        }

        // 更新 Provider 统计
        $stats['providers'][$provider]['total_input_tokens'] += $inputTokens;
        $stats['providers'][$provider]['total_output_tokens'] += $outputTokens;
        $stats['providers'][$provider]['total_cost'] += $cost;
        $stats['providers'][$provider]['request_count']++;

        // 更新模型统计
        if (!isset($stats['providers'][$provider]['models'][$model])) {
            $stats['providers'][$provider]['models'][$model] = [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost' => 0,
                'requests' => 0,
            ];
        }
        $stats['providers'][$provider]['models'][$model]['input_tokens'] += $inputTokens;
        $stats['providers'][$provider]['models'][$model]['output_tokens'] += $outputTokens;
        $stats['providers'][$provider]['models'][$model]['cost'] += $cost;
        $stats['providers'][$provider]['models'][$model]['requests']++;

        // 更新日统计
        $stats['daily'][$today]['input_tokens'] += $inputTokens;
        $stats['daily'][$today]['output_tokens'] += $outputTokens;
        $stats['daily'][$today]['cost'] += $cost;
        $stats['daily'][$today]['requests']++;

        // 更新月统计
        $stats['monthly'][$month]['input_tokens'] += $inputTokens;
        $stats['monthly'][$month]['output_tokens'] += $outputTokens;
        $stats['monthly'][$month]['cost'] += $cost;
        $stats['monthly'][$month]['requests']++;

        // 更新总计
        $stats['total'] = [
            'input_tokens' => ($stats['total']['input_tokens'] ?? 0) + $inputTokens,
            'output_tokens' => ($stats['total']['output_tokens'] ?? 0) + $outputTokens,
            'cost' => ($stats['total']['cost'] ?? 0) + $cost,
            'requests' => ($stats['total']['requests'] ?? 0) + 1,
        ];

        $stats['last_updated'] = time();

        // 清理旧的日统计（保留 30 天）
        $cutoffDate = date('Y-m-d', strtotime('-30 days'));
        foreach (array_keys($stats['daily'] ?? []) as $date) {
            if ($date < $cutoffDate) {
                unset($stats['daily'][$date]);
            }
        }

        // 清理旧的月统计（保留 12 个月）
        $cutoffMonth = date('Y-m', strtotime('-12 months'));
        foreach (array_keys($stats['monthly'] ?? []) as $m) {
            if ($m < $cutoffMonth) {
                unset($stats['monthly'][$m]);
            }
        }

        update_option(self::OPTION_KEY, $stats, false);
    }

    /**
     * 添加到历史记录
     */
    private static function addToHistory(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        int $latencyMs
    ): void {
        $history = get_option(self::HISTORY_KEY, []);

        $history[] = [
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
            'latency_ms' => $latencyMs,
            'timestamp' => time(),
        ];

        // 保持历史记录大小
        if (count($history) > self::MAX_HISTORY) {
            $history = array_slice($history, -self::MAX_HISTORY);
        }

        update_option(self::HISTORY_KEY, $history, false);
    }

    /**
     * 获取汇总统计
     */
    public static function getStats(): array
    {
        return get_option(self::OPTION_KEY, [
            'providers' => [],
            'daily' => [],
            'monthly' => [],
            'total' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost' => 0,
                'requests' => 0,
            ],
        ]);
    }

    /**
     * 获取今日统计
     */
    public static function getTodayStats(): array
    {
        $stats = self::getStats();
        $today = date('Y-m-d');

        return $stats['daily'][$today] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost' => 0,
            'requests' => 0,
        ];
    }

    /**
     * 获取本月统计
     */
    public static function getMonthStats(): array
    {
        $stats = self::getStats();
        $month = date('Y-m');

        return $stats['monthly'][$month] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost' => 0,
            'requests' => 0,
        ];
    }

    /**
     * 获取历史记录
     *
     * @param int $limit 返回条数
     * @return array
     */
    public static function getHistory(int $limit = 50): array
    {
        $history = get_option(self::HISTORY_KEY, []);
        return array_slice(array_reverse($history), 0, $limit);
    }

    /**
     * 获取定价信息
     */
    public static function getPricing(): array
    {
        return self::PRICING;
    }

    /**
     * 清除所有统计数据
     */
    public static function clearAll(): void
    {
        delete_option(self::OPTION_KEY);
        delete_option(self::HISTORY_KEY);
    }

    /**
     * 格式化 token 数量
     */
    public static function formatTokens(int $tokens): string
    {
        if ($tokens >= 1_000_000) {
            return round($tokens / 1_000_000, 2) . 'M';
        }
        if ($tokens >= 1_000) {
            return round($tokens / 1_000, 1) . 'K';
        }
        return (string) $tokens;
    }

    /**
     * 格式化成本
     */
    public static function formatCost(float $cost): string
    {
        if ($cost < 0.01) {
            return '$' . number_format($cost, 4);
        }
        return '$' . number_format($cost, 2);
    }
}
