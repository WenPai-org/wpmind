<?php
/**
 * WPMind 设置页面模板
 *
 * 参考 Slim SEO 风格设计
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap wpmind-wrap">
    <!-- 标题栏 -->
    <h1 class="wpmind-title">
        <span class="wpmind-title-left">
            <img src="https://wpcy.com/wp-content/uploads/2025/07/wpmind-logo.webp" alt="WPMind" class="wpmind-logo">
            <?php esc_html_e( '文派心思', 'wpmind' ); ?>
            <span class="wpmind-version">v<?php echo esc_html( WPMIND_VERSION ); ?></span>
        </span>

        <span class="wpmind-title-right">
            <a href="https://developer.wordpress.org/plugins/ai/" target="_blank" class="wpmind-title-link">
                <span class="dashicons dashicons-book"></span>
                <?php esc_html_e( '文档', 'wpmind' ); ?>
            </a>
            <a href="https://github.com/developer-jeremywang/wpmind" target="_blank" class="wpmind-title-link">
                <span class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e( 'GitHub', 'wpmind' ); ?>
            </a>
            <a href="https://wpmind.developer.wang/support" target="_blank" class="wpmind-title-link">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e( '支持', 'wpmind' ); ?>
            </a>
        </span>
    </h1>

    <!-- 主内容区 -->
    <div class="wpmind-content">
        <!-- Tab 卡片 -->
        <div class="wpmind-tabs-card">
            <!-- Tab 导航 -->
            <nav class="wpmind-tab-list">
                <a href="#dashboard" class="wpmind-tab wpmind-tab-active" data-tab="dashboard">
                    <?php esc_html_e( '仪表板', 'wpmind' ); ?>
                </a>
                <a href="#services" class="wpmind-tab" data-tab="services">
                    <?php esc_html_e( '服务配置', 'wpmind' ); ?>
                </a>
                <a href="#routing" class="wpmind-tab" data-tab="routing">
                    <?php esc_html_e( '智能路由', 'wpmind' ); ?>
                </a>
                <a href="#budget" class="wpmind-tab" data-tab="budget">
                    <?php esc_html_e( '预算管理', 'wpmind' ); ?>
                </a>
            </nav>

            <!-- Tab 内容 -->
            <div id="dashboard" class="wpmind-tab-pane wpmind-tab-pane-active">
                <?php include WPMIND_PLUGIN_DIR . 'templates/tabs/dashboard.php'; ?>
            </div>
            <div id="services" class="wpmind-tab-pane">
                <?php include WPMIND_PLUGIN_DIR . 'templates/tabs/services.php'; ?>
            </div>
            <div id="routing" class="wpmind-tab-pane">
                <?php include WPMIND_PLUGIN_DIR . 'templates/tabs/routing.php'; ?>
            </div>
            <div id="budget" class="wpmind-tab-pane">
                <?php include WPMIND_PLUGIN_DIR . 'templates/tabs/budget.php'; ?>
            </div>
        </div>
    </div>
</div>
