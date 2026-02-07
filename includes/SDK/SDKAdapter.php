<?php
/**
 * WP AI Client SDK 适配器
 *
 * 封装 SDK 调用，提供与 PublicAPI 兼容的接口。
 * SDK 使用异常而非 WP_Error，响应是 GenerativeAiResult 对象而非数组。
 * 本适配器负责两者之间的转换。
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
	 * @param array  $args     请求参数（messages, max_tokens, temperature, json_mode, tools, tool_choice）
	 * @param string $provider 服务商标识
	 * @param string $model    模型标识
	 * @return array|WP_Error
	 */
	public function chat(array $args, string $provider, string $model): array|WP_Error {
		// 检查 SDK 可用性
		if (!class_exists('WordPress\\AiClient\\AiClient')) {
			return new WP_Error('wpmind_sdk_unavailable', __('WP AI Client SDK 不可用', 'wpmind'));
		}

		// 解析 Provider 类名
		$provider_class = $this->resolve_provider_class($provider);

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			// 获取模型实例
			$model_instance = null;
			if ($provider_class && $model !== 'auto' && $model !== 'default') {
				try {
					$model_instance = $registry->getProviderModel($provider_class, $model);
				} catch (\Exception $e) {
					// 模型不存在，尝试不指定模型
				}
			}

			// 构建 PromptBuilder
			$builder = \WordPress\AiClient\AiClient::prompt($args['messages']);

			if ($model_instance) {
				$builder->usingModel($model_instance);
			} elseif ($provider_class) {
				$builder->usingProvider($provider_class);
			}

			$builder->usingTemperature($args['temperature'] ?? 0.7);
			$builder->usingMaxTokens($args['max_tokens'] ?? 2000);

			// 提取 System instruction
			$system_msg = null;
			foreach ($args['messages'] as $msg) {
				if (($msg['role'] ?? '') === 'system') {
					$system_msg = $msg['content'] ?? '';
					break;
				}
			}
			if ($system_msg) {
				$builder->usingSystemInstruction($system_msg);
			}

			// JSON mode
			if (!empty($args['json_mode'])) {
				$builder->asJsonResponse();
			}

			// 执行请求
			$result = $builder->generateTextResult();

			// 提取 finish_reason
			$finish_reason = '';
			$candidates = $result->getCandidates();
			if (!empty($candidates)) {
				$fr = $candidates[0]->getFinishReason();
				if ($fr !== null) {
					$finish_reason = is_object($fr) && property_exists($fr, 'value') ? $fr->value : (string) $fr;
				}
			}

			return [
				'content'       => $result->toText(),
				'provider'      => $provider,
				'model'         => $model,
				'usage'         => [
					'prompt_tokens'     => $result->getTokenUsage()->getPromptTokens(),
					'completion_tokens' => $result->getTokenUsage()->getCompletionTokens(),
					'total_tokens'      => $result->getTokenUsage()->getTotalTokens(),
				],
				'finish_reason' => $finish_reason,
			];
		} catch (\InvalidArgumentException $e) {
			return new WP_Error('wpmind_sdk_invalid_args', $e->getMessage());
		} catch (\RuntimeException $e) {
			return $this->convert_exception_to_wp_error($e);
		} catch (\Exception $e) {
			return $this->convert_exception_to_wp_error($e);
		}
	}

	/**
	 * 解析 Provider 类名
	 *
	 * 先检查 WPMind 注册的 Provider，再检查 SDK 内置 Provider。
	 *
	 * @param string $provider 服务商标识
	 * @return string|null Provider 完整类名，未找到返回 null
	 */
	private function resolve_provider_class(string $provider): ?string {
		// 先检查 WPMind 注册的 Provider
		if (class_exists('WPMind\\Providers\\ProviderRegistrar')) {
			$class = \WPMind\Providers\ProviderRegistrar::getProviderClass($provider);
			if ($class) {
				return $class;
			}
		}

		// 再检查 SDK 内置 Provider
		return self::BUILTIN_PROVIDERS[$provider] ?? null;
	}

	/**
	 * 将异常转换为 WP_Error
	 *
	 * 尝试从异常消息中提取 HTTP 状态码。
	 *
	 * @param \Exception $e 异常
	 * @return WP_Error
	 */
	private function convert_exception_to_wp_error(\Exception $e): WP_Error {
		$message = $e->getMessage();
		$status = 0;

		// 尝试从异常消息中提取 HTTP 状态码
		if (preg_match('/\b(4\d{2}|5\d{2})\b/', $message, $matches)) {
			$status = (int) $matches[1];
		}

		$error_data = [];
		if ($status > 0) {
			$error_data['status'] = $status;
		}

		return new WP_Error(
			'wpmind_sdk_error',
			sprintf(__('SDK 错误: %s', 'wpmind'), $message),
			$error_data
		);
	}
}