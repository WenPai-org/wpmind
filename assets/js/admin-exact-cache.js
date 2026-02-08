/**
 * WPMind Admin Exact Cache handlers.
 *
 * @package WPMind
 * @since 3.6.0
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
	 * Exact Cache Manager
	 */
	var CacheManager = {
		chart: null,

		init: function() {
			this.bindEvents();
			this.loadStats();
		},

		bindEvents: function() {
			var self = this;
			$( '#wpmind-save-cache-settings' ).on( 'click', function() {
				self.saveSettings();
			} );
			$( '#wpmind-flush-cache' ).on( 'click', function() {
				self.flushCache();
			} );
			$( '#wpmind-reset-cache-stats' ).on( 'click', function() {
				self.resetStats();
			} );
			$( '.wpmind-refresh-cache-stats' ).on( 'click', function() {
				self.loadStats();
			} );
		},

		loadStats: function() {
			var self = this;
			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'GET',
				data: {
					action: 'wpmind_get_cache_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						self.updateCards( response.data.stats, response.data.savings );
						self.renderChart( response.data.daily );
					}
				}
			} );
		},

		updateCards: function( stats, savings ) {
			var hits = parseInt( stats.hits || 0, 10 );
			var misses = parseInt( stats.misses || 0, 10 );
			var total = hits + misses;
			var rate = total > 0 ? ( hits / total * 100 ).toFixed( 1 ) : '0';

			$( '#wpmind-cache-hit-rate' ).text( rate + '%' );
			$( '#wpmind-cache-entries' ).text( stats.entries || 0 );
			$( '#wpmind-cache-savings' ).text( '$' + ( savings.total_usd || 0 ) );
			$( '#wpmind-cache-total-req' ).text( total );
		},

		renderChart: function( daily ) {
			if ( typeof Chart === 'undefined' ) {
				return;
			}

			var canvas = document.getElementById( 'wpmind-cache-trend-canvas' );
			if ( ! canvas ) {
				return;
			}

			var labels = [];
			var hitsData = [];
			var missesData = [];

			$.each( daily, function( date, metrics ) {
				labels.push( date.substring( 5 ) ); // MM-DD
				hitsData.push( metrics.hits || 0 );
				missesData.push( metrics.misses || 0 );
			} );

			if ( this.chart ) {
				this.chart.destroy();
			}

			this.chart = new Chart( canvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: '命中',
							data: hitsData,
							borderColor: '#10b981',
							backgroundColor: 'rgba(16, 185, 129, 0.1)',
							fill: true,
							tension: 0.3,
							pointRadius: 3
						},
						{
							label: '未命中',
							data: missesData,
							borderColor: '#ef4444',
							backgroundColor: 'rgba(239, 68, 68, 0.1)',
							fill: true,
							tension: 0.3,
							pointRadius: 3
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'top',
							align: 'end',
							labels: {
								usePointStyle: true,
								pointStyle: 'circle',
								padding: 16,
								font: { size: 12 }
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: { precision: 0 }
						}
					}
				}
			} );
		},

		saveSettings: function() {
			var $button = $( '#wpmind-save-cache-settings' );
			var originalText = $button.html();

			$button.html( '<span class="dashicons ri-loader-4-line"></span> 保存中...' ).prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_cache_settings',
					nonce: wpmindData.nonce,
					enabled: $( 'input[name="wpmind_cache_enabled"]' ).is( ':checked' ) ? '1' : '0',
					default_ttl: $( 'input[name="wpmind_cache_default_ttl"]' ).val(),
					max_entries: $( 'input[name="wpmind_cache_max_entries"]' ).val(),
					scope_mode: $( 'select[name="wpmind_cache_scope_mode"]' ).val()
				},
				success: function( response ) {
					if ( response.success ) {
						$button.html( '<span class="dashicons ri-check-line"></span> 已保存' );
						Toast.success( '缓存设置已保存' );
					} else {
						$button.html( '<span class="dashicons ri-error-warning-line"></span> 保存失败' );
						Toast.error( response.data && response.data.message || '保存失败' );
					}
					setTimeout( function() {
						$button.html( originalText ).prop( 'disabled', false );
					}, 1500 );
				},
				error: function() {
					$button.html( '<span class="dashicons ri-error-warning-line"></span> 网络错误' );
					setTimeout( function() {
						$button.html( originalText ).prop( 'disabled', false );
					}, 2000 );
				}
			} );
		},

		flushCache: function() {
			if ( ! confirm( '确定要清空所有缓存条目吗？此操作不可撤销。' ) ) {
				return;
			}

			var self = this;
			var $button = $( '#wpmind-flush-cache' );
			$button.prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_flush_cache',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '缓存已清空' );
						self.loadStats();
					} else {
						Toast.error( '清空失败' );
					}
					$button.prop( 'disabled', false );
				},
				error: function() {
					Toast.error( '网络错误' );
					$button.prop( 'disabled', false );
				}
			} );
		},

		resetStats: function() {
			if ( ! confirm( '确定要重置所有统计数据吗？此操作不可撤销。' ) ) {
				return;
			}

			var self = this;
			var $button = $( '#wpmind-reset-cache-stats' );
			$button.prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_reset_cache_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '统计已重置' );
						self.loadStats();
					} else {
						Toast.error( '重置失败' );
					}
					$button.prop( 'disabled', false );
				},
				error: function() {
					Toast.error( '网络错误' );
					$button.prop( 'disabled', false );
				}
			} );
		}
	};

	Admin.CacheManager = CacheManager;

	/**
	 * Initialize on document ready.
	 */
	$( function() {
		if ( ! $( '#wpmind-save-cache-settings' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'exact-cache', function() {
			CacheManager.init();
		} );
	} );
} )( jQuery );