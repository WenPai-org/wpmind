<?php
/**
 * 文派心思官方算力图像生成 Provider
 *
 * 通过官方托管网关（mind-api.wenpai.net/v1/images/generations）生成图像，
 * 自动携带站点派生/独立授权令牌并同步 X-Mind-Credits-* 点数余额响应头。
 *
 * @package WPMind
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

use WPMind\Provider\WenpaiCloud;

defined( 'ABSPATH' ) || exit;

/**
 * 文派心思官方算力图像生成 Provider
 */
class WenpaiCloudImageProvider extends AbstractImageProvider {

	/**
	 * Provider ID.
	 *
	 * @var string
	 */
	protected string $id = WenpaiCloud::PROVIDER_ID;

	/**
	 * Provider display name.
	 *
	 * @var string
	 */
	protected string $name = '文派心思官方算力';

	/**
	 * Default Mind gateway base URL.
	 *
	 * @var string
	 */
	protected string $base_url = 'https://mind-api.wenpai.net/v1/';

	/**
	 * Supported image models.
	 *
	 * @var array<int, string>
	 */
	protected array $models = [ 'gpt-image-1', 'wanx-v1' ];

	/**
	 * 构造函数。
	 *
	 * @param string $api_key    当前站点授权令牌（可留空，调用时动态派生）.
	 * @param string $custom_url 自定义官方网关地址（可留空）.
	 */
	public function __construct( string $api_key = '', string $custom_url = '' ) {
		$effective_key = '' !== trim( $api_key ) ? trim( $api_key ) : WenpaiCloud::derive_request_token();
		$effective_url = '' !== trim( $custom_url ) ? trim( $custom_url ) : WenpaiCloud::base_url();
		parent::__construct( $effective_key, $effective_url );
	}

	/**
	 * 生成图像。
	 *
	 * @param string               $prompt  图像提示词.
	 * @param array<string, mixed> $options 生成选项（model/size/n）.
	 * @return array<string, mixed>
	 */
	public function generate( string $prompt, array $options = [] ): array {
		$token = WenpaiCloud::derive_request_token();
		if ( '' !== $token ) {
			$this->api_key = $token;
		}
		if ( '' === $this->api_key ) {
			return [
				'success' => false,
				'error'   => __( '心思 AI 尚未绑定站点授权令牌', 'wpmind' ),
			];
		}

		$model = isset( $options['model'] ) && is_string( $options['model'] ) && '' !== trim( $options['model'] )
			? trim( $options['model'] )
			: 'auto';
		$size  = isset( $options['size'] ) && is_string( $options['size'] ) && '' !== trim( $options['size'] )
			? trim( $options['size'] )
			: '1024x1024';
		$n     = isset( $options['n'] ) ? max( 1, (int) $options['n'] ) : 1;

		$url      = $this->base_url . 'images/generations';
		$response = wp_remote_post(
			$url,
			[
				'timeout'               => $this->timeout,
				'headers'               => $this->getHeaders(),
				'body'                  => wp_json_encode(
					[
						'model'  => $model,
						'prompt' => $prompt,
						'n'      => $n,
						'size'   => $size,
					]
				),
				'_wpmind_skip_tracking' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		WenpaiCloud::update_balance_from_headers( wp_remote_retrieve_headers( $response ) );

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_json   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$error_msg = is_array( $body_json )
				? (string) ( $body_json['error']['message'] ?? $body_json['message'] ?? __( '图像生成失败', 'wpmind' ) )
				: __( '图像生成失败', 'wpmind' );
			return [
				'success' => false,
				'error'   => $error_msg,
				'status'  => $status_code,
			];
		}

		$data = is_array( $body_json['data'] ?? null ) ? $body_json['data'] : [];
		$urls = [];
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
				$urls[] = $item['url'];
			} elseif ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
				$urls[] = 'data:image/png;base64,' . $item['b64_json'];
			}
		}

		return [
			'success' => ! empty( $urls ),
			'urls'    => $urls,
			'url'     => $urls[0] ?? '',
			'error'   => empty( $urls ) ? __( '网关未返回有效图像结果', 'wpmind' ) : '',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function testConnection(): array {
		$res = WenpaiCloud::refresh_balance();
		if ( is_wp_error( $res ) ) {
			return [
				'success' => false,
				'message' => $res->get_error_message(),
			];
		}
		return [
			'success' => true,
			'message' => __( '心思 AI 网关连接正常', 'wpmind' ),
		];
	}
}
