<?php
/**
 * WPMind 公共 API 主类
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

namespace WPMind\API;

use WP_Error;

/**
 * 公共 API 主类
 *
 * 提供统一的 AI 能力调用接口
 *
 * @since 2.5.0
 */
class PublicAPI {

    /**
     * 单例实例
     *
     * @var PublicAPI|null
     */
    private static $instance = null;

    /**
     * 获取单例实例
     *
     * @return PublicAPI
     */
    public static function instance(): PublicAPI {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        // 注册 Hooks
        $this->register_hooks();
    }

    /**
     * 注册 Hooks
     */
    private function register_hooks(): void {
        // 默认的响应过滤（可被其他插件覆盖）
        add_filter('wpmind_chat_response', [$this, 'filter_chat_response'], 10, 3);
    }

    /**
     * 检查 WPMind 是否可用
     *
     * @return bool
     */
    public function is_available(): bool {
        // 使用 WPMind 实例获取端点配置
        if (!class_exists('\\WPMind\\WPMind')) {
            return false;
        }

        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();
        
        if (empty($endpoints)) {
            return false;
        }

        // 检查是否至少有一个启用的端点且有 API Key
        foreach ($endpoints as $endpoint) {
            if (!empty($endpoint['enabled']) && !empty($endpoint['api_key'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取状态信息
     *
     * @return array
     */
    public function get_status(): array {
        $endpoints = get_option('wpmind_endpoints', []);
        $default_provider = get_option('wpmind_default_provider', '');

        // 获取用量统计 - UsageTracker 是静态类
        $today_tokens = 0;
        $month_tokens = 0;

        if (class_exists('\\WPMind\\Usage\\UsageTracker')) {
            $today_stats = \WPMind\Usage\UsageTracker::getTodayStats();
            $month_stats = \WPMind\Usage\UsageTracker::getMonthStats();
            $today_tokens = $today_stats['total_tokens'] ?? 0;
            $month_tokens = $month_stats['total_tokens'] ?? 0;
        }

        return [
            'available' => $this->is_available(),
            'provider'  => $default_provider,
            'model'     => $this->get_current_model($default_provider),
            'usage'     => [
                'today' => $today_tokens,
                'month' => $month_tokens,
                'limit' => 0,
            ],
        ];
    }

    /**
     * AI 对话
     *
     * @param array|string $messages 消息或简单 Prompt
     * @param array        $options  选项
     * @return array|WP_Error
     */
    public function chat($messages, array $options = []) {
        // 默认选项
        $defaults = [
            'context'     => '',
            'system'      => '',
            'max_tokens'  => 1000,
            'temperature' => 0.7,
            'model'       => 'auto',
            'provider'    => 'auto',
            'json_mode'   => false,
            'cache_ttl'   => 0,
        ];
        $options = wp_parse_args($options, $defaults);

        // 提取 context
        $context = $options['context'];

        // 标准化消息格式
        $normalized_messages = $this->normalize_messages($messages, $options);

        // 构建请求参数
        $args = [
            'messages'    => $normalized_messages,
            'max_tokens'  => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'json_mode'   => $options['json_mode'],
        ];

        // 应用参数过滤
        $args = apply_filters('wpmind_chat_args', $args, $context, $messages);

        // 选择模型
        $model = $options['model'];
        if ($model === 'auto') {
            $model = $this->get_default_model();
        }
        $model = apply_filters('wpmind_select_model', $model, $context, get_current_user_id());

        // 选择服务商
        $provider = $options['provider'];
        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 检查缓存
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('chat', $args);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // 触发请求前 Action
        do_action('wpmind_before_request', 'chat', $args, $context);

        // 执行请求
        $result = $this->execute_chat_request($args, $provider, $model);

        // 错误处理
        if (is_wp_error($result)) {
            do_action('wpmind_error', $result, 'chat', $args);
            return $result;
        }

        // 应用响应过滤
        $result = apply_filters('wpmind_chat_response', $result, $args, $context);

        // 触发请求后 Action
        $usage = $result['usage'] ?? [];
        do_action('wpmind_after_request', 'chat', $result, $args, $usage);

        // 缓存结果
        if ($options['cache_ttl'] > 0 && !is_wp_error($result)) {
            set_transient($cache_key, $result, $options['cache_ttl']);
        }

        return $result;
    }

    /**
     * 翻译文本
     *
     * @param string $text    要翻译的文本
     * @param string $from    源语言
     * @param string $to      目标语言
     * @param array  $options 选项
     * @return string|WP_Error
     */
    public function translate(string $text, string $from = 'auto', string $to = 'en', array $options = []) {
        // 默认选项
        $defaults = [
            'context'   => 'translation',
            'format'    => 'text',
            'hint'      => '',
            'cache_ttl' => 86400, // 默认缓存 1 天
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 应用参数过滤
        $args = compact('text', 'from', 'to', 'options');
        $args = apply_filters('wpmind_translate_args', $args, $context);

        // 检查缓存
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('translate', $args);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // 构建翻译 Prompt
        $prompt = $this->build_translate_prompt($text, $from, $to, $options);

        do_action('wpmind_before_request', 'translate', $args, $context);

        // 调用 chat
        $result = $this->chat($prompt, [
            'context'     => $context,
            'max_tokens'  => max(500, strlen($text) * 2),
            'temperature' => 0.3, // 翻译用较低温度
            'cache_ttl'   => 0,   // chat 层不缓存，这里统一缓存
        ]);

        if (is_wp_error($result)) {
            do_action('wpmind_error', $result, 'translate', $args);
            return $result;
        }

        $translated = trim($result['content']);

        // Slug 格式处理
        if ($options['format'] === 'slug') {
            $translated = sanitize_title($translated);
        }

        // 应用响应过滤
        $translated = apply_filters('wpmind_translate_response', $translated, $text, $from, $to);

        do_action('wpmind_after_request', 'translate', $translated, $args, $result['usage'] ?? []);

        // 缓存结果
        if ($options['cache_ttl'] > 0) {
            set_transient($cache_key, $translated, $options['cache_ttl']);
        }

        return $translated;
    }

    /**
     * 生成图像
     *
     * @param string $prompt  图像描述
     * @param array  $options 选项
     * @return array|WP_Error
     */
    public function generate_image(string $prompt, array $options = []) {
        // 默认选项
        $defaults = [
            'context'       => 'image_generation',
            'size'          => '1024x1024',
            'quality'       => 'standard',
            'style'         => 'natural',
            'provider'      => 'auto',
            'return_format' => 'url',
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        do_action('wpmind_before_request', 'image', compact('prompt', 'options'), $context);

        // 使用现有的图像路由器
        if (class_exists('\\WPMind\\Routing\\ImageRouter')) {
            $router = \WPMind\Routing\ImageRouter::instance();
            $result = $router->generate($prompt, $options);
        } else {
            return new WP_Error(
                'wpmind_image_not_available',
                __('图像生成服务不可用', 'wpmind')
            );
        }

        if (is_wp_error($result)) {
            do_action('wpmind_error', $result, 'image', compact('prompt', 'options'));
            return $result;
        }

        do_action('wpmind_after_request', 'image', $result, compact('prompt', 'options'), []);

        return $result;
    }

    // ============================================
    // v2.6.0 增强 API
    // ============================================

    /**
     * 流式输出
     *
     * @since 2.6.0
     * @param array|string $messages 消息
     * @param callable     $callback 回调函数，接收每个 chunk
     * @param array        $options  选项
     * @return bool|WP_Error
     */
    public function stream($messages, callable $callback, array $options = []) {
        // 默认选项
        $defaults = [
            'context'     => '',
            'system'      => '',
            'max_tokens'  => 2000,
            'temperature' => 0.7,
            'model'       => 'auto',
            'provider'    => 'auto',
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];
        $normalized_messages = $this->normalize_messages($messages, $options);

        // 选择服务商和模型
        $provider = $options['provider'] === 'auto' 
            ? get_option('wpmind_default_provider', 'deepseek') 
            : $options['provider'];
        $model = $options['model'] === 'auto' 
            ? $this->get_current_model($provider) 
            : $options['model'];

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        if (!isset($endpoints[$provider])) {
            return new WP_Error('wpmind_provider_not_found', 
                sprintf(__('服务商 %s 未配置', 'wpmind'), $provider));
        }

        $endpoint = $endpoints[$provider];
        $api_key = $endpoint['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error('wpmind_api_key_missing', 
                sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $provider));
        }

        // 确定模型
        if ($model === 'auto' || $model === 'default') {
            $model = $endpoint['models'][0] ?? 'gpt-3.5-turbo';
        }

        do_action('wpmind_before_request', 'stream', compact('messages', 'options'), $context);

        // 构建请求
        $base_url = $endpoint['custom_url'] ?? $endpoint['base_url'] ?? '';
        $api_url = trailingslashit($base_url) . 'chat/completions';

        $request_body = [
            'model'       => $model,
            'messages'    => $normalized_messages,
            'max_tokens'  => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'stream'      => true,
        ];

        // 使用 PHP stream context 处理流式响应
        $context_options = [
            'http' => [
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key,
                ],
                'content' => wp_json_encode($request_body),
                'timeout' => 120,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ];

        $stream_context = stream_context_create($context_options);
        $stream = @fopen($api_url, 'r', false, $stream_context);

        if (!$stream) {
            return new WP_Error('wpmind_stream_failed', __('无法建立流式连接', 'wpmind'));
        }

        $full_content = '';

        while (!feof($stream)) {
            $line = fgets($stream);
            if (empty($line)) continue;

            $line = trim($line);
            if (strpos($line, 'data: ') !== 0) continue;

            $data = substr($line, 6);
            if ($data === '[DONE]') break;

            $json = json_decode($data, true);
            if (!$json) continue;

            $delta = $json['choices'][0]['delta']['content'] ?? '';
            if (!empty($delta)) {
                $full_content .= $delta;
                call_user_func($callback, $delta, $json);
            }
        }

        fclose($stream);

        do_action('wpmind_after_request', 'stream', ['content' => $full_content], compact('messages', 'options'), []);

        return true;
    }

    /**
     * 结构化输出（JSON Schema）
     *
     * @since 2.6.0
     * @param array|string $messages   消息
     * @param array        $schema     JSON Schema 定义
     * @param array        $options    选项
     * @return array|WP_Error
     */
    public function structured($messages, array $schema, array $options = []) {
        // 默认选项
        $defaults = [
            'context'     => 'structured',
            'max_tokens'  => 2000,
            'temperature' => 0.3, // 结构化输出用较低温度
            'retries'     => 3,   // 自动重试次数
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 构建带 Schema 说明的 System Prompt
        $schema_json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $schema_prompt = "你必须返回严格符合以下 JSON Schema 的 JSON 对象。不要返回其他内容，只返回 JSON：\n\n```json\n{$schema_json}\n```";

        // 如果是字符串，加上 schema 要求
        if (is_string($messages)) {
            $messages = [
                ['role' => 'system', 'content' => $schema_prompt],
                ['role' => 'user', 'content' => $messages],
            ];
        } else {
            // 在消息开头添加 schema 说明
            array_unshift($messages, ['role' => 'system', 'content' => $schema_prompt]);
        }

        $max_retries = $options['retries'];
        $last_error = null;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $result = $this->chat($messages, [
                'context'     => $context,
                'max_tokens'  => $options['max_tokens'],
                'temperature' => $options['temperature'],
                'json_mode'   => true,
                'cache_ttl'   => 0,
            ]);

            if (is_wp_error($result)) {
                return $result;
            }

            $content = $result['content'];

            // 尝试解析 JSON
            $parsed = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // 验证 Schema（简化验证）
                if ($this->validate_schema($parsed, $schema)) {
                    return [
                        'data'     => $parsed,
                        'provider' => $result['provider'],
                        'model'    => $result['model'],
                        'usage'    => $result['usage'],
                        'attempts' => $attempt,
                    ];
                }
            }

            // 记录错误，准备重试
            $last_error = json_last_error_msg();

            // 添加修正提示
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $messages[] = [
                'role' => 'user', 
                'content' => "JSON 解析失败或不符合 Schema: {$last_error}。请重新生成严格符合 Schema 的 JSON。",
            ];
        }

        return new WP_Error(
            'wpmind_structured_failed',
            sprintf(__('结构化输出失败（尝试 %d 次）: %s', 'wpmind'), $max_retries, $last_error)
        );
    }

    /**
     * 批量处理
     *
     * @since 2.6.0
     * @param array  $items          要处理的项目数组
     * @param string $prompt_template Prompt 模板，使用 {{item}} 作为占位符
     * @param array  $options         选项
     * @return array|WP_Error
     */
    public function batch(array $items, string $prompt_template, array $options = []) {
        // 默认选项
        $defaults = [
            'context'        => 'batch',
            'max_tokens'     => 500,
            'temperature'    => 0.7,
            'concurrency'    => 1,      // 并发数（PHP 无真正并发，保留接口）
            'delay_ms'       => 100,    // 请求间延迟（毫秒）
            'stop_on_error'  => false,  // 遇错是否停止
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];
        $results = [];
        $errors = [];

        do_action('wpmind_before_request', 'batch', compact('items', 'prompt_template', 'options'), $context);

        foreach ($items as $index => $item) {
            // 替换占位符
            $item_str = is_array($item) ? wp_json_encode($item, JSON_UNESCAPED_UNICODE) : (string)$item;
            $prompt = str_replace('{{item}}', $item_str, $prompt_template);
            $prompt = str_replace('{{index}}', (string)$index, $prompt);

            // 执行请求
            $result = $this->chat($prompt, [
                'context'     => $context . '_item_' . $index,
                'max_tokens'  => $options['max_tokens'],
                'temperature' => $options['temperature'],
                'cache_ttl'   => 0,
            ]);

            if (is_wp_error($result)) {
                $errors[$index] = $result->get_error_message();
                if ($options['stop_on_error']) {
                    break;
                }
                $results[$index] = null;
            } else {
                $results[$index] = [
                    'content' => $result['content'],
                    'usage'   => $result['usage'],
                ];
            }

            // 延迟
            if ($options['delay_ms'] > 0 && $index < count($items) - 1) {
                usleep($options['delay_ms'] * 1000);
            }
        }

        $total_tokens = array_sum(array_map(function($r) {
            return $r['usage']['total_tokens'] ?? 0;
        }, array_filter($results)));

        do_action('wpmind_after_request', 'batch', $results, compact('items', 'options'), ['total_tokens' => $total_tokens]);

        return [
            'results'      => $results,
            'errors'       => $errors,
            'total_items'  => count($items),
            'success_count'=> count(array_filter($results)),
            'error_count'  => count($errors),
            'total_tokens' => $total_tokens,
        ];
    }

    /**
     * 文本嵌入向量
     *
     * @since 2.6.0
     * @param string|array $texts   要嵌入的文本（单个或数组）
     * @param array        $options 选项
     * @return array|WP_Error
     */
    public function embed($texts, array $options = []) {
        // 默认选项
        $defaults = [
            'context'  => 'embedding',
            'model'    => 'auto',
            'provider' => 'auto',
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 确保是数组
        $input_texts = is_array($texts) ? $texts : [$texts];

        // 选择服务商
        $provider = $options['provider'] === 'auto' 
            ? get_option('wpmind_default_provider', 'openai') 
            : $options['provider'];

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        if (!isset($endpoints[$provider])) {
            return new WP_Error('wpmind_provider_not_found', 
                sprintf(__('服务商 %s 未配置', 'wpmind'), $provider));
        }

        $endpoint = $endpoints[$provider];
        $api_key = $endpoint['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error('wpmind_api_key_missing', 
                sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $provider));
        }

        // 确定嵌入模型
        $embed_model = $options['model'];
        if ($embed_model === 'auto') {
            // 根据服务商选择默认嵌入模型
            $embed_models = [
                'openai'   => 'text-embedding-3-small',
                'deepseek' => 'text-embedding-3-small', // DeepSeek 兼容 OpenAI
                'zhipu'    => 'embedding-2',
                'qwen'     => 'text-embedding-v2',
            ];
            $embed_model = $embed_models[$provider] ?? 'text-embedding-3-small';
        }

        do_action('wpmind_before_request', 'embed', compact('texts', 'options'), $context);

        // 构建请求
        $base_url = $endpoint['custom_url'] ?? $endpoint['base_url'] ?? '';
        $api_url = trailingslashit($base_url) . 'embeddings';

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'model' => $embed_model,
                'input' => $input_texts,
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('wpmind_embed_failed', 
                sprintf(__('嵌入请求失败: %s', 'wpmind'), $response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
            return new WP_Error('wpmind_embed_error', 
                sprintf(__('嵌入 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
        }

        // 提取向量
        $embeddings = [];
        foreach ($data['data'] ?? [] as $item) {
            $embeddings[] = $item['embedding'];
        }

        $usage = [
            'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'total_tokens'  => $data['usage']['total_tokens'] ?? 0,
        ];

        do_action('wpmind_after_request', 'embed', $embeddings, compact('texts', 'options'), $usage);

        return [
            'embeddings' => $embeddings,
            'model'      => $embed_model,
            'provider'   => $provider,
            'usage'      => $usage,
            'dimensions' => !empty($embeddings[0]) ? count($embeddings[0]) : 0,
        ];
    }

    /**
     * 计算 Token 数量（估算）
     *
     * @since 2.6.0
     * @param string|array $content 文本内容或消息数组
     * @return int
     */
    public function count_tokens($content): int {
        // 如果是消息数组，提取文本
        if (is_array($content)) {
            $text = '';
            foreach ($content as $msg) {
                if (isset($msg['content'])) {
                    $text .= $msg['content'] . ' ';
                }
            }
            $content = $text;
        }

        // 简化的 token 估算（中文约 1.5 字符/token，英文约 4 字符/token）
        $chinese_chars = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $content, $matches);
        $other_chars = mb_strlen($content) - $chinese_chars;
        
        $estimated_tokens = (int)($chinese_chars / 1.5 + $other_chars / 4);
        
        return max(1, $estimated_tokens);
    }

    /**
     * 验证 JSON Schema（简化版）
     *
     * @param array $data   数据
     * @param array $schema Schema
     * @return bool
     */
    private function validate_schema(array $data, array $schema): bool {
        // 检查必需字段
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!isset($data[$field])) {
                    return false;
                }
            }
        }

        // 检查属性类型
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                if (!isset($data[$key])) continue;
                
                $value = $data[$key];
                $type = $prop['type'] ?? null;

                if ($type === 'string' && !is_string($value)) return false;
                if ($type === 'integer' && !is_int($value)) return false;
                if ($type === 'number' && !is_numeric($value)) return false;
                if ($type === 'boolean' && !is_bool($value)) return false;
                if ($type === 'array' && !is_array($value)) return false;
                if ($type === 'object' && !is_array($value)) return false;
            }
        }

        return true;
    }

    // ============================================
    // 私有辅助方法
    // ============================================

    /**
     * 标准化消息格式
     *
     * @param array|string $messages 原始消息
     * @param array        $options  选项
     * @return array
     */
    private function normalize_messages($messages, array $options): array {
        // 如果是字符串，转换为消息数组
        if (is_string($messages)) {
            $normalized = [];

            // 添加 system 消息
            if (!empty($options['system'])) {
                $normalized[] = [
                    'role'    => 'system',
                    'content' => $options['system'],
                ];
            }

            // 添加 user 消息
            $normalized[] = [
                'role'    => 'user',
                'content' => $messages,
            ];

            return $normalized;
        }

        // 已经是数组格式
        return $messages;
    }

    /**
     * 执行 Chat 请求
     *
     * @param array  $args     请求参数
     * @param string $provider 服务商
     * @param string $model    模型
     * @return array|WP_Error
     */
    private function execute_chat_request(array $args, string $provider, string $model) {
        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        if (!isset($endpoints[$provider])) {
            return new WP_Error(
                'wpmind_provider_not_found',
                sprintf(__('服务商 %s 未配置', 'wpmind'), $provider)
            );
        }

        $endpoint = $endpoints[$provider];
        $api_key = $endpoint['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error(
                'wpmind_api_key_missing',
                sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $provider)
            );
        }

        // 确定模型
        if ($model === 'auto' || empty($model) || $model === 'default') {
            // models 是数组，取第一个
            $model = $endpoint['models'][0] ?? 'gpt-3.5-turbo';
        }

        // 构建请求体
        $request_body = [
            'model'       => $model,
            'messages'    => $args['messages'],
            'max_tokens'  => $args['max_tokens'],
            'temperature' => $args['temperature'],
        ];

        if (!empty($args['json_mode'])) {
            $request_body['response_format'] = ['type' => 'json_object'];
        }

        // 确定 API URL
        $base_url = $endpoint['custom_url'] ?? $endpoint['base_url'] ?? '';
        $api_url = trailingslashit($base_url) . 'chat/completions';

        // 发送请求
        $start_time = microtime(true);
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($request_body),
            'timeout' => 60,
        ]);

        $latency_ms = (int)((microtime(true) - $start_time) * 1000);

        // 错误处理
        if (is_wp_error($response)) {
            // 记录失败
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->recordResult($provider, false, $latency_ms);
            }

            return new WP_Error(
                'wpmind_request_failed',
                sprintf(__('请求失败: %s', 'wpmind'), $response->get_error_message())
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // HTTP 错误
        if ($status_code !== 200) {
            // 记录失败
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->recordResult($provider, false, $latency_ms);
            }

            $error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
            return new WP_Error(
                'wpmind_api_error',
                sprintf(__('API 错误 (%d): %s', 'wpmind'), $status_code, $error_message),
                ['status' => $status_code]
            );
        }

        // 记录成功
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            \WPMind\Failover\FailoverManager::instance()->recordResult($provider, true, $latency_ms);
        }

        // 解析响应
        return $this->parse_chat_response($data, $provider, $model);
    }

    /**
     * 解析 Chat 响应
     *
     * @param array  $response 原始响应
     * @param string $provider 服务商
     * @param string $model    模型
     * @return array
     */
    private function parse_chat_response(array $response, string $provider, string $model): array {
        $content = '';
        $usage = [
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
        ];

        // 提取内容
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
        } elseif (isset($response['content'][0]['text'])) {
            // Anthropic 格式
            $content = $response['content'][0]['text'];
        }

        // 提取用量
        if (isset($response['usage'])) {
            $usage = [
                'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
            ];
        }

        return [
            'content'  => $content,
            'provider' => $provider,
            'model'    => $model,
            'usage'    => $usage,
        ];
    }

    /**
     * 构建翻译 Prompt
     *
     * @param string $text    文本
     * @param string $from    源语言
     * @param string $to      目标语言
     * @param array  $options 选项
     * @return string
     */
    private function build_translate_prompt(string $text, string $from, string $to, array $options): string {
        $lang_names = [
            'zh' => '中文',
            'en' => '英文',
            'ja' => '日文',
            'ko' => '韩文',
            'fr' => '法文',
            'de' => '德文',
            'es' => '西班牙文',
            'auto' => '自动检测',
        ];

        $from_name = $lang_names[$from] ?? $from;
        $to_name = $lang_names[$to] ?? $to;

        $prompt = "将以下{$from_name}文本翻译成{$to_name}";

        // Slug 格式特殊处理
        if ($options['format'] === 'slug') {
            $prompt .= "，输出结果应该适合作为 URL slug，使用小写英文和连字符";
        }

        // 添加提示
        if (!empty($options['hint'])) {
            $prompt .= "。提示：{$options['hint']}";
        }

        $prompt .= "。只返回翻译结果，不要其他解释：\n\n{$text}";

        return $prompt;
    }

    /**
     * 生成缓存键
     *
     * @param string $type 类型
     * @param array  $args 参数
     * @return string
     */
    private function generate_cache_key(string $type, array $args): string {
        $key = 'wpmind_' . $type . '_' . md5(serialize($args));
        return apply_filters('wpmind_cache_key', $key, $type, $args);
    }

    /**
     * 获取当前模型
     *
     * @param string $provider 服务商
     * @return string
     */
    private function get_current_model(string $provider): string {
        if (!class_exists('\\WPMind\\WPMind')) {
            return 'default';
        }

        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();
        
        // models 是数组，取第一个作为默认
        if (isset($endpoints[$provider]['models']) && is_array($endpoints[$provider]['models'])) {
            return $endpoints[$provider]['models'][0] ?? 'default';
        }

        return 'default';
    }

    /**
     * 获取默认模型
     *
     * @return string
     */
    private function get_default_model(): string {
        $provider = get_option('wpmind_default_provider', 'openai');
        return $this->get_current_model($provider);
    }

    /**
     * 默认响应过滤器
     *
     * @param array  $response 响应
     * @param array  $args     参数
     * @param string $context  上下文
     * @return array
     */
    public function filter_chat_response(array $response, array $args, string $context): array {
        // 默认不做修改，允许其他插件覆盖
        return $response;
    }
}
