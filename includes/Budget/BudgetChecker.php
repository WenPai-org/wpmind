<?php
/**
 * Budget Checker - 预算检查器 (兼容层)
 *
 * 此文件为向后兼容保留，实际功能已迁移到 Cost Control 模块
 *
 * @package WPMind
 * @since 1.7.0
 * @deprecated 3.3.0 Use WPMind\Modules\CostControl\BudgetChecker instead
 */

declare(strict_types=1);

namespace WPMind\Budget;

// 如果模块类存在，委托给模块
if ( class_exists( '\\WPMind\\Modules\\CostControl\\BudgetChecker' ) ) {
    /**
     * 兼容层：委托给 Cost Control 模块
     *
     * @since 3.3.0
     */
    class BudgetChecker
    {
        /**
         * 检查结果常量
         */
        public const STATUS_OK        = 'ok';
        public const STATUS_WARNING   = 'warning';
        public const STATUS_EXCEEDED  = 'exceeded';

        /**
         * 获取单例实例
         *
         * @return \WPMind\Modules\CostControl\BudgetChecker
         */
        public static function instance(): \WPMind\Modules\CostControl\BudgetChecker
        {
            return \WPMind\Modules\CostControl\BudgetChecker::instance();
        }

        /**
         * 获取状态的显示标签
         */
        public static function getStatusLabel( string $status ): string
        {
            return \WPMind\Modules\CostControl\BudgetChecker::getStatusLabel( $status );
        }

        /**
         * 获取状态的 CSS 类
         */
        public static function getStatusClass( string $status ): string
        {
            return \WPMind\Modules\CostControl\BudgetChecker::getStatusClass( $status );
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
                [ \WPMind\Modules\CostControl\BudgetChecker::instance(), $name ],
                $arguments
            );
        }
    }
} else {
    // 模块未加载时的回退实现
    require_once __DIR__ . '/BudgetCheckerFallback.php';
}
