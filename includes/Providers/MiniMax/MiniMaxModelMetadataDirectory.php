<?php
/**
 * MiniMax 模型元数据目录
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\MiniMax;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * MiniMax 模型元数据目录
 *
 * @since 1.4.0
 */
class MiniMaxModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return MiniMaxProvider::class;
	}
}
