/**
 * WPMind Admin boot.
 *
 * @package WPMind
 * @since 3.3.0
 */

(function ($) {
    'use strict';

    var Admin = window.WPMindAdmin || (window.WPMindAdmin = {});
    Admin.state = Admin.state || {
        chartsLoaded: false
    };

    Admin.safeInit = Admin.safeInit || function (label, fn) {
        try {
            fn();
        } catch (error) {
            console.warn('[WPMind] ' + label + ' init failed:', error);
        }
    };

    Admin.ensureChartsInit = Admin.ensureChartsInit || function () {
        if (Admin.state.chartsLoaded) return;
        if (Admin.AnalyticsCharts && typeof Admin.AnalyticsCharts.init === 'function') {
            Admin.AnalyticsCharts.init();
            Admin.state.chartsLoaded = true;
        }
    };

    /**
     * Tab 导航管理
     */
    function initTabs() {
        var $tabs = $('.wpmind-tab');
        var $panes = $('.wpmind-tab-pane');

        if (!$tabs.length) return;

        // 从 URL hash 恢复 Tab 状态
        var hash = window.location.hash.slice(1) || 'dashboard';
        switchTab(hash);

        // Tab 点击事件
        $tabs.on('click', function (e) {
            e.preventDefault();
            var tabId = $(this).data('tab');
            switchTab(tabId);
            history.replaceState(null, null, '#' + tabId);
        });

        function switchTab(tabId) {
            // 验证 tabId 是否有效
            if (!$('#' + tabId).length) {
                tabId = 'dashboard';
            }

            $tabs.removeClass('wpmind-tab-active');
            $tabs.filter('[data-tab="' + tabId + '"]').addClass('wpmind-tab-active');

            $panes.removeClass('wpmind-tab-pane-active');
            $('#' + tabId).addClass('wpmind-tab-pane-active');

            // 懒加载图表（仅在首次切换到仪表板时）
            if (tabId === 'dashboard' && !Admin.state.chartsLoaded) {
                Admin.ensureChartsInit();
            }
        }
    }

    /**
     * Initialize on document ready
     */
    $(function () {
        // Health check
        $('body').addClass('wpmind-js-loaded');
        if (typeof wpmindData !== 'undefined' && wpmindData.version) {
            console.log('[WPMind] admin scripts v' + wpmindData.version + ' loaded');
        }

        Admin.safeInit('tabs', initTabs);

        // 图表懒加载：只在仪表板 Tab 激活时初始化
        if ($('#dashboard').hasClass('wpmind-tab-pane-active')) {
            Admin.safeInit('analytics', Admin.ensureChartsInit);
        }
    });
})(jQuery);
