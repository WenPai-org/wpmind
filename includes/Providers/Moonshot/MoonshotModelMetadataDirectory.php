<?php
/**
 * Moonshot 模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Moonshot;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Moonshot 模型元数据目录
 *
 * @since 1.3.0
 */
class MoonshotModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return MoonshotProvider::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function getStaticModels(): array
    {
        return [
            'moonshot-v1-8k'   => 'Moonshot v1 8K',
            'moonshot-v1-32k'  => 'Moonshot v1 32K',
            'moonshot-v1-128k' => 'Moonshot v1 128K',
        ];
    }
}
