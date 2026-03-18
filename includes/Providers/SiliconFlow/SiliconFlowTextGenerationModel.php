<?php
/**
 * 硅基流动文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\SiliconFlow;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * 硅基流动文本生成模型
 *
 * @since 1.3.0
 */
class SiliconFlowTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return SiliconFlowProvider::class;
	}
}
