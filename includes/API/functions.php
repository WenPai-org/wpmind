<?php
/**
 * WPMind 公共 API 全局函数
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

declare(strict_types=1);

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
 * 获取精确缓存统计
 *
 * @since 4.0.0
 * @return array
 */
if (!function_exists('wpmind_get_cache_stats')) {
    function wpmind_get_cache_stats(): array {
        if (!class_exists('WPMind\API\PublicAPI')) {
            return [
                'enabled'     => false,
                'hits'        => 0,
                'misses'      => 0,
                'writes'      => 0,
                'hit_rate'    => 0,
                'entries'     => 0,
                'max_entries' => 0,
            ];
        }

        return \WPMind\API\PublicAPI::instance()->get_exact_cache_stats();
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
 * 将中文转换为语义化拼音
 *
 * 与普通拼音不同，语义化拼音按词语分隔而非按字分隔。
 * 例如 "你好世界" 会转换为 "nihao-shijie" 而不是 "ni-hao-shi-jie"。
 *
 * @since 2.5.0
 * @param string $text    要转换的中文文本
 * @param array  $options {
 *     可选参数
 *     @type string $context   上下文标识，默认 'pinyin_conversion'
 *     @type int    $cache_ttl 缓存时间（秒），默认 604800（7天）
 * }
 * @return string|WP_Error 成功返回拼音字符串，失败返回 WP_Error
 * 
 * @example
 * $pinyin = wpmind_pinyin('你好世界');
 * // 返回: "nihao-shijie"
 * 
 * @example 
 * $pinyin = wpmind_pinyin('WordPress性能优化指南');
 * // 返回: "WordPress-xingneng-youhua-zhinan"
 */
if (!function_exists('wpmind_pinyin')) {
    function wpmind_pinyin(string $text, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error(
                'wpmind_not_available',
                __('WPMind 插件未激活', 'wpmind')
            );
        }
        
        // 设置 format 为 pinyin
        $options = wp_parse_args($options, [
            'context'   => 'pinyin_conversion',
            'format'    => 'pinyin',
            'cache_ttl' => 604800, // 7 天
        ]);
        
        // 调用 translate 方法，但 format=pinyin 会触发拼音转换逻辑
        return \WPMind\API\PublicAPI::instance()->translate($text, 'zh', 'zh', $options);
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

// ============================================
// v2.6.0 增强 API 全局函数
// ============================================

/**
 * 流式输出
 *
 * @since 2.6.0
 * @param array|string $messages 消息
 * @param callable     $callback 回调函数，每收到一个 chunk 调用一次
 * @param array        $options  选项
 * @return bool|WP_Error
 *
 * @example
 * wpmind_stream('写一个故事', function($chunk, $json) {
 *     echo $chunk;
 *     flush();
 * });
 */
if (!function_exists('wpmind_stream')) {
    function wpmind_stream($messages, callable $callback, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->stream($messages, $callback, $options);
    }
}

/**
 * 结构化输出（JSON Schema）
 *
 * @since 2.6.0
 * @param array|string $messages 消息
 * @param array        $schema   JSON Schema 定义
 * @param array        $options  选项
 * @return array|WP_Error
 *
 * @example
 * $result = wpmind_structured('提取这段文本的关键信息：...', [
 *     'type' => 'object',
 *     'required' => ['title', 'date', 'summary'],
 *     'properties' => [
 *         'title'   => ['type' => 'string'],
 *         'date'    => ['type' => 'string'],
 *         'summary' => ['type' => 'string'],
 *     ],
 * ]);
 * // 返回: ['data' => ['title' => '...', 'date' => '...', 'summary' => '...'], ...]
 */
if (!function_exists('wpmind_structured')) {
    function wpmind_structured($messages, array $schema, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->structured($messages, $schema, $options);
    }
}

/**
 * 批量处理
 *
 * @since 2.6.0
 * @param array  $items          要处理的项目数组
 * @param string $prompt_template Prompt 模板，{{item}} 为占位符
 * @param array  $options         选项
 * @return array|WP_Error
 *
 * @example
 * $titles = ['标题1', '标题2', '标题3'];
 * $result = wpmind_batch($titles, '将这个标题翻译成英文：{{item}}');
 * // 返回: ['results' => [...], 'total_items' => 3, ...]
 */
if (!function_exists('wpmind_batch')) {
    function wpmind_batch(array $items, string $prompt_template, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->batch($items, $prompt_template, $options);
    }
}

/**
 * 文本嵌入向量
 *
 * @since 2.6.0
 * @param string|array $texts   要嵌入的文本
 * @param array        $options 选项
 * @return array|WP_Error
 *
 * @example
 * $result = wpmind_embed('WordPress 是一个开源 CMS');
 * // 返回: ['embeddings' => [[0.123, 0.456, ...]], 'dimensions' => 1536, ...]
 */
if (!function_exists('wpmind_embed')) {
    function wpmind_embed($texts, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->embed($texts, $options);
    }
}

/**
 * 估算 Token 数量
 *
 * @since 2.6.0
 * @param string|array $content 文本或消息数组
 * @return int 估算的 token 数量
 *
 * @example
 * $tokens = wpmind_count_tokens('这是一段中文文本');
 * // 返回: 约 8
 */
if (!function_exists('wpmind_count_tokens')) {
    function wpmind_count_tokens($content): int {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            // 简易估算
            $text = is_array($content) ? json_encode($content) : $content;
            return max(1, (int)(mb_strlen($text) / 3));
        }
        return \WPMind\API\PublicAPI::instance()->count_tokens($content);
    }
}

// ============================================
// v2.7.0 专用 API 全局函数
// ============================================

/**
 * 文本摘要
 *
 * @since 2.7.0
 * @param string $text    要摘要的文本
 * @param array  $options 选项
 * @return string|WP_Error
 *
 * @example
 * $summary = wpmind_summarize('这是一篇很长的文章...', [
 *     'style' => 'bullet',  // paragraph/bullet/title
 *     'max_length' => 100,
 * ]);
 */
if (!function_exists('wpmind_summarize')) {
    function wpmind_summarize(string $text, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->summarize($text, $options);
    }
}

/**
 * 内容审核
 *
 * @since 2.7.0
 * @param string $content 要审核的内容
 * @param array  $options 选项
 * @return array|WP_Error
 *
 * @example
 * $result = wpmind_moderate('用户提交的评论...');
 * if (!$result['safe']) {
 *     // 内容不安全
 * }
 */
if (!function_exists('wpmind_moderate')) {
    function wpmind_moderate(string $content, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->moderate($content, $options);
    }
}

/**
 * 音频转录（语音转文字）
 *
 * @since 2.7.0
 * @param string $audio_file 音频文件路径或 URL
 * @param array  $options    选项
 * @return array|WP_Error
 *
 * @example
 * $result = wpmind_transcribe('/path/to/audio.mp3');
 * echo $result['text'];
 */
if (!function_exists('wpmind_transcribe')) {
    function wpmind_transcribe(string $audio_file, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->transcribe($audio_file, $options);
    }
}

/**
 * 文本转语音
 *
 * @since 2.7.0
 * @param string $text    要转换的文本
 * @param array  $options 选项
 * @return array|WP_Error
 *
 * @example
 * $result = wpmind_speech('欢迎使用 WordPress', [
 *     'voice' => 'nova',
 * ]);
 * echo $result['url']; // 音频 URL
 */
if (!function_exists('wpmind_speech')) {
    function wpmind_speech(string $text, array $options = []) {
        if (!class_exists('WPMind\\API\\PublicAPI')) {
            return new WP_Error('wpmind_not_available', __('WPMind 插件未激活', 'wpmind'));
        }
        return \WPMind\API\PublicAPI::instance()->speech($text, $options);
    }
}
