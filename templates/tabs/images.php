<?php
/**
 * WPMind 图像服务 Tab
 *
 * 图像生成 AI 服务商配置
 *
 * @package WPMind
 * @since 2.4.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取已保存的图像服务配置
$image_endpoints = get_option( 'wpmind_image_endpoints', [] );

// 可用的图像生成服务商
$available_providers = [
    'zhipu_cogview' => [
        'name'         => '智谱 CogView',
        'display_name' => 'zhipu-cogview',
        'description'  => '智谱 AI 的图像生成模型，支持 CogView-3 等模型',
        'icon'         => 'ri-palette-line',
        'icon_color'   => '#1a56db',
        'base_url'     => 'https://open.bigmodel.cn/api/paas/v4/',
        'models'       => [ 'cogview-3', 'cogview-3-plus' ],
        'default_model'=> 'cogview-3',
    ],
    'tongyi_wanxiang' => [
        'name'         => '通义万相',
        'display_name' => 'tongyi-wanxiang',
        'description'  => '阿里云通义万相，支持文生图、图生图等功能',
        'icon'         => 'ri-image-ai-line',
        'icon_color'   => '#ff6a00',
        'base_url'     => 'https://dashscope.aliyuncs.com/api/v1/',
        'models'       => [ 'wanx-v1', 'wanx-style-repaint-v1' ],
        'default_model'=> 'wanx-v1',
    ],
    'wenxin_yige' => [
        'name'         => '文心一格',
        'display_name' => 'wenxin-yige',
        'description'  => '百度文心一格，AI 艺术与创意绘画平台',
        'icon'         => 'ri-brush-ai-line',
        'icon_color'   => '#2932e1',
        'base_url'     => 'https://aip.baidubce.com/',
        'models'       => [ 'sd_xl', 'visualglm' ],
        'default_model'=> 'sd_xl',
    ],
    'stability_ai' => [
        'name'         => 'Stability AI',
        'display_name' => 'stability-ai',
        'description'  => 'Stable Diffusion 官方 API（需境外网络）',
        'icon'         => 'ri-seedling-line',
        'icon_color'   => '#7c3aed',
        'base_url'     => 'https://api.stability.ai/v1/',
        'models'       => [ 'stable-diffusion-xl-1024-v1-0', 'stable-diffusion-v1-6' ],
        'default_model'=> 'stable-diffusion-xl-1024-v1-0',
    ],
];
?>

<div class="wpmind-images-panel">
    <div class="wpmind-panel-header">
        <h2>
            <span class="dashicons ri-image-add-line"></span>
            <?php esc_html_e( '图像生成服务', 'wpmind' ); ?>
        </h2>
        <p class="description">
            <?php esc_html_e( '配置图像生成 AI 服务商。启用后可在 AI Experiments 中使用 Image Generation 功能。', 'wpmind' ); ?>
        </p>
    </div>

    <?php if ( empty( $available_providers ) ) : ?>
    <div class="wpmind-empty-state">
        <span class="dashicons ri-image-line"></span>
        <p><?php esc_html_e( '暂无可用的图像生成服务商', 'wpmind' ); ?></p>
    </div>
    <?php else : ?>

    <form method="post" action="options.php" id="wpmind-images-form">
        <?php settings_fields( 'wpmind_image_settings' ); ?>
        
        <div class="wpmind-endpoints-list">
            <?php foreach ( $available_providers as $key => $provider ) :
                $saved_config = $image_endpoints[ $key ] ?? [];
                $is_enabled = ! empty( $saved_config['enabled'] );
                $has_api_key = ! empty( $saved_config['api_key'] );
                $icon_class = $provider['icon'] ?? 'ri-image-line';
                $icon_color = $provider['icon_color'] ?? '#6b7280';
            ?>
            <div class="wpmind-endpoint-card<?php echo ( $is_enabled && $has_api_key ) ? '' : ' is-collapsed'; ?>" id="image-endpoint-<?php echo esc_attr( $key ); ?>">
                <div class="wpmind-endpoint-header">
                    <button type="button" class="wpmind-endpoint-toggle" aria-expanded="<?php echo ( $is_enabled && $has_api_key ) ? 'true' : 'false'; ?>">
                        <span class="dashicons ri-arrow-down-s-line"></span>
                    </button>
                    <i class="<?php echo esc_attr( $icon_class ); ?> wpmind-provider-icon" style="color: <?php echo esc_attr( $icon_color ); ?>;"></i>
                    <span class="wpmind-endpoint-name">
                        <?php echo esc_html( $provider['name'] ); ?>
                    </span>
                    <code class="wpmind-endpoint-key"><?php echo esc_html( $provider['display_name'] ); ?></code>
                    
                    <?php if ( $is_enabled && $has_api_key ) : ?>
                    <span class="wpmind-endpoint-status is-active">
                        <?php esc_html_e( '已启用', 'wpmind' ); ?>
                    </span>
                    <?php endif; ?>

                    <label class="wpmind-toggle">
                        <input type="checkbox" 
                               name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][enabled]" 
                               value="1" 
                               <?php checked( $is_enabled ); ?>>
                        <span class="wpmind-toggle-slider"></span>
                    </label>
                </div>

                <div class="wpmind-endpoint-content">
                    <p class="wpmind-endpoint-description">
                        <?php echo esc_html( $provider['description'] ); ?>
                    </p>

                    <table class="wpmind-endpoint-table">
                        <tr>
                            <th><?php esc_html_e( 'API Key', 'wpmind' ); ?></th>
                            <td>
                                <div class="wpmind-input-group">
                                    <input type="password"
                                           id="image_api_key_<?php echo esc_attr( $key ); ?>"
                                           name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][api_key]"
                                           value="<?php echo $has_api_key ? '********' : ''; ?>"
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e( '输入 API Key', 'wpmind' ); ?>"
                                           autocomplete="off">
                                    <button type="button"
                                            class="button wpmind-toggle-key"
                                            data-target="image_api_key_<?php echo esc_attr( $key ); ?>"
                                            aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
                                        <span class="dashicons ri-eye-line"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( '默认模型', 'wpmind' ); ?></th>
                            <td>
                                <select name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][model]" class="regular-text">
                                    <?php foreach ( $provider['models'] as $model ) : ?>
                                    <option value="<?php echo esc_attr( $model ); ?>" 
                                            <?php selected( $saved_config['model'] ?? $provider['default_model'], $model ); ?>>
                                        <?php echo esc_html( $model ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( '自定义 URL', 'wpmind' ); ?></th>
                            <td>
                                <input type="url"
                                       name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][custom_url]"
                                       value="<?php echo esc_attr( $saved_config['custom_url'] ?? '' ); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr( $provider['base_url'] ); ?>">
                                <p class="description">
                                    <?php esc_html_e( '留空使用默认 API 地址', 'wpmind' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <button type="button" 
                                        class="button wpmind-test-connection" 
                                        data-provider="<?php echo esc_attr( $key ); ?>"
                                        data-type="image">
                                    <?php esc_html_e( '测试连接', 'wpmind' ); ?>
                                </button>
                                <span class="wpmind-test-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="wpmind-form-actions">
            <?php submit_button( __( '保存图像服务配置', 'wpmind' ), 'primary', 'submit', false ); ?>
        </div>
    </form>

    <?php endif; ?>
</div>

<style>
/* 图像服务面板特定样式 */
.wpmind-images-panel .wpmind-panel-header {
    margin-bottom: var(--wpmind-space-6);
}

.wpmind-images-panel .wpmind-panel-header h2 {
    display: flex;
    align-items: center;
    gap: var(--wpmind-space-2);
    margin: 0 0 var(--wpmind-space-2) 0;
}

.wpmind-images-panel .wpmind-panel-header h2 .dashicons {
    font-size: 20px;
    color: var(--wpmind-primary);
}

.wpmind-images-panel .wpmind-provider-icon {
    font-size: 24px;
}
</style>
