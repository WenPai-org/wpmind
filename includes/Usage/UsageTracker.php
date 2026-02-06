<?php
/**
 * Usage Tracker - Token 用量追踪 (兼容层)
 *
 * 此文件为向后兼容保留，实际功能已迁移到 Cost Control 模块
 *
 * @package WPMind
 * @since 1.6.0
 * @deprecated 3.3.0 Use WPMind\Modules\CostControl\UsageTracker instead
 */

declare(strict_types=1);

namespace WPMind\Usage;

// 如果模块类存在，委托给模块
if ( class_exists( '\\WPMind\\Modules\\CostControl\\UsageTracker' ) ) {
    /**
     * 兼容层：委托给 Cost Control 模块
     *
     * @since 3.3.0
     */
    class UsageTracker
    {
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
            \WPMind\Modules\CostControl\UsageTracker::record(
                $provider,
                $model,
                $inputTokens,
                $outputTokens,
                $latencyMs
            );
        }

        /**
         * 魔术方法：静态调用转发
         *
         * @param string $name 方法名
         * @param array  $arguments 参数
         * @return mixed
         */
        public static function __callStatic( string $name, array $arguments )
        {
            return call_user_func_array(
                [ '\\WPMind\\Modules\\CostControl\\UsageTracker', $name ],
                $arguments
            );
        }
    }
} else {
    // 模块未加载时的回退实现
    require_once __DIR__ . '/UsageTrackerFallback.php';
}
