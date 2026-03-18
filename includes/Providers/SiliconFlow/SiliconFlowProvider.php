<?php
/**
 * 硅基流动 Provider
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\SiliconFlow;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * 硅基流动 AI Provider
 *
 * @since 1.3.0
 */
class SiliconFlowProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://api.siliconflow.cn/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'siliconflow';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return '硅基流动';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://cloud.siliconflow.cn/account/ak';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return SiliconFlowTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new SiliconFlowModelMetadataDirectory();
	}
}
