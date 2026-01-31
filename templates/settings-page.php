<?php
/**
 * WPMind 设置页面模板
 *
 * @package WPMind
 * @since 1.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取数据
$wpmind_instance  = \WPMind\wpmind();
$endpoints        = $wpmind_instance->get_custom_endpoints();
$default_provider = get_option( 'wpmind_default_provider', '' );
$request_timeout  = get_option( 'wpmind_request_timeout', 60 );

// 获取故障转移状态
$failover_manager = \WPMind\Failover\FailoverManager::instance();
$provider_status  = $failover_manager->getStatusSummary();
?>

<div class="wrap wpmind-settings">
    <h1><?php esc_html_e( '文派心思设置', 'wpmind' ); ?></h1>
    <p class="description">
        <?php esc_html_e( '配置自定义 AI 服务端点，支持国内外多种 AI 服务。', 'wpmind' ); ?>
    </p>

    <!-- Provider 状态面板 -->
    <?php if ( ! empty( $provider_status ) ) : ?>
    <div class="wpmind-status-panel">
        <h2 class="title">
            <?php esc_html_e( '服务状态', 'wpmind' ); ?>
            <button type="button" class="button button-small wpmind-refresh-status" title="<?php esc_attr_e( '刷新状态', 'wpmind' ); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
            <button type="button" class="button button-small wpmind-reset-all-breakers" title="<?php esc_attr_e( '重置所有熔断器', 'wpmind' ); ?>">
                <span class="dashicons dashicons-image-rotate"></span>
                <?php esc_html_e( '重置', 'wpmind' ); ?>
            </button>
        </h2>
        <div class="wpmind-status-grid" id="wpmind-status-grid">
            <?php foreach ( $provider_status as $provider_id => $status ) : ?>
            <div class="wpmind-status-item" data-provider="<?php echo esc_attr( $provider_id ); ?>">
                <span class="wpmind-status-indicator wpmind-status-<?php echo esc_attr( $status['state'] ); ?>"></span>
                <span class="wpmind-status-name"><?php echo esc_html( $status['display_name'] ); ?></span>
                <span class="wpmind-status-label"><?php echo esc_html( $status['state_label'] ); ?></span>
                <span class="wpmind-status-score" title="<?php esc_attr_e( '健康分数', 'wpmind' ); ?>">
                    <?php echo esc_html( $status['health_score'] ); ?>%
                </span>
                <?php if ( $status['state'] === 'open' && $status['recovery_in'] ) : ?>
                <span class="wpmind-status-recovery">
                    <?php printf( esc_html__( '%d分钟后恢复', 'wpmind' ), ceil( $status['recovery_in'] / 60 ) ); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php" id="wpmind-settings-form">
        <?php settings_fields( 'wpmind_settings' ); ?>

        <h2 class="title"><?php esc_html_e( '全局设置', 'wpmind' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="wpmind_request_timeout">
                        <?php esc_html_e( '请求超时', 'wpmind' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number"
                           id="wpmind_request_timeout"
                           name="wpmind_request_timeout"
                           value="<?php echo esc_attr( $request_timeout ); ?>"
                           min="10"
                           max="300"
                           step="1"
                           class="small-text">
                    <span class="description">
                        <?php esc_html_e( '秒 (建议 60-120)', 'wpmind' ); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wpmind_default_provider">
                        <?php esc_html_e( '默认服务', 'wpmind' ); ?>
                    </label>
                </th>
                <td>
                    <select id="wpmind_default_provider" name="wpmind_default_provider">
                        <option value="">
                            <?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?>
                        </option>
                        <?php foreach ( $endpoints as $key => $endpoint ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"
                                <?php selected( $default_provider, $key ); ?>>
                                <?php echo esc_html( $endpoint['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'AI 服务端点', 'wpmind' ); ?></h2>

        <div class="wpmind-endpoints-grid">
            <?php foreach ( $endpoints as $key => $endpoint ) : 
                $has_api_key = $wpmind_instance->has_api_key( $key );
            ?>
            <div class="wpmind-endpoint-card" id="endpoint-<?php echo esc_attr( $key ); ?>">
                <div class="wpmind-endpoint-header">
                    <?php
                    // 使用 lobe-icons CDN SVG 图标 (v1.77.0)
                    $icon_slug = $endpoint['icon'] ?? $key;
                    $icon_url  = "https://registry.npmmirror.com/@lobehub/icons-static-svg/1.77.0/files/icons/{$icon_slug}.svg";
                    ?>
                    <img src="<?php echo esc_url( $icon_url ); ?>"
                         alt="<?php echo esc_attr( $endpoint['name'] ); ?>"
                         class="wpmind-provider-icon"
                         width="24"
                         height="24"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';">
                    <span class="dashicons <?php echo ! empty( $endpoint['is_official'] ) ? 'dashicons-admin-site' : 'dashicons-cloud'; ?>" style="display:none;"></span>
                    <span class="wpmind-endpoint-name">
                        <?php echo esc_html( $endpoint['name'] ); ?>
                    </span>
                    <code class="wpmind-endpoint-key"><?php echo esc_html( $endpoint['display_name'] ?? $key ); ?></code>
                    <?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
                        <span class="wpmind-status wpmind-status-official">
                            <?php esc_html_e( '官方服务', 'wpmind' ); ?>
                        </span>
                    <?php elseif ( ! empty( $endpoint['enabled'] ) && $has_api_key ) : ?>
                        <span class="wpmind-status wpmind-status-active">
                            <?php esc_html_e( '已启用', 'wpmind' ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( '启用', 'wpmind' ); ?></th>
                        <td>
                            <label class="wpmind-toggle">
                                <input type="checkbox"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][enabled]"
                                       value="1"
                                       <?php checked( ! empty( $endpoint['enabled'] ) ); ?>>
                                <span class="wpmind-toggle-slider"></span>
                                <span class="wpmind-toggle-label">
                                    <?php esc_html_e( '启用此服务', 'wpmind' ); ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key_<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( 'API Key', 'wpmind' ); ?>
                            </label>
                        </th>
                        <td>
                            <div class="wpmind-api-key-field">
                                <input type="password"
                                       id="api_key_<?php echo esc_attr( $key ); ?>"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][api_key]"
                                       value=""
                                       class="regular-text"
                                       autocomplete="new-password"
                                       placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
                                <button type="button"
                                        class="button wpmind-toggle-key"
                                        data-target="api_key_<?php echo esc_attr( $key ); ?>"
                                        aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <?php if ( $has_api_key ) : ?>
                            <label class="wpmind-clear-key">
                                <input type="checkbox"
                                       name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]"
                                       value="1"
                                       class="wpmind-clear-checkbox">
                                <?php esc_html_e( '清除 API Key', 'wpmind' ); ?>
                            </label>
                            <?php endif; ?>
                            <?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
                            <p class="description">
                                <?php esc_html_e( '此 API Key 将同步到 WordPress AI Client', 'wpmind' ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '连接测试', 'wpmind' ); ?></th>
                        <td>
                            <button type="button"
                                    class="button wpmind-test-connection"
                                    data-provider="<?php echo esc_attr( $key ); ?>"
                                    <?php echo empty( $endpoint['enabled'] ) ? 'disabled' : ''; ?>>
                                <?php esc_html_e( '测试连接', 'wpmind' ); ?>
                            </button>
                            <span class="wpmind-test-result"></span>
                        </td>
                    </tr>
                    <tr class="wpmind-advanced-row">
                        <td colspan="2">
                            <button type="button" class="button button-link wpmind-toggle-advanced">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                <?php esc_html_e( '高级设置', 'wpmind' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>

                <!-- 高级设置（默认隐藏） -->
                <table class="form-table wpmind-advanced-settings" style="display: none;">
                    <tr>
                        <th scope="row">
                            <label for="custom_base_url_<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url"
                                   id="custom_base_url_<?php echo esc_attr( $key ); ?>"
                                   name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]"
                                   value="<?php echo esc_attr( $endpoint['custom_base_url'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr( $endpoint['base_url'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></th>
                        <td class="wpmind-models">
                            <?php foreach ( (array) $endpoint['models'] as $model ) : ?>
                                <code><?php echo esc_html( $model ); ?></code>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <?php submit_button( __( '保存设置', 'wpmind' ) ); ?>
    </form>
</div>
