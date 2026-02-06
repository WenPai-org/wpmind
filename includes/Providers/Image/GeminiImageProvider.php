<?php
/**
 * Google Gemini Image Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

declare(strict_types=1);

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * Google Gemini Image Provider
 */
class GeminiImageProvider extends AbstractImageProvider {

    protected string $id = 'google_gemini_image';
    protected string $name = 'Gemini Pro Image';
    protected string $base_url = 'https://generativelanguage.googleapis.com/v1/';
    protected array $models = [ 'gemini-3-pro-image', 'imagen-3' ];

    /**
     * {@inheritdoc}
     */
    protected function getHeaders(): array {
        // Gemini 使用 API Key 作为查询参数，不需要 Authorization header
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 获取带 API Key 的 URL
     */
    private function getUrlWithKey( string $endpoint ): string {
        return $this->base_url . ltrim( $endpoint, '/' ) . '?key=' . $this->api_key;
    }

    /**
     * {@inheritdoc}
     */
    public function generate( string $prompt, array $options = [] ): array {
        $model = $options['model'] ?? 'imagen-3';

        $url = $this->getUrlWithKey( 'models/' . $model . ':generateContent' );

        $response = wp_remote_post( $url, [
            'timeout' => $this->timeout,
            'headers' => $this->getHeaders(),
            'body'    => wp_json_encode( [
                'contents' => [
                    [
                        'parts' => [
                            [ 'text' => $prompt ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => [ 'image' ],
                ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // 提取图像数据
        $images = $body['candidates'][0]['content']['parts'] ?? [];
        $urls = [];
        
        foreach ( $images as $part ) {
            if ( isset( $part['inlineData'] ) ) {
                // Base64 编码的图像
                $urls[] = 'data:' . $part['inlineData']['mimeType'] . ';base64,' . $part['inlineData']['data'];
            }
        }

        if ( empty( $urls ) ) {
            return [
                'success' => false,
                'error'   => $body['error']['message'] ?? '生成失败',
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
        $url = $this->getUrlWithKey( 'models' );
        
        $response = wp_remote_get( $url, [
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
        if ( $status === 400 || $status === 403 ) {
            return [ 'success' => false, 'message' => 'API Key 无效' ];
        }

        return [ 'success' => false, 'message' => '连接失败 (HTTP ' . $status . ')' ];
    }
}
