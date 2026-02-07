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
	 * 嵌入模型映射表
	 */
	private const EMBED_MODELS = [
		'openai'   => 'text-embedding-3-small',
		'deepseek' => 'text-embedding-3-small',
		'zhipu'    => 'embedding-2',
		'qwen'     => 'text-embedding-v2',
	];

	/**
	 * 文本嵌入向量
	 *
	 * @since 2.6.0
	 * @param string|array $texts   要嵌入的文本
	 * @param array        $options 选项
	 * @return array|WP_Error
	 */
	public function embed($texts, array $options = []) {
		$defaults = [
			'context'  => 'embedding',
			'model'    => 'auto',
			'provider' => 'auto',
		];
		$options = wp_parse_args($options, $defaults);

		$context = $options['context'];
		$input_texts = is_array($texts) ? $texts : [$texts];

		$original_model = $options['model'];
		$model_is_auto = ($original_model === 'auto');

		$provider = $this->resolve_provider($options['provider'], $context);

		do_action('wpmind_before_request', 'embed', compact('texts', 'options'), $context);

		return $this->execute_with_failover('embed', $provider, $context, function (string $try_provider, array $endpoint) use ($input_texts, $model_is_auto, $original_model, $texts, $options) {
			$embed_model = $model_is_auto
				? (self::EMBED_MODELS[$try_provider] ?? 'text-embedding-3-small')
				: $original_model;

			$base_url = $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '';
			$api_url = trailingslashit($base_url) . 'embeddings';
			$api_key = $endpoint['api_key'];

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
				$this->record_result($try_provider, false, $latency_ms);
				return new WP_Error('wpmind_embed_failed',
					sprintf(__('嵌入请求失败: %s', 'wpmind'), $response->get_error_message()));
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			if ($status_code !== 200) {
				$this->record_result($try_provider, false, $latency_ms);
				$error_message = $data['error']['message'] ?? __('未知错误', 'wpmind');
				return new WP_Error('wpmind_embed_error',
					sprintf(__('嵌入 API 错误 (%d): %s', 'wpmind'), $status_code, $error_message));
			}

			$this->record_result($try_provider, true, $latency_ms);

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
		});
	}
}
