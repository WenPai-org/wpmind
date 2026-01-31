<?php
/**
 * WPMind 设置页面模板
 *
 * Tab 导航结构：仪表板 | 服务配置 | 智能路由 | 预算管理
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap wpmind-settings">
    <h1><?php esc_html_e( '文派心思设置', 'wpmind' ); ?></h1>
    <p class="description">
        <?php esc_html_e( '配置自定义 AI 服务端点，支持国内外多种 AI 服务。', 'wpmind' ); ?>
    </p>

    <!-- Tab 导航 -->
    <nav class="nav-tab-wrapper wpmind-tabs">
        <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( '仪表板', 'wpmind' ); ?>
        </a>
        <a href="#services" class="nav-tab" data-tab="services">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php esc_html_e( '服务配置', 'wpmind' ); ?>
        </a>
        <a href="#routing" class="nav-tab" data-tab="routing">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e( '智能路由', 'wpmind' ); ?>
        </a>
        <a href="#budget" class="nav-tab" data-tab="budget">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e( '预算管理', 'wpmind' ); ?>
        </a>
    </nav>

    <!-- Tab 内容 -->
    <div class="wpmind-tab-content">
        <div id="dashboard" class="wpmind-tab-pane active">
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
