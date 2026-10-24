<?php
/**
 * Alt bulk workflow: scan → task → dry-run preview (no write).
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

use WPMind\Modules\MediaIntelligence\AltTextGenerator;

/**
 * Alt bulk create + dry-run service.
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
}
