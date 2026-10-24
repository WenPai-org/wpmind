<?php
/**
 * 通义千问 Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Qwen;

use WPMind\Provider\EndpointRepository;
use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * 通义千问 (Qwen) AI Provider
 *
 * @since 1.3.0
 */
class QwenProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * 实际 Base URL：自定义地址优先，取不到时用默认值（B1，SDK 路径也能用自定义地址）。
	 *
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return EndpointRepository::resolve( static::providerId(), 'https://dashscope.aliyuncs.com/compatible-mode/v1' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function providerId(): string {
		return 'qwen';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return '通义千问';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://dashscope.console.aliyun.com/apiKey';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return QwenTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new QwenModelMetadataDirectory();
	}
}
