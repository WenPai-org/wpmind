<?php
/**
 * Analytics Manager - 兼容层
 *
 * 当 Analytics 模块启用时代理到模块实现，
 * 否则使用 Fallback 实现。
 *
 * @package WPMind\Analytics
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Analytics;

use WPMind\Core\ModuleLoader;

class AnalyticsManager
{
    /**
     * 单例实例
     */
    private static ?AnalyticsManager $instance = null;

    /**
     * 模块实例
     */
    private $moduleInstance = null;

    /**
     * 获取单例实例
     */
    public static function instance(): AnalyticsManager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有构造函数
     */
    private function __construct()
    {
        $this->init_module_instance();
    }

    /**
     * 初始化模块实例
     */
    private function init_module_instance(): void
    {
        $module_loader = ModuleLoader::instance();

        if ($module_loader->is_module_enabled('analytics')) {
            // 模块启用，使用模块实现
            if (class_exists('\\WPMind\\Modules\\Analytics\\AnalyticsManager')) {
                $this->moduleInstance = \WPMind\Modules\Analytics\AnalyticsManager::instance();
            }
        }

        // 如果模块未启用或类不存在，使用 Fallback
        if ($this->moduleInstance === null) {
            if (!class_exists('\\WPMind\\Analytics\\AnalyticsManagerFallback')) {
                require_once __DIR__ . '/AnalyticsManagerFallback.php';
            }
            $this->moduleInstance = AnalyticsManagerFallback::instance();
        }
    }

    /**
     * 魔术方法：代理所有方法调用到实际实现
     */
    public function __call(string $name, array $arguments)
    {
        if ($this->moduleInstance && method_exists($this->moduleInstance, $name)) {
            return call_user_func_array([$this->moduleInstance, $name], $arguments);
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', get_class($this), $name)
        );
    }

    /**
     * 获取用量趋势数据
     */
    public function get_usage_trend(int $days = 7, ?array $stats = null): array
    {
        return $this->moduleInstance->get_usage_trend($days, $stats);
    }

    /**
     * 获取服务商对比数据
     */
    public function get_provider_comparison(?array $stats = null): array
    {
        return $this->moduleInstance->get_provider_comparison($stats);
    }

    /**
     * 获取成本分析数据
     */
    public function get_cost_analysis(int $months = 6, ?array $stats = null): array
    {
        return $this->moduleInstance->get_cost_analysis($months, $stats);
    }

    /**
     * 获取模型使用分布
     */
    public function get_model_distribution(?array $stats = null): array
    {
        return $this->moduleInstance->get_model_distribution($stats);
    }

    /**
     * 获取性能指标
     */
    public function get_latency_metrics(int $limit = 100): array
    {
        return $this->moduleInstance->get_latency_metrics($limit);
    }

    /**
     * 获取仪表板摘要数据
     */
    public function get_dashboard_summary(): array
    {
        return $this->moduleInstance->get_dashboard_summary();
    }

    /**
     * 获取完整的分析数据
     */
    public function get_analytics_data(string $range = '7d'): array
    {
        return $this->moduleInstance->get_analytics_data($range);
    }
}
