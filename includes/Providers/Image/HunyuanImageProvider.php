<?php
/**
 * 腾讯混元图像 Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 腾讯混元图像 Provider
 */
class HunyuanImageProvider extends AbstractImageProvider {

    protected string $id = 'tencent_hunyuan';
    protected string $name = '混元图像 3.0';
    protected string $base_url = 'https://hunyuan.cloud.tencent.com/hyllm/v1/';
    protected array $models = [ 'hunyuan-image-3.0', 'hunyuan-image-turbo' ];

    /**
     * {@inheritdoc}
     */
    public function generate( string $prompt, array $options = [] ): array {
        $model = $options['model'] ?? 'hunyuan-image-3.0';
        $size  = $options['size'] ?? '1024x1024';

        $response = $this->request( 'images/generations', [
            'model'  => $model,
            'prompt' => $prompt,
            'size'   => $size,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $urls = [];
        foreach ( $response['data'] ?? [] as $item ) {
            if ( isset( $item['url'] ) ) {
                $urls[] = $item['url'];
            } elseif ( isset( $item['b64_image'] ) ) {
                $urls[] = 'data:image/png;base64,' . $item['b64_image'];
            }
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
        $response = wp_remote_get( $this->base_url . 'models', [
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
