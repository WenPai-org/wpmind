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
		if ( Admin.AnalyticsCharts && typeof Admin.AnalyticsCharts.init === 'function' ) {
			Admin.AnalyticsCharts.init();
			Admin.state.chartsLoaded = true;
		}
	};

	/**
	 * Tab 导航管理
	 */
	function initTabs() {
		var $tabs = $( '.wpmind-tab' );
		var $panes = $( '.wpmind-tab-pane' );

		if ( ! $tabs.length ) {
			// kit 壳页面没有 hash tab：旧模板遗留的 .wpmind-tab-link 改为跳 view 路由。
			$( '.wpmind-tab-link' ).on( 'click', function( e ) {
				var tabId = $( this ).attr( 'data-tab-link' );
				if ( ! tabId ) {
					return;
				}
				e.preventDefault();
				window.location.href = window.location.pathname + '?page=wpmind&tab=' + encodeURIComponent( tabId );
			} );
			return;
		}

		// 从 URL hash 恢复 Tab 状态，fallback 到第一个可用 Tab
		var firstTab = $tabs.first().data( 'tab' ) || 'services';
		var hash = window.location.hash.slice( 1 ) || firstTab;
		switchTab( hash );

		// Tab 点击事件
		$tabs.on( 'click', function( e ) {
			e.preventDefault();
			var tabId = $( this ).data( 'tab' );
			switchTab( tabId );
			history.replaceState( null, null, '#' + tabId );
		} );

		// 概览页快捷入口点击事件（直接绑定，避免 <a> 锚点干扰）
		$( '.wpmind-tab-link' ).on( 'click', function( e ) {
			e.preventDefault();
			var tabId = $( this ).attr( 'data-tab-link' );
			if ( tabId ) {
				switchTab( tabId );
				history.replaceState( null, null, '#' + tabId );
			}
			return false;
		} );

		function switchTab( tabId ) {
			// 验证 tabId 是否有效，fallback 到第一个可用 Tab
			if ( ! $( '#' + tabId ).length ) {
				tabId = firstTab;
			}

			$tabs.removeClass( 'wpmind-tab-active' );
			$tabs.filter( '[data-tab="' + tabId + '"]' ).addClass( 'wpmind-tab-active' );

			$panes.removeClass( 'wpmind-tab-pane-active' );
			$( '#' + tabId ).addClass( 'wpmind-tab-pane-active' );

			// 懒加载图表（仅在首次切换到数据分析时）
			if ( 'analytics' === tabId && ! Admin.state.chartsLoaded ) {
				Admin.ensureChartsInit();
			}
		}
	}

	/**
	 * AI 服务页简单/高级模式：切换 .wenpai-app.mode-advanced，localStorage 记忆。
	 */
	function initModeSwitch() {
		var $seg = $( '.wpmind-mode' );
		if ( ! $seg.length ) {
			return;
		}
		var $app = $( '.wenpai-app' );
		var apply = function( mode ) {
			$app.toggleClass( 'mode-advanced', 'advanced' === mode );
			$seg.find( 'a' ).removeClass( 'on' ).filter( '[data-mode="' + mode + '"]' ).addClass( 'on' );
		};
		var saved = null;
		try { saved = window.localStorage.getItem( 'wpmind_admin_mode' ); } catch ( e ) {}
		apply( 'advanced' === saved ? 'advanced' : 'simple' );
		$seg.on( 'click', 'a', function( e ) {
			e.preventDefault();
			var mode = $( this ).attr( 'data-mode' );
			apply( mode );
			try { window.localStorage.setItem( 'wpmind_admin_mode', mode ); } catch ( err ) {}
		} );
	}

	/**
	 * Initialize on document ready
	 */
	$( function() {
		// Health check
		$( 'body' ).addClass( 'wpmind-js-loaded' );
		if ( typeof wpmindData !== 'undefined' && wpmindData.version ) {
			console.log( '[WPMind] admin scripts v' + wpmindData.version + ' loaded' );
		}

		Admin.safeInit( 'tabs', initTabs );
		Admin.safeInit( 'mode-switch', initModeSwitch );

		// 图表懒加载：只在数据分析 Tab 激活时初始化
		if ( $( '#analytics' ).hasClass( 'wpmind-tab-pane-active' ) ) {
			Admin.safeInit( 'analytics', Admin.ensureChartsInit );
		}
	} );
} )( jQuery );
