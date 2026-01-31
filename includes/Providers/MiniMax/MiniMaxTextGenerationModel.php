<?php
/**
 * MiniMax 文本生成模型
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\MiniMax;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * MiniMax 文本生成模型
 *
 * @since 1.4.0
 */
class MiniMaxTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return MiniMaxProvider::class;
    }
}
