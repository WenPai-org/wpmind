<?php
/**
 * WPMind 错误处理类
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

declare(strict_types=1);

namespace WPMind\API;

use WP_Error;

/**
 * 统一错误处理类
 *
 * 提供标准化的错误创建和处理机制
 *
 * @since 2.5.0
 */
class ErrorHandler {

    /**
     * 错误代码常量
     */
    // 通用错误
    const ERROR_NOT_AVAILABLE = 'wpmind_not_available';
    const ERROR_INVALID_PARAMS = 'wpmind_invalid_params';
    const ERROR_EMPTY_INPUT = 'wpmind_empty_input';
    
    // 调用限制错误
    const ERROR_RECURSIVE_CALL = 'wpmind_recursive_call';
    const ERROR_CALL_DEPTH_EXCEEDED = 'wpmind_call_depth_exceeded';
    const ERROR_RATE_LIMITED = 'wpmind_rate_limited';
    const ERROR_BUDGET_EXCEEDED = 'wpmind_budget_exceeded';
    
    // API 错误
    const ERROR_API_ERROR = 'wpmind_api_error';
    const ERROR_API_TIMEOUT = 'wpmind_api_timeout';
    const ERROR_API_AUTH = 'wpmind_api_auth';
    const ERROR_API_QUOTA = 'wpmind_api_quota';
    
    // 服务商错误
    const ERROR_PROVIDER_NOT_FOUND = 'wpmind_provider_not_found';
    const ERROR_PROVIDER_NOT_CONFIGURED = 'wpmind_provider_not_configured';
    const ERROR_MODEL_NOT_SUPPORTED = 'wpmind_model_not_supported';

    /**
     * 错误消息映射
     *
     * @var array
     */
    private static $error_messages = [
        self::ERROR_NOT_AVAILABLE => 'WPMind 插件未激活或未配置',
        self::ERROR_INVALID_PARAMS => '无效的参数',
        self::ERROR_EMPTY_INPUT => '输入内容为空',
        self::ERROR_RECURSIVE_CALL => '检测到循环调用',
        self::ERROR_CALL_DEPTH_EXCEEDED => '调用深度超过限制',
        self::ERROR_RATE_LIMITED => '请求频率过高，请稍后再试',
        self::ERROR_BUDGET_EXCEEDED => '已超出预算限制',
        self::ERROR_API_ERROR => 'API 调用失败',
        self::ERROR_API_TIMEOUT => 'API 请求超时',
        self::ERROR_API_AUTH => 'API 认证失败，请检查 API Key',
        self::ERROR_API_QUOTA => 'API 配额已用尽',
        self::ERROR_PROVIDER_NOT_FOUND => '找不到指定的服务商',
        self::ERROR_PROVIDER_NOT_CONFIGURED => '服务商未配置',
        self::ERROR_MODEL_NOT_SUPPORTED => '不支持的模型',
    ];

    /**
     * 创建标准错误对象
     *
     * @param string $code    错误代码（使用类常量）
     * @param string $message 可选的自定义消息
     * @param array  $data    额外的错误数据
     * @return WP_Error
     */
    public static function create(string $code, string $message = '', array $data = []): WP_Error {
        // 如果没有提供消息，使用默认消息
        if (empty($message)) {
            $message = self::$error_messages[$code] ?? __('未知错误', 'wpmind');
        }

        // 添加时间戳和请求 ID
        $data['timestamp'] = time();
        $data['request_id'] = self::generate_request_id();

        // 记录错误日志
        self::log_error($code, $message, $data);

        return new WP_Error($code, __($message, 'wpmind'), $data);
    }

    /**
     * 快捷方法：创建"不可用"错误
     *
     * @return WP_Error
     */
    public static function not_available(): WP_Error {
        return self::create(self::ERROR_NOT_AVAILABLE);
    }

    /**
     * 快捷方法：创建"无效参数"错误
     *
     * @param string $param_name 参数名
     * @param mixed  $value      参数值
     * @return WP_Error
     */
    public static function invalid_param(string $param_name, $value = null): WP_Error {
        return self::create(
            self::ERROR_INVALID_PARAMS,
            sprintf(__('无效的参数: %s', 'wpmind'), $param_name),
            ['param' => $param_name, 'value' => $value]
        );
    }

    /**
     * 快捷方法：创建"API 错误"
     *
     * @param string $provider API 服务商
     * @param string $message  错误消息
     * @param int    $code     HTTP 状态码
     * @return WP_Error
     */
    public static function api_error(string $provider, string $message, int $code = 0): WP_Error {
        return self::create(
            self::ERROR_API_ERROR,
            $message,
            ['provider' => $provider, 'http_code' => $code]
        );
    }

    /**
     * 快捷方法：创建"循环调用"错误
     *
     * @param string $method  方法名
     * @param string $call_id 调用 ID
     * @return WP_Error
     */
    public static function recursive_call(string $method, string $call_id): WP_Error {
        return self::create(
            self::ERROR_RECURSIVE_CALL,
            sprintf(__('检测到循环调用: %s', 'wpmind'), $method),
            ['method' => $method, 'call_id' => $call_id]
        );
    }

    /**
     * 快捷方法：创建"调用深度超限"错误
     *
     * @param string $method    方法名
     * @param int    $depth     当前深度
     * @param int    $max_depth 最大深度
     * @return WP_Error
     */
    public static function call_depth_exceeded(string $method, int $depth, int $max_depth): WP_Error {
        return self::create(
            self::ERROR_CALL_DEPTH_EXCEEDED,
            sprintf(__('调用深度超限: %s (当前 %d, 最大 %d)', 'wpmind'), $method, $depth, $max_depth),
            ['method' => $method, 'depth' => $depth, 'max_depth' => $max_depth]
        );
    }

    /**
     * 从 API 响应创建错误
     *
     * @param array  $response API 响应
     * @param string $provider 服务商
     * @return WP_Error
     */
    public static function from_api_response(array $response, string $provider): WP_Error {
        $http_code = $response['response']['code'] ?? 0;
        $body = $response['body'] ?? '';

        // 尝试解析 JSON 错误
        $error_data = json_decode($body, true);
        $error_message = $error_data['error']['message'] 
            ?? $error_data['message'] 
            ?? $body 
            ?? __('未知 API 错误', 'wpmind');

        // 根据 HTTP 状态码分类错误
        $code = self::ERROR_API_ERROR;
        if ($http_code === 401 || $http_code === 403) {
            $code = self::ERROR_API_AUTH;
        } elseif ($http_code === 429) {
            $code = self::ERROR_RATE_LIMITED;
        } elseif ($http_code === 408 || $http_code === 504) {
            $code = self::ERROR_API_TIMEOUT;
        }

        return self::create($code, $error_message, [
            'provider'  => $provider,
            'http_code' => $http_code,
            'response'  => substr((string) $body, 0, 500),
        ]);
    }

    /**
     * 检查错误是否为特定类型
     *
     * @param WP_Error $error 错误对象
     * @param string   $code  错误代码
     * @return bool
     */
    public static function is_error_type(WP_Error $error, string $code): bool {
        return $error->get_error_code() === $code;
    }

    /**
     * 检查错误是否可重试
     *
     * @param WP_Error $error 错误对象
     * @return bool
     */
    public static function is_retryable(WP_Error $error): bool {
        $retryable_codes = [
            self::ERROR_API_TIMEOUT,
            self::ERROR_RATE_LIMITED,
        ];

        return in_array($error->get_error_code(), $retryable_codes, true);
    }

    /**
     * 生成请求 ID
     *
     * @return string
     */
    private static function generate_request_id(): string {
        return 'wpmind_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
    }

    /**
     * 记录错误日志
     *
     * @param string $code    错误代码
     * @param string $message 错误消息
     * @param array  $data    错误数据
     */
    private static function log_error(string $code, string $message, array $data): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[WPMind Error] %s: %s | Request ID: %s',
            $code,
            $message,
            $data['request_id'] ?? 'unknown'
        );

        if (!empty($data['provider'])) {
            $log_message .= ' | Provider: ' . $data['provider'];
        }

        if (!empty($data['http_code'])) {
            $log_message .= ' | HTTP Code: ' . $data['http_code'];
        }

        error_log($log_message);
    }

    /**
     * 获取所有错误代码
     *
     * @return array
     */
    public static function get_all_error_codes(): array {
        return [
            self::ERROR_NOT_AVAILABLE,
            self::ERROR_INVALID_PARAMS,
            self::ERROR_EMPTY_INPUT,
            self::ERROR_RECURSIVE_CALL,
            self::ERROR_CALL_DEPTH_EXCEEDED,
            self::ERROR_RATE_LIMITED,
            self::ERROR_BUDGET_EXCEEDED,
            self::ERROR_API_ERROR,
            self::ERROR_API_TIMEOUT,
            self::ERROR_API_AUTH,
            self::ERROR_API_QUOTA,
            self::ERROR_PROVIDER_NOT_FOUND,
            self::ERROR_PROVIDER_NOT_CONFIGURED,
            self::ERROR_MODEL_NOT_SUPPORTED,
        ];
    }

    /**
     * 获取用户友好的错误消息
     *
     * @param WP_Error $error 错误对象
     * @return string
     */
    public static function get_user_friendly_message(WP_Error $error): string {
        $code = $error->get_error_code();

        // 用户友好消息映射
        $user_messages = [
            self::ERROR_NOT_AVAILABLE => __('AI 服务暂不可用，请稍后再试或检查插件设置。', 'wpmind'),
            self::ERROR_RATE_LIMITED => __('请求太频繁，请稍后再试。', 'wpmind'),
            self::ERROR_BUDGET_EXCEEDED => __('本月 AI 使用额度已用完，请联系管理员。', 'wpmind'),
            self::ERROR_API_TIMEOUT => __('AI 服务响应超时，请稍后再试。', 'wpmind'),
            self::ERROR_API_AUTH => __('AI 服务认证失败，请联系管理员检查配置。', 'wpmind'),
        ];

        return $user_messages[$code] ?? __('操作失败，请稍后再试。', 'wpmind');
    }
}
