<?php
/**
 * OpenAI GPT-Image Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI GPT-Image Provider
 */
class OpenAIImageProvider extends AbstractImageProvider {

	protected string $id       = 'openai_gpt_image';
	protected string $name     = 'GPT-Image 1.5';
	protected string $base_url = 'https://api.openai.com/v1/';
	protected array $models    = [ 'gpt-image-1.5', 'dall-e-3' ];

	/**
	 * {@inheritdoc}
	 */
	public function generate( string $prompt, array $options = [] ): array {
		$model = $options['model'] ?? 'dall-e-3';
		$size  = $options['size'] ?? '1024x1024';
		$n     = $options['n'] ?? 1;

		$response = $this->request(
			'images/generations',
			[
				'model'  => $model,
				'prompt' => $prompt,
				'n'      => $n,
				'size'   => $size,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$urls = array_map(
			function ( $item ) {
				return $item['url'] ?? '';
			},
			$response['data'] ?? []
		);

		return [
			'success' => true,
			'urls'    => $urls,
			'url'     => $urls[0] ?? '',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function testConnection(): array {
		$response = wp_remote_get(
			$this->base_url . 'models',
			[
				'timeout' => 30,
				'headers' => $this->getHeaders(),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( $status === 200 ) {
			return [
				'success' => true,
				'message' => '连接成功',
			];
		}
		if ( $status === 401 ) {
			return [
				'success' => false,
				'message' => 'API Key 无效',
			];
		}

		return [
			'success' => false,
			'message' => '连接失败 (HTTP ' . $status . ')',
		];
	}
}
