<?php
/**
 * Workflow AJAX handlers (unified tasks + Alt/Meta dry-run + review/apply/undo).
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

/**
 * Workflow AJAX controller.
 *
 * @since 0.14.0
 */
final class WorkflowAjax {

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'wp_ajax_wpmind_workflow_alt_create', array( self::class, 'ajax_alt_create' ) );
		add_action( 'wp_ajax_wpmind_workflow_alt_dry_run', array( self::class, 'ajax_alt_dry_run' ) );
		add_action( 'wp_ajax_wpmind_workflow_alt_review', array( self::class, 'ajax_alt_review' ) );
		add_action( 'wp_ajax_wpmind_workflow_alt_apply', array( self::class, 'ajax_alt_apply' ) );
		add_action( 'wp_ajax_wpmind_workflow_alt_undo', array( self::class, 'ajax_alt_undo' ) );
		add_action( 'wp_ajax_wpmind_workflow_alt_submit_review', array( self::class, 'ajax_alt_submit_review' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_create', array( self::class, 'ajax_meta_create' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_dry_run', array( self::class, 'ajax_meta_dry_run' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_review', array( self::class, 'ajax_meta_review' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_apply', array( self::class, 'ajax_meta_apply' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_undo', array( self::class, 'ajax_meta_undo' ) );
		add_action( 'wp_ajax_wpmind_workflow_meta_submit_review', array( self::class, 'ajax_meta_submit_review' ) );
		add_action( 'wp_ajax_wpmind_workflow_task_get', array( self::class, 'ajax_task_get' ) );
		add_action( 'wp_ajax_wpmind_workflow_task_list', array( self::class, 'ajax_task_list' ) );
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @param bool $require_post Require POST method.
	 * @return void
	 */
	private static function verify( bool $require_post = false ): void {
		if ( $require_post && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => 'Method not allowed' ), 405 );
		}
		check_ajax_referer( 'wpmind_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ), 403 );
		}
	}

	/**
	 * Create alt_bulk task from missing-alt scan.
	 *
	 * @return void
	 */
	public static function ajax_alt_create(): void {
		self::verify( true );

		$service = new AltBulkService();
		$result  = $service->create_task_from_scan();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'task'    => $result,
				'limit'   => AltBulkService::MAX_CANDIDATES,
				'message' => sprintf(
					/* translators: 1: candidate count, 2: max candidates */
					__( '已创建任务，候选 %1$d 张（单任务上限 %2$d）', 'wpmind' ),
					(int) ( $result['summary']['candidates'] ?? 0 ),
					AltBulkService::MAX_CANDIDATES
				),
			)
		);
	}

	/**
	 * Dry-run one batch of an alt_bulk task.
	 *
	 * @return void
	 */
	public static function ajax_alt_dry_run(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$service = new AltBulkService();
		$result  = $service->dry_run_batch( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Submit dry_run task to pending_review.
	 *
	 * @return void
	 */
	public static function ajax_alt_submit_review(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$result = ( new AltBulkService() )->mark_pending_review( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'task' => $result ) );
	}

	/**
	 * Review (approve/reject) alt_bulk items.
	 *
	 * @return void
	 */
	public static function ajax_alt_review(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$raw       = wp_unslash( (string) ( $_POST['decisions'] ?? '[]' ) );
		$decisions = json_decode( $raw, true );
		if ( ! is_array( $decisions ) ) {
			wp_send_json_error( array( 'message' => __( '审核数据无效', 'wpmind' ) ) );
		}

		$result = ( new AltBulkService() )->review_items( $task_id, $decisions );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'task' => $result ) );
	}

	/**
	 * Apply a batch of approved items.
	 *
	 * @return void
	 */
	public static function ajax_alt_apply(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$result = ( new AltBulkService() )->apply_approved( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Undo applied items (optional object_ids subset).
	 *
	 * @return void
	 */
	public static function ajax_alt_undo(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$object_ids = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		if ( isset( $_POST['object_ids'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
			$raw = wp_unslash( $_POST['object_ids'] );
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				$raw     = is_array( $decoded ) ? $decoded : array();
			}
			if ( is_array( $raw ) ) {
				$object_ids = array_map( 'intval', $raw );
			}
		}

		$result = ( new AltBulkService() )->undo_applied( $task_id, $object_ids );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}


	/**
	 * Create meta_bulk task from missing-meta scan.
	 *
	 * @return void
	 */
	public static function ajax_meta_create(): void {
		self::verify( true );

		$service = new MetaBulkService();
		$result  = $service->create_task_from_scan();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$calls  = (int) ( $result['summary']['estimated_calls'] ?? 0 );
		$tokens = (int) ( $result['summary']['estimated_max_tokens'] ?? 0 );

		wp_send_json_success(
			array(
				'task'    => $result,
				'limit'   => MetaBulkService::MAX_CANDIDATES,
				'message' => sprintf(
					/* translators: 1: candidate count, 2: max candidates, 3: estimated calls, 4: estimated max tokens */
					__( '已创建 Meta 任务，候选 %1$d 篇（上限 %2$d）；预估约 %3$d 次调用 / %4$d tokens（粗估）', 'wpmind' ),
					(int) ( $result['summary']['candidates'] ?? 0 ),
					MetaBulkService::MAX_CANDIDATES,
					$calls,
					$tokens
				),
			)
		);
	}

	/**
	 * Dry-run one batch of a meta_bulk task.
	 *
	 * @return void
	 */
	public static function ajax_meta_dry_run(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$service = new MetaBulkService();
		$result  = $service->dry_run_batch( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}


	/**
	 * Submit meta dry_run task to pending_review.
	 *
	 * @return void
	 */
	public static function ajax_meta_submit_review(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$result = ( new MetaBulkService() )->mark_pending_review( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'task' => $result ) );
	}

	/**
	 * Review (approve/reject) meta_bulk items.
	 *
	 * @return void
	 */
	public static function ajax_meta_review(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$raw       = wp_unslash( (string) ( $_POST['decisions'] ?? '[]' ) );
		$decisions = json_decode( $raw, true );
		if ( ! is_array( $decisions ) ) {
			wp_send_json_error( array( 'message' => __( '审核数据无效', 'wpmind' ) ) );
		}

		$result = ( new MetaBulkService() )->review_items( $task_id, $decisions );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'task' => $result ) );
	}

	/**
	 * Apply a batch of approved meta items.
	 *
	 * @return void
	 */
	public static function ajax_meta_apply(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$result = ( new MetaBulkService() )->apply_approved( $task_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Undo applied meta items (optional object_ids subset).
	 *
	 * @return void
	 */
	public static function ajax_meta_undo(): void {
		self::verify( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		$task_id = (int) ( $_POST['task_id'] ?? 0 );
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '无效任务 ID', 'wpmind' ) ) );
		}

		$object_ids = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
		if ( isset( $_POST['object_ids'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify().
			$raw = wp_unslash( $_POST['object_ids'] );
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				$raw     = is_array( $decoded ) ? $decoded : array();
			}
			if ( is_array( $raw ) ) {
				$object_ids = array_map( 'intval', $raw );
			}
		}

		$result = ( new MetaBulkService() )->undo_applied( $task_id, $object_ids );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get one task (with items).
	 *
	 * @return void
	 */
	public static function ajax_task_get(): void {
		self::verify( false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in verify().
		$task_id = (int) ( $_REQUEST['task_id'] ?? 0 );
		$task    = ( new TaskRepository() )->get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => __( '任务不存在', 'wpmind' ) ) );
		}
		wp_send_json_success( array( 'task' => $task ) );
	}

	/**
	 * List tasks (without items).
	 *
	 * @return void
	 */
	public static function ajax_task_list(): void {
		self::verify( false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in verify().
		$type = sanitize_key( (string) ( $_REQUEST['type'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in verify().
		$status = sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in verify().
		$limit = (int) ( $_REQUEST['limit'] ?? 20 );

		$args = array( 'limit' => $limit );
		if ( '' !== $type ) {
			$args['type'] = $type;
		}
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		$tasks = ( new TaskRepository() )->list_tasks( $args );
		wp_send_json_success( array( 'tasks' => $tasks ) );
	}
}
