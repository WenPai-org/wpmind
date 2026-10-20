<?php
/**
 * MiniMax Provider
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\MiniMax;

use WPMind\Provider\EndpointRepository;
use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * MiniMax AI Provider
 *
 * @since 1.4.0
 */
class MiniMaxProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * 实际 Base URL：自定义地址优先，取不到时用默认值（B1，SDK 路径也能用自定义地址）。
	 *
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return EndpointRepository::resolve( static::providerId(), 'https://api.minimax.chat/v1' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'minimax';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'MiniMax';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function credentialsUrl(): ?string {
		return 'https://platform.minimaxi.com/user-center/basic-information/interface-key';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function textGenerationModelClass(): string {
		return MiniMaxTextGenerationModel::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new MiniMaxModelMetadataDirectory();
	}
}
