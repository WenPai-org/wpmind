<?php
/**
 * Chat Service
 *
 * 处理 AI 对话和流式输出
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Chat Service
 *
 * @since 3.7.0
 */
class ChatService extends AbstractService {

	/**
	 * AI 对话（核心实现）
	 *
	 * @param string|array $messages 消息
	 * @param array        $options  选项
	 * @return array|WP_Error
	 */
	public function chat($messages, array $options = []) {
		$defaults = [
			'context'     => '',
			'system'      => '',
			'max_tokens'  => 1000,
			'temperature' => 0.7,
			'model'       => 'auto',
			'provider'    => 'auto',
			'json_mode'   => false,
			'cache_ttl'   => 0,
			'tools'       => [],
			'tool_choice' => 'auto',
		];
		$options = wp_parse_args($options, $defaults);

		$context = $options['context'];

		$normalized_messages = $this->normalize_messages($messages, $options);

		$args = [
			'messages'    => $normalized_messages,
			'max_tokens'  => $options['max_tokens'],
			'temperature' => $options['temperature'],
			'json_mode'   => $options['json_mode'],
			'tools'       => $options['tools'],
			'tool_choice' => $options['tool_choice'],
		];

		$args = apply_filters('wpmind_chat_args', $args, $context, $messages);

		$original_model = $options['model'];
		$model_is_auto = ($original_model === 'auto');

		if ($model_is_auto) {
			$model = $this->get_default_model();
		} else {
			$model = $original_model;
		}
		$model = apply_filters('wpmind_select_model', $model, $context, get_current_user_id());

		$provider = $this->resolve_provider($options['provider'], $context);
		$failover_chain = $this->get_failover_chain($provider);

		if (!empty($failover_chain) && $failover_chain[0] !== $provider) {
			do_action('wpmind_provider_failover', $provider, $failover_chain[0], $context);
		}

		if ($options['cache_ttl'] > 0) {
			$cache_key = $this->generate_cache_key('chat', $args, $provider, $model);
			$cached = get_transient($cache_key);
			if ($cached !== false) {
				return $cached;
			}
		}

		do_action('wpmind_before_request', 'chat', $args, $context);

		$result = null;
		$last_error = null;
		$tried_providers = [];
		$failover_count = count($failover_chain);

		foreach ($failover_chain as $index => $try_provider) {
			$tried_providers[] = $try_provider;
			$is_last_provider = ($index === $failover_count - 1);
			$max_retries = $is_last_provider ? 3 : 1;

			if ($model_is_auto) {
				$try_model = $this->get_current_model($try_provider);
			} else {
				$try_model = $model;
				$endpoints = $this->get_endpoints();
				$provider_models = $endpoints[$try_provider]['models'] ?? [];
				if (!empty($provider_models) && !in_array($try_model, $provider_models, true)) {
					$try_model = $this->get_current_model($try_provider);
				}
			}

			for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
				$result = $this->execute_chat_request($args, $try_provider, $try_model);

				if (!is_wp_error($result)) {
					if ($try_provider !== $provider) {
						$result['failover'] = [
							'original_provider' => $provider,
							'actual_provider'   => $try_provider,
							'tried_providers'   => $tried_providers,
						];
					}

					if ($try_model !== $model) {
						$result['model_fallback'] = true;
						$result['original_model'] = $model;
					}
					break 2;
				}

				$last_error = $result;

				$error_code = $result->get_error_code();
				if (in_array($error_code, ['wpmind_api_key_missing', 'wpmind_provider_not_found'], true)) {
					break;
				}

				$error_data = $result->get_error_data();
				$status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;

				if ($status > 0 && !\WPMind\ErrorHandler::should_retry($status)) {
					break;
				}

				if ($attempt < $max_retries) {
					$delay_ms = \WPMind\ErrorHandler::get_retry_delay($attempt + 1);
					do_action('wpmind_retry', $try_provider, $attempt + 1, $status);
					sleep((int) ($delay_ms / 1000));
				}
			}
		}

		if (is_wp_error($result)) {
			do_action('wpmind_error', $result, 'chat', $args);
			return $result;
		}

		$result = apply_filters('wpmind_chat_response', $result, $args, $context);

		$usage = $result['usage'] ?? [];
		do_action('wpmind_after_request', 'chat', $result, $args, $usage);

		if ($options['cache_ttl'] > 0 && !is_wp_error($result)) {
			set_transient($cache_key, $result, $options['cache_ttl']);
		}

		return $result;
	}

	/**
	 * 流式输出
	 *
	 * @since 2.6.0
	 * @param array|string $messages 消息
	 * @param callable     $callback 回调函数
	 * @param array        $options  选项
	 * @return bool|WP_Error
	 */
	public function stream($messages, callable $callback, array $options = []) {
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

		$original_model = $options['model'];
		$model_is_auto = ($original_model === 'auto');

		$provider = $this->resolve_provider($options['provider'], $context);
		$failover_chain = $this->get_failover_chain($provider);

		if (!empty($failover_chain) && $failover_chain[0] !== $provider) {
			do_action('wpmind_provider_failover', $provider, $failover_chain[0], $context);
		}

		do_action('wpmind_before_request', 'stream', compact('messages', 'options'), $context);

		$endpoints = $this->get_endpoints();
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

			$model = $model_is_auto
				? $this->get_current_model($try_provider)
				: $original_model;

			if ($model === 'auto' || $model === 'default') {
				$model = $endpoint['models'][0] ?? 'gpt-3.5-turbo';
			}

			$base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
			$api_url = trailingslashit($base_url) . 'chat/completions';

			$request_body = [
				'model'       => $model,
				'messages'    => $normalized_messages,
				'max_tokens'  => $options['max_tokens'],
				'temperature' => $options['temperature'],
				'stream'      => true,
			];

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
				$this->record_result($try_provider, false, $latency_ms);

				$last_error = new WP_Error('wpmind_stream_failed',
					sprintf(__('服务商 %s 无法建立流式连接', 'wpmind'), $try_provider));
				continue;
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

			$latency_ms = (int)((microtime(true) - $start_time) * 1000);
			$this->record_result($try_provider, true, $latency_ms);

			do_action('wpmind_after_request', 'stream', ['content' => $full_content], compact('messages', 'options'), []);

			return true;
		}

		if ($last_error) {
			do_action('wpmind_error', $last_error, 'stream', compact('messages', 'options'));
			return $last_error;
		}

		return new WP_Error('wpmind_stream_failed', __('无法建立流式连接', 'wpmind'));
	}

	/**
	 * 标准化消息格式
	 *
	 * @param array|string $messages 原始消息
	 * @param array        $options  选项
	 * @return array
	 */
	public function normalize_messages($messages, array $options): array {
		if (is_string($messages)) {
			$normalized = [];

			if (!empty($options['system'])) {
				$normalized[] = [
					'role'    => 'system',
					'content' => $options['system'],
				];
			}

			$normalized[] = [
				'role'    => 'user',
				'content' => $messages,
			];

			return $normalized;
		}

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
		if (!class_exists('\\WPMind\\SDK\\SDKAdapter')) {
			return false;
		}

		if (!class_exists('WordPress\\AiClient\\AiClient')) {
			return false;
		}

		if (!get_option('wpmind_sdk_enabled', true)) {
			return false;
		}

		if (!empty($args['tools'])) {
			return false;
		}

		$sdk_providers = apply_filters('wpmind_sdk_providers', ['anthropic', 'google']);
		return in_array($provider, $sdk_providers, true);
	}

	/**
	 * 执行 Chat 请求（路由方法）
	 *
	 * @since 3.6.0
	 * @param array  $args     请求参数
	 * @param string $provider 服务商
	 * @param string $model    模型
	 * @return array|WP_Error
	 */
	private function execute_chat_request(array $args, string $provider, string $model) {
		if ($this->should_use_sdk($provider, $args)) {
			$sdk = new \WPMind\SDK\SDKAdapter();
			$start_time = microtime(true);
			$result = $sdk->chat($args, $provider, $model);
			$latency_ms = (int)((microtime(true) - $start_time) * 1000);

			if (!is_wp_error($result)) {
				$this->record_result($provider, true, $latency_ms);
				return $result;
			}

			$error_code = $result->get_error_code();

			if ($error_code === 'wpmind_sdk_invalid_args' || $error_code === 'wpmind_sdk_unavailable') {
				do_action('wpmind_sdk_fallback', $provider, $error_code, $result->get_error_message());
			} else {
				$this->record_result($provider, false, $latency_ms);
				return $result;
			}
		}

		return $this->execute_chat_request_native($args, $provider, $model);
	}

	/**
	 * 通过原生 HTTP 执行 chat 请求
	 *
	 * @since 3.6.0
	 * @param array  $args     请求参数
	 * @param string $provider 服务商
	 * @param string $model    模型
	 * @return array|WP_Error
	 */
	private function execute_chat_request_native(array $args, string $provider, string $model) {
		$endpoints = $this->get_endpoints();

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

		if ($model === 'auto' || empty($model) || $model === 'default') {
			$model = $endpoint['models'][0] ?? 'gpt-3.5-turbo';
		}

		$request_body = [
			'model'       => $model,
			'messages'    => $args['messages'],
			'max_tokens'  => $args['max_tokens'],
			'temperature' => $args['temperature'],
		];

		if (!empty($args['json_mode'])) {
			$request_body['response_format'] = ['type' => 'json_object'];
		}

		if (!empty($args['tools'])) {
			$request_body['tools'] = $args['tools'];
			if (!empty($args['tool_choice']) && $args['tool_choice'] !== 'auto') {
				$request_body['tool_choice'] = $args['tool_choice'];
			}
		}

		$base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
		$api_url = trailingslashit($base_url) . 'chat/completions';

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

		if (is_wp_error($response)) {
			$this->record_result($provider, false, $latency_ms);

			return new WP_Error(
				'wpmind_request_failed',
				sprintf(__('请求失败: %s', 'wpmind'), $response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code !== 200) {
			$this->record_result($provider, false, $latency_ms);

			$error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
			return new WP_Error(
				'wpmind_api_error',
				sprintf(__('API 错误 (%d): %s', 'wpmind'), $status_code, $error_message),
				['status' => $status_code, 'body' => substr((string) $body, 0, 500)]
			);
		}

		if (!is_array($data)) {
			$this->record_result($provider, false, $latency_ms);

			return new WP_Error(
				'wpmind_invalid_response',
				__('服务商返回了无效的响应格式', 'wpmind'),
				['status' => $status_code, 'body' => substr((string) $body, 0, 500)]
			);
		}

		$this->record_result($provider, true, $latency_ms);

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
	public function parse_chat_response(array $response, string $provider, string $model): array {
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

		if (isset($message['content'])) {
			$content = $message['content'];
		} elseif (isset($response['content'][0]['text'])) {
			$content = $response['content'][0]['text'];
		}

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

		if (!empty($tool_calls)) {
			$result['tool_calls'] = $tool_calls;
		}

		return $result;
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
		return $response;
	}

	/**
	 * 公共访问器：获取当前模型（供 Facade 的 get_status() 使用）
	 *
	 * @param string $provider 服务商
	 * @return string
	 */
	public function get_current_model_public(string $provider): string {
		return $this->get_current_model($provider);
	}
}
