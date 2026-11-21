/**
 * WPMind Admin routing panel handlers.
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
	 * Routing Panel 路由管理
	 */
	var RoutingManager = {
		init: function() {
			if ( ! $( '.wpmind-routing-panel' ).length ) {
				return;
			}
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// 策略选择（kit seg_radio 的 radio change 事件）
			$( document ).on( 'change', 'input[name="routing_strategy"]', function() {
				self.setStrategy( $( this ).val() );
			} );

			// 刷新路由状态
			$( document ).on( 'click', '.wpmind-refresh-routing', function( e ) {
				e.preventDefault();
				var $btn = $( this );
				$btn.addClass( 'is-busy' ).prop( 'disabled', true );
				self.refreshStatus( function() {
					$btn.removeClass( 'is-busy' ).prop( 'disabled', false );
				} );
			} );

			// 保存优先级
			$( document ).on( 'click', '.wpmind-save-priority', function( e ) {
				e.preventDefault();
				self.savePriority();
			} );

			// 清除优先级（确认门由 kit data-wenpai-confirm 统一接管）
			$( document ).on( 'click', '.wpmind-clear-priority', function( e ) {
				e.preventDefault();
				self.clearPriority();
			} );
		},

		getPriorityOrder: function() {
			var raw = $( '#wpmind-priority-order' ).val();
			if ( raw ) {
				return String( raw ).split( ',' ).map( function( item ) {
					return item.trim();
				} ).filter( Boolean );
			}
			var priority = [];
			$( '#wpmind-priority-list [data-wenpai-sortable-item]' ).each( function() {
				var id = $( this ).attr( 'data-wenpai-sortable-item' );
				if ( id ) {
					priority.push( id );
				}
			} );
			return priority;
		},

		savePriority: function() {
			var self = this;
			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				return;
			}

			var priority = self.getPriorityOrder();
			if ( 0 === priority.length ) {
				Toast.warning( '没有可排序的服务商' );
				return;
			}

			var $btn = $( '.wpmind-save-priority' );
			$btn.prop( 'disabled', true ).addClass( 'is-busy' );

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_set_provider_priority',
					priority: priority,
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '优先级已保存' );
						if ( ! $( '.wpmind-clear-priority' ).length ) {
							var $clear = $( '<button type="button" class="btn btn-sm btn-ghost wpmind-clear-priority">清除优先级</button>' );
							$clear.attr( 'data-wenpai-confirm', '清除后将恢复由当前路由策略自动排序。' );
							$clear.attr( 'data-wenpai-confirm-title', '清除手动优先级' );
							$clear.attr( 'data-wenpai-confirm-ok', '确认清除' );
							$( '.wpmind-routing-priority-actions' ).prepend( $clear );
						}
						if ( ! $( '.wpmind-priority-badge' ).length ) {
							$( '.wpmind-routing-priority .card-sub' ).append(
								' <span class="wenpai-micro-tag wpmind-priority-badge">手动优先级生效中</span>'
							);
						}
						self.refreshStatus();
					} else {
						var msg = ( response.data && response.data.message ) || '保存失败';
						Toast.error( msg );
					}
				},
				error: function() {
					Toast.error( '保存失败，请重试' );
				},
				complete: function() {
					$btn.prop( 'disabled', false ).removeClass( 'is-busy' );
				}
			} );
		},

		clearPriority: function() {
			var self = this;
			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				return;
			}

			var $btn = $( '.wpmind-clear-priority' );
			$btn.prop( 'disabled', true ).addClass( 'is-busy' );

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_set_provider_priority',
					priority: [],
					clear: 1,
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '手动优先级已清除' );
						$( '.wpmind-clear-priority' ).remove();
						$( '.wpmind-priority-badge' ).remove();
						self.refreshStatus();
					} else {
						var msg = ( response.data && response.data.message ) || '清除失败';
						Toast.error( msg );
					}
				},
				error: function() {
					Toast.error( '清除失败，请重试' );
				},
				complete: function() {
					$btn.prop( 'disabled', false ).removeClass( 'is-busy' );
				}
			} );
		},

		setStrategy: function( strategy ) {
			if ( 'undefined' === typeof wpmindData ) {
				Toast.error( '配置错误' );
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_set_routing_strategy',
					strategy: strategy,
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						Toast.success( '路由策略已更新' );
						RoutingManager.refreshStatus();
					} else {
						var msg = ( response.data && response.data.message ) || '更新失败';
						Toast.error( msg );
					}
				},
				error: function() {
					Toast.error( '更新失败，请重试' );
				}
			} );
		},

		refreshStatus: function( callback ) {
			if ( 'undefined' === typeof wpmindData ) {
				if ( 'function' === typeof callback ) {
					callback();
				}
				return;
			}

			$.ajax( {
				url: wpmindData.ajaxurl || ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_get_routing_status',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success && response.data ) {
						RoutingManager.updateDisplay( response.data );
						Toast.success( '路由状态已刷新' );
					}
				},
				error: function() {
					Toast.error( '刷新失败' );
				},
				complete: function() {
					if ( 'function' === typeof callback ) {
						callback();
					}
				}
			} );
		},

		updateDisplay: function( data ) {
			// 更新 kit meter 得分排名
			var $scores = $( '#wpmind-routing-scores' );
			if ( $scores.length && data.provider_scores ) {
				$scores.empty();
				var rank = 0;
				$.each( data.provider_scores, function( providerId, scoreData ) {
					rank++;
					var r = scoreData.rank || rank;
					var score = Number( scoreData.score ) || 0;
					var pct = Math.max( 0, Math.min( 100, score ) );
					var $item = $( '<li class="wenpai-meter__row"></li>' );
					if ( 1 === r ) {
						$item.addClass( 'is-top' );
					}
					$item.append( $( '<span class="wenpai-meter__rank"></span>' ).text( String( r ) ) );
					$item.append( $( '<span class="wenpai-meter__name"></span>' ).text( scoreData.name || providerId ) );
					var $fill = $( '<i aria-hidden="true"></i>' ).css( 'inline-size', pct + '%' );
					$item.append( $( '<span class="wenpai-meter__bar" aria-hidden="true"></span>' ).append( $fill ) );
					$item.append( $( '<span class="wenpai-meter__value"></span>' ).text( score.toFixed( 1 ) ) );
					$scores.append( $item );
				} );
			}

			// 更新首选服务商
			if ( data.recommended && data.provider_scores && data.provider_scores[ data.recommended ] ) {
				var recommendedData = data.provider_scores[ data.recommended ];
				$( '#wpmind-recommended-provider' ).text( recommendedData.name );
				$( '.wpmind-routing-status-score-value' ).text( Number( recommendedData.score || 0 ).toFixed( 1 ) );
			}

			// 更新 kit tag_feed 故障转移链
			var $failoverChain = $( '#wpmind-failover-chain' );
			if ( $failoverChain.length && data.failover_chain && data.failover_chain.length ) {
				$failoverChain.empty();
				$.each( data.failover_chain, function( index, provider ) {
					var isFirst = 0 === index;
					var name = ( data.provider_scores && data.provider_scores[ provider ] && data.provider_scores[ provider ].name ) || provider;
					var $li = $( '<li></li>' );
					$li.append(
						$( '<span class="wenpai-micro-tag"></span>' )
							.addClass( isFirst ? 'is-ok' : '' )
							.text( isFirst ? '主通道' : '备选 ' + index )
					);
					$li.append(
						$( '<span class="wenpai-tag-feed__text"></span>' ).append(
							$( '<span></span>' ).text( name )
						)
					);
					$li.append(
						$( '<span class="wenpai-tag-feed__side"></span>' ).append(
							$( '<span></span>' ).text( '#' + ( index + 1 ) )
						)
					);
					$failoverChain.append( $li );
				} );
			}
		}
	};

	Admin.RoutingManager = RoutingManager;

	$( function() {
		if ( ! $( '.wpmind-routing-panel' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'routing', function() {
			RoutingManager.init();
		} );
	} );
} )( jQuery );
