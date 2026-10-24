<?php
/**
 * Workflow bootstrapping: CPT, AJAX, admin assets.
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

/**
 * Workflow bootstrap.
 *
 * @since 0.14.0
 */
final class WorkflowBoot {

	/**
	 * Whether hooks are already registered.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Register hooks once.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( TaskRepository::class, 'register_post_type' ) );
		WorkflowAjax::register_hooks();
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue workflow admin script on WPMind settings page.
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_wpmind' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'wpmind-admin-workflow',
			WPMIND_PLUGIN_URL . 'assets/js/admin-workflow.js',
			array( 'jquery', 'wpmind-admin-boot' ),
			WPMIND_VERSION,
			true
		);

		wp_localize_script(
			'wpmind-admin-workflow',
			'wpmindWorkflow',
			array(
				'maxCandidates' => AltBulkService::MAX_CANDIDATES,
				'batchSize'     => AltBulkService::DRY_RUN_BATCH,
				'applyBatch'    => AltBulkService::APPLY_BATCH,
				'i18n'          => array(
					'creating'   => __( '正在创建任务…', 'wpmind' ),
					'dryRunning' => __( '预览生成中…', 'wpmind' ),
					'done'       => __( 'Dry-run 完成', 'wpmind' ),
					'empty'      => __( '没有缺少 Alt 的图片', 'wpmind' ),
					'submitting' => __( '提交审核中…', 'wpmind' ),
					'reviewing'  => __( '保存审核决定…', 'wpmind' ),
					'applying'   => __( '写入已通过项…', 'wpmind' ),
					'applyDone'  => __( '写入完成', 'wpmind' ),
					'undoing'    => __( '撤销中…', 'wpmind' ),
					'undoDone'   => __( '撤销完成', 'wpmind' ),
					'approved'   => __( '已通过', 'wpmind' ),
					'rejected'   => __( '已拒绝', 'wpmind' ),
					'limitHint'  => sprintf(
						/* translators: %d: max candidates */
						__( '单任务最多 %d 张', 'wpmind' ),
						AltBulkService::MAX_CANDIDATES
					),
				),
			)
		);
	}
}
