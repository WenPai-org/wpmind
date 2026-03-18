<?php
/**
 * Baidu ERNIE 文本生成模型
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Baidu;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Baidu ERNIE 文本生成模型
 *
 * @since 1.4.0
 */
class BaiduTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return BaiduProvider::class;
	}
}
