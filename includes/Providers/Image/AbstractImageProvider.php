<?php
/**
 * 抽象图像 Provider 基类
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 抽象图像 Provider 基类
 */
abstract class AbstractImageProvider implements ImageProviderInterface {

    /**
     * Provider ID
     *
     * @var string
     */
    protected string $id;

    /**
     * Provider 名称
     *
     * @var string
     */
    protected string $name;

    /**
     * API Key
     *
     * @var string
     */
    protected string $api_key;

    /**
     * Base URL
     *
     * @var string
     */
    protected string $base_url;

    /**
     * 支持的模型
     *
     * @var array
     */
    protected array $models = [];

    /**
     * 请求超时时间（秒）
     *
     * @var int
     */
    protected int $timeout = 120;

    /**
     * 构造函数
     *
     * @param string $api_key API Key
     * @param string $custom_url 自定义 URL（可选）
     */
    public function __construct( string $api_key, string $custom_url = '' ) {
        $this->api_key = $api_key;
        if ( ! empty( $custom_url ) ) {
            $this->base_url = rtrim( $custom_url, '/' ) . '/';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getModels(): array {
        return $this->models;
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $endpoint API 端点
     * @param array  $body 请求体
     * @param string $method 请求方法
     * @return array|\WP_Error
     */
    protected function request( string $endpoint, array $body = [], string $method = 'POST' ) {
        $url = $this->base_url . ltrim( $endpoint, '/' );

        $args = [
            'method'  => $method,
            'timeout' => $this->timeout,
            'headers' => $this->getHeaders(),
        ];

        if ( ! empty( $body ) && $method === 'POST' ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $body_json   = json_decode( $body_raw, true );

        if ( $status_code >= 400 ) {
            $error_msg = $body_json['error']['message'] ?? $body_json['message'] ?? '请求失败';
            return new \WP_Error( 'api_error', $error_msg, [ 'status' => $status_code ] );
        }

        return $body_json;
    }

    /**
     * 获取请求头
     *
     * @return array
     */
    protected function getHeaders(): array {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ];
    }
}
