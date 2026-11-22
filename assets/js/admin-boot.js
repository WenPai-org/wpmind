/**
 * WPMind Admin boot.
 *
 * @package WPMind
 * @since 3.3.0
 */

( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );
	Admin.state = Admin.state || {
		chartsLoaded: false
	};

	Admin.safeInit = Admin.safeInit || function( label, fn ) {
		try {
			fn();
		} catch ( error ) {
			console.warn( '[WPMind] ' + label + ' init failed:', error );
		}
	};

	Admin.ensureChartsInit = Admin.ensureChartsInit || function() {
		if ( Admin.state.chartsLoaded ) {
			return;
		}
		if ( Admin.AnalyticsCharts && 'function' === typeof Admin.AnalyticsCharts.init ) {
			Admin.AnalyticsCharts.init();
			Admin.state.chartsLoaded = true;
		}
	};

	/**
	 * 兼容旧版 .wpmind-tab-link 跳转到 ?page=wpmind&tab=<id>
	 */
	function initLegacyTabLinks() {
		$( '.wpmind-tab-link' ).on( 'click', function( e ) {
			var tabId = $( this ).attr( 'data-tab-link' );
			if ( ! tabId ) {
				return;
			}
			e.preventDefault();
			window.location.href = window.location.pathname + '?page=wpmind&tab=' + encodeURIComponent( tabId );
		} );
	}

	/**
	 * 心思 AI 页交互：混用策略说明实时联动 + 刷新心思点数/测试连接。
	 */
	function initCloudControls() {
		var $desc = $( '#wpmind-cloud-mode-desc' );
		if ( $desc.length ) {
			var modeMap = {};
			try {
				modeMap = JSON.parse( $desc.attr( 'data-mode-descs' ) || '{}' );
			} catch ( e ) {
				modeMap = {};
			}
			$( document ).on( 'change', 'input[name="wpmind_cloud_mode"]', function() {
				var val = $( this ).val();
				if ( val && modeMap[ val ] ) {
					$desc.text( modeMap[ val ] );
				}
			} );
		}

		$( document ).on( 'click', '.wpmind-cloud-refresh', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}
			var origLabel = $btn.text();
			var ajaxUrl   = ( window.wpmindData && window.wpmindData.ajaxurl ) || window.ajaxurl || '';
			var nonce     = ( window.wpmindData && window.wpmindData.nonce ) || '';
			if ( ! ajaxUrl || ! nonce ) {
				return;
			}

			$btn.prop( 'disabled', true ).text( '查询中…' );
			$.post( ajaxUrl, {
				action: 'wpmind_cloud_refresh',
				nonce: nonce,
				token: $( '#wpmind_cloud_token' ).val() || '',
				base_url: $( '#wpmind_cloud_base_url' ).val() || ''
			} )
				.done( function( resp ) {
					if ( resp && resp.success && resp.data ) {
						if ( 'undefined' !== typeof resp.data.formatted_balance ) {
							$( '#wpmind-cloud-stat-balance' ).text( resp.data.formatted_balance );
							$( '#wpmind-cloud-stat-balance-unit' ).text( '心思点数' );
						}
						if ( 'undefined' !== typeof resp.data.formatted_used ) {
							$( '#wpmind-cloud-stat-used' ).text( resp.data.formatted_used );
						}
						Admin.Toast.success( resp.data.message || '点数余额已刷新。' );
					} else {
						var msg = ( resp && resp.data && resp.data.message ) || '刷新点数失败，请稍后重试。';
						Admin.Toast.error( msg );
					}
				} )
				.fail( function() {
					Admin.Toast.error( '网络请求失败，请稍后重试。' );
				} )
				.always( function() {
					$btn.prop( 'disabled', false ).text( origLabel );
				} );
		} );
	}

	/**
	 * Initialize on document ready
	 */
	$( function() {
		$( 'body' ).addClass( 'wpmind-js-loaded' );
		Admin.safeInit( 'tabs', initLegacyTabLinks );
		Admin.safeInit( 'cloud', initCloudControls );

		if ( $( '#wpmind-usage-trend-chart' ).length ) {
			Admin.safeInit( 'analytics', Admin.ensureChartsInit );
		}
	} );
} )( jQuery );
