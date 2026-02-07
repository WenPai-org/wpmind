<?php
/**
 * Admin assets loader.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

/**
 * Class AdminAssets
 */
final class AdminAssets {

    /**
     * Singleton instance.
     *
     * @var AdminAssets|null
     */
    private static ?AdminAssets $instance = null;

    /**
     * Get singleton instance.
     *
     * @return AdminAssets
     */
    public static function instance(): AdminAssets {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * 加载管理后台资源
     *
     * @param string $hook_suffix 当前页面钩子后缀
     * @since 1.1.0
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        // 一级菜单的 hook suffix 是 toplevel_page_{menu_slug}
        if ( 'toplevel_page_wpmind' !== $hook_suffix ) {
            return;
        }

        // Remixicon 图标库
        wp_enqueue_style(
            'remixicon',
            'https://cdn.jsdelivr.net/npm/remixicon@4.9.1/fonts/remixicon.min.css',
            [],
            '4.9.1'
        );

        wp_enqueue_style(
            'wpmind-admin',
            WPMIND_PLUGIN_URL . 'assets/css/admin.css',
            [ 'remixicon' ],
            WPMIND_VERSION
        );

        wp_enqueue_style(
            'wpmind-modules',
            WPMIND_PLUGIN_URL . 'assets/css/modules.css',
            [ 'wpmind-admin' ],
            WPMIND_VERSION
        );

        wp_enqueue_style(
            'wpmind-overview',
            WPMIND_PLUGIN_URL . 'assets/css/overview.css',
            [ 'wpmind-admin' ],
            WPMIND_VERSION
        );

        wp_enqueue_style(
            'wpmind-panels',
            WPMIND_PLUGIN_URL . 'assets/css/panels.css',
            [ 'wpmind-admin' ],
            WPMIND_VERSION
        );

        wp_enqueue_style(
            'wpmind-routing',
            WPMIND_PLUGIN_URL . 'assets/css/pages/routing.css',
            [ 'wpmind-panels' ],
            WPMIND_VERSION
        );

        wp_enqueue_style(
            'wpmind-responsive',
            WPMIND_PLUGIN_URL . 'assets/css/responsive.css',
            [ 'wpmind-panels', 'wpmind-routing' ],
            WPMIND_VERSION
        );

        // Chart.js 图表库（本地优先，CDN 兜底）
        wp_register_script(
            'chartjs',
            WPMIND_PLUGIN_URL . 'assets/js/vendor/chartjs/chart.umd.min.js',
            [],
            '4.5.0',
            true
        );
        $chartjs_cdn = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js';
        wp_add_inline_script(
            'chartjs',
            "if (typeof Chart === 'undefined' && !document.querySelector('script[data-wpmind-fallback=\"chartjs-cdn\"]')) {" .
            "var wpmindChartJsCdn = document.createElement('script');" .
            "wpmindChartJsCdn.src = '{$chartjs_cdn}';" .
            "wpmindChartJsCdn.defer = true;" .
            "wpmindChartJsCdn.setAttribute('data-wpmind-fallback', 'chartjs-cdn');" .
            "document.head.appendChild(wpmindChartJsCdn);" .
            "}",
            'after'
        );

        wp_enqueue_script(
            'wpmind-admin-ui',
            WPMIND_PLUGIN_URL . 'assets/js/admin-ui.js',
            [ 'jquery' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-boot',
            WPMIND_PLUGIN_URL . 'assets/js/admin-boot.js',
            [ 'wpmind-admin-ui' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-endpoints',
            WPMIND_PLUGIN_URL . 'assets/js/admin-endpoints.js',
            [ 'wpmind-admin-boot' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-routing',
            WPMIND_PLUGIN_URL . 'assets/js/admin-routing.js',
            [ 'wpmind-admin-boot', 'jquery-ui-sortable' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-analytics',
            WPMIND_PLUGIN_URL . 'assets/js/admin-analytics.js',
            [ 'wpmind-admin-boot', 'chartjs' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-budget',
            WPMIND_PLUGIN_URL . 'assets/js/admin-budget.js',
            [ 'wpmind-admin-boot' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-geo',
            WPMIND_PLUGIN_URL . 'assets/js/admin-geo.js',
            [ 'wpmind-admin-boot' ],
            WPMIND_VERSION,
            true
        );

        wp_enqueue_script(
            'wpmind-admin-modules',
            WPMIND_PLUGIN_URL . 'assets/js/admin-modules.js',
            [ 'wpmind-admin-boot' ],
            WPMIND_VERSION,
            true
        );

        // 完整的国际化字符串
        wp_localize_script( 'wpmind-admin-boot', 'wpmindL10n', [
            'testSuccess'    => __( '连接成功！', 'wpmind' ),
            'testFailed'     => __( '连接失败：', 'wpmind' ),
            'testing'        => __( '测试中...', 'wpmind' ),
            'enabled'        => __( '已启用', 'wpmind' ),
            'apiKeyRequired' => __( '请为已启用的服务填写 API Key', 'wpmind' ),
            'apiKeySet'      => __( '已设置', 'wpmind' ),
            'apiKeyCleared'  => __( 'API Key 将被清除', 'wpmind' ),
        ] );

        // 为 AJAX 添加数据
        wp_localize_script( 'wpmind-admin-boot', 'wpmindData', [
            'nonce'   => wp_create_nonce( 'wpmind_ajax' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'version' => WPMIND_VERSION,
            'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
        ] );
    }
}
