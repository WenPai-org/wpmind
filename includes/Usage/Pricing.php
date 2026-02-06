<?php
/**
 * AI Provider Pricing Data
 *
 * Centralized pricing constants shared between UsageTracker implementations.
 * Prices are per 1M tokens. Data source: provider official sites (2026-01).
 *
 * @package WPMind\Usage
 * @since 3.2.1
 */

declare(strict_types=1);

namespace WPMind\Usage;

class Pricing
{
    /**
     * Provider pricing data (per 1M tokens).
     *
     * currency: USD or CNY
     */
    public const DATA = [
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
            'deepseek-chat' => ['input' => 1.00, 'output' => 2.00],
            'deepseek-reasoner' => ['input' => 4.00, 'output' => 16.00],
            'default' => ['input' => 1.00, 'output' => 2.00],
        ],
        'qwen' => [
            'currency' => 'CNY',
            'qwen-turbo' => ['input' => 2.00, 'output' => 6.00],
            'qwen-plus' => ['input' => 4.00, 'output' => 12.00],
            'qwen-max' => ['input' => 20.00, 'output' => 60.00],
            'default' => ['input' => 2.00, 'output' => 6.00],
        ],
        'zhipu' => [
            'currency' => 'CNY',
            'glm-4' => ['input' => 100.00, 'output' => 100.00],
            'glm-4-flash' => ['input' => 1.00, 'output' => 1.00],
            'glm-4-plus' => ['input' => 50.00, 'output' => 50.00],
            'default' => ['input' => 1.00, 'output' => 1.00],
        ],
        'moonshot' => [
            'currency' => 'CNY',
            'moonshot-v1-8k' => ['input' => 12.00, 'output' => 12.00],
            'moonshot-v1-32k' => ['input' => 24.00, 'output' => 24.00],
            'moonshot-v1-128k' => ['input' => 60.00, 'output' => 60.00],
            'default' => ['input' => 12.00, 'output' => 12.00],
        ],
        'doubao' => [
            'currency' => 'CNY',
            'doubao-pro-4k' => ['input' => 0.80, 'output' => 2.00],
            'doubao-pro-32k' => ['input' => 0.80, 'output' => 2.00],
            'doubao-pro-128k' => ['input' => 5.00, 'output' => 9.00],
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
            'ernie-4.0' => ['input' => 30.00, 'output' => 60.00],
            'ernie-3.5' => ['input' => 1.20, 'output' => 1.20],
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
     * Get pricing for a provider.
     */
    public static function get(string $provider): array
    {
        return self::DATA[$provider] ?? [];
    }

    /**
     * Get currency for a provider.
     */
    public static function getCurrency(string $provider): string
    {
        return self::DATA[$provider]['currency'] ?? 'USD';
    }

    /**
     * Calculate cost for a request.
     */
    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens
    ): float {
        $pricing = self::DATA[$provider] ?? [];
        $modelPricing = $pricing[$model] ?? $pricing['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($inputTokens / 1_000_000) * ($modelPricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1_000_000) * ($modelPricing['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }
}
