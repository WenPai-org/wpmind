<?php
/**
 * Meta bulk workflow: scan → task → dry-run (preview only).
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

use WPMind\Modules\AutoMeta\MetaGenerator;

/**
 * Meta bulk create + dry-run service (no apply/undo in PR3).
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
}
