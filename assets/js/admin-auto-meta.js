/**
 * WPMind Admin Auto-Meta handlers.
 *
 * @package WPMind
 * @since 3.11.0
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
	 * Auto-Meta Manager
	 */
	var AutoMetaManager = {

		init: function() {
			this.bindEvents();
			this.restoreSubTab();
			this.loadStats();
		},

		bindEvents: function() {
			var self = this;

			// Sub-tab switching (scoped to auto-meta panel).
			$( '.wpmind-am-subtabs .wpmind-mi-subtab' ).on( 'click', function() {
				self.switchTab( $( this ).data( 'tab' ) );
			} );

			$( '#wpmind-save-am-settings' ).on( 'click', function() {
				self.saveSettings( $( this ) );
			} );
			$( '#wpmind-am-generate' ).on( 'click', function() {
				self.manualGenerate();
			} );
		},

		switchTab: function( tab ) {
			$( '.wpmind-am-subtabs .wpmind-mi-subtab' ).removeClass( 'active' );
			$( '.wpmind-am-subtabs .wpmind-mi-subtab[data-tab="' + tab + '"]' ).addClass( 'active' );

			$( '.wpmind-auto-meta-panel .wpmind-mi-tab-panel' ).removeClass( 'active' );
			$( '.wpmind-auto-meta-panel .wpmind-mi-tab-panel[data-panel="' + tab + '"]' ).addClass( 'active' );

			try {
				sessionStorage.setItem( 'wpmind_am_subtab', tab );
			} catch ( e ) {}
		},

		restoreSubTab: function() {
			var tab = 'am-settings';
			try {
				var saved = sessionStorage.getItem( 'wpmind_am_subtab' );
				if ( saved && $( '.wpmind-am-subtabs .wpmind-mi-subtab[data-tab="' + saved + '"]' ).length ) {
					tab = saved;
				}
			} catch ( e ) {}
			this.switchTab( tab );
		},

		loadStats: function() {
			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'GET',
				data: {
					action: 'wpmind_auto_meta_get_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						$( '#wpmind-am-total-gen' ).text( response.data.total_generated );
						$( '#wpmind-am-month-gen' ).text( response.data.month_generated );
					}
				}
			} );
		},

		saveSettings: function( $button ) {
			var originalText = $button.html();
			$button.html( '<span class="dashicons ri-loader-4-line"></span> 保存中...' ).prop( 'disabled', true );

			var postTypes = [];
			$( 'input[name="wpmind_auto_meta_post_types[]"]:checked' ).each( function() {
				postTypes.push( $( this ).val() );
			} );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_auto_meta_settings',
					nonce: wpmindData.nonce,
					enabled: '1',
					auto_excerpt: $( 'input[name="wpmind_auto_meta_excerpt"]' ).is( ':checked' ) ? '1' : '0',
					auto_tags: $( 'input[name="wpmind_auto_meta_tags"]' ).is( ':checked' ) ? '1' : '0',
					auto_category: $( 'input[name="wpmind_auto_meta_category"]' ).is( ':checked' ) ? '1' : '0',
					auto_faq: $( 'input[name="wpmind_auto_meta_faq"]' ).is( ':checked' ) ? '1' : '0',
					auto_seo_desc: $( 'input[name="wpmind_auto_meta_seo_desc"]' ).is( ':checked' ) ? '1' : '0',
					post_types: postTypes
				},
				success: function( response ) {
					if ( response.success ) {
						$button.html( '<span class="dashicons ri-check-line"></span> 已保存' );
						Toast.success( 'Auto-Meta 设置已保存' );
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

		manualGenerate: function() {
			var postId = $( '#wpmind-am-post-id' ).val();
			if ( ! postId || postId <= 0 ) {
				Toast.warning( '请输入有效的文章 ID' );
				return;
			}

			var $button = $( '#wpmind-am-generate' );
			var originalText = $button.html();
			$button.html( '<span class="dashicons ri-loader-4-line"></span> 生成中...' ).prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_auto_meta_generate',
					nonce: wpmindData.nonce,
					post_id: postId
				},
				success: function( response ) {
					if ( response.success ) {
						var d = response.data;
						$( '#wpmind-am-result-excerpt' ).text( d.excerpt || '--' );
						$( '#wpmind-am-result-tags' ).text( d.tags && d.tags.length ? d.tags.join( ', ' ) : '--' );
						$( '#wpmind-am-result-categories' ).text( d.categories && d.categories.length ? d.categories.join( ', ' ) : '--' );
						$( '#wpmind-am-result-seo' ).text( d.seo_description || '--' );

						var faqHtml = '--';
						if ( d.faq && d.faq.length ) {
							faqHtml = '<ul>';
							$.each( d.faq, function( i, item ) {
								faqHtml += '<li><strong>' + $( '<span>' ).text( item.question ).html() + '</strong><br>';
								faqHtml += $( '<span>' ).text( item.answer ).html() + '</li>';
							} );
							faqHtml += '</ul>';
						}
						$( '#wpmind-am-result-faq' ).html( faqHtml );

						$( '.wpmind-am-result' ).show();
						Toast.success( d.message || '生成成功' );
					} else {
						Toast.error( response.data && response.data.message || '生成失败' );
					}
					$button.html( originalText ).prop( 'disabled', false );
				},
				error: function() {
					Toast.error( '网络错误' );
					$button.html( originalText ).prop( 'disabled', false );
				}
			} );
		}
	};

	Admin.AutoMetaManager = AutoMetaManager;

	/**
	 * Initialize on document ready.
	 */
	$( function() {
		if ( ! $( '#wpmind-save-am-settings' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'auto-meta', function() {
			AutoMetaManager.init();
		} );
	} );
} )( jQuery );
