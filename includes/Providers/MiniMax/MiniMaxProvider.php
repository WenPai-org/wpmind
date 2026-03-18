<?php
/**
 * MiniMax Provider
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\MiniMax;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * MiniMax AI Provider
 *
 * @since 1.4.0
 */
class MiniMaxProvider extends AbstractOpenAiCompatibleProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://api.minimax.chat/v1';
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
