<?php
/**
 * Structured Output Service
 *
 * 处理结构化输出（JSON Schema）和批量处理
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Structured Output Service
 *
 * @since 3.7.0
 */
class StructuredOutputService extends AbstractService {

	private ChatService $chat_service;

	public function __construct( ChatService $chat_service ) {
		$this->chat_service = $chat_service;
	}

	/**
	 * 结构化输出（JSON Schema）
	 *
	 * @since 2.6.0
	 * @param array|string $messages 消息
	 * @param array        $schema   JSON Schema
	 * @param array        $options  选项
	 * @return array|WP_Error
	 */
	public function structured( $messages, array $schema, array $options = [] ) {
		$defaults = [
			'context'     => 'structured',
			'max_tokens'  => 2000,
			'temperature' => 0.3,
			'retries'     => 3,
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		$schema_json   = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		$schema_prompt = "你必须返回严格符合以下 JSON Schema 的 JSON 对象。不要返回其他内容，只返回 JSON：\n\n```json\n{$schema_json}\n```";

		if ( is_string( $messages ) ) {
			$messages = [
				[
					'role'    => 'system',
					'content' => $schema_prompt,
				],
				[
					'role'    => 'user',
					'content' => $messages,
				],
			];
		} else {
			array_unshift(
				$messages,
				[
					'role'    => 'system',
					'content' => $schema_prompt,
				]
			);
		}

		$max_retries = $options['retries'];
		$last_error  = null;

		for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
			$result = $this->chat_service->chat(
				$messages,
				[
					'context'     => $context,
					'max_tokens'  => $options['max_tokens'],
					'temperature' => $options['temperature'],
					'json_mode'   => true,
					'cache_ttl'   => 0,
				]
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$content = $result['content'];
			$parsed  = json_decode( $content, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				if ( $this->validate_schema( $parsed, $schema ) ) {
					return [
						'data'     => $parsed,
						'provider' => $result['provider'],
						'model'    => $result['model'],
						'usage'    => $result['usage'],
						'attempts' => $attempt,
					];
				}
			}

			$last_error = json_last_error_msg();

			$messages[] = [
				'role'    => 'assistant',
				'content' => $content,
			];
			$messages[] = [
				'role'    => 'user',
				'content' => "JSON 解析失败或不符合 Schema: {$last_error}。请重新生成严格符合 Schema 的 JSON。",
			];
		}

		return new WP_Error(
			'wpmind_structured_failed',
			sprintf( __( '结构化输出失败（尝试 %1$d 次）: %2$s', 'wpmind' ), $max_retries, $last_error )
		);
	}

	/**
	 * 批量处理
	 *
	 * @since 2.6.0
	 * @param array  $items          要处理的项目数组
	 * @param string $prompt_template Prompt 模板
	 * @param array  $options         选项
	 * @return array|WP_Error
	 */
	public function batch( array $items, string $prompt_template, array $options = [] ) {
		$defaults = [
			'context'       => 'batch',
			'max_tokens'    => 500,
			'temperature'   => 0.7,
			'concurrency'   => 1,
			'delay_ms'      => 100,
			'stop_on_error' => false,
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];
		$results = [];
		$errors  = [];

		do_action( 'wpmind_before_request', 'batch', compact( 'items', 'prompt_template', 'options' ), $context );

		foreach ( $items as $index => $item ) {
			$item_str = is_array( $item ) ? wp_json_encode( $item, JSON_UNESCAPED_UNICODE ) : (string) $item;
			$prompt   = str_replace( '{{item}}', $item_str, $prompt_template );
			$prompt   = str_replace( '{{index}}', (string) $index, $prompt );

			$result = $this->chat_service->chat(
				$prompt,
				[
					'context'     => $context . '_item_' . $index,
					'max_tokens'  => $options['max_tokens'],
					'temperature' => $options['temperature'],
					'cache_ttl'   => 0,
				]
			);

			if ( is_wp_error( $result ) ) {
				$errors[ $index ] = $result->get_error_message();
				if ( $options['stop_on_error'] ) {
					break;
				}
				$results[ $index ] = null;
			} else {
				$results[ $index ] = [
					'content' => $result['content'],
					'usage'   => $result['usage'],
				];
			}

			if ( $options['delay_ms'] > 0 && $index < count( $items ) - 1 ) {
				usleep( $options['delay_ms'] * 1000 );
			}
		}

		$total_tokens = array_sum(
			array_map(
				function ( $r ) {
					return $r['usage']['total_tokens'] ?? 0;
				},
				array_filter( $results )
			)
		);

		do_action( 'wpmind_after_request', 'batch', $results, compact( 'items', 'options' ), [ 'total_tokens' => $total_tokens ] );

		return [
			'results'       => $results,
			'errors'        => $errors,
			'total_items'   => count( $items ),
			'success_count' => count( array_filter( $results ) ),
			'error_count'   => count( $errors ),
			'total_tokens'  => $total_tokens,
		];
	}

	/**
	 * 验证 JSON Schema（简化版）
	 *
	 * @param array $data   数据
	 * @param array $schema Schema
	 * @return bool
	 */
	public function validate_schema( array $data, array $schema ): bool {
		if ( isset( $schema['required'] ) ) {
			foreach ( $schema['required'] as $field ) {
				if ( ! isset( $data[ $field ] ) ) {
					return false;
				}
			}
		}

		if ( isset( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $prop ) {
				if ( ! isset( $data[ $key ] ) ) {
					continue;
				}

				$value = $data[ $key ];
				$type  = $prop['type'] ?? null;

				if ( $type === 'string' && ! is_string( $value ) ) {
					return false;
				}
				if ( $type === 'integer' && ! is_int( $value ) ) {
					return false;
				}
				if ( $type === 'number' && ! is_numeric( $value ) ) {
					return false;
				}
				if ( $type === 'boolean' && ! is_bool( $value ) ) {
					return false;
				}
				if ( $type === 'array' && ! is_array( $value ) ) {
					return false;
				}
				if ( $type === 'object' && ! is_array( $value ) ) {
					return false;
				}
			}
		}

		return true;
	}
}
