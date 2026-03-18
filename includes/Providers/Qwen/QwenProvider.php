<?php
/**
 * 通义千问 Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Qwen;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * 通义千问 (Qwen) AI Provider
 *
 * @since 1.3.0
 */
class QwenProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://dashscope.aliyuncs.com/compatible-mode/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
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
