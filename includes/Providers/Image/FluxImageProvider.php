<?php
/**
 * Flux (Black Forest Labs) Image Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * Flux Image Provider (via Fal.ai)
 */
class FluxImageProvider extends AbstractImageProvider {

    protected string $id = 'flux';
    protected string $name = 'Flux.1 / Flux.2';
    protected string $base_url = 'https://fal.run/fal-ai/';
    protected array $models = [ 'flux-pro', 'flux-dev', 'flux-schnell' ];

    /**
     * {@inheritdoc}
     */
    protected function getHeaders(): array {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Key ' . $this->api_key,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generate( string $prompt, array $options = [] ): array {
        $model = $options['model'] ?? 'flux-pro';
        $size  = $options['size'] ?? '1024x1024';
        
        // 解析尺寸
        $dimensions = explode( 'x', $size );
        $width  = (int) ( $dimensions[0] ?? 1024 );
        $height = (int) ( $dimensions[1] ?? 1024 );

        $response = wp_remote_post( $this->base_url . $model, [
            'timeout' => $this->timeout,
            'headers' => $this->getHeaders(),
            'body'    => wp_json_encode( [
                'prompt'       => $prompt,
                'image_size'   => [
                    'width'  => $width,
                    'height' => $height,
                ],
                'num_images'   => $options['n'] ?? 1,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        $urls = array_column( $body['images'] ?? [], 'url' );

        if ( empty( $urls ) ) {
            return [
                'success' => false,
                'error'   => $body['detail'] ?? '生成失败',
            ];
        }

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
        // Fal.ai 没有 models 端点，直接测试简单请求
        $response = wp_remote_get( 'https://api.fal.ai/v1/apps', [
            'timeout' => 30,
            'headers' => $this->getHeaders(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        
        if ( $status === 200 ) {
            return [ 'success' => true, 'message' => '连接成功' ];
        }
        if ( $status === 401 ) {
            return [ 'success' => false, 'message' => 'API Key 无效' ];
        }

        return [ 'success' => false, 'message' => '连接失败 (HTTP ' . $status . ')' ];
    }
}
