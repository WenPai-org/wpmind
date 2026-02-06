<?php
/**
 * Usage Tracker - Token 用量追踪
 *
 * 记录每个 Provider 的 token 用量和成本估算
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\CostControl;

use WPMind\Usage\Pricing;

class UsageTracker
{
    private const OPTION_KEY = 'wpmind_usage_stats';
    private const HISTORY_KEY = 'wpmind_usage_history';
    private const MAX_HISTORY = 1000; // 最多保留 1000 条记录

    /**
     * 各服务商的定价 (per 1M tokens)
     *
     * @deprecated Use WPMind\Usage\Pricing::DATA instead.
     */
    private const PRICING = [];

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

        // 计算成本
        $cost = self::calculateCost($provider, $model, $inputTokens, $outputTokens);

        // 更新汇总统计
        self::updateStats($provider, $model, $inputTokens, $outputTokens, $cost);

        // 只有有 token 使用时才添加到历史记录
        if ($inputTokens > 0 || $outputTokens > 0) {
            self::addToHistory($provider, $model, $inputTokens, $outputTokens, $cost, $latencyMs);
        }
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
        $allPricing = self::getPricing();
        $pricing = $allPricing[$provider] ?? [];
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
        $allPricing = self::getPricing();
        return $allPricing[$provider]['currency'] ?? 'USD';
    }

    /**
     * 更新汇总统计（带并发锁）
     */
    private static function updateStats(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost
    ): void {
        $lock_key = 'wpmind_usage_stats_lock';
        $max_retries = 3;
        $retry_delay = 50000; // 50ms

        // 尝试获取锁
        for ($i = 0; $i < $max_retries; $i++) {
            if (false === get_transient($lock_key)) {
                set_transient($lock_key, true, 5);
                break;
            }
            usleep($retry_delay);
        }

        try {
            $stats = get_option(self::OPTION_KEY, []);

            if (!is_array($stats)) {
                $stats = [];
            }

            $today = wp_date('Y-m-d');
            $month = wp_date('Y-m');
            $currency = self::getCurrency($provider);

            // 初始化结构
            if (!isset($stats['providers']) || !is_array($stats['providers'])) {
                $stats['providers'] = [];
            }
            if (!isset($stats['providers'][$provider])) {
                $stats['providers'][$provider] = [
                    'total_input_tokens' => 0,
                    'total_output_tokens' => 0,
                    'total_cost' => 0,
                    'request_count' => 0,
                    'models' => [],
                ];
            }

            if (!isset($stats['daily']) || !is_array($stats['daily'])) {
                $stats['daily'] = [];
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

            if (!isset($stats['monthly']) || !is_array($stats['monthly'])) {
                $stats['monthly'] = [];
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
            if (!isset($stats['total']) || !is_array($stats['total'])) {
                $stats['total'] = [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'cost_usd' => 0,
                    'cost_cny' => 0,
                    'requests' => 0,
                ];
            }
            $stats['total']['input_tokens'] = ($stats['total']['input_tokens'] ?? 0) + $inputTokens;
            $stats['total']['output_tokens'] = ($stats['total']['output_tokens'] ?? 0) + $outputTokens;
            if ($currency === 'CNY') {
                $stats['total']['cost_cny'] = ($stats['total']['cost_cny'] ?? 0) + $cost;
            } else {
                $stats['total']['cost_usd'] = ($stats['total']['cost_usd'] ?? 0) + $cost;
            }
            $stats['total']['requests'] = ($stats['total']['requests'] ?? 0) + 1;

            $stats['last_updated'] = time();

            // 清理旧的日统计（保留 30 天）
            $cutoffDate = wp_date('Y-m-d', strtotime('-30 days'));
            foreach (array_keys($stats['daily'] ?? []) as $date) {
                if ($date < $cutoffDate) {
                    unset($stats['daily'][$date]);
                }
            }

            // 清理旧的月统计（保留 12 个月）
            $cutoffMonth = wp_date('Y-m', strtotime('-12 months'));
            foreach (array_keys($stats['monthly'] ?? []) as $m) {
                if ($m < $cutoffMonth) {
                    unset($stats['monthly'][$m]);
                }
            }

            update_option(self::OPTION_KEY, $stats, false);
            wp_cache_set('wpmind_usage_stats', $stats, '', 300);
        } finally {
            delete_transient($lock_key);
        }
    }
    /**
     * 添加到历史记录（带并发锁）
     */
    private static function addToHistory(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        int $latencyMs
    ): void {
        $lock_key = 'wpmind_usage_history_lock';
        $max_retries = 3;
        $retry_delay = 50000;

        for ($i = 0; $i < $max_retries; $i++) {
            if (false === get_transient($lock_key)) {
                set_transient($lock_key, true, 5);
                break;
            }
            usleep($retry_delay);
        }

        try {
            $history = get_option(self::HISTORY_KEY, []);

            if (!is_array($history)) {
                $history = [];
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

            if (count($history) > self::MAX_HISTORY) {
                $history = array_slice($history, -self::MAX_HISTORY);
            }

            update_option(self::HISTORY_KEY, $history, false);
            wp_cache_set('wpmind_usage_history', $history, '', 300);
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * 获取汇总统计
     */
    public static function getStats(): array
    {
        $cache_key = 'wpmind_usage_stats';
        $stats = wp_cache_get($cache_key);

        if (false === $stats) {
            $stats = get_option(self::OPTION_KEY, []);
            wp_cache_set($cache_key, $stats, '', 300);
        }

        if (!is_array($stats)) {
            $stats = [];
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
        $today = wp_date('Y-m-d');

        return $stats['daily'][$today] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];
    }

    /**
     * 获取本月统计
     */
    public static function getMonthStats(): array
    {
        $stats = self::getStats();
        $month = wp_date('Y-m');

        return $stats['monthly'][$month] ?? [
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

        // 使用 WordPress 时区计算本周的日期范围
        $weekStart = wp_date('Y-m-d', strtotime('monday this week'));
        $today = wp_date('Y-m-d');

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
     * 获取历史记录
     */
    public static function getHistory(int $limit = 50): array
    {
        $cache_key = 'wpmind_usage_history';
        $history = wp_cache_get($cache_key);

        if (false === $history) {
            $history = get_option(self::HISTORY_KEY, []);
            wp_cache_set($cache_key, $history, '', 300);
        }

        if (!is_array($history)) {
            $history = [];
        }

        return array_slice(array_reverse($history), 0, $limit);
    }

    /**
     * 获取定价信息
     */
    public static function getPricing(): array
    {
        if (!class_exists(Pricing::class)) {
            require_once WPMIND_PATH . 'includes/Usage/Pricing.php';
        }
        return Pricing::DATA;
    }

    /**
     * 清除所有统计数据
     */
    public static function clearAll(): void
    {
        delete_option(self::OPTION_KEY);
        delete_option(self::HISTORY_KEY);
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

    /**
     * 获取 Provider 的 Remixicon 图标类
     */
    public static function getProviderIcon(string $provider): string
    {
        $icons = [
            'openai'      => 'ri-openai-line',
            'anthropic'   => 'ri-claude-line',
            'google'      => 'ri-gemini-line',
            'deepseek'    => 'ri-deepseek-line',
            'qwen'        => 'ri-qwen-ai-line',
            'zhipu'       => 'ri-zhipu-ai-line',
            'moonshot'    => 'ri-moon-line',
            'doubao'      => 'ri-fire-line',
            'siliconflow' => 'ri-cpu-line',
            'baidu'       => 'ri-baidu-line',
            'minimax'     => 'ri-sparkling-line',
        ];
        return $icons[$provider] ?? 'ri-robot-line';
    }

    /**
     * 获取 Provider 的图标颜色
     */
    public static function getProviderColor(string $provider): string
    {
        // 统一使用单色，与 WordPress 后台风格一致
        return '#50575e';
    }
}
