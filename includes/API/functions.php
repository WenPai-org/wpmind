<?php
/**
 * WPMind 公共 API 全局函数
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 检查 WPMind 是否可用
 *
 * @since 2.5.0
 * @return bool
 * 
 * @example
 * if (wpmind_is_available()) {
 *     $result = wpmind_chat('Hello');
 * }
 */
if (!function_exists('wpmind_is_available')) {
    function wpmind_is_available(): bool {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return false;
        }
        return \WPMind\API\PublicAPI::instance()->is_available();
    }
}

/**
 * 获取 WPMind 状态
 *
 * @since 2.5.0
 * @return array {
 *     @type bool   $available 是否可用
 *     @type string $provider  当前服务商
 *     @type string $model     当前模型
 *     @type array  $usage     用量统计
 * }
 * 
 * @example
 * $status = wpmind_get_status();
 * echo $status['usage']['today'];
 */
if (!function_exists('wpmind_get_status')) {
    function wpmind_get_status(): array {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return [
                'available' => false,
                'provider'  => '',
                'model'     => '',
                'usage'     => ['today' => 0, 'month' => 0, 'limit' => 0],
            ];
        }
        return \WPMind\API\PublicAPI::instance()->get_status();
    }
}

/**
 * AI 对话
 *
 * @since 2.5.0
 * @param array|string $messages 消息数组或简单 Prompt 字符串
 * @param array        $options {
 *     可选参数
 *     @type string $context     上下文标识，用于 Hook 区分场景
 *     @type string $system      系统提示词（简单模式）
 *     @type int    $max_tokens  最大 token 数，默认 1000
 *     @type float  $temperature 温度 (0-1)，默认 0.7
 *     @type string $model       模型，默认 'auto'
 *     @type string $provider    服务商，默认 'auto'
 *     @type bool   $json_mode   JSON 模式，默认 false
 *     @type int    $cache_ttl   缓存秒数，默认 0（不缓存）
 * }
 * @return array|WP_Error {
 *     成功时返回数组
 *     @type string $content  AI 生成的内容
 *     @type string $provider 使用的服务商
 *     @type string $model    使用的模型
 *     @type array  $usage    Token 用量
 * }
 * 
 * @example 简单模式
 * $result = wpmind_chat('写一首关于春天的诗');
 * echo $result['content'];
 * 
 * @example 多轮对话
 * $result = wpmind_chat([
 *     ['role' => 'system', 'content' => '你是一个 SEO 专家'],
 *     ['role' => 'user', 'content' => '为这篇文章生成标题：...'],
 * ], [
 *     'context' => 'seo_generation',
 * ]);
 */
if (!function_exists('wpmind_chat')) {
    function wpmind_chat($messages, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error(
                'wpmind_not_available',
                __('WPMind 插件未激活', 'wpmind')
            );
        }
        return \WPMind\API\PublicAPI::instance()->chat($messages, $options);
    }
}

/**
 * 翻译文本
 *
 * @since 2.5.0
 * @param string $text    要翻译的文本
 * @param string $from    源语言代码 (auto/zh/en/ja...)，默认 'auto'
 * @param string $to      目标语言代码，默认 'en'
 * @param array  $options {
 *     可选参数
 *     @type string $context   上下文标识，默认 'translation'
 *     @type string $format    输出格式 (text/slug)，默认 'text'
 *     @type string $hint      翻译提示
 *     @type int    $cache_ttl 缓存秒数，默认 86400（1天）
 * }
 * @return string|WP_Error 翻译结果或错误
 * 
 * @example 普通翻译
 * $english = wpmind_translate('你好世界', 'zh', 'en');
 * // 返回: "Hello World"
 * 
 * @example Slug 格式
 * $slug = wpmind_translate('WordPress 性能优化', 'zh', 'en', [
 *     'format' => 'slug',
 * ]);
 * // 返回: "wordpress-performance-optimization"
 */
if (!function_exists('wpmind_translate')) {
    function wpmind_translate(string $text, string $from = 'auto', string $to = 'en', array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error(
                'wpmind_not_available',
                __('WPMind 插件未激活', 'wpmind')
            );
        }
        return \WPMind\API\PublicAPI::instance()->translate($text, $from, $to, $options);
    }
}

/**
 * 生成图像
 *
 * @since 2.5.0
 * @param string $prompt  图像描述
 * @param array  $options {
 *     可选参数
 *     @type string $context       上下文标识
 *     @type string $size          尺寸，默认 '1024x1024'
 *     @type string $quality       质量 (standard/hd)，默认 'standard'
 *     @type string $style         风格 (natural/vivid)，默认 'natural'
 *     @type string $provider      服务商，默认 'auto'
 *     @type string $return_format 返回格式 (url/attachment_id)，默认 'url'
 * }
 * @return array|WP_Error {
 *     成功时返回数组
 *     @type string $url            图像 URL
 *     @type string $provider       使用的服务商
 *     @type string $revised_prompt 修改后的提示词（部分服务商）
 * }
 * 
 * @example
 * $image = wpmind_generate_image('一只可爱的熊猫在竹林中', [
 *     'size' => '1024x1024',
 * ]);
 * echo '<img src="' . $image['url'] . '">';
 */
if (!function_exists('wpmind_generate_image')) {
    function wpmind_generate_image(string $prompt, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error(
                'wpmind_not_available',
                __('WPMind 插件未激活', 'wpmind')
            );
        }
        return \WPMind\API\PublicAPI::instance()->generate_image($prompt, $options);
    }
}

/**
 * 注册默认 Hooks
 *
 * 为不存在 WPMind 时提供 fallback
 */
add_filter('wpmind_is_available', function($available) {
    return wpmind_is_available();
});

add_filter('wpmind_chat', function($default, $messages, $options = []) {
    if (wpmind_is_available()) {
        return wpmind_chat($messages, $options);
    }
    return $default;
}, 10, 3);

add_filter('wpmind_translate', function($default, $text, $from = 'auto', $to = 'en', $options = []) {
    if (wpmind_is_available()) {
        return wpmind_translate($text, $from, $to, $options);
    }
    return $default;
}, 10, 5);
