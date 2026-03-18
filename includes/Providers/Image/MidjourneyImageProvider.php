<?php
/**
 * Midjourney Image Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * Midjourney Provider (通过第三方 API)
 */
class MidjourneyImageProvider extends AbstractImageProvider {

	protected string $id       = 'midjourney';
	protected string $name     = 'Midjourney v7';
	protected string $base_url = 'https://api.midjourney.com/v1/';
	protected array $models    = [ 'midjourney-v7', 'midjourney-v6.1' ];
	protected int $timeout     = 300; // Midjourney 需要更长的超时时间

	/**
	 * {@inheritdoc}
	 */
	public function generate( string $prompt, array $options = [] ): array {
		// 提交任务
		$response = $this->request(
			'imagine',
			[
				'prompt' => $prompt,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$task_id = $response['taskId'] ?? $response['id'] ?? null;

		if ( ! $task_id ) {
			return [
				'success' => false,
				'error'   => '无法获取任务 ID',
			];
		}

		// 轮询任务状态
		$max_attempts = 120; // 最多等待 4 分钟
		$attempt      = 0;

		while ( $attempt < $max_attempts ) {
			sleep( 2 );
			++$attempt;

			$status_response = $this->request( 'task/' . $task_id . '/fetch', [], 'GET' );

			if ( is_wp_error( $status_response ) ) {
				continue;
			}

			$status = $status_response['status'] ?? '';

			if ( $status === 'SUCCESS' || $status === 'COMPLETED' ) {
				$url = $status_response['imageUrl'] ?? $status_response['result']['url'] ?? '';

				return [
					'success' => ! empty( $url ),
					'urls'    => [ $url ],
					'url'     => $url,
				];
			}

			if ( $status === 'FAILED' || $status === 'ERROR' ) {
				return [
					'success' => false,
					'error'   => $status_response['failReason'] ?? '生成失败',
				];
			}
		}

		return [
			'success' => false,
			'error'   => '任务超时',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function testConnection(): array {
		$response = wp_remote_get(
			$this->base_url . 'status',
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
