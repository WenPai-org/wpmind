<?php
/**
 * Provider 注册器
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Provider 注册器
 */
class ProviderRegistrar {

	private const PROVIDER_MAP = [
		'deepseek'    => 'WPMind\\Providers\\DeepSeek\\DeepSeekProvider',
		'qwen'        => 'WPMind\\Providers\\Qwen\\QwenProvider',
		'zhipu'       => 'WPMind\\Providers\\Zhipu\\ZhipuProvider',
		'moonshot'    => 'WPMind\\Providers\\Moonshot\\MoonshotProvider',
		'doubao'      => 'WPMind\\Providers\\Doubao\\DoubaoProvider',
		'siliconflow' => 'WPMind\\Providers\\SiliconFlow\\SiliconFlowProvider',
		'baidu'       => 'WPMind\\Providers\\Baidu\\BaiduProvider',
		'minimax'     => 'WPMind\\Providers\\MiniMax\\MiniMaxProvider',
	];

	public static function registerProviders( ProviderRegistry $registry, array $endpoints ): void {
		/**
		 * 过滤 Provider 映射表，允许第三方注册自定义 Provider
		 *
		 * @since 3.7.0
		 * @param array<string, string> $map Provider ID => FQCN 映射
		 */
		$map = apply_filters( 'wpmind_provider_map', self::PROVIDER_MAP );

		foreach ( $map as $key => $provider_class ) {
			if ( empty( $endpoints[ $key ]['enabled'] ) ) {
				continue;
			}

			// custom_base_url is only honored by the native HTTP path; SDK provider
			// classes use vendor default endpoints. Skip SDK registration when a
			// custom base URL is configured to avoid wrong-endpoint requests.
			if ( ! empty( $endpoints[ $key ]['custom_base_url'] ) ) {
				continue;
			}

			if ( ! class_exists( $provider_class ) ) {
				continue;
			}

			// Register the provider even without a key so WP 7.x Connectors can
			// discover and list it for credential setup.
			$registry->registerProvider( $provider_class );

			// Credential resolution: wpmind option first, then the WP 7.x
			// Connectors option (read-only fallback).
			$api_key = isset( $endpoints[ $key ]['api_key'] ) && is_string( $endpoints[ $key ]['api_key'] )
				? trim( $endpoints[ $key ]['api_key'] )
				: '';
			if ( '' === $api_key ) {
				$api_key = \WPMind\Provider\ConnectorCredentials::key_for( $key );
			}

			// Inject API key auth only when a key exists; otherwise Connectors UI
			// or env vars can still supply credentials later.
			if ( '' !== $api_key ) {
				$registry->setProviderRequestAuthentication(
					$provider_class,
					new ApiKeyRequestAuthentication( $api_key )
				);
			}
		}
	}

	public static function getSupportedProviderIds(): array {
		$map = apply_filters( 'wpmind_provider_map', self::PROVIDER_MAP );
		return array_keys( $map );
	}

	public static function getProviderClass( string $providerId ): ?string {
		$map = apply_filters( 'wpmind_provider_map', self::PROVIDER_MAP );
		return $map[ $providerId ] ?? null;
	}

	/**
	 * 获取 WP 7.0 Connectors 注册所需的元数据
	 *
	 * @since 3.8.0
	 * @return array<string, array{name: string, description: string, credentials_url: string}>
	 */
	public static function getConnectorMeta(): array {
		return apply_filters(
			'wpmind_connector_meta',
			[
				'deepseek'    => [
					'name'            => 'DeepSeek',
					'description'     => __( 'Text generation with DeepSeek models.', 'wpmind' ),
					'credentials_url' => 'https://platform.deepseek.com/api_keys',
				],
				'qwen'        => [
					'name'            => 'Qwen (通义千问)',
					'description'     => __( 'Text generation with Alibaba Qwen models.', 'wpmind' ),
					'credentials_url' => 'https://dashscope.console.aliyun.com/apiKey',
				],
				'zhipu'       => [
					'name'            => 'Zhipu AI (智谱)',
					'description'     => __( 'Text generation with Zhipu GLM models.', 'wpmind' ),
					'credentials_url' => 'https://open.bigmodel.cn/usercenter/apikeys',
				],
				'moonshot'    => [
					'name'            => 'Moonshot (月之暗面)',
					'description'     => __( 'Text generation with Moonshot Kimi models.', 'wpmind' ),
					'credentials_url' => 'https://platform.moonshot.cn/console/api-keys',
				],
				'doubao'      => [
					'name'            => 'Doubao (豆包)',
					'description'     => __( 'Text generation with ByteDance Doubao models.', 'wpmind' ),
					'credentials_url' => 'https://console.volcengine.com/ark/region:ark+cn-beijing/apiKey',
				],
				'siliconflow' => [
					'name'            => 'SiliconFlow (硅基流动)',
					'description'     => __( 'Text generation via SiliconFlow model hub.', 'wpmind' ),
					'credentials_url' => 'https://cloud.siliconflow.cn/account/ak',
				],
				'baidu'       => [
					'name'            => 'Baidu ERNIE (文心一言)',
					'description'     => __( 'Text generation with Baidu ERNIE models.', 'wpmind' ),
					'credentials_url' => 'https://console.bce.baidu.com/qianfan/ais/console/applicationConsole/application',
				],
				'minimax'     => [
					'name'            => 'MiniMax',
					'description'     => __( 'Text generation with MiniMax models.', 'wpmind' ),
					'credentials_url' => 'https://platform.minimaxi.com/user-center/basic-information/interface-key',
				],
			]
		);
	}
}
