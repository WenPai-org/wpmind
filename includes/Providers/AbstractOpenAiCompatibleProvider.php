<?php
/**
 * OpenAI 兼容 Provider 抽象基类
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * OpenAI 兼容 API Provider 抽象基类
 *
 * 为所有使用 OpenAI 兼容 API 的国内 AI 服务提供基础实现。
 *
 * @since 1.3.0
 */
abstract class AbstractOpenAiCompatibleProvider extends AbstractApiProvider {

	/**
	 * 获取 Provider ID
	 *
	 * @return string
	 */
	abstract protected static function providerId(): string;

	/**
	 * 获取 Provider 显示名称
	 *
	 * @return string
	 */
	abstract protected static function providerName(): string;

	/**
	 * 获取 API 凭据获取 URL
	 *
	 * @return string|null
	 */
	abstract protected static function credentialsUrl(): ?string;

	/**
	 * {@inheritDoc}
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				$modelClass = static::textGenerationModelClass();
				return new $modelClass( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			sprintf(
				'Unsupported model capabilities for %s: %s',
				$providerMetadata->getName(),
				implode( ', ', $capabilities )
			)
		);
	}

	/**
	 * 获取文本生成模型类名
	 *
	 * @return class-string<ModelInterface>
	 */
	abstract protected static function textGenerationModelClass(): string;

	/**
	 * 获取 Provider Logo 文件路径
	 *
	 * @return string|null
	 */
	protected static function logoPath(): ?string {
		$path = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . static::providerId() . '.svg';
		return file_exists( $path ) ? $path : null;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$meta        = ProviderRegistrar::getConnectorMeta();
		$description = $meta[ static::providerId() ]['description'] ?? null;

		return new ProviderMetadata(
			static::providerId(),
			static::providerName(),
			ProviderTypeEnum::cloud(),
			static::credentialsUrl(),
			RequestAuthenticationMethod::apiKey(),
			$description,
			static::logoPath()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// 从模型目录获取第一个模型用于可用性检查
		$directory    = static::modelMetadataDirectory();
		$models       = $directory->listModelMetadata();
		$firstModelId = $models[0]->getId();

		// 使用自定义的 AuthenticatedProviderAvailability
		// 它实现了 WithRequestAuthenticationInterface，可以接收 API Key 认证
		return new AuthenticatedProviderAvailability(
			static::model( $firstModelId )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	abstract protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface;
}
