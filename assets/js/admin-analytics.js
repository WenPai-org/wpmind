/**
 * WPMind Admin analytics charts.
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
	 * Analytics Dashboard 图表管理
	 */
	var AnalyticsCharts = {
		charts: {},

		// 现代化配色方案
		colors: {
			primary: '#3858e9',
			primaryLight: 'rgba(56, 88, 233, 0.1)',
			secondary: '#10b981',
			secondaryLight: 'rgba(16, 185, 129, 0.1)',
			accent: '#f59e0b',
			danger: '#ef4444',
			gray: {
				50: '#f9fafb',
				100: '#f3f4f6',
				200: '#e5e7eb',
				300: '#d1d5db',
				400: '#9ca3af',
				500: '#6b7280',
				600: '#4b5563',
				700: '#374151',
				800: '#1f2937',
				900: '#111827'
			}
		},

		// 全局图表默认配置
		getDefaultOptions: function() {
			var self = this;
			return {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 750,
					easing: 'easeOutQuart'
				},
				plugins: {
					legend: {
						position: 'top',
						align: 'end',
						labels: {
							usePointStyle: true,
							pointStyle: 'circle',
							padding: 20,
							font: {
								family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
								size: 12,
								weight: '500'
							},
							color: self.colors.gray[ 600 ]
						}
					},
					tooltip: {
						backgroundColor: self.colors.gray[ 800 ],
						titleFont: {
							family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
							size: 13,
							weight: '600'
						},
						bodyFont: {
							family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
							size: 12
						},
						padding: 12,
						cornerRadius: 0,
						displayColors: true,
						boxPadding: 6
					}
				},
				scales: {
					x: {
						grid: {
							display: false
						},
						ticks: {
							font: {
								family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
								size: 11
							},
							color: self.colors.gray[ 500 ]
						}
					},
					y: {
						grid: {
							color: self.colors.gray[ 100 ],
							drawBorder: false
						},
						ticks: {
							font: {
								family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
								size: 11
							},
							color: self.colors.gray[ 500 ]
						}
					}
				}
			};
		},

		init: function() {
			if ( ! $( '#wpmind-usage-trend-chart' ).length ) {
				return;
			}

			var self = this;

			if ( 'undefined' === typeof Chart ) {
				// Chart.js 尚未加载，轮询等待
				var retries = 0;
				var maxRetries = 10;
				var timer = setInterval( function() {
					retries++;
					if ( 'undefined' !== typeof Chart ) {
						clearInterval( timer );
						self.loadData();
						self.bindEvents();
					} else if ( retries >= maxRetries ) {
						clearInterval( timer );
						$( '.wpmind-chart-container' ).html(
							'<p style="text-align:center;color:#6b7280;padding:2em 0;">' +
							'图表库加载失败，其他功能不受影响。</p>'
						);
					}
				}, 500 );
				return;
			}

			this.loadData();
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// 时间范围切换
			$( '#wpmind-analytics-range' ).on( 'change', function() {
				self.loadData();
			} );

			// 刷新按钮
			$( '.wpmind-refresh-analytics' ).on( 'click', function( e ) {
				e.preventDefault();
				var $btn = $( this );
				$btn.find( '.dashicons' ).addClass( 'wpmind-spinning' );
				self.loadData( function() {
					$btn.find( '.dashicons' ).removeClass( 'wpmind-spinning' );
				} );
			} );
		},

		loadData: function( callback ) {
			var self = this;
			var range = $( '#wpmind-analytics-range' ).val() || '7d';

			if ( 'undefined' === typeof wpmindData ) {
				return;
			}

			// 显示加载状态
			$( '.wpmind-chart-container' ).addClass( 'is-loading' );

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_get_analytics_data',
					range: range,
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success && response.data ) {
						self.renderCharts( response.data );
					} else {
						Toast.error( '加载分析数据失败' );
					}
				},
				error: function() {
					Toast.error( '加载分析数据失败，请稍后重试' );
				},
				complete: function() {
					$( '.wpmind-chart-container' ).removeClass( 'is-loading' );
					if ( 'function' === typeof callback ) {
						callback();
					}
				}
			} );
		},

		renderCharts: function( data ) {
			this.renderTrendChart( data.trend );
			this.renderProviderChart( data.providers );
			this.renderCostChart( data.cost );
			this.renderModelChart( data.models );
		},

		renderTrendChart: function( data ) {
			var ctx = document.getElementById( 'wpmind-usage-trend-chart' );
			if ( ! ctx ) {
				return;
			}

			if ( this.charts.trend ) {
				this.charts.trend.destroy();
			}

			var self = this;
			var options = this.getDefaultOptions();

			this.charts.trend = new Chart( ctx, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [ {
						label: 'Tokens',
						data: data.datasets.tokens,
						borderColor: self.colors.primary,
						backgroundColor: self.colors.primaryLight,
						fill: true,
						tension: 0.4,
						borderWidth: 2,
						pointRadius: 0,
						pointHoverRadius: 6,
						pointHoverBackgroundColor: self.colors.primary,
						pointHoverBorderColor: '#fff',
						pointHoverBorderWidth: 2,
						yAxisID: 'y'
					}, {
						label: '请求数',
						data: data.datasets.requests,
						borderColor: self.colors.secondary,
						backgroundColor: 'transparent',
						fill: false,
						tension: 0.4,
						borderWidth: 2,
						borderDash: [ 5, 5 ],
						pointRadius: 0,
						pointHoverRadius: 6,
						pointHoverBackgroundColor: self.colors.secondary,
						pointHoverBorderColor: '#fff',
						pointHoverBorderWidth: 2,
						yAxisID: 'y1'
					} ]
				},
				options: $.extend( true, {}, options, {
					interaction: {
						mode: 'index',
						intersect: false
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: 'Tokens',
								font: { size: 11, weight: '500' },
								color: self.colors.gray[ 500 ]
							},
							grid: {
								color: self.colors.gray[ 100 ],
								drawBorder: false
							},
							ticks: {
								font: { size: 11 },
								color: self.colors.gray[ 500 ]
							}
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							title: {
								display: true,
								text: '请求数',
								font: { size: 11, weight: '500' },
								color: self.colors.gray[ 500 ]
							},
							grid: {
								drawOnChartArea: false
							},
							ticks: {
								font: { size: 11 },
								color: self.colors.gray[ 500 ]
							}
						}
					}
				} )
			} );
		},

		renderProviderChart: function( data ) {
			var ctx = document.getElementById( 'wpmind-provider-chart' );
			if ( ! ctx ) {
				return;
			}

			if ( this.charts.provider ) {
				this.charts.provider.destroy();
			}

			if ( ! data.labels || 0 === data.labels.length ) {
				return;
			}

			var self = this;

			this.charts.provider = new Chart( ctx, {
				type: 'doughnut',
				data: {
					labels: data.labels,
					datasets: [ {
						data: data.datasets.requests,
						backgroundColor: data.colors,
						borderWidth: 0,
						hoverOffset: 8
					} ]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: '65%',
					animation: {
						animateRotate: true,
						animateScale: true
					},
					plugins: {
						legend: {
							position: 'right',
							labels: {
								usePointStyle: true,
								pointStyle: 'circle',
								padding: 16,
								font: {
									family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
									size: 12
								},
								color: self.colors.gray[ 600 ]
							}
						},
						tooltip: {
							backgroundColor: self.colors.gray[ 800 ],
							titleFont: { size: 13, weight: '600' },
							bodyFont: { size: 12 },
							padding: 12,
							cornerRadius: 0,
							callbacks: {
								label: function( context ) {
									var total = context.dataset.data.reduce( function( a, b ) {
										return a + b;
									}, 0 );
									var percentage = ( ( context.raw / total ) * 100 ).toFixed( 1 );
									return context.label + ': ' + context.raw + ' (' + percentage + '%)';
								}
							}
						}
					}
				}
			} );
		},

		renderCostChart: function( data ) {
			var ctx = document.getElementById( 'wpmind-cost-chart' );
			if ( ! ctx ) {
				return;
			}

			if ( this.charts.cost ) {
				this.charts.cost.destroy();
			}

			var self = this;
			var options = this.getDefaultOptions();

			this.charts.cost = new Chart( ctx, {
				type: 'bar',
				data: {
					labels: data.labels,
					datasets: [ {
						label: 'USD',
						data: data.datasets.cost_usd,
						backgroundColor: self.colors.primary,
						borderColor: self.colors.primary,
						borderWidth: 0,
						borderRadius: 0,
						barPercentage: 0.7,
						categoryPercentage: 0.8
					}, {
						label: 'CNY',
						data: data.datasets.cost_cny,
						backgroundColor: self.colors.danger,
						borderColor: self.colors.danger,
						borderWidth: 0,
						borderRadius: 0,
						barPercentage: 0.7,
						categoryPercentage: 0.8
					} ]
				},
				options: $.extend( true, {}, options, {
					scales: {
						y: {
							beginAtZero: true,
							title: {
								display: true,
								text: '费用',
								font: { size: 11, weight: '500' },
								color: self.colors.gray[ 500 ]
							},
							grid: {
								color: self.colors.gray[ 100 ],
								drawBorder: false
							},
							ticks: {
								font: { size: 11 },
								color: self.colors.gray[ 500 ]
							}
						}
					}
				} )
			} );
		},

		renderModelChart: function( data ) {
			var ctx = document.getElementById( 'wpmind-model-chart' );
			if ( ! ctx ) {
				return;
			}

			if ( this.charts.model ) {
				this.charts.model.destroy();
			}

			if ( ! data.labels || 0 === data.labels.length ) {
				return;
			}

			var self = this;

			this.charts.model = new Chart( ctx, {
				type: 'bar',
				data: {
					labels: data.labels,
					datasets: [ {
						label: '请求数',
						data: data.datasets.requests,
						backgroundColor: self.colors.primary,
						borderColor: self.colors.primary,
						borderWidth: 0,
						borderRadius: 0,
						barPercentage: 0.6
					} ]
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					animation: {
						duration: 750,
						easing: 'easeOutQuart'
					},
					plugins: {
						legend: {
							display: false
						},
						tooltip: {
							backgroundColor: self.colors.gray[ 800 ],
							titleFont: { size: 13, weight: '600' },
							bodyFont: { size: 12 },
							padding: 12,
							cornerRadius: 0
						}
					},
					scales: {
						x: {
							beginAtZero: true,
							title: {
								display: true,
								text: '请求数',
								font: { size: 11, weight: '500' },
								color: self.colors.gray[ 500 ]
							},
							grid: {
								color: self.colors.gray[ 100 ],
								drawBorder: false
							},
							ticks: {
								font: { size: 11 },
								color: self.colors.gray[ 500 ]
							}
						},
						y: {
							grid: {
								display: false
							},
							ticks: {
								font: { size: 11 },
								color: self.colors.gray[ 600 ]
							}
						}
					}
				}
			} );
		}
	};

	Admin.AnalyticsCharts = AnalyticsCharts;

	/**
	 * Initialize on document ready
	 */
	$( function() {
		if ( ! $( '#wpmind-usage-trend-chart' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		if ( $( '#analytics' ).hasClass( 'wpmind-tab-pane-active' ) ) {
			safeInit( 'analytics', Admin.ensureChartsInit || AnalyticsCharts.init.bind( AnalyticsCharts ) );
		}
	} );
} )( jQuery );
