/**
 * WPMind Admin GEO settings handlers.
 *
 * @package WPMind
 * @since 3.10.0
 */

( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );

	/**
	 * GEO Settings Manager
	 */
	var GeoManager = {
		init: function() {
			this.bindEvents();
			this.restoreSubTab();
		},

		bindEvents: function() {
			var self = this;
			$( '#wpmind-save-geo' ).on( 'click', function() {
				self.saveSettings();
			} );

			// Sub-tab switching.
			$( '.wpmind-geo-subtab' ).on( 'click', function() {
				var tab = $( this ).data( 'tab' );
				self.switchTab( tab );
			} );
		},

		switchTab: function( tab ) {
			// Update active button.
			$( '.wpmind-geo-subtab' ).removeClass( 'active' );
			$( '.wpmind-geo-subtab[data-tab="' + tab + '"]' ).addClass( 'active' );

			// Update active panel.
			$( '.wpmind-geo-tab-panel' ).removeClass( 'active' );
			$( '.wpmind-geo-tab-panel[data-panel="' + tab + '"]' ).addClass( 'active' );

			// Remember active tab.
			try {
				sessionStorage.setItem( 'wpmind_geo_subtab', tab );
			} catch ( e ) {}
		},

		restoreSubTab: function() {
			var tab = 'basics';
			try {
				var saved = sessionStorage.getItem( 'wpmind_geo_subtab' );
				if ( saved && $( '.wpmind-geo-subtab[data-tab="' + saved + '"]' ).length ) {
					tab = saved;
				}
			} catch ( e ) {}
			this.switchTab( tab );
		},

		saveSettings: function() {
			var $button = $( '#wpmind-save-geo' );
			var originalText = $button.html();

			// Collect all settings across all tabs.
			var settings = {
				// Basics tab.
				wpmind_geo_enabled: $( 'input[name="wpmind_geo_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_chinese_optimize: $( 'input[name="wpmind_chinese_optimize"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_geo_signals: $( 'input[name="wpmind_geo_signals"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_crawler_tracking: $( 'input[name="wpmind_crawler_tracking"]' ).is( ':checked' ) ? 1 : 0,

				// Content tab.
				wpmind_standalone_markdown_feed: $( 'input[name="wpmind_standalone_markdown_feed"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_llms_txt_enabled: $( 'input[name="wpmind_llms_txt_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_ai_sitemap_enabled: $( 'input[name="wpmind_ai_sitemap_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_ai_sitemap_max_entries: $( 'input[name="wpmind_ai_sitemap_max_entries"]' ).val() || 500,
				wpmind_ai_summary_enabled: $( 'input[name="wpmind_ai_summary_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_ai_summary_fallback: $( 'select[name="wpmind_ai_summary_fallback"]' ).val() || 'excerpt',

				// Schema tab.
				wpmind_schema_enabled: $( 'input[name="wpmind_schema_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_schema_mode: $( 'select[name="wpmind_schema_mode"]' ).val() || 'auto',
				wpmind_entity_linker_enabled: $( 'input[name="wpmind_entity_linker_enabled"]' ).is( ':checked' ) ? 1 : 0,

				// Control tab.
				wpmind_ai_indexing_enabled: $( 'input[name="wpmind_ai_indexing_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_ai_default_declaration: $( 'select[name="wpmind_ai_default_declaration"]' ).val() || 'original',
				wpmind_ai_excluded_post_types: [],
				wpmind_robots_ai_enabled: $( 'input[name="wpmind_robots_ai_enabled"]' ).is( ':checked' ) ? 1 : 0,
				wpmind_robots_ai_rules: {}
			};

			// Collect checked post type exclusions.
			$( 'input[name="wpmind_ai_excluded_post_types[]"]:checked' ).each( function() {
				settings.wpmind_ai_excluded_post_types.push( $( this ).val() );
			} );

			// Collect robots.txt AI rules.
			$( 'select[name^="wpmind_robots_ai_rules["]' ).each( function() {
				var name = $( this ).attr( 'name' );
				var match = name.match( /\[(.+?)\]/ );
				if ( match ) {
					settings.wpmind_robots_ai_rules[ match[1] ] = $( this ).val();
				}
			} );

			// Show loading state.
			$button.html( '<span class="dashicons ri-loader-4-line"></span> 保存中...' ).prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_geo_settings',
					nonce: wpmindData.nonce,
					settings: settings
				},
				success: function( response ) {
					if ( response.success ) {
						$button.html( '<span class="dashicons ri-check-line"></span> 已保存' );
						setTimeout( function() {
							$button.html( originalText ).prop( 'disabled', false );
							location.reload();
						}, 1500 );
					} else {
						$button.html( '<span class="dashicons ri-error-warning-line"></span> 保存失败' );
						setTimeout( function() {
							$button.html( originalText ).prop( 'disabled', false );
						}, 2000 );
					}
				},
				error: function() {
					$button.html( '<span class="dashicons ri-error-warning-line"></span> 网络错误' );
					setTimeout( function() {
						$button.html( originalText ).prop( 'disabled', false );
					}, 2000 );
				}
			} );
		}
	};

	Admin.GeoManager = GeoManager;

	/**
	 * Initialize on document ready
	 */
	$( function() {
		if ( ! $( '#wpmind-save-geo' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'geo', function() {
			GeoManager.init();
		} );
	} );
} )( jQuery );
