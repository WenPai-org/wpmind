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
		if ( 'string' !== typeof text ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	};

	/**
	 * Toast 通知系统 — 优先委托 kit window.wenpaiToast，不可用时退回页顶轻提示。
	 */
	Admin.Toast = {
		container: null,

		init: function() {
			if ( ! this.container ) {
				this.container = $( '<div class="wpmind-notice"></div>' );
				var $head = $( '.wenpai-page-head' ).first();
				if ( $head.length ) {
					$head.after( this.container );
				} else {
					$( '.wenpai-app' ).first().prepend( this.container );
				}
			}
		},

		show: function( message, type, duration ) {
			type = type || 'info';
			if ( 'function' === typeof window.wenpaiToast ) {
				var toneMap = {
					success: 'ok',
					error: 'bad',
					warning: 'warn',
					info: ''
				};
				window.wenpaiToast( String( message || '' ), toneMap[ type ] || '' );
				return null;
			}

			this.init();
			duration = duration || 3000;
			var levelMap = {
				success: 'ok',
				error: 'bad',
				warning: 'warn',
				info: 'info'
			};
			var $notice = $( '<div class="notice ' + ( levelMap[ type ] || 'info' ) + '" role="status"><span></span></div>' );
			$notice.find( 'span' ).text( String( message || '' ) );
			this.container.append( $notice );

			if ( duration > 0 ) {
				setTimeout( function() {
					$notice.remove();
				}, duration );
			}
			return $notice;
		},

		success: function( message, duration ) {
			return this.show( message, 'success', duration );
		},

		error: function( message, duration ) {
			return this.show( message, 'error', duration || 6000 );
		},

		warning: function( message, duration ) {
			return this.show( message, 'warning', duration || 5000 );
		},

		info: function( message, duration ) {
			return this.show( message, 'info', duration );
		}
	};
} )( jQuery );
