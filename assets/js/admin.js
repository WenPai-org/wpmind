/**
 * WPMind Admin JavaScript
 *
 * @package WPMind
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Toast 通知系统 - 使用 WordPress 原生 notice 样式
     */
    var Toast = {
        container: null,

        init: function() {
            if (!this.container) {
                // 在页面顶部创建通知容器
                this.container = $('<div class="wpmind-notice-container"></div>');
                $('.wrap.wpmind-settings').prepend(this.container);
            }
        },

        show: function(message, type, duration) {
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

            var $notice = $('<div class="notice ' + noticeType[type] + ' is-dismissible wpmind-notice">' +
                '<p></p>' +
                '</div>');

            // 使用 .text() 防止 XSS
            $notice.find('p').text(message);

            this.container.append($notice);

            // 添加 WordPress 原生关闭按钮
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">关闭此通知</span></button>');

            // 动画显示
            $notice.hide().slideDown(200);

            // 关闭按钮事件
            $notice.find('.notice-dismiss').on('click', function() {
                Toast.hide($notice);
            });

            // 自动关闭
            if (duration > 0) {
                setTimeout(function() {
                    Toast.hide($notice);
                }, duration);
            }

            return $notice;
        },

        hide: function($notice) {
            $notice.slideUp(200, function() {
                $(this).remove();
            });
        },

        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },

        error: function(message, duration) {
            return this.show(message, 'error', duration || 5000);
        },

        warning: function(message, duration) {
            return this.show(message, 'warning', duration || 4000);
        },

        info: function(message, duration) {
            return this.show(message, 'info', duration);
        }
    };

    /**
     * 确认对话框
     */
    var Dialog = {
        show: function(options) {
            var defaults = {
                title: '确认操作',
                message: '确定要执行此操作吗？',
                confirmText: '确定',
                cancelText: '取消',
                type: 'warning',
                onConfirm: function() {},
                onCancel: function() {}
            };

            var settings = $.extend({}, defaults, options);

            var icons = {
                warning: 'dashicons-warning',
                danger: 'dashicons-dismiss',
                info: 'dashicons-info',
                success: 'dashicons-yes-alt'
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
            setTimeout(function() {
                $overlay.addClass('is-visible');
                $dialog.addClass('is-visible');
            }, 10);

            // 关闭函数
            var close = function() {
                $overlay.removeClass('is-visible');
                $dialog.removeClass('is-visible');
                setTimeout(function() {
                    $overlay.remove();
                    $dialog.remove();
                }, 300);
            };

            // 事件绑定
            $dialog.find('.wpmind-dialog-cancel').on('click', function() {
                close();
                settings.onCancel();
            });

            $dialog.find('.wpmind-dialog-confirm').on('click', function() {
                close();
                settings.onConfirm();
            });

            $overlay.on('click', function() {
                close();
                settings.onCancel();
            });

            // ESC 关闭
            $(document).on('keydown.wpmind-dialog', function(e) {
                if (e.keyCode === 27) {
                    close();
                    settings.onCancel();
                    $(document).off('keydown.wpmind-dialog');
                }
            });
        },

        confirm: function(message, onConfirm, onCancel) {
            this.show({
                message: message,
                onConfirm: onConfirm || function() {},
                onCancel: onCancel || function() {}
            });
        }
    };

    /**
     * Toggle password visibility
     */
    function initPasswordToggle() {
        $('.wpmind-toggle-key').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var targetId = $button.data('target');
            var $target = $('#' + targetId);
            var $icon = $button.find('.dashicons');

            if ($target.attr('type') === 'password') {
                $target.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $target.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });
    }

    /**
     * API Key 输入处理
     */
    function initApiKeyValidation() {
        $('input[id^="api_key_"]').on('input', function() {
            var $input = $(this);
            $input.siblings('.wpmind-validation-message').remove();
            $input.removeClass('is-valid is-invalid');
        });
    }

    /**
     * 测试连接功能
     */
    function initTestConnection() {
        $('.wpmind-test-connection').on('click', function(e) {
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
            $button.html('<span class="dashicons dashicons-update wpmind-spinning"></span> 测试中');
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
                success: function(response) {
                    if (response.success) {
                        var message = '连接成功';
                        if (response.data && response.data.retried) {
                            message += ' (重试后)';
                        }
                        $result.html('<span class="dashicons dashicons-yes-alt"></span> ' + message).addClass('success');
                        Toast.success(provider.toUpperCase() + ' ' + message);
                    } else {
                        var errorMsg = (response.data && response.data.message) || '连接失败';
                        $result.html('<span class="dashicons dashicons-dismiss"></span> ' + errorMsg).addClass('error');
                        if (response.data && response.data.details) {
                            $result.attr('title', response.data.details);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    var message = '连接失败';
                    if (status === 'timeout') {
                        message = '请求超时';
                    }
                    $result.html('<span class="dashicons dashicons-dismiss"></span> ' + message).addClass('error');
                },
                complete: function() {
                    $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                    setTimeout(function() {
                        $result.fadeOut(300, function() {
                            $(this).text('').removeClass('success error warning').removeAttr('title').show();
                        });
                    }, 6000);
                }
            });
        });
    }

    /**
     * Update card status when checkbox changes
     */
    function initStatusUpdate() {
        $('.wpmind-endpoint-card input[type="checkbox"]').not('.wpmind-clear-checkbox').on('change', function() {
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
        $('.wpmind-clear-checkbox').on('change', function() {
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
        $('#wpmind-settings-form').on('submit', function(e) {
            var hasEnabledWithoutKey = false;
            var $problemCard = null;

            $('.wpmind-endpoint-card').each(function() {
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
        $('.wpmind-toggle-advanced').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $card = $button.closest('.wpmind-endpoint-card');
            var $advanced = $card.find('.wpmind-advanced-settings');
            var $icon = $button.find('.dashicons');

            if ($advanced.is(':visible')) {
                $advanced.slideUp(200);
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            } else {
                $advanced.slideDown(200);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        });
    }

    /**
     * 刷新 Provider 状态
     */
    function initStatusRefresh() {
        $(document).on('click', '.wpmind-refresh-status', function(e) {
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
                success: function(response) {
                    if (response.success && response.data.providers) {
                        updateStatusGrid(response.data.providers);
                        Toast.success('状态已刷新');
                    }
                },
                error: function() {
                    Toast.error('刷新失败');
                },
                complete: function() {
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

        $.each(providers, function(providerId, status) {
            var $item = $grid.find('[data-provider="' + providerId + '"]');
            if ($item.length) {
                $item.addClass('is-updating');

                setTimeout(function() {
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
        $(document).on('click', '.wpmind-reset-all-breakers', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);

            Dialog.show({
                title: '重置熔断器',
                message: '确定要重置所有熔断器吗？<br><small style="color:#666;">这将清除所有服务的健康状态记录</small>',
                type: 'warning',
                confirmText: '确定重置',
                cancelText: '取消',
                onConfirm: function() {
                    var originalHtml = $button.html();
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-update wpmind-spinning"></span>');

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
                        success: function(response) {
                            if (response.success) {
                                Toast.success('熔断器已重置');
                                setTimeout(function() {
                                    $('.wpmind-refresh-status').trigger('click');
                                }, 300);
                            } else {
                                Toast.error('重置失败');
                            }
                        },
                        error: function() {
                            Toast.error('重置失败');
                        },
                        complete: function() {
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
        $(document).on('click', '.wpmind-refresh-usage', function(e) {
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
                success: function(response) {
                    if (response.success) {
                        updateUsageDisplay(response.data);
                        Toast.success('统计已刷新');
                    }
                },
                error: function() {
                    Toast.error('刷新失败');
                },
                complete: function() {
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
        $('#wpmind_budget_enabled').on('change', function() {
            $('#wpmind-budget-settings').toggle(this.checked);
        });

        // 切换邮件字段显示
        $('input[name="email_alert"]').on('change', function() {
            $('.wpmind-budget-email-field').toggle(this.checked);
        });

        // 保存预算设置
        $('#wpmind-save-budget').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            if ($button.prop('disabled')) return;

            var originalText = $button.text();
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update wpmind-spinning"></span> 保存中');

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
                    email_address: $('input[name="alert_email"]').val() || ''
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
                success: function(response) {
                    if (response.success) {
                        Toast.success('预算设置已保存');
                    } else {
                        var msg = (response.data && response.data.message) || '保存失败';
                        Toast.error(msg);
                    }
                },
                error: function() {
                    Toast.error('保存失败，请重试');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * 清除用量统计
     */
    function initUsageClear() {
        $(document).on('click', '.wpmind-clear-usage', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);

            Dialog.show({
                title: '清除统计',
                message: '确定要清除所有用量统计数据吗？<br><small style="color:#666;">此操作不可恢复</small>',
                type: 'danger',
                confirmText: '确定清除',
                cancelText: '取消',
                onConfirm: function() {
                    var originalHtml = $button.html();
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-update wpmind-spinning"></span>');

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
                        success: function(response) {
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
                        error: function() {
                            Toast.error('清除失败');
                        },
                        complete: function() {
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        });
    }

    /**
     * Initialize on document ready
     */
    $(function() {
        initPasswordToggle();
        initApiKeyValidation();
        initTestConnection();
        initStatusUpdate();
        initClearKeyHandler();
        initFormValidation();
        initAdvancedToggle();
        initStatusRefresh();
        initResetBreakers();
        initUsageRefresh();
        initUsageClear();
        initBudgetSettings();
    });

})(jQuery);
