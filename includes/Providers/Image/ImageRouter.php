<?php
/**
 * 图像生成路由器
 *
 * 管理多个图像生成 Provider，提供智能路由
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 图像生成路由器
 */
class ImageRouter {

    /**
     * 单例实例
     *
     * @var ImageRouter|null
     */
    private static ?ImageRouter $instance = null;

    /**
     * 已注册的 Provider
     *
     * @var array<string, ImageProviderInterface>
     */
    private array $providers = [];

    /**
     * Provider 配置
     *
     * @var array
     */
    private array $config = [];

    /**
     * 获取单例实例
     *
     * @return ImageRouter
     */
    public static function instance(): ImageRouter {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有构造函数
     */
    private function __construct() {
        $this->config = get_option( 'wpmind_image_endpoints', [] );
        $this->initProviders();
    }

    /**
     * 初始化已启用的 Provider
     */
    private function initProviders(): void {
        $provider_classes = [
            'openai_gpt_image'    => OpenAIImageProvider::class,
            'google_gemini_image' => GeminiImageProvider::class,
            'tencent_hunyuan'     => HunyuanImageProvider::class,
            'bytedance_reve'      => ReveImageProvider::class,
            'flux'                => FluxImageProvider::class,
            'bytedance_seedream'  => SeedreamImageProvider::class,
            'midjourney'          => MidjourneyImageProvider::class,
            'qwen_image'          => QwenImageProvider::class,
        ];

        foreach ( $this->config as $key => $settings ) {
            if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
                continue;
            }

            if ( ! isset( $provider_classes[ $key ] ) ) {
                continue;
            }

            $class = $provider_classes[ $key ];
            
            // 检查类是否存在
            if ( ! class_exists( $class ) ) {
                continue;
            }

            $custom_url = $settings['custom_base_url'] ?? '';
            $this->providers[ $key ] = new $class( $settings['api_key'], $custom_url );
        }
    }

    /**
     * 生成图像
     *
     * @param string $prompt 提示词
     * @param array  $options 选项
     * @return array
     */
    public function generate( string $prompt, array $options = [] ): array {
        $provider_id = $options['provider'] ?? $this->getDefaultProvider();
        
        if ( ! isset( $this->providers[ $provider_id ] ) ) {
            // 尝试使用任何可用的 Provider
            $provider_id = array_key_first( $this->providers );
        }

        if ( ! $provider_id || ! isset( $this->providers[ $provider_id ] ) ) {
            return [
                'success' => false,
                'error'   => '没有可用的图像生成服务',
            ];
        }

        $provider = $this->providers[ $provider_id ];
        
        try {
            $result = $provider->generate( $prompt, $options );
            
            // 记录用量
            if ( $result['success'] ) {
                $this->trackUsage( $provider_id, $prompt, $options );
            }
            
            return $result;
        } catch ( \Exception $e ) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取默认 Provider
     *
     * @return string
     */
    private function getDefaultProvider(): string {
        $default = get_option( 'wpmind_default_image_provider', '' );
        
        if ( ! empty( $default ) && isset( $this->providers[ $default ] ) ) {
            return $default;
        }

        return array_key_first( $this->providers ) ?? '';
    }

    /**
     * 获取所有已启用的 Provider
     *
     * @return array
     */
    public function getEnabledProviders(): array {
        return array_keys( $this->providers );
    }

    /**
     * 检查是否有可用的 Provider
     *
     * @return bool
     */
    public function hasAvailableProvider(): bool {
        return ! empty( $this->providers );
    }

    /**
     * 测试 Provider 连接
     *
     * @param string $provider_id Provider ID
     * @return array
     */
    public function testConnection( string $provider_id ): array {
        if ( ! isset( $this->providers[ $provider_id ] ) ) {
            return [
                'success' => false,
                'message' => 'Provider 未找到或未启用',
            ];
        }

        return $this->providers[ $provider_id ]->testConnection();
    }

    /**
     * 记录用量
     *
     * @param string $provider_id Provider ID
     * @param string $prompt 提示词
     * @param array  $options 选项
     */
    private function trackUsage( string $provider_id, string $prompt, array $options ): void {
        // TODO: 实现图像生成用量追踪
        do_action( 'wpmind_image_generated', $provider_id, $prompt, $options );
    }
}
