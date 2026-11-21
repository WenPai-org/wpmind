/**
 * WPMind Admin endpoints & tools handlers.
 *
 * @package WPMind
 * @since 3.3.0
 */

( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );
	var Toast = Admin.Toast || {
		success: function() {},
		error: function() {},
		warning: function() {},
		info: function() {}
	};
	var escapeHtml = Admin.escapeHtml || function( text ) {
		return 'string' === typeof text ? text : '';
	};

	function resultEl( $button ) {
		var $res = $button.siblings( '.wpmind-key__result' );
		if ( ! $res.length ) {
			$res = $button.siblings( '.wpmind-test-result' );
		}
		return $res;
	}

	/**
	 * Toggle password visibility
	 */
	function initPasswordToggle() {
		$( '.wpmind-toggle-key' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var targetId = $button.data( 'target' );
			var $target = $( '#' + targetId );

			if ( 'password' === $target.attr( 'type' ) ) {
				$target.attr( 'type', 'text' );
				$button.find( '.wpmind-key__eye-on, .wpmind-eye-on' ).prop( 'hidden', true );
				$button.find( '.wpmind-key__eye-off, .wpmind-eye-off' ).prop( 'hidden', false );
			} else {
				$target.attr( 'type', 'password' );
				$button.find( '.wpmind-key__eye-on, .wpmind-eye-on' ).prop( 'hidden', false );
				$button.find( '.wpmind-key__eye-off, .wpmind-eye-off' ).prop( 'hidden', true );
			}
		} );
	}

	/**
	 * API Key 输入处理
	 */
	function initApiKeyValidation() {
		$( 'input[id^="api_key_"]' ).on( 'input', function() {
			$( this ).removeClass( 'is-valid is-invalid' );
		} );
	}

	/**
	 * 启用开关同步启用/禁用测试按钮
	 */
	function initSwitchEnableSync() {
		$( '.wpmind-endpoint-card input[type="checkbox"][role="switch"]' ).on( 'change', function() {
			var $card = $( this ).closest( '.wpmind-endpoint-card, details.wenpai-fold' );
			$card.find( '.wpmind-test-connection, .wpmind-test-image-connection' ).prop( 'disabled', ! this.checked );
		} );
	}

	/**
	 * 测试连接功能
	 */
	function initTestConnection() {
		$( '.wpmind-test-connection' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var provider = $button.data( 'provider' );
			var $result = resultEl( $button );
			var $card = $button.closest( '.wpmind-endpoint-card, details.wenpai-fold' );

			var apiKey = $card.find( 'input[name*="[api_key]"]' ).val() || '';
			var customUrl = $card.find( 'input[name*="[custom_base_url]"]' ).val() || '';
			var deep = $card.find( '.wpmind-deep-test' ).is( ':checked' ) ? 1 : 0;

			$button.addClass( 'is-busy' ).prop( 'disabled', true ).text( '测试中…' );
			$result.text( '' ).removeClass( 'is-ok is-bad is-warn' ).removeAttr( 'title' );

			if ( 'undefined' === typeof wpmindData ) {
				$result.text( '配置错误' ).addClass( 'is-bad' );
				$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( '测试连接' );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_test_connection',
					provider: provider,
					api_key: apiKey,
					custom_url: customUrl,
					deep: deep,
					nonce: wpmindData.nonce
				},
				timeout: 45000,
				success: function( response ) {
					if ( response.success ) {
						var message = '连接成功';
						var extra = '';
						if ( response.data ) {
							if ( response.data.retried ) {
								extra += ' (重试后)';
							}
							if ( response.data.latency ) {
								extra += ' ' + response.data.latency + 'ms';
							}
						}
						$result.text( message + extra ).addClass( 'is-ok' );
						Toast.success( String( provider ).toUpperCase() + ' ' + message );
					} else {
						var errorMsg = ( response.data && response.data.message ) || '连接失败';
						var errorCode = ( response.data && response.data.code ) ? ' [' + escapeHtml( String( response.data.code ) ) + ']' : '';
						$result.text( errorMsg + errorCode ).addClass( 'is-bad' );
						Toast.error( String( provider ).toUpperCase() + ': ' + errorMsg );
					}
				},
				error: function( xhr, status ) {
					var message = 'timeout' === status ? '请求超时，请检查网络连接' : '网络错误，请检查连接';
					$result.text( message ).addClass( 'is-bad' );
					Toast.error( String( provider ).toUpperCase() + ': ' + message );
				},
				complete: function() {
					$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( '测试连接' );
				}
			} );
		} );
	}

	/**
	 * 测试图像服务连接功能
	 */
	function initImageTestConnection() {
		$( '.wpmind-test-image-connection' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var provider = $button.data( 'provider' );
			var $result = resultEl( $button );

			$button.addClass( 'is-busy' ).prop( 'disabled', true ).text( '测试中…' );
			$result.text( '' ).removeClass( 'is-ok is-bad' );

			if ( 'undefined' === typeof wpmindData ) {
				$result.text( '配置错误' ).addClass( 'is-bad' );
				$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( '测试连接' );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_test_image_connection',
					provider: provider,
					nonce: wpmindData.nonce
				},
				timeout: 45000,
				success: function( response ) {
					if ( response.success ) {
						$result.text( '连接成功' ).addClass( 'is-ok' );
						Toast.success( provider + ' 连接成功' );
					} else {
						var errorMsg = ( response.data && response.data.message ) || '连接失败';
						$result.text( errorMsg ).addClass( 'is-bad' );
						Toast.error( provider + ': ' + errorMsg );
					}
				},
				error: function( xhr, status ) {
					var message = 'timeout' === status ? '请求超时，请检查网络连接' : '网络错误，请检查连接';
					$result.text( message ).addClass( 'is-bad' );
					Toast.error( provider + ': ' + message );
				},
				complete: function() {
					$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( '测试连接' );
				}
			} );
		} );
	}

	/**
	 * Handle clear API key checkbox
	 */
	function initClearKeyHandler() {
		$( '.wpmind-clear-checkbox' ).on( 'change', function() {
			var $card = $( this ).closest( '.wpmind-endpoint-card, details.wenpai-fold' );
			var $apiKeyInput = $card.find( 'input[id^="api_key_"], input[id^="image_api_key_"]' );

			if ( this.checked ) {
				$apiKeyInput.prop( 'disabled', true ).attr( 'placeholder', 'API Key 将被清除' );
			} else {
				$apiKeyInput.prop( 'disabled', false ).attr( 'placeholder', '••••••••••••••••' );
			}
		} );
	}

	/**
	 * Form validation before submit
	 */
	function initFormValidation() {
		$( '#wpmind-settings-form' ).on( 'submit', function( e ) {
			var hasEnabledWithoutKey = false;
			var $problemCard = null;

			$( '#wpmind-settings-form .wpmind-endpoint-card' ).each( function() {
				var $card = $( this );
				var $apiKey = $card.find( 'input[id^="api_key_"]' );
				// 已由站点配置锁定的端点没有密钥输入框，直接放行。
				if ( ! $apiKey.length ) {
					return;
				}
				var $checkbox = $card.find( 'input[type="checkbox"][role="switch"]' );
				var $clearCheckbox = $card.find( '.wpmind-clear-checkbox' );
				var hasExistingKey = $apiKey.attr( 'placeholder' ) && -1 !== $apiKey.attr( 'placeholder' ).indexOf( '•' );
				var willClear = $clearCheckbox.is( ':checked' );

				if ( $checkbox.is( ':checked' ) && ! $apiKey.val() && ! hasExistingKey && ! willClear ) {
					hasEnabledWithoutKey = true;
					$problemCard = $card;
					return false;
				}
			} );

			if ( hasEnabledWithoutKey ) {
				e.preventDefault();
				Toast.error( '请为已启用的服务填写 API Key' );
				if ( $problemCard ) {
					var $fold = $problemCard.closest( 'details.wenpai-fold' );
					if ( $fold.length ) {
						$fold.prop( 'open', true );
					}
					$problemCard.find( 'input[id^="api_key_"]' ).focus();
				}
				return false;
			}
		} );
	}

	/**
	 * 验证并替换 API Key
	 */
	function initRotateKey() {
		$( '.wpmind-rotate-key' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var provider = $button.data( 'provider' );
			var $result = resultEl( $button );
			var $card = $button.closest( '.wpmind-endpoint-card, details.wenpai-fold' );
			var $apiKeyInput = $card.find( 'input[name*="[api_key]"]' );
			var apiKey = $apiKeyInput.val();
			var $customUrlInput = $card.find( 'input[name*="[custom_base_url]"]' );
			var customUrl = $customUrlInput.length ? $customUrlInput.val() : '';

			if ( ! apiKey ) {
				$result.text( '请先填写新的 API Key' ).removeClass( 'is-ok is-bad' ).addClass( 'is-bad' );
				Toast.warning( String( provider ).toUpperCase() + ': 请先填写新的 API Key' );
				return;
			}

			var origText = $button.text();
			$button.addClass( 'is-busy' ).prop( 'disabled', true ).text( '验证中…' );
			$result.text( '' ).removeClass( 'is-ok is-bad is-warn' );

			if ( 'undefined' === typeof wpmindData ) {
				$result.text( '配置错误' ).addClass( 'is-bad' );
				$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( origText );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_rotate_key',
					provider: provider,
					api_key: apiKey,
					custom_url: customUrl,
					nonce: wpmindData.nonce
				},
				timeout: 45000,
				success: function( response ) {
					if ( response.success ) {
						var message = ( response.data && response.data.message ) || '已替换';
						var extra = response.data && response.data.latency ? ' ' + response.data.latency + 'ms' : '';
						$result.text( message + extra ).addClass( 'is-ok' );
						Toast.success( String( provider ).toUpperCase() + ' ' + message );
						$apiKeyInput.val( '' );
						var fp = response.data && response.data.fingerprint ? String( response.data.fingerprint ) : '';
						var $meta = $card.find( '.wpmind-key-meta' );
						if ( fp && $meta.length ) {
							$meta.text( '指纹 ' + fp + '，留空表示不修改已保存的值。' );
						}
					} else {
						var errorMsg = ( response.data && response.data.message ) || '替换失败';
						$result.text( errorMsg ).addClass( 'is-bad' );
						Toast.error( String( provider ).toUpperCase() + ': ' + errorMsg );
					}
				},
				error: function( xhr, status ) {
					var message = 'timeout' === status ? '请求超时，请检查网络连接' : '请求失败';
					$result.text( message ).addClass( 'is-bad' );
					Toast.error( String( provider ).toUpperCase() + ': ' + message );
				},
				complete: function() {
					$button.removeClass( 'is-busy' ).prop( 'disabled', false ).text( origText );
				}
			} );
		} );
	}

	/**
	 * Reset circuit breaker for one provider
	 */
	function initResetBreaker() {
		$( '.wpmind-reset-breaker' ).on( 'click', function( e ) {
			e.preventDefault();
			var $button = $( this );
			var provider = $button.data( 'provider' );
			var $result = resultEl( $button );
			if ( 'undefined' === typeof wpmindData ) {
				return;
			}
			$button.prop( 'disabled', true );
			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_reset_circuit_breaker',
					provider: provider,
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					var msg = ( response.data && response.data.message ) || ( response.success ? '已重置' : '重置失败' );
					$result.text( msg ).removeClass( 'is-ok is-bad' ).addClass( response.success ? 'is-ok' : 'is-bad' );
					if ( response.success ) {
						Toast.success( provider + ': ' + msg );
					}
				},
				complete: function() {
					$button.prop( 'disabled', false );
				}
			} );
		} );
	}

	function downloadJson( filename, data ) {
		var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = filename || 'wpmind-export.json';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	}

	/**
	 * Diagnostics / config transfer toolbar
	 */
	function initDiagnosticsTools() {
		var pendingImportText = '';

		$( '#wpmind-export-diagnostics' ).on( 'click', function( e ) {
			e.preventDefault();
			if ( 'undefined' === typeof wpmindData ) {
				return;
			}
			$.post( wpmindData.ajaxurl || ajaxurl, {
				action: 'wpmind_export_diagnostics',
				nonce: wpmindData.nonce
			} ).done( function( response ) {
				if ( response.success && response.data ) {
					downloadJson( response.data.filename, response.data.data );
					Toast.success( '诊断包已下载' );
				} else {
					Toast.error( ( response.data && response.data.message ) || '导出失败' );
				}
			} );
		} );

		$( '#wpmind-export-config' ).on( 'click', function( e ) {
			e.preventDefault();
			if ( 'undefined' === typeof wpmindData ) {
				return;
			}
			$.post( wpmindData.ajaxurl || ajaxurl, {
				action: 'wpmind_export_provider_config',
				nonce: wpmindData.nonce
			} ).done( function( response ) {
				if ( response.success && response.data ) {
					downloadJson( response.data.filename, response.data.data );
					Toast.success( '端点配置已下载' );
				} else {
					Toast.error( ( response.data && response.data.message ) || '导出失败' );
				}
			} );
		} );

		$( '#wpmind-import-config-file' ).on( 'change', function() {
			var file = this.files && this.files[ 0 ];
			this.value = '';
			if ( ! file || 'undefined' === typeof wpmindData ) {
				return;
			}
			var reader = new FileReader();
			reader.onload = function() {
				var text = String( reader.result || '' );
				$.post( wpmindData.ajaxurl || ajaxurl, {
					action: 'wpmind_import_provider_config',
					nonce: wpmindData.nonce,
					config: text,
					confirm: '0'
				} ).done( function( response ) {
					if ( ! response.success ) {
						Toast.error( ( response.data && response.data.message ) || '配置校验失败' );
						return;
					}
					pendingImportText = text;
					var msg = ( response.data && response.data.message ) || '校验通过，点击「写入配置」确认导入。';
					$( '#wpmind-import-confirm-msg' ).text( msg );
					$( '#wpmind-import-confirm-box' ).prop( 'hidden', false );
				} );
			};
			reader.readAsText( file );
		} );

		$( '#wpmind-import-confirm-cancel' ).on( 'click', function() {
			pendingImportText = '';
			$( '#wpmind-import-confirm-box' ).prop( 'hidden', true );
		} );

		$( '#wpmind-import-confirm-apply' ).on( 'click', function() {
			if ( ! pendingImportText || 'undefined' === typeof wpmindData ) {
				return;
			}
			$.post( wpmindData.ajaxurl || ajaxurl, {
				action: 'wpmind_import_provider_config',
				nonce: wpmindData.nonce,
				config: pendingImportText,
				confirm: '1'
			} ).done( function( resp2 ) {
				if ( resp2.success ) {
					Toast.success( ( resp2.data && resp2.data.message ) || '配置已导入' );
					$( '#wpmind-import-confirm-box' ).prop( 'hidden', true );
					pendingImportText = '';
					window.setTimeout( function() {
						window.location.reload();
					}, 800 );
				} else {
					Toast.error( ( resp2.data && resp2.data.message ) || '导入失败' );
				}
			} );
		} );
	}

	/**
	 * 拉取远程模型列表并追加到「支持的模型」输入框
	 */
	function initFetchModels() {
		$( '.wpmind-fetch-models' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var provider = $button.data( 'provider' );
			var $card = $button.closest( '.wpmind-endpoint-card, details.wenpai-fold' );
			var apiKey = $card.find( 'input[name*="[api_key]"]' ).val() || '';
			var customUrl = $card.find( 'input[name*="[custom_base_url]"]' ).val() || '';
			var $modelsInput = $card.find( 'input[id^="models_"]' );
			var $hint = $button.siblings( '.wpmind-fetch-models-hint' );
			var origText = $button.text();

			if ( 'undefined' === typeof wpmindData ) {
				$hint.text( '配置错误' );
				return;
			}

			$button.prop( 'disabled', true ).text( '拉取中…' );
			$hint.text( '' );

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_fetch_models',
					provider: provider,
					api_key: apiKey,
					custom_url: customUrl,
					nonce: wpmindData.nonce
				},
				timeout: 30000,
				success: function( response ) {
					if ( response.success && response.data && response.data.ids ) {
						var ids = response.data.ids;
						var current = $modelsInput.val();
						var merged = current ? current.split( ',' ).map( function( s ) { return s.trim(); } ).filter( Boolean ) : [];
						ids.forEach( function( id ) {
							if ( -1 === merged.indexOf( id ) ) {
								merged.push( id );
							}
						} );
						$modelsInput.val( merged.join( ', ' ) );

						var parts = [ '已获取 ' + ids.length + ' 个对话模型' ];
						if ( response.data.filtered ) {
							parts.push( '过滤 ' + response.data.filtered + ' 个非对话模型' );
						}
						if ( response.data.from_cache ) {
							parts.push( '缓存' );
						}
						$hint.text( parts.join( '，' ) + '，已追加到上方列表' );
						Toast.success( provider + ': ' + ids.length + ' 个模型' );
					} else {
						var msg = ( response.data && response.data.message ) || '拉取失败';
						$hint.text( msg );
						Toast.error( provider + ': ' + msg );
					}
				},
				error: function( xhr, status ) {
					var message = 'timeout' === status ? '请求超时，请检查网络连接' : '网络错误，请检查连接';
					$hint.text( message );
					Toast.error( provider + ': ' + message );
				},
				complete: function() {
					$button.prop( 'disabled', false ).text( origText );
				}
			} );
		} );
	}

	$( function() {
		if ( ! $( '.wpmind-endpoint-card' ).length && ! $( '#wpmind-settings-form' ).length && ! $( '#wpmind-export-diagnostics' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'endpoints:password-toggle', initPasswordToggle );
		safeInit( 'endpoints:key-validation', initApiKeyValidation );
		safeInit( 'endpoints:switch-sync', initSwitchEnableSync );
		safeInit( 'endpoints:test-connection', initTestConnection );
		safeInit( 'endpoints:rotate-key', initRotateKey );
		safeInit( 'endpoints:reset-breaker', initResetBreaker );
		safeInit( 'endpoints:diagnostics', initDiagnosticsTools );
		safeInit( 'endpoints:test-image', initImageTestConnection );
		safeInit( 'endpoints:clear-key', initClearKeyHandler );
		safeInit( 'endpoints:form-validation', initFormValidation );
		safeInit( 'endpoints:fetch-models', initFetchModels );
	} );
} )( jQuery );
