/**
 * WPMind Admin Media Intelligence handlers.
 *
 * @package WPMind
 * @since 4.3.0
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
	 * Media Intelligence Manager
	 */
	var MediaManager = {
		missingCount: 0,
		totalProcessed: 0,
		isProcessing: false,

		init: function() {
			this.bindEvents();
			this.loadStats();
		},

		bindEvents: function() {
			var self = this;

			$( '#wpmind-save-media-settings, #wpmind-save-media-safety' ).on( 'click', function() {
				self.saveSettings( $( this ) );
			} );
			$( '#wpmind-media-scan' ).on( 'click', function() {
				self.scanImages();
			} );
			$( '#wpmind-media-bulk-start' ).on( 'click', function() {
				self.startBulkProcess();
			} );
		},

		loadStats: function() {
			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'GET',
				data: {
					action: 'wpmind_media_get_stats',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						$( '#wpmind-media-total-gen' ).text( response.data.total_generated );
						$( '#wpmind-media-month-gen' ).text( response.data.month_generated );
					}
				}
			} );
		},

		saveSettings: function( $button ) {
			$button = $button || $( '#wpmind-save-media-settings' );
			var originalHtml = $button.html();

			$button.addClass( 'is-busy' ).text( '保存中…' ).prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_save_media_settings',
					nonce: wpmindData.nonce,
					auto_alt: $( 'input[name="wpmind_media_auto_alt"]' ).is( ':checked' ) ? '1' : '0',
					auto_title: $( 'input[name="wpmind_media_auto_title"]' ).is( ':checked' ) ? '1' : '0',
					nsfw_enabled: $( 'input[name="wpmind_media_nsfw_enabled"]' ).is( ':checked' ) ? '1' : '0',
					language: $( 'select[name="wpmind_media_language"]' ).val()
				},
				success: function( response ) {
					if ( response.success ) {
						$button.removeClass( 'is-busy' ).text( '已保存' );
						Toast.success( '媒体智能设置已保存' );
					} else {
						$button.removeClass( 'is-busy' ).text( '保存失败' );
						Toast.error( response.data && response.data.message || '保存失败' );
					}
					setTimeout( function() {
						$button.html( originalHtml ).prop( 'disabled', false );
					}, 1500 );
				},
				error: function() {
					$button.removeClass( 'is-busy' ).text( '网络错误' );
					setTimeout( function() {
						$button.html( originalHtml ).prop( 'disabled', false );
					}, 2000 );
				}
			} );
		},

		scanImages: function() {
			var self = this;
			var $button = $( '#wpmind-media-scan' );
			$button.prop( 'disabled', true );

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'GET',
				data: {
					action: 'wpmind_media_bulk_scan',
					nonce: wpmindData.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						self.missingCount = response.data.missing_alt;
						$( '#wpmind-media-missing' ).text( self.missingCount );

						if ( self.missingCount > 0 ) {
							$( '#wpmind-media-bulk-start' ).prop( 'disabled', false );
							Toast.info( '发现 ' + self.missingCount + ' 张图片缺少 Alt Text' );
						} else {
							Toast.success( '所有图片都已有 Alt Text' );
						}
					} else {
						Toast.error( '扫描失败' );
					}
					$button.prop( 'disabled', false );
				},
				error: function() {
					Toast.error( '网络错误' );
					$button.prop( 'disabled', false );
				}
			} );
		},

		startBulkProcess: function() {
			if ( this.isProcessing ) {
				return;
			}
			this.isProcessing = true;
			this.totalProcessed = 0;

			$( '#wpmind-media-bulk-start' ).prop( 'disabled', true );
			$( '.wpmind-media-progress' ).prop( 'hidden', false );

			this.processBatch();
		},

		setProgress: function( pct, labelText ) {
			var $bar = $( '#wpmind-media-progressbar' );
			$bar.attr( 'aria-valuenow', pct ).attr( 'aria-valuetext', labelText );
			$bar.children( 'i' ).css( 'width', pct + '%' );
			$bar.siblings( '.progress-pct' ).text( labelText );
		},

		processBatch: function() {
			var self = this;

			$.ajax( {
				url: wpmindData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmind_media_bulk_process',
					nonce: wpmindData.nonce,
					offset: self.totalProcessed
				},
				success: function( response ) {
					if ( ! response.success ) {
						self.finishBulk( '处理出错' );
						return;
					}

					self.totalProcessed += response.data.processed;

					var total = self.missingCount || 1;
					var pct = Math.min( 100, Math.round( self.totalProcessed / total * 100 ) );
					self.setProgress( pct, pct + '% (' + self.totalProcessed + '/' + total + ')' );

					if ( response.data.done ) {
						self.finishBulk( '批量处理完成，共处理 ' + self.totalProcessed + ' 张图片' );
					} else {
						self.processBatch();
					}
				},
				error: function() {
					self.finishBulk( '网络错误，已处理 ' + self.totalProcessed + ' 张' );
				}
			} );
		},

		finishBulk: function( message ) {
			this.isProcessing = false;
			this.setProgress( 100, '100%' );
			Toast.success( message );
			this.loadStats();

			setTimeout( function() {
				$( '#wpmind-media-bulk-start' ).prop( 'disabled', true );
				$( '.wpmind-media-progress' ).prop( 'hidden', true );
			}, 3000 );
		}
	};

	Admin.MediaManager = MediaManager;

	/**
	 * Initialize on document ready.
	 */
	$( function() {
		if ( ! $( '#wpmind-save-media-settings' ).length ) {
			return;
		}

		var safeInit = Admin.safeInit || function( label, fn ) {
			try {
				fn();
			} catch ( error ) {
				console.warn( '[WPMind] ' + label + ' init failed:', error );
			}
		};

		safeInit( 'media-intelligence', function() {
			MediaManager.init();
		} );
	} );
} )( jQuery );
