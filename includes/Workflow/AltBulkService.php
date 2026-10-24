<?php
/**
 * Alt bulk workflow: scan → task → dry-run → review → apply → undo.
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

use WPMind\Modules\MediaIntelligence\AltTextGenerator;

/**
 * Alt bulk create + dry-run + review/apply/undo service.
 *
 * @since 0.14.0
 */
final class AltBulkService {

	/**
	 * Max candidates per alt_bulk task.
	 */
	public const MAX_CANDIDATES = 50;

	/**
	 * Items processed per dry-run AJAX round.
	 */
	public const DRY_RUN_BATCH = 5;

	/**
	 * Items applied per apply AJAX round.
	 */
	public const APPLY_BATCH = 5;

	/**
	 * Task repository.
	 *
	 * @var TaskRepository
	 */
	private TaskRepository $repo;

	/**
	 * Constructor.
	 *
	 * @param TaskRepository|null $repo Repository.
	 */
	public function __construct( ?TaskRepository $repo = null ) {
		$this->repo = $repo ?? new TaskRepository();
	}

	/**
	 * IDs of image attachments missing alt text (excludes prior failures).
	 *
	 * Aligns with MediaAjaxController::ajax_bulk_process SQL semantics.
	 *
	 * @param int $limit Max IDs to return (capped at MAX_CANDIDATES).
	 * @return list<int>
	 */
	public function find_missing_alt_ids( int $limit = self::MAX_CANDIDATES ): array {
		global $wpdb;

		$limit = max( 1, min( self::MAX_CANDIDATES, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- fixed pattern match, not user-supplied wildcards.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%%'
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} pm
			       WHERE pm.post_id = p.ID
			         AND pm.meta_key = '_wp_attachment_image_alt'
			         AND pm.meta_value != ''
			   )
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} pm2
			       WHERE pm2.post_id = p.ID
			         AND pm2.meta_key = '_wpmind_alt_failed'
			   )
			 ORDER BY p.ID ASC
			 LIMIT %d",
				$limit + 1
			)
		);

		$ids    = array_map( 'intval', is_array( $ids ) ? $ids : array() );
		$capped = count( $ids ) > $limit;
		if ( $capped ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		$GLOBALS['wpmind_alt_bulk_last_capped'] = $capped;

		return $ids;
	}

	/**
	 * Create an alt_bulk task from current missing-alt scan.
	 *
	 * @param int|null $limit Optional candidate cap.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create_task_from_scan( ?int $limit = null ) {
		$limit  = $limit ?? self::MAX_CANDIDATES;
		$ids    = $this->find_missing_alt_ids( $limit );
		$capped = ! empty( $GLOBALS['wpmind_alt_bulk_last_capped'] );

		$items = array();
		foreach ( $ids as $id ) {
			$before  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$items[] = array(
				'object_id'   => $id,
				'before'      => $before,
				'proposed'    => '',
				'item_status' => TaskRepository::ITEM_PENDING,
			);
		}

		return $this->repo->create_task(
			array(
				'type'    => TaskRepository::TYPE_ALT_BULK,
				'status'  => TaskRepository::STATUS_DRAFT,
				'items'   => $items,
				'summary' => array(
					'candidates' => count( $items ),
					'previewed'  => 0,
					'errors'     => 0,
					'capped'     => $capped,
					'limit'      => self::MAX_CANDIDATES,
				),
			)
		);
	}

	/**
	 * Dry-run a batch: write proposed alt into task items only (never attachment meta).
	 *
	 * @param int $task_id    Task ID.
	 * @param int $batch_size Batch size.
	 * @return array{task:array<string,mixed>,processed:int,errors:int,done:bool}|\WP_Error
	 */
	public function dry_run_batch( int $task_id, int $batch_size = self::DRY_RUN_BATCH ) {
		$task = $this->repo->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_not_found', __( '任务不存在', 'wpmind' ) );
		}
		if ( TaskRepository::TYPE_ALT_BULK !== ( $task['type'] ?? '' ) ) {
			return new \WP_Error( 'wpmind_task_wrong_type', __( '不是 Alt 批量任务', 'wpmind' ) );
		}

		$batch_size = max( 1, min( 20, $batch_size ) );
		$pending    = array();
		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_PENDING === ( $item['item_status'] ?? '' ) ) {
				$pending[] = $item;
			}
		}

		$slice     = array_slice( $pending, 0, $batch_size );
		$processed = 0;
		$errors    = 0;
		$updates   = array();
		$generator = new AltTextGenerator( false );

		foreach ( $slice as $item ) {
			$oid          = (int) $item['object_id'];
			$alt_before   = (string) get_post_meta( $oid, '_wp_attachment_image_alt', true );
			$title_before = '';
			$post         = get_post( $oid );
			if ( $post ) {
				$title_before = (string) $post->post_title;
			}

			$result = $generator->generate_proposed_alt( $oid );

			// Guard: attachment alt / title must remain unchanged after generate.
			$alt_after = (string) get_post_meta( $oid, '_wp_attachment_image_alt', true );
			if ( $alt_after !== $alt_before ) {
				if ( '' === $alt_before ) {
					delete_post_meta( $oid, '_wp_attachment_image_alt' );
				} else {
					update_post_meta( $oid, '_wp_attachment_image_alt', $alt_before );
				}
			}
			$after_post = get_post( $oid );
			if ( $post && $after_post && (string) $after_post->post_title !== $title_before ) {
				wp_update_post(
					array(
						'ID'         => $oid,
						'post_title' => $title_before,
					)
				);
			}

			if ( is_wp_error( $result ) ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $alt_before,
					'proposed'      => '',
					'item_status'   => TaskRepository::ITEM_ERROR,
					'error_message' => $result->get_error_message(),
				);
				continue;
			}

			++$processed;
			$updates[] = array(
				'object_id'   => $oid,
				'before'      => $alt_before,
				'proposed'    => (string) $result,
				'item_status' => TaskRepository::ITEM_PREVIEWED,
			);
		}

		if ( $updates ) {
			$updated = $this->repo->add_or_update_items( $task_id, $updates );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$task = $updated;
		}

		$this->repo->update_task(
			$task_id,
			array( 'status' => TaskRepository::STATUS_DRY_RUN )
		);
		$task = $this->repo->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_missing', __( '任务读取失败', 'wpmind' ) );
		}

		$still_pending = 0;
		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_PENDING === ( $item['item_status'] ?? '' ) ) {
				++$still_pending;
			}
		}

		return array(
			'task'      => $task,
			'processed' => $processed,
			'errors'    => $errors,
			'done'      => 0 === $still_pending,
		);
	}

	/**
	 * Move task from dry_run to pending_review when no pending items remain.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function mark_pending_review( int $task_id ) {
		$task = $this->require_alt_bulk( $task_id );
		if ( is_wp_error( $task ) ) {
			return $task;
		}

		$status = (string) ( $task['status'] ?? '' );
		if ( TaskRepository::STATUS_PENDING_REVIEW === $status ) {
			return $task;
		}
		if ( TaskRepository::STATUS_DRY_RUN !== $status ) {
			return new \WP_Error( 'wpmind_task_bad_status', __( '仅 dry_run 任务可提交审核', 'wpmind' ) );
		}

		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_PENDING === ( $item['item_status'] ?? '' ) ) {
				return new \WP_Error( 'wpmind_task_still_pending', __( '仍有未预览项，无法提交审核', 'wpmind' ) );
			}
		}

		$updated = $this->repo->update_task(
			$task_id,
			array( 'status' => TaskRepository::STATUS_PENDING_REVIEW )
		);
		return $updated;
	}

	/**
	 * Approve or reject previewed (or already-approved) items.
	 *
	 * @param int              $task_id   Task ID.
	 * @param array<int,mixed> $decisions Decisions (object_id, decision, optional applied).
	 * @return array<string,mixed>|\WP_Error
	 */
	public function review_items( int $task_id, array $decisions ) {
		$task = $this->require_alt_bulk( $task_id );
		if ( is_wp_error( $task ) ) {
			return $task;
		}

		$by_id = array();
		foreach ( $task['items'] as $item ) {
			$by_id[ (int) $item['object_id'] ] = $item;
		}

		$updates = array();
		foreach ( $decisions as $decision ) {
			if ( ! is_array( $decision ) ) {
				continue;
			}
			$oid  = (int) ( $decision['object_id'] ?? 0 );
			$verb = sanitize_key( (string) ( $decision['decision'] ?? '' ) );
			if ( $oid <= 0 || ! isset( $by_id[ $oid ] ) ) {
				return new \WP_Error( 'wpmind_review_bad_item', __( '审核项无效', 'wpmind' ) );
			}
			if ( ! in_array( $verb, array( 'approve', 'reject' ), true ) ) {
				return new \WP_Error( 'wpmind_review_bad_decision', __( '审核决定无效', 'wpmind' ) );
			}

			$item   = $by_id[ $oid ];
			$status = (string) ( $item['item_status'] ?? '' );
			$ok     = in_array(
				$status,
				array(
					TaskRepository::ITEM_PREVIEWED,
					TaskRepository::ITEM_APPROVED,
				),
				true
			);
			if ( ! $ok ) {
				return new \WP_Error(
					'wpmind_review_bad_status',
					sprintf(
						/* translators: %d: attachment ID */
						__( '附件 %d 当前状态不可审核', 'wpmind' ),
						$oid
					)
				);
			}

			if ( 'reject' === $verb ) {
				$updates[] = array(
					'object_id'   => $oid,
					'before'      => (string) ( $item['before'] ?? '' ),
					'proposed'    => (string) ( $item['proposed'] ?? '' ),
					'item_status' => TaskRepository::ITEM_REJECTED,
				);
				continue;
			}

			$applied   = array_key_exists( 'applied', $decision )
				? (string) $decision['applied']
				: (string) ( $item['proposed'] ?? '' );
			$updates[] = array(
				'object_id'   => $oid,
				'before'      => (string) ( $item['before'] ?? '' ),
				'proposed'    => (string) ( $item['proposed'] ?? '' ),
				'applied'     => $applied,
				'item_status' => TaskRepository::ITEM_APPROVED,
			);
		}

		if ( ! $updates ) {
			return new \WP_Error( 'wpmind_review_empty', __( '没有可审核的项', 'wpmind' ) );
		}

		// Ensure header is at least pending_review once decisions land.
		$header_status = (string) ( $task['status'] ?? '' );
		if ( TaskRepository::STATUS_DRY_RUN === $header_status ) {
			$still_pending = false;
			foreach ( $task['items'] as $item ) {
				if ( TaskRepository::ITEM_PENDING === ( $item['item_status'] ?? '' ) ) {
					$still_pending = true;
					break;
				}
			}
			if ( ! $still_pending ) {
				$this->repo->update_task(
					$task_id,
					array( 'status' => TaskRepository::STATUS_PENDING_REVIEW )
				);
			}
		}

		return $this->repo->add_or_update_items( $task_id, $updates );
	}

	/**
	 * Write approved items to attachment alt meta (batched).
	 *
	 * On failure keeps APPROVED and sets error_message. Concurrent before mismatch → ITEM_ERROR.
	 *
	 * @param int $task_id    Task ID.
	 * @param int $batch_size Batch size.
	 * @return array{task:array<string,mixed>,processed:int,errors:int,done:bool}|\WP_Error
	 */
	public function apply_approved( int $task_id, int $batch_size = self::APPLY_BATCH ) {
		$task = $this->require_alt_bulk( $task_id );
		if ( is_wp_error( $task ) ) {
			return $task;
		}

		$batch_size = max( 1, min( 20, $batch_size ) );
		$approved   = array();
		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_APPROVED === ( $item['item_status'] ?? '' ) ) {
				$approved[] = $item;
			}
		}

		$slice     = array_slice( $approved, 0, $batch_size );
		$processed = 0;
		$errors    = 0;
		$updates   = array();

		foreach ( $slice as $item ) {
			$oid     = (int) $item['object_id'];
			$before  = (string) ( $item['before'] ?? '' );
			$applied = array_key_exists( 'applied', $item )
				? (string) $item['applied']
				: (string) ( $item['proposed'] ?? '' );

			$current = (string) get_post_meta( $oid, '_wp_attachment_image_alt', true );
			if ( $current !== $before ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => (string) ( $item['proposed'] ?? '' ),
					'applied'       => $applied,
					'item_status'   => TaskRepository::ITEM_ERROR,
					'error_message' => __( '附件 Alt 已变更，跳过写入以防覆盖', 'wpmind' ),
				);
				continue;
			}

			$ok = update_post_meta( $oid, '_wp_attachment_image_alt', $applied );
			// update_post_meta returns false when value unchanged; treat as success if current equals applied.
			$after = (string) get_post_meta( $oid, '_wp_attachment_image_alt', true );
			if ( $after !== $applied && false === $ok ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => (string) ( $item['proposed'] ?? '' ),
					'applied'       => $applied,
					'item_status'   => TaskRepository::ITEM_APPROVED,
					'error_message' => __( '写入 Alt 失败', 'wpmind' ),
				);
				continue;
			}

			++$processed;
			$updates[] = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => (string) ( $item['proposed'] ?? '' ),
				'applied'     => $applied,
				'applied_at'  => gmdate( 'Y-m-d H:i:s' ),
				'item_status' => TaskRepository::ITEM_APPLIED,
			);
		}

		if ( $updates ) {
			$updated = $this->repo->add_or_update_items( $task_id, $updates );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$task = $updated;
		} else {
			$task = $this->repo->get_task( $task_id );
			if ( ! $task ) {
				return new \WP_Error( 'wpmind_task_missing', __( '任务读取失败', 'wpmind' ) );
			}
		}

		$still_approved = 0;
		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_APPROVED === ( $item['item_status'] ?? '' ) ) {
				++$still_approved;
			}
		}

		$done = 0 === $still_approved;
		if ( $done ) {
			$this->repo->update_task(
				$task_id,
				array( 'status' => TaskRepository::STATUS_APPLIED )
			);
			$task = $this->repo->get_task( $task_id );
			if ( ! $task ) {
				return new \WP_Error( 'wpmind_task_missing', __( '任务读取失败', 'wpmind' ) );
			}
		}

		return array(
			'task'      => $task,
			'processed' => $processed,
			'errors'    => $errors,
			'done'      => $done,
		);
	}

	/**
	 * Restore applied items' alt to before snapshot.
	 *
	 * @param int            $task_id    Task ID.
	 * @param list<int>|null $object_ids Optional subset; null = all applied.
	 * @return array{task:array<string,mixed>,processed:int,errors:int}|\WP_Error
	 */
	public function undo_applied( int $task_id, ?array $object_ids = null ) {
		$task = $this->require_alt_bulk( $task_id );
		if ( is_wp_error( $task ) ) {
			return $task;
		}

		$filter = null;
		if ( null !== $object_ids ) {
			$filter = array();
			foreach ( $object_ids as $id ) {
				$filter[ (int) $id ] = true;
			}
		}

		$processed = 0;
		$errors    = 0;
		$updates   = array();

		foreach ( $task['items'] as $item ) {
			if ( TaskRepository::ITEM_APPLIED !== ( $item['item_status'] ?? '' ) ) {
				continue;
			}
			$oid = (int) $item['object_id'];
			if ( null !== $filter && ! isset( $filter[ $oid ] ) ) {
				continue;
			}

			$before  = (string) ( $item['before'] ?? '' );
			$applied = array_key_exists( 'applied', $item )
				? (string) $item['applied']
				: (string) ( $item['proposed'] ?? '' );
			$current = (string) get_post_meta( $oid, '_wp_attachment_image_alt', true );

			if ( $current !== $applied ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => (string) ( $item['proposed'] ?? '' ),
					'applied'       => $applied,
					'applied_at'    => (string) ( $item['applied_at'] ?? '' ),
					'item_status'   => TaskRepository::ITEM_APPLIED,
					'error_message' => __( '附件 Alt 已变更，跳过撤销', 'wpmind' ),
				);
				continue;
			}

			if ( '' === $before ) {
				delete_post_meta( $oid, '_wp_attachment_image_alt' );
			} else {
				update_post_meta( $oid, '_wp_attachment_image_alt', $before );
			}

			++$processed;
			$row = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => (string) ( $item['proposed'] ?? '' ),
				'applied'     => $applied,
				'item_status' => TaskRepository::ITEM_UNDONE,
			);
			if ( ! empty( $item['applied_at'] ) ) {
				$row['applied_at'] = (string) $item['applied_at'];
			}
			$updates[] = $row;
		}

		if ( $updates ) {
			$updated = $this->repo->add_or_update_items( $task_id, $updates );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$task = $updated;
		}

		return array(
			'task'      => $task,
			'processed' => $processed,
			'errors'    => $errors,
		);
	}

	/**
	 * Require an existing alt_bulk task.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function require_alt_bulk( int $task_id ) {
		$task = $this->repo->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_not_found', __( '任务不存在', 'wpmind' ) );
		}
		if ( TaskRepository::TYPE_ALT_BULK !== ( $task['type'] ?? '' ) ) {
			return new \WP_Error( 'wpmind_task_wrong_type', __( '不是 Alt 批量任务', 'wpmind' ) );
		}
		return $task;
	}
}
