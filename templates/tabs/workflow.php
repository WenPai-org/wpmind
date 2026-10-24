<?php
/**
 * Workflow / tasks tab.
 *
 * @package WPMind
 * @since 0.14.0
 */

defined( 'ABSPATH' ) || exit;

use WPMind\Workflow\AltBulkService;
use WPMind\Workflow\MetaBulkService;
use WPMind\Workflow\TaskRepository;

$wpmind_repo       = new TaskRepository();
$wpmind_tasks      = $wpmind_repo->list_tasks( array( 'limit' => 20 ) );
$wpmind_limit      = AltBulkService::MAX_CANDIDATES;
$wpmind_meta_limit = MetaBulkService::MAX_CANDIDATES;
?>
<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-list-check-3"></span>
		<?php esc_html_e( '任务 / 工作流', 'wpmind' ); ?>
	</h2>
</div>

<div class="wpmind-tab-pane-body">
	<p class="wpmind-module-desc">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: alt max, 2: meta max */
				__( '统一任务记录。Alt：dry-run → 审核 → 写入 → 撤销（最多 %1$d 张）。Meta：dry-run → 审核 → 写入 → 撤销（最多 %2$d 篇）。', 'wpmind' ),
				$wpmind_limit,
				$wpmind_meta_limit
			)
		);
		?>
	</p>

	<div class="wpmind-module-actions" style="margin-bottom:16px;">
		<button type="button" id="wpmind-workflow-alt-create" class="button button-primary">
			<span class="dashicons ri-add-line"></span>
			<?php esc_html_e( '扫描并创建 Alt 任务', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-alt-dryrun" class="button" disabled>
			<span class="dashicons ri-eye-line"></span>
			<?php esc_html_e( '对当前任务 Dry-run', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-alt-submit-review" class="button" disabled>
			<span class="dashicons ri-send-plane-line"></span>
			<?php esc_html_e( '提交审核', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-alt-apply" class="button" disabled>
			<span class="dashicons ri-save-line"></span>
			<?php esc_html_e( '写入已通过项', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-alt-undo" class="button" disabled>
			<span class="dashicons ri-arrow-go-back-line"></span>
			<?php esc_html_e( '撤销已写入', 'wpmind' ); ?>
		</button>
		<span style="display:inline-block;width:12px;"></span>
		<button type="button" id="wpmind-workflow-meta-create" class="button button-primary">
			<span class="dashicons ri-add-line"></span>
			<?php esc_html_e( '扫描并创建 Meta 任务', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-meta-dryrun" class="button" disabled>
			<span class="dashicons ri-eye-line"></span>
			<?php esc_html_e( '对当前 Meta 任务 Dry-run', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-meta-submit-review" class="button" disabled>
			<span class="dashicons ri-send-plane-line"></span>
			<?php esc_html_e( '提交 Meta 审核', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-meta-apply" class="button" disabled>
			<span class="dashicons ri-save-line"></span>
			<?php esc_html_e( '写入已通过 Meta 项', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-meta-undo" class="button" disabled>
			<span class="dashicons ri-arrow-go-back-line"></span>
			<?php esc_html_e( '撤销已写入 Meta', 'wpmind' ); ?>
		</button>
		<button type="button" id="wpmind-workflow-refresh" class="button">
			<span class="dashicons ri-refresh-line"></span>
			<?php esc_html_e( '刷新列表', 'wpmind' ); ?>
		</button>
	</div>

	<p id="wpmind-workflow-status" class="description"></p>

	<table class="widefat striped" id="wpmind-workflow-task-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '类型', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '摘要', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '创建时间 (UTC)', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $wpmind_tasks ) ) : ?>
			<tr class="wpmind-workflow-empty"><td colspan="6"><?php esc_html_e( '暂无任务', 'wpmind' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $wpmind_tasks as $wpmind_task ) : ?>
				<tr data-task-id="<?php echo esc_attr( (string) $wpmind_task['id'] ); ?>">
					<td><?php echo esc_html( (string) $wpmind_task['id'] ); ?></td>
					<td><?php echo esc_html( (string) $wpmind_task['type'] ); ?></td>
					<td><?php echo esc_html( (string) $wpmind_task['status'] ); ?></td>
					<td>
						<?php
						$wpmind_summary = is_array( $wpmind_task['summary'] ?? null ) ? $wpmind_task['summary'] : array();
						echo esc_html(
							sprintf(
								/* translators: 1: candidates 2: previewed 3: approved 4: rejected 5: applied 6: undone 7: errors */
								__( '候选 %1$d / 预览 %2$d / 通过 %3$d / 拒绝 %4$d / 已写 %5$d / 撤销 %6$d / 错 %7$d', 'wpmind' ),
								(int) ( $wpmind_summary['candidates'] ?? 0 ),
								(int) ( $wpmind_summary['previewed'] ?? 0 ),
								(int) ( $wpmind_summary['approved'] ?? 0 ),
								(int) ( $wpmind_summary['rejected'] ?? 0 ),
								(int) ( $wpmind_summary['applied'] ?? 0 ),
								(int) ( $wpmind_summary['undone'] ?? 0 ),
								(int) ( $wpmind_summary['errors'] ?? 0 )
							)
						);
						?>
					</td>
					<td><?php echo esc_html( (string) ( $wpmind_task['created_at'] ?? '' ) ); ?></td>
					<td>
						<button type="button" class="button button-small wpmind-workflow-view" data-task-id="<?php echo esc_attr( (string) $wpmind_task['id'] ); ?>">
							<?php esc_html_e( '查看', 'wpmind' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<div id="wpmind-workflow-items" style="margin-top:16px;display:none;">
		<h3><?php esc_html_e( '任务项（before / proposed / applied）', 'wpmind' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( '对象 ID', 'wpmind' ); ?></th>
					<th><?php esc_html_e( 'Before', 'wpmind' ); ?></th>
					<th><?php esc_html_e( 'Proposed', 'wpmind' ); ?></th>
					<th><?php esc_html_e( 'Applied', 'wpmind' ); ?></th>
					<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
					<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
				</tr>
			</thead>
			<tbody id="wpmind-workflow-items-body"></tbody>
		</table>
	</div>
</div>
