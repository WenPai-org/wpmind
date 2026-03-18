<?php
/**
 * 字节跳动 Reve Image Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 字节跳动 Reve Image Provider
 */
class ReveImageProvider extends AbstractImageProvider {

	protected string $id       = 'bytedance_reve';
	protected string $name     = 'Reve Image';
	protected string $base_url = 'https://ark.cn-beijing.volces.com/api/v3/';
	protected array $models    = [ 'reve-image-v2', 'reve-image-turbo' ];

	/**
	 * {@inheritdoc}
	 */
	public function generate( string $prompt, array $options = [] ): array {
		$model = $options['model'] ?? 'reve-image-v2';
		$size  = $options['size'] ?? '1024x1024';

		$response = $this->request(
			'images/generations',
			[
				'model'  => $model,
				'prompt' => $prompt,
				'size'   => $size,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$urls = array_column( $response['data'] ?? [], 'url' );

		return [
			'success' => ! empty( $urls ),
			'urls'    => $urls,
			'url'     => $urls[0] ?? '',
			'error'   => empty( $urls ) ? '生成失败' : null,
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
