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
class DoubaoModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return DoubaoProvider::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function getStaticModels(): array
    {
        return [
            'doubao-pro-4k'   => '豆包 Pro 4K',
            'doubao-pro-32k'  => '豆包 Pro 32K',
            'doubao-pro-128k' => '豆包 Pro 128K',
            'doubao-lite-4k'  => '豆包 Lite 4K',
            'doubao-lite-32k' => '豆包 Lite 32K',
        ];
    }
}
