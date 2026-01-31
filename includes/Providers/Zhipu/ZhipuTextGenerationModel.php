<?php
/**
 * 智谱 AI 文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Zhipu;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * 智谱 AI 文本生成模型
 *
 * @since 1.3.0
 */
class ZhipuTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return ZhipuProvider::class;
    }
}
