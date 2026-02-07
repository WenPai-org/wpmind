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

/**
 * Provider 注册器
 */
class ProviderRegistrar
{
    private const PROVIDER_MAP = [
        'deepseek'    => 'WPMind\\Providers\\DeepSeek\\DeepSeekProvider',
        'qwen'        => 'WPMind\\Providers\\Qwen\\QwenProvider',
        'zhipu'       => 'WPMind\\Providers\\Zhipu\\ZhipuProvider',
        'moonshot'    => 'WPMind\\Providers\\Moonshot\\MoonshotProvider',
        'doubao'      => 'WPMind\\Providers\\Doubao\\DoubaoProvider',
        'siliconflow' => 'WPMind\\Providers\\SiliconFlow\\SiliconFlowProvider',
        'baidu'       => 'WPMind\\Providers\\Baidu\\BaiduProvider',
        'minimax'     => 'WPMind\\Providers\\MiniMax\\MiniMaxProvider',
    ];

    public static function registerProviders(ProviderRegistry $registry, array $endpoints): void
    {
        /**
         * 过滤 Provider 映射表，允许第三方注册自定义 Provider
         *
         * @since 3.7.0
         * @param array<string, string> $map Provider ID => FQCN 映射
         */
        $map = apply_filters('wpmind_provider_map', self::PROVIDER_MAP);

        foreach ($map as $key => $providerClass) {
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
        $map = apply_filters('wpmind_provider_map', self::PROVIDER_MAP);
        return array_keys($map);
    }

    public static function getProviderClass(string $providerId): ?string
    {
        $map = apply_filters('wpmind_provider_map', self::PROVIDER_MAP);
        return $map[$providerId] ?? null;
    }
}
