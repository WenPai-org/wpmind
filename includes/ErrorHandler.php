<?php
/**
 * 错误处理器
 *
 * @package WPMind
 * @since 1.4.1
 */

declare(strict_types=1);

namespace WPMind;

/**
 * 错误处理器类
 *
 * 提供统一的错误消息映射和用户友好的错误反馈
 *
 * @since 1.4.1
 */
class ErrorHandler
{
    /**
     * HTTP 状态码到错误消息的映射
     */
    private const HTTP_ERROR_MESSAGES = [
        400 => '请求格式错误，请检查参数',
        401 => 'API Key 无效或已过期',
        403 => 'API Key 权限不足，请检查是否启用了相关 API',
        404 => 'API 端点不存在，请检查 Base URL 是否正确',
        429 => '请求过于频繁，请稍后再试',
        500 => '服务器内部错误，请稍后再试',
        502 => '网关错误，服务暂时不可用',
        503 => '服务暂时不可用，请稍后再试',
        504 => '网关超时，请检查网络连接',
    ];

    /**
     * 错误类型到错误消息的映射
     */
    private const ERROR_TYPE_MESSAGES = [
        'connection_failed'    => '无法连接到服务器，请检查网络',
        'timeout'              => '请求超时，请检查网络或增加超时时间',
        'ssl_error'            => 'SSL 证书验证失败',
        'dns_error'            => '无法解析域名，请检查 Base URL',
        'invalid_response'     => '服务器返回了无效的响应',
        'rate_limit'           => '已达到 API 调用限制',
        'quota_exceeded'       => 'API 配额已用尽',
        'model_not_found'      => '指定的模型不存在',
        'invalid_api_key'      => 'API Key 格式不正确',
        'authentication_error' => '认证失败，请检查 API Key',
    ];

    /**
     * Provider 特定的错误消息
     */
    private const PROVIDER_ERROR_HINTS = [
        'openai' => [
            401 => '请确认 API Key 以 sk- 开头',
            429 => '已达到 OpenAI 速率限制，请升级账户或稍后再试',
        ],
        'anthropic' => [
            401 => '请确认 API Key 以 sk-ant- 开头',
            529 => 'Anthropic API 过载，请稍后再试',
        ],
        'deepseek' => [
            401 => '请在 DeepSeek 平台确认 API Key 是否有效',
            402 => '账户余额不足，请充值后再试',
        ],
        'qwen' => [
            401 => '请在阿里云 DashScope 确认 API Key',
        ],
        'zhipu' => [
            401 => '请在智谱开放平台确认 API Key',
        ],
        'moonshot' => [
            401 => '请在 Moonshot 平台确认 API Key',
        ],
        'baidu' => [
            401 => '请在百度智能云确认 API Key 和 Secret Key',
        ],
        'minimax' => [
            401 => '请在 MiniMax 平台确认 API Key',
        ],
    ];

    /**
     * 获取用户友好的错误消息
     *
     * @param int         $http_code HTTP 状态码
     * @param string      $provider  Provider ID
     * @param string|null $raw_error 原始错误消息
     * @return string 用户友好的错误消息
     */
    public static function getErrorMessage(int $http_code, string $provider = '', ?string $raw_error = null): string
    {
        // 检查 Provider 特定的错误消息
        if (!empty($provider) && isset(self::PROVIDER_ERROR_HINTS[$provider][$http_code])) {
            return self::PROVIDER_ERROR_HINTS[$provider][$http_code];
        }

        // 检查通用 HTTP 错误消息
        if (isset(self::HTTP_ERROR_MESSAGES[$http_code])) {
            return self::HTTP_ERROR_MESSAGES[$http_code];
        }

        // 尝试从原始错误中提取有用信息
        if (!empty($raw_error)) {
            return self::parseRawError($raw_error);
        }

        // 默认消息
        return sprintf(__('连接失败：HTTP %d', 'wpmind'), $http_code);
    }

    /**
     * 从 WP_Error 获取用户友好的错误消息
     *
     * @param \WP_Error $error    WP_Error 对象
     * @param string    $provider Provider ID
     * @return string 用户友好的错误消息
     */
    public static function getWpErrorMessage(\WP_Error $error, string $provider = ''): string
    {
        $error_code = $error->get_error_code();
        $error_message = $error->get_error_message();

        // 检测常见的 WP_Error 类型
        if (strpos($error_code, 'http_request_failed') !== false) {
            if (strpos($error_message, 'timed out') !== false) {
                return self::ERROR_TYPE_MESSAGES['timeout'];
            }
            if (strpos($error_message, 'Could not resolve host') !== false) {
                return self::ERROR_TYPE_MESSAGES['dns_error'];
            }
            if (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                return self::ERROR_TYPE_MESSAGES['ssl_error'];
            }
            return self::ERROR_TYPE_MESSAGES['connection_failed'];
        }

        // 返回原始错误消息
        return $error_message;
    }

    /**
     * 解析原始错误消息
     *
     * @param string $raw_error 原始错误消息
     * @return string 解析后的错误消息
     */
    private static function parseRawError(string $raw_error): string
    {
        // 尝试解析 JSON 错误响应
        $decoded = json_decode($raw_error, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // OpenAI 格式
            if (isset($decoded['error']['message'])) {
                return self::translateErrorMessage($decoded['error']['message']);
            }
            // Anthropic 格式
            if (isset($decoded['error']['type'])) {
                return self::translateErrorMessage($decoded['error']['type']);
            }
            // 通用格式
            if (isset($decoded['message'])) {
                return self::translateErrorMessage($decoded['message']);
            }
        }

        return $raw_error;
    }

    /**
     * 翻译常见的英文错误消息
     *
     * @param string $message 英文错误消息
     * @return string 翻译后的消息
     */
    private static function translateErrorMessage(string $message): string
    {
        $translations = [
            'invalid_api_key'           => 'API Key 无效',
            'insufficient_quota'        => 'API 配额不足',
            'rate_limit_exceeded'       => '请求过于频繁',
            'model_not_found'           => '模型不存在',
            'context_length_exceeded'   => '内容长度超出限制',
            'invalid_request_error'     => '请求格式错误',
            'authentication_error'      => '认证失败',
            'permission_denied'         => '权限不足',
            'overloaded'                => '服务过载，请稍后再试',
        ];

        $lower_message = strtolower($message);
        foreach ($translations as $key => $translation) {
            if (strpos($lower_message, $key) !== false) {
                return $translation;
            }
        }

        return $message;
    }

    /**
     * 获取错误类型消息
     *
     * @param string $error_type 错误类型
     * @return string 错误消息
     */
    public static function getErrorTypeMessage(string $error_type): string
    {
        return self::ERROR_TYPE_MESSAGES[$error_type] ?? __('未知错误', 'wpmind');
    }

    /**
     * 判断是否应该重试
     *
     * @param int $http_code HTTP 状态码
     * @return bool 是否应该重试
     */
    public static function shouldRetry(int $http_code): bool
    {
        // 可重试的状态码
        $retryable_codes = [408, 429, 500, 502, 503, 504];
        return in_array($http_code, $retryable_codes, true);
    }

    /**
     * 获取重试延迟时间（毫秒）
     *
     * @param int $attempt 当前尝试次数（从 1 开始）
     * @return int 延迟时间（毫秒）
     */
    public static function getRetryDelay(int $attempt): int
    {
        // 指数退避：1s, 2s, 4s...
        return min(1000 * pow(2, $attempt - 1), 8000);
    }
}
