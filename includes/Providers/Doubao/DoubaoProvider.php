<?php
/**
 * 豆包 (字节) Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Doubao;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * 豆包 (字节跳动) AI Provider
 *
 * @since 1.3.0
 */
class DoubaoProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://ark.cn-beijing.volces.com/api/v3';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'doubao';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return '豆包 (字节)';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://console.volcengine.com/ark/region:ark+cn-beijing/apiKey';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return DoubaoTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new DoubaoModelMetadataDirectory();
	}
}
