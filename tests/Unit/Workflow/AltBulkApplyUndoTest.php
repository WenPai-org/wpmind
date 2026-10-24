<?php
/**
 * Alt bulk review / apply / undo unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Workflow\AltBulkService;
use WPMind\Workflow\TaskRepository;

/**
 * @covers \WPMind\Workflow\AltBulkService
 * @covers \WPMind\Workflow\TaskRepository
 */
final class AltBulkApplyUndoTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']      = [];
		$GLOBALS['wpmind_test_posts']        = [];
		$GLOBALS['wpmind_test_postmeta']     = [];
		$GLOBALS['wpmind_test_post_autoinc'] = 1;
		$GLOBALS['wpmind_test_user_id']      = 1;
		$GLOBALS['wpmind_test_filters']      = [];

		foreach ( [ 301 => 'img-a', 302 => 'img-b', 303 => 'img-c' ] as $id => $title ) {
			$GLOBALS['wpmind_test_posts'][ $id ] = (object) [
				'ID'                => $id,
				'post_type'         => 'attachment',
				'post_status'       => 'inherit',
				'post_title'        => $title,
				'post_author'       => 1,
				'post_date_gmt'     => '2026-01-01 00:00:00',
				'post_modified_gmt' => '2026-01-01 00:00:00',
				'post_excerpt'      => '',
				'post_mime_type'    => 'image/jpeg',
			];
			$GLOBALS['wpmind_test_postmeta'][ $id ] = [];
		}
		$GLOBALS['wpmind_test_post_autoinc'] = 400;
	}

	/**
	 * Build a dry-run-ready alt_bulk task with previewed items.
	 *
	 * @param list<array<string,mixed>> $items Items.
	 * @return array<string,mixed>
	 */
	private function make_previewed_task( array $items ): array {
		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'   => TaskRepository::TYPE_ALT_BULK,
				'status' => TaskRepository::STATUS_DRY_RUN,
				'items'  => $items,
			]
		);
		$this->assertIsArray( $task );
		return $task;
	}

	public function test_approve_apply_writes_attachment_alt(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 301,
					'before'      => '',
					'proposed'    => 'a red cat',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service = new AltBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 301, 'decision' => 'approve' ],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( TaskRepository::ITEM_APPROVED, $reviewed['items'][0]['item_status'] );
		$this->assertSame( 'a red cat', $reviewed['items'][0]['applied'] );
		$this->assertSame( 1, $reviewed['summary']['approved'] );

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( TaskRepository::ITEM_APPLIED, $result['task']['items'][0]['item_status'] );
		$this->assertSame( TaskRepository::STATUS_APPLIED, $result['task']['status'] );
		$this->assertSame( 'a red cat', get_post_meta( 301, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 1, $result['task']['summary']['applied'] );
	}

	public function test_reject_does_not_write_alt(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 302,
					'before'      => '',
					'proposed'    => 'should not write',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service  = new AltBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 302, 'decision' => 'reject' ],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( TaskRepository::ITEM_REJECTED, $reviewed['items'][0]['item_status'] );
		$this->assertSame( 1, $reviewed['summary']['rejected'] );

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( '', get_post_meta( 302, '_wp_attachment_image_alt', true ) );
	}

	public function test_undo_restores_before_including_empty_delete(): void {
		update_post_meta( 301, '_wp_attachment_image_alt', 'keep-me' );

		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 301,
					'before'      => 'keep-me',
					'proposed'    => 'new-alt',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
				[
					'object_id'   => 303,
					'before'      => '',
					'proposed'    => 'temp-alt',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service = new AltBulkService( new TaskRepository() );
		$service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 301, 'decision' => 'approve' ],
				[ 'object_id' => 303, 'decision' => 'approve' ],
			]
		);
		$applied = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $applied );
		$this->assertSame( 'new-alt', get_post_meta( 301, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'temp-alt', get_post_meta( 303, '_wp_attachment_image_alt', true ) );

		$undo = $service->undo_applied( (int) $task['id'] );
		$this->assertIsArray( $undo );
		$this->assertSame( 2, $undo['processed'] );
		$this->assertSame( 'keep-me', get_post_meta( 301, '_wp_attachment_image_alt', true ) );
		$this->assertSame( '', get_post_meta( 303, '_wp_attachment_image_alt', true ) );

		foreach ( $undo['task']['items'] as $item ) {
			$this->assertSame( TaskRepository::ITEM_UNDONE, $item['item_status'] );
		}
		$this->assertSame( 2, $undo['task']['summary']['undone'] );
	}

	public function test_cannot_review_pending_or_apply_without_approved(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 301,
					'before'      => '',
					'proposed'    => '',
					'item_status' => TaskRepository::ITEM_PENDING,
				],
			]
		);

		$service = new AltBulkService( new TaskRepository() );
		$err     = $service->review_items(
			(int) $task['id'],
			[
				[ 'object_id' => 301, 'decision' => 'approve' ],
			]
		);
		$this->assertInstanceOf( WP_Error::class, $err );

		$result = $service->apply_approved( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['processed'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( '', get_post_meta( 301, '_wp_attachment_image_alt', true ) );
	}

	public function test_non_alt_bulk_rejects_apply_and_undo(): void {
		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_META_BULK,
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

		$service = new AltBulkService( $repo );
		$apply   = $service->apply_approved( (int) $task['id'] );
		$this->assertInstanceOf( WP_Error::class, $apply );
		$this->assertSame( 'wpmind_task_wrong_type', $apply->get_error_code() );

		$undo = $service->undo_applied( (int) $task['id'] );
		$this->assertInstanceOf( WP_Error::class, $undo );
		$this->assertSame( 'wpmind_task_wrong_type', $undo->get_error_code() );
	}

	public function test_review_allows_light_edit_of_applied_text(): void {
		$task = $this->make_previewed_task(
			[
				[
					'object_id'   => 301,
					'before'      => '',
					'proposed'    => 'proposed text',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);

		$service  = new AltBulkService( new TaskRepository() );
		$reviewed = $service->review_items(
			(int) $task['id'],
			[
				[
					'object_id' => 301,
					'decision'  => 'approve',
					'applied'   => 'edited text',
				],
			]
		);
		$this->assertIsArray( $reviewed );
		$this->assertSame( 'edited text', $reviewed['items'][0]['applied'] );

		$result = $service->apply_approved( (int) $task['id'] );
		$this->assertIsArray( $result );
		$this->assertSame( 'edited text', get_post_meta( 301, '_wp_attachment_image_alt', true ) );
	}
}
