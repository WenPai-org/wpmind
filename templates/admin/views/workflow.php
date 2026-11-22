<?php
/**
 * 任务视图：kit steps + progressbar + 任务列表 + fold 明细。
 * 直接访问 ?tab=workflow 始终可达；相关模块未启用且无任务时展示 empty_state。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;
use WPMind\Workflow\AltBulkService;
use WPMind\Workflow\MetaBulkService;
use WPMind\Workflow\TaskRepository;

( static function (): void {
	$wpmind_repo       = new TaskRepository();
	$wpmind_tasks      = $wpmind_repo->list_tasks( [ 'limit' => 20 ] );
	$wpmind_limit      = AltBulkService::MAX_CANDIDATES;
	$wpmind_meta_limit = MetaBulkService::MAX_CANDIDATES;
	$wpmind_current    = $wpmind_tasks[0] ?? null;

	$wpmind_loader      = class_exists( ModuleLoader::class ) ? ModuleLoader::instance() : null;
	$wpmind_has_modules = $wpmind_loader
	&& ( $wpmind_loader->is_module_enabled( 'auto-meta' ) || $wpmind_loader->is_module_enabled( 'media-intelligence' ) );

	$wpmind_task_status_pill = static function ( string $status ): array {
		$map = [
			'draft'          => [ __( '草稿', 'wpmind' ), 'mute' ],
			'dry_run'        => [ __( '预演完成', 'wpmind' ), 'warn' ],
			'pending_review' => [ __( '待审核', 'wpmind' ), 'warn' ],
			'applied'        => [ __( '已写入', 'wpmind' ), 'ok' ],
			'cancelled'      => [ __( '已取消', 'wpmind' ), 'mute' ],
			'undone'         => [ __( '已撤销', 'wpmind' ), 'mute' ],
		];
		return $map[ $status ] ?? [ $status, 'mute' ];
	};

	$wpmind_task_type_label = static function ( string $type ): string {
		return 'meta_bulk' === $type ? __( 'Meta 批量', 'wpmind' ) : __( 'Alt 批量', 'wpmind' );
	};

	// 0-based steps index for Wenpai_Admin_UI::steps().
	$wpmind_step_index = static function ( ?array $task ): int {
		if ( ! $task ) {
			return -1;
		}
		$status = (string) ( $task['status'] ?? 'draft' );
		$map    = [
			'draft'          => 0,
			'dry_run'        => 1,
			'pending_review' => 2,
			'applied'        => 3,
			'undone'         => 4,
			'cancelled'      => -1,
		];
		return $map[ $status ] ?? 0;
	};

	wpmind_page_head(
		__( '任务', 'wpmind' ),
		__( '批量任务走预演、审核、写入与撤销流程。', 'wpmind' )
	);
	?>

<div class="wenpai-stack">
	<?php if ( ! $wpmind_has_modules && empty( $wpmind_tasks ) ) : ?>
		<?php
		Wenpai_Admin_UI::card_open();
		Wenpai_Admin_UI::empty_state(
			[
				'icon'   => 'tools',
				'text'   => __( '批量任务依赖的模块尚未开启', 'wpmind' ),
				'meta'   => __( '开启「自动元数据」或「媒体智能」模块后，可在此创建并审核批量生成任务。', 'wpmind' ),
				'action' => [
					'label'   => __( '前往模块页开启', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'modules' ] ),
					'variant' => 'primary',
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	<?php else : ?>
		<?php
		// ---- 卡 1：当前任务 ----
		$current_sub  = __( '还没有任务，点击下方「扫描并创建」开始。', 'wpmind' );
		$current_pill = '';
		if ( $wpmind_current ) {
			$current_sub = sprintf(
				/* translators: 1: task type label, 2: task id, 3: created at */
				__( '%1$s #%2$d · 创建于 %3$s', 'wpmind' ),
				$wpmind_task_type_label( (string) $wpmind_current['type'] ),
				(int) $wpmind_current['id'],
				(string) ( $wpmind_current['created_at'] ?? '' )
			);
			[ $sp_text, $sp_state ] = $wpmind_task_status_pill( (string) $wpmind_current['status'] );
			$current_pill           = Wenpai_Admin_UI::pill_html(
				[
					'text'  => $sp_text,
					'state' => $sp_state,
				]
			);
		}

		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '当前任务', 'wpmind' ),
				'sub'               => $current_sub,
				'tile'              => false,
				'head_actions_html' => $current_pill,
			]
		);

		Wenpai_Admin_UI::steps(
			[
				'label'   => __( '任务执行步骤', 'wpmind' ),
				'current' => $wpmind_step_index( $wpmind_current ),
				'items'   => [
					__( '创建', 'wpmind' ),
					__( '预演', 'wpmind' ),
					__( '审核', 'wpmind' ),
					__( '写入', 'wpmind' ),
					[
						'label' => __( '撤销', 'wpmind' ),
						'no'    => '↩',
					],
				],
			]
		);

		if ( $wpmind_current ) {
			$cs         = is_array( $wpmind_current['summary'] ?? null ) ? $wpmind_current['summary'] : [];
			$candidates = max( 1, (int) ( $cs['candidates'] ?? 0 ) );
			$done_cnt   = (int) ( $cs['applied'] ?? 0 ) > 0
				? (int) $cs['applied']
				: ( (int) ( $cs['approved'] ?? 0 ) > 0 ? (int) $cs['approved'] : (int) ( $cs['previewed'] ?? 0 ) );

			Wenpai_Admin_UI::progressbar(
				[
					'value' => $done_cnt,
					'max'   => $candidates,
					'label' => __( '当前任务进度', 'wpmind' ),
				]
			);

			Wenpai_Admin_UI::statbar(
				[
					'items' => [
						[
							'label' => __( '候选', 'wpmind' ),
							'value' => (string) (int) ( $cs['candidates'] ?? 0 ),
						],
						[
							'label' => __( '预览', 'wpmind' ),
							'value' => (string) (int) ( $cs['previewed'] ?? 0 ),
						],
						[
							'label' => __( '通过', 'wpmind' ),
							'value' => (string) (int) ( $cs['approved'] ?? 0 ),
						],
						[
							'label' => __( '已写', 'wpmind' ),
							'value' => (string) (int) ( $cs['applied'] ?? 0 ),
						],
					],
				]
			);
		}
		?>
		<div class="card-foot start">
			<span class="meta"><?php esc_html_e( '仅已批准项会写入内容，每一步均可撤销。', 'wpmind' ); ?></span>
			<span class="wenpai-actions">
				<span class="wpmind-workflow-actset" data-type="alt_bulk" hidden>
					<button class="btn btn-ghost btn-sm" type="button" id="wpmind-workflow-alt-dryrun" disabled><?php esc_html_e( '运行预演', 'wpmind' ); ?></button>
					<button class="btn btn-secondary btn-sm" type="button" id="wpmind-workflow-alt-submit-review" disabled><?php esc_html_e( '提交审核', 'wpmind' ); ?></button>
					<?php
					$alt_apply_confirm = wpmind_confirm_attrs(
						__( '确认将已批准的 Alt 文本写入媒体附件？', 'wpmind' ),
						[
							'title'    => __( '确认写入', 'wpmind' ),
							'primary'  => true,
							'ok_label' => __( '确认写入', 'wpmind' ),
						]
					);
					$alt_undo_confirm  = wpmind_confirm_attrs(
						__( '确认撤销本任务已写入的 Alt 文本并恢复原值？', 'wpmind' ),
						[
							'title'    => __( '确认撤销', 'wpmind' ),
							'danger'   => true,
							'ok_label' => __( '确认撤销', 'wpmind' ),
						]
					);
					?>
					<button class="btn btn-primary btn-sm" type="button" id="wpmind-workflow-alt-apply" disabled<?php echo $alt_apply_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '写入已通过项', 'wpmind' ); ?></button>
					<button class="btn btn-ghost btn-sm" type="button" id="wpmind-workflow-alt-undo" disabled<?php echo $alt_undo_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '撤销已写入', 'wpmind' ); ?></button>
				</span>
				<span class="wpmind-workflow-actset" data-type="meta_bulk" hidden>
					<button class="btn btn-ghost btn-sm" type="button" id="wpmind-workflow-meta-dryrun" disabled><?php esc_html_e( '运行预演', 'wpmind' ); ?></button>
					<button class="btn btn-secondary btn-sm" type="button" id="wpmind-workflow-meta-submit-review" disabled><?php esc_html_e( '提交审核', 'wpmind' ); ?></button>
					<?php
					$meta_apply_confirm = wpmind_confirm_attrs(
						__( '确认将已批准的元数据写入文章？', 'wpmind' ),
						[
							'title'    => __( '确认写入', 'wpmind' ),
							'primary'  => true,
							'ok_label' => __( '确认写入', 'wpmind' ),
						]
					);
					$meta_undo_confirm  = wpmind_confirm_attrs(
						__( '确认撤销本任务已写入的文章元数据？', 'wpmind' ),
						[
							'title'    => __( '确认撤销', 'wpmind' ),
							'danger'   => true,
							'ok_label' => __( '确认撤销', 'wpmind' ),
						]
					);
					?>
					<button class="btn btn-primary btn-sm" type="button" id="wpmind-workflow-meta-apply" disabled<?php echo $meta_apply_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '写入已通过项', 'wpmind' ); ?></button>
					<button class="btn btn-ghost btn-sm" type="button" id="wpmind-workflow-meta-undo" disabled<?php echo $meta_undo_confirm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '撤销已写入', 'wpmind' ); ?></button>
				</span>
			</span>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();

		// ---- 卡 2：任务列表 ----
		$list_head_actions = '<span class="wenpai-actions">'
			. Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '刷新列表', 'wpmind' ),
					'variant' => 'ghost',
					'size'    => 'sm',
					'attrs'   => [ 'id' => 'wpmind-workflow-refresh' ],
				]
			)
			. Wenpai_Admin_UI::button_html(
				[
					'label'   => sprintf( /* translators: %d: max candidates */ __( '扫描并创建 Alt 任务（≤%d）', 'wpmind' ), $wpmind_limit ),
					'variant' => 'primary',
					'size'    => 'sm',
					'attrs'   => [ 'id' => 'wpmind-workflow-alt-create' ],
				]
			)
			. Wenpai_Admin_UI::button_html(
				[
					'label'   => sprintf( /* translators: %d: max candidates */ __( '创建 Meta 任务（≤%d）', 'wpmind' ), $wpmind_meta_limit ),
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => [ 'id' => 'wpmind-workflow-meta-create' ],
				]
			)
			. '</span>';

		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '任务列表', 'wpmind' ),
				'sub'               => __( '点击「查看」载入审核明细。', 'wpmind' ),
				'tile'              => false,
				'head_control_html' => $list_head_actions,
			]
		);
		?>
		<div class="wenpai-table-wrap">
			<table class="tbl" id="wpmind-workflow-task-table">
				<thead>
					<tr>
						<th class="num"><?php esc_html_e( 'ID', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '类型', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '进度', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '创建时间 (UTC)', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $wpmind_tasks ) ) : ?>
						<tr class="wpmind-workflow-empty"><td colspan="6"><?php esc_html_e( '还没有批量任务，点击上方按钮扫描并创建。', 'wpmind' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $wpmind_tasks as $wpmind_task ) : ?>
							<?php
							$wpmind_ts   = $wpmind_task_status_pill( (string) $wpmind_task['status'] );
							$wpmind_sm   = is_array( $wpmind_task['summary'] ?? null ) ? $wpmind_task['summary'] : [];
							$wpmind_prog = 'draft' === ( $wpmind_task['status'] ?? '' )
								? __( '未开始', 'wpmind' )
								: sprintf(
									/* translators: 1: approved, 2: candidates, 3: errors */
									__( '通过 %1$d/%2$d · 错 %3$d', 'wpmind' ),
									(int) ( $wpmind_sm['approved'] ?? 0 ),
									(int) ( $wpmind_sm['candidates'] ?? 0 ),
									(int) ( $wpmind_sm['errors'] ?? 0 )
								);
							?>
							<tr data-task-id="<?php echo esc_attr( (string) $wpmind_task['id'] ); ?>">
								<td class="num"><?php echo esc_html( (string) $wpmind_task['id'] ); ?></td>
								<td><?php echo esc_html( $wpmind_task_type_label( (string) $wpmind_task['type'] ) ); ?></td>
								<td>
								<?php
								$task_pill_html = Wenpai_Admin_UI::pill_html(
									[
										'text'  => $wpmind_ts[0],
										'state' => $wpmind_ts[1],
									]
								);
								echo $task_pill_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
								</td>
								<td><?php echo esc_html( $wpmind_prog ); ?></td>
								<td class="mono"><?php echo esc_html( (string) ( $wpmind_task['created_at'] ?? '' ) ); ?></td>
								<td>
									<?php
									Wenpai_Admin_UI::button(
										[
											'label'   => __( '查看', 'wpmind' ),
											'variant' => 'secondary',
											'size'    => 'sm',
											'class'   => 'wpmind-workflow-view',
											'attrs'   => [ 'data-task-id' => (string) $wpmind_task['id'] ],
										]
									);
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			[
				'start'     => true,
				'meta_html' => '<span class="meta" id="wpmind-workflow-status"></span>',
			]
		);
		Wenpai_Admin_UI::card_close();

		// ---- 卡 3：任务项明细（JS 载入后取消 hidden） ----
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '任务项明细', 'wpmind' ),
				'sub'   => __( '对照原值、建议值与已写入值，逐条批准或驳回。', 'wpmind' ),
				'tile'  => false,
				'attrs' => [
					'id'     => 'wpmind-workflow-items',
					'hidden' => 'hidden',
				],
			]
		);
		?>
		<div class="wenpai-table-wrap">
			<table class="tbl">
				<thead>
					<tr>
						<th class="num"><?php esc_html_e( '对象 ID', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '原值', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '建议值', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '已写入', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
					</tr>
				</thead>
				<tbody id="wpmind-workflow-items-body"></tbody>
			</table>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();
	endif;
	?>
</div>
	<?php
} )();
