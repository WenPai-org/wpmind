<?php
/**
 * Admin page rendering and settings.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

use WPMind\WPMind;

/**
 * Class AdminPage
 */
final class AdminPage {

    /**
     * Singleton instance.
     *
     * @var AdminPage|null
     */
    private static ?AdminPage $instance = null;

    /**
     * Get singleton instance.
     *
     * @return AdminPage
     */
    public static function instance(): AdminPage {
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
            'wpmind_exact_cache_enabled',
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_exact_cache_enabled' ],
                'default'           => '1',
            ]
        );

        register_setting(
            'wpmind_settings',
            'wpmind_exact_cache_default_ttl',
            [
                'type'              => 'integer',
                'sanitize_callback' => [ $this, 'sanitize_exact_cache_ttl' ],
                'default'           => 900,
            ]
        );

        register_setting(
            'wpmind_settings',
            'wpmind_exact_cache_max_entries',
            [
                'type'              => 'integer',
                'sanitize_callback' => [ $this, 'sanitize_exact_cache_max_entries' ],
                'default'           => 500,
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
        $allowed = array_keys( WPMind::instance()->get_default_endpoints() );
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

        $defaults = WPMind::instance()->get_default_endpoints();
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
     * 清理 Exact Cache 开关
     *
     * @param mixed $input 输入值
     * @return string
     */
    public function sanitize_exact_cache_enabled( $input ): string {
        if ( ! empty( $input ) && in_array( (string) $input, [ '1', 'true', 'on', 'yes' ], true ) ) {
            return '1';
        }

        return '0';
    }

    /**
     * 清理 Exact Cache 默认 TTL
     *
     * @param mixed $input 输入值
     * @return int
     */
    public function sanitize_exact_cache_ttl( $input ): int {
        $ttl = (int) $input;
        return max( 0, min( 86400, $ttl ) );
    }

    /**
     * 清理 Exact Cache 最大条目
     *
     * @param mixed $input 输入值
     * @return int
     */
    public function sanitize_exact_cache_max_entries( $input ): int {
        $entries = (int) $input;

        if ( $entries <= 0 ) {
            return 500;
        }

        return min( 50000, max( 100, $entries ) );
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
}
