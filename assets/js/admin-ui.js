/**
 * WPMind Admin UI helpers.
 *
 * @package WPMind
 * @since 3.3.0
 */

(function ($) {
	'use strict';

	var Admin = window.WPMindAdmin || (window.WPMindAdmin = {});

	/**
	 * HTML 转义函数 - 防止 XSS
	 */
	Admin.escapeHtml = function (text) {
		if (typeof text !== 'string') return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	};

	/**
	 * Toast 通知系统 - 使用 WordPress 原生 notice 样式
	 */
	Admin.Toast = {
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
				Admin.Toast.hide($notice);
			});

			// 自动关闭
			if (duration > 0) {
				setTimeout(function () {
					Admin.Toast.hide($notice);
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
	Admin.Dialog = {
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
})(jQuery);
