<?php
/**
 * Moonshot 文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Moonshot;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Moonshot 文本生成模型
 *
 * @since 1.3.0
 */
class MoonshotTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return MoonshotProvider::class;
	}
}
