<?php
/**
 * Meta bulk dry-run must not write post meta / excerpt / tags.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Workflow\MetaBulkService;
use WPMind\Workflow\TaskRepository;

/**
 * @covers \WPMind\Workflow\MetaBulkService
 * @covers \WPMind\Modules\AutoMeta\MetaGenerator
 */
final class MetaBulkDryRunTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']      = [
			'wpmind_auto_meta_post_types' => [ 'post' ],
			'wpmind_auto_meta_excerpt'    => '1',
			'wpmind_auto_meta_seo_desc'   => '1',
			'wpmind_auto_meta_faq'        => '1',
			'wpmind_auto_meta_tags'       => '1',
			'wpmind_auto_meta_category'   => '0',
		];
		$GLOBALS['wpmind_test_posts']            = [];
		$GLOBALS['wpmind_test_postmeta']         = [];
		$GLOBALS['wpmind_test_post_autoinc']     = 1;
		$GLOBALS['wpmind_test_user_id']          = 1;
		$GLOBALS['wpmind_test_filters']          = [];
		$GLOBALS['wpmind_test_post_tags']        = [];
		$GLOBALS['wpmind_test_post_categories']  = [];
		$GLOBALS['wpmind_test_post_cat_ids']     = [];

		foreach ( [ 501 => 'Post A', 502 => 'Post B' ] as $id => $title ) {
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

		require_once WPMIND_PLUGIN_DIR . 'modules/auto-meta/includes/MetaGenerator.php';
	}

	public function test_create_task_from_scan_is_meta_bulk(): void {
		$service = new MetaBulkService();
		$task    = $service->create_task_from_scan( 10 );
		$this->assertIsArray( $task );
		$this->assertSame( TaskRepository::TYPE_META_BULK, $task['type'] );
		$this->assertSame( TaskRepository::STATUS_DRAFT, $task['status'] );
		$this->assertGreaterThanOrEqual( 2, (int) $task['summary']['candidates'] );
		$this->assertArrayHasKey( 'estimated_calls', $task['summary'] );
		$this->assertSame( (int) $task['summary']['candidates'], (int) $task['summary']['estimated_calls'] );
		$this->assertSame(
			(int) $task['summary']['candidates'] * MetaBulkService::ESTIMATED_TOKENS_PER_CALL,
			(int) $task['summary']['estimated_max_tokens']
		);
		foreach ( $task['items'] as $item ) {
			$this->assertIsArray( $item['before'] );
			$this->assertArrayHasKey( 'excerpt', $item['before'] );
			$this->assertSame( TaskRepository::ITEM_PENDING, $item['item_status'] );
		}
	}

	public function test_dry_run_writes_proposed_but_not_post_fields(): void {
		$GLOBALS['wpmind_test_filters']['wpmind_meta_proposed_override'] = static function ( $override, int $post_id ) {
			return [
				'excerpt'         => 'ex-' . $post_id,
				'seo_description' => 'seo-' . $post_id,
				'tags'            => [ 't1', 't2' ],
				'category'        => 'News',
				'faq'             => [
					[ 'question' => 'Q?', 'answer' => 'A.' ],
				],
			];
		};

		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_META_BULK,
				'items' => [
					[
						'object_id'   => 501,
						'before'      => [
							'excerpt'         => '',
							'seo_description' => '',
							'tags'            => [],
							'category'        => '',
							'faq'             => [],
							'source'          => '',
						],
						'proposed'    => [],
						'item_status' => TaskRepository::ITEM_PENDING,
					],
					[
						'object_id'   => 502,
						'before'      => [
							'excerpt'         => '',
							'seo_description' => '',
							'tags'            => [],
							'category'        => '',
							'faq'             => [],
							'source'          => '',
						],
						'proposed'    => [],
						'item_status' => TaskRepository::ITEM_PENDING,
					],
				],
			]
		);
		$this->assertIsArray( $task );

		$result = ( new MetaBulkService( $repo ) )->dry_run_batch( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 2, $result['processed'] );
		$this->assertSame( TaskRepository::STATUS_DRY_RUN, $result['task']['status'] );

		foreach ( $result['task']['items'] as $item ) {
			$this->assertSame( TaskRepository::ITEM_PREVIEWED, $item['item_status'] );
			$this->assertIsArray( $item['proposed'] );
			$this->assertSame( 'ex-' . $item['object_id'], $item['proposed']['excerpt'] );

			$oid  = (int) $item['object_id'];
			$post = get_post( $oid );
			$this->assertNotNull( $post );
			$this->assertSame( '', (string) $post->post_excerpt );
			$this->assertSame( '', (string) get_post_meta( $oid, '_wpmind_seo_description', true ) );
			$this->assertSame( '', (string) get_post_meta( $oid, '_wpmind_faq_schema', true ) );
			$this->assertSame( '', (string) get_post_meta( $oid, '_wpmind_auto_meta_source', true ) );
			$this->assertSame( '', (string) get_post_meta( $oid, '_wpmind_auto_meta_hash', true ) );
			$this->assertSame( '', (string) get_post_meta( $oid, '_wpmind_auto_meta_lock', true ) );
		}
	}

	public function test_dry_run_rejects_non_meta_bulk(): void {
		$repo = new TaskRepository();
		$task = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_ALT_BULK,
				'items' => [
					[
						'object_id'   => 201,
						'before'      => '',
						'proposed'    => '',
						'item_status' => TaskRepository::ITEM_PENDING,
					],
				],
			]
		);
		$this->assertIsArray( $task );

		$result = ( new MetaBulkService( $repo ) )->dry_run_batch( (int) $task['id'], 5 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpmind_task_wrong_type', $result->get_error_code() );
	}

	public function test_normalize_items_keeps_array_and_string_snapshots(): void {
		$repo = new TaskRepository();

		$meta = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_META_BULK,
				'items' => [
					[
						'object_id'   => 501,
						'before'      => [
							'excerpt'         => 'old',
							'seo_description' => 'seo',
							'tags'            => [ 'a' ],
							'category'        => 'c',
							'faq'             => [],
							'source'          => '',
						],
						'proposed'    => [
							'excerpt'         => 'new',
							'seo_description' => 'seo2',
							'tags'            => [ 'b' ],
							'category'        => 'c2',
							'faq'             => [ [ 'question' => 'q', 'answer' => 'a' ] ],
						],
						'item_status' => TaskRepository::ITEM_PREVIEWED,
					],
				],
			]
		);
		$this->assertIsArray( $meta );
		$this->assertIsArray( $meta['items'][0]['before'] );
		$this->assertSame( 'old', $meta['items'][0]['before']['excerpt'] );
		$this->assertIsArray( $meta['items'][0]['proposed']['faq'] );

		$alt = $repo->create_task(
			[
				'type'  => TaskRepository::TYPE_ALT_BULK,
				'items' => [
					[
						'object_id'   => 201,
						'before'      => '',
						'proposed'    => 'a cat',
						'item_status' => TaskRepository::ITEM_PREVIEWED,
					],
				],
			]
		);
		$this->assertIsArray( $alt );
		$this->assertSame( '', $alt['items'][0]['before'] );
		$this->assertSame( 'a cat', $alt['items'][0]['proposed'] );
	}
}
