<?php
/**
 * Embedding Service
 *
 * 处理文本嵌入向量
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Embedding Service
 *
 * @since 3.7.0
 */
class EmbeddingService extends AbstractService {

	/**
	 * 嵌入模型映射表（仅保留真实支持 embeddings 接口的服务商）。
	 */
	private const EMBED_MODELS = [
		'openai'       => 'text-embedding-3-small',
		'zhipu'        => 'embedding-2',
		'qwen'         => 'text-embedding-v2',
		'wenpai_cloud' => 'text-embedding-3-small',
	];

	/**
	 * 文本嵌入向量
	 *
	 * @since 2.6.0
	 * @param string|array $texts   要嵌入的文本.
	 * @param array        $options 选项.
	 * @return array|WP_Error
	 */
	public function embed( $texts, array $options = [] ) {
		$defaults = [
			'context'  => 'embedding',
			'model'    => 'auto',
			'provider' => 'auto',
		];
		$options  = wp_parse_args( $options, $defaults );

		$context     = (string) $options['context'];
		$input_texts = is_array( $texts ) ? $texts : [ $texts ];

		$original_model = (string) $options['model'];
		$model_is_auto  = ( 'auto' === $original_model );

		$provider            = $this->resolve_provider( (string) $options['provider'], $context );
		$supported_providers = array_values(
			array_filter(
				(array) apply_filters( 'wpmind_embedding_providers', array_keys( self::EMBED_MODELS ) ),
				'is_string'
			)
		);

		// 发上游请求前执行站内预算强控.
		$budget_check = $this->check_budget_before_request( $provider, $context );
		if ( is_wp_error( $budget_check ) ) {
			do_action( 'wpmind_error', $budget_check, 'embed', compact( 'texts', 'options' ) );
			return $budget_check;
		}
		if ( '' !== $budget_check['fallback_model'] ) {
			$original_model = $budget_check['fallback_model'];
			$model_is_auto  = false;
		}

		do_action( 'wpmind_before_request', 'embed', compact( 'texts', 'options' ), $context );

		return $this->execute_with_failover(
			'embed',
			$provider,
			$context,
			function ( string $try_provider, array $endpoint ) use ( $input_texts, $model_is_auto, $original_model, $texts, $options, $context ) {
				$embed_model = $model_is_auto
					? ( self::EMBED_MODELS[ $try_provider ] ?? 'text-embedding-3-small' )
					: $original_model;

				$base_url = $this->resolve_base_url( $try_provider );
				$api_url  = trailingslashit( $base_url ) . 'embeddings';
				$api_key  = $endpoint['api_key'];

				$start_time = microtime( true );

				$response = wp_remote_post(
					$api_url,
					[
						'headers'               => [
							'Authorization' => 'Bearer ' . $api_key,
							'Content-Type'  => 'application/json',
						],
						'body'                  => wp_json_encode(
							[
								'model' => $embed_model,
								'input' => $input_texts,
							]
						),
						'_wpmind_skip_tracking' => true,
						'timeout'               => 60,
					]
				);

				$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

				if ( is_wp_error( $response ) ) {
					$this->record_result( $try_provider, false, $latency_ms );
					return new WP_Error(
						'wpmind_embed_failed',
						sprintf( __( '嵌入请求失败: %s', 'wpmind' ), $response->get_error_message() )
					);
				}

				if ( \WPMind\Provider\WenpaiCloud::PROVIDER_ID === $try_provider ) {
					\WPMind\Provider\WenpaiCloud::update_balance_from_headers( wp_remote_retrieve_headers( $response ) );
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				$body        = wp_remote_retrieve_body( $response );
				$data        = json_decode( $body, true );

				if ( $status_code !== 200 ) {
					$this->record_result( $try_provider, false, $latency_ms );
					$error_message = is_array( $data ) ? ( $data['error']['message'] ?? __( '未知错误', 'wpmind' ) ) : __( '未知错误', 'wpmind' );
					return new WP_Error(
						'wpmind_embed_error',
						sprintf( __( '嵌入 API 错误 (%1$d): %2$s', 'wpmind' ), $status_code, $error_message )
					);
				}

				if ( ! is_array( $data ) ) {
					$this->record_result( $try_provider, false, $latency_ms );
					return new WP_Error( 'wpmind_invalid_response', __( '嵌入 API 返回了无效的响应格式', 'wpmind' ) );
				}

				$this->record_result( $try_provider, true, $latency_ms );

				$embeddings = [];
				foreach ( $data['data'] ?? [] as $item ) {
					$embeddings[] = $item['embedding'];
				}

				$prompt_tokens = max( 0, (int) ( $data['usage']['prompt_tokens'] ?? $data['usage']['total_tokens'] ?? 0 ) );
				if ( 0 === $prompt_tokens ) {
					$joined        = implode( ' ', array_map( 'strval', $input_texts ) );
					$prompt_tokens = max( 1, (int) ceil( mb_strlen( $joined ) / 3 ) );
				}

				$usage = [
					'prompt_tokens' => $prompt_tokens,
					'total_tokens'  => max( $prompt_tokens, (int) ( $data['usage']['total_tokens'] ?? $prompt_tokens ) ),
				];

				do_action(
					'wpmind_usage_record',
					$try_provider,
					$embed_model,
					$prompt_tokens,
					0,
					$latency_ms,
					$context,
					\WPMind\Provider\WenpaiCloud::payer_for( $try_provider )
				);

				do_action( 'wpmind_after_request', 'embed', $embeddings, compact( 'texts', 'options' ), $usage );

				return [
					'embeddings' => $embeddings,
					'model'      => $embed_model,
					'provider'   => $try_provider,
					'usage'      => $usage,
					'dimensions' => ! empty( $embeddings[0] ) ? count( $embeddings[0] ) : 0,
				];
			},
			$supported_providers
		);
	}
}
