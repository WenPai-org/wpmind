/**
 * WPMind Admin JavaScript
 *
 * @package WPMind
 * @since 1.1.0
 */

(function ($) {
    'use strict';

    /**
     * HTML 转义函数 - 防止 XSS
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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
            if (tabId === 'dashboard' && !window.wpmindChartsLoaded) {
                if (typeof AnalyticsCharts !== 'undefined') {
                    AnalyticsCharts.init();
                }
                window.wpmindChartsLoaded = true;
            }
        }
    }

    /**
     * Toast 通知系统 - 使用 WordPress 原生 notice 样式
     */
    var Toast = {
        container: null,

        init: function () {
            if (!this.container) {
                // 在 wpmind-title 下方创建通知容器
                this.container = $('<div class="wpmind-notice-container"></div>');
                $('.wpmind-title').after(this.container);
            }
        },

        show: function (message, type, duration) {
            this.init();
            type = type || 'info';
            duration = duration || 3000;

            // WordPress 原生 notice 类型映射
            var noticeType = {
                success: 'notice-success',
                error: 'notice-error',
                warning: 'notice-warning',
                info: 'notice-info'
            };

            // 图标映射
            var icons = {
                success: 'ri-checkbox-circle-line',
                error: 'ri-close-circle-line',
                warning: 'ri-alert-line',
                info: 'ri-information-line'
            };

            var $notice = $('<div class="notice ' + noticeType[type] + ' is-dismissible wpmind-notice">' +
                '<p><span class="dashicons ' + icons[type] + ' wpmind-notice-icon"></span><span class="wpmind-notice-text"></span></p>' +
                '</div>');

            // 使用 .text() 防止 XSS
            $notice.find('.wpmind-notice-text').text(message);

            this.container.append($notice);

            // 添加 WordPress 原生关闭按钮
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">关闭此通知</span></button>');

            // 动画显示
            $notice.hide().slideDown(200);

            // 关闭按钮事件
            $notice.find('.notice-dismiss').on('click', function () {
                Toast.hide($notice);
            });

            // 自动关闭
            if (duration > 0) {
                setTimeout(function () {
                    Toast.hide($notice);
                }, duration);
            }

            return $notice;
        },

        hide: function ($notice) {
            $notice.slideUp(200, function () {
                $(this).remove();
            });
        },

        success: function (message, duration) {
            return this.show(message, 'success', duration);
        },

        error: function (message, duration) {
            // 错误消息显示更长时间
            return this.show(message, 'error', duration || 8000);
        },

        warning: function (message, duration) {
            return this.show(message, 'warning', duration || 5000);
        },

        info: function (message, duration) {
            return this.show(message, 'info', duration);
        }
    };

    /**
     * 确认对话框
     */
    var Dialog = {
        show: function (options) {
            var defaults = {
                title: '确认操作',
                message: '确定要执行此操作吗？',
                confirmText: '确定',
                cancelText: '取消',
                type: 'warning',
                onConfirm: function () { },
                onCancel: function () { }
            };

            var settings = $.extend({}, defaults, options);

            var icons = {
                warning: 'ri-alert-line',
                danger: 'ri-close-circle-line',
                info: 'ri-information-line',
                success: 'ri-checkbox-circle-line'
            };

            var $overlay = $('<div class="wpmind-dialog-overlay"></div>');
            var $dialog = $('<div class="wpmind-dialog wpmind-dialog-' + settings.type + '">' +
                '<div class="wpmind-dialog-header">' +
                '<span class="dashicons ' + icons[settings.type] + '"></span>' +
                '<span class="wpmind-dialog-title">' + settings.title + '</span>' +
                '</div>' +
                '<div class="wpmind-dialog-body">' +
                '<p>' + settings.message + '</p>' +
                '</div>' +
                '<div class="wpmind-dialog-footer">' +
                '<button type="button" class="button wpmind-dialog-cancel">' + settings.cancelText + '</button>' +
                '<button type="button" class="button button-primary wpmind-dialog-confirm">' + settings.confirmText + '</button>' +
                '</div>' +
                '</div>');

            $('body').append($overlay).append($dialog);

            // 动画显示
            setTimeout(function () {
                $overlay.addClass('is-visible');
                $dialog.addClass('is-visible');
            }, 10);

            // 关闭函数
            var close = function () {
                $overlay.removeClass('is-visible');
                $dialog.removeClass('is-visible');
                setTimeout(function () {
                    $overlay.remove();
                    $dialog.remove();
                }, 300);
            };

            // 事件绑定
            $dialog.find('.wpmind-dialog-cancel').on('click', function () {
                close();
                settings.onCancel();
            });

            $dialog.find('.wpmind-dialog-confirm').on('click', function () {
                close();
                settings.onConfirm();
            });

            $overlay.on('click', function () {
                close();
                settings.onCancel();
            });

            // ESC 关闭
            $(document).on('keydown.wpmind-dialog', function (e) {
                if (e.keyCode === 27) {
                    close();
                    settings.onCancel();
                    $(document).off('keydown.wpmind-dialog');
                }
            });
        },

        confirm: function (message, onConfirm, onCancel) {
            this.show({
                message: message,
                onConfirm: onConfirm || function () { },
                onCancel: onCancel || function () { }
            });
        }
    };

    /**
     * Toggle password visibility
     */
    function initPasswordToggle() {
        $('.wpmind-toggle-key').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var targetId = $button.data('target');
            var $target = $('#' + targetId);
            var $icon = $button.find('.dashicons');

            if ($target.attr('type') === 'password') {
                $target.attr('type', 'text');
                $icon.removeClass('ri-eye-line').addClass('ri-eye-off-line');
            } else {
                $target.attr('type', 'password');
                $icon.removeClass('ri-eye-off-line').addClass('ri-eye-line');
            }
        });
    }

    /**
     * Endpoint Card 折叠功能
     */
    function initEndpointCollapse() {
        // 点击 header 或 toggle 按钮折叠/展开
        $(document).on('click', '.wpmind-endpoint-header', function (e) {
            // 如果点击的是内部的其他按钮或链接，不触发折叠
            if ($(e.target).closest('a, button:not(.wpmind-endpoint-toggle), input, select').length && !$(e.target).closest('.wpmind-endpoint-toggle').length) {
                return;
            }

            var $card = $(this).closest('.wpmind-endpoint-card');
            var $toggle = $(this).find('.wpmind-endpoint-toggle');
            var isCollapsed = $card.hasClass('is-collapsed');

            if (isCollapsed) {
                $card.removeClass('is-collapsed');
                $toggle.attr('aria-expanded', 'true');
            } else {
                $card.addClass('is-collapsed');
                $toggle.attr('aria-expanded', 'false');
            }
        });

        // 阻止 toggle 按钮的默认行为（因为 header 已经处理了点击）
        $(document).on('click', '.wpmind-endpoint-toggle', function (e) {
            e.stopPropagation();
            $(this).closest('.wpmind-endpoint-header').trigger('click');
        });
    }

    /**
     * API Key 输入处理
     */
    function initApiKeyValidation() {
        $('input[id^="api_key_"]').on('input', function () {
            var $input = $(this);
            $input.siblings('.wpmind-validation-message').remove();
            $input.removeClass('is-valid is-invalid');
        });
    }

    /**
     * 测试连接功能
     */
    function initTestConnection() {
        $('.wpmind-test-connection').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var provider = $button.data('provider');
            var $result = $button.siblings('.wpmind-test-result');
            var $card = $button.closest('.wpmind-endpoint-card');

            var $apiKeyInput = $card.find('input[name*="[api_key]"]');
            var apiKey = $apiKeyInput.val();
            var $customUrlInput = $card.find('input[name*="[custom_base_url]"]');
            var customUrl = $customUrlInput.val();

            // 设置加载状态
            $button.addClass('is-testing').prop('disabled', true);
            $button.html('<span class="dashicons ri-loader-4-line wpmind-spinning"></span> 测试中');
            $result.text('').removeClass('success error warning').removeAttr('title');

            if (typeof wpmindData === 'undefined') {
                $result.text('配置错误').addClass('error');
                $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_test_connection',
                    provider: provider,
                    api_key: apiKey,
                    custom_url: customUrl,
                    nonce: wpmindData.nonce
                },
                timeout: 45000,
                success: function (response) {
                    if (response.success) {
                        var message = '连接成功';
                        var extra = '';
                        if (response.data) {
                            if (response.data.retried) {
                                extra += ' (重试后)';
                            }
                            if (response.data.latency) {
                                extra += ' ' + response.data.latency + 'ms';
                            }
                        }
                        $result.html('<span class="dashicons ri-checkbox-circle-line"></span> ' + message + extra).addClass('success');
                        Toast.success(provider.toUpperCase() + ' ' + message);
                    } else {
                        var errorMsg = (response.data && response.data.message) || '连接失败';
                        var errorCode = (response.data && response.data.code) ? ' [' + response.data.code + ']' : '';
                        var retryInfo = (response.data && response.data.retried) ? ' (已重试)' : '';

                        $result.html(
                            '<span class="dashicons ri-close-circle-line"></span> ' +
                            escapeHtml(errorMsg) + errorCode + retryInfo
                        ).addClass('error');

                        // 显示详细信息提示
                        if (response.data && response.data.details) {
                            $result.attr('title', '详细信息: ' + escapeHtml(response.data.details));
                        }

                        // Toast 也显示错误
                        Toast.error(provider.toUpperCase() + ': ' + errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    var message = '连接失败';
                    if (status === 'timeout') {
                        message = '请求超时，请检查网络连接';
                    } else if (status === 'error') {
                        message = '网络错误，请检查连接';
                    }
                    $result.html('<span class="dashicons ri-close-circle-line"></span> ' + message).addClass('error');
                    Toast.error(provider.toUpperCase() + ': ' + message);
                },
                complete: function () {
                    $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                    // 延长显示时间到 10 秒，让用户有足够时间阅读错误信息
                    setTimeout(function () {
                        $result.fadeOut(300, function () {
                            $(this).text('').removeClass('success error warning').removeAttr('title').show();
                        });
                    }, 10000);
                }
            });
        });
    }

    /**
     * 测试图像服务连接功能
     */
    function initImageTestConnection() {
        $('.wpmind-test-image-connection').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var provider = $button.data('provider');
            var $result = $button.siblings('.wpmind-test-result');

            // 设置加载状态
            $button.addClass('is-testing').prop('disabled', true);
            $button.html('<span class="dashicons ri-loader-4-line wpmind-spinning"></span> 测试中');
            $result.text('').removeClass('success error');

            if (typeof wpmindData === 'undefined') {
                $result.text('配置错误').addClass('error');
                $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_test_image_connection',
                    provider: provider,
                    nonce: wpmindData.nonce
                },
                timeout: 45000,
                success: function (response) {
                    if (response.success) {
                        $result.html('<span class="dashicons ri-checkbox-circle-line"></span> 连接成功').addClass('success');
                        Toast.success(provider + ' 连接成功');
                    } else {
                        var errorMsg = (response.data && response.data.message) || '连接失败';
                        var errorCode = (response.data && response.data.code) ? ' [' + response.data.code + ']' : '';
                        $result.html('<span class="dashicons ri-close-circle-line"></span> ' + escapeHtml(errorMsg) + errorCode).addClass('error');
                        Toast.error(provider + ': ' + errorMsg);
                    }
                },
                error: function (xhr, status) {
                    var message = status === 'timeout' ? '请求超时，请检查网络连接' : '网络错误，请检查连接';
                    $result.html('<span class="dashicons ri-close-circle-line"></span> ' + message).addClass('error');
                    Toast.error(provider + ': ' + message);
                },
                complete: function () {
                    $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                    setTimeout(function () {
                        $result.fadeOut(300, function () {
                            $(this).text('').removeClass('success error').show();
                        });
                    }, 10000);
                }
            });
        });
    }

    /**
     * Update card status when checkbox changes
     */
    function initStatusUpdate() {
        $('.wpmind-endpoint-card input[type="checkbox"]').not('.wpmind-clear-checkbox').on('change', function () {
            var $card = $(this).closest('.wpmind-endpoint-card');
            var $header = $card.find('.wpmind-endpoint-header');
            var $status = $header.find('.wpmind-status').not('.wpmind-status-official');
            var $apiKey = $card.find('input[type="password"], input[type="text"]').filter('[id^="api_key_"]');
            var hasKey = $apiKey.attr('placeholder') && $apiKey.attr('placeholder').length > 0;

            if (this.checked && (hasKey || $apiKey.val())) {
                if (!$status.length) {
                    $header.append('<span class="wpmind-status wpmind-status-active">已启用</span>');
                }
            } else {
                $status.remove();
            }
        });
    }

    /**
     * Handle clear API key checkbox
     */
    function initClearKeyHandler() {
        $('.wpmind-clear-checkbox').on('change', function () {
            var $card = $(this).closest('.wpmind-endpoint-card');
            var $apiKeyInput = $card.find('input[id^="api_key_"]');

            if (this.checked) {
                $apiKeyInput.prop('disabled', true).attr('placeholder', 'API Key 将被清除');
                $card.addClass('wpmind-card-warning');
            } else {
                $apiKeyInput.prop('disabled', false).attr('placeholder', '••••••••••••••••');
                $card.removeClass('wpmind-card-warning');
            }
        });
    }

    /**
     * Form validation before submit
     */
    function initFormValidation() {
        $('#wpmind-settings-form').on('submit', function (e) {
            var hasEnabledWithoutKey = false;
            var $problemCard = null;

            $('.wpmind-endpoint-card').each(function () {
                var $card = $(this);
                var $checkbox = $card.find('input[type="checkbox"]').not('.wpmind-clear-checkbox');
                var $apiKey = $card.find('input[type="password"], input[type="text"]').filter('[id^="api_key_"]');
                var $clearCheckbox = $card.find('.wpmind-clear-checkbox');
                var hasExistingKey = $apiKey.attr('placeholder') && $apiKey.attr('placeholder').indexOf('•') !== -1;
                var willClear = $clearCheckbox.is(':checked');

                $card.removeClass('wpmind-card-error');

                if ($checkbox.is(':checked') && !$apiKey.val() && !hasExistingKey && !willClear) {
                    hasEnabledWithoutKey = true;
                    $problemCard = $card;
                    $card.addClass('wpmind-card-error');
                    return false;
                }
            });

            if (hasEnabledWithoutKey) {
                e.preventDefault();
                Toast.error('请为已启用的服务填写 API Key');
                if ($problemCard) {
                    $('html, body').animate({
                        scrollTop: $problemCard.offset().top - 100
                    }, 300);
                    $problemCard.find('input[id^="api_key_"]').focus();
                }
                return false;
            }
        });
    }

    /**
     * 折叠/展开高级设置
     */
    function initAdvancedToggle() {
        $('.wpmind-toggle-advanced').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $card = $button.closest('.wpmind-endpoint-card');
            var $advanced = $card.find('.wpmind-advanced-settings');
            var $icon = $button.find('.dashicons');

            if ($advanced.is(':visible')) {
                $advanced.slideUp(200);
                $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
            } else {
                $advanced.slideDown(200);
                $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
            }
        });
    }

    /**
     * 刷新 Provider 状态
     */
    function initStatusRefresh() {
        $(document).on('click', '.wpmind-refresh-status', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            if ($button.hasClass('is-loading')) return;

            $button.addClass('is-loading');

            if (typeof wpmindData === 'undefined') {
                console.error('WPMind: wpmindData not found');
                $button.removeClass('is-loading');
                Toast.error('配置错误');
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_get_provider_status',
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success && response.data.providers) {
                        updateStatusGrid(response.data.providers);
                        Toast.success('状态已刷新');
                    }
                },
                error: function () {
                    Toast.error('刷新失败');
                },
                complete: function () {
                    $button.removeClass('is-loading');
                }
            });
        });
    }

    /**
     * 更新状态网格
     */
    function updateStatusGrid(providers) {
        var $grid = $('#wpmind-status-grid');

        $.each(providers, function (providerId, status) {
            var $item = $grid.find('[data-provider="' + providerId + '"]');
            if ($item.length) {
                $item.addClass('is-updating');

                setTimeout(function () {
                    $item.find('.wpmind-status-indicator')
                        .removeClass('wpmind-status-closed wpmind-status-open wpmind-status-half_open')
                        .addClass('wpmind-status-' + status.state);
                    $item.find('.wpmind-status-label').text(status.state_label);
                    $item.find('.wpmind-status-score').text(status.health_score + '%');

                    var $recovery = $item.find('.wpmind-status-recovery');
                    if (status.state === 'open' && status.recovery_in) {
                        var minutes = Math.ceil(status.recovery_in / 60);
                        if ($recovery.length) {
                            $recovery.text(minutes + '分钟后恢复');
                        } else {
                            $item.append('<span class="wpmind-status-recovery">' + minutes + '分钟后恢复</span>');
                        }
                    } else {
                        $recovery.remove();
                    }

                    $item.removeClass('is-updating');
                }, 150);
            }
        });
    }

    /**
     * 重置熔断器
     */
    function initResetBreakers() {
        $(document).on('click', '.wpmind-reset-all-breakers', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);

            Dialog.show({
                title: '重置熔断器',
                message: '确定要重置所有熔断器吗？<br><small style="color:#666;">这将清除所有服务的健康状态记录</small>',
                type: 'warning',
                confirmText: '确定重置',
                cancelText: '取消',
                onConfirm: function () {
                    var originalHtml = $button.html();
                    $button.prop('disabled', true).html('<span class="dashicons ri-loader-4-line wpmind-spinning"></span>');

                    if (typeof wpmindData === 'undefined') {
                        Toast.error('配置错误');
                        $button.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    $.ajax({
                        url: wpmindData.ajaxurl || ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpmind_reset_circuit_breaker',
                            provider: 'all',
                            nonce: wpmindData.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                Toast.success('熔断器已重置');
                                setTimeout(function () {
                                    $('.wpmind-refresh-status').trigger('click');
                                }, 300);
                            } else {
                                Toast.error('重置失败');
                            }
                        },
                        error: function () {
                            Toast.error('重置失败');
                        },
                        complete: function () {
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        });
    }

    /**
     * 刷新用量统计
     */
    function initUsageRefresh() {
        $(document).on('click', '.wpmind-refresh-usage', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            if ($button.hasClass('is-loading')) return;

            $button.addClass('is-loading');
            $button.find('.dashicons').addClass('wpmind-spinning');

            if (typeof wpmindData === 'undefined') {
                Toast.error('配置错误');
                $button.removeClass('is-loading');
                $button.find('.dashicons').removeClass('wpmind-spinning');
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_get_usage_stats',
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        updateUsageDisplay(response.data);
                        Toast.success('统计已刷新');
                    }
                },
                error: function () {
                    Toast.error('刷新失败');
                },
                complete: function () {
                    $button.removeClass('is-loading');
                    $button.find('.dashicons').removeClass('wpmind-spinning');
                }
            });
        });
    }

    /**
     * 更新用量显示
     */
    function updateUsageDisplay(data) {
        var today = data.today || {};
        var month = data.month || {};
        var total = (data.stats && data.stats.total) || {};

        $('#today-tokens').text(formatTokens(today.input_tokens + today.output_tokens));
        $('#today-cost').text(formatCost(today.cost || 0));
        $('#today-requests').text(today.requests || 0);

        $('#month-tokens').text(formatTokens(month.input_tokens + month.output_tokens));
        $('#month-cost').text(formatCost(month.cost || 0));
        $('#month-requests').text(month.requests || 0);

        $('#total-tokens').text(formatTokens((total.input_tokens || 0) + (total.output_tokens || 0)));
        $('#total-cost').text(formatCost(total.cost || 0));
        $('#total-requests').text(total.requests || 0);
    }

    /**
     * 格式化 token 数量
     */
    function formatTokens(tokens) {
        tokens = tokens || 0;
        if (tokens >= 1000000) {
            return (tokens / 1000000).toFixed(2) + 'M';
        }
        if (tokens >= 1000) {
            return (tokens / 1000).toFixed(1) + 'K';
        }
        return tokens.toString();
    }

    /**
     * 格式化成本
     */
    function formatCost(cost) {
        cost = cost || 0;
        if (cost < 0.01) {
            return '$' + cost.toFixed(4);
        }
        return '$' + cost.toFixed(2);
    }

    /**
     * 预算设置管理
     */
    function initBudgetSettings() {
        // 切换预算设置面板显示
        $('#wpmind_budget_enabled').on('change', function () {
            $('#wpmind-budget-settings').toggle(this.checked);
        });

        // 切换邮件字段显示
        $('input[name="email_alert"]').on('change', function () {
            $('.wpmind-budget-email-field').toggle(this.checked);
        });

        // 保存预算设置
        $('#wpmind-save-budget').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            if ($button.prop('disabled')) return;

            var originalText = $button.text();
            $button.prop('disabled', true).html('<span class="dashicons ri-loader-4-line wpmind-spinning"></span> 保存中');

            // 收集设置数据
            var settings = {
                enabled: $('#wpmind_budget_enabled').is(':checked'),
                global: {
                    daily_limit_usd: parseFloat($('input[name="daily_limit_usd"]').val()) || 0,
                    monthly_limit_usd: parseFloat($('input[name="monthly_limit_usd"]').val()) || 0,
                    daily_limit_cny: parseFloat($('input[name="daily_limit_cny"]').val()) || 0,
                    monthly_limit_cny: parseFloat($('input[name="monthly_limit_cny"]').val()) || 0,
                    alert_threshold: parseInt($('input[name="alert_threshold"]').val()) || 80
                },
                enforcement_mode: $('select[name="enforcement_mode"]').val() || 'alert',
                notifications: {
                    admin_notice: $('input[name="admin_notice"]').is(':checked'),
                    email_alert: $('input[name="email_alert"]').is(':checked'),
                    email_address: $('input[name="email_address"]').val() || ''
                }
            };

            if (typeof wpmindData === 'undefined') {
                Toast.error('配置错误');
                $button.prop('disabled', false).text(originalText);
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_save_budget_settings',
                    settings: JSON.stringify(settings),
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success('预算设置已保存');
                    } else {
                        var msg = (response.data && response.data.message) || '保存失败';
                        Toast.error(msg);
                    }
                },
                error: function () {
                    Toast.error('保存失败，请重试');
                },
                complete: function () {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * 清除用量统计
     */
    function initUsageClear() {
        $(document).on('click', '.wpmind-clear-usage', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);

            Dialog.show({
                title: '清除统计',
                message: '确定要清除所有用量统计数据吗？<br><small style="color:#666;">此操作不可恢复</small>',
                type: 'danger',
                confirmText: '确定清除',
                cancelText: '取消',
                onConfirm: function () {
                    var originalHtml = $button.html();
                    $button.prop('disabled', true).html('<span class="dashicons ri-loader-4-line wpmind-spinning"></span>');

                    if (typeof wpmindData === 'undefined') {
                        Toast.error('配置错误');
                        $button.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    $.ajax({
                        url: wpmindData.ajaxurl || ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpmind_clear_usage_stats',
                            nonce: wpmindData.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                Toast.success('统计已清除');
                                // 重置显示
                                updateUsageDisplay({
                                    today: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 },
                                    month: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 },
                                    stats: { total: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 } }
                                });
                            } else {
                                Toast.error('清除失败');
                            }
                        },
                        error: function () {
                            Toast.error('清除失败');
                        },
                        complete: function () {
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        });
    }

    /**
     * Analytics Dashboard 图表管理
     */
    var AnalyticsCharts = {
        charts: {},

        // 现代化配色方案
        colors: {
            primary: '#3858e9',
            primaryLight: 'rgba(56, 88, 233, 0.1)',
            secondary: '#10b981',
            secondaryLight: 'rgba(16, 185, 129, 0.1)',
            accent: '#f59e0b',
            danger: '#ef4444',
            gray: {
                50: '#f9fafb',
                100: '#f3f4f6',
                200: '#e5e7eb',
                300: '#d1d5db',
                400: '#9ca3af',
                500: '#6b7280',
                600: '#4b5563',
                700: '#374151',
                800: '#1f2937',
                900: '#111827'
            }
        },

        // 全局图表默认配置
        getDefaultOptions: function () {
            var self = this;
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 750,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: {
                                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                                size: 12,
                                weight: '500'
                            },
                            color: self.colors.gray[600]
                        }
                    },
                    tooltip: {
                        backgroundColor: self.colors.gray[800],
                        titleFont: {
                            family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            size: 12
                        },
                        padding: 12,
                        cornerRadius: 0,
                        displayColors: true,
                        boxPadding: 6
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                                size: 11
                            },
                            color: self.colors.gray[500]
                        }
                    },
                    y: {
                        grid: {
                            color: self.colors.gray[100],
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                                size: 11
                            },
                            color: self.colors.gray[500]
                        }
                    }
                }
            };
        },

        init: function () {
            if (!$('#wpmind-usage-trend-chart').length) return;
            if (typeof Chart === 'undefined') return;

            this.loadData();
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // 时间范围切换
            $('#wpmind-analytics-range').on('change', function () {
                self.loadData();
            });

            // 刷新按钮
            $('.wpmind-refresh-analytics').on('click', function (e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.find('.dashicons').addClass('wpmind-spinning');
                self.loadData(function () {
                    $btn.find('.dashicons').removeClass('wpmind-spinning');
                });
            });
        },

        loadData: function (callback) {
            var self = this;
            var range = $('#wpmind-analytics-range').val() || '7d';

            if (typeof wpmindData === 'undefined') return;

            // 显示加载状态
            $('.wpmind-chart-container').addClass('is-loading');

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_get_analytics_data',
                    range: range,
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        self.renderCharts(response.data);
                    } else {
                        Toast.error('加载分析数据失败');
                    }
                },
                error: function () {
                    Toast.error('加载分析数据失败，请稍后重试');
                },
                complete: function () {
                    $('.wpmind-chart-container').removeClass('is-loading');
                    if (typeof callback === 'function') callback();
                }
            });
        },

        renderCharts: function (data) {
            this.renderTrendChart(data.trend);
            this.renderProviderChart(data.providers);
            this.renderCostChart(data.cost);
            this.renderModelChart(data.models);
        },

        renderTrendChart: function (data) {
            var ctx = document.getElementById('wpmind-usage-trend-chart');
            if (!ctx) return;

            if (this.charts.trend) {
                this.charts.trend.destroy();
            }

            var self = this;
            var options = this.getDefaultOptions();

            this.charts.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Tokens',
                        data: data.datasets.tokens,
                        borderColor: self.colors.primary,
                        backgroundColor: self.colors.primaryLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: self.colors.primary,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        yAxisID: 'y'
                    }, {
                        label: '请求数',
                        data: data.datasets.requests,
                        borderColor: self.colors.secondary,
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: self.colors.secondary,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        yAxisID: 'y1'
                    }]
                },
                options: $.extend(true, {}, options, {
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Tokens',
                                font: { size: 11, weight: '500' },
                                color: self.colors.gray[500]
                            },
                            grid: {
                                color: self.colors.gray[100],
                                drawBorder: false
                            },
                            ticks: {
                                font: { size: 11 },
                                color: self.colors.gray[500]
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: '请求数',
                                font: { size: 11, weight: '500' },
                                color: self.colors.gray[500]
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                font: { size: 11 },
                                color: self.colors.gray[500]
                            }
                        }
                    }
                })
            });
        },

        renderProviderChart: function (data) {
            var ctx = document.getElementById('wpmind-provider-chart');
            if (!ctx) return;

            if (this.charts.provider) {
                this.charts.provider.destroy();
            }

            if (!data.labels || data.labels.length === 0) {
                return;
            }

            var self = this;

            this.charts.provider = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.datasets.requests,
                        backgroundColor: data.colors,
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    animation: {
                        animateRotate: true,
                        animateScale: true
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 16,
                                font: {
                                    family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                                    size: 12
                                },
                                color: self.colors.gray[600]
                            }
                        },
                        tooltip: {
                            backgroundColor: self.colors.gray[800],
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 0,
                            callbacks: {
                                label: function (context) {
                                    var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var percentage = ((context.raw / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        renderCostChart: function (data) {
            var ctx = document.getElementById('wpmind-cost-chart');
            if (!ctx) return;

            if (this.charts.cost) {
                this.charts.cost.destroy();
            }

            var self = this;
            var options = this.getDefaultOptions();

            this.charts.cost = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'USD',
                        data: data.datasets.cost_usd,
                        backgroundColor: self.colors.primary,
                        borderColor: self.colors.primary,
                        borderWidth: 0,
                        borderRadius: 0,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }, {
                        label: 'CNY',
                        data: data.datasets.cost_cny,
                        backgroundColor: self.colors.danger,
                        borderColor: self.colors.danger,
                        borderWidth: 0,
                        borderRadius: 0,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: $.extend(true, {}, options, {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '费用',
                                font: { size: 11, weight: '500' },
                                color: self.colors.gray[500]
                            },
                            grid: {
                                color: self.colors.gray[100],
                                drawBorder: false
                            },
                            ticks: {
                                font: { size: 11 },
                                color: self.colors.gray[500]
                            }
                        }
                    }
                })
            });
        },

        renderModelChart: function (data) {
            var ctx = document.getElementById('wpmind-model-chart');
            if (!ctx) return;

            if (this.charts.model) {
                this.charts.model.destroy();
            }

            if (!data.labels || data.labels.length === 0) {
                return;
            }

            var self = this;

            this.charts.model = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '请求数',
                        data: data.datasets.requests,
                        backgroundColor: self.colors.primary,
                        borderColor: self.colors.primary,
                        borderWidth: 0,
                        borderRadius: 0,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 750,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: self.colors.gray[800],
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 0
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '请求数',
                                font: { size: 11, weight: '500' },
                                color: self.colors.gray[500]
                            },
                            grid: {
                                color: self.colors.gray[100],
                                drawBorder: false
                            },
                            ticks: {
                                font: { size: 11 },
                                color: self.colors.gray[500]
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: { size: 11 },
                                color: self.colors.gray[600]
                            }
                        }
                    }
                }
            });
        }
    };

    /**
     * Routing Panel 路由管理
     */
    var RoutingManager = {
        init: function () {
            if (!$('.wpmind-routing-panel').length) return;
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // 策略选择 - 监听 radio change 事件
            $(document).on('change', 'input[name="routing_strategy"]', function () {
                console.log('Radio changed:', $(this).val());
                var strategy = $(this).val();
                self.setStrategy(strategy);
            });

            // 策略卡片点击 - 备用方案
            $(document).on('click', '.wpmind-strategy-item', function (e) {
                var $radio = $(this).find('input[type="radio"]');
                if (!$radio.prop('checked')) {
                    $radio.prop('checked', true).trigger('change');
                }
            });

            // 刷新路由状态
            $(document).on('click', '.wpmind-refresh-routing', function (e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.find('.dashicons').addClass('wpmind-spinning');
                self.refreshStatus(function () {
                    $btn.find('.dashicons').removeClass('wpmind-spinning');
                });
            });

            // 初始化拖拽排序
            self.initSortable();

            // 保存优先级
            $(document).on('click', '.wpmind-save-priority', function (e) {
                e.preventDefault();
                self.savePriority();
            });

            // 清除优先级
            $(document).on('click', '.wpmind-clear-priority', function (e) {
                e.preventDefault();
                self.clearPriority();
            });
        },

        initSortable: function () {
            var $list = $('#wpmind-priority-list');
            if (!$list.length) return;

            // 检查 jQuery UI sortable 是否可用
            if (typeof $.fn.sortable !== 'function') {
                console.warn('WPMind: jQuery UI Sortable not available');
                return;
            }

            $list.sortable({
                handle: '.wpmind-priority-handle',
                placeholder: 'wpmind-priority-placeholder',
                axis: 'y',
                tolerance: 'pointer',
                update: function () {
                    // 更新序号显示
                    $list.find('.wpmind-priority-item').each(function (index) {
                        $(this).find('.wpmind-priority-index').text(index + 1);
                    });
                }
            });
        },

        savePriority: function () {
            var self = this;
            if (typeof wpmindData === 'undefined') {
                Toast.error('配置错误');
                return;
            }

            var $list = $('#wpmind-priority-list');
            var priority = [];
            $list.find('.wpmind-priority-item').each(function () {
                priority.push($(this).data('provider'));
            });

            if (priority.length === 0) {
                Toast.warning('没有可排序的 Provider');
                return;
            }

            var $btn = $('.wpmind-save-priority');
            $btn.prop('disabled', true).find('.dashicons').addClass('wpmind-spinning');

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_set_provider_priority',
                    priority: priority,
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success('优先级已保存');
                        // 显示清除按钮
                        if (!$('.wpmind-clear-priority').length) {
                            $('.wpmind-routing-priority-actions').prepend(
                                '<button type="button" class="button button-small wpmind-clear-priority" title="清除手动优先级">' +
                                '<span class="dashicons ri-delete-bin-line"></span> 清除</button>'
                            );
                        }
                        // 显示已启用标记
                        if (!$('.wpmind-priority-badge').length) {
                            $('.wpmind-routing-priority .wpmind-routing-section-desc').append(
                                ' <span class="wpmind-priority-badge">已启用手动优先级</span>'
                            );
                        }
                        // 刷新路由状态
                        self.refreshStatus();
                    } else {
                        var msg = (response.data && response.data.message) || '保存失败';
                        Toast.error(msg);
                    }
                },
                error: function () {
                    Toast.error('保存失败，请重试');
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('wpmind-spinning');
                }
            });
        },

        clearPriority: function () {
            var self = this;
            if (typeof wpmindData === 'undefined') {
                Toast.error('配置错误');
                return;
            }

            Dialog.show({
                title: '清除手动优先级',
                message: '确定要清除手动优先级设置吗？<br><small style="color:#666;">清除后将使用智能路由自动排序</small>',
                type: 'warning',
                confirmText: '确定清除',
                cancelText: '取消',
                onConfirm: function () {
                    var $btn = $('.wpmind-clear-priority');
                    $btn.prop('disabled', true).find('.dashicons').addClass('wpmind-spinning');

                    $.ajax({
                        url: wpmindData.ajaxurl || ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpmind_set_provider_priority',
                            priority: [],
                            clear: 1,
                            nonce: wpmindData.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                Toast.success('手动优先级已清除');
                                // 移除清除按钮和标记
                                $('.wpmind-clear-priority').remove();
                                $('.wpmind-priority-badge').remove();
                                // 刷新路由状态
                                self.refreshStatus();
                            } else {
                                var msg = (response.data && response.data.message) || '清除失败';
                                Toast.error(msg);
                            }
                        },
                        error: function () {
                            Toast.error('清除失败，请重试');
                        },
                        complete: function () {
                            $btn.prop('disabled', false).find('.dashicons').removeClass('wpmind-spinning');
                        }
                    });
                }
            });
        },

        setStrategy: function (strategy) {
            if (typeof wpmindData === 'undefined') {
                Toast.error('配置错误');
                return;
            }

            // 更新 UI 状态
            $('.wpmind-strategy-item').removeClass('is-active');
            $('input[name="routing_strategy"][value="' + strategy + '"]')
                .closest('.wpmind-strategy-item')
                .addClass('is-active');

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_set_routing_strategy',
                    strategy: strategy,
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success('路由策略已更新');
                        // 刷新得分显示
                        RoutingManager.refreshStatus();
                    } else {
                        var msg = (response.data && response.data.message) || '更新失败';
                        Toast.error(msg);
                    }
                },
                error: function () {
                    Toast.error('更新失败，请重试');
                }
            });
        },

        refreshStatus: function (callback) {
            if (typeof wpmindData === 'undefined') {
                if (typeof callback === 'function') callback();
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_get_routing_status',
                    nonce: wpmindData.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        RoutingManager.updateDisplay(response.data);
                        Toast.success('路由状态已刷新');
                    }
                },
                error: function () {
                    Toast.error('刷新失败');
                },
                complete: function () {
                    if (typeof callback === 'function') callback();
                }
            });
        },

        updateDisplay: function (data) {
            // 更新得分排名
            var $scores = $('#wpmind-routing-scores');
            if ($scores.length && data.provider_scores) {
                $scores.empty();
                $.each(data.provider_scores, function (providerId, scoreData) {
                    var isTop = scoreData.rank === 1;
                    var $item = $('<div class="wpmind-routing-score-item"></div>');
                    if (isTop) $item.addClass('is-top');
                    $item.append($('<span class="wpmind-routing-rank"></span>').text(scoreData.rank));
                    $item.append($('<span class="wpmind-routing-provider-name"></span>').text(scoreData.name));
                    var $bar = $('<div class="wpmind-routing-score-bar"></div>');
                    $bar.append($('<div class="wpmind-routing-score-fill"></div>').css('width', scoreData.score + '%'));
                    $item.append($bar);
                    $item.append($('<span class="wpmind-routing-score-value"></span>').text(scoreData.score.toFixed(1)));
                    $scores.append($item);
                });
            }

            // 更新推荐 Provider
            if (data.recommended && data.provider_scores && data.provider_scores[data.recommended]) {
                var recommendedData = data.provider_scores[data.recommended];
                $('#wpmind-recommended-provider').text(recommendedData.name);
                // 更新得分显示
                $('.wpmind-routing-status-score-value').text(recommendedData.score.toFixed(1));
            }

            // 更新故障转移链 - 新的可视化结构
            var $failoverChain = $('#wpmind-failover-chain');
            if ($failoverChain.length && data.failover_chain && data.failover_chain.length) {
                $failoverChain.empty();
                $.each(data.failover_chain, function (index, provider) {
                    var isFirst = index === 0;
                    var $node = $('<div class="wpmind-routing-failover-node"></div>');
                    if (isFirst) $node.addClass('is-active');
                    $node.append('<span class="wpmind-routing-failover-dot"></span>');
                    $node.append($('<span class="wpmind-routing-failover-name"></span>').text(provider));
                    if (isFirst) {
                        $node.append('<span class="wpmind-routing-failover-badge">主</span>');
                    }
                    $failoverChain.append($node);
                    // 添加连接线（除了最后一个）
                    if (index < data.failover_chain.length - 1) {
                        $failoverChain.append('<div class="wpmind-routing-failover-line"></div>');
                    }
                });
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(function () {
        initTabs();
        initPasswordToggle();
        initEndpointCollapse();
        initApiKeyValidation();
        initTestConnection();
        initImageTestConnection();
        initStatusUpdate();
        initClearKeyHandler();
        initFormValidation();
        initAdvancedToggle();
        initStatusRefresh();
        initResetBreakers();
        initUsageRefresh();
        initUsageClear();
        initBudgetSettings();
        RoutingManager.init();

        // 图表懒加载：只在仪表板 Tab 激活时初始化
        if ($('#dashboard').hasClass('active')) {
            AnalyticsCharts.init();
            window.wpmindChartsLoaded = true;
        }
    });

})(jQuery);
