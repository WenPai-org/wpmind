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

	/**
	 * {@inheritDoc}
	 */
	protected function getStaticModels(): array {
		return [
			'ernie-4.0-8k'   => 'ERNIE 4.0 8K',
			'ernie-3.5-8k'   => 'ERNIE 3.5 8K',
			'ernie-speed-8k' => 'ERNIE Speed 8K',
			'ernie-lite-8k'  => 'ERNIE Lite 8K',
			'ernie-tiny-8k'  => 'ERNIE Tiny 8K',
		];
	}
}
