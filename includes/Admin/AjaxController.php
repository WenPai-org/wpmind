<?php
/**
 * Admin AJAX controller.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

use WPMind\WPMind;
use WPMind\Core\ModuleLoader;
use WPMind\ErrorHandler;
use WPMind\Failover\FailoverManager;
use WPMind\Routing\IntelligentRouter;
use WPMind\Routing\RoutingContext;
use WPMind\Usage\UsageTracker;
use WPMind\Budget\BudgetManager;
use WPMind\Budget\BudgetChecker;
use WPMind\Analytics\AnalyticsManager;

/**
 * Class AjaxController
 */
final class AjaxController {

    /**
     * Singleton instance.
     *
     * @var AjaxController|null
     */
    private static ?AjaxController $instance = null;

    /**
     * Get singleton instance.
     *
     * @return AjaxController
     */
    public static function instance(): AjaxController {
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
     * Register AJAX hooks.
     */
    public function register_hooks(): void {
        add_action( 'wp_ajax_wpmind_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_wpmind_test_image_connection', [ $this, 'ajax_test_image_connection' ] );
        add_action( 'wp_ajax_wpmind_get_provider_status', [ $this, 'ajax_get_provider_status' ] );
        add_action( 'wp_ajax_wpmind_reset_circuit_breaker', [ $this, 'ajax_reset_circuit_breaker' ] );
        add_action( 'wp_ajax_wpmind_get_usage_stats', [ $this, 'ajax_get_usage_stats' ] );
        // wpmind_clear_usage_stats is handled by CostControlModule.
        add_action( 'wp_ajax_wpmind_save_budget_settings', [ $this, 'ajax_save_budget_settings' ] );
        add_action( 'wp_ajax_wpmind_get_budget_status', [ $this, 'ajax_get_budget_status' ] );
        add_action( 'wp_ajax_wpmind_get_analytics_data', [ $this, 'ajax_get_analytics_data' ] );
        add_action( 'wp_ajax_wpmind_get_routing_status', [ $this, 'ajax_get_routing_status' ] );
        add_action( 'wp_ajax_wpmind_set_routing_strategy', [ $this, 'ajax_set_routing_strategy' ] );
        add_action( 'wp_ajax_wpmind_route_request', [ $this, 'ajax_route_request' ] );
        add_action( 'wp_ajax_wpmind_set_provider_priority', [ $this, 'ajax_set_provider_priority' ] );
        // GEO settings are handled by GeoModule.
    }

    /**
     * AJAX 测试连接
     *
     * @since 1.4.0
     */
    public function ajax_test_connection(): void {
        // 验证 nonce
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        // 验证权限
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        $custom_url = esc_url_raw( $_POST['custom_url'] ?? '' );

        if ( empty( $provider ) ) {
            wp_send_json_error( [ 'message' => __( '缺少服务标识', 'wpmind' ) ] );
        }

        // 获取端点配置
        $wpmind = WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();
        if ( ! isset( $endpoints[ $provider ] ) ) {
            wp_send_json_error( [ 'message' => __( '服务不存在', 'wpmind' ) ] );
        }

        $endpoint = $endpoints[ $provider ];

        // 如果没有提供 API Key，尝试从已保存的配置中获取
        if ( empty( $api_key ) ) {
            $api_key = $wpmind->get_api_key( $provider );
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( '请先配置 API Key', 'wpmind' ) ] );
        }

        // 确定使用的 Base URL
        $base_url = ! empty( $custom_url ) ? $custom_url : $endpoint['base_url'];

        // 测试连接（带重试）
        $test_url = trailingslashit( $base_url ) . 'models';
        $max_retries = 2;
        $last_status_code = 0;
        $start_time = microtime( true );
        $response = null;

        for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
            $response = wp_remote_get( $test_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 15,
                '_wpmind_skip_tracking' => true, // 标记：跳过 http_api_debug 追踪，避免双重计数
            ] );

            if ( is_wp_error( $response ) ) {
                // 检查是否应该重试
                if ( $attempt < $max_retries ) {
                    usleep( ErrorHandler::get_retry_delay( $attempt ) * 1000 );
                    continue;
                }

                // 记录失败到健康追踪
                $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
                FailoverManager::instance()->record_result( $provider, false, $latency_ms );

                // 使用 ErrorHandler 获取友好的错误消息
                wp_send_json_error( [
                    'message' => ErrorHandler::get_wp_error_message( $response, $provider ),
                    'details' => $response->get_error_message(),
                    'retried' => $attempt > 1,
                ] );
            }

            $last_status_code = wp_remote_retrieve_response_code( $response );
            $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

            // 成功
            if ( $last_status_code === 200 ) {
                // 记录成功到健康追踪
                FailoverManager::instance()->record_result( $provider, true, $latency_ms );

                wp_send_json_success( [
                    'message' => __( '连接成功', 'wpmind' ),
                    'retried' => $attempt > 1,
                    'latency' => $latency_ms,
                ] );
            }

            // 不可重试的错误
            if ( ! ErrorHandler::should_retry( $last_status_code ) ) {
                break;
            }

            // 可重试的错误，等待后重试
            if ( $attempt < $max_retries ) {
                usleep( ErrorHandler::get_retry_delay( $attempt ) * 1000 );
            }
        }

        // 记录失败到健康追踪
        $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
        FailoverManager::instance()->record_result( $provider, false, $latency_ms );

        // 获取响应体以提取更详细的错误信息
        $response_body = $response ? wp_remote_retrieve_body( $response ) : '';

        wp_send_json_error( [
            'message' => ErrorHandler::get_error_message( $last_status_code, $provider, $response_body ),
            'code'    => $last_status_code,
            'retried' => $max_retries > 1,
        ] );
    }

    /**
     * AJAX 测试图像服务连接
     *
     * @since 2.4.0
     */
    public function ajax_test_image_connection(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );

        if ( empty( $provider ) ) {
            wp_send_json_error( [ 'message' => __( '缺少服务标识', 'wpmind' ) ] );
        }

        // 获取图像端点配置
        $image_endpoints = get_option( 'wpmind_image_endpoints', [] );

        if ( ! isset( $image_endpoints[ $provider ] ) ) {
            wp_send_json_error( [ 'message' => __( '服务未配置', 'wpmind' ) ] );
        }

        $config = $image_endpoints[ $provider ];

        if ( empty( $config['api_key'] ) ) {
            wp_send_json_error( [ 'message' => __( '请先配置 API Key', 'wpmind' ) ] );
        }

        // 根据不同的服务商进行测试
        $result = $this->test_image_provider_connection( $provider, $config );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => __( '连接成功', 'wpmind' ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => $result['message'] ?? __( '连接失败', 'wpmind' ),
            ] );
        }
    }

    /**
     * AJAX 获取 Provider 状态
     *
     * @since 1.5.0
     */
    public function ajax_get_provider_status(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $failover = FailoverManager::instance();
        $status = $failover->get_status_summary();

        wp_send_json_success( [
            'providers' => $status,
            'available' => $failover->get_available_providers(),
        ] );
    }

    /**
     * AJAX 重置熔断器
     *
     * @since 1.5.0
     */
    public function ajax_reset_circuit_breaker(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        $failover = FailoverManager::instance();

        if ( empty( $provider ) || $provider === 'all' ) {
            $failover->reset_all();
            wp_send_json_success( [ 'message' => __( '所有熔断器已重置', 'wpmind' ) ] );
        } else {
            $failover->reset_provider( $provider );
            wp_send_json_success( [ 'message' => __( '熔断器已重置', 'wpmind' ) ] );
        }
    }

    /**
     * AJAX 获取用量统计
     *
     * @since 1.6.0
     */
    public function ajax_get_usage_stats(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $stats = UsageTracker::get_stats();
        $today = UsageTracker::get_today_stats();
        $month = UsageTracker::get_month_stats();
        $history = UsageTracker::get_history( 20 );

        wp_send_json_success( [
            'stats'   => $stats,
            'today'   => $today,
            'month'   => $month,
            'history' => $history,
        ] );
    }

    /**
     * AJAX 保存预算设置
     *
     * @since 1.7.0
     */
    public function ajax_save_budget_settings(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        // 检查 Cost Control 模块是否启用
        $module_loader = ModuleLoader::instance();
        if ( ! $module_loader->is_module_enabled( 'cost-control' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cost Control 模块未启用', 'wpmind' ) ] );
        }

        // 解析 JSON 数据
        $json_input = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
        $input = json_decode( $json_input, true );

        if ( ! is_array( $input ) ) {
            wp_send_json_error( [ 'message' => __( '无效的数据格式', 'wpmind' ) ] );
        }

        // 构建设置数组
        $settings = [];
        $settings['enabled'] = ! empty( $input['enabled'] );

        $settings['global'] = [
            'daily_limit_usd'   => (float) ( $input['global']['daily_limit_usd'] ?? 0 ),
            'daily_limit_cny'   => (float) ( $input['global']['daily_limit_cny'] ?? 0 ),
            'monthly_limit_usd' => (float) ( $input['global']['monthly_limit_usd'] ?? 0 ),
            'monthly_limit_cny' => (float) ( $input['global']['monthly_limit_cny'] ?? 0 ),
            'alert_threshold'   => (int) ( $input['global']['alert_threshold'] ?? 80 ),
        ];

        $settings['enforcement_mode'] = sanitize_text_field( $input['enforcement_mode'] ?? 'alert' );

        $settings['notifications'] = [
            'admin_notice'  => ! empty( $input['notifications']['admin_notice'] ),
            'email_alert'   => ! empty( $input['notifications']['email_alert'] ),
            'email_address' => sanitize_email( $input['notifications']['email_address'] ?? '' ),
        ];

        // 按服务商设置
        $settings['providers'] = [];
        if ( ! empty( $input['providers'] ) && is_array( $input['providers'] ) ) {
            foreach ( $input['providers'] as $provider => $limits ) {
                $provider = sanitize_key( $provider );
                $settings['providers'][ $provider ] = [
                    'daily_limit'   => (float) ( $limits['daily_limit'] ?? 0 ),
                    'monthly_limit' => (float) ( $limits['monthly_limit'] ?? 0 ),
                ];
            }
        }

        $manager = BudgetManager::instance();
        $result = $manager->save_settings( $settings );

        if ( $result ) {
            wp_send_json_success( [ 'message' => __( '预算设置已保存', 'wpmind' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( '保存失败', 'wpmind' ) ] );
        }
    }

    /**
     * AJAX 获取预算状态
     *
     * @since 1.7.0
     */
    public function ajax_get_budget_status(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        // 检查 Cost Control 模块是否启用
        $module_loader = ModuleLoader::instance();
        if ( ! $module_loader->is_module_enabled( 'cost-control' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cost Control 模块未启用', 'wpmind' ) ] );
        }

        $checker = BudgetChecker::instance();
        $summary = $checker->get_summary();

        wp_send_json_success( $summary );
    }

    /**
     * AJAX 获取分析数据
     *
     * @since 1.8.0
     */
    public function ajax_get_analytics_data(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $range = isset( $_POST['range'] ) ? sanitize_text_field( $_POST['range'] ) : '7d';

        $analytics = AnalyticsManager::instance();
        $data = $analytics->get_analytics_data( $range );

        wp_send_json_success( $data );
    }

    /**
     * AJAX 获取路由状态
     *
     * @since 1.9.0
     */
    public function ajax_get_routing_status(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $router = IntelligentRouter::instance();
        $status = $router->get_status_summary();

        wp_send_json_success( $status );
    }

    /**
     * AJAX 设置路由策略
     *
     * @since 1.9.0
     */
    public function ajax_set_routing_strategy(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $strategy = sanitize_text_field( $_POST['strategy'] ?? '' );

        if ( empty( $strategy ) ) {
            wp_send_json_error( [ 'message' => __( '请选择路由策略', 'wpmind' ) ] );
        }

        $router = IntelligentRouter::instance();
        $result = $router->set_strategy( $strategy );

        if ( $result ) {
            wp_send_json_success( [
                'message' => __( '路由策略已更新', 'wpmind' ),
                'strategy' => $strategy,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( '无效的路由策略', 'wpmind' ) ] );
        }
    }

    /**
     * AJAX 设置 Provider 优先级
     *
     * @since 2.3.0
     */
    public function ajax_set_provider_priority(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $priority = isset( $_POST['priority'] ) ? array_map( 'sanitize_text_field', (array) $_POST['priority'] ) : [];
        $clear = ! empty( $_POST['clear'] );

        $router = IntelligentRouter::instance();

        if ( $clear ) {
            $result = $router->clear_manual_priority();
            $message = __( '已清除手动优先级设置', 'wpmind' );
        } else {
            $result = $router->set_manual_priority( $priority );
            $message = __( 'Provider 优先级已更新', 'wpmind' );
        }

        if ( $result ) {
            // 刷新 FailoverManager
            FailoverManager::instance()->refresh();

            wp_send_json_success( [
                'message'  => $message,
                'priority' => $router->get_manual_priority(),
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( '保存失败', 'wpmind' ) ] );
        }
    }

    /**
     * AJAX 路由请求（获取推荐 Provider）
     *
     * @since 1.9.0
     */
    public function ajax_route_request(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        $preferred = sanitize_text_field( $_POST['preferred'] ?? '' );
        $excluded = isset( $_POST['excluded'] ) ? array_slice( array_map( 'sanitize_text_field', (array) $_POST['excluded'] ), 0, 50 ) : [];
        $input_tokens = absint( $_POST['input_tokens'] ?? 0 );
        $output_tokens = absint( $_POST['output_tokens'] ?? 0 );

        $context = RoutingContext::create()
            ->with_preferred_provider( $preferred ?: null )
            ->with_excluded_providers( $excluded )
            ->with_estimated_tokens( $input_tokens, $output_tokens );

        $router = IntelligentRouter::instance();
        $selected = $router->route( $context );
        $failoverChain = $router->get_failover_chain( $context );
        $scores = $router->get_provider_scores( $context );

        wp_send_json_success( [
            'selected' => $selected,
            'failover_chain' => $failoverChain,
            'scores' => $scores,
        ] );
    }

    /**
     * 测试图像服务商连接
     *
     * @param string $provider 服务商 ID
     * @param array  $config 配置
     * @return array
     * @since 2.4.0
     */
    private function test_image_provider_connection( string $provider, array $config ): array {
        $api_key = $config['api_key'];
        $custom_url = $config['custom_base_url'] ?? '';

        // 服务商测试端点映射（已通过 Gemini CLI 核实 2026-02-01）
        $test_endpoints = [
            'openai_gpt_image'    => 'https://api.openai.com/v1/models',
            'google_gemini_image' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'tencent_hunyuan'     => 'https://hunyuan.tencentcloudapi.com/',
            'bytedance_doubao'    => 'https://ark.cn-beijing.volces.com/api/v3/models',
            'flux'                => 'https://fal.run/fal-ai/flux/dev',
            'qwen_image'          => 'https://dashscope.aliyuncs.com/api/v1/models',
        ];

        $test_url = ! empty( $custom_url )
            ? rtrim( $custom_url, '/' ) . '/models'
            : ( $test_endpoints[ $provider ] ?? '' );

        if ( empty( $test_url ) ) {
            return [ 'success' => false, 'message' => '未知的服务商' ];
        }

        // 特殊处理：部分服务商使用不同的认证方式
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ];

        // Google Gemini 使用 API Key 作为查询参数
        if ( $provider === 'google_gemini_image' ) {
            $test_url .= '?key=' . $api_key;
            unset( $headers['Authorization'] );
        }

        $response = wp_remote_get( $test_url, [
            'headers' => $headers,
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 200 ) {
            return [ 'success' => true, 'message' => '连接成功' ];
        }

        if ( $status_code === 401 || $status_code === 403 ) {
            return [ 'success' => false, 'message' => 'API Key 无效或无权限' ];
        }

        return [
            'success' => false,
            'message' => '连接失败 (HTTP ' . $status_code . ')',
        ];
    }
}
