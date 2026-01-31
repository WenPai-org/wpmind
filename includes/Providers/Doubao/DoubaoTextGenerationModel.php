<?php
/**
 * 豆包文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Doubao;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * 豆包文本生成模型
 *
 * @since 1.3.0
 */
class DoubaoTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return DoubaoProvider::class;
    }
}
