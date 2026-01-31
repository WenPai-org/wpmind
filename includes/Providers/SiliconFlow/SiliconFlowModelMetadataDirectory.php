<?php
/**
 * 硅基流动模型元数据目录
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers\SiliconFlow;

use WPMind\Providers\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * 硅基流动模型元数据目录
 *
 * @since 1.3.0
 */
class SiliconFlowModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     */
    protected static function providerClass(): string
    {
        return SiliconFlowProvider::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function getStaticModels(): array
    {
        return [
            'deepseek-ai/DeepSeek-V3'        => 'DeepSeek V3',
            'deepseek-ai/DeepSeek-R1'        => 'DeepSeek R1',
            'Qwen/Qwen2.5-72B-Instruct'      => 'Qwen 2.5 72B',
            'Qwen/Qwen2.5-32B-Instruct'      => 'Qwen 2.5 32B',
            'Qwen/Qwen2.5-7B-Instruct'       => 'Qwen 2.5 7B',
            'THUDM/glm-4-9b-chat'            => 'GLM-4 9B',
            'meta-llama/Llama-3.3-70B-Instruct' => 'Llama 3.3 70B',
        ];
    }
}
