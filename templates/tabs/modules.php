<?php
/**
 * Modules Management Tab
 *
 * @package WPMind
 * @since 3.2.0
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Core\ModuleLoader;

$module_loader = ModuleLoader::instance();
$modules       = $module_loader->get_modules();
?>

<div class="wpmind-modules-container">
    <div class="wpmind-modules-header">
        <h2><?php esc_html_e( '模块管理', 'wpmind' ); ?></h2>
        <p class="description">
            <?php esc_html_e( '启用或禁用 WPMind 功能模块。禁用不需要的模块可以提升性能。', 'wpmind' ); ?>
        </p>
    </div>

    <div class="wpmind-modules-grid">
        <?php foreach ( $modules as $module_id => $module ) : ?>
        <div class="wpmind-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-disabled'; ?>" data-module-id="<?php echo esc_attr( $module_id ); ?>">
            <div class="wpmind-module-header">
                <span class="wpmind-module-icon dashicons <?php echo esc_attr( $module['icon'] ); ?>"></span>
                <div class="wpmind-module-info">
                    <h3 class="wpmind-module-name"><?php echo esc_html( $module['name'] ); ?></h3>
                    <span class="wpmind-module-version">v<?php echo esc_html( $module['version'] ); ?></span>
                </div>
                <div class="wpmind-module-toggle">
                    <label class="wpmind-switch">
                        <input type="checkbox"
                               class="wpmind-module-switch"
                               data-module-id="<?php echo esc_attr( $module_id ); ?>"
                               <?php checked( $module['enabled'] ); ?>
                               <?php disabled( ! $module['can_disable'] && $module['enabled'] ); ?>>
                        <span class="wpmind-switch-slider"></span>
                    </label>
                </div>
            </div>
            <div class="wpmind-module-body">
                <p class="wpmind-module-description"><?php echo esc_html( $module['description'] ); ?></p>
            </div>
            <div class="wpmind-module-footer">
                <span class="wpmind-module-status">
                    <?php if ( $module['enabled'] ) : ?>
                        <span class="status-enabled"><span class="dashicons ri-checkbox-circle-line"></span> <?php esc_html_e( '已启用', 'wpmind' ); ?></span>
                    <?php else : ?>
                        <span class="status-disabled"><span class="dashicons ri-close-circle-line"></span> <?php esc_html_e( '已禁用', 'wpmind' ); ?></span>
                    <?php endif; ?>
                </span>
                <?php if ( $module['enabled'] && ! empty( $module['settings_tab'] ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmind&tab=' . $module['settings_tab'] ) ); ?>" class="wpmind-module-settings-link">
                        <span class="dashicons ri-settings-3-line"></span> <?php esc_html_e( '设置', 'wpmind' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ( empty( $modules ) ) : ?>
        <div class="wpmind-no-modules">
            <span class="dashicons ri-puzzle-line"></span>
            <p><?php esc_html_e( '暂无可用模块', 'wpmind' ); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.wpmind-modules-container {
    max-width: 1200px;
}

.wpmind-modules-header {
    margin-bottom: 24px;
}

.wpmind-modules-header h2 {
    margin: 0 0 8px;
    font-size: 1.3em;
}

.wpmind-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.wpmind-module-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.wpmind-module-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.wpmind-module-card.is-disabled {
    opacity: 0.7;
}

.wpmind-module-header {
    display: flex;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #f0f0f0;
    gap: 12px;
}

.wpmind-module-icon {
    font-size: 24px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border-radius: 8px;
    color: #666;
}

.wpmind-module-card.is-enabled .wpmind-module-icon {
    background: #e8f5e9;
    color: #2e7d32;
}

.wpmind-module-info {
    flex: 1;
}

.wpmind-module-name {
    margin: 0;
    font-size: 1em;
    font-weight: 600;
}

.wpmind-module-version {
    font-size: 0.8em;
    color: #888;
}

.wpmind-module-body {
    padding: 16px;
}

.wpmind-module-description {
    margin: 0;
    color: #666;
    font-size: 0.9em;
    line-height: 1.5;
}

.wpmind-module-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #fafafa;
    border-top: 1px solid #f0f0f0;
}

.wpmind-module-status {
    font-size: 0.85em;
}

.status-enabled {
    color: #2e7d32;
}

.status-disabled {
    color: #888;
}

.wpmind-module-settings-link {
    font-size: 0.85em;
    text-decoration: none;
    color: #0073aa;
}

.wpmind-module-settings-link:hover {
    color: #005177;
}

/* Switch styles */
.wpmind-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.wpmind-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.wpmind-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.wpmind-switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.wpmind-switch input:checked + .wpmind-switch-slider {
    background-color: #2e7d32;
}

.wpmind-switch input:checked + .wpmind-switch-slider:before {
    transform: translateX(20px);
}

.wpmind-switch input:disabled + .wpmind-switch-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

.wpmind-no-modules {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.wpmind-no-modules .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin-bottom: 16px;
}
</style>

<script>
jQuery(function($) {
    $('.wpmind-module-switch').on('change', function() {
        var $switch = $(this);
        var moduleId = $switch.data('module-id');
        var enable = $switch.is(':checked');
        var $card = $switch.closest('.wpmind-module-card');

        $switch.prop('disabled', true);

        $.ajax({
            url: wpmindData.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmind_toggle_module',
                nonce: wpmindData.nonce,
                module_id: moduleId,
                enable: enable
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.reload) {
                        location.reload();
                    } else {
                        $card.toggleClass('is-enabled', enable).toggleClass('is-disabled', !enable);
                    }
                } else {
                    alert(response.data.message || '操作失败');
                    $switch.prop('checked', !enable);
                }
            },
            error: function() {
                alert('网络错误');
                $switch.prop('checked', !enable);
            },
            complete: function() {
                $switch.prop('disabled', false);
            }
        });
    });
});
</script>
