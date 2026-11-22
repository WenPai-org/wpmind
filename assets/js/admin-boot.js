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
	 * Initialize on document ready
	 */
	$( function() {
		$( 'body' ).addClass( 'wpmind-js-loaded' );
		Admin.safeInit( 'tabs', initLegacyTabLinks );

		if ( $( '#wpmind-usage-trend-chart' ).length ) {
			Admin.safeInit( 'analytics', Admin.ensureChartsInit );
		}
	} );
} )( jQuery );
