<?php
/**
 * 智谱 AI 模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Zhipu;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * 智谱 AI 模型元数据目录
 *
 * @since 1.3.0
 */
class ZhipuModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return ZhipuProvider::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getStaticModels(): array {
		return [
			'glm-4'       => 'GLM-4',
			'glm-4-flash' => 'GLM-4 Flash',
			'glm-4-plus'  => 'GLM-4 Plus',
			'glm-4-air'   => 'GLM-4 Air',
			'glm-4-long'  => 'GLM-4 Long',
		];
	}
}
