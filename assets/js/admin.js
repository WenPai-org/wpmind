/**
 * WPMind Admin JavaScript
 *
 * @package WPMind
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * API Key 格式验证规则
     */
    const apiKeyPatterns = {
        'openai': /^sk-[A-Za-z0-9]{48,}$/,
        'anthropic': /^sk-ant-[A-Za-z0-9-]{95,}$/,
        'google': /^[A-Za-z0-9_-]{39}$/,
        'deepseek': /^sk-[A-Za-z0-9]{48,}$/,
        'qwen': /^sk-[A-Za-z0-9]{32,}$/,
        'zhipu': /^[A-Za-z0-9]{32}\.[A-Za-z0-9]{6}$/,
        'moonshot': /^sk-[A-Za-z0-9]{48,}$/,
        'doubao': /^[A-Za-z0-9-]{32,}$/,
        'siliconflow': /^sk-[A-Za-z0-9]{48,}$/,
        'baidu': /^[A-Za-z0-9]{24,}$/,
        'minimax': /^[A-Za-z0-9]{32,}$/
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
                $icon.removeClass('dashicons-visibility')
                     .addClass('dashicons-hidden');
            } else {
                $target.attr('type', 'password');
                $icon.removeClass('dashicons-hidden')
                     .addClass('dashicons-visibility');
            }
        });
    }

    /**
     * API Key 格式验证
     */
    function initApiKeyValidation() {
        $('input[id^="api_key_"]').on('input', function() {
            var $input = $(this);
            var $card = $input.closest('.wpmind-endpoint-card');
            var providerId = $card.attr('id').replace('endpoint-', '');
            var value = $input.val().trim();

            // 移除之前的验证消息
            $input.siblings('.wpmind-validation-message').remove();

            if (!value) {
                $input.removeClass('is-valid is-invalid');
                return;
            }

            var pattern = apiKeyPatterns[providerId];
            if (!pattern) {
                // 没有验证规则，不验证
                $input.removeClass('is-valid is-invalid');
                return;
            }

            if (pattern.test(value)) {
                $input.removeClass('is-invalid').addClass('is-valid');
                $input.after('<span class="wpmind-validation-message success">✓ 格式正确</span>');
            } else {
                $input.removeClass('is-valid').addClass('is-invalid');
                $input.after('<span class="wpmind-validation-message error">✗ 格式不正确</span>');
            }
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

            // 获取 API Key（如果有输入）
            var $apiKeyInput = $card.find('input[name*="[api_key]"]');
            var apiKey = $apiKeyInput.val();

            // 获取自定义端点（如果有）
            var $customUrlInput = $card.find('input[name*="[custom_base_url]"]');
            var customUrl = $customUrlInput.val();

            // 设置加载状态
            $button.addClass('is-testing').prop('disabled', true).text('测试中...');
            $result.text('').removeClass('success error warning').removeAttr('title');

            // 检查 wpmindData 是否存在
            if (typeof wpmindData === 'undefined') {
                $result.text('配置错误').addClass('error');
                $button.removeClass('is-testing').prop('disabled', false).text('测试连接');
                return;
            }

            // 发送 AJAX 请求
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
                timeout: 45000, // 增加超时时间以支持重试
                success: function(response) {
                    if (response.success) {
                        var message = '连接成功';
                        if (response.data && response.data.retried) {
                            message += ' (重试后)';
                        }
                        $result.text(message).addClass('success');
                    } else {
                        var errorMsg = (response.data && response.data.message) || '连接失败';
                        $result.text(errorMsg).addClass('error');
                        // 如果有详细信息，添加到 title 属性
                        if (response.data && response.data.details) {
                            $result.attr('title', response.data.details);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    var message = '连接失败';
                    if (status === 'timeout') {
                        message = '请求超时，请检查网络连接';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    $result.text(message).addClass('error');
                },
                complete: function() {
                    $button.removeClass('is-testing').prop('disabled', false).text('测试连接');

                    // 8秒后清除结果（增加时间让用户看清错误信息）
                    setTimeout(function() {
                        $result.fadeOut(function() {
                            $(this).text('').removeClass('success error warning').removeAttr('title').show();
                        });
                    }, 8000);
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
            var $status = $header.find('.wpmind-status');
            var $apiKey = $card.find('input[type="password"], input[type="text"]').filter('[id^="api_key_"]');
            var hasKey = $apiKey.attr('placeholder') && $apiKey.attr('placeholder').length > 0;

            if (this.checked && (hasKey || $apiKey.val())) {
                if (!$status.length) {
                    $header.append('<span class="wpmind-status wpmind-status-active">' +
                        (wpmindL10n.enabled || '已启用') + '</span>');
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
                $apiKeyInput.prop('disabled', true).attr('placeholder', wpmindL10n.apiKeyCleared || 'API Key 将被清除');
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
            var hasInvalidKey = false;

            $('.wpmind-endpoint-card').each(function() {
                var $card = $(this);
                var $checkbox = $card.find('input[type="checkbox"]').not('.wpmind-clear-checkbox');
                var $apiKey = $card.find('input[type="password"], input[type="text"]').filter('[id^="api_key_"]');
                var $clearCheckbox = $card.find('.wpmind-clear-checkbox');
                var hasExistingKey = $apiKey.attr('placeholder') && $apiKey.attr('placeholder').length > 0;
                var willClear = $clearCheckbox.is(':checked');

                // 检查是否有无效的 API Key
                if ($apiKey.hasClass('is-invalid')) {
                    hasInvalidKey = true;
                    $card.css('border-color', '#d63638');
                    return false; // break
                }

                // 如果启用了但没有 key（新输入或已有）且不是要清除
                if ($checkbox.is(':checked') && !$apiKey.val() && !hasExistingKey && !willClear) {
                    hasEnabledWithoutKey = true;
                    $card.css('border-color', '#d63638');
                    $apiKey.focus();
                    return false; // break
                } else {
                    $card.css('border-color', '');
                }
            });

            if (hasInvalidKey) {
                e.preventDefault();
                alert('请修正 API Key 格式错误');
                return false;
            }

            if (hasEnabledWithoutKey) {
                e.preventDefault();
                alert(wpmindL10n.apiKeyRequired || '请为已启用的服务填写 API Key');
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
                $button.find('span:not(.dashicons)').text('高级设置');
            } else {
                $advanced.slideDown(200);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $button.find('span:not(.dashicons)').text('收起');
            }
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
    });

})(jQuery);
