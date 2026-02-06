<?php
/**
 * Analytics Module - 分析面板模块
 *
 * @package WPMind\Modules\Analytics
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Analytics;

use WPMind\Core\ModuleInterface;

class AnalyticsModule implements ModuleInterface
{
    /**
     * 模块配置
     */
    private array $config = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $config_file = __DIR__ . '/module.json';
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true) ?: [];
        }
    }

    /**
     * 获取模块 ID
     */
    public function get_id(): string
    {
        return 'analytics';
    }

    /**
     * 获取模块名称
     */
    public function get_name(): string
    {
        return $this->config['name'] ?? 'Analytics';
    }

    /**
     * 获取模块描述
     */
    public function get_description(): string
    {
        return $this->config['description'] ?? '分析仪表板';
    }

    /**
     * 获取模块版本
     */
    public function get_version(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * 获取模块依赖
     */
    public function get_dependencies(): array
    {
        return $this->config['requires'] ?? [];
    }

    /**
     * 检查模块依赖是否满足
     */
    public function check_dependencies(): bool
    {
        $requires = $this->get_dependencies();

        if (empty($requires)) {
            return true;
        }

        $module_loader = \WPMind\Core\ModuleLoader::instance();

        foreach ($requires as $required_module) {
            if (!$module_loader->is_module_enabled($required_module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取设置标签页
     */
    public function get_settings_tab(): ?string
    {
        return $this->config['settings_tab'] ?? 'dashboard';
    }

    /**
     * 初始化模块
     */
    public function init(): void
    {
        // 加载模块类
        $this->load_classes();

        // 注册设置标签页
        add_filter('wpmind_settings_tabs', [$this, 'register_settings_tab'], 10);

        // 注册 AJAX 处理器
        add_action('wp_ajax_wpmind_get_analytics', [$this, 'ajax_get_analytics']);

        // 注册资源加载
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * 加载模块类
     */
    private function load_classes(): void
    {
        require_once __DIR__ . '/includes/AnalyticsManager.php';
    }

    /**
     * 注册设置标签页
     */
    public function register_settings_tab(array $tabs): array
    {
        $tab_config = $this->config['settings_tab'] ?? [];

        $tabs['dashboard'] = [
            'label'    => $tab_config['label'] ?? __('分析面板', 'wpmind'),
            'icon'     => $tab_config['icon'] ?? 'dashicons-chart-area',
            'priority' => $tab_config['priority'] ?? 10,
            'template' => __DIR__ . '/templates/dashboard.php',
        ];

        return $tabs;
    }

    /**
     * AJAX: 获取分析数据
     */
    public function ajax_get_analytics(): void
    {
        check_ajax_referer('wpmind_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('权限不足', 'wpmind')]);
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '7d';

        $analytics = AnalyticsManager::instance();
        $data = $analytics->getAnalyticsData($range);

        wp_send_json_success($data);
    }

    /**
     * 加载模块资源
     */
    public function enqueue_assets(string $hook): void
    {
        // 只在 WPMind 设置页面加载
        if (strpos($hook, 'wpmind') === false) {
            return;
        }

        // Chart.js 已在主插件加载，这里只需确保依赖
    }

    /**
     * 激活模块
     */
    public function activate(): void
    {
        // 模块激活时的初始化操作
    }

    /**
     * 停用模块
     */
    public function deactivate(): void
    {
        // 模块停用时的清理操作
    }

    /**
     * 卸载模块
     */
    public function uninstall(): void
    {
        // 模块卸载时删除数据（可选）
    }
}
