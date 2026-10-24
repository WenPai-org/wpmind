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
use WPMind\Provider\Health\ErrorClass;
use WPMind\Provider\Health\ErrorClassifier;
use WPMind\Provider\ProviderClient;

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
	public function chat( $messages, array $options = [] ) {
		$defaults = [
			'context'            => '',
			'system'             => '',
			'max_tokens'         => 1000,
			'temperature'        => 0.7,
			'model'              => 'auto',
			'provider'           => 'auto',
			'json_mode'          => false,
			'cache_ttl'          => 0,
			'tools'              => [],
			'tool_choice'        => 'auto',
			'failover_providers' => [],
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		$normalized_messages = $this->normalize_messages( $messages, $options );

		$args = [
			'messages'    => $normalized_messages,
			'max_tokens'  => $options['max_tokens'],
			'temperature' => $options['temperature'],
			'json_mode'   => $options['json_mode'],
			'tools'       => $options['tools'],
			'tool_choice' => $options['tool_choice'],
		];

		$args = apply_filters( 'wpmind_chat_args', $args, $context, $messages );

		$original_model = $options['model'];
		$model_is_auto  = ( $original_model === 'auto' );

		if ( $model_is_auto ) {
			$model = $this->get_default_model();
		} else {
			$model = $original_model;
		}
		$model = apply_filters( 'wpmind_select_model', $model, $context, get_current_user_id() );

		$provider       = $this->resolve_provider( $options['provider'], $context );
		$failover_chain = $this->get_failover_chain( $provider );

		// Filter failover chain to only supported providers (e.g. vision-capable).
		if ( ! empty( $options['failover_providers'] ) ) {
			$allowed        = $options['failover_providers'];
			$failover_chain = array_values(
				array_filter(
					$failover_chain,
					function ( $p ) use ( $allowed ) {
						return in_array( $p, $allowed, true );
					}
				)
			);

			if ( empty( $failover_chain ) ) {
				return new WP_Error(
					'wpmind_no_supported_provider',
					__( '没有可用的服务商支持此请求类型', 'wpmind' )
				);
			}
		}

		if ( ! empty( $failover_chain ) && $failover_chain[0] !== $provider ) {
			do_action( 'wpmind_provider_failover', $provider, $failover_chain[0], $context, 'preferred_unavailable' );
		}

		$cache_key    = $this->generate_cache_key( 'chat', $args, $provider, $model );
		$cache_lookup = $this->get_cached_value( $cache_key, (int) $options['cache_ttl'] );
		if ( $cache_lookup['hit'] ) {
			return $cache_lookup['value'];
		}

		do_action( 'wpmind_before_request', 'chat', $args, $context );

		$result          = null;
		$last_error      = null;
		$tried_providers = [];
		$attempts        = [];
		$budget_ms       = max( 1000, (int) get_option( 'wpmind_request_timeout', 60 ) * 1000 );
		$deadline        = (int) ( microtime( true ) * 1000 ) + $budget_ms;
		$hop_cap_ms      = 30000;

		foreach ( $failover_chain as $index => $try_provider ) {
			$remaining = $deadline - (int) ( microtime( true ) * 1000 );
			if ( $remaining <= 0 ) {
				break;
			}

			$tried_providers[] = $try_provider;

			if ( $model_is_auto ) {
				$try_model = $this->get_current_model( $try_provider );
			} else {
				$try_model       = $model;
				$endpoints       = $this->get_endpoints();
				$provider_models = $endpoints[ $try_provider ]['models'] ?? [];
				if ( ! empty( $provider_models ) && ! in_array( $try_model, $provider_models, true ) ) {
					$try_model = $this->get_current_model( $try_provider );
				}
			}

			$resolution = \WPMind\Provider\Catalog\ModelCatalog::instance()->resolve( $try_provider, (string) $try_model );
			$try_model  = $resolution->model;

			$provider_attempts = 0;
			$max_attempts      = 2; // 每家最多 2 次
			$model_swapped     = false;

			while ( $provider_attempts < $max_attempts ) {
				$remaining = $deadline - (int) ( microtime( true ) * 1000 );
				if ( $remaining <= 0 ) {
					break 2;
				}
				$hop_timeout = max( 1, (int) floor( min( $remaining, $hop_cap_ms ) / 1000 ) );

				++$provider_attempts;
				$result = $this->execute_chat_request( $args, $try_provider, $try_model, $hop_timeout );

				if ( ! is_wp_error( $result ) ) {
					$failover_meta = [
						'original_provider' => $provider,
						'actual_provider'   => $try_provider,
						'tried_providers'   => $tried_providers,
						'attempts'          => $attempts,
					];
					if ( $try_provider !== $provider || count( $tried_providers ) > 1 || [] !== $attempts ) {
						$result['failover'] = $failover_meta;
					}

					if ( $try_model !== $model ) {
						$result['model_fallback'] = true;
						$result['original_model'] = $model;
					}
					break 2;
				}

				$last_error = $result;
				$error_data = $result->get_error_data();
				$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 0;
				$body       = is_array( $error_data ) ? (string) ( $error_data['body'] ?? '' ) : '';
				$error_class_value = is_array( $error_data ) ? (string) ( $error_data['error_class'] ?? '' ) : '';
				$retry_after = is_array( $error_data ) ? (int) ( $error_data['retry_after'] ?? 0 ) : 0;

				if ( '' !== $error_class_value ) {
					$error_class = ErrorClass::tryFrom( $error_class_value ) ?? ErrorClass::ServerError;
				} else {
					$classified  = ErrorClassifier::classify( $result, $status, $body );
					$error_class = $classified['class'];
					$retry_after = $classified['retry_after'];
				}

				$attempts[] = [
					'provider'    => $try_provider,
					'error_class' => $error_class->value,
				];

				$error_code = $result->get_error_code();
				if ( in_array( $error_code, [ 'wpmind_api_key_missing', 'wpmind_provider_not_found' ], true ) ) {
					break;
				}

				if ( ! $error_class->should_failover() ) {
					// bad_request：不回退
					break 2;
				}

				// model_unavailable：同一家换 default_model 试 1 次
				if ( ErrorClass::ModelUnavailable === $error_class && ! $model_swapped ) {
					$default_model = $this->get_current_model( $try_provider );
					if ( $default_model !== $try_model ) {
						$try_model     = $default_model;
						$model_swapped = true;
						continue;
					}
				}

				if ( ErrorClass::RateLimited === $error_class && $retry_after > 0 && $retry_after <= 2 && $provider_attempts < $max_attempts ) {
					$remaining = $deadline - (int) ( microtime( true ) * 1000 );
					$wait_ms   = min( $retry_after * 1000, max( 0, $remaining ) );
					if ( $wait_ms > 0 ) {
						usleep( $wait_ms * 1000 );
					}
					do_action( 'wpmind_retry', $try_provider, $provider_attempts, $status );
					continue;
				}

				if ( ErrorClass::ServerError === $error_class && $provider_attempts < $max_attempts ) {
					$remaining = $deadline - (int) ( microtime( true ) * 1000 );
					if ( $remaining > 0 ) {
						do_action( 'wpmind_retry', $try_provider, $provider_attempts, $status );
						continue;
					}
				}

				break; // 换下一家
			}

			// 准备跳到下一家
			$next = $failover_chain[ $index + 1 ] ?? null;
			if ( null !== $next ) {
				$last_class = $attempts[ count( $attempts ) - 1 ]['error_class'] ?? 'server_error';
				do_action( 'wpmind_provider_failover', $try_provider, $next, $context, $last_class );
			}
		}

		if ( is_wp_error( $result ) && [] !== $attempts ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) ) {
				$data = [];
			}
			$data['failover'] = [
				'original_provider' => $provider,
				'tried_providers'   => $tried_providers,
				'attempts'          => $attempts,
			];
			$result = new WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
		}

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_error', $result, 'chat', $args );
			return $result;
		}

		$result = apply_filters( 'wpmind_chat_response', $result, $args, $context );

		$usage = $result['usage'] ?? [];
		do_action( 'wpmind_after_request', 'chat', $result, $args, $usage );

		if ( ! is_wp_error( $result ) ) {
			$this->set_cached_value(
				$cache_key,
				$result,
				(int) $options['cache_ttl'],
				[
					'type'     => 'chat',
					'context'  => $context,
					'provider' => $provider,
					'model'    => $model,
				]
			);
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
	public function stream( $messages, callable $callback, array $options = [] ) {
		$defaults = [
			'context'     => '',
			'system'      => '',
			'max_tokens'  => 2000,
			'temperature' => 0.7,
			'model'       => 'auto',
			'provider'    => 'auto',
		];
		$options  = wp_parse_args( $options, $defaults );

		$context             = $options['context'];
		$normalized_messages = $this->normalize_messages( $messages, $options );

		$original_model = $options['model'];
		$model_is_auto  = ( $original_model === 'auto' );

		$provider       = $this->resolve_provider( $options['provider'], $context );
		$failover_chain = $this->get_failover_chain( $provider );

		if ( ! empty( $failover_chain ) && $failover_chain[0] !== $provider ) {
			do_action( 'wpmind_provider_failover', $provider, $failover_chain[0], $context, 'preferred_unavailable' );
		}

		do_action( 'wpmind_before_request', 'stream', compact( 'messages', 'options' ), $context );

		$endpoints             = $this->get_endpoints();
		$last_error            = null;
		$stream_output_started = false;

		foreach ( $failover_chain as $index => $try_provider ) {
			if ( ! isset( $endpoints[ $try_provider ] ) ) {
				$last_error = new WP_Error(
					'wpmind_provider_not_found',
					sprintf( __( '服务商 %s 未配置', 'wpmind' ), $try_provider )
				);
				continue;
			}

			$endpoint = $endpoints[ $try_provider ];
			$api_key  = $endpoint['api_key'] ?? '';

			if ( empty( $api_key ) ) {
				$last_error = new WP_Error(
					'wpmind_api_key_missing',
					sprintf( __( '服务商 %s 未配置 API Key', 'wpmind' ), $try_provider )
				);
				continue;
			}

			$model = $model_is_auto
				? $this->get_current_model( $try_provider )
				: $original_model;

			if ( $model === 'auto' || $model === 'default' ) {
				$model = $this->get_current_model( $try_provider );
			}
			$model = \WPMind\Provider\Catalog\ModelCatalog::instance()->resolve( $try_provider, (string) $model )->model;

			$base_url = $this->resolve_base_url( $try_provider );
			$api_url  = trailingslashit( $base_url ) . 'chat/completions';

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
					'content' => wp_json_encode( $request_body ),
					'timeout' => 120,
				],
				'ssl'  => [
					'verify_peer' => true,
				],
			];

			$start_time = microtime( true );

			// 检查 allow_url_fopen 是否启用
			if ( ! ini_get( 'allow_url_fopen' ) ) {
				$last_error = new WP_Error(
					'wpmind_stream_unsupported',
					__( '流式输出需要 PHP allow_url_fopen 配置启用', 'wpmind' )
				);
				continue;
			}

			$stream_ctx = stream_context_create( $stream_context_options );
			$stream     = @fopen( $api_url, 'r', false, $stream_ctx );

			if ( ! $stream ) {
				$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$this->record_result( $try_provider, false, $latency_ms );

				$last_error = new WP_Error(
					'wpmind_stream_failed',
					sprintf( __( '服务商 %s 无法建立流式连接', 'wpmind' ), $try_provider )
				);
				continue; // 建连前可回退
			}

			$full_content     = '';
			$content_started  = false;

			while ( ! feof( $stream ) ) {
				$line = fgets( $stream );
				if ( empty( $line ) ) {
					continue;
				}

				$line = trim( $line );
				if ( strpos( $line, 'data: ' ) !== 0 ) {
					continue;
				}

				$data = substr( $line, 6 );
				if ( $data === '[DONE]' ) {
					break;
				}

				$json = json_decode( $data, true );
				if ( ! $json ) {
					continue;
				}

				$delta = $json['choices'][0]['delta']['content'] ?? '';
				if ( ! empty( $delta ) ) {
					$content_started        = true;
					$stream_output_started  = true;
					$full_content          .= $delta;
					call_user_func( $callback, $delta, $json );
				}
			}

			fclose( $stream );

			$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

			if ( $stream_output_started || '' !== $full_content ) {
				$this->record_result( $try_provider, true, $latency_ms );
				do_action( 'wpmind_after_request', 'stream', [ 'content' => $full_content ], compact( 'messages', 'options' ), [] );
				return true;
			}

			// 已建连但未出字：记失败；若本家曾出字则不回退（上面已 return）。
			$this->record_result( $try_provider, false, $latency_ms );
			$last_error = new WP_Error(
				'wpmind_stream_empty',
				sprintf( __( '服务商 %s 流式响应为空', 'wpmind' ), $try_provider )
			);
			continue;
		}

		if ( $last_error ) {
			do_action( 'wpmind_error', $last_error, 'stream', compact( 'messages', 'options' ) );
			return $last_error;
		}

		return new WP_Error( 'wpmind_stream_failed', __( '无法建立流式连接', 'wpmind' ) );
	}

	/**
	 * 标准化消息格式
	 *
	 * @param array|string $messages 原始消息
	 * @param array        $options  选项
	 * @return array
	 */
	public function normalize_messages( $messages, array $options ): array {
		if ( is_string( $messages ) ) {
			$normalized = [];

			if ( ! empty( $options['system'] ) ) {
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
	private function should_use_sdk( string $provider, array $args ): bool {
		if ( ! class_exists( '\\WPMind\\SDK\\SDKAdapter' ) ) {
			return false;
		}

		if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
			return false;
		}

		if ( ! get_option( 'wpmind_sdk_enabled', true ) ) {
			return false;
		}

		if ( ! empty( $args['tools'] ) ) {
			return false;
		}

		$sdk_providers = apply_filters( 'wpmind_sdk_providers', [ 'anthropic', 'google' ] );
		return in_array( $provider, $sdk_providers, true );
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
	private function execute_chat_request( array $args, string $provider, string $model, int $timeout = 0 ) {
		if ( $this->should_use_sdk( $provider, $args ) ) {
			$sdk        = new \WPMind\SDK\SDKAdapter();
			$start_time = microtime( true );
			$result     = $sdk->chat( $args, $provider, $model );
			$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

			if ( ! is_wp_error( $result ) ) {
				$this->record_result( $provider, true, $latency_ms );
				return $result;
			}

			$error_code = $result->get_error_code();

			if ( $error_code === 'wpmind_sdk_invalid_args' || $error_code === 'wpmind_sdk_unavailable' ) {
				do_action( 'wpmind_sdk_fallback', $provider, $error_code, $result->get_error_message() );
			} else {
				$this->record_result( $provider, false, $latency_ms );
				return $result;
			}
		}

		return $this->execute_chat_request_native( $args, $provider, $model, $timeout );
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
	private function execute_chat_request_native( array $args, string $provider, string $model, int $timeout = 0 ) {
		$endpoints = $this->get_endpoints();

		if ( ! isset( $endpoints[ $provider ] ) ) {
			return new WP_Error(
				'wpmind_provider_not_found',
				sprintf( __( '服务商 %s 未配置', 'wpmind' ), $provider )
			);
		}

		$endpoint = $endpoints[ $provider ];
		$api_key  = $endpoint['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'wpmind_api_key_missing',
				sprintf( __( '服务商 %s 未配置 API Key', 'wpmind' ), $provider ),
				[ 'error_class' => ErrorClass::AuthFailed->value ]
			);
		}

		if ( $model === 'auto' || empty( $model ) || $model === 'default' ) {
			$model = $this->get_current_model( $provider );
		}
		$model = \WPMind\Provider\Catalog\ModelCatalog::instance()->resolve( $provider, (string) $model )->model;

		$request_body = [
			'model'       => $model,
			'messages'    => $args['messages'],
			'max_tokens'  => $args['max_tokens'],
			'temperature' => $args['temperature'],
		];

		if ( ! empty( $args['json_mode'] ) ) {
			$request_body['response_format'] = [ 'type' => 'json_object' ];
		}

		if ( ! empty( $args['tools'] ) ) {
			$request_body['tools'] = $args['tools'];
			if ( ! empty( $args['tool_choice'] ) && $args['tool_choice'] !== 'auto' ) {
				$request_body['tool_choice'] = $args['tool_choice'];
			}
		}

		if ( $timeout <= 0 ) {
			$timeout = (int) get_option( 'wpmind_request_timeout', 60 );
		}

		$client = new ProviderClient();
		$result = $client->request(
			$provider,
			'POST',
			'chat/completions',
			[
				'body'    => $request_body,
				'timeout' => $timeout,
				'model'   => $model,
			]
		);

		if ( ! $result['ok'] ) {
			return $result['wp_error'];
		}

		if ( ! is_array( $result['data'] ) ) {
			return new WP_Error(
				'wpmind_invalid_response',
				__( '服务商返回了无效的响应格式', 'wpmind' ),
				[
					'status'      => $result['status'],
					'body'        => ErrorClassifier::safe_body( (string) $result['body'] ),
					'error_class' => ErrorClass::ServerError->value,
				]
			);
		}

		return $this->parse_chat_response( $result['data'], $provider, $model );
	}

	public function parse_chat_response( array $response, string $provider, string $model ): array {
		$content       = '';
		$tool_calls    = [];
		$finish_reason = '';
		$usage         = [
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
		];

		$message       = $response['choices'][0]['message'] ?? [];
		$finish_reason = $response['choices'][0]['finish_reason'] ?? '';

		if ( isset( $message['content'] ) ) {
			$content = $message['content'];
		} elseif ( isset( $response['content'][0]['text'] ) ) {
			$content = $response['content'][0]['text'];
		}

		if ( isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
			foreach ( $message['tool_calls'] as $call ) {
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

		if ( isset( $response['usage'] ) ) {
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

		if ( ! empty( $tool_calls ) ) {
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
	public function filter_chat_response( array $response, array $args, string $context ): array {
		return $response;
	}

	/**
	 * 公共访问器：获取当前模型（供 Facade 的 get_status() 使用）
	 *
	 * @param string $provider 服务商
	 * @return string
	 */
	public function get_current_model_public( string $provider ): string {
		return $this->get_current_model( $provider );
	}
}
