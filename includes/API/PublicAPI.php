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
