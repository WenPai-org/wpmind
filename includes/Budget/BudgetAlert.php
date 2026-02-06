<?php
/**
 * Budget Alert - 预算告警通知 (兼容层)
 *
 * 此文件为向后兼容保留，实际功能已迁移到 Cost Control 模块
 *
 * @package WPMind
 * @since 1.7.0
 * @deprecated 3.3.0 Use WPMind\Modules\CostControl\BudgetAlert instead
 */

declare(strict_types=1);

namespace WPMind\Budget;

// 如果模块类存在，委托给模块
if ( class_exists( '\\WPMind\\Modules\\CostControl\\BudgetAlert' ) ) {
    /**
     * 兼容层：委托给 Cost Control 模块
     *
     * @since 3.3.0
     */
    class BudgetAlert
    {
        /**
         * 获取单例实例
         *
         * @return \WPMind\Modules\CostControl\BudgetAlert
         */
        public static function instance(): \WPMind\Modules\CostControl\BudgetAlert
        {
            return \WPMind\Modules\CostControl\BudgetAlert::instance();
        }

        /**
         * 初始化告警系统
         */
        public static function init(): void
        {
            \WPMind\Modules\CostControl\BudgetAlert::init();
        }

        /**
         * 获取预算状态徽章 HTML
         */
        public static function getStatusBadge( string $status ): string
        {
            return \WPMind\Modules\CostControl\BudgetAlert::getStatusBadge( $status );
        }

        /**
         * 获取进度条 HTML
         */
        public static function getProgressBar( float $percentage, string $status ): string
        {
            return \WPMind\Modules\CostControl\BudgetAlert::getProgressBar( $percentage, $status );
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
                [ \WPMind\Modules\CostControl\BudgetAlert::instance(), $name ],
                $arguments
            );
        }
    }
} else {
    // 模块未加载时的回退实现
    require_once __DIR__ . '/BudgetAlertFallback.php';
}
