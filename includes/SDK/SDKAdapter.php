<?php
/**
 * WP AI Client SDK 适配器
 *
 * 封装 SDK 调用，提供与 PublicAPI 兼容的接口。
 * SDK 使用异常而非 WP_Error，响应是 GenerativeAiResult 对象而非数组。
 * 本适配器负责两者之间的转换。
 *
 * WP 7.0+ 使用 wp_ai_client_prompt() 核心 wrapper，获得：
 * - WP_Error 原生处理（替代 try-catch）
 * - wp_ai_client_default_request_timeout filter 自动应用
 * - wp_supports_ai() 自动检查
 * - wp_ai_client_prevent_prompt filter 自动应用
 *
 * @package WPMind\SDK
 * @since 3.6.0
 */

declare(strict_types=1);

namespace WPMind\SDK;

use WP_Error;

/**
 * SDK 适配器
 *
 * 将 WP AI Client SDK 的调用方式适配为 PublicAPI 兼容的数组格式。
 *
 * @since 3.6.0
 */
class SDKAdapter {

	/**
	 * SDK 内置 Provider 映射
	 *
	 * @var array<string, string>
	 */
	private const BUILTIN_PROVIDERS = [
		'openai'    => 'WordPress\\AiClient\\Providers\\ProviderImplementations\\OpenAi\\OpenAiProvider',
		'anthropic' => 'WordPress\\AiClient\\Providers\\ProviderImplementations\\Anthropic\\AnthropicProvider',
		'google'    => 'WordPress\\AiClient\\Providers\\ProviderImplementations\\Google\\GoogleProvider',
	];

	/**
	 * AI 对话
	 *
	 * @param array  $args     Request parameters.
	 * @param string $provider Provider identifier.
	 * @param string $model    Model identifier.
	 * @return array|WP_Error
	 */
	public function chat( array $args, string $provider, string $model ): array|WP_Error {
		// Check SDK availability.
		if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
			return new WP_Error( 'wpmind_sdk_unavailable', __( 'WP AI Client SDK 不可用', 'wpmind' ) );
		}

		// WP 7.0+: use core wrapper for WP_Error handling and auto filters.
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			return $this->chat_via_wp_core( $args, $provider, $model );
		}

		// Legacy: direct SDK call with exception handling.
		return $this->chat_via_sdk( $args, $provider, $model );
	}

	/**
	 * WP 7.0+ 路径：通过 wp_ai_client_prompt() 核心入口
	 *
	 * 使用 snake_case 方法名（WP wrapper 代理到 SDK camelCase）。
	 * 返回 WP_Error 替代抛异常。
	 *
	 * @param array  $args     Request parameters.
	 * @param string $provider Provider identifier.
	 * @param string $model    Model identifier.
	 * @return array|WP_Error
	 * @since 4.1.0
	 */
	private function chat_via_wp_core( array $args, string $provider, string $model ): array|WP_Error {
		$provider_class = $this->resolve_provider_class( $provider );
		$registry       = \WordPress\AiClient\AiClient::defaultRegistry();

		// Resolve model instance.
		$model_instance = $this->resolve_model_instance( $registry, $provider_class, $model );

		// Build prompt (wp_ai_client_prompt auto-applies timeout/prevent/supports_ai).
		$builder = wp_ai_client_prompt( $args['messages'] );

		if ( $model_instance ) {
			$builder->using_model( $model_instance );
		} elseif ( $provider_class ) {
			$builder->using_provider( $provider_class );
		}

		$builder->using_temperature( $args['temperature'] ?? 0.7 );
		$builder->using_max_tokens( $args['max_tokens'] ?? 2000 );

		// System instruction.
		$system_msg = $this->extract_system_message( $args['messages'] );
		if ( $system_msg ) {
			$builder->using_system_instruction( $system_msg );
		}

		// JSON mode.
		if ( ! empty( $args['json_mode'] ) ) {
			$builder->as_json_response();
		}

		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->format_result( $result, $provider, $model );
	}

	/**
	 * Legacy 路径：直接 SDK 调用 + exception 捕获
	 *
	 * @param array  $args     Request parameters.
	 * @param string $provider Provider identifier.
	 * @param string $model    Model identifier.
	 * @return array|WP_Error
	 * @since 4.1.0
	 */
	private function chat_via_sdk( array $args, string $provider, string $model ): array|WP_Error {
		$provider_class = $this->resolve_provider_class( $provider );

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			$model_instance = $this->resolve_model_instance( $registry, $provider_class, $model );

			$builder = \WordPress\AiClient\AiClient::prompt( $args['messages'] );

			if ( $model_instance ) {
				$builder->usingModel( $model_instance );
			} elseif ( $provider_class ) {
				$builder->usingProvider( $provider_class );
			}

			$builder->usingTemperature( $args['temperature'] ?? 0.7 );
			$builder->usingMaxTokens( $args['max_tokens'] ?? 2000 );

			$system_msg = $this->extract_system_message( $args['messages'] );
			if ( $system_msg ) {
				$builder->usingSystemInstruction( $system_msg );
			}

			if ( ! empty( $args['json_mode'] ) ) {
				$builder->asJsonResponse();
			}

			$result = $builder->generateTextResult();

			return $this->format_result( $result, $provider, $model );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'wpmind_sdk_invalid_args', $e->getMessage() );
		} catch ( \Exception $e ) {
			return $this->convert_exception_to_wp_error( $e );
		}
	}

	/**
	 * 解析模型实例
	 *
	 * @param object      $registry       Provider registry.
	 * @param string|null $provider_class Provider class name.
	 * @param string      $model          Model identifier.
	 * @return object|null Model instance.
	 * @since 4.1.0
	 */
	private function resolve_model_instance( object $registry, ?string $provider_class, string $model ): ?object {
		if ( ! $provider_class || 'auto' === $model || 'default' === $model ) {
			return null;
		}

		try {
			return $registry->getProviderModel( $provider_class, $model );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * 从消息列表中提取 system message
	 *
	 * @param array $messages Message list.
	 * @return string|null
	 * @since 4.1.0
	 */
	private function extract_system_message( array $messages ): ?string {
		foreach ( $messages as $msg ) {
			if ( ( $msg['role'] ?? '' ) === 'system' ) {
				$content = $msg['content'] ?? '';
				return '' !== $content ? $content : null;
			}
		}
		return null;
	}

	/**
	 * 格式化 SDK 结果为统一数组
	 *
	 * @param object $result   GenerativeAiResult.
	 * @param string $provider Provider ID.
	 * @param string $model    Model identifier.
	 * @return array
	 * @since 4.1.0
	 */
	private function format_result( object $result, string $provider, string $model ): array {
		$finish_reason = '';
		$candidates    = $result->getCandidates();
		if ( ! empty( $candidates ) ) {
			$fr = $candidates[0]->getFinishReason();
			if ( null !== $fr ) {
				$finish_reason = is_object( $fr ) && property_exists( $fr, 'value' ) ? $fr->value : (string) $fr;
			}
		}

		return [
			'content'       => $result->toText(),
			'provider'      => $provider,
			'model'         => $model,
			'usage'         => $this->extract_token_usage( $result ),
			'finish_reason' => $finish_reason,
		];
	}

	/**
	 * 安全提取 token 用量
	 *
	 * @param object $result  SDK result object.
	 * @return array
	 */
	private function extract_token_usage( object $result ): array {
		try {
			$usage = $result->getTokenUsage();
			if ( null === $usage ) {
				return [
					'prompt_tokens'     => 0,
					'completion_tokens' => 0,
					'total_tokens'      => 0,
				];
			}
			return [
				'prompt_tokens'     => $usage->getPromptTokens() ?? 0,
				'completion_tokens' => $usage->getCompletionTokens() ?? 0,
				'total_tokens'      => $usage->getTotalTokens() ?? 0,
			];
		} catch ( \Throwable $e ) {
			return [
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			];
		}
	}

	/**
	 * 解析 Provider 类名
	 *
	 * 先检查 WPMind 注册的 Provider，再检查 SDK 内置 Provider。
	 *
	 * @param string $provider Provider identifier.
	 * @return string|null Provider class name, or null if not found.
	 */
	private function resolve_provider_class( string $provider ): ?string {
		// Check WPMind registered providers first.
		if ( class_exists( 'WPMind\\Providers\\ProviderRegistrar' ) ) {
			$class = \WPMind\Providers\ProviderRegistrar::getProviderClass( $provider );
			if ( $class ) {
				return $class;
			}
		}

		// Then check SDK built-in providers.
		return self::BUILTIN_PROVIDERS[ $provider ] ?? null;
	}

	/**
	 * 将异常转换为 WP_Error
	 *
	 * 尝试从异常消息中提取 HTTP 状态码。
	 *
	 * @param \Exception $e Exception.
	 * @return WP_Error
	 */
	private function convert_exception_to_wp_error( \Exception $e ): WP_Error {
		$message = $e->getMessage();
		$status  = 0;

		// Try to extract HTTP status code from exception message.
		if ( preg_match( '/\b(4\d{2}|5\d{2})\b/', $message, $matches ) ) {
			$status = (int) $matches[1];
		}

		$error_data = [];
		if ( $status > 0 ) {
			$error_data['status'] = $status;
		}

		// Only log full exception details in debug mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[WPMind SDK] Exception: %s', $message ) );
		}

		// Return generic description without exposing internals.
		$user_message = $status > 0
				? sprintf(
					/* translators: %d: HTTP status code. */
					__( 'SDK 请求失败 (HTTP %d)', 'wpmind' ),
					$status
				)
				: __( 'SDK 请求失败', 'wpmind' );

		return new WP_Error(
			'wpmind_sdk_error',
			$user_message,
			$error_data
		);
	}
}
