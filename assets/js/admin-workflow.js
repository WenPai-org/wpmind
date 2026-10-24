/**
 * WPMind Workflow / unified tasks admin handlers.
 *
 * @package WPMind
 * @since 0.14.0
 */
( function( $ ) {
	'use strict';

	var Admin = window.WPMindAdmin || ( window.WPMindAdmin = {} );
	var Toast = Admin.Toast || {
		success: function() {},
		error: function() {},
		info: function() {}
	};
	var cfg = window.wpmindWorkflow || {};
	var i18n = cfg.i18n || {};

	var Workflow = {
		currentTaskId: 0,

		init: function() {
			var self = this;
			$( '#wpmind-workflow-alt-create' ).on( 'click', function() {
				self.createAltTask();
			} );
			$( '#wpmind-workflow-alt-dryrun' ).on( 'click', function() {
				self.dryRunLoop();
			} );
			$( '#wpmind-workflow-refresh' ).on( 'click', function() {
				self.refreshList();
			} );
			$( document ).on( 'click', '.wpmind-workflow-view', function() {
				self.loadTask( parseInt( $( this ).data( 'task-id' ), 10 ) || 0 );
			} );

			// Media tab shortcut buttons (optional).
			$( '#wpmind-media-workflow-create' ).on( 'click', function() {
				self.createAltTask();
			} );
			$( '#wpmind-media-workflow-dryrun' ).on( 'click', function() {
				self.dryRunLoop();
			} );
		},

		createAltTask: function() {
			var self = this;
			var $btn = $( '#wpmind-workflow-alt-create, #wpmind-media-workflow-create' );
			$btn.prop( 'disabled', true );
			$( '#wpmind-workflow-status' ).text( i18n.creating || '…' );

			$.post( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_alt_create',
				nonce: wpmindData.nonce
			} ).done( function( res ) {
				if ( ! res.success ) {
					Toast.error( ( res.data && res.data.message ) || 'error' );
					return;
				}
				self.currentTaskId = res.data.task.id;
				$( '#wpmind-workflow-alt-dryrun, #wpmind-media-workflow-dryrun' ).prop( 'disabled', false );
				Toast.success( res.data.message || 'ok' );
				$( '#wpmind-workflow-status' ).text( res.data.message || '' );
				self.refreshList();
				self.renderItems( res.data.task );
			} ).fail( function() {
				Toast.error( 'network' );
			} ).always( function() {
				$btn.prop( 'disabled', false );
			} );
		},

		dryRunLoop: function() {
			var self = this;
			if ( ! self.currentTaskId ) {
				Toast.error( 'no task' );
				return;
			}
			var $btn = $( '#wpmind-workflow-alt-dryrun, #wpmind-media-workflow-dryrun' );
			$btn.prop( 'disabled', true );
			$( '#wpmind-workflow-status' ).text( i18n.dryRunning || '…' );

			function round() {
				$.post( wpmindData.ajaxurl, {
					action: 'wpmind_workflow_alt_dry_run',
					nonce: wpmindData.nonce,
					task_id: self.currentTaskId
				} ).done( function( res ) {
					if ( ! res.success ) {
						Toast.error( ( res.data && res.data.message ) || 'error' );
						$btn.prop( 'disabled', false );
						return;
					}
					self.renderItems( res.data.task );
					self.refreshList();
					if ( res.data.done ) {
						Toast.success( i18n.done || 'done' );
						$( '#wpmind-workflow-status' ).text( i18n.done || 'done' );
						$btn.prop( 'disabled', false );
					} else {
						round();
					}
				} ).fail( function() {
					Toast.error( 'network' );
					$btn.prop( 'disabled', false );
				} );
			}
			round();
		},

		loadTask: function( id ) {
			var self = this;
			if ( ! id ) {
				return;
			}
			self.currentTaskId = id;
			$( '#wpmind-workflow-alt-dryrun, #wpmind-media-workflow-dryrun' ).prop( 'disabled', false );
			$.get( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_task_get',
				nonce: wpmindData.nonce,
				task_id: id
			} ).done( function( res ) {
				if ( res.success ) {
					self.renderItems( res.data.task );
				}
			} );
		},

		refreshList: function() {
			$.get( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_task_list',
				nonce: wpmindData.nonce,
				limit: 20
			} ).done( function( res ) {
				if ( ! res.success ) {
					return;
				}
				var $tb = $( '#wpmind-workflow-task-table tbody' );
				$tb.empty();
				if ( ! res.data.tasks.length ) {
					$tb.append( '<tr class="wpmind-workflow-empty"><td colspan="6">暂无任务</td></tr>' );
					return;
				}
				res.data.tasks.forEach( function( t ) {
					var s = t.summary || {};
					var summary = '候选 ' + ( s.candidates || 0 ) + ' / 已预览 ' + ( s.previewed || 0 ) + ' / 错误 ' + ( s.errors || 0 );
					$tb.append(
						'<tr data-task-id="' + t.id + '">' +
						'<td>' + t.id + '</td>' +
						'<td>' + t.type + '</td>' +
						'<td>' + t.status + '</td>' +
						'<td>' + summary + '</td>' +
						'<td>' + ( t.created_at || '' ) + '</td>' +
						'<td><button type="button" class="button button-small wpmind-workflow-view" data-task-id="' + t.id + '">查看</button></td>' +
						'</tr>'
					);
				} );
			} );
		},

		renderItems: function( task ) {
			if ( ! task ) {
				return;
			}
			this.currentTaskId = task.id;
			var $wrap = $( '#wpmind-workflow-items' );
			var $body = $( '#wpmind-workflow-items-body' );
			$body.empty();
			( task.items || [] ).forEach( function( it ) {
				$body.append(
					'<tr>' +
					'<td>' + it.object_id + '</td>' +
					'<td>' + $( '<div/>' ).text( it.before || '' ).html() + '</td>' +
					'<td>' + $( '<div/>' ).text( it.proposed || '' ).html() + '</td>' +
					'<td>' + ( it.item_status || '' ) + ( it.error_message ? ( ' — ' + $( '<div/>' ).text( it.error_message ).html() ) : '' ) + '</td>' +
					'</tr>'
				);
			} );
			$wrap.show();
		}
	};

	$( function() {
		var safeInit = Admin.safeInit || function( label, fn ) {
			try { fn(); } catch ( e ) { if ( window.console ) { console.error( label, e ); } }
		};
		safeInit( 'workflow', function() {
			Workflow.init();
		} );
	} );
}( jQuery ) );
