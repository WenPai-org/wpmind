/**
 * WPMind Admin budget & usage handlers.
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

	/**
	 * 格式化 token 数量
	 */
	function formatTokens( tokens ) {
		tokens = tokens || 0;
		if ( tokens >= 1000000 ) {
			return ( tokens / 1000000 ).toFixed( 2 ) + 'M';
		}
		if ( tokens >= 1000 ) {
			return ( tokens / 1000 ).toFixed( 1 ) + 'K';
		}
		return tokens.toString();
	}

	/**
	 * 格式化成本
	 */
	function formatCost( cost ) {
		cost = cost || 0;
		if ( cost < 0.01 ) {
			return '$' + cost.toFixed( 4 );
		}
		return '$' + cost.toFixed( 2 );
	}

	/**
	 * 更新用量显示
	 */
	function updateUsageDisplay( data ) {
		var today = data.today || {};
		var month = data.month || {};
		var total = ( data.stats && data.stats.total ) || {};

		$( '#today-tokens' ).text( formatTokens( ( today.input_tokens || 0 ) + ( today.output_tokens || 0 ) ) );
		$( '#today-cost' ).text( formatCost( today.cost || 0 ) );
		$( '#today-requests' ).text( today.requests || 0 );

		$( '#month-tokens' ).text( formatTokens( ( month.input_tokens || 0 ) + ( month.output_tokens || 0 ) ) );
		$( '#month-cost' ).text( formatCost( month.cost || 0 ) );
		$( '#month-requests' ).text( month.requests || 0 );

		$( '#total-tokens' ).text( formatTokens( ( total.input_tokens || 0 ) + ( total.output_tokens || 0 ) ) );
		$( '#total-cost' ).text( formatCost( total.cost || 0 ) );
		$( '#total-requests' ).text( total.requests || 0 );
	}

	/**
	 * 刷新用量统计
	 */
	function initUsageRefresh() {
		$( document ).on( 'click', '.wpmind-refresh-usage', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $( this );
			if ( $button.hasClass( 'is-loading' ) ) {
				return;
			}

			$button.addClass( 'is-loading is-busy' ).prop( 'disabled', true );

			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				$button.removeClass( 'is-loading is-busy' ).prop( 'disabled', false );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_get_usage_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						updateUsageDisplay( response.data );
						Toast.success( '统计已刷新' );
					}
				},
				error: function() {
					Toast.error( '刷新失败' );
				},
				complete: function() {
					$button.removeClass( 'is-loading is-busy' ).prop( 'disabled', false );
				}
			} );
		} );
	}

	/**
	 * 清除用量统计（确认门由 kit data-wenpai-confirm 统一接管）
	 */
	function initUsageClear() {
		$( document ).on( 'click', '.wpmind-clear-usage', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $( this );
			var originalHtml = $button.html();
			$button.prop( 'disabled', true ).addClass( 'is-busy' ).text( '处理中…' );

			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				$button.prop( 'disabled', false ).removeClass( 'is-busy' ).html( originalHtml );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_clear_usage_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '统计已清除' );
						updateUsageDisplay( {
							today: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 },
							month: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 },
							stats: { total: { input_tokens: 0, output_tokens: 0, cost: 0, requests: 0 } }
						} );
					} else {
						Toast.error( '清除失败' );
					}
				},
				error: function() {
					Toast.error( '清除失败' );
				},
				complete: function() {
					$button.prop( 'disabled', false ).removeClass( 'is-busy' ).html( originalHtml );
				}
			} );
		} );
	}

	/**
	 * 预算设置管理
	 */
	function initBudgetSettings() {
		$( '#wpmind_budget_enabled' ).on( 'change', function() {
			$( '#wpmind-budget-settings' ).prop( 'hidden', ! this.checked );
		} );

		$( 'input[name="email_alert"]' ).on( 'change', function() {
			$( '.wpmind-budget-email-field' ).prop( 'hidden', ! this.checked );
		} );

		$( '#wpmind-save-budget' ).on( 'click', function( e ) {
			e.preventDefault();

			var $button = $( this );
			if ( $button.prop( 'disabled' ) ) {
				return;
			}

			var originalText = $button.text();
			$button.prop( 'disabled', true ).addClass( 'is-busy' ).text( '保存中…' );

			var settings = {
				enabled: $( '#wpmind_budget_enabled' ).is( ':checked' ),
				global: {
					daily_limit_usd: parseFloat( $( 'input[name="daily_limit_usd"]' ).val() ) || 0,
					monthly_limit_usd: parseFloat( $( 'input[name="monthly_limit_usd"]' ).val() ) || 0,
					daily_limit_cny: parseFloat( $( 'input[name="daily_limit_cny"]' ).val() ) || 0,
					monthly_limit_cny: parseFloat( $( 'input[name="monthly_limit_cny"]' ).val() ) || 0,
					alert_threshold: parseInt( $( 'input[name="alert_threshold"]' ).val(), 10 ) || 80
				},
				enforcement_mode: $( 'select[name="enforcement_mode"]' ).val() || 'alert',
				notifications: {
					admin_notice: $( 'input[name="admin_notice"]' ).is( ':checked' ),
					email_alert: $( 'input[name="email_alert"]' ).is( ':checked' ),
					email_address: $( 'input[name="email_address"]' ).val() || ''
				}
			};

			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				$button.prop( 'disabled', false ).removeClass( 'is-busy' ).text( originalText );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_budget_settings',
					settings: JSON.stringify( settings ),
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '预算设置已保存' );
					} else {
						var msg = ( response.data && response.data.message ) || '保存失败';
						Toast.error( msg );
					}
				},
				error: function() {
					Toast.error( '保存失败，请重试' );
				},
				complete: function() {
					$button.prop( 'disabled', false ).removeClass( 'is-busy' ).text( originalText );
				}
			} );
		} );
	}

	/**
	 * Cost Control 设置保存
	 */
	function initCostControlSettings() {
		$( '#wpmind-save-cost-control' ).on( 'click', function() {
			var $button = $( this );
			var $spinner = $button.siblings( '.spinner' );

			$button.prop( 'disabled', true ).addClass( 'is-busy' );
			$spinner.addClass( 'is-active' );

			var settings = {
				enabled: $( '#wpmind_budget_enabled' ).is( ':checked' ),
				global: {
					daily_limit_usd: parseFloat( $( '#budget_daily_usd' ).val() ) || 0,
					daily_limit_cny: parseFloat( $( '#budget_daily_cny' ).val() ) || 0,
					monthly_limit_usd: parseFloat( $( '#budget_monthly_usd' ).val() ) || 0,
					monthly_limit_cny: parseFloat( $( '#budget_monthly_cny' ).val() ) || 0,
					alert_threshold: parseInt( $( '#budget_alert_threshold' ).val(), 10 ) || 80
				},
				enforcement_mode: $( '#budget_enforcement_mode' ).val(),
				notifications: {
					admin_notice: $( 'input[name="admin_notice"]' ).is( ':checked' ),
					email_alert: $( 'input[name="email_alert"]' ).is( ':checked' ),
					email_address: $( 'input[name="email_address"]' ).val() || ''
				}
			};

			if ( 'undefined' === typeof wpmindData ) {
				$button.prop( 'disabled', false ).removeClass( 'is-busy' );
				$spinner.removeClass( 'is-active' );
				Toast.error( '配置错误' );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_cost_control_settings',
					nonce: wpmindData.nonce,
					settings: JSON.stringify( settings )
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( ( response.data && response.data.message ) || '设置已保存' );
					} else {
						Toast.error( ( response.data && response.data.message ) || '保存失败' );
					}
				},
				error: function() {
					Toast.error( '保存失败' );
				},
				complete: function() {
					$button.prop( 'disabled', false ).removeClass( 'is-busy' );
					$spinner.removeClass( 'is-active' );
				}
			} );
		} );
	}

	/**
	 * Cost Control 清除统计（确认门由 kit data-wenpai-confirm 统一接管）
	 */
	function initCostControlClearUsage() {
		$( '#wpmind-clear-usage-stats' ).on( 'click', function() {
			var $button = $( this );
			var $spinner = $button.siblings( '.spinner' );

			$button.prop( 'disabled', true ).addClass( 'is-busy' );
			$spinner.addClass( 'is-active' );

			if ( 'undefined' === typeof wpmindData ) {
				$button.prop( 'disabled', false ).removeClass( 'is-busy' );
				$spinner.removeClass( 'is-active' );
				Toast.error( '配置错误' );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_clear_usage_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( ( response.data && response.data.message ) || '统计已清除' );
						setTimeout( function() {
							location.reload();
						}, 600 );
					} else {
						Toast.error( ( response.data && response.data.message ) || '清除失败' );
					}
				},
				error: function() {
					Toast.error( '清除失败' );
				},
				complete: function() {
					$button.prop( 'disabled', false ).removeClass( 'is-busy' );
					$spinner.removeClass( 'is-active' );
				}
			} );
		} );
	}

	$( function() {
		if (
			! $( '.wpmind-refresh-usage' ).length &&
			! $( '.wpmind-clear-usage' ).length &&
			! $( '#wpmind-save-budget' ).length &&
			! $( '#wpmind-save-cost-control' ).length &&
			! $( '#wpmind-clear-usage-stats' ).length &&
			! $( '#wpmind_budget_enabled' ).length
		) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'usage:refresh', initUsageRefresh );
		safeInit( 'usage:clear', initUsageClear );
		safeInit( 'budget:settings', initBudgetSettings );
		safeInit( 'cost-control:save', initCostControlSettings );
		safeInit( 'cost-control:clear', initCostControlClearUsage );
	} );
} )( jQuery );
