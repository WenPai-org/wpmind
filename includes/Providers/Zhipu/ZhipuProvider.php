<?php
/**
 * 智谱 AI Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Zhipu;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * 智谱 AI Provider
 *
 * @since 1.3.0
 */
class ZhipuProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://open.bigmodel.cn/api/paas/v4';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'zhipu';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return '智谱 AI';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://open.bigmodel.cn/usercenter/apikeys';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return ZhipuTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new ZhipuModelMetadataDirectory();
	}
}
