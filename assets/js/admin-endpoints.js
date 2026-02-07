/**
 * WPMind Admin endpoints handlers.
 *
 * @package WPMind
 * @since 3.3.0
 */

(function ($) {
	'use strict';

	var Admin = window.WPMindAdmin || (window.WPMindAdmin = {});
	var Toast = Admin.Toast || {
		success: function () { },
		error: function () { },
		warning: function () { },
		info: function () { }
	};
	var escapeHtml = Admin.escapeHtml || function (text) {
		return typeof text === 'string' ? text : '';
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
						var errorCode = (response.data && response.data.code) ? ' [' + escapeHtml(String(response.data.code)) + ']' : '';
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
				error: function (xhr, status) {
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
	 * Initialize on document ready
	 */
	$(function () {
		if (!$('.wpmind-endpoint-card').length && !$('#wpmind-settings-form').length) return;

		var safeInit = Admin.safeInit || function (label, fn) {
			try {
				fn();
			} catch (error) {
				console.warn('[WPMind] ' + label + ' init failed:', error);
			}
		};

		safeInit('endpoints:password-toggle', initPasswordToggle);
		safeInit('endpoints:collapse', initEndpointCollapse);
		safeInit('endpoints:key-validation', initApiKeyValidation);
		safeInit('endpoints:test-connection', initTestConnection);
		safeInit('endpoints:test-image', initImageTestConnection);
		safeInit('endpoints:status-update', initStatusUpdate);
		safeInit('endpoints:clear-key', initClearKeyHandler);
		safeInit('endpoints:form-validation', initFormValidation);
		safeInit('endpoints:advanced-toggle', initAdvancedToggle);
	});
})(jQuery);
