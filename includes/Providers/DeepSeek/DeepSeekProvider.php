<?php
/**
 * DeepSeek Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\DeepSeek;

use WPMind\Provider\EndpointRepository;
use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * DeepSeek AI Provider
 *
 * @since 1.3.0
 */
class DeepSeekProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * 实际 Base URL：自定义地址优先，取不到时用默认值（B1，SDK 路径也能用自定义地址）。
	 *
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return EndpointRepository::resolve( static::providerId(), 'https://api.deepseek.com/v1' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'deepseek';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'DeepSeek';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://platform.deepseek.com/api_keys';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return DeepSeekTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new DeepSeekModelMetadataDirectory();
	}
}
