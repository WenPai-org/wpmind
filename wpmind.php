<?php
/**
 * Plugin Name: WPMind
 * Plugin URI: https://linuxjoy.com/plugins/wpmind
 * Description: 文派心思 - WordPress AI 自定义端点扩展，支持国内外多种 AI 服务
 * Version: 1.4.0
 * Author: LinuxJoy
 * Author URI: https://linuxjoy.com
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
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
    define( 'WPMIND_VERSION', '1.4.0' );
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
        $this->load_custom_endpoints();
        $this->init_hooks();
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
                'display_name' => '通义千问',
                'icon'         => 'qwen',
                'base_url'     => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'models'       => [ 'qwen-turbo', 'qwen-plus', 'qwen-max' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'zhipu' => [
                'name'         => '智谱 AI',
                'display_name' => '智谱清言',
                'icon'         => 'zhipu',
                'base_url'     => 'https://open.bigmodel.cn/api/paas/v4',
                'models'       => [ 'glm-4', 'glm-4-flash', 'glm-4-plus' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'moonshot' => [
                'name'         => 'Moonshot (Kimi)',
                'display_name' => 'Kimi',
                'icon'         => 'kimi',
                'base_url'     => 'https://api.moonshot.cn/v1',
                'models'       => [ 'moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'doubao' => [
                'name'         => '豆包 (字节)',
                'display_name' => '豆包',
                'icon'         => 'doubao',
                'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3',
                'models'       => [ 'doubao-pro-4k', 'doubao-pro-32k', 'doubao-pro-128k' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'siliconflow' => [
                'name'         => '硅基流动',
                'display_name' => '硅基流动',
                'icon'         => 'siliconcloud',
                'base_url'     => 'https://api.siliconflow.cn/v1',
                'models'       => [ 'deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct' ],
                'enabled'      => false,
                'api_key'      => '',
            ],
            'baidu' => [
                'name'         => '百度文心',
                'display_name' => '文心一言',
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

        // AI 过滤器
        add_filter( 'ai_experiments_preferred_models', [ $this, 'filter_preferred_models' ] );
        add_filter( 'wp_ai_client_default_request_timeout', [ $this, 'filter_request_timeout' ] );
        add_filter( 'mcp_adapter_default_server_config', [ $this, 'filter_mcp_config' ] );

        // 插件链接
        add_filter(
            'plugin_action_links_' . plugin_basename( WPMIND_PLUGIN_FILE ),
            [ $this, 'plugin_action_links' ]
        );

        // 插件行元信息
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
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

        wp_enqueue_style(
            'wpmind-admin',
            WPMIND_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPMIND_VERSION
        );

        wp_enqueue_script(
            'wpmind-admin',
            WPMIND_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
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
            'nonce'  => wp_create_nonce( 'wpmind_ajax' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
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
     * @param array $models 现有模型列表
     * @return array 合并后的模型列表
     */
    public function filter_preferred_models( array $models ): array {
        $custom_models = [];

        foreach ( $this->custom_endpoints as $key => $endpoint ) {
            if ( empty( $endpoint['enabled'] ) || empty( $endpoint['api_key'] ) ) {
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

        // 测试连接
        $test_url = trailingslashit( $base_url ) . 'models';

        $response = wp_remote_get( $test_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [
                'message' => sprintf(
                    __( '连接失败：%s', 'wpmind' ),
                    $response->get_error_message()
                ),
            ] );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 200 || $status_code === 401 ) {
            // 200 = 成功，401 = API Key 错误但端点可访问
            if ( $status_code === 401 ) {
                wp_send_json_error( [ 'message' => __( 'API Key 无效', 'wpmind' ) ] );
            } else {
                wp_send_json_success( [ 'message' => __( '连接成功', 'wpmind' ) ] );
            }
        } else {
            wp_send_json_error( [
                'message' => sprintf(
                    __( '连接失败：HTTP %d', 'wpmind' ),
                    $status_code
                ),
            ] );
        }
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
            esc_url( admin_url( 'options-general.php?page=wpmind' ) ),
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
}
register_activation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\activate' );

/**
 * 插件停用钩子
 *
 * @since 1.1.0
 */
function deactivate(): void {
    // 无需操作
}
register_deactivation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\deactivate' );

// 初始化插件
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpmind' );

/**
 * PSR-4 自动加载器
 *
 * @since 1.3.0
 */
spl_autoload_register( function ( string $class ): void {
    // 只处理 WPMind\Providers 命名空间
    $prefix = 'WPMind\\Providers\\';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // 获取相对类名
    $relative_class = substr( $class, $len );

    // 转换为文件路径
    $file = WPMIND_PLUGIN_DIR . 'includes/Providers/' . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// 加载 Provider 注册模块
require_once WPMIND_PLUGIN_DIR . 'includes/Providers/register.php';
