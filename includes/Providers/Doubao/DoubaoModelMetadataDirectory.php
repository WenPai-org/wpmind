<?php
/**
 * 豆包模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Doubao;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * 豆包模型元数据目录
 *
 * @since 1.3.0
 */
class DoubaoModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return DoubaoProvider::class;
	}
}
