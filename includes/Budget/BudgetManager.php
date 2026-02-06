<?php
/**
 * Budget Manager - 预算管理器 (兼容层)
 *
 * 此文件为向后兼容保留，实际功能已迁移到 Cost Control 模块
 *
 * @package WPMind
 * @since 1.7.0
 * @deprecated 3.3.0 Use WPMind\Modules\CostControl\BudgetManager instead
 */

declare(strict_types=1);

namespace WPMind\Budget;

// 如果模块类存在，委托给模块
if ( class_exists( '\\WPMind\\Modules\\CostControl\\BudgetManager' ) ) {
    /**
     * 兼容层：委托给 Cost Control 模块
     *
     * @since 3.3.0
     */
    class BudgetManager
    {
        /**
         * 强制模式常量
         */
        public const MODE_ALERT    = 'alert';
        public const MODE_DISABLE  = 'disable';
        public const MODE_DOWNGRADE = 'downgrade';

        /**
         * 获取单例实例
         *
         * @return \WPMind\Modules\CostControl\BudgetManager
         */
        public static function instance(): \WPMind\Modules\CostControl\BudgetManager
        {
            return \WPMind\Modules\CostControl\BudgetManager::instance();
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
                [ \WPMind\Modules\CostControl\BudgetManager::instance(), $name ],
                $arguments
            );
        }
    }
} else {
    // 模块未加载时的回退实现
    require_once __DIR__ . '/BudgetManagerFallback.php';
}
