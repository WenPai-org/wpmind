<?php
/**
 * WPMind 公共 API 主类
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

declare(strict_types=1);

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
     * 调用栈追踪（防止循环调用）
     *
     * @var array
     */
    private static $call_stack = [];

    /**
     * 最大调用深度
     *
     * @var int
     */
    private static $max_call_depth = 3;

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
     * 检查是否存在循环调用
     *
     * @param string $method 方法名
     * @param string $call_id 调用标识（基于参数生成）
     * @return bool|WP_Error true 表示可以继续，WP_Error 表示检测到循环
     */
    private function check_recursive_call(string $method, string $call_id) {
        $key = $method . ':' . $call_id;
        
        // 检查是否同一个调用正在进行
        if (isset(self::$call_stack[$key])) {
            return ErrorHandler::recursive_call($method, $call_id);
        }
        
        // 检查调用深度
        $method_count = 0;
        foreach (self::$call_stack as $stack_key => $value) {
            if (strpos($stack_key, $method . ':') === 0) {
                $method_count++;
            }
        }
        
        if ($method_count >= self::$max_call_depth) {
            return ErrorHandler::call_depth_exceeded($method, $method_count, self::$max_call_depth);
        }
        
        return true;
    }

    /**
     * 开始追踪调用
     *
     * @param string $method 方法名
     * @param string $call_id 调用标识
     */
    private function begin_call(string $method, string $call_id): void {
        $key = $method . ':' . $call_id;
        self::$call_stack[$key] = microtime(true);
    }

    /**
     * 结束追踪调用
     *
     * @param string $method 方法名
     * @param string $call_id 调用标识
     */
    private function end_call(string $method, string $call_id): void {
        $key = $method . ':' . $call_id;
        unset(self::$call_stack[$key]);
    }

    /**
     * 生成调用标识
     *
     * @param mixed $args 参数
     * @return string
     */
    private function generate_call_id($args): string {
        return md5(serialize($args));
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
        // 静态缓存：每个请求只检查一次
        static $cached_result = null;
        
        if ($cached_result !== null) {
            return $cached_result;
        }
        
        // 使用 WPMind 实例获取端点配置
        if (!class_exists('\\WPMind\\WPMind')) {
            $cached_result = false;
            return false;
        }

        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();
        
        if (empty($endpoints)) {
            $cached_result = false;
            return false;
        }

        // 检查是否至少有一个启用的端点且有 API Key
        foreach ($endpoints as $endpoint) {
            if (!empty($endpoint['enabled']) && !empty($endpoint['api_key'])) {
                $cached_result = true;
                return true;
            }
        }

        $cached_result = false;
        return false;
    }

    /**
     * 获取状态信息
     *
     * @return array
     */
    public function get_status(): array {
        $endpoints = get_option('wpmind_custom_endpoints', []);
        $default_provider = get_option('wpmind_default_provider', '');

        // 获取用量统计 - UsageTracker 是静态类
        $today_tokens = 0;
        $month_tokens = 0;

        if (class_exists('\\WPMind\\Usage\\UsageTracker')) {
            $today_stats = \WPMind\Usage\UsageTracker::get_today_stats();
            $month_stats = \WPMind\Usage\UsageTracker::get_month_stats();
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
        // 生成调用标识
        $call_id = $this->generate_call_id(['messages' => $messages, 'options' => $options]);
        
        // 检查循环调用
        $recursive_check = $this->check_recursive_call('chat', $call_id);
        if (is_wp_error($recursive_check)) {
            return $recursive_check;
        }
        
        // 开始追踪调用
        $this->begin_call('chat', $call_id);
        
        try {
            return $this->do_chat($messages, $options);
        } finally {
            $this->end_call('chat', $call_id);
        }
    }

    /**
     * 实际执行 chat 逻辑
     *
     * @param string|array $messages 消息
     * @param array $options 选项
     * @return array|WP_Error
     */
    private function do_chat($messages, array $options = []) {
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
            'tools'       => [],           // v2.7.0: Function Calling
            'tool_choice' => 'auto',       // v2.7.0: auto/none/required/{type: function, function: {name: ...}}
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
            'tools'       => $options['tools'],
            'tool_choice' => $options['tool_choice'],
        ];

        // 应用参数过滤
        $args = apply_filters('wpmind_chat_args', $args, $context, $messages);

        // 保存用户原始 model 意图，failover 时动态选择模型
        $original_model = $options['model'];
        $model_is_auto = ($original_model === 'auto');

        // 非 auto 时保留用户指定的 model，auto 时用默认 provider 的默认模型（用于缓存键等）
        if ($model_is_auto) {
            $model = $this->get_default_model();
        } else {
            $model = $original_model;
        }
        $model = apply_filters('wpmind_select_model', $model, $context, get_current_user_id());

        // 选择服务商（集成故障转移）
        $provider = $options['provider'];
        $original_provider = $provider;

        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 使用 FailoverManager 检查 Provider 健康状态并获取故障转移链
        $failover_chain = [$provider];
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            $failover = \WPMind\Failover\FailoverManager::instance();
            $failover_chain = $failover->get_failover_chain($provider);

            // 如果首选 Provider 不可用，记录日志
            if (!empty($failover_chain) && $failover_chain[0] !== $provider) {
                do_action('wpmind_provider_failover', $provider, $failover_chain[0], $context);
            }
        }

        // 检查缓存（provider/model 已确定，加入缓存键避免不同 Provider 命中同一缓存）
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('chat', $args, $provider, $model);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // 触发请求前 Action
        do_action('wpmind_before_request', 'chat', $args, $context);

        // 使用故障转移链依次尝试
        $result = null;
        $last_error = null;
        $tried_providers = [];

        $failover_count = count($failover_chain);

        foreach ($failover_chain as $index => $try_provider) {
            $tried_providers[] = $try_provider;
            $is_last_provider = ($index === $failover_count - 1);
            $max_retries = $is_last_provider ? 3 : 1;

            // 动态选择当前 provider 的模型
            if ($model_is_auto) {
                // auto 模式：每个 provider 用自己的默认模型
                $try_model = $this->get_current_model($try_provider);
            } else {
                // 显式 model：先尝试用户指定的，若 provider 不支持则回退
                $try_model = $model;
                $wpmind_instance = class_exists('\\WPMind\\WPMind') ? \WPMind\WPMind::instance() : null;
                if ($wpmind_instance) {
                    $endpoints = $wpmind_instance->get_custom_endpoints();
                    $provider_models = $endpoints[$try_provider]['models'] ?? [];
                    if (!empty($provider_models) && !in_array($try_model, $provider_models, true)) {
                        // 目标 provider 不支持该模型，回退到其默认模型
                        $try_model = $this->get_current_model($try_provider);
                    }
                }
            }

            for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
                $result = $this->execute_chat_request($args, $try_provider, $try_model);

                if (!is_wp_error($result)) {
                    // 成功，如果使用了备用 Provider，添加标记
                    if ($try_provider !== $provider) {
                        $result['failover'] = [
                            'original_provider' => $provider,
                            'actual_provider'   => $try_provider,
                            'tried_providers'   => $tried_providers,
                        ];
                    }

                    // 如果模型发生了回退，添加标记
                    if ($try_model !== $model) {
                        $result['model_fallback'] = true;
                        $result['original_model'] = $model;
                    }
                    break 2;
                }

                $last_error = $result;

                // 不可重试的配置错误，直接跳到下一个 Provider
                $error_code = $result->get_error_code();
                if (in_array($error_code, ['wpmind_api_key_missing', 'wpmind_provider_not_found'], true)) {
                    break;
                }

                // 提取 HTTP status code 判断是否可重试
                $error_data = $result->get_error_data();
                $status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;

                if ($status > 0 && !\WPMind\ErrorHandler::should_retry($status)) {
                    break; // 不可重试的 HTTP 错误（如 401/403），跳到下一个 Provider
                }

                // 可重试但还有剩余尝试次数，执行退避
                if ($attempt < $max_retries) {
                    $delay_ms = \WPMind\ErrorHandler::get_retry_delay($attempt + 1);
                    do_action('wpmind_retry', $try_provider, $attempt + 1, $status);
                    sleep((int) ($delay_ms / 1000));
                }
            }
        }

        // 如果所有 Provider 都失败了
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
        // 生成调用标识
        $call_id = $this->generate_call_id(['text' => $text, 'from' => $from, 'to' => $to, 'options' => $options]);
        
        // 检查循环调用
        $recursive_check = $this->check_recursive_call('translate', $call_id);
        if (is_wp_error($recursive_check)) {
            return $recursive_check;
        }
        
        // 开始追踪调用
        $this->begin_call('translate', $call_id);
        
        try {
            return $this->do_translate($text, $from, $to, $options);
        } finally {
            $this->end_call('translate', $call_id);
        }
    }

    /**
     * 实际执行翻译逻辑
     *
     * @param string $text    要翻译的文本
     * @param string $from    源语言
     * @param string $to      目标语言
     * @param array  $options 选项
     * @return string|WP_Error
     */
    private function do_translate(string $text, string $from, string $to, array $options) {
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

        // 解析默认 provider/model 用于缓存键
        $default_provider = get_option('wpmind_default_provider', 'openai');
        $default_model = $this->get_current_model($default_provider);

        // 检查缓存
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('translate', $args, $default_provider, $default_model);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // 构建翻译 Prompt
        $prompt = $this->build_translate_prompt($text, $from, $to, $options);

        do_action('wpmind_before_request', 'translate', $args, $context);

        // 调用 chat（注意：这里会触发 chat 的循环调用检查，但由于是不同的方法，不会被阻止）
        $result = $this->do_chat($prompt, [
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
        // 注意：不能使用 sanitize_title()，因为它会触发 sanitize_title filter
        // 如果其他插件（如 WPSlug）拦截该 filter 并调用 wpmind_translate，会导致无限循环
        if ($options['format'] === 'slug') {
            $translated = sanitize_title_with_dashes($translated, '', 'save');
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
        if (class_exists('\\WPMind\\Providers\\Image\\ImageRouter')) {
            $router = \WPMind\Providers\Image\ImageRouter::instance();
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

        // 保存用户原始 model 意图，failover 时动态选择模型
        $original_model = $options['model'];
        $model_is_auto = ($original_model === 'auto');

        // 选择服务商（集成路由 + 故障转移）
        $provider = $options['provider'];
        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 使用 FailoverManager 获取故障转移链
        $failover_chain = [$provider];
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            $failover = \WPMind\Failover\FailoverManager::instance();
            $failover_chain = $failover->get_failover_chain($provider);

            // 如果首选 Provider 不可用，记录日志
            if (!empty($failover_chain) && $failover_chain[0] !== $provider) {
                do_action('wpmind_provider_failover', $provider, $failover_chain[0], $context);
            }
        }

        do_action('wpmind_before_request', 'stream', compact('messages', 'options'), $context);

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        // 使用故障转移链依次尝试
        $last_error = null;

        foreach ($failover_chain as $index => $try_provider) {
            if (!isset($endpoints[$try_provider])) {
                $last_error = new WP_Error('wpmind_provider_not_found',
                    sprintf(__('服务商 %s 未配置', 'wpmind'), $try_provider));
                continue;
            }

            $endpoint = $endpoints[$try_provider];
            $api_key = $endpoint['api_key'] ?? '';

            if (empty($api_key)) {
                $last_error = new WP_Error('wpmind_api_key_missing',
                    sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $try_provider));
                continue;
            }

            // 动态获取模型：auto 时用当前 provider 的默认模型
            $model = $model_is_auto
                ? $this->get_current_model($try_provider)
                : $original_model;

            if ($model === 'auto' || $model === 'default') {
                $model = $endpoint['models'][0] ?? 'gpt-3.5-turbo';
            }

            // 构建请求
            $base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
            $api_url = trailingslashit($base_url) . 'chat/completions';

            $request_body = [
                'model'       => $model,
                'messages'    => $normalized_messages,
                'max_tokens'  => $options['max_tokens'],
                'temperature' => $options['temperature'],
                'stream'      => true,
            ];

            // 使用 PHP stream context 处理流式响应
            $stream_context_options = [
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

            $start_time = microtime(true);
            $stream_ctx = stream_context_create($stream_context_options);
            $stream = @fopen($api_url, 'r', false, $stream_ctx);

            if (!$stream) {
                $latency_ms = (int)((microtime(true) - $start_time) * 1000);

                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $last_error = new WP_Error('wpmind_stream_failed',
                    sprintf(__('服务商 %s 无法建立流式连接', 'wpmind'), $try_provider));
                continue;
            }

            // 流式读取成功，解析 SSE 数据
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

            $latency_ms = (int)((microtime(true) - $start_time) * 1000);

            // 记录成功
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, true, $latency_ms);
            }

            do_action('wpmind_after_request', 'stream', ['content' => $full_content], compact('messages', 'options'), []);

            return true;
        }

        // 所有 Provider 都失败了
        if ($last_error) {
            do_action('wpmind_error', $last_error, 'stream', compact('messages', 'options'));
            return $last_error;
        }

        return new WP_Error('wpmind_stream_failed', __('无法建立流式连接', 'wpmind'));
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

        // 保存用户原始 model 意图
        $original_model = $options['model'];
        $model_is_auto = ($original_model === 'auto');

        // 选择服务商（集成路由 + 故障转移）
        $provider = $options['provider'];
        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 使用 FailoverManager 获取故障转移链
        $failover_chain = [$provider];
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            $failover = \WPMind\Failover\FailoverManager::instance();
            $failover_chain = $failover->get_failover_chain($provider);

            // 如果首选 Provider 不可用，记录日志
            if (!empty($failover_chain) && $failover_chain[0] !== $provider) {
                do_action('wpmind_provider_failover', $provider, $failover_chain[0], $context);
            }
        }

        do_action('wpmind_before_request', 'embed', compact('texts', 'options'), $context);

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        // 嵌入模型映射表
        $embed_models = [
            'openai'   => 'text-embedding-3-small',
            'deepseek' => 'text-embedding-3-small', // DeepSeek 兼容 OpenAI
            'zhipu'    => 'embedding-2',
            'qwen'     => 'text-embedding-v2',
        ];

        // 使用故障转移链依次尝试
        $last_error = null;

        foreach ($failover_chain as $index => $try_provider) {
            if (!isset($endpoints[$try_provider])) {
                $last_error = new WP_Error('wpmind_provider_not_found',
                    sprintf(__('服务商 %s 未配置', 'wpmind'), $try_provider));
                continue;
            }

            $endpoint = $endpoints[$try_provider];
            $api_key = $endpoint['api_key'] ?? '';

            if (empty($api_key)) {
                $last_error = new WP_Error('wpmind_api_key_missing',
                    sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $try_provider));
                continue;
            }

            // 动态获取嵌入模型
            $embed_model = $model_is_auto
                ? ($embed_models[$try_provider] ?? 'text-embedding-3-small')
                : $original_model;

            // 构建请求
            $base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
            $api_url = trailingslashit($base_url) . 'embeddings';

            $start_time = microtime(true);

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

            $latency_ms = (int)((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $last_error = new WP_Error('wpmind_embed_failed',
                    sprintf(__('嵌入请求失败: %s', 'wpmind'), $response->get_error_message()));
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($status_code !== 200) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
                $last_error = new WP_Error('wpmind_embed_error',
                    sprintf(__('嵌入 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
                continue;
            }

            // 记录成功
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, true, $latency_ms);
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
                'provider'   => $try_provider,
                'usage'      => $usage,
                'dimensions' => !empty($embeddings[0]) ? count($embeddings[0]) : 0,
            ];
        }

        // 所有 Provider 都失败了
        if ($last_error) {
            do_action('wpmind_error', $last_error, 'embed', compact('texts', 'options'));
            return $last_error;
        }

        return new WP_Error('wpmind_embed_failed', __('嵌入请求失败', 'wpmind'));
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
    // v2.7.0 专用 API
    // ============================================

    /**
     * 文本摘要
     *
     * @since 2.7.0
     * @param string $text    要摘要的文本
     * @param array  $options 选项
     * @return string|WP_Error
     */
    public function summarize(string $text, array $options = []) {
        $defaults = [
            'context'    => 'summarize',
            'max_length' => 200,          // 摘要最大字数
            'style'      => 'paragraph',  // paragraph（段落）/ bullet（要点）/ title（标题）
            'language'   => 'auto',       // 输出语言
            'cache_ttl'  => 3600,         // 缓存 1 小时
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 解析默认 provider/model 用于缓存键
        $default_provider = get_option('wpmind_default_provider', 'openai');
        $default_model = $this->get_current_model($default_provider);

        // 检查缓存
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('summarize', compact('text', 'options'), $default_provider, $default_model);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // 构建 Prompt
        $style_prompts = [
            'paragraph' => '用一段简洁的文字总结以下内容',
            'bullet'    => '用要点列表总结以下内容的关键信息',
            'title'     => '为以下内容生成一个简洁的标题',
        ];
        $style_prompt = $style_prompts[$options['style']] ?? $style_prompts['paragraph'];

        $length_hint = $options['style'] === 'title' 
            ? '（不超过 20 个字）' 
            : "（不超过 {$options['max_length']} 个字）";

        $lang_hint = $options['language'] !== 'auto' 
            ? "，用{$options['language']}输出" 
            : '';

        $prompt = "{$style_prompt}{$length_hint}{$lang_hint}：\n\n{$text}";

        do_action('wpmind_before_request', 'summarize', compact('text', 'options'), $context);

        $result = $this->chat($prompt, [
            'context'     => $context,
            'max_tokens'  => max(100, $options['max_length'] * 2),
            'temperature' => 0.3,
            'cache_ttl'   => 0,
        ]);

        if (is_wp_error($result)) {
            do_action('wpmind_error', $result, 'summarize', compact('text', 'options'));
            return $result;
        }

        $summary = trim($result['content']);

        do_action('wpmind_after_request', 'summarize', $summary, compact('text', 'options'), $result['usage']);

        // 缓存结果
        if ($options['cache_ttl'] > 0) {
            set_transient($cache_key, $summary, $options['cache_ttl']);
        }

        return $summary;
    }

    /**
     * 内容审核
     *
     * @since 2.7.0
     * @param string $content 要审核的内容
     * @param array  $options 选项
     * @return array|WP_Error
     */
    public function moderate(string $content, array $options = []) {
        $defaults = [
            'context'    => 'moderation',
            'categories' => ['spam', 'adult', 'violence', 'hate', 'illegal'],
            'threshold'  => 0.7,  // 判定阈值
            'cache_ttl'  => 300,  // 缓存 5 分钟
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 解析默认 provider/model 用于缓存键
        $default_provider = get_option('wpmind_default_provider', 'openai');
        $default_model = $this->get_current_model($default_provider);

        // 检查缓存
        if ($options['cache_ttl'] > 0) {
            $cache_key = $this->generate_cache_key('moderate', compact('content', 'options'), $default_provider, $default_model);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $categories = implode('、', $options['categories']);

        $schema = [
            'type' => 'object',
            'required' => ['safe', 'categories'],
            'properties' => [
                'safe' => ['type' => 'boolean'],
                'categories' => [
                    'type' => 'object',
                    'properties' => array_combine(
                        $options['categories'],
                        array_fill(0, count($options['categories']), [
                            'type' => 'object',
                            'properties' => [
                                'flagged' => ['type' => 'boolean'],
                                'score'   => ['type' => 'number'],
                                'reason'  => ['type' => 'string'],
                            ],
                        ])
                    ),
                ],
                'summary' => ['type' => 'string'],
            ],
        ];

        $prompt = "请审核以下内容是否包含不当信息。检查类别：{$categories}。\n\n内容：\n{$content}";

        do_action('wpmind_before_request', 'moderate', compact('content', 'options'), $context);

        $result = $this->structured($prompt, $schema, [
            'context'     => $context,
            'temperature' => 0.1,
            'retries'     => 2,
        ]);

        if (is_wp_error($result)) {
            do_action('wpmind_error', $result, 'moderate', compact('content', 'options'));
            return $result;
        }

        $moderation = [
            'safe'       => $result['data']['safe'] ?? true,
            'categories' => $result['data']['categories'] ?? [],
            'summary'    => $result['data']['summary'] ?? '',
            'provider'   => $result['provider'],
            'model'      => $result['model'],
            'usage'      => $result['usage'],
        ];

        do_action('wpmind_after_request', 'moderate', $moderation, compact('content', 'options'), $result['usage']);

        // 缓存结果
        if ($options['cache_ttl'] > 0) {
            set_transient($cache_key, $moderation, $options['cache_ttl']);
        }

        return $moderation;
    }

    /**
     * 音频转录（语音转文字）
     *
     * @since 2.7.0
     * @param string $audio_file 音频文件路径或 URL
     * @param array  $options    选项
     * @return array|WP_Error
     */
    public function transcribe(string $audio_file, array $options = []) {
        $defaults = [
            'context'  => 'transcription',
            'language' => 'auto',    // 语言提示
            'prompt'   => '',        // 可选的提示词
            'format'   => 'text',    // text/json/srt/vtt
            'provider' => 'auto',    // auto 时使用默认 provider
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 支持 transcribe 的 provider 列表
        $transcribe_providers = ['openai'];

        // 选择服务商（集成路由 + 故障转移）
        $provider = $options['provider'];
        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 使用 FailoverManager 获取故障转移链
        $failover_chain = [$provider];
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            $failover = \WPMind\Failover\FailoverManager::instance();
            $failover_chain = $failover->get_failover_chain($provider);
        }

        // 过滤出支持 transcribe 的 provider
        $failover_chain = array_values(array_filter($failover_chain, function ($p) use ($transcribe_providers) {
            return in_array($p, $transcribe_providers, true);
        }));

        if (empty($failover_chain)) {
            return new WP_Error('wpmind_transcribe_not_supported',
                __('没有可用的转录服务商（仅 OpenAI 支持 Whisper）', 'wpmind'));
        }

        // 确定是文件路径还是 URL（在循环外处理，避免重复下载）
        $is_temp = false;
        if (filter_var($audio_file, FILTER_VALIDATE_URL)) {
            $temp_file = download_url($audio_file);
            if (is_wp_error($temp_file)) {
                return $temp_file;
            }
            $file_path = $temp_file;
            $is_temp = true;
        } else {
            $file_path = $audio_file;
        }

        if (!file_exists($file_path)) {
            return new WP_Error('wpmind_file_not_found', __('音频文件不存在', 'wpmind'));
        }

        // 读取文件内容（在循环外读取，避免重复 IO）
        $file_content = file_get_contents($file_path);

        // 清理临时文件
        if ($is_temp && isset($temp_file) && file_exists($temp_file)) {
            unlink($temp_file);
        }

        do_action('wpmind_before_request', 'transcribe', compact('audio_file', 'options'), $context);

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        // 使用故障转移链依次尝试
        $last_error = null;

        foreach ($failover_chain as $index => $try_provider) {
            if (!isset($endpoints[$try_provider])) {
                $last_error = new WP_Error('wpmind_provider_not_found',
                    sprintf(__('服务商 %s 未配置', 'wpmind'), $try_provider));
                continue;
            }

            $endpoint = $endpoints[$try_provider];
            $api_key = $endpoint['api_key'] ?? '';

            if (empty($api_key)) {
                $last_error = new WP_Error('wpmind_api_key_missing',
                    sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $try_provider));
                continue;
            }

            // 准备请求
            $base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
            $api_url = trailingslashit($base_url) . 'audio/transcriptions';

            $boundary = wp_generate_password(24, false);
            $body = '';

            // 文件
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.mp3\"\r\n";
            $body .= "Content-Type: audio/mpeg\r\n\r\n";
            $body .= $file_content . "\r\n";

            // 模型
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
            $body .= "whisper-1\r\n";

            // 语言
            if ($options['language'] !== 'auto') {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
                $body .= "{$options['language']}\r\n";
            }

            // 提示词
            if (!empty($options['prompt'])) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
                $body .= "{$options['prompt']}\r\n";
            }

            // 格式
            $response_format = $options['format'] === 'text' ? 'text' : $options['format'];
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
            $body .= "{$response_format}\r\n";

            $body .= "--{$boundary}--\r\n";

            $start_time = microtime(true);

            $response = wp_remote_post($api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body'    => $body,
                'timeout' => 120,
            ]);

            $latency_ms = (int)((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $last_error = new WP_Error('wpmind_transcribe_failed',
                    sprintf(__('转录请求失败: %s', 'wpmind'), $response->get_error_message()));
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $resp_body = wp_remote_retrieve_body($response);

            if ($status_code !== 200) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $data = json_decode($resp_body, true);
                $error_message = $data['error']['message'] ?? $resp_body;
                $last_error = new WP_Error('wpmind_transcribe_error',
                    sprintf(__('转录 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
                continue;
            }

            // 记录成功
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, true, $latency_ms);
            }

            $result = [
                'text'     => $options['format'] === 'text' ? $resp_body : '',
                'data'     => $options['format'] !== 'text' ? json_decode($resp_body, true) : null,
                'provider' => $try_provider,
                'format'   => $options['format'],
            ];

            if ($options['format'] !== 'text' && is_array($result['data'])) {
                $result['text'] = $result['data']['text'] ?? '';
            }

            do_action('wpmind_after_request', 'transcribe', $result, compact('audio_file', 'options'), []);

            return $result;
        }

        // 所有 Provider 都失败了
        if ($last_error) {
            do_action('wpmind_error', $last_error, 'transcribe', compact('audio_file', 'options'));
            return $last_error;
        }

        return new WP_Error('wpmind_transcribe_failed', __('转录请求失败', 'wpmind'));
    }

    /**
     * 文本转语音
     *
     * @since 2.7.0
     * @param string $text    要转换的文本
     * @param array  $options 选项
     * @return array|WP_Error 包含音频 URL 或二进制数据
     */
    public function speech(string $text, array $options = []) {
        $defaults = [
            'context'  => 'speech',
            'voice'    => 'alloy',     // OpenAI: alloy/echo/fable/onyx/nova/shimmer
            'model'    => 'tts-1',     // tts-1 / tts-1-hd
            'speed'    => 1.0,         // 0.25 - 4.0
            'format'   => 'mp3',       // mp3/opus/aac/flac
            'save_to'  => '',          // 保存路径，空则返回 URL
            'provider' => 'auto',      // auto 时使用默认 provider
        ];
        $options = wp_parse_args($options, $defaults);

        $context = $options['context'];

        // 支持 TTS 的 provider 列表
        $speech_providers = ['openai', 'deepseek'];

        // 选择服务商（集成路由 + 故障转移）
        $provider = $options['provider'];
        if ($provider === 'auto') {
            $provider = get_option('wpmind_default_provider', 'openai');
        }
        $provider = apply_filters('wpmind_select_provider', $provider, $context);

        // 使用 FailoverManager 获取故障转移链
        $failover_chain = [$provider];
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            $failover = \WPMind\Failover\FailoverManager::instance();
            $failover_chain = $failover->get_failover_chain($provider);
        }

        // 过滤出支持 TTS 的 provider
        $failover_chain = array_values(array_filter($failover_chain, function ($p) use ($speech_providers) {
            return in_array($p, $speech_providers, true);
        }));

        if (empty($failover_chain)) {
            return new WP_Error('wpmind_speech_not_supported',
                __('没有可用的语音合成服务商', 'wpmind'));
        }

        do_action('wpmind_before_request', 'speech', compact('text', 'options'), $context);

        // 获取端点配置
        $wpmind = \WPMind\WPMind::instance();
        $endpoints = $wpmind->get_custom_endpoints();

        // 使用故障转移链依次尝试
        $last_error = null;

        foreach ($failover_chain as $index => $try_provider) {
            if (!isset($endpoints[$try_provider])) {
                $last_error = new WP_Error('wpmind_provider_not_found',
                    sprintf(__('服务商 %s 未配置', 'wpmind'), $try_provider));
                continue;
            }

            $endpoint = $endpoints[$try_provider];
            $api_key = $endpoint['api_key'] ?? '';

            if (empty($api_key)) {
                $last_error = new WP_Error('wpmind_api_key_missing',
                    sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $try_provider));
                continue;
            }

            $base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
            $api_url = trailingslashit($base_url) . 'audio/speech';

            $start_time = microtime(true);

            $response = wp_remote_post($api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'model'           => $options['model'],
                    'input'           => $text,
                    'voice'           => $options['voice'],
                    'speed'           => $options['speed'],
                    'response_format' => $options['format'],
                ]),
                'timeout' => 60,
            ]);

            $latency_ms = (int)((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $last_error = new WP_Error('wpmind_speech_failed',
                    sprintf(__('语音合成请求失败: %s', 'wpmind'), $response->get_error_message()));
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $audio_data = wp_remote_retrieve_body($response);

            if ($status_code !== 200) {
                // 记录失败
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, false, $latency_ms);
                }

                $data = json_decode($audio_data, true);
                $error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
                $last_error = new WP_Error('wpmind_speech_error',
                    sprintf(__('语音合成 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
                continue;
            }

            // 记录成功
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->record_result($try_provider, true, $latency_ms);
            }

            $result = [
                'provider' => $try_provider,
                'model'    => $options['model'],
                'voice'    => $options['voice'],
                'format'   => $options['format'],
                'size'     => strlen($audio_data),
            ];

            // 保存到文件或上传到媒体库
            if (!empty($options['save_to'])) {
                // 验证路径在 uploads 目录内，防止路径遍历
                $upload_dir = wp_upload_dir();
                $save_dir = realpath(dirname($options['save_to']));
                $base_dir = realpath($upload_dir['basedir']);
                if ($save_dir === false || $base_dir === false || strpos($save_dir, $base_dir) !== 0) {
                    return new WP_Error('wpmind_invalid_path', __('保存路径必须在 uploads 目录内', 'wpmind'));
                }
                file_put_contents($options['save_to'], $audio_data);
                $result['file'] = $options['save_to'];
            } else {
                // 上传到 WordPress 媒体库
                $upload = wp_upload_bits(
                    'wpmind-speech-' . time() . '.' . $options['format'],
                    null,
                    $audio_data
                );

                if (!empty($upload['error'])) {
                    return new WP_Error('wpmind_upload_failed', $upload['error']);
                }

                $result['url'] = $upload['url'];
                $result['file'] = $upload['file'];
            }

            do_action('wpmind_after_request', 'speech', $result, compact('text', 'options'), []);

            return $result;
        }

        // 所有 Provider 都失败了
        if ($last_error) {
            do_action('wpmind_error', $last_error, 'speech', compact('text', 'options'));
            return $last_error;
        }

        return new WP_Error('wpmind_speech_failed', __('语音合成请求失败', 'wpmind'));
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
     * 判断是否应该使用 SDK 执行请求
     *
     * @since 3.6.0
     * @param string $provider 服务商 ID
     * @param array  $args     请求参数
     * @return bool
     */
    private function should_use_sdk(string $provider, array $args): bool {
        // 1. SDK 适配器类不存在，不使用
        if (!class_exists('\\WPMind\\SDK\\SDKAdapter')) {
            return false;
        }

        // 2. AiClient SDK 不可用，不使用
        if (!class_exists('WordPress\\AiClient\\AiClient')) {
            return false;
        }

        // 3. 用户配置禁用
        if (!get_option('wpmind_sdk_enabled', true)) {
            return false;
        }

        // 4. 能力 gate：tools 请求暂不走 SDK（v3.6.0 限制）
        if (!empty($args['tools'])) {
            return false;
        }

        // 5. Provider 白名单（默认 anthropic/google，可通过 filter 扩展）
        $sdk_providers = apply_filters('wpmind_sdk_providers', ['anthropic', 'google']);
        return in_array($provider, $sdk_providers, true);
    }

    /**
     * 执行 Chat 请求（路由方法）
     *
     * 优先尝试 SDK 路径，失败时回退到原生 HTTP。
     *
     * @since 3.6.0
     * @param array  $args     请求参数
     * @param string $provider 服务商
     * @param string $model    模型
     * @return array|WP_Error
     */
    private function execute_chat_request(array $args, string $provider, string $model) {
        // 尝试 SDK 路径
        if ($this->should_use_sdk($provider, $args)) {
            $sdk = new \WPMind\SDK\SDKAdapter();
            $start_time = microtime(true);
            $result = $sdk->chat($args, $provider, $model);
            $latency_ms = (int)((microtime(true) - $start_time) * 1000);

            if (!is_wp_error($result)) {
                // SDK 成功，记录健康状态
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($provider, true, $latency_ms);
                }
                return $result;
            }

            // SDK 失败：按错误分类处理
            $error_code = $result->get_error_code();

            if ($error_code === 'wpmind_sdk_invalid_args' || $error_code === 'wpmind_sdk_unavailable') {
                // 适配错误或 SDK 不可用，静默回退到 HTTP（不记录失败，不消耗重试预算）
                do_action('wpmind_sdk_fallback', $provider, $error_code, $result->get_error_message());
            } else {
                // Provider 错误（429/5xx 等），记录失败并返回（让外层重试/failover）
                if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                    \WPMind\Failover\FailoverManager::instance()->record_result($provider, false, $latency_ms);
                }
                return $result;
            }
        }

        // HTTP 路径（原实现）
        return $this->execute_chat_request_native($args, $provider, $model);
    }

    /**
     * 通过原生 HTTP 执行 chat 请求（SDK 的 fallback）
     *
     * @since 3.6.0
     * @param array  $args     请求参数
     * @param string $provider 服务商
     * @param string $model    模型
     * @return array|WP_Error
     */
    private function execute_chat_request_native(array $args, string $provider, string $model) {
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

        // v2.7.0: Function Calling / Tools
        if (!empty($args['tools'])) {
            $request_body['tools'] = $args['tools'];
            if (!empty($args['tool_choice']) && $args['tool_choice'] !== 'auto') {
                $request_body['tool_choice'] = $args['tool_choice'];
            }
        }

        // 确定 API URL
        $base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
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
                \WPMind\Failover\FailoverManager::instance()->record_result($provider, false, $latency_ms);
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
                \WPMind\Failover\FailoverManager::instance()->record_result($provider, false, $latency_ms);
            }

            $error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
            return new WP_Error(
                'wpmind_api_error',
                sprintf(__('API 错误 (%d): %s', 'wpmind'), $status_code, $error_message),
                ['status' => $status_code, 'body' => substr((string) $body, 0, 500)]
            );
        }

        // 非 JSON 响应防护（如 Cloudflare HTML 错误页、空响应）
        if ( ! is_array($data) ) {
            // 记录失败
            if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
                \WPMind\Failover\FailoverManager::instance()->record_result($provider, false, $latency_ms);
            }

            return new WP_Error(
                'wpmind_invalid_response',
                __('服务商返回了无效的响应格式', 'wpmind'),
                ['status' => $status_code, 'body' => substr((string) $body, 0, 500)]
            );
        }

        // 记录成功
        if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
            \WPMind\Failover\FailoverManager::instance()->record_result($provider, true, $latency_ms);
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
        $tool_calls = [];
        $finish_reason = '';
        $usage = [
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
        ];

        $message = $response['choices'][0]['message'] ?? [];
        $finish_reason = $response['choices'][0]['finish_reason'] ?? '';

        // 提取内容
        if (isset($message['content'])) {
            $content = $message['content'];
        } elseif (isset($response['content'][0]['text'])) {
            // Anthropic 格式
            $content = $response['content'][0]['text'];
        }

        // v2.7.0: 提取 tool_calls
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $call) {
                $tool_calls[] = [
                    'id'       => $call['id'] ?? '',
                    'type'     => $call['type'] ?? 'function',
                    'function' => [
                        'name'      => $call['function']['name'] ?? '',
                        'arguments' => $call['function']['arguments'] ?? '{}',
                    ],
                ];
            }
        }

        // 提取用量
        if (isset($response['usage'])) {
            $usage = [
                'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
            ];
        }

        $result = [
            'content'       => $content,
            'provider'      => $provider,
            'model'         => $model,
            'usage'         => $usage,
            'finish_reason' => $finish_reason,
        ];

        // 只有在有 tool_calls 时才添加
        if (!empty($tool_calls)) {
            $result['tool_calls'] = $tool_calls;
        }

        return $result;
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

        // 语义化拼音格式：AI 生成按词分隔的拼音
        if ($options['format'] === 'pinyin') {
            $prompt = "将以下中文文本转换为拼音，要求：
1. 按词语分隔，不是按字分隔（如 '你好世界' 应为 'nihao-shijie' 而非 'ni-hao-shi-jie'）
2. 词语之间用连字符 '-' 连接
3. 同一词语内的拼音不加分隔符
4. 全部小写，无声调
5. 保留英文和数字原样
6. 只返回拼音结果，不要其他解释

文本：{$text}";
            return $prompt;
        }

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
     * @param string $type     类型
     * @param array  $args     参数
     * @param string $provider 服务商
     * @param string $model    模型
     * @return string
     */
    private function generate_cache_key(string $type, array $args, string $provider = '', string $model = ''): string {
        $key = 'wpmind_' . $type . '_' . $provider . '_' . $model . '_' . md5(serialize($args));
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
