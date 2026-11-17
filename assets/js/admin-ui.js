/**
 * WPMind Admin UI helpers.
 *
 * @package WPMind
 * @since 3.3.0
 */

( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );

	/**
	 * HTML 转义函数 - 防止 XSS
	 */
	Admin.escapeHtml = function( text ) {
		if ( 'string' !== typeof text ) return '';
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	};

	/**
	 * 内联 SVG 图标（stroke 风格，与 PHP AdminIcons / kit Wenpai_Admin_Icons 一致）。
	 * path 为静态数据，直接拼接；class 由调用方给定。
	 */
	Admin.Icons = {
		svg: function( path, className ) {
			return '<svg class="' + ( className || 'wenpai-ico' ) + '" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
		}
	};

	/**
	 * Toast 通知系统 - 使用 WordPress 原生 notice 样式
	 */
	Admin.Toast = {
		container: null,

		init: function() {
			if ( ! this.container ) {
				// 通知容器挂在 kit 页头之后（页面顶部），兜底挂到 .wenpai-app 开头
				this.container = $( '<div class="wpmind-notice-container"></div>' );
				var $head = $( '.wenpai-page-head' ).first();
				if ( $head.length ) {
					$head.after( this.container );
				} else {
					$( '.wenpai-app' ).first().prepend( this.container );
				}
			}
		},

		show: function( message, type, duration ) {
			this.init();
			type = type || 'info';
			duration = duration || 3000;

			// WordPress 原生 notice 类型映射 + kit 色调类
			var noticeType = {
				success: 'notice-success ok',
				error: 'notice-error bad',
				warning: 'notice-warning warn',
				info: 'notice-info info'
			};

			// 图标：内联 SVG（stroke 风格，与 AdminIcons / kit 一致，禁用图标字体）
			var icons = {
				success: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/>',
				error: '<circle cx="12" cy="12" r="9"/><path d="m9.5 9.5 5 5M14.5 9.5l-5 5"/>',
				warning: '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4"/><circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="none"/>',
				info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="0.5" fill="currentColor" stroke="none"/>'
			};

			var $notice = $( '<div class="notice ' + noticeType[ type ] + ' is-dismissible wpmind-notice">' +
				'<p>' + Admin.Icons.svg( icons[ type ], 'wenpai-ico wpmind-notice-icon' ) + '<span class="wpmind-notice-text"></span></p>' +
				'</div>' );

			// 使用 .text() 防止 XSS
			$notice.find( '.wpmind-notice-text' ).text( message );

			this.container.append( $notice );

			// 添加 WordPress 原生关闭按钮
			$notice.append( '<button type="button" class="notice-dismiss"><span class="screen-reader-text">关闭此通知</span></button>' );

			// 动画显示
			$notice.hide().slideDown( 200 );

			// 关闭按钮事件
			$notice.find( '.notice-dismiss' ).on( 'click', function() {
				Admin.Toast.hide( $notice );
			} );

			// 自动关闭
			if ( 0 < duration ) {
				setTimeout( function() {
					Admin.Toast.hide( $notice );
				}, duration );
			}

			return $notice;
		},

		hide: function( $notice ) {
			$notice.slideUp( 200, function() {
				$( this ).remove();
			} );
		},

		success: function( message, duration ) {
			return this.show( message, 'success', duration );
		},

		error: function( message, duration ) {
			// 错误消息显示更长时间
			return this.show( message, 'error', duration || 8000 );
		},

		warning: function( message, duration ) {
			return this.show( message, 'warning', duration || 5000 );
		},

		info: function( message, duration ) {
			return this.show( message, 'info', duration );
		}
	};

	/**
	 * 确认对话框
	 */
	Admin.Dialog = {
		show: function( options ) {
			var defaults = {
				title: '确认操作',
				message: '确定要执行此操作吗？',
				confirmText: '确认执行',
				cancelText: '取消',
				type: 'warning',
				onConfirm: function() {},
				onCancel: function() {}
			};

			var settings = $.extend( {}, defaults, options );

			var icons = {
				warning: '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4"/><circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="none"/>',
				danger: '<circle cx="12" cy="12" r="9"/><path d="m9.5 9.5 5 5M14.5 9.5l-5 5"/>',
				info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="0.5" fill="currentColor" stroke="none"/>',
				success: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/>'
			};

			var $overlay = $( '<div class="wpmind-dialog-overlay"></div>' );
			var $dialog = $( '<div class="wpmind-dialog wpmind-dialog-' + settings.type + '">' +
				'<div class="wpmind-dialog-header">' +
				Admin.Icons.svg( icons[ settings.type ], 'wenpai-ico wpmind-dialog-icon' ) +
				'<span class="wpmind-dialog-title">' + settings.title + '</span>' +
				'</div>' +
				'<div class="wpmind-dialog-body">' +
				'<p>' + settings.message + '</p>' +
				'</div>' +
				'<div class="wpmind-dialog-footer">' +
				'<button type="button" class="btn btn-ghost wpmind-dialog-cancel">' + settings.cancelText + '</button>' +
				'<button type="button" class="btn btn-primary wpmind-dialog-confirm">' + settings.confirmText + '</button>' +
				'</div>' +
				'</div>' );

			$( 'body' ).append( $overlay ).append( $dialog );

			// 动画显示
			setTimeout( function() {
				$overlay.addClass( 'is-visible' );
				$dialog.addClass( 'is-visible' );
			}, 10 );

			// 关闭函数
			var close = function() {
				$overlay.removeClass( 'is-visible' );
				$dialog.removeClass( 'is-visible' );
				setTimeout( function() {
					$overlay.remove();
					$dialog.remove();
				}, 300 );
			};

			// 事件绑定
			$dialog.find( '.wpmind-dialog-cancel' ).on( 'click', function() {
				close();
				settings.onCancel();
			} );

			$dialog.find( '.wpmind-dialog-confirm' ).on( 'click', function() {
				close();
				settings.onConfirm();
			} );

			$overlay.on( 'click', function() {
				close();
				settings.onCancel();
			} );

			// ESC 关闭
			$( document ).on( 'keydown.wpmind-dialog', function( e ) {
				if ( 27 === e.keyCode ) {
					close();
					settings.onCancel();
					$( document ).off( 'keydown.wpmind-dialog' );
				}
			} );
		},

		confirm: function( message, onConfirm, onCancel ) {
			this.show( {
				message: message,
				onConfirm: onConfirm || function() {},
				onCancel: onCancel || function() {}
			} );
		}
	};
} )( jQuery );
