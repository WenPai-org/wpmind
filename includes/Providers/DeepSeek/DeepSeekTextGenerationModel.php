<?php
/**
 * DeepSeek 文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\DeepSeek;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * DeepSeek 文本生成模型
 *
 * @since 1.3.0
 */
class DeepSeekTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return DeepSeekProvider::class;
	}
}
