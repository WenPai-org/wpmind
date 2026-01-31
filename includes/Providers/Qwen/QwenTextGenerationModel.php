<?php
/**
 * 通义千问文本生成模型
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Qwen;

use WPMind\Providers\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * 通义千问文本生成模型
 *
 * @since 1.3.0
 */
class QwenTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return QwenProvider::class;
    }
}
