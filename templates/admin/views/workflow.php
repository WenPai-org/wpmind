<?php
/**
 * 任务视图：当前任务焦点卡 + 任务列表 + fold 明细。
 * JS（admin-workflow.js）按 #wpmind-workflow-* ID 与 .wpmind-workflow-* 类驱动。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;
use WPMind\Workflow\AltBulkService;
use WPMind\Workflow\MetaBulkService;
use WPMind\Workflow\TaskRepository;

$wpmind_repo       = new TaskRepository();
$wpmind_tasks      = $wpmind_repo->list_tasks( array( 'limit' => 20 ) );
$wpmind_limit      = AltBulkService::MAX_CANDIDATES;
$wpmind_meta_limit = MetaBulkService::MAX_CANDIDATES;
$wpmind_current    = $wpmind_tasks[0] ?? null;

$wpmind_task_status_pill = static function ( string $status ): array {
	$map = [
		'draft'           => [ '草稿', 'mute' ],
		'dry_run'         => [ '预演完成', 'warn' ],
		'pending_review'  => [ '待审核', 'warn' ],
		'applied'         => [ '已写入', 'ok' ],
		'cancelled'       => [ '已取消', 'mute' ],
		'undone'          => [ '已撤销', 'mute' ],
	];
	return $map[ $status ] ?? [ $status, 'mute' ];
};

$wpmind_task_type_label = static function ( string $type ): string {
	return 'meta_bulk' === $type ? __( 'Meta 批量', 'wpmind' ) : __( 'Alt 批量', 'wpmind' );
};

// 状态机五步：create → dry_run → review → apply → undo。当前步高亮。
$wpmind_flow_stage = static function ( ?array $task ): int {
	if ( ! $task ) {
		return 0;
	}
	$status = (string) ( $task['status'] ?? 'draft' );
	$map    = [
		'draft'          => 1,
		'dry_run'        => 2,
		'pending_review' => 3,
		'applied'        => 4,
		'cancelled'      => 0,
		'undone'         => 5,
	];
	return $map[ $status ] ?? 1;
};
$wpmind_stage = $wpmind_flow_stage( $wpmind_current );
$wpmind_flow  = [
	[ 'create', '创建' ],
	[ 'dry_run', '预演' ],
	[ 'review', '审核' ],
	[ 'apply', '写入' ],
	[ 'undo', '撤销' ],
];

Wenpai_Admin_Shell::page_head(
	__( '任务', 'wpmind' ),
	__( 'AI 批量工作流：Alt 标题 / Meta 元数据 统一走 dry-run → 审核 → 写入 → 撤销。', 'wpmind' )
);
?>

<div class="wenpai-stack">

	<?php /* ---- 卡 1：当前任务（焦点卡） ---- */ ?>
	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '当前任务', 'wpmind' ); ?></h2>
				<?php if ( $wpmind_current ) : ?>
				<p class="card-sub">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: task type label, 2: task id, 3: created at */
							__( '%1$s #%2$d · 创建于 %3$s', 'wpmind' ),
							$wpmind_task_type_label( (string) $wpmind_current['type'] ),
							(int) $wpmind_current['id'],
							(string) ( $wpmind_current['created_at'] ?? '' )
						)
					);
					?>
				</p>
				<?php else : ?>
				<p class="card-sub"><?php esc_html_e( '还没有任务，从右侧「扫描并创建」开始', 'wpmind' ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $wpmind_current ) : ?>
				<?php [ $wpmind_sp_text, $wpmind_sp_state ] = $wpmind_task_status_pill( (string) $wpmind_current['status'] ); ?>
				<span class="pill <?php echo esc_attr( $wpmind_sp_state ); ?>"><?php echo esc_html( $wpmind_sp_text ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $wpmind_current ) : ?>
		<div class="wpmind-flow" aria-label="<?php esc_attr_e( '任务状态机：创建 → 预演 → 审核 → 写入 → 撤销', 'wpmind' ); ?>">
			<?php foreach ( $wpmind_flow as $i => [ $wpmind_step_key, $wpmind_step_label ] ) : ?>
				<?php $wpmind_step_no = $i + 1; ?>
				<span class="wpmind-flow-step <?php echo $wpmind_step_no < $wpmind_stage ? 'is-done' : ( $wpmind_step_no === $wpmind_stage ? 'is-cur' : '' ); ?>">
					<span class="wpmind-flow-dot"><?php echo $wpmind_step_no < $wpmind_stage ? '✓' : ( 'undo' === $wpmind_step_key ? '↩' : (string) $wpmind_step_no ); ?></span>
					<span class="wpmind-flow-label"><?php echo esc_html( $wpmind_step_label . ' ' . $wpmind_step_key ); ?></span>
				</span>
				<?php if ( $i < count( $wpmind_flow ) - 1 ) : ?>
					<span class="wpmind-flow-arrow" aria-hidden="true">→</span>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php
		$wpmind_cs = is_array( $wpmind_current['summary'] ?? null ) ? $wpmind_current['summary'] : [];
		?>
		<div class="wpmind-task-stats" aria-label="<?php esc_attr_e( '任务进度统计', 'wpmind' ); ?>">
			<span>候选 <b><?php echo (int) ( $wpmind_cs['candidates'] ?? 0 ); ?></b></span>
			<span>预览 <b><?php echo (int) ( $wpmind_cs['previewed'] ?? 0 ); ?></b></span>
			<span>通过 <b><?php echo (int) ( $wpmind_cs['approved'] ?? 0 ); ?></b></span>
			<span>拒绝 <b><?php echo (int) ( $wpmind_cs['rejected'] ?? 0 ); ?></b></span>
			<span>已写 <b><?php echo (int) ( $wpmind_cs['applied'] ?? 0 ); ?></b></span>
			<span>撤销 <b><?php echo (int) ( $wpmind_cs['undone'] ?? 0 ); ?></b></span>
			<span>错误 <b><?php echo (int) ( $wpmind_cs['errors'] ?? 0 ); ?></b></span>
		</div>
		<?php endif; ?>

		<div class="card-foot">
			<span class="meta"><?php esc_html_e( '批准的项才会写入；每步有记录，可撤销。', 'wpmind' ); ?></span>
			<span class="wenpai-actions">
				<span class="wpmind-workflow-actset" data-type="alt_bulk">
					<button class="btn btn-ghost" type="button" id="wpmind-workflow-alt-dryrun" disabled><?php esc_html_e( 'Dry-run', 'wpmind' ); ?></button>
					<button class="btn" type="button" id="wpmind-workflow-alt-submit-review" disabled><?php esc_html_e( '提交审核', 'wpmind' ); ?></button>
					<button class="btn btn-primary" type="button" id="wpmind-workflow-alt-apply" disabled><?php esc_html_e( '写入已通过项', 'wpmind' ); ?></button>
					<button class="btn btn-ghost" type="button" id="wpmind-workflow-alt-undo" disabled><?php esc_html_e( '撤销已写入', 'wpmind' ); ?></button>
				</span>
				<span class="wpmind-workflow-actset" data-type="meta_bulk">
					<button class="btn btn-ghost" type="button" id="wpmind-workflow-meta-dryrun" disabled><?php esc_html_e( 'Dry-run', 'wpmind' ); ?></button>
					<button class="btn" type="button" id="wpmind-workflow-meta-submit-review" disabled><?php esc_html_e( '提交审核', 'wpmind' ); ?></button>
					<button class="btn btn-primary" type="button" id="wpmind-workflow-meta-apply" disabled><?php esc_html_e( '写入已通过项', 'wpmind' ); ?></button>
					<button class="btn btn-ghost" type="button" id="wpmind-workflow-meta-undo" disabled><?php esc_html_e( '撤销已写入', 'wpmind' ); ?></button>
				</span>
			</span>
		</div>
	</section>

	<?php /* ---- 卡 2：任务列表 ---- */ ?>
	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '任务列表', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '最近批量任务，按创建时间倒序；点「查看」载入明细', 'wpmind' ); ?></p>
			</div>
			<span class="wenpai-actions">
				<button class="btn btn-ghost" type="button" id="wpmind-workflow-refresh"><?php esc_html_e( '刷新', 'wpmind' ); ?></button>
				<button class="btn btn-primary" type="button" id="wpmind-workflow-alt-create"><?php echo esc_html( sprintf( /* translators: %d: max candidates */ __( '扫描并创建 Alt 任务（≤%d）', 'wpmind' ), $wpmind_limit ) ); ?></button>
				<button class="btn btn-ghost" type="button" id="wpmind-workflow-meta-create"><?php echo esc_html( sprintf( /* translators: %d: max candidates */ __( 'Meta 任务（≤%d）', 'wpmind' ), $wpmind_meta_limit ) ); ?></button>
			</span>
		</div>

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
				<tr class="wpmind-workflow-empty"><td colspan="6"><?php esc_html_e( '暂无任务', 'wpmind' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $wpmind_tasks as $wpmind_task ) : ?>
				<?php
					$wpmind_ts = $wpmind_task_status_pill( (string) $wpmind_task['status'] );
					$wpmind_sm = is_array( $wpmind_task['summary'] ?? null ) ? $wpmind_task['summary'] : [];
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
						<td><span class="pill <?php echo esc_attr( $wpmind_ts[1] ); ?>"><?php echo esc_html( $wpmind_ts[0] ); ?></span></td>
						<td><?php echo esc_html( $wpmind_prog ); ?></td>
						<td class="mono"><?php echo esc_html( (string) ( $wpmind_task['created_at'] ?? '' ) ); ?></td>
						<td><span class="wenpai-actions"><button type="button" class="btn btn-sm btn-secondary wpmind-workflow-view" data-task-id="<?php echo esc_attr( (string) $wpmind_task['id'] ); ?>"><?php esc_html_e( '查看', 'wpmind' ); ?></button></span></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		</div>

		<div class="card-foot">
			<p class="meta" id="wpmind-workflow-status" style="margin:0;"></p>
		</div>
	</section>

	<?php /* ---- 卡 3：任务项明细（fold，JS 载入后展开） ---- */ ?>
	<details class="card wpmind-fold" id="wpmind-workflow-items" open style="display:none;">
		<summary>
			<span class="wpmind-fold-chevron" aria-hidden="true"><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; ?></span>
			<span class="section-title"><?php esc_html_e( '任务项明细', 'wpmind' ); ?></span>
			<span class="meta"><?php esc_html_e( 'before / proposed / applied · 批准的项才会写入', 'wpmind' ); ?></span>
		</summary>
		<div class="wpmind-fold-body">
		<div class="wenpai-table-wrap">
		<table class="tbl">
			<thead>
				<tr>
					<th class="num"><?php esc_html_e( '对象 ID', 'wpmind' ); ?></th>
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
	</details>
</div>
