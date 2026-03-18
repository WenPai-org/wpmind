<?php
/**
 * 通义千问模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Qwen;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * 通义千问模型元数据目录
 *
 * @since 1.3.0
 */
class QwenModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected static function providerClass(): string {
		return QwenProvider::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getStaticModels(): array {
		return [
			'qwen-turbo'      => 'Qwen Turbo',
			'qwen-plus'       => 'Qwen Plus',
			'qwen-max'        => 'Qwen Max',
			'qwen-max-latest' => 'Qwen Max (Latest)',
			'qwen-long'       => 'Qwen Long',
		];
	}
}
