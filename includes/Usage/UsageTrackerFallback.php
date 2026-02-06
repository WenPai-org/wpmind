<?php
/**
 * Usage Tracker Fallback - Token 用量追踪回退实现
 *
 * 当 Cost Control 模块未加载时使用此实现
 *
 * @package WPMind
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Usage;

/**
 * 回退实现：保持原有功能
 */
class UsageTracker
{
    private const OPTION_KEY = 'wpmind_usage_stats';
    private const HISTORY_KEY = 'wpmind_usage_history';
    private const MAX_HISTORY = 1000;

    /**
     * Get pricing data from shared Pricing class.
     */
    private static function get_pricing_data(): array
    {
        if (!class_exists(Pricing::class)) {
            require_once __DIR__ . '/Pricing.php';
        }
        return Pricing::DATA;
    }

    public static function record(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs = 0
    ): void {
        $inputTokens = max(0, $inputTokens);
        $outputTokens = max(0, $outputTokens);
        $latencyMs = max(0, $latencyMs);

        $cost = self::calculate_cost($provider, $model, $inputTokens, $outputTokens);
        self::update_stats($provider, $model, $inputTokens, $outputTokens, $cost);

        if ($inputTokens > 0 || $outputTokens > 0) {
            self::add_to_history($provider, $model, $inputTokens, $outputTokens, $cost, $latencyMs);
        }
    }

    public static function calculate_cost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens
    ): float {
        $allPricing = self::get_pricing_data();
        $pricing = $allPricing[$provider] ?? [];
        $modelPricing = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($inputTokens / 1_000_000) * ($modelPricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1_000_000) * ($modelPricing['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    public static function get_currency(string $provider): string
    {
        $allPricing = self::get_pricing_data();
        return $allPricing[$provider]['currency'] ?? 'USD';
    }

    private static function update_stats(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost
    ): void {
        $stats = get_option(self::OPTION_KEY, []);
        if (!is_array($stats)) {
            $stats = [];
        }

        $today = wp_date('Y-m-d');
        $month = wp_date('Y-m');
        $currency = self::get_currency($provider);

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

        $stats['providers'][$provider]['total_input_tokens'] += $inputTokens;
        $stats['providers'][$provider]['total_output_tokens'] += $outputTokens;
        $stats['providers'][$provider]['total_cost'] += $cost;
        $stats['providers'][$provider]['request_count']++;

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

        $stats['daily'][$today]['input_tokens'] += $inputTokens;
        $stats['daily'][$today]['output_tokens'] += $outputTokens;
        if ($currency === 'CNY') {
            $stats['daily'][$today]['cost_cny'] = ($stats['daily'][$today]['cost_cny'] ?? 0) + $cost;
        } else {
            $stats['daily'][$today]['cost_usd'] = ($stats['daily'][$today]['cost_usd'] ?? 0) + $cost;
        }
        $stats['daily'][$today]['requests']++;

        $stats['monthly'][$month]['input_tokens'] += $inputTokens;
        $stats['monthly'][$month]['output_tokens'] += $outputTokens;
        if ($currency === 'CNY') {
            $stats['monthly'][$month]['cost_cny'] = ($stats['monthly'][$month]['cost_cny'] ?? 0) + $cost;
        } else {
            $stats['monthly'][$month]['cost_usd'] = ($stats['monthly'][$month]['cost_usd'] ?? 0) + $cost;
        }
        $stats['monthly'][$month]['requests']++;

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

        update_option(self::OPTION_KEY, $stats, false);
    }

    private static function add_to_history(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        int $latencyMs
    ): void {
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
    }

    public static function get_stats(): array
    {
        $stats = get_option(self::OPTION_KEY, []);
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

    public static function get_today_stats(): array
    {
        $stats = self::get_stats();
        $today = wp_date('Y-m-d');

        return $stats['daily'][$today] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];
    }

    public static function get_month_stats(): array
    {
        $stats = self::get_stats();
        $month = wp_date('Y-m');

        return $stats['monthly'][$month] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];
    }

    public static function get_week_stats(): array
    {
        $stats = self::get_stats();
        $daily = $stats['daily'] ?? [];

        $weekStart = wp_date('Y-m-d', strtotime('monday this week'));
        $today = wp_date('Y-m-d');

        $result = [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'cost_cny' => 0,
            'requests' => 0,
        ];

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

    public static function get_history(int $limit = 50): array
    {
        $history = get_option(self::HISTORY_KEY, []);
        if (!is_array($history)) {
            $history = [];
        }

        return array_slice(array_reverse($history), 0, $limit);
    }

    public static function get_pricing(): array
    {
        return self::get_pricing_data();
    }

    public static function clear_all(): void
    {
        delete_option(self::OPTION_KEY);
        delete_option(self::HISTORY_KEY);
        wp_cache_delete('wpmind_usage_stats');
        wp_cache_delete('wpmind_usage_history');
    }

    public static function format_tokens(int $tokens): string
    {
        if ($tokens >= 1_000_000) {
            return round($tokens / 1_000_000, 2) . 'M';
        }
        if ($tokens >= 1_000) {
            return round($tokens / 1_000, 1) . 'K';
        }
        return (string) $tokens;
    }

    public static function format_cost(float $cost, string $currency = 'USD'): string
    {
        $symbol = $currency === 'CNY' ? '¥' : '$';

        if ($cost < 0.01) {
            return $symbol . number_format($cost, 4);
        }
        return $symbol . number_format($cost, 2);
    }

    public static function format_cost_by_currency(float $costUsd, float $costCny): string
    {
        $parts = [];

        if ($costUsd > 0) {
            $parts[] = self::format_cost($costUsd, 'USD');
        }

        if ($costCny > 0) {
            $parts[] = self::format_cost($costCny, 'CNY');
        }

        if (empty($parts)) {
            return '$0.00';
        }

        return implode(' / ', $parts);
    }

    public static function get_provider_display_name(string $provider): string
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

    public static function get_provider_icon(string $provider): string
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

    public static function get_provider_color(string $provider): string
    {
        return '#50575e';
    }
}
