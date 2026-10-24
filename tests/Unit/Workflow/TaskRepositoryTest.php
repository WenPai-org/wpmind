<?php
/**
 * Workflow TaskRepository unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Workflow\TaskRepository;

/**
 * @covers \WPMind\Workflow\TaskRepository
 */
final class TaskRepositoryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']      = [];
		$GLOBALS['wpmind_test_posts']        = [];
		$GLOBALS['wpmind_test_postmeta']     = [];
		$GLOBALS['wpmind_test_post_autoinc'] = 1;
		$GLOBALS['wpmind_test_user_id']      = 7;
	}

	public function test_create_get_list_update_and_items(): void {
		$repo    = new TaskRepository();
		$created = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_ALT_BULK,
				'items' => [
					[
						'object_id'   => 101,
						'before'      => '',
						'proposed'    => '',
						'item_status' => TaskRepository::ITEM_PENDING,
					],
					[
						'object_id'   => 102,
						'before'      => 'old',
						'proposed'    => '',
						'item_status' => TaskRepository::ITEM_PENDING,
					],
				],
				'summary' => [
					'candidates' => 2,
					'limit'      => 50,
				],
			]
		);

		$this->assertIsArray( $created );
		$this->assertSame( TaskRepository::TYPE_ALT_BULK, $created['type'] );
		$this->assertSame( TaskRepository::STATUS_DRAFT, $created['status'] );
		$this->assertSame( 7, $created['created_by'] );
		$this->assertCount( 2, $created['items'] );
		$this->assertSame( 2, $created['summary']['candidates'] );

		$got = $repo->get_task( (int) $created['id'] );
		$this->assertNotNull( $got );
		$this->assertSame( $created['id'], $got['id'] );

		$list = $repo->list_tasks( [ 'type' => TaskRepository::TYPE_ALT_BULK ] );
		$this->assertCount( 1, $list );
		$this->assertArrayNotHasKey( 'items', $list[0] );

		$updated = $repo->add_or_update_items(
			(int) $created['id'],
			[
				[
					'object_id'   => 101,
					'proposed'    => 'a cat',
					'item_status' => TaskRepository::ITEM_PREVIEWED,
				],
			]
		);
		$this->assertIsArray( $updated );
		$this->assertSame( 1, $updated['summary']['previewed'] );
		$this->assertSame( 'a cat', $updated['items'][0]['proposed'] );

		$status = $repo->update_task( (int) $created['id'], [ 'status' => TaskRepository::STATUS_DRY_RUN ] );
		$this->assertIsArray( $status );
		$this->assertSame( TaskRepository::STATUS_DRY_RUN, $status['status'] );
	}
}
