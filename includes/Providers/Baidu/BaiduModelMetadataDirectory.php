<?php
/**
 * Baidu ERNIE 模型元数据目录
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Baidu;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Baidu ERNIE 模型元数据目录
 *
 * @since 1.4.0
 */
class BaiduModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return BaiduProvider::class;
	}
}
