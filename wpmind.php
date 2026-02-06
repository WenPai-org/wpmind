<?php
/**
 * Plugin Name: WPMind
 * Plugin URI: https://wpcy.com/mind
 * Description: 文派心思 - WordPress AI 自定义端点扩展，支持国内外多种 AI 服务
 * Version: 3.2.0
 * Author: 文派心思
 * Author URI: https://wpcy.com/mind
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmind
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package WPMind
 */

declare( strict_types=1 );

namespace WPMind;

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 插件常量（防止重复定义）
if ( ! defined( 'WPMIND_VERSION' ) ) {
    define( 'WPMIND_VERSION', '3.2.0' );
}
if ( ! defined( 'WPMIND_PLUGIN_FILE' ) ) {
    define( 'WPMIND_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPMIND_PLUGIN_DIR' ) ) {
    define( 'WPMIND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPMIND_PLUGIN_URL' ) ) {
    define( 'WPMIND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
// Alias for module compatibility.
if ( ! defined( 'WPMIND_PATH' ) ) {
    define( 'WPMIND_PATH', WPMIND_PLUGIN_DIR );
}

/**
 * 插件主类
 *
 * @since 1.0.0
 */
final class WPMind {

    /**
     * 单例实例
     *
     * @var WPMind|null
     */
    private static ?WPMind $instance = null;

    /**
     * 自定义端点配置
     *
     * @var array
     */
    private array $custom_endpoints = [];

    /**
     * 获取单例实例
     *
     * @return WPMind
     */
    public static function instance(): WPMind {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->load_textdomain();
        $this->load_core();
        $this->load_custom_endpoints();
        $this->load_public_api();
        $this->load_modules();
        $this->init_hooks();
    }

    /**
     * 加载核心类
     *
     * @since 3.2.0
     */
    private function load_core(): void {
        require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleInterface.php';
        require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleLoader.php';
    }

    /**
     * 加载公共 API
     *
     * @since 2.5.0
     */
    private function load_public_api(): void {
        // 加载公共 API 类（ErrorHandler 必须在 PublicAPI 之前加载）
        require_once WPMIND_PLUGIN_DIR . 'includes/API/ErrorHandler.php';
        require_once WPMIND_PLUGIN_DIR . 'includes/API/PublicAPI.php';
        require_once WPMIND_PLUGIN_DIR . 'includes/API/functions.php';

        // 初始化 API 单例
        \WPMind\API\PublicAPI::instance();

        // 开发环境：加载测试端点
        if (defined('WP_DEBUG') && WP_DEBUG && file_exists(WPMIND_PLUGIN_DIR . 'tests/ajax-test-endpoint.php')) {
            define('WPMIND_DEV_MODE', true);
            require_once WPMIND_PLUGIN_DIR . 'tests/ajax-test-endpoint.php';
        }
    }

    /**
     * 加载模块
     *
     * @since 3.2.0
     */
    private function load_modules(): void {
        $module_loader = \WPMind\Core\ModuleLoader::instance();
        $module_loader->init();

        /**
         * Fires after all modules are loaded.
         *
         * @since 3.2.0
         */
        do_action( 'wpmind_loaded' );
    }

    /**
     * 禁止克隆
     */
    private function __clone() {}

    /**
     * 禁止反序列化
     *
     * @throws \Exception 禁止反序列化
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * 加载翻译文件
     *
     * @since 1.1.0
     */
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'wpmind',
            false,
            dirname( plugin_basename( WPMIND_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * 加载自定义端点配置
     */
    private function load_custom_endpoints(): void {
        $saved = get_option( 'wpmind_custom_endpoints', [] );
        $defaults = $this->get_default_endpoints();

        // 合并默认配置和保存的配置
        $this->custom_endpoints = [];
        foreach ( $defaults as $key => $default ) {
            $this->custom_endpoints[ $key ] = wp_parse_args(
                $saved[ $key ] ?? [],
                $default
            );
            // 确保 models 是数组
            if ( ! is_array( $this->custom_endpoints[ $key ]['models'] ) ) {
                $this->custom_endpoints[ $key ]['models'] = array_filter(
                    array_map( 'trim', explode( ',', (string) $this->custom_endpoints[ $key ]['models'] ) )
                );
            }
        }
    }

    /**
     * 获取默认端点配置
     *
     * @return array
     */
    public function get_default_endpoints(): array {
        return [
            // WordPress 官方服务
            'openai' => [
                'name'         => 'OpenAI',
                'display_name' => 'ChatGPT',
                'icon'         => 'openai',
                'base_url'     => 'https://api.openai.com/v1',
                'models'       => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ],
                'enabled'      => false,
                'api_key'      => '',
                'is_official'  => true,
            ],
            'anthropic' => [
                'name'         => 'Anthropic',
                'display_name' => 'Claude',
                'icon'         => 'claude',
                'base_url'     => 'https://api.anthropic.com/v1',
                'models'       => [ 'claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229' ],
                'enabled'      => false,
                'api_key'      => '',
                'is_official'  => true,
            ],
            'google' => [
                'name'         => 'Google AI',
                'display_name' => 'Gemini',
                'icon'         => 'gemini',
                'base_url'     => 'https://generativelanguage.googleapis.com/v1beta',
                'models'       => [ 'gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash' ],
                'enabled'      => false,
                'api_key'      => '',
                'is_official'  => true,
            ],

            // 国内 AI 服务
            'deepseek' => [
                'name'         => 'DeepSeek',
                'display_name' => 'DeepSeek',
                'icon'         => 'deepseek',
                'base_url'     => 'https://api.deepseek.com/v1',
                'models'       => [ 'deepseek-chat', 'deepseek-coder', 'deepseek-reasoner' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'qwen' => [
                'name'         => '通义千问',
                'display_name' => 'Qwen',
                'icon'         => 'qwen',
                'base_url'     => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'models'       => [ 'qwen-turbo', 'qwen-plus', 'qwen-max' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'zhipu' => [
                'name'         => '智谱 AI',
                'display_name' => 'ChatGLM',
                'icon'         => 'zhipu',
                'base_url'     => 'https://open.bigmodel.cn/api/paas/v4',
                'models'       => [ 'glm-4', 'glm-4-flash', 'glm-4-plus' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'moonshot' => [
                'name'         => 'Moonshot',
                'display_name' => 'Kimi',
                'icon'         => 'kimi',
                'base_url'     => 'https://api.moonshot.cn/v1',
                'models'       => [ 'moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'doubao' => [
                'name'         => '豆包',
                'display_name' => 'Doubao',
                'icon'         => 'doubao',
                'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3',
                'models'       => [ 'doubao-pro-4k', 'doubao-pro-32k', 'doubao-pro-128k' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'siliconflow' => [
                'name'         => '硅基流动',
                'display_name' => 'SiliconFlow',
                'icon'         => 'siliconcloud',
                'base_url'     => 'https://api.siliconflow.cn/v1',
                'models'       => [ 'deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'baidu' => [
                'name'         => '百度文心',
                'display_name' => 'ERNIE',
                'icon'         => 'wenxin',
                'base_url'     => 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop',
                'models'       => [ 'ernie-4.0-8k', 'ernie-3.5-8k', 'ernie-speed-8k' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'minimax' => [
                'name'         => 'MiniMax',
                'display_name' => 'MiniMax',
                'icon'         => 'minimax',
                'base_url'     => 'https://api.minimax.chat/v1',
                'models'       => [ 'abab6.5s-chat', 'abab6.5-chat', 'abab5.5-chat' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
        ];
    }

    /**
     * 初始化钩子
     */
    private function init_hooks(): void {
        // 管理后台
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // AJAX 处理
        add_action( 'wp_ajax_wpmind_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_wpmind_test_image_connection', [ $this, 'ajax_test_image_connection' ] );
        add_action( 'wp_ajax_wpmind_get_provider_status', [ $this, 'ajax_get_provider_status' ] );
        add_action( 'wp_ajax_wpmind_reset_circuit_breaker', [ $this, 'ajax_reset_circuit_breaker' ] );
        add_action( 'wp_ajax_wpmind_get_usage_stats', [ $this, 'ajax_get_usage_stats' ] );
        // wpmind_clear_usage_stats is now handled by CostControlModule.
        add_action( 'wp_ajax_wpmind_save_budget_settings', [ $this, 'ajax_save_budget_settings' ] );
        add_action( 'wp_ajax_wpmind_get_budget_status', [ $this, 'ajax_get_budget_status' ] );
        add_action( 'wp_ajax_wpmind_get_analytics_data', [ $this, 'ajax_get_analytics_data' ] );
        add_action( 'wp_ajax_wpmind_get_routing_status', [ $this, 'ajax_get_routing_status' ] );
        add_action( 'wp_ajax_wpmind_set_routing_strategy', [ $this, 'ajax_set_routing_strategy' ] );
        add_action( 'wp_ajax_wpmind_route_request', [ $this, 'ajax_route_request' ] );
        add_action( 'wp_ajax_wpmind_set_provider_priority', [ $this, 'ajax_set_provider_priority' ] );
        // GEO settings are now handled by GeoModule.

        // AI 过滤器 - 对齐官方 WordPress AI 插件 filter hook
        add_filter( 'ai_experiments_preferred_models_for_text_generation', [ $this, 'filter_preferred_models' ] );
        add_filter( 'wp_ai_client_default_request_timeout', [ $this, 'filter_request_timeout' ] );
        add_filter( 'mcp_adapter_default_server_config', [ $this, 'filter_mcp_config' ] );
        
        // 图像生成能力
        add_filter( 'ai_experiments_image_generation_handler', [ $this, 'handle_image_generation' ], 10, 2 );

        // HTTP API 钩子 - 追踪 AI 请求结果
        add_action( 'http_api_debug', [ $this, 'track_ai_request_result' ], 10, 5 );

        // 智能路由集成
        $this->init_routing_hooks();

        // 插件链接
        add_filter(
            'plugin_action_links_' . plugin_basename( WPMIND_PLUGIN_FILE ),
            [ $this, 'plugin_action_links' ]
        );

        // 插件行元信息
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
    }

    /**
     * 初始化智能路由钩子
     *
     * @since 3.2.0
     */
    private function init_routing_hooks(): void {
        // 初始化路由钩子（类通过 autoloader 自动加载）
        \WPMind\Routing\RoutingHooks::instance();
    }

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

        // Chart.js 图表库（本地化，避免 CDN 被墙）
        wp_enqueue_script(
            'chartjs',
            WPMIND_PLUGIN_URL . 'assets/js/vendor/chartjs/chart.umd.min.js',
            [],
            '4.5.0',
            true
        );

        wp_enqueue_script(
            'wpmind-admin',
            WPMIND_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            WPMIND_VERSION,
            true
        );

        // 完整的国际化字符串
        wp_localize_script( 'wpmind-admin', 'wpmindL10n', [
            'testSuccess'    => __( '连接成功！', 'wpmind' ),
            'testFailed'     => __( '连接失败：', 'wpmind' ),
            'testing'        => __( '测试中...', 'wpmind' ),
            'enabled'        => __( '已启用', 'wpmind' ),
            'apiKeyRequired' => __( '请为已启用的服务填写 API Key', 'wpmind' ),
            'apiKeySet'      => __( '已设置', 'wpmind' ),
            'apiKeyCleared'  => __( 'API Key 将被清除', 'wpmind' ),
        ] );

        // 为 AJAX 添加数据
        wp_localize_script( 'wpmind-admin', 'wpmindData', [
            'nonce'   => wp_create_nonce( 'wpmind_ajax' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'version' => WPMIND_VERSION,
            'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
        ] );
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __( '心思设置', 'wpmind' ),
            __( '心思', 'wpmind' ),
            'manage_options',
            'wpmind',
            [ $this, 'render_settings_page' ],
            'dashicons-heart',
            30
        );
    }

    /**
     * 注册设置
     */
    public function register_settings(): void {
        register_setting(
            'wpmind_settings',
            'wpmind_custom_endpoints',
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_endpoints' ],
                'default'           => [],
            ]
        );

        register_setting(
            'wpmind_settings',
            'wpmind_request_timeout',
            [
                'type'              => 'integer',
                'sanitize_callback' => [ $this, 'sanitize_timeout' ],
                'default'           => 60,
            ]
        );

        register_setting(
            'wpmind_settings',
            'wpmind_default_provider',
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_default_provider' ],
                'default'           => '',
            ]
        );

        // 图像服务设置
        register_setting(
            'wpmind_image_settings',
            'wpmind_image_endpoints',
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_image_endpoints' ],
                'default'           => [],
            ]
        );

        register_setting(
            'wpmind_image_settings',
            'wpmind_default_image_provider',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        // GEO settings are managed by GeoModule via AJAX.
    }

    /**
     * 清理默认提供者（允许列表校验）
     *
     * @param mixed $input 输入值
     * @return string 清理后的值
     * @since 1.2.0
     */
    public function sanitize_default_provider( $input ): string {
        $key = sanitize_key( $input );
        
        // 允许空值
        if ( empty( $key ) ) {
            return '';
        }
        
        // 只允许已定义的端点
        $allowed = array_keys( $this->get_default_endpoints() );
        return in_array( $key, $allowed, true ) ? $key : '';
    }

    /**
     * 清理端点配置
     *
     * @param mixed $input 输入数据
     * @return array 清理后的数据
     * @since 1.1.0
     */
    public function sanitize_endpoints( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $defaults = $this->get_default_endpoints();
        $sanitized = [];

        foreach ( $defaults as $key => $default ) {
            // 强制使用默认的 name, base_url, models（忽略用户提交的值，防止篡改）
            $sanitized[ $key ] = [
                'name'     => $default['name'],
                'base_url' => $default['base_url'],
                'models'   => $default['models'],
                'enabled'  => ! empty( $input[ $key ]['enabled'] ),
                'api_key'  => $this->sanitize_api_key(
                    $input[ $key ] ?? [],
                    $key
                ),
            ];
        }

        return $sanitized;
    }

    /**
     * 清理 API Key（支持清除功能）
     *
     * @param array  $endpoint_input 端点输入数据
     * @param string $endpoint_key   端点标识
     * @return string 处理后的 API Key
     * @since 1.2.0
     */
    private function sanitize_api_key( array $endpoint_input, string $endpoint_key ): string {
        // 如果勾选了清除，返回空字符串
        if ( ! empty( $endpoint_input['clear_api_key'] ) ) {
            return '';
        }

        $api_key = trim( (string) ( $endpoint_input['api_key'] ?? '' ) );

        // 如果为空，保留原有值
        if ( $api_key === '' ) {
            $existing = get_option( 'wpmind_custom_endpoints', [] );
            return $existing[ $endpoint_key ]['api_key'] ?? '';
        }

        return sanitize_text_field( $api_key );
    }

    /**
     * 清理图像端点配置
     *
     * @param mixed $input 输入数据
     * @return array 清理后的图像端点配置
     * @since 2.4.0
     */
    public function sanitize_image_endpoints( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $sanitized = [];
        $available_providers = [
            'openai_gpt_image',
            'google_gemini_image',
            'tencent_hunyuan',
            'bytedance_doubao',
            'flux',
            'qwen_image',
        ];

        foreach ( $available_providers as $key ) {
            if ( ! isset( $input[ $key ] ) ) {
                continue;
            }

            $provider_input = $input[ $key ];
            
            // 处理清除 API Key
            $api_key = $this->sanitize_image_api_key( $provider_input, $key );
            if ( ! empty( $provider_input['clear_api_key'] ) ) {
                $api_key = '';
            }
            
            $sanitized[ $key ] = [
                'enabled'         => ! empty( $provider_input['enabled'] ),
                'api_key'         => $api_key,
                'custom_base_url' => esc_url_raw( $provider_input['custom_base_url'] ?? '' ),
            ];
        }

        return $sanitized;
    }

    /**
     * 清理图像服务 API Key
     *
     * @param array  $provider_input 服务商输入数据
     * @param string $provider_key   服务商标识
     * @return string 处理后的 API Key
     * @since 2.4.0
     */
    private function sanitize_image_api_key( array $provider_input, string $provider_key ): string {
        $api_key = trim( (string) ( $provider_input['api_key'] ?? '' ) );

        // 如果是掩码值（********），保留原有值
        if ( $api_key === '' || $api_key === '********' ) {
            $existing = get_option( 'wpmind_image_endpoints', [] );
            return $existing[ $provider_key ]['api_key'] ?? '';
        }

        return sanitize_text_field( $api_key );
    }

    /**
     * 清理超时设置
     *
     * @param mixed $input 输入值
     * @return int 清理后的超时值
     * @since 1.1.0
     */
    public function sanitize_timeout( $input ): int {
        $timeout = absint( $input );
        return max( 10, min( 300, $timeout ) );
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 显示保存成功消息
        settings_errors( 'wpmind_messages' );

        include WPMIND_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * 过滤首选模型
     *
     * 集成故障转移：排除熔断中的 Provider
     *
     * @param array $models 现有模型列表
     * @return array 合并后的模型列表
     */
    public function filter_preferred_models( array $models ): array {
        $custom_models = [];
        $failover = Failover\FailoverManager::instance();

        foreach ( $this->custom_endpoints as $key => $endpoint ) {
            if ( empty( $endpoint['enabled'] ) || empty( $endpoint['api_key'] ) ) {
                continue;
            }

            // 检查熔断器状态，排除不可用的 Provider（使用只读方法避免状态转换）
            $breaker = $failover->getCircuitBreaker( $key );
            if ( $breaker && ! $breaker->isAvailableReadOnly() ) {
                continue;
            }

            foreach ( (array) $endpoint['models'] as $model ) {
                $model = trim( $model );
                if ( ! empty( $model ) ) {
                    $custom_models[] = [ $key, $model ];
                }
            }
        }

        return array_merge( $custom_models, $models );
    }

    /**
     * 过滤请求超时
     *
     * @param int $timeout 默认超时
     * @return int 配置的超时值
     */
    public function filter_request_timeout( int $timeout ): int {
        $custom_timeout = get_option( 'wpmind_request_timeout' );
        return ! empty( $custom_timeout ) ? (int) $custom_timeout : $timeout;
    }

    /**
     * 过滤 MCP 配置
     *
     * @param array $config MCP 配置
     * @return array 修改后的配置
     */
    public function filter_mcp_config( array $config ): array {
        $config['name'] = 'wpmind-mcp';
        $config['version'] = WPMIND_VERSION;
        return $config;
    }

    /**
     * 处理图像生成请求
     *
     * 连接 AI Experiments Image Generation 能力与 WPMind 图像路由器
     *
     * @param mixed  $result 原始结果
     * @param string $prompt 图像生成提示词
     * @return array 图像生成结果
     * @since 2.4.0
     */
    public function handle_image_generation( $result, string $prompt ): array {
        // 如果已有结果，直接返回
        if ( ! empty( $result ) && is_array( $result ) && ! empty( $result['url'] ) ) {
            return $result;
        }

        // 检查是否有可用的图像服务
        $image_endpoints = get_option( 'wpmind_image_endpoints', [] );
        $has_enabled = false;
        
        foreach ( $image_endpoints as $config ) {
            if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
                $has_enabled = true;
                break;
            }
        }

        if ( ! $has_enabled ) {
            return [
                'success' => false,
                'error'   => __( '没有配置图像生成服务', 'wpmind' ),
            ];
        }

        // 使用图像路由器
        $router = Providers\Image\ImageRouter::instance();
        
        return $router->generate( $prompt, [
            'size' => '1024x1024',
        ] );
    }

    /**
     * 追踪 AI 请求结果
     *
     * 通过 HTTP API 钩子监控发往 AI 服务的请求，记录成功/失败状态
     *
     * @param array|\WP_Error $response HTTP 响应或错误
     * @param string          $context  请求上下文
     * @param string          $class    传输类名
     * @param array           $parsed_args 请求参数
     * @param string          $url      请求 URL
     * @since 1.5.0
     */
    public function track_ai_request_result( $response, string $context, string $class, array $parsed_args, string $url ): void {
        // 只处理响应阶段
        if ( $context !== 'response' ) {
            return;
        }

        // 跳过已标记的请求（避免与手动 recordResult 双重计数）
        if ( ! empty( $parsed_args['_wpmind_skip_tracking'] ) ) {
            return;
        }

        // 识别 AI Provider
        $provider = $this->identify_provider_from_url( $url );
        if ( ! $provider ) {
            return;
        }

        // 计算延迟（从请求开始时间，如果可用）
        $latency_ms = 0;
        if ( isset( $parsed_args['_wpmind_start_time'] ) ) {
            $latency_ms = (int) ( ( microtime( true ) - $parsed_args['_wpmind_start_time'] ) * 1000 );
        }

        // 判断成功/失败
        $success = false;
        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $success = ( $status_code >= 200 && $status_code < 300 );

            // 记录 Token 用量
            if ( $success ) {
                $this->track_token_usage( $response, $provider, $latency_ms );
            }
        }

        // 记录结果
        Failover\FailoverManager::instance()->recordResult( $provider, $success, $latency_ms );
    }

    /**
     * 追踪 Token 用量
     *
     * 使用事件驱动架构，触发 wpmind_usage_record action hook
     * Cost Control 模块会监听此 hook 并处理用量记录
     *
     * @param array  $response HTTP 响应
     * @param string $provider Provider ID
     * @param int    $latency_ms 延迟（毫秒）
     * @since 1.6.0
     * @since 3.3.0 改用事件驱动架构
     */
    private function track_token_usage( array $response, string $provider, int $latency_ms ): void {
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return;
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            return;
        }

        // 提取 usage 信息
        $usage = $data['usage'] ?? null;
        if ( ! $usage ) {
            return;
        }

        // 兼容不同 Provider 的格式
        // OpenAI/国内服务: prompt_tokens / completion_tokens
        // Anthropic: input_tokens / output_tokens
        $input_tokens = (int) ( $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0 );
        $output_tokens = (int) ( $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0 );

        // 验证 tokens 非负
        $input_tokens = max( 0, $input_tokens );
        $output_tokens = max( 0, $output_tokens );

        if ( $input_tokens === 0 && $output_tokens === 0 ) {
            return;
        }

        // 提取模型名称
        $model = $data['model'] ?? 'unknown';

        /**
         * 触发用量记录事件
         *
         * Cost Control 模块会监听此 hook 并处理：
         * - 记录用量统计
         * - 检查预算限制
         * - 发送告警通知
         *
         * @since 3.3.0
         *
         * @param string $provider Provider ID
         * @param string $model 模型名称
         * @param int    $input_tokens 输入 tokens
         * @param int    $output_tokens 输出 tokens
         * @param int    $latency_ms 延迟（毫秒）
         */
        do_action( 'wpmind_usage_record', $provider, $model, $input_tokens, $output_tokens, $latency_ms );

        // 向后兼容：如果没有模块监听，直接调用旧的类
        if ( ! did_action( 'wpmind_usage_recorded' ) ) {
            // 记录用量（兼容层会自动委托给模块或使用回退实现）
            Usage\UsageTracker::record( $provider, $model, $input_tokens, $output_tokens, $latency_ms );

            // 检查预算并发送告警
            Budget\BudgetAlert::instance()->checkAndAlert();
        }
    }

    /**
     * 从 URL 识别 AI Provider
     *
     * 支持默认域名和用户自定义的 base_url
     *
     * @param string $url 请求 URL
     * @return string|null Provider ID 或 null
     */
    private function identify_provider_from_url( string $url ): ?string {
        // 默认域名模式
        $default_patterns = [
            'openai'      => 'api.openai.com',
            'anthropic'   => 'api.anthropic.com',
            'google'      => 'generativelanguage.googleapis.com',
            'deepseek'    => 'api.deepseek.com',
            'qwen'        => 'dashscope.aliyuncs.com',
            'zhipu'       => 'open.bigmodel.cn',
            'moonshot'    => 'api.moonshot.cn',
            'doubao'      => 'ark.cn-beijing.volces.com',
            'siliconflow' => 'api.siliconflow.cn',
            'baidu'       => 'aip.baidubce.com',
            'minimax'     => 'api.minimax.chat',
        ];

        // 首先检查用户自定义的 base_url
        foreach ( $this->custom_endpoints as $provider => $config ) {
            if ( empty( $config['enabled'] ) ) {
                continue;
            }

            // 检查自定义 URL
            if ( ! empty( $config['custom_base_url'] ) && str_contains( $url, wp_parse_url( $config['custom_base_url'], PHP_URL_HOST ) ) ) {
                return $provider;
            }

            // 检查默认 base_url
            if ( ! empty( $config['base_url'] ) && str_contains( $url, wp_parse_url( $config['base_url'], PHP_URL_HOST ) ) ) {
                return $provider;
            }
        }

        // 回退到默认域名模式
        foreach ( $default_patterns as $provider => $pattern ) {
            if ( str_contains( $url, $pattern ) ) {
                if ( isset( $this->custom_endpoints[ $provider ] ) && ! empty( $this->custom_endpoints[ $provider ]['enabled'] ) ) {
                    return $provider;
                }
            }
        }

        return null;
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
        $endpoints = $this->get_custom_endpoints();
        if ( ! isset( $endpoints[ $provider ] ) ) {
            wp_send_json_error( [ 'message' => __( '服务不存在', 'wpmind' ) ] );
        }

        $endpoint = $endpoints[ $provider ];

        // 如果没有提供 API Key，尝试从已保存的配置中获取
        if ( empty( $api_key ) ) {
            $api_key = $this->get_api_key( $provider );
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( '请先配置 API Key', 'wpmind' ) ] );
        }

        // 确定使用的 Base URL
        $base_url = ! empty( $custom_url ) ? $custom_url : $endpoint['base_url'];

        // 测试连接（带重试）
        $test_url = trailingslashit( $base_url ) . 'models';
        $max_retries = 2;
        $last_error = null;
        $last_status_code = 0;
        $start_time = microtime( true );

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
                $last_error = $response;
                // 检查是否应该重试
                if ( $attempt < $max_retries ) {
                    usleep( ErrorHandler::getRetryDelay( $attempt ) * 1000 );
                    continue;
                }

                // 记录失败到健康追踪
                $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
                Failover\FailoverManager::instance()->recordResult( $provider, false, $latency_ms );

                // 使用 ErrorHandler 获取友好的错误消息
                wp_send_json_error( [
                    'message' => ErrorHandler::getWpErrorMessage( $response, $provider ),
                    'details' => $response->get_error_message(),
                    'retried' => $attempt > 1,
                ] );
            }

            $last_status_code = wp_remote_retrieve_response_code( $response );
            $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

            // 成功
            if ( $last_status_code === 200 ) {
                // 记录成功到健康追踪
                Failover\FailoverManager::instance()->recordResult( $provider, true, $latency_ms );

                wp_send_json_success( [
                    'message' => __( '连接成功', 'wpmind' ),
                    'retried' => $attempt > 1,
                    'latency' => $latency_ms,
                ] );
            }

            // 不可重试的错误
            if ( ! ErrorHandler::shouldRetry( $last_status_code ) ) {
                break;
            }

            // 可重试的错误，等待后重试
            if ( $attempt < $max_retries ) {
                usleep( ErrorHandler::getRetryDelay( $attempt ) * 1000 );
            }
        }

        // 记录失败到健康追踪
        $latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
        Failover\FailoverManager::instance()->recordResult( $provider, false, $latency_ms );

        // 获取响应体以提取更详细的错误信息
        $response_body = wp_remote_retrieve_body( $response );

        wp_send_json_error( [
            'message' => ErrorHandler::getErrorMessage( $last_status_code, $provider, $response_body ),
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

        $failover = Failover\FailoverManager::instance();
        $status = $failover->getStatusSummary();

        wp_send_json_success( [
            'providers' => $status,
            'available' => $failover->getAvailableProviders(),
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
        $failover = Failover\FailoverManager::instance();

        if ( empty( $provider ) || $provider === 'all' ) {
            $failover->resetAll();
            wp_send_json_success( [ 'message' => __( '所有熔断器已重置', 'wpmind' ) ] );
        } else {
            $failover->resetProvider( $provider );
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

        $stats = Usage\UsageTracker::getStats();
        $today = Usage\UsageTracker::getTodayStats();
        $month = Usage\UsageTracker::getMonthStats();
        $history = Usage\UsageTracker::getHistory( 20 );

        wp_send_json_success( [
            'stats'   => $stats,
            'today'   => $today,
            'month'   => $month,
            'history' => $history,
        ] );
    }

    /**
     * AJAX 清除用量统计
     *
     * @since 1.6.0
     */
    public function ajax_clear_usage_stats(): void {
        check_ajax_referer( 'wpmind_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
        }

        Usage\UsageTracker::clearAll();
        wp_send_json_success( [ 'message' => __( '用量统计已清除', 'wpmind' ) ] );
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
        $module_loader = Core\ModuleLoader::instance();
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

        $manager = Budget\BudgetManager::instance();
        $result = $manager->saveSettings( $settings );

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
        $module_loader = Core\ModuleLoader::instance();
        if ( ! $module_loader->is_module_enabled( 'cost-control' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cost Control 模块未启用', 'wpmind' ) ] );
        }

        $checker = Budget\BudgetChecker::instance();
        $summary = $checker->getSummary();

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

        $analytics = Analytics\AnalyticsManager::instance();
        $data = $analytics->getAnalyticsData( $range );

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

        $router = Routing\IntelligentRouter::instance();
        $status = $router->getStatusSummary();

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

        $router = Routing\IntelligentRouter::instance();
        $result = $router->setStrategy( $strategy );

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

        $router = Routing\IntelligentRouter::instance();

        if ( $clear ) {
            $result = $router->clearManualPriority();
            $message = __( '已清除手动优先级设置', 'wpmind' );
        } else {
            $result = $router->setManualPriority( $priority );
            $message = __( 'Provider 优先级已更新', 'wpmind' );
        }

        if ( $result ) {
            // 刷新 FailoverManager
            Failover\FailoverManager::instance()->refresh();

            wp_send_json_success( [
                'message'  => $message,
                'priority' => $router->getManualPriority(),
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

        $context = Routing\RoutingContext::create()
            ->withPreferredProvider( $preferred ?: null )
            ->withExcludedProviders( $excluded )
            ->withEstimatedTokens( $input_tokens, $output_tokens );

        $router = Routing\IntelligentRouter::instance();
        $selected = $router->route( $context );
        $failoverChain = $router->getFailoverChain( $context );
        $scores = $router->getProviderScores( $context );

        wp_send_json_success( [
            'selected' => $selected,
            'failover_chain' => $failoverChain,
            'scores' => $scores,
        ] );
    }

    /**
     * 插件操作链接
     *
     * @param array $links 现有链接
     * @return array 修改后的链接
     */
    public function plugin_action_links( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=wpmind' ) ),
            esc_html__( '设置', 'wpmind' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * 插件行元信息
     *
     * @param array  $links 现有链接
     * @param string $file  插件文件
     * @return array 修改后的链接
     * @since 1.1.0
     */
    public function plugin_row_meta( array $links, string $file ): array {
        if ( plugin_basename( WPMIND_PLUGIN_FILE ) !== $file ) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( 'https://linuxjoy.com/plugins/wpmind/docs' ),
            esc_html__( '文档', 'wpmind' )
        );

        return $links;
    }

    /**
     * 获取自定义端点配置
     *
     * @return array 端点配置
     */
    public function get_custom_endpoints(): array {
        return $this->custom_endpoints;
    }

    /**
     * 检查端点是否已配置 API Key
     *
     * @param string $endpoint_key 端点标识
     * @return bool 是否已配置
     * @since 1.2.0
     */
    public function has_api_key( string $endpoint_key ): bool {
        return ! empty( $this->custom_endpoints[ $endpoint_key ]['api_key'] );
    }

    /**
     * 获取指定端点的 API Key
     *
     * @param string $endpoint_key 端点标识
     * @return string API Key
     * @since 1.1.0
     */
    public function get_api_key( string $endpoint_key ): string {
        return $this->custom_endpoints[ $endpoint_key ]['api_key'] ?? '';
    }

    /**
     * 检查端点是否可用
     *
     * @param string $endpoint_key 端点标识
     * @return bool 是否可用
     * @since 1.1.0
     */
    public function is_endpoint_available( string $endpoint_key ): bool {
        if ( ! isset( $this->custom_endpoints[ $endpoint_key ] ) ) {
            return false;
        }

        $endpoint = $this->custom_endpoints[ $endpoint_key ];
        return ! empty( $endpoint['enabled'] ) && ! empty( $endpoint['api_key'] );
    }
}

/**
 * 获取插件实例
 *
 * @return WPMind 插件实例
 */
function wpmind(): WPMind {
    return WPMind::instance();
}

/**
 * 插件激活钩子
 *
 * @since 1.1.0
 */
function activate(): void {
    if ( false === get_option( 'wpmind_request_timeout' ) ) {
        add_option( 'wpmind_request_timeout', 60, '', false ); // autoload = false
    }

    // Schedule rewrite rules flush for after plugin is fully loaded.
    // This ensures module routes are registered before flushing.
    add_option( 'wpmind_flush_rewrite_rules', '1' );
}
register_activation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\activate' );

/**
 * 插件停用钩子
 *
 * @since 1.1.0
 */
function deactivate(): void {
    // Flush rewrite rules to remove plugin routes.
    flush_rewrite_rules();
}
register_deactivation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\deactivate' );

// 初始化插件
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpmind' );

// Flush rewrite rules after plugin activation (delayed to ensure routes are registered).
// Use admin_init for admin requests, or a later init priority for frontend.
add_action( 'admin_init', function(): void {
    if ( get_option( 'wpmind_flush_rewrite_rules' ) === '1' ) {
        delete_option( 'wpmind_flush_rewrite_rules' );
        flush_rewrite_rules();
    }
} );

// Also check on frontend requests with high priority.
add_action( 'wp_loaded', function(): void {
    if ( get_option( 'wpmind_flush_rewrite_rules' ) === '1' ) {
        delete_option( 'wpmind_flush_rewrite_rules' );
        flush_rewrite_rules();
    }
} );

/**
 * PSR-4 自动加载器
 *
 * @since 1.3.0
 */
spl_autoload_register( function ( string $class ): void {
    // WPMind 根命名空间
    $prefix = 'WPMind\\';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // 获取相对类名
    $relative_class = substr( $class, $len );

    // 转换为文件路径
    $file = WPMIND_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// 加载 Provider 注册模块
require_once WPMIND_PLUGIN_DIR . 'includes/Providers/register.php';
