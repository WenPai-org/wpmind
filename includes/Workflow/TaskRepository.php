<?php
/**
 * Unified workflow task store (non-public CPT).
 *
 * @package WPMind\Workflow
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Workflow;

/**
 * Task header on CPT wpmind_task; items and summary in post meta JSON.
 *
 * @since 0.14.0
 */
final class TaskRepository {

	public const POST_TYPE = 'wpmind_task';

	public const TYPE_ALT_BULK  = 'alt_bulk';
	public const TYPE_META_BULK = 'meta_bulk';

	public const STATUS_DRAFT          = 'draft';
	public const STATUS_DRY_RUN        = 'dry_run';
	public const STATUS_PENDING_REVIEW = 'pending_review';
	public const STATUS_APPLIED        = 'applied';
	public const STATUS_CANCELLED      = 'cancelled';

	public const ITEM_PENDING   = 'pending';
	public const ITEM_PREVIEWED = 'previewed';
	public const ITEM_SKIPPED   = 'skipped';
	public const ITEM_ERROR     = 'error';
	public const ITEM_APPROVED  = 'approved';
	public const ITEM_REJECTED  = 'rejected';
	public const ITEM_APPLIED   = 'applied';
	public const ITEM_UNDONE    = 'undone';

	private const META_TYPE    = '_wpmind_task_type';
	private const META_STATUS  = '_wpmind_task_status';
	private const META_SUMMARY = '_wpmind_task_summary';
	private const META_ITEMS   = '_wpmind_task_items';

	/**
	 * Register the non-public CPT.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'WPMind Tasks', 'wpmind' ),
					'singular_name' => __( 'WPMind Task', 'wpmind' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Create a workflow task.
	 *
	 * @param array{type?:string,status?:string,summary?:array<string,mixed>,items?:list<array<string,mixed>>,created_by?:int} $args Task args.
	 * @return array<string,mixed>|\WP_Error Task array or error.
	 */
	public function create_task( array $args ) {
		$type = sanitize_key( (string) ( $args['type'] ?? self::TYPE_ALT_BULK ) );
		if ( ! in_array( $type, array( self::TYPE_ALT_BULK, self::TYPE_META_BULK ), true ) ) {
			return new \WP_Error( 'wpmind_task_bad_type', __( '无效的任务类型', 'wpmind' ) );
		}

		$status = sanitize_key( (string) ( $args['status'] ?? self::STATUS_DRAFT ) );
		if ( ! $this->is_valid_status( $status ) ) {
			$status = self::STATUS_DRAFT;
		}

		$items   = $this->normalize_items( is_array( $args['items'] ?? null ) ? $args['items'] : array() );
		$summary = $this->normalize_summary(
			is_array( $args['summary'] ?? null ) ? $args['summary'] : array(),
			$items
		);

		$created_by = isset( $args['created_by'] ) ? (int) $args['created_by'] : (int) get_current_user_id();
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );

		$post_id = wp_insert_post(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 'private',
				'post_title'    => $this->title_for( $type, $summary ),
				'post_author'   => max( 0, $created_by ),
				'post_date_gmt' => $now_gmt,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, self::META_TYPE, $type );
		update_post_meta( $post_id, self::META_STATUS, $status );
		update_post_meta( $post_id, self::META_SUMMARY, wp_json_encode( $summary ) );
		update_post_meta( $post_id, self::META_ITEMS, wp_json_encode( $items ) );

		$task = $this->get_task( $post_id );
		return $task ?? new \WP_Error( 'wpmind_task_missing', __( '任务创建后读取失败', 'wpmind' ) );
	}

	/**
	 * Get one task by ID.
	 *
	 * @param int $task_id Task post ID.
	 * @return array<string,mixed>|null
	 */
	public function get_task( int $task_id ): ?array {
		$post = get_post( $task_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$type    = (string) get_post_meta( $task_id, self::META_TYPE, true );
		$status  = (string) get_post_meta( $task_id, self::META_STATUS, true );
		$summary = $this->decode_array( get_post_meta( $task_id, self::META_SUMMARY, true ) );
		$items   = $this->normalize_items( $this->decode_list( get_post_meta( $task_id, self::META_ITEMS, true ) ) );

		return array(
			'id'         => $task_id,
			'type'       => '' !== $type ? $type : self::TYPE_ALT_BULK,
			'status'     => '' !== $status ? $status : self::STATUS_DRAFT,
			'created_at' => (string) $post->post_date_gmt,
			'updated_at' => (string) ( $post->post_modified_gmt ?: $post->post_date_gmt ),
			'created_by' => (int) $post->post_author,
			'summary'    => $this->normalize_summary( $summary, $items ),
			'items'      => $items,
		);
	}

	/**
	 * List tasks (items omitted).
	 *
	 * @param array{type?:string,status?:string,limit?:int,offset?:int} $args Query args.
	 * @return list<array<string,mixed>>
	 */
	public function list_tasks( array $args = array() ): array {
		$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		$meta_query = array();
		if ( ! empty( $args['type'] ) ) {
			$meta_query[] = array(
				'key'   => self::META_TYPE,
				'value' => sanitize_key( (string) $args['type'] ),
			);
		}
		if ( ! empty( $args['status'] ) ) {
			$meta_query[] = array(
				'key'   => self::META_STATUS,
				'value' => sanitize_key( (string) $args['status'] ),
			);
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query;
		}

		$ids  = get_posts( $query_args );
		$list = array();
		foreach ( $ids as $id ) {
			$task = $this->get_task( (int) $id );
			if ( $task ) {
				unset( $task['items'] );
				$list[] = $task;
			}
		}
		return $list;
	}

	/**
	 * Update task status and/or summary.
	 *
	 * @param int   $task_id Task ID.
	 * @param array $patch   Fields to patch (status/summary).
	 * @return array<string,mixed>|\WP_Error
	 */
	public function update_task( int $task_id, array $patch ) {
		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_not_found', __( '任务不存在', 'wpmind' ) );
		}

		if ( isset( $patch['status'] ) ) {
			$status = sanitize_key( (string) $patch['status'] );
			if ( $this->is_valid_status( $status ) ) {
				update_post_meta( $task_id, self::META_STATUS, $status );
			}
		}

		if ( array_key_exists( 'summary', $patch ) ) {
			$summary_in = $patch['summary'];
			$items      = $this->normalize_items( $this->decode_list( get_post_meta( $task_id, self::META_ITEMS, true ) ) );
			$summary    = $this->normalize_summary( $summary_in, $items );
			update_post_meta( $task_id, self::META_SUMMARY, wp_json_encode( $summary ) );
			wp_update_post(
				array(
					'ID'         => $task_id,
					'post_title' => $this->title_for( (string) $task['type'], $summary ),
				)
			);
		} else {
			wp_update_post( array( 'ID' => $task_id ) );
		}

		$updated = $this->get_task( $task_id );
		return $updated ?? new \WP_Error( 'wpmind_task_missing', __( '任务更新后读取失败', 'wpmind' ) );
	}

	/**
	 * Merge or upsert items by object_id; refreshes summary counters.
	 *
	 * @param int                       $task_id  Task ID.
	 * @param list<array<string,mixed>> $incoming Incoming items.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function add_or_update_items( int $task_id, array $incoming ) {
		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return new \WP_Error( 'wpmind_task_not_found', __( '任务不存在', 'wpmind' ) );
		}

		$by_id = array();
		foreach ( $task['items'] as $item ) {
			$by_id[ (int) $item['object_id'] ] = $item;
		}
		foreach ( $this->normalize_items( $incoming ) as $item ) {
			$oid = (int) $item['object_id'];
			if ( $oid <= 0 ) {
				continue;
			}
			$prev          = $by_id[ $oid ] ?? array();
			$by_id[ $oid ] = array_merge( $prev, $item );
		}

		$items   = array_values( $by_id );
		$summary = $this->normalize_summary( is_array( $task['summary'] ) ? $task['summary'] : array(), $items );

		update_post_meta( $task_id, self::META_ITEMS, wp_json_encode( $items ) );
		update_post_meta( $task_id, self::META_SUMMARY, wp_json_encode( $summary ) );
		wp_update_post(
			array(
				'ID'         => $task_id,
				'post_title' => $this->title_for( (string) $task['type'], $summary ),
			)
		);

		$updated = $this->get_task( $task_id );
		return $updated ?? new \WP_Error( 'wpmind_task_missing', __( '任务项写入后读取失败', 'wpmind' ) );
	}

	/**
	 * Delete all workflow task posts (uninstall helper).
	 *
	 * @return void
	 */
	public static function delete_all_tasks(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Whether status is allowed.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	private function is_valid_status( string $status ): bool {
		return in_array(
			$status,
			array(
				self::STATUS_DRAFT,
				self::STATUS_DRY_RUN,
				self::STATUS_PENDING_REVIEW,
				self::STATUS_APPLIED,
				self::STATUS_CANCELLED,
			),
			true
		);
	}

	/**
	 * Decode JSON/array meta into an associative array.
	 *
	 * @param mixed $raw Raw JSON or array.
	 * @return array<string,mixed>
	 */
	private function decode_array( $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * Decode JSON/array meta into a list.
	 *
	 * @param mixed $raw Raw JSON or array.
	 * @return list<mixed>
	 */
	private function decode_list( $raw ): array {
		$arr = $this->decode_array( $raw );
		return array_values( $arr );
	}

	/**
	 * Normalize task items.
	 *
	 * @param array<int|string,mixed> $items Items.
	 * @return list<array<string,mixed>>
	 */
	private function normalize_items( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$oid = (int) ( $item['object_id'] ?? 0 );
			if ( $oid <= 0 ) {
				continue;
			}
			$status = sanitize_key( (string) ( $item['item_status'] ?? self::ITEM_PENDING ) );
			if ( ! in_array(
				$status,
				array(
					self::ITEM_PENDING,
					self::ITEM_PREVIEWED,
					self::ITEM_SKIPPED,
					self::ITEM_ERROR,
					self::ITEM_APPROVED,
					self::ITEM_REJECTED,
					self::ITEM_APPLIED,
					self::ITEM_UNDONE,
				),
				true
			) ) {
				$status = self::ITEM_PENDING;
			}
			$row = array(
				'object_id'   => $oid,
				'before'      => $this->normalize_snapshot_value( $item['before'] ?? '' ),
				'proposed'    => $this->normalize_snapshot_value( $item['proposed'] ?? '' ),
				'item_status' => $status,
			);
			if ( array_key_exists( 'applied', $item ) ) {
				$row['applied'] = $this->normalize_snapshot_value( $item['applied'] );
			}
			if ( ! empty( $item['applied_at'] ) ) {
				$row['applied_at'] = sanitize_text_field( (string) $item['applied_at'] );
			}
			if ( ! empty( $item['error_message'] ) ) {
				$row['error_message'] = sanitize_text_field( (string) $item['error_message'] );
			}
			$out[] = $row;
		}
		return $out;
	}


	/**
	 * Keep string snapshots (Alt) or array snapshots (Meta); never cast array to "Array".
	 *
	 * @param mixed $value Raw before/proposed/applied.
	 * @return string|array<string,mixed>
	 */
	private function normalize_snapshot_value( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( null === $value ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Normalize summary counters from items.
	 *
	 * @param array<string,mixed>       $summary Summary.
	 * @param list<array<string,mixed>> $items   Items.
	 * @return array<string,mixed>
	 */
	private function normalize_summary( array $summary, array $items ): array {
		$candidates = (int) ( $summary['candidates'] ?? count( $items ) );
		$previewed  = 0;
		$errors     = 0;
		$approved   = 0;
		$rejected   = 0;
		$applied    = 0;
		$undone     = 0;
		foreach ( $items as $item ) {
			$status = (string) ( $item['item_status'] ?? '' );
			if ( self::ITEM_PREVIEWED === $status ) {
				++$previewed;
			}
			if ( self::ITEM_ERROR === $status ) {
				++$errors;
			}
			if ( self::ITEM_APPROVED === $status ) {
				++$approved;
			}
			if ( self::ITEM_REJECTED === $status ) {
				++$rejected;
			}
			if ( self::ITEM_APPLIED === $status ) {
				++$applied;
			}
			if ( self::ITEM_UNDONE === $status ) {
				++$undone;
			}
		}
		$out = array(
			'candidates' => max( $candidates, count( $items ) ),
			'previewed'  => $previewed,
			'errors'     => $errors,
			'approved'   => $approved,
			'rejected'   => $rejected,
			'applied'    => $applied,
			'undone'     => $undone,
			'capped'     => ! empty( $summary['capped'] ),
			'limit'      => isset( $summary['limit'] ) ? (int) $summary['limit'] : null,
		);
		if ( isset( $summary['estimated_calls'] ) ) {
			$out['estimated_calls'] = (int) $summary['estimated_calls'];
		}
		if ( isset( $summary['estimated_max_tokens'] ) ) {
			$out['estimated_max_tokens'] = (int) $summary['estimated_max_tokens'];
		}
		return $out;
	}

	/**
	 * Build a short post title for the task.
	 *
	 * @param string              $type    Task type.
	 * @param array<string,mixed> $summary Summary.
	 * @return string
	 */
	private function title_for( string $type, array $summary ): string {
		$n = (int) ( $summary['candidates'] ?? 0 );
		if ( self::TYPE_ALT_BULK === $type ) {
			return sprintf(
				/* translators: %d: candidate count */
				__( 'Alt 批量任务（%d）', 'wpmind' ),
				$n
			);
		}
		if ( self::TYPE_META_BULK === $type ) {
			return sprintf(
				/* translators: %d: candidate count */
				__( 'Meta 批量任务（%d）', 'wpmind' ),
				$n
			);
		}
		return sprintf(
			/* translators: 1: type, 2: count */
			__( '任务 %1$s（%2$d）', 'wpmind' ),
			$type,
			$n
		);
	}
}
