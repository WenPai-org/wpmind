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

	function esc( s ) {
		return $( '<div/>' ).text( s == null ? '' : String( s ) ).html();
	}

	function snapshotFmt( v ) {
		if ( v == null || v === '' ) {
			return '';
		}
		if ( typeof v === 'object' ) {
			try {
				var parts = [];
				if ( v.excerpt ) { parts.push( '摘要:' + String( v.excerpt ).slice( 0, 40 ) ); }
				if ( v.seo_description ) { parts.push( 'SEO:' + String( v.seo_description ).slice( 0, 40 ) ); }
				if ( v.tags && v.tags.length ) { parts.push( '标签:' + v.tags.slice( 0, 3 ).join( ',' ) ); }
				if ( v.category ) { parts.push( '分类:' + v.category ); }
				if ( v.faq && v.faq.length ) { parts.push( 'FAQ×' + v.faq.length ); }
				if ( v.source ) { parts.push( 'source:' + v.source ); }
				return parts.length ? parts.join( ' | ' ) : JSON.stringify( v ).slice( 0, 120 );
			} catch ( e ) {
				return '[object]';
			}
		}
		return String( v );
	}

	function summaryText( s ) {
		s = s || {};
		return '候选 ' + ( s.candidates || 0 ) +
			' / 预览 ' + ( s.previewed || 0 ) +
			' / 通过 ' + ( s.approved || 0 ) +
			' / 拒绝 ' + ( s.rejected || 0 ) +
			' / 已写 ' + ( s.applied || 0 ) +
			' / 撤销 ' + ( s.undone || 0 ) +
			' / 错 ' + ( s.errors || 0 );
	}

	var Workflow = {
		currentTaskId: 0,
		currentTask: null,

		init: function() {
			var self = this;
			$( '#wpmind-workflow-alt-create' ).on( 'click', function() {
				self.createAltTask();
			} );
			$( '#wpmind-workflow-meta-create' ).on( 'click', function() {
				self.createMetaTask();
			} );
			$( '#wpmind-workflow-alt-dryrun' ).on( 'click', function() {
				self.dryRunLoop();
			} );
			$( '#wpmind-workflow-meta-dryrun' ).on( 'click', function() {
				self.metaDryRunLoop();
			} );
			$( '#wpmind-workflow-alt-submit-review' ).on( 'click', function() {
				self.submitReview();
			} );
			$( '#wpmind-workflow-alt-apply' ).on( 'click', function() {
				self.applyLoop();
			} );
			$( '#wpmind-workflow-alt-undo' ).on( 'click', function() {
				self.undoApplied();
			} );
			$( '#wpmind-workflow-refresh' ).on( 'click', function() {
				self.refreshList();
			} );
			$( document ).on( 'click', '.wpmind-workflow-view', function() {
				self.loadTask( parseInt( $( this ).data( 'task-id' ), 10 ) || 0 );
			} );
			$( document ).on( 'click', '.wpmind-workflow-approve', function() {
				var oid = parseInt( $( this ).data( 'object-id' ), 10 ) || 0;
				self.reviewOne( oid, 'approve' );
			} );
			$( document ).on( 'click', '.wpmind-workflow-reject', function() {
				var oid = parseInt( $( this ).data( 'object-id' ), 10 ) || 0;
				self.reviewOne( oid, 'reject' );
			} );

			// Media tab shortcut buttons (optional).
			$( '#wpmind-media-workflow-create' ).on( 'click', function() {
				self.createAltTask();
			} );
			$( '#wpmind-media-workflow-dryrun' ).on( 'click', function() {
				self.dryRunLoop();
			} );
		},

		setActionButtons: function( task ) {
			var has = !! this.currentTaskId;
			var type = task && task.type ? task.type : '';
			var isAlt = type === 'alt_bulk';
			var isMeta = type === 'meta_bulk';
			$( '#wpmind-workflow-alt-dryrun, #wpmind-media-workflow-dryrun' ).prop( 'disabled', ! ( has && isAlt ) );
			$( '#wpmind-workflow-meta-dryrun' ).prop( 'disabled', ! ( has && isMeta ) );
			$( '#wpmind-workflow-alt-submit-review' ).prop( 'disabled', ! ( has && isAlt ) );
			$( '#wpmind-workflow-alt-apply' ).prop( 'disabled', ! ( has && isAlt ) );
			$( '#wpmind-workflow-alt-undo' ).prop( 'disabled', ! ( has && isAlt ) );
			if ( ! task ) {
				return;
			}
			var status = task.status || '';
			var s = task.summary || {};
			if ( isAlt ) {
				$( '#wpmind-workflow-alt-submit-review' ).prop(
					'disabled',
					! ( status === 'dry_run' && ( s.previewed || 0 ) > 0 )
				);
				$( '#wpmind-workflow-alt-apply' ).prop(
					'disabled',
					! ( ( s.approved || 0 ) > 0 )
				);
				$( '#wpmind-workflow-alt-undo' ).prop(
					'disabled',
					! ( ( s.applied || 0 ) > 0 )
				);
			}
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
				self.currentTask = res.data.task;
				self.setActionButtons( res.data.task );
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


		createMetaTask: function() {
			var self = this;
			var $btn = $( '#wpmind-workflow-meta-create' );
			$btn.prop( 'disabled', true );
			$( '#wpmind-workflow-status' ).text( i18n.metaCreating || i18n.creating || '…' );

			$.post( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_meta_create',
				nonce: wpmindData.nonce
			} ).done( function( res ) {
				if ( ! res.success ) {
					Toast.error( ( res.data && res.data.message ) || 'error' );
					return;
				}
				self.currentTaskId = res.data.task.id;
				self.currentTask = res.data.task;
				self.setActionButtons( res.data.task );
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

		metaDryRunLoop: function() {
			var self = this;
			if ( ! self.currentTaskId ) {
				Toast.error( 'no task' );
				return;
			}
			var $btn = $( '#wpmind-workflow-meta-dryrun' );
			$btn.prop( 'disabled', true );
			$( '#wpmind-workflow-status' ).text( i18n.metaDryRunning || i18n.dryRunning || '…' );

			function round() {
				$.post( wpmindData.ajaxurl, {
					action: 'wpmind_workflow_meta_dry_run',
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
						Toast.success( i18n.metaDone || i18n.done || 'done' );
						$( '#wpmind-workflow-status' ).text( i18n.metaDone || i18n.done || 'done' );
						self.setActionButtons( res.data.task );
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
						self.setActionButtons( res.data.task );
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

		submitReview: function() {
			var self = this;
			if ( ! self.currentTaskId ) {
				return;
			}
			$( '#wpmind-workflow-status' ).text( i18n.submitting || '…' );
			$.post( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_alt_submit_review',
				nonce: wpmindData.nonce,
				task_id: self.currentTaskId
			} ).done( function( res ) {
				if ( ! res.success ) {
					Toast.error( ( res.data && res.data.message ) || 'error' );
					return;
				}
				self.renderItems( res.data.task );
				self.refreshList();
				Toast.success( 'pending_review' );
			} ).fail( function() {
				Toast.error( 'network' );
			} );
		},

		reviewOne: function( objectId, decision ) {
			var self = this;
			if ( ! self.currentTaskId || ! objectId ) {
				return;
			}
			$( '#wpmind-workflow-status' ).text( i18n.reviewing || '…' );
			$.post( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_alt_review',
				nonce: wpmindData.nonce,
				task_id: self.currentTaskId,
				decisions: JSON.stringify( [ { object_id: objectId, decision: decision } ] )
			} ).done( function( res ) {
				if ( ! res.success ) {
					Toast.error( ( res.data && res.data.message ) || 'error' );
					return;
				}
				Toast.success( decision === 'approve' ? ( i18n.approved || 'ok' ) : ( i18n.rejected || 'ok' ) );
				self.renderItems( res.data.task );
				self.refreshList();
			} ).fail( function() {
				Toast.error( 'network' );
			} );
		},

		applyLoop: function() {
			var self = this;
			if ( ! self.currentTaskId ) {
				return;
			}
			var $btn = $( '#wpmind-workflow-alt-apply' );
			$btn.prop( 'disabled', true );
			$( '#wpmind-workflow-status' ).text( i18n.applying || '…' );

			function round() {
				$.post( wpmindData.ajaxurl, {
					action: 'wpmind_workflow_alt_apply',
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
						Toast.success( i18n.applyDone || 'done' );
						$( '#wpmind-workflow-status' ).text( i18n.applyDone || 'done' );
						self.setActionButtons( res.data.task );
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

		undoApplied: function() {
			var self = this;
			if ( ! self.currentTaskId ) {
				return;
			}
			$( '#wpmind-workflow-status' ).text( i18n.undoing || '…' );
			$.post( wpmindData.ajaxurl, {
				action: 'wpmind_workflow_alt_undo',
				nonce: wpmindData.nonce,
				task_id: self.currentTaskId
			} ).done( function( res ) {
				if ( ! res.success ) {
					Toast.error( ( res.data && res.data.message ) || 'error' );
					return;
				}
				Toast.success( i18n.undoDone || 'done' );
				$( '#wpmind-workflow-status' ).text( i18n.undoDone || 'done' );
				self.renderItems( res.data.task );
				self.refreshList();
			} ).fail( function() {
				Toast.error( 'network' );
			} );
		},

		loadTask: function( id ) {
			var self = this;
			if ( ! id ) {
				return;
			}
			self.currentTaskId = id;
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
					$tb.append(
						'<tr data-task-id="' + t.id + '">' +
						'<td>' + t.id + '</td>' +
						'<td>' + esc( t.type ) + '</td>' +
						'<td>' + esc( t.status ) + '</td>' +
						'<td>' + esc( summaryText( t.summary ) ) + '</td>' +
						'<td>' + esc( t.created_at || '' ) + '</td>' +
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
			this.currentTask = task;
			this.setActionButtons( task );
			var $wrap = $( '#wpmind-workflow-items' );
			var $body = $( '#wpmind-workflow-items-body' );
			$body.empty();
			var taskType = task.type || '';
			( task.items || [] ).forEach( function( it ) {
				var st = it.item_status || '';
				var actions = '';
				if ( taskType === 'alt_bulk' && ( st === 'previewed' || st === 'approved' ) ) {
					actions =
						'<button type="button" class="button button-small wpmind-workflow-approve" data-object-id="' + it.object_id + '">通过</button> ' +
						'<button type="button" class="button button-small wpmind-workflow-reject" data-object-id="' + it.object_id + '">拒绝</button>';
				}
				var statusCell = esc( st );
				if ( it.error_message ) {
					statusCell += ' — ' + esc( it.error_message );
				}
				$body.append(
					'<tr>' +
					'<td>' + it.object_id + '</td>' +
					'<td>' + esc( snapshotFmt( it.before ) ) + '</td>' +
					'<td>' + esc( snapshotFmt( it.proposed ) ) + '</td>' +
					'<td>' + esc( snapshotFmt( it.applied ) ) + '</td>' +
					'<td>' + statusCell + '</td>' +
					'<td>' + actions + '</td>' +
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
