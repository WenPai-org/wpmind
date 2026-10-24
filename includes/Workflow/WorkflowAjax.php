<?php
/**
 * Workflow AJAX handlers (unified tasks + alt dry-run).
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
