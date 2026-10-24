<?php
/**
 * Baidu ERNIE Provider
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Baidu;

use WPMind\Provider\EndpointRepository;
use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * Baidu ERNIE AI Provider
 *
 * @since 1.4.0
 */
class BaiduProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * 实际 Base URL：自定义地址优先，取不到时用默认值（B1，SDK 路径也能用自定义地址）。
	 *
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return EndpointRepository::resolve( static::providerId(), 'https://qianfan.baidubce.com/v2' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function providerId(): string {
		return 'baidu';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'Baidu ERNIE';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://console.bce.baidu.com/qianfan/ais/console/applicationConsole/application';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return BaiduTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new BaiduModelMetadataDirectory();
	}
}
