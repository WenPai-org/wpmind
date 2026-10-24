<?php
/**
 * Meta bulk workflow: scan → task → dry-run → review → apply → undo.
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

use WPMind\Modules\AutoMeta\MetaGenerator;

/**
 * Meta bulk create + dry-run + review/apply/undo service.
 *
 * @since 0.14.0
 */
final class MetaBulkService {

	/**
	 * Max candidates per meta_bulk task.
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
	 * Approximate max_tokens per generate_all_meta call.
	 */
	public const ESTIMATED_TOKENS_PER_CALL = 800;

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
	 * Find published posts that look like they are missing Auto Meta.
	 *
	 * Heuristic (any one qualifies): empty excerpt (if excerpt on), missing SEO
	 * desc (if on), missing FAQ (if on), or no `_wpmind_auto_meta_source`.
	 * Excludes posts with `_wpmind_auto_meta_lock=1`.
	 *
	 * @param int $limit Max IDs (capped at MAX_CANDIDATES).
	 * @return list<int>
	 */
	public function find_candidate_post_ids( int $limit = self::MAX_CANDIDATES ): array {
		$limit = max( 1, min( self::MAX_CANDIDATES, $limit ) );
		$types = get_option( 'wpmind_auto_meta_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $types ) || ! $types ) {
			$types = array( 'post', 'page' );
		}

		$want_excerpt = get_option( 'wpmind_auto_meta_excerpt', '1' ) === '1';
		$want_seo     = get_option( 'wpmind_auto_meta_seo_desc', '1' ) === '1';
		$want_faq     = get_option( 'wpmind_auto_meta_faq', '1' ) === '1';

		$ids = array();
		foreach ( $types as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' === $type ) {
				continue;
			}
			$found = get_posts(
				array(
					'post_type'              => $type,
					'post_status'            => 'publish',
					'posts_per_page'         => $limit + 1,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $found as $pid ) {
				$ids[] = (int) $pid;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_NUMERIC );

		$candidates = array();
		foreach ( $ids as $pid ) {
			if ( '1' === (string) get_post_meta( $pid, '_wpmind_auto_meta_lock', true ) ) {
				continue;
			}
			if ( ! $this->post_needs_meta( $pid, $want_excerpt, $want_seo, $want_faq ) ) {
				continue;
			}
			$candidates[] = $pid;
			if ( count( $candidates ) > $limit ) {
				break;
			}
		}

		$capped = count( $candidates ) > $limit;
		if ( $capped ) {
			$candidates = array_slice( $candidates, 0, $limit );
		}

		$GLOBALS['wpmind_meta_bulk_last_capped'] = $capped;

		return $candidates;
	}

	/**
	 * Create a meta_bulk task from current missing-meta scan.
	 *
	 * @param int|null $limit Optional candidate cap.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create_task_from_scan( ?int $limit = null ) {
		$limit  = $limit ?? self::MAX_CANDIDATES;
		$ids    = $this->find_candidate_post_ids( $limit );
		$capped = ! empty( $GLOBALS['wpmind_meta_bulk_last_capped'] );
		$count  = count( $ids );

		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'object_id'   => $id,
				'before'      => $this->snapshot_fields( $id ),
				'proposed'    => array(),
				'item_status' => TaskRepository::ITEM_PENDING,
			);
		}

		return $this->repo->create_task(
			array(
				'type'    => TaskRepository::TYPE_META_BULK,
				'status'  => TaskRepository::STATUS_DRAFT,
				'items'   => $items,
				'summary' => array(
					'candidates'           => $count,
					'previewed'            => 0,
					'errors'               => 0,
					'capped'               => $capped,
					'limit'                => self::MAX_CANDIDATES,
					'estimated_calls'      => $count,
					'estimated_max_tokens' => $count * self::ESTIMATED_TOKENS_PER_CALL,
				),
			)
		);
	}

	/**
	 * Dry-run a batch: write proposed meta into task items only (never post fields/meta).
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
		if ( TaskRepository::TYPE_META_BULK !== ( $task['type'] ?? '' ) ) {
			return new \WP_Error( 'wpmind_task_wrong_type', __( '不是 Meta 批量任务', 'wpmind' ) );
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

		if ( ! class_exists( MetaGenerator::class ) ) {
			$autoload = dirname( __DIR__, 2 ) . '/modules/auto-meta/includes/MetaGenerator.php';
			if ( is_readable( $autoload ) ) {
				require_once $autoload;
			}
		}
		$generator = new MetaGenerator();

		foreach ( $slice as $item ) {
			$oid    = (int) $item['object_id'];
			$guard  = $this->capture_writable_state( $oid );
			$before = is_array( $item['before'] ?? null )
				? $item['before']
				: $this->snapshot_fields( $oid );

			$result = $generator->preview_meta( $oid );

			$restored = $this->restore_if_mutated( $oid, $guard );
			if ( $restored ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => array(),
					'item_status'   => TaskRepository::ITEM_ERROR,
					'error_message' => __( 'Dry-run 意外改写了文章 Meta，已还原', 'wpmind' ),
				);
				continue;
			}

			if ( is_wp_error( $result ) ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => array(),
					'item_status'   => TaskRepository::ITEM_ERROR,
					'error_message' => $result->get_error_message(),
				);
				continue;
			}

			++$processed;
			$updates[] = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => $result,
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
	 * Whether a post matches the "missing meta" heuristic.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $want_excerpt Excerpt feature on.
	 * @param bool $want_seo     SEO feature on.
	 * @param bool $want_faq     FAQ feature on.
	 * @return bool
	 */
	private function post_needs_meta( int $post_id, bool $want_excerpt, bool $want_seo, bool $want_faq ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( $want_excerpt && '' === trim( (string) $post->post_excerpt ) ) {
			return true;
		}
		if ( $want_seo && '' === (string) get_post_meta( $post_id, '_wpmind_seo_description', true ) ) {
			return true;
		}
		if ( $want_faq && '' === (string) get_post_meta( $post_id, '_wpmind_faq_schema', true ) ) {
			return true;
		}
		if ( '' === (string) get_post_meta( $post_id, '_wpmind_auto_meta_source', true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Snapshot current meta fields for before/proposed storage.
	 *
	 * @param int $post_id Post ID.
	 * @return array{excerpt:string,seo_description:string,tags:list<string>,category:string,faq:list<mixed>,source:string}
	 */
	private function snapshot_fields( int $post_id ): array {
		$post    = get_post( $post_id );
		$excerpt = $post ? (string) $post->post_excerpt : '';

		$tags = array();
		if ( function_exists( 'wp_get_post_tags' ) ) {
			$raw = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $tag ) {
					$tags[] = (string) $tag;
				}
			}
		} elseif ( isset( $GLOBALS['wpmind_test_post_tags'][ $post_id ] ) && is_array( $GLOBALS['wpmind_test_post_tags'][ $post_id ] ) ) {
			$tags = array_map( 'strval', $GLOBALS['wpmind_test_post_tags'][ $post_id ] );
		}

		$category = '';
		if ( function_exists( 'wp_get_post_categories' ) ) {
			$cats = wp_get_post_categories( $post_id );
			if ( is_array( $cats ) && $cats ) {
				$cid = (int) $cats[0];
				if ( function_exists( 'get_cat_name' ) ) {
					$category = (string) get_cat_name( $cid );
				} else {
					$category = (string) $cid;
				}
			}
		} elseif ( isset( $GLOBALS['wpmind_test_post_categories'][ $post_id ] ) ) {
			$category = (string) $GLOBALS['wpmind_test_post_categories'][ $post_id ];
		}

		$faq_raw = get_post_meta( $post_id, '_wpmind_faq_schema', true );
		$faq     = array();
		if ( is_string( $faq_raw ) && '' !== $faq_raw ) {
			$decoded = json_decode( $faq_raw, true );
			if ( is_array( $decoded ) ) {
				$faq = $decoded;
			}
		} elseif ( is_array( $faq_raw ) ) {
			$faq = $faq_raw;
		}

		return array(
			'excerpt'         => $excerpt,
			'seo_description' => (string) get_post_meta( $post_id, '_wpmind_seo_description', true ),
			'tags'            => array_values( $tags ),
			'category'        => $category,
			'faq'             => $faq,
			'source'          => (string) get_post_meta( $post_id, '_wpmind_auto_meta_source', true ),
		);
	}

	/**
	 * Capture writable state for dry-run mutation guard.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function capture_writable_state( int $post_id ): array {
		$post = get_post( $post_id );
		$tags = array();
		if ( function_exists( 'wp_get_post_tags' ) ) {
			$raw  = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$tags = is_array( $raw ) ? array_map( 'strval', $raw ) : array();
		} elseif ( isset( $GLOBALS['wpmind_test_post_tags'][ $post_id ] ) ) {
			$tags = array_map( 'strval', (array) $GLOBALS['wpmind_test_post_tags'][ $post_id ] );
		}
		sort( $tags );

		$cats = array();
		if ( function_exists( 'wp_get_post_categories' ) ) {
			$raw  = wp_get_post_categories( $post_id );
			$cats = is_array( $raw ) ? array_map( 'intval', $raw ) : array();
		} elseif ( isset( $GLOBALS['wpmind_test_post_cat_ids'][ $post_id ] ) ) {
			$cats = array_map( 'intval', (array) $GLOBALS['wpmind_test_post_cat_ids'][ $post_id ] );
		}
		sort( $cats );

		return array(
			'excerpt'         => $post ? (string) $post->post_excerpt : '',
			'tags'            => $tags,
			'categories'      => $cats,
			'seo_description' => (string) get_post_meta( $post_id, '_wpmind_seo_description', true ),
			'faq_schema'      => (string) get_post_meta( $post_id, '_wpmind_faq_schema', true ),
			'source'          => (string) get_post_meta( $post_id, '_wpmind_auto_meta_source', true ),
			'hash'            => (string) get_post_meta( $post_id, '_wpmind_auto_meta_hash', true ),
			'generated_at'    => (string) get_post_meta( $post_id, '_wpmind_auto_meta_generated_at', true ),
			'lock'            => (string) get_post_meta( $post_id, '_wpmind_auto_meta_lock', true ),
		);
	}

	/**
	 * Restore post fields/meta if dry-run mutated them. Returns true if a mutation was found.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $guard   Captured state.
	 * @return bool
	 */
	private function restore_if_mutated( int $post_id, array $guard ): bool {
		$after   = $this->capture_writable_state( $post_id );
		$mutated = false;

		if ( (string) $after['excerpt'] !== (string) $guard['excerpt'] ) {
			$mutated = true;
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_excerpt' => (string) $guard['excerpt'],
				)
			);
		}

		if ( (array) $after['tags'] !== (array) $guard['tags'] ) {
			$mutated = true;
			if ( function_exists( 'wp_set_post_tags' ) ) {
				wp_set_post_tags( $post_id, (array) $guard['tags'], false );
			} else {
				$GLOBALS['wpmind_test_post_tags'][ $post_id ] = (array) $guard['tags'];
			}
		}

		if ( (array) $after['categories'] !== (array) $guard['categories'] ) {
			$mutated = true;
			if ( function_exists( 'wp_set_post_categories' ) ) {
				wp_set_post_categories( $post_id, (array) $guard['categories'] );
			} else {
				$GLOBALS['wpmind_test_post_cat_ids'][ $post_id ] = (array) $guard['categories'];
			}
		}

		foreach (
			array(
				'_wpmind_seo_description'        => 'seo_description',
				'_wpmind_faq_schema'             => 'faq_schema',
				'_wpmind_auto_meta_source'       => 'source',
				'_wpmind_auto_meta_hash'         => 'hash',
				'_wpmind_auto_meta_generated_at' => 'generated_at',
				'_wpmind_auto_meta_lock'         => 'lock',
			) as $meta_key => $guard_key
		) {
			$before_val = (string) ( $guard[ $guard_key ] ?? '' );
			$after_val  = (string) ( $after[ $guard_key ] ?? '' );
			if ( $before_val === $after_val ) {
				continue;
			}
			$mutated = true;
			if ( '' === $before_val ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $before_val );
			}
		}

		return $mutated;
	}

	/**
	 * Move task from dry_run to pending_review when no pending items remain.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function mark_pending_review( int $task_id ) {
		$task = $this->require_meta_bulk( $task_id );
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

		return $this->repo->update_task(
			$task_id,
			array( 'status' => TaskRepository::STATUS_PENDING_REVIEW )
		);
	}

	/**
	 * Approve or reject previewed (or already-approved) items.
	 *
	 * @param int              $task_id   Task ID.
	 * @param array<int,mixed> $decisions Decisions (object_id, decision, optional applied array).
	 * @return array<string,mixed>|\WP_Error
	 */
	public function review_items( int $task_id, array $decisions ) {
		$task = $this->require_meta_bulk( $task_id );
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
						/* translators: %d: post ID */
						__( '文章 %d 当前状态不可审核', 'wpmind' ),
						$oid
					)
				);
			}

			$before   = $this->as_snapshot_array( $item['before'] ?? array() );
			$proposed = $this->as_snapshot_array( $item['proposed'] ?? array() );

			if ( 'reject' === $verb ) {
				$updates[] = array(
					'object_id'   => $oid,
					'before'      => $before,
					'proposed'    => $proposed,
					'item_status' => TaskRepository::ITEM_REJECTED,
				);
				continue;
			}

			$applied = array_key_exists( 'applied', $decision ) && is_array( $decision['applied'] )
				? $this->as_snapshot_array( $decision['applied'] )
				: $proposed;

			$updates[] = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => $proposed,
				'applied'     => $applied,
				'item_status' => TaskRepository::ITEM_APPROVED,
			);
		}

		if ( ! $updates ) {
			return new \WP_Error( 'wpmind_review_empty', __( '没有可审核的项', 'wpmind' ) );
		}

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
	 * Write approved items to post fields/meta (batched).
	 *
	 * Concurrent before mismatch on writable fields → ITEM_ERROR (no silent overwrite).
	 *
	 * @param int $task_id    Task ID.
	 * @param int $batch_size Batch size.
	 * @return array{task:array<string,mixed>,processed:int,errors:int,done:bool}|\WP_Error
	 */
	public function apply_approved( int $task_id, int $batch_size = self::APPLY_BATCH ) {
		$task = $this->require_meta_bulk( $task_id );
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
			$oid      = (int) $item['object_id'];
			$before   = $this->as_snapshot_array( $item['before'] ?? array() );
			$proposed = $this->as_snapshot_array( $item['proposed'] ?? array() );
			$applied  = array_key_exists( 'applied', $item ) && is_array( $item['applied'] )
				? $this->as_snapshot_array( $item['applied'] )
				: $proposed;

			$current = $this->snapshot_fields( $oid );
			$keys    = $this->writable_field_keys( $applied, $before, $current );

			if ( $this->snapshots_differ_on( $current, $before, $keys ) ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => $proposed,
					'applied'       => $applied,
					'item_status'   => TaskRepository::ITEM_ERROR,
					'error_message' => __( '文章 Meta 已变更，跳过写入以防覆盖', 'wpmind' ),
				);
				continue;
			}

			$write = $this->apply_fields( $oid, $applied, $before );
			if ( is_wp_error( $write ) ) {
				++$errors;
				$updates[] = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => $proposed,
					'applied'       => $applied,
					'item_status'   => TaskRepository::ITEM_APPROVED,
					'error_message' => $write->get_error_message(),
				);
				continue;
			}

			++$processed;
			$updates[] = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => $proposed,
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
	 * Restore applied items' fields/meta to before snapshot.
	 *
	 * If current values differ from applied on restored fields, skip that item.
	 *
	 * @param int            $task_id    Task ID.
	 * @param list<int>|null $object_ids Optional subset; null = all applied.
	 * @return array{task:array<string,mixed>,processed:int,errors:int}|\WP_Error
	 */
	public function undo_applied( int $task_id, ?array $object_ids = null ) {
		$task = $this->require_meta_bulk( $task_id );
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

			$before   = $this->as_snapshot_array( $item['before'] ?? array() );
			$proposed = $this->as_snapshot_array( $item['proposed'] ?? array() );
			$applied  = array_key_exists( 'applied', $item ) && is_array( $item['applied'] )
				? $this->as_snapshot_array( $item['applied'] )
				: $proposed;
			$current  = $this->snapshot_fields( $oid );

			// Guard: if user changed fields after apply, skip.
			// Skip 'source' (tracking): apply always writes source=auto while proposed may omit it.
			$check_keys = array( 'excerpt', 'seo_description', 'tags', 'category', 'faq' );
			if ( $this->snapshots_differ_on( $current, $applied, $check_keys ) ) {
				++$errors;
				$row = array(
					'object_id'     => $oid,
					'before'        => $before,
					'proposed'      => $proposed,
					'applied'       => $applied,
					'item_status'   => TaskRepository::ITEM_APPLIED,
					'error_message' => __( '文章 Meta 已变更，跳过撤销', 'wpmind' ),
				);
				if ( ! empty( $item['applied_at'] ) ) {
					$row['applied_at'] = (string) $item['applied_at'];
				}
				$updates[] = $row;
				continue;
			}

			$this->restore_fields( $oid, $before );

			++$processed;
			$row = array(
				'object_id'   => $oid,
				'before'      => $before,
				'proposed'    => $proposed,
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
	 * Require an existing meta_bulk task.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function require_meta_bulk( int $task_id ) {
		$task = $this->repo->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_not_found', __( '任务不存在', 'wpmind' ) );
		}
		if ( TaskRepository::TYPE_META_BULK !== ( $task['type'] ?? '' ) ) {
			return new \WP_Error( 'wpmind_task_wrong_type', __( '不是 Meta 批量任务', 'wpmind' ) );
		}
		return $task;
	}

	/**
	 * Coerce a snapshot value to the Meta array shape.
	 *
	 * @param mixed $value Raw snapshot.
	 * @return array{excerpt:string,seo_description:string,tags:list<string>,category:string,faq:list<mixed>,source:string}
	 */
	private function as_snapshot_array( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = array();
		}
		$tags = array();
		if ( ! empty( $value['tags'] ) && is_array( $value['tags'] ) ) {
			foreach ( $value['tags'] as $tag ) {
				$tags[] = (string) $tag;
			}
		}
		$faq = array();
		if ( ! empty( $value['faq'] ) && is_array( $value['faq'] ) ) {
			$faq = $value['faq'];
		}
		return array(
			'excerpt'         => (string) ( $value['excerpt'] ?? '' ),
			'seo_description' => (string) ( $value['seo_description'] ?? '' ),
			'tags'            => $tags,
			'category'        => (string) ( $value['category'] ?? '' ),
			'faq'             => $faq,
			'source'          => (string) ( $value['source'] ?? '' ),
		);
	}

	/**
	 * Field keys that apply_fields would attempt to write.
	 *
	 * @param array<string,mixed> $applied Applied snapshot.
	 * @param array<string,mixed> $before  Before snapshot.
	 * @param array<string,mixed> $current Current snapshot.
	 * @return list<string>
	 */
	private function writable_field_keys( array $applied, array $before, array $current ): array {
		// Intended write keys from options + applied payload (ignore current empty-guards).
		// Empty/default guards live in apply_fields; concurrency compares these vs before.
		unset( $before, $current );
		$keys = array();

		if ( get_option( 'wpmind_auto_meta_excerpt', '1' ) === '1'
			&& '' !== trim( (string) ( $applied['excerpt'] ?? '' ) )
		) {
			$keys[] = 'excerpt';
		}

		if ( get_option( 'wpmind_auto_meta_tags', '1' ) === '1'
			&& ! empty( $applied['tags'] ) && is_array( $applied['tags'] )
		) {
			$keys[] = 'tags';
		}

		if ( get_option( 'wpmind_auto_meta_category', '0' ) === '1'
			&& '' !== (string) ( $applied['category'] ?? '' )
		) {
			$keys[] = 'category';
		}

		if ( get_option( 'wpmind_auto_meta_faq', '1' ) === '1' && ! empty( $applied['faq'] ) ) {
			$keys[] = 'faq';
		}

		if ( get_option( 'wpmind_auto_meta_seo_desc', '1' ) === '1'
			&& '' !== (string) ( $applied['seo_description'] ?? '' )
		) {
			$keys[] = 'seo_description';
		}

		$unique = array();
		foreach ( array_unique( $keys ) as $key ) {
			$unique[] = (string) $key;
		}
		return $unique;
	}

	/**
	 * Whether snapshots differ on any of the given keys.
	 *
	 * @param array $a    Left snapshot.
	 * @param array $b    Right snapshot.
	 * @param array $keys Keys to compare.
	 * @return bool
	 */
	private function snapshots_differ_on( array $a, array $b, array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( 'tags' === $key ) {
				if ( $this->normalize_tags( (array) ( $a['tags'] ?? array() ) )
					!== $this->normalize_tags( (array) ( $b['tags'] ?? array() ) ) ) {
					return true;
				}
				continue;
			}
			if ( 'faq' === $key ) {
				$left  = wp_json_encode( $a['faq'] ?? array() );
				$right = wp_json_encode( $b['faq'] ?? array() );
				if ( (string) $left !== (string) $right ) {
					return true;
				}
				continue;
			}
			if ( (string) ( $a[ $key ] ?? '' ) !== (string) ( $b[ $key ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalize tag list for comparison.
	 *
	 * @param array<int|string,mixed> $tags Tags.
	 * @return list<string>
	 */
	private function normalize_tags( array $tags ): array {
		$out = array();
		foreach ( $tags as $tag ) {
			$out[] = (string) $tag;
		}
		sort( $out );
		return $out;
	}

	/**
	 * Apply approved fields to a post (save_meta-aligned guards). Never leaves lock meta.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $applied Applied snapshot.
	 * @param array<string,mixed> $before  Before snapshot.
	 * @return true|\WP_Error
	 */
	private function apply_fields( int $post_id, array $applied, array $before ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'wpmind_meta_apply_bad_post', __( '文章不存在', 'wpmind' ) );
		}

		// 1. Excerpt (only when empty, or still equals before).
		if ( get_option( 'wpmind_auto_meta_excerpt', '1' ) === '1'
			&& '' !== trim( (string) ( $applied['excerpt'] ?? '' ) )
		) {
			$current_excerpt = (string) $post->post_excerpt;
			if ( '' === trim( $current_excerpt )
				|| (string) ( $before['excerpt'] ?? '' ) === $current_excerpt
			) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_excerpt' => sanitize_text_field( (string) $applied['excerpt'] ),
					)
				);
			}
		}

		// 2. Tags (only when empty, or still equals before).
		if ( get_option( 'wpmind_auto_meta_tags', '1' ) === '1'
			&& ! empty( $applied['tags'] ) && is_array( $applied['tags'] )
		) {
			$current_tags = $this->get_post_tag_names( $post_id );
			$before_tags  = $this->normalize_tags( (array) ( $before['tags'] ?? array() ) );
			if ( empty( $current_tags )
				|| $before_tags === $this->normalize_tags( $current_tags )
			) {
				$tags = array_map( 'sanitize_text_field', array_map( 'strval', $applied['tags'] ) );
				$this->set_post_tag_names( $post_id, $tags );
			}
		}

		// 3. Category (only when default-only, or still equals before).
		if ( get_option( 'wpmind_auto_meta_category', '0' ) === '1'
			&& '' !== (string) ( $applied['category'] ?? '' )
		) {
			$current_cat = (string) $this->snapshot_fields( $post_id )['category'];
			$before_cat  = (string) ( $before['category'] ?? '' );
			if ( $current_cat === $before_cat || $this->is_default_category_only( $post_id ) ) {
				$this->set_post_category_by_name( $post_id, (string) $applied['category'] );
			}
		}

		// 4. FAQ Schema.
		if ( get_option( 'wpmind_auto_meta_faq', '1' ) === '1' && ! empty( $applied['faq'] ) ) {
			update_post_meta( $post_id, '_wpmind_faq_schema', wp_json_encode( $applied['faq'] ) );
		}

		// 5. SEO description.
		if ( get_option( 'wpmind_auto_meta_seo_desc', '1' ) === '1'
			&& '' !== (string) ( $applied['seo_description'] ?? '' )
		) {
			update_post_meta(
				$post_id,
				'_wpmind_seo_description',
				sanitize_text_field( (string) $applied['seo_description'] )
			);
		}

		// 6. Tracking metadata (no lock).
		$post = get_post( $post_id );
		update_post_meta( $post_id, '_wpmind_auto_meta_source', 'auto' );
		$hash_src = $post
			? (string) $post->post_title . (string) $post->post_content
			: '';
		update_post_meta( $post_id, '_wpmind_auto_meta_hash', md5( $hash_src ) );
		$generated_at = function_exists( 'current_time' )
			? (string) current_time( 'mysql' )
			: gmdate( 'Y-m-d H:i:s' );
		update_post_meta( $post_id, '_wpmind_auto_meta_generated_at', $generated_at );
		delete_post_meta( $post_id, '_wpmind_auto_meta_lock' );

		return true;
	}

	/**
	 * Restore post fields/meta from before snapshot.
	 *
	 * Empty before values clear excerpt/tags/FAQ/SEO; tracking deleted when before.source empty.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $before  Before snapshot.
	 * @return void
	 */
	private function restore_fields( int $post_id, array $before ): void {
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_excerpt' => (string) ( $before['excerpt'] ?? '' ),
			)
		);

		$this->set_post_tag_names( $post_id, array_map( 'strval', (array) ( $before['tags'] ?? array() ) ) );

		$before_cat = (string) ( $before['category'] ?? '' );
		if ( '' === $before_cat ) {
			$this->reset_post_to_default_category( $post_id );
		} else {
			$this->set_post_category_by_name( $post_id, $before_cat );
		}

		$faq = $before['faq'] ?? array();
		if ( empty( $faq ) ) {
			delete_post_meta( $post_id, '_wpmind_faq_schema' );
		} else {
			update_post_meta( $post_id, '_wpmind_faq_schema', wp_json_encode( $faq ) );
		}

		$seo = (string) ( $before['seo_description'] ?? '' );
		if ( '' === $seo ) {
			delete_post_meta( $post_id, '_wpmind_seo_description' );
		} else {
			update_post_meta( $post_id, '_wpmind_seo_description', $seo );
		}

		$source = (string) ( $before['source'] ?? '' );
		if ( '' === $source ) {
			delete_post_meta( $post_id, '_wpmind_auto_meta_source' );
			delete_post_meta( $post_id, '_wpmind_auto_meta_hash' );
			delete_post_meta( $post_id, '_wpmind_auto_meta_generated_at' );
		} else {
			update_post_meta( $post_id, '_wpmind_auto_meta_source', $source );
		}
		delete_post_meta( $post_id, '_wpmind_auto_meta_lock' );
	}

	/**
	 * Get post tag names (WP or test stub).
	 *
	 * @param int $post_id Post ID.
	 * @return list<string>
	 */
	private function get_post_tag_names( int $post_id ): array {
		if ( function_exists( 'wp_get_post_tags' ) ) {
			$raw = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			return is_array( $raw ) ? array_map( 'strval', $raw ) : array();
		}
		if ( isset( $GLOBALS['wpmind_test_post_tags'][ $post_id ] ) && is_array( $GLOBALS['wpmind_test_post_tags'][ $post_id ] ) ) {
			return array_map( 'strval', $GLOBALS['wpmind_test_post_tags'][ $post_id ] );
		}
		return array();
	}

	/**
	 * Set post tags (WP or test stub).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tags    Tag names.
	 * @return void
	 */
	private function set_post_tag_names( int $post_id, array $tags ): void {
		if ( function_exists( 'wp_set_post_tags' ) ) {
			wp_set_post_tags( $post_id, $tags, false );
			return;
		}
		$GLOBALS['wpmind_test_post_tags'][ $post_id ] = $tags;
	}

	/**
	 * Whether the post has only the default category (WP) or empty (tests).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_default_category_only( int $post_id ): bool {
		if ( function_exists( 'wp_get_post_categories' ) ) {
			$cats        = wp_get_post_categories( $post_id );
			$default_cat = (int) get_option( 'default_category', 1 );
			return is_array( $cats ) && 1 === count( $cats ) && (int) $cats[0] === $default_cat;
		}
		$cat = (string) ( $GLOBALS['wpmind_test_post_categories'][ $post_id ] ?? '' );
		return '' === $cat || 'Uncategorized' === $cat;
	}

	/**
	 * Set post category by name (WP or test stub).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Category name.
	 * @return void
	 */
	private function set_post_category_by_name( int $post_id, string $name ): void {
		if ( function_exists( 'get_cat_ID' ) && function_exists( 'wp_set_post_categories' ) ) {
			$cat = (int) get_cat_ID( sanitize_text_field( $name ) );
			if ( $cat > 0 ) {
				wp_set_post_categories( $post_id, array( $cat ) );
			}
			return;
		}
		$GLOBALS['wpmind_test_post_categories'][ $post_id ] = $name;
	}

	/**
	 * Reset post category to default / empty.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function reset_post_to_default_category( int $post_id ): void {
		if ( function_exists( 'wp_set_post_categories' ) ) {
			$default_cat = (int) get_option( 'default_category', 1 );
			wp_set_post_categories( $post_id, array( $default_cat ) );
			return;
		}
		$GLOBALS['wpmind_test_post_categories'][ $post_id ] = '';
		$GLOBALS['wpmind_test_post_cat_ids'][ $post_id ]    = array();
	}
}
