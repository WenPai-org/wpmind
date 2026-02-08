<?php
/**
 * Response Transformer
 *
 * Converts WPMind internal results into OpenAI-compatible response formats.
 *
 * @package WPMind\Modules\ApiGateway\Transform
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Transform;

/**
 * Class ResponseTransformer
 *
 * Transforms WPMind PublicAPI results into OpenAI-compatible
 * JSON response structures.
 */
final class ResponseTransformer {

	/**
	 * Transform a chat result into OpenAI chat.completion format.
	 *
	 * @param mixed  $result     Result from PublicAPI::chat().
	 * @param string $model      The model identifier from the request.
	 * @param string $request_id Unique request ID.
	 * @return array OpenAI-compatible chat completion response.
	 */
	public function transform_chat( mixed $result, string $model, string $request_id ): array {
		$content = $this->extract_content( $result );

		return [
			'id'      => 'wpmind-' . $request_id,
			'object'  => 'chat.completion',
			'created' => time(),
			'model'   => $model,
			'choices' => [
				[
					'index'         => 0,
					'message'       => [
						'role'    => 'assistant',
						'content' => $content,
					],
					'finish_reason' => 'stop',
				],
			],
			'usage'   => $this->extract_usage( $result ),
		];
	}

	/**
	 * Transform an embedding result into OpenAI embedding format.
	 *
	 * @param mixed  $result     Result from PublicAPI::embed().
	 * @param string $model      The model identifier from the request.
	 * @param string $request_id Unique request ID.
	 * @return array OpenAI-compatible embedding response.
	 */
	public function transform_embedding( mixed $result, string $model, string $request_id ): array {
		$embeddings = $this->extract_embeddings( $result );
		$data       = [];

		foreach ( $embeddings as $index => $embedding ) {
			$data[] = [
				'object'    => 'embedding',
				'index'     => $index,
				'embedding' => $embedding,
			];
		}

		return [
			'object' => 'list',
			'data'   => $data,
			'model'  => $model,
			'usage'  => [
				'prompt_tokens' => 0,
				'total_tokens'  => 0,
			],
		];
	}

	/**
	 * Transform a models list into OpenAI models list format.
	 *
	 * @param string[] $models List of model identifiers.
	 * @return array OpenAI-compatible models list response.
	 */
	public function transform_models( array $models ): array {
		$data = [];

		foreach ( $models as $model_id ) {
			$data[] = [
				'id'       => $model_id,
				'object'   => 'model',
				'created'  => 0,
				'owned_by' => 'wpmind',
			];
		}

		return [
			'object' => 'list',
			'data'   => $data,
		];
	}

	/**
	 * Extract text content from a WPMind result.
	 *
	 * The result may be a plain string, or an array with a 'content' key,
	 * or an array with nested 'choices'.
	 *
	 * @param mixed $result WPMind API result.
	 * @return string Extracted content.
	 */
	private function extract_content( mixed $result ): string {
		if ( is_string( $result ) ) {
			return $result;
		}

		if ( is_array( $result ) ) {
			if ( isset( $result['content'] ) && is_string( $result['content'] ) ) {
				return $result['content'];
			}

			if ( isset( $result['choices'][0]['message']['content'] ) ) {
				return (string) $result['choices'][0]['message']['content'];
			}

			if ( isset( $result['text'] ) && is_string( $result['text'] ) ) {
				return $result['text'];
			}
		}

		return '';
	}

	/**
	 * Extract usage data from a WPMind result.
	 *
	 * @param mixed $result WPMind API result.
	 * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
	 */
	private function extract_usage( mixed $result ): array {
		$default = [
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
		];

		if ( ! is_array( $result ) || ! isset( $result['usage'] ) ) {
			return $default;
		}

		$usage = $result['usage'];

		return [
			'prompt_tokens'     => (int) ( $usage['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $usage['completion_tokens'] ?? 0 ),
			'total_tokens'      => (int) ( $usage['total_tokens'] ?? 0 ),
		];
	}

	/**
	 * Extract embedding vectors from a WPMind result.
	 *
	 * @param mixed $result WPMind API result.
	 * @return array<int, array<float>> List of embedding vectors.
	 */
	private function extract_embeddings( mixed $result ): array {
		if ( ! is_array( $result ) ) {
			return [];
		}

		// Result is already a list of vectors.
		if ( isset( $result[0] ) && is_array( $result[0] ) ) {
			return $result;
		}

		// Result has a 'data' key with embeddings.
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$vectors = [];
			foreach ( $result['data'] as $item ) {
				if ( isset( $item['embedding'] ) ) {
					$vectors[] = $item['embedding'];
				} elseif ( is_array( $item ) && isset( $item[0] ) ) {
					$vectors[] = $item;
				}
			}
			return $vectors;
		}

		// Result has an 'embedding' key (single vector).
		if ( isset( $result['embedding'] ) && is_array( $result['embedding'] ) ) {
			return [ $result['embedding'] ];
		}

		return [];
	}
}
