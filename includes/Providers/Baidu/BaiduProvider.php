<?php
/**
 * Baidu ERNIE Provider
 *
 * @package WPMind
 * @since 1.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Baidu;

use WPMind\Providers\AbstractOpenAiCompatibleProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * Baidu ERNIE AI Provider
 *
 * @since 1.4.0
 */
class BaiduProvider extends AbstractOpenAiCompatibleProvider
{
    /**
     * {@inheritDoc}
     */
    protected static function baseUrl(): string
    {
        return 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop';
    }

    /**
     * {@inheritDoc}
     */
    protected static function providerId(): string
    {
        return 'baidu';
    }

    /**
     * {@inheritDoc}
     */
    protected static function providerName(): string
    {
        return 'Baidu ERNIE';
    }

    /**
     * {@inheritDoc}
     */
    protected static function credentialsUrl(): ?string
    {
        return 'https://console.bce.baidu.com/qianfan/ais/console/applicationConsole/application';
    }

    /**
     * {@inheritDoc}
     */
    protected static function textGenerationModelClass(): string
    {
        return BaiduTextGenerationModel::class;
    }

    /**
     * {@inheritDoc}
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new BaiduModelMetadataDirectory();
    }
}
