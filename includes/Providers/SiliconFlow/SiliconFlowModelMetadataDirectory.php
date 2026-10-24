<?php
/**
 * 硅基流动模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\SiliconFlow;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * 硅基流动模型元数据目录
 *
 * @since 1.3.0
 */
class SiliconFlowModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return SiliconFlowProvider::class;
	}
}
