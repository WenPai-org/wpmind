<?php
/**
 * 通义万相图像生成 Provider
 *
 * @package WPMind
 * @since 2.4.0
 */

namespace WPMind\Providers\Image;

defined( 'ABSPATH' ) || exit;

/**
 * 通义万相 Provider
 */
class QwenImageProvider extends AbstractImageProvider {

    /**
     * {@inheritdoc}
     */
    protected string $id = 'qwen_image';

    /**
     * {@inheritdoc}
     */
    protected string $name = '通义万相';

    /**
     * {@inheritdoc}
     */
    protected string $base_url = 'https://dashscope.aliyuncs.com/api/v1/';

    /**
     * {@inheritdoc}
     */
    protected array $models = [ 'wanx-v1', 'wanx2.1-t2i-turbo' ];

    /**
     * {@inheritdoc}
     */
    protected function getHeaders(): array {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-DashScope-Async' => 'enable', // 异步模式
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generate( string $prompt, array $options = [] ): array {
        $model = $options['model'] ?? 'wanx-v1';
        $size  = $options['size'] ?? '1024*1024';
        $n     = $options['n'] ?? 1;

        // 创建任务
        $response = $this->request( 'services/aigc/text2image/image-synthesis', [
            'model' => $model,
            'input' => [
                'prompt' => $prompt,
            ],
            'parameters' => [
                'size' => $size,
                'n'    => $n,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        // 获取任务 ID
        $task_id = $response['output']['task_id'] ?? null;
        if ( ! $task_id ) {
            return [
                'success' => false,
                'error'   => '无法获取任务 ID',
            ];
        }

        // 轮询任务状态
        $max_attempts = 60;
        $attempt = 0;
        
        while ( $attempt < $max_attempts ) {
            sleep( 2 );
            $attempt++;
            
            $status_response = $this->request( 'tasks/' . $task_id, [], 'GET' );
            
            if ( is_wp_error( $status_response ) ) {
                continue;
            }
            
            $status = $status_response['output']['task_status'] ?? '';
            
            if ( $status === 'SUCCEEDED' ) {
                $results = $status_response['output']['results'] ?? [];
                $urls = array_column( $results, 'url' );
                
                return [
                    'success' => true,
                    'urls'    => $urls,
                    'url'     => $urls[0] ?? '',
                ];
            }
            
            if ( $status === 'FAILED' ) {
                return [
                    'success' => false,
                    'error'   => $status_response['output']['message'] ?? '生成失败',
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
        // 简单的模型列表请求来测试连接
        $response = wp_remote_get( $this->base_url . 'models', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( $status_code === 200 || $status_code === 401 ) {
            // 401 也说明连接成功，只是认证失败
            if ( $status_code === 401 ) {
                return [
                    'success' => false,
                    'message' => 'API Key 无效',
                ];
            }
            
            return [
                'success' => true,
                'message' => '连接成功',
            ];
        }

        return [
            'success' => false,
            'message' => '连接失败 (HTTP ' . $status_code . ')',
        ];
    }
}
