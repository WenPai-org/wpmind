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

			$custom_url              = $settings['custom_base_url'] ?? '';
			$this->providers[ $key ] = new $class( $settings['api_key'], $custom_url );
		}
	}

	/**
	 * 图像 Provider 显示名映射。
	 *
	 * @var array<string, string>
	 */
	public const DISPLAY_NAMES = [
		'openai_gpt_image'    => 'GPT Image',
		'google_gemini_image' => 'Gemini Image',
		'tencent_hunyuan'     => '混元图像',
		'bytedance_reve'      => 'Reve Image',
		'flux'                => 'Flux',
		'bytedance_seedream'  => 'Seedream',
		'midjourney'          => 'Midjourney',
		'qwen_image'          => '千问图像',
	];

	/**
	 * 获取图像 Provider 显示名。
	 *
	 * @param string $provider_id Provider ID.
	 * @return string
	 */
	public static function get_display_name( string $provider_id ): string {
		return self::DISPLAY_NAMES[ $provider_id ] ?? $provider_id;
	}

	/**
	 * 生成图像
	 *
	 * @param string $prompt 提示词.
	 * @param array  $options 选项.
	 * @return array|\WP_Error
	 */
	public function generate( string $prompt, array $options = [] ): array|\WP_Error {
		$requested_provider = isset( $options['provider'] ) && is_string( $options['provider'] )
			? trim( $options['provider'] )
			: '';
		$provider_id        = ( '' !== $requested_provider && 'auto' !== $requested_provider )
			? $requested_provider
			: $this->getDefaultProvider();

		if ( ! isset( $this->providers[ $provider_id ] ) ) {
			// 尝试使用任何可用的 Provider.
			$provider_id = (string) ( array_key_first( $this->providers ) ?? '' );
		}

		if ( '' === $provider_id || ! isset( $this->providers[ $provider_id ] ) ) {
			return new \WP_Error(
				'wpmind_image_no_provider',
				__( '没有可用的图像生成服务', 'wpmind' )
			);
		}

		$provider   = $this->providers[ $provider_id ];
		$start_time = microtime( true );

		try {
			$result     = $provider->generate( $prompt, $options );
			$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

			if ( empty( $result['success'] ) ) {
				$message = is_string( $result['error'] ?? null ) && '' !== $result['error']
					? $result['error']
					: __( '图像生成失败', 'wpmind' );
				return new \WP_Error(
					'wpmind_image_generation_failed',
					$message,
					[ 'provider' => $provider_id ]
				);
			}

			$result['provider'] = $provider_id;
			$this->trackUsage( $provider_id, $prompt, $options, $latency_ms );

			return $result;
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'wpmind_image_generation_exception',
				$e->getMessage(),
				[ 'provider' => $provider_id ]
			);
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
	 * 记录用量。
	 *
	 * @param string $provider_id Provider ID.
	 * @param string $prompt      提示词.
	 * @param array  $options     选项.
	 * @param int    $latency_ms  请求延迟（毫秒）.
	 */
	private function trackUsage( string $provider_id, string $prompt, array $options, int $latency_ms = 0 ): void {
		$model   = isset( $options['model'] ) && is_string( $options['model'] ) && '' !== trim( $options['model'] )
			? trim( $options['model'] )
			: $provider_id;
		$context = isset( $options['context'] ) && is_string( $options['context'] ) && '' !== trim( $options['context'] )
			? trim( $options['context'] )
			: 'image_generation';
		$in      = max( 1, mb_strlen( $prompt ) );
		$payer   = \WPMind\Provider\WenpaiCloud::payer_for( $provider_id );

		do_action( 'wpmind_usage_record', $provider_id, $model, $in, 0, $latency_ms, $context, $payer );
		do_action( 'wpmind_image_generated', $provider_id, $prompt, $options );
	}
}
