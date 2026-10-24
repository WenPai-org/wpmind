<?php
/**
 * Meta bulk review / apply / undo unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Workflow\MetaBulkService;
use WPMind\Workflow\TaskRepository;

/**
 * @covers \WPMind\Workflow\MetaBulkService
 * @covers \WPMind\Workflow\TaskRepository
 */
final class MetaBulkApplyUndoTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options'] = [
			'wpmind_auto_meta_post_types' => [ 'post' ],
			'wpmind_auto_meta_excerpt'    => '1',
			'wpmind_auto_meta_seo_desc'   => '1',
			'wpmind_auto_meta_faq'        => '1',
			'wpmind_auto_meta_tags'       => '1',
			'wpmind_auto_meta_category'   => '0',
			'default_category'            => 1,
		];
		$GLOBALS['wpmind_test_posts']           = [];
		$GLOBALS['wpmind_test_postmeta']        = [];
		$GLOBALS['wpmind_test_post_autoinc']    = 1;
		$GLOBALS['wpmind_test_user_id']         = 1;
		$GLOBALS['wpmind_test_filters']         = [];
		$GLOBALS['wpmind_test_post_tags']       = [];
		$GLOBALS['wpmind_test_post_categories'] = [];
		$GLOBALS['wpmind_test_post_cat_ids']    = [];

		foreach ( [ 501 => 'Post A', 502 => 'Post B', 503 => 'Post C' ] as $id => $title ) {
			$GLOBALS['wpmind_test_posts'][ $id ] = (object) [
				'ID'                => $id,
				'post_type'         => 'post',
				'post_status'       => 'publish',
				'post_title'        => $title,
				'post_content'      => 'Body for ' . $title,
				'post_author'       => 1,
				'post_date_gmt'     => '2026-01-01 00:00:00',
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_excerpt'      => '',
				'post_mime_type'    => '',
			];
			$GLOBALS['wpmind_test_postmeta'][ $id ] = [];
		}
		$GLOBALS['wpmind_test_post_autoinc'] = 900;
	}

	/**
	 * Empty Meta snapshot shape.
	 *
	 * @param array<string,mixed> $over Overrides.
	 * @return array<string,mixed>
	 */
	private function snap( array $over = [] ): array {
		return array_merge(
			[
				'excerpt'         => '',
				'seo_description' => '',
				'tags'            => [],
				'category'        => '',
				'faq'             => [],
				'source'          => '',
			],
			$over
		);
	}

	/**
	 * Build a dry-run-ready meta_bulk task with previewed items.
	 *
	 * @param list<array<string,mixed>> $items Items.
	 * @return array<string,mixed>
	 */
	private function make_previewed_task( array $items ): array {
		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'   => TaskRepository::TYPE_META_BULK,
				'status' => TaskRepository::STATUS_DRY_RUN,
				'items'  => $items,
			]
		);
		$this->assertIsArray( $task );
		return $task;
	}

	public function test_approve_apply_writes_excerpt_seo_faq(): void {
		$proposed = $this->snap(
			[
				'excerpt'         => 'ex-501',
				'seo_description' => 'seo-501',
				'tags'            => [ 't1', 't2' ],
				'faq'             => [ [ 'question' => 'Q?', 'answer' => 'A.' ] ],
			]
		);

		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 501,
					'before'      => $this->snap(),
					'proposed'    => $proposed,
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service  = new MetaBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 501, 'decision' => 'approve' ],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( TaskRepository::ITEM_APPROVED, $reviewed['items'][0]['item_status'] );
		$this->assertSame( 'ex-501', $reviewed['items'][0]['applied']['excerpt'] );
		$this->assertSame( 1, $reviewed['summary']['approved'] );

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( TaskRepository::ITEM_APPLIED, $result['task']['items'][0]['item_status'] );
		$this->assertSame( TaskRepository::STATUS_APPLIED, $result['task']['status'] );

		$post = get_post( 501 );
		$this->assertNotNull( $post );
		$this->assertSame( 'ex-501', (string) $post->post_excerpt );
		$this->assertSame( 'seo-501', (string) get_post_meta( 501, '_wpmind_seo_description', true ) );
		$this->assertNotSame( '', (string) get_post_meta( 501, '_wpmind_faq_schema', true ) );
		$this->assertSame( 'auto', (string) get_post_meta( 501, '_wpmind_auto_meta_source', true ) );
		$this->assertSame( '', (string) get_post_meta( 501, '_wpmind_auto_meta_lock', true ) );
		$this->assertSame( [ 't1', 't2' ], $GLOBALS['wpmind_test_post_tags'][ 501 ] ?? [] );
		$this->assertSame( 1, $result['task']['summary']['applied'] );
	}

	public function test_reject_does_not_write_meta(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 502,
					'before'      => $this->snap(),
					'proposed'    => $this->snap(
						[
							'excerpt'         => 'should-not',
							'seo_description' => 'nope',
							'tags'            => [ 'x' ],
							'faq'             => [ [ 'question' => 'q', 'answer' => 'a' ] ],
						]
					),
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service  = new MetaBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 502, 'decision' => 'reject' ],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( TaskRepository::ITEM_REJECTED, $reviewed['items'][0]['item_status'] );
		$this->assertSame( 1, $reviewed['summary']['rejected'] );

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 0, $result['processed'] );

		$post = get_post( 502 );
		$this->assertNotNull( $post );
		$this->assertSame( '', (string) $post->post_excerpt );
		$this->assertSame( '', (string) get_post_meta( 502, '_wpmind_seo_description', true ) );
		$this->assertSame( '', (string) get_post_meta( 502, '_wpmind_faq_schema', true ) );
		$this->assertSame( '', (string) get_post_meta( 502, '_wpmind_auto_meta_source', true ) );
	}

	public function test_undo_restores_before_including_empty_delete(): void {
		$GLOBALS['wpmind_test_posts'][501]->post_excerpt = 'keep-ex';
		update_post_meta( 501, '_wpmind_seo_description', 'keep-seo' );
		update_post_meta( 501, '_wpmind_faq_schema', wp_json_encode( [ [ 'question' => 'old', 'answer' => 'a' ] ] ) );
		update_post_meta( 501, '_wpmind_auto_meta_source', 'manual' );
		$GLOBALS['wpmind_test_post_tags'][501] = [ 'old-tag' ];

		$before_501 = $this->snap(
			[
				'excerpt'         => 'keep-ex',
				'seo_description' => 'keep-seo',
				'tags'            => [ 'old-tag' ],
				'faq'             => [ [ 'question' => 'old', 'answer' => 'a' ] ],
				'source'          => 'manual',
			]
		);
		$proposed_501 = $this->snap(
			[
				'excerpt'         => 'new-ex',
				'seo_description' => 'new-seo',
				'tags'            => [ 'new-tag' ],
				'faq'             => [ [ 'question' => 'new', 'answer' => 'b' ] ],
			]
		);

		$before_503 = $this->snap();
		$proposed_503 = $this->snap(
			[
				'excerpt'         => 'temp-ex',
				'seo_description' => 'temp-seo',
				'tags'            => [ 'tmp' ],
				'faq'             => [ [ 'question' => 't', 'answer' => 't' ] ],
			]
		);

		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 501,
					'before'      => $before_501,
					'proposed'    => $proposed_501,
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
				[
					'object_id'   => 503,
					'before'      => $before_503,
					'proposed'    => $proposed_503,
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service = new MetaBulkService( new TaskRepository() );
		$service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 501, 'decision' => 'approve' ],
				[ 'object_id' => 503, 'decision' => 'approve' ],
			]
		);
		$applied = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $applied );
		$this->assertSame( 'new-ex', (string) get_post( 501 )->post_excerpt );
		$this->assertSame( 'temp-ex', (string) get_post( 503 )->post_excerpt );

		$undo = $service->undo_applied( (int) $task['id'] );
		$this->assertIsArray( $undo );
		$this->assertSame( 2, $undo['processed'] );

		$this->assertSame( 'keep-ex', (string) get_post( 501 )->post_excerpt );
		$this->assertSame( 'keep-seo', (string) get_post_meta( 501, '_wpmind_seo_description', true ) );
		$this->assertSame( 'manual', (string) get_post_meta( 501, '_wpmind_auto_meta_source', true ) );
		$this->assertSame( [ 'old-tag' ], $GLOBALS['wpmind_test_post_tags'][501] ?? [] );

		$this->assertSame( '', (string) get_post( 503 )->post_excerpt );
		$this->assertSame( '', (string) get_post_meta( 503, '_wpmind_seo_description', true ) );
		$this->assertSame( '', (string) get_post_meta( 503, '_wpmind_faq_schema', true ) );
		$this->assertSame( '', (string) get_post_meta( 503, '_wpmind_auto_meta_source', true ) );

		foreach ( $undo['task']['items'] as $item ) {
			$this->assertSame( TaskRepository::ITEM_UNDONE, $item['item_status'] );
		}
		$this->assertSame( 2, $undo['task']['summary']['undone'] );
	}

	public function test_apply_skips_when_before_mismatches_current(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 501,
					'before'      => $this->snap(),
					'proposed'    => $this->snap( [ 'excerpt' => 'new', 'seo_description' => 'seo' ] ),
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service = new MetaBulkService( new TaskRepository() );
		$service->review_items(
			(int) $task['id'],
			[ [ 'object_id' => 501, 'decision' => 'approve' ] ]
		);

		// Concurrent user edit after approve.
		$GLOBALS['wpmind_test_posts'][501]->post_excerpt = 'user-changed';

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( 1, $result['errors'] );
		$this->assertSame( TaskRepository::ITEM_ERROR, $result['task']['items'][0]['item_status'] );
		$this->assertSame( 'user-changed', (string) get_post( 501 )->post_excerpt );
		$this->assertSame( '', (string) get_post_meta( 501, '_wpmind_seo_description', true ) );
	}

	public function test_undo_skips_when_current_differs_from_applied(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 501,
					'before'      => $this->snap(),
					'proposed'    => $this->snap( [ 'excerpt' => 'applied-ex', 'seo_description' => 'seo' ] ),
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service = new MetaBulkService( new TaskRepository() );
		$service->review_items(
			(int) $task['id'],
			[ [ 'object_id' => 501, 'decision' => 'approve' ] ]
		);
		$applied = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $applied );
		$this->assertSame( 'applied-ex', (string) get_post( 501 )->post_excerpt );

		// User edits after apply.
		$GLOBALS['wpmind_test_posts'][501]->post_excerpt = 'post-apply-edit';

		$undo = $service->undo_applied( (int) $task['id'] );
		$this->assertIsArray( $undo );
		$this->assertSame( 0, $undo['processed'] );
		$this->assertSame( 1, $undo['errors'] );
		$this->assertSame( TaskRepository::ITEM_APPLIED, $undo['task']['items'][0]['item_status'] );
		$this->assertSame( 'post-apply-edit', (string) get_post( 501 )->post_excerpt );
	}

	public function test_non_meta_bulk_rejects_apply_and_undo(): void {
		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_ALT_BULK,
				'items' => [
					[
						'object_id'   => 301,
						'before'      => '',
						'proposed'    => 'x',
						'item_status' => TaskRepository::ITEM_APPROVED,
						'applied'     => 'x',
					],
				],
			]
		);
		$this->assertIsArray( $task );

		$service = new MetaBulkService( $repo );
		$apply   = $service->apply_approved( (int) $task['id'] );
		$this->assertInstanceOf( WP_Error::class, $apply );
		$this->assertSame( 'wpmind_task_wrong_type', $apply->get_error_code() );

		$undo = $service->undo_applied( (int) $task['id'] );
		$this->assertInstanceOf( WP_Error::class, $undo );
		$this->assertSame( 'wpmind_task_wrong_type', $undo->get_error_code() );
	}

	public function test_review_allows_light_edit_of_applied_array(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 501,
					'before'      => $this->snap(),
					'proposed'    => $this->snap( [ 'excerpt' => 'proposed', 'seo_description' => 'seo' ] ),
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service  = new MetaBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[
					'object_id' => 501,
					'decision'  => 'approve',
					'applied'   => $this->snap( [ 'excerpt' => 'edited', 'seo_description' => 'seo-edit' ] ),
				],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( 'edited', $reviewed['items'][0]['applied']['excerpt'] );

		$result = $service->apply_approved( (int) $task['id'] );
		$this->assertIsArray( $result );
		$this->assertSame( 'edited', (string) get_post( 501 )->post_excerpt );
		$this->assertSame( 'seo-edit', (string) get_post_meta( 501, '_wpmind_seo_description', true ) );
	}
}
