/**
 * WPMind Admin modules toggle handlers.
 *
 * @package WPMind
 * @since 3.3.0
 */

( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );
	var Toast = Admin.Toast || {
		success: function() {},
		error: function() {}
	};

	function toast( message, tone ) {
		if ( 'function' === typeof window.wenpaiToast ) {
			window.wenpaiToast( message, tone || 'ok' );
		} else if ( 'bad' === tone ) {
			Toast.error( message );
		} else {
			Toast.success( message );
		}
	}

	function toggleModule( moduleId, enable, onDone, onFail ) {
		if ( 'undefined' === typeof wpmindData ) {
			toast( '配置错误', 'bad' );
			if ( 'function' === typeof onFail ) {
				onFail();
			}
			return;
		}

		$.ajax( {
			url: wpmindData.ajaxurl,
			type: 'POST',
			data: {
				action: 'wpmind_toggle_module',
				nonce: wpmindData.nonce,
				module_id: moduleId,
				enable: enable ? '1' : '0'
			},
			success: function( response ) {
				if ( response && response.success ) {
					toast( '已保存', 'ok' );
					if ( response.data && response.data.reload ) {
						window.setTimeout( function() {
							window.location.reload();
						}, 250 );
					} else if ( 'function' === typeof onDone ) {
						onDone( response );
					}
				} else {
					toast( ( response && response.data && response.data.message ) || '保存失败', 'bad' );
					if ( 'function' === typeof onFail ) {
						onFail();
					}
				}
			},
			error: function() {
				toast( '网络错误，请稍后重试', 'bad' );
				if ( 'function' === typeof onFail ) {
					onFail();
				}
			}
		} );
	}

	function initModuleSwitches() {
		$( '.wpmind-module-switch' ).on( 'change', function() {
			var $switch = $( this );
			var moduleId = $switch.data( 'module-id' );
			var enable = $switch.is( ':checked' );
			var $card = $switch.closest( '.wpmind-module-item, .wenpai-module-card' );

			$switch.prop( 'disabled', true );
			toggleModule(
				moduleId,
				enable,
				function() {
					$card.toggleClass( 'is-enabled', enable ).toggleClass( 'is-disabled', ! enable );
					$switch.prop( 'disabled', false );
				},
				function() {
					$switch.prop( 'checked', ! enable ).prop( 'disabled', false );
				}
			);
		} );

		// 空态一键开启按钮（例如分析模块未启用时）。
		$( '.wpmind-enable-module-btn' ).on( 'click', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var moduleId = $btn.data( 'module-id' );
			if ( ! moduleId ) {
				return;
			}
			$btn.prop( 'disabled', true );
			toggleModule(
				moduleId,
				true,
				function() {
					window.location.reload();
				},
				function() {
					$btn.prop( 'disabled', false );
				}
			);
		} );
	}

	$( function() {
		if ( ! $( '.wpmind-module-switch' ).length && ! $( '.wpmind-enable-module-btn' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'modules', initModuleSwitches );
	} );
} )( jQuery );
