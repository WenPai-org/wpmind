<?php
/**
 * OpenAI 兼容模型元数据目录抽象基类
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory as BaseDirectory;

/**
 * OpenAI 兼容模型元数据目录抽象基类
 *
 * 提供静态模型列表，避免调用可能不支持的 /models 端点。
 *
 * @since 1.3.0
 */
abstract class AbstractOpenAiCompatibleModelMetadataDirectory extends BaseDirectory
{
    /**
     * 获取 Provider 类名
     *
     * @return class-string<AbstractOpenAiCompatibleProvider>
     */
    abstract protected static function providerClass(): string;

    /**
     * 获取静态模型列表
     *
     * @return array<string, string> 模型 ID => 显示名称
     */
    abstract protected function getStaticModels(): array;

    /**
     * {@inheritDoc}
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $providerClass = static::providerClass();
        return new Request(
            $method,
            $providerClass::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * 重写以使用静态模型列表，避免调用 /models 端点
     */
    protected function sendListModelsRequest(): array
    {
        $staticModels = $this->getStaticModels();
        $modelMetadataMap = [];

        foreach ($staticModels as $modelId => $displayName) {
            $modelMetadataMap[$modelId] = $this->createModelMetadata($modelId, $displayName);
        }

        return $modelMetadataMap;
    }

    /**
     * 创建模型元数据
     *
     * @param string $modelId 模型 ID
     * @param string $displayName 显示名称
     * @return ModelMetadata
     */
    protected function createModelMetadata(string $modelId, string $displayName): ModelMetadata
    {
        return new ModelMetadata(
            $modelId,
            $displayName,
            $this->getDefaultCapabilities(),
            $this->getDefaultOptions()
        );
    }

    /**
     * 获取默认能力列表
     *
     * @return array<CapabilityEnum>
     */
    protected function getDefaultCapabilities(): array
    {
        return [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];
    }

    /**
     * 获取默认选项列表
     *
     * @return array<SupportedOption>
     */
    protected function getDefaultOptions(): array
    {
        return [
            // candidateCount 不限制值，让模型类处理实际限制
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * 此方法不会被调用，因为我们重写了 sendListModelsRequest
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        // 不会被调用
        return [];
    }
}
