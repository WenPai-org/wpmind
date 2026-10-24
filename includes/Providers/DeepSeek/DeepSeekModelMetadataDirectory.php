<?php
/**
 * DeepSeek 模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\DeepSeek;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * DeepSeek 模型元数据目录
 *
 * @since 1.3.0
 */
class DeepSeekModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return DeepSeekProvider::class;
	}
}
