<?php
/**
 * Provider 注册器
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\ProviderRegistry;
use WPMind\Providers\DeepSeek\DeepSeekProvider;
use WPMind\Providers\Qwen\QwenProvider;
use WPMind\Providers\Zhipu\ZhipuProvider;
use WPMind\Providers\Moonshot\MoonshotProvider;
use WPMind\Providers\Doubao\DoubaoProvider;
use WPMind\Providers\SiliconFlow\SiliconFlowProvider;
use WPMind\Providers\Baidu\BaiduProvider;
use WPMind\Providers\MiniMax\MiniMaxProvider;

/**
 * Provider 注册器
 */
class ProviderRegistrar
{
    private const PROVIDER_MAP = [
        'deepseek'    => DeepSeekProvider::class,
        'qwen'        => QwenProvider::class,
        'zhipu'       => ZhipuProvider::class,
        'moonshot'    => MoonshotProvider::class,
        'doubao'      => DoubaoProvider::class,
        'siliconflow' => SiliconFlowProvider::class,
        'baidu'       => BaiduProvider::class,
        'minimax'     => MiniMaxProvider::class,
    ];

    public static function registerProviders(ProviderRegistry $registry, array $endpoints): void
    {
        foreach (self::PROVIDER_MAP as $key => $providerClass) {
            if (empty($endpoints[$key]['enabled']) || empty($endpoints[$key]['api_key'])) {
                continue;
            }

            // 注册 Provider
            $registry->registerProvider($providerClass);

            // 设置 API Key 认证
            $registry->setProviderRequestAuthentication(
                $providerClass,
                new ApiKeyRequestAuthentication($endpoints[$key]['api_key'])
            );
        }
    }

    public static function getSupportedProviderIds(): array
    {
        return array_keys(self::PROVIDER_MAP);
    }

    public static function getProviderClass(string $providerId): ?string
    {
        return self::PROVIDER_MAP[$providerId] ?? null;
    }
}
