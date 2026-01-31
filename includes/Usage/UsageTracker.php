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
     * 各服务商的定价 (per 1M tokens)
     * 数据来源: 各服务商官网 (2026-01)
     *
     * currency: USD 或 CNY
     * 国内服务商使用人民币计价
     */
    private const PRICING = [
        'openai' => [
            'currency' => 'USD',
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            'default' => ['input' => 2.50, 'output' => 10.00],
        ],
        'anthropic' => [
            'currency' => 'USD',
            'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
            'default' => ['input' => 3.00, 'output' => 15.00],
        ],
        'google' => [
            'currency' => 'USD',
            'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
            'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
            'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
            'default' => ['input' => 0.075, 'output' => 0.30],
        ],
        'deepseek' => [
            'currency' => 'CNY',
            'deepseek-chat' => ['input' => 1.00, 'output' => 2.00],      // ¥1/¥2 per 1M
            'deepseek-reasoner' => ['input' => 4.00, 'output' => 16.00], // ¥4/¥16 per 1M
            'default' => ['input' => 1.00, 'output' => 2.00],
        ],
        'qwen' => [
            'currency' => 'CNY',
            'qwen-turbo' => ['input' => 2.00, 'output' => 6.00],   // ¥0.002/¥0.006 per 1K
            'qwen-plus' => ['input' => 4.00, 'output' => 12.00],   // ¥0.004/¥0.012 per 1K
            'qwen-max' => ['input' => 20.00, 'output' => 60.00],   // ¥0.02/¥0.06 per 1K
            'default' => ['input' => 2.00, 'output' => 6.00],
        ],
        'zhipu' => [
            'currency' => 'CNY',
            'glm-4' => ['input' => 100.00, 'output' => 100.00],    // ¥0.1 per 1K
            'glm-4-flash' => ['input' => 1.00, 'output' => 1.00],  // ¥0.001 per 1K (免费额度后)
            'glm-4-plus' => ['input' => 50.00, 'output' => 50.00], // ¥0.05 per 1K
            'default' => ['input' => 1.00, 'output' => 1.00],
        ],
        'moonshot' => [
            'currency' => 'CNY',
            'moonshot-v1-8k' => ['input' => 12.00, 'output' => 12.00],   // ¥12 per 1M
            'moonshot-v1-32k' => ['input' => 24.00, 'output' => 24.00],  // ¥24 per 1M
            'moonshot-v1-128k' => ['input' => 60.00, 'output' => 60.00], // ¥60 per 1M
            'default' => ['input' => 12.00, 'output' => 12.00],
        ],
        'doubao' => [
            'currency' => 'CNY',
            'doubao-pro-4k' => ['input' => 0.80, 'output' => 2.00],     // ¥0.0008/¥0.002 per 1K
            'doubao-pro-32k' => ['input' => 0.80, 'output' => 2.00],
            'doubao-pro-128k' => ['input' => 5.00, 'output' => 9.00],   // ¥0.005/¥0.009 per 1K
            'default' => ['input' => 0.80, 'output' => 2.00],
        ],
        'siliconflow' => [
            'currency' => 'CNY',
            'deepseek-ai/DeepSeek-V3' => ['input' => 1.00, 'output' => 2.00],
            'Qwen/Qwen2.5-72B-Instruct' => ['input' => 4.00, 'output' => 4.00],
            'default' => ['input' => 1.00, 'output' => 2.00],
        ],
        'baidu' => [
            'currency' => 'CNY',
            'ernie-4.0' => ['input' => 30.00, 'output' => 60.00],  // ¥0.03/¥0.06 per 1K
            'ernie-3.5' => ['input' => 1.20, 'output' => 1.20],    // ¥0.0012 per 1K
            'default' => ['input' => 1.20, 'output' => 1.20],
        ],
        'minimax' => [
            'currency' => 'CNY',
            'abab6.5s-chat' => ['input' => 1.00, 'output' => 1.00],
            'abab6.5-chat' => ['input' => 30.00, 'output' => 30.00],
            'default' => ['input' => 1.00, 'output' => 1.00],
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
        // 输入验证：确保 tokens 非负
        $inputTokens = max(0, $inputTokens);
        $outputTokens = max(0, $outputTokens);
        $latencyMs = max(0, $latencyMs);

        if ($inputTokens === 0 && $outputTokens === 0) {
            return;
        }

        // 计算成本
        $cost = self::calculateCost($provider, $model, $inputTokens, $outputTokens);

        // 更新汇总统计
        self::updateStats($provider, $model, $inputTokens, $outputTokens, $cost);

        // 添加到历史记录
        self::addToHistory($provider, $model, $inputTokens, $outputTokens, $cost, $latencyMs);
    }

    /**
     * 计算成本
     *
     * @return float 成本（按服务商货币计价）
     */
    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens
    ): float {
        $pricing = self::PRICING[$provider] ?? [];
        $modelPricing = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($inputTokens / 1_000_000) * ($modelPricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1_000_000) * ($modelPricing['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * 获取服务商的货币类型
     *
     * @param string $provider Provider ID
     * @return string USD 或 CNY
     */
    public static function getCurrency(string $provider): string
    {
        return self::PRICING[$provider]['currency'] ?? 'USD';
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
        // 使用对象缓存减少数据库读取
        $cache_key = 'wpmind_usage_stats';
        $stats = wp_cache_get($cache_key);

        if (false === $stats) {
            $stats = get_option(self::OPTION_KEY, []);
        }

        $today = date('Y-m-d');
        $month = date('Y-m');
        $currency = self::getCurrency($provider);

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
                'cost_usd' => 0,
                'cost_cny' => 0,
                'requests' => 0,
            ];
        }

        if (!isset($stats['monthly'][$month])) {
            $stats['monthly'][$month] = [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'cost_cny' => 0,
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

        // 更新日统计（分货币）
        $stats['daily'][$today]['input_tokens'] += $inputTokens;
        $stats['daily'][$today]['output_tokens'] += $outputTokens;
        if ($currency === 'CNY') {
            $stats['daily'][$today]['cost_cny'] = ($stats['daily'][$today]['cost_cny'] ?? 0) + $cost;
        } else {
            $stats['daily'][$today]['cost_usd'] = ($stats['daily'][$today]['cost_usd'] ?? 0) + $cost;
        }
        $stats['daily'][$today]['requests']++;

        // 更新月统计（分货币）
        $stats['monthly'][$month]['input_tokens'] += $inputTokens;
        $stats['monthly'][$month]['output_tokens'] += $outputTokens;
        if ($currency === 'CNY') {
            $stats['monthly'][$month]['cost_cny'] = ($stats['monthly'][$month]['cost_cny'] ?? 0) + $cost;
        } else {
            $stats['monthly'][$month]['cost_usd'] = ($stats['monthly'][$month]['cost_usd'] ?? 0) + $cost;
        }
        $stats['monthly'][$month]['requests']++;

        // 更新总计（分货币）
        if (!isset($stats['total'])) {
            $stats['total'] = [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'cost_cny' => 0,
                'requests' => 0,
            ];
        }
        $stats['total']['input_tokens'] += $inputTokens;
        $stats['total']['output_tokens'] += $outputTokens;
        if ($currency === 'CNY') {
            $stats['total']['cost_cny'] = ($stats['total']['cost_cny'] ?? 0) + $cost;
        } else {
            $stats['total']['cost_usd'] = ($stats['total']['cost_usd'] ?? 0) + $cost;
        }
        $stats['total']['requests']++;

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

        // 保存到数据库和缓存
        update_option(self::OPTION_KEY, $stats, false);
        wp_cache_set($cache_key, $stats, '', 300); // 缓存 5 分钟
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
        // 使用对象缓存
        $cache_key = 'wpmind_usage_history';
        $history = wp_cache_get($cache_key);

        if (false === $history) {
            $history = get_option(self::HISTORY_KEY, []);
        }

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

        // 保存到数据库和缓存
        update_option(self::HISTORY_KEY, $history, false);
        wp_cache_set($cache_key, $history, '', 300); // 缓存 5 分钟
    }

    /**
     * 获取汇总统计
     */
    public static function getStats(): array
    {
        // 优先从缓存读取
        $cache_key = 'wpmind_usage_stats';
        $stats = wp_cache_get($cache_key);

        if (false === $stats) {
            $stats = get_option(self::OPTION_KEY, []);
            wp_cache_set($cache_key, $stats, '', 300);
        }

        return $stats ?: [
            'providers' => [],
            'daily' => [],
            'monthly' => [],
            'total' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'cost_cny' => 0,
                'requests' => 0,
            ],
        ];
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
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];
    }

    /**
     * 获取本周统计（周一到周日）
     */
    public static function getWeekStats(): array
    {
        $stats = self::getStats();
        $daily = $stats['daily'] ?? [];

        // 计算本周的日期范围（周一到今天）
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $today = date('Y-m-d');

        $result = [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];

        // 汇总本周每天的数据
        foreach ($daily as $date => $dayStats) {
            if ($date >= $weekStart && $date <= $today) {
                $result['input_tokens'] += $dayStats['input_tokens'] ?? 0;
                $result['output_tokens'] += $dayStats['output_tokens'] ?? 0;
                $result['cost_usd'] += $dayStats['cost_usd'] ?? 0;
                $result['cost_cny'] += $dayStats['cost_cny'] ?? 0;
                $result['requests'] += $dayStats['requests'] ?? 0;
            }
        }

        return $result;
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
            'cost_usd' => 0,
            'cost_cny' => 0,
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
        // 优先从缓存读取
        $cache_key = 'wpmind_usage_history';
        $history = wp_cache_get($cache_key);

        if (false === $history) {
            $history = get_option(self::HISTORY_KEY, []);
            wp_cache_set($cache_key, $history, '', 300);
        }

        return array_slice(array_reverse($history ?: []), 0, $limit);
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

        // 清除缓存
        wp_cache_delete('wpmind_usage_stats');
        wp_cache_delete('wpmind_usage_history');
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
     *
     * @param float $cost 成本
     * @param string $currency 货币类型 (USD/CNY)
     * @return string 格式化后的成本
     */
    public static function formatCost(float $cost, string $currency = 'USD'): string
    {
        $symbol = $currency === 'CNY' ? '¥' : '$';

        if ($cost < 0.01) {
            return $symbol . number_format($cost, 4);
        }
        return $symbol . number_format($cost, 2);
    }

    /**
     * 格式化分货币费用显示
     *
     * @param float $costUsd USD 费用
     * @param float $costCny CNY 费用
     * @return string 格式化后的费用字符串，如 "$0.50 / ¥2.00"
     */
    public static function formatCostByCurrency(float $costUsd, float $costCny): string
    {
        $parts = [];

        if ($costUsd > 0) {
            $parts[] = self::formatCost($costUsd, 'USD');
        }

        if ($costCny > 0) {
            $parts[] = self::formatCost($costCny, 'CNY');
        }

        if (empty($parts)) {
            return '$0.00';
        }

        return implode(' / ', $parts);
    }

    /**
     * 获取 Provider 的显示名称
     */
    public static function getProviderDisplayName(string $provider): string
    {
        $names = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'google' => 'Google AI',
            'deepseek' => 'DeepSeek',
            'qwen' => '通义千问',
            'zhipu' => '智谱 AI',
            'moonshot' => 'Moonshot',
            'doubao' => '豆包',
            'siliconflow' => '硅基流动',
            'baidu' => '百度文心',
            'minimax' => 'MiniMax',
        ];
        return $names[$provider] ?? $provider;
    }
}
