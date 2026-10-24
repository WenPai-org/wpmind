<?php
/**
 * Alt bulk dry-run must not write attachment alt.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Workflow\AltBulkService;
use WPMind\Workflow\TaskRepository;

/**
 * @covers \WPMind\Workflow\AltBulkService
 * @covers \WPMind\Modules\MediaIntelligence\AltTextGenerator
 */
final class AltBulkDryRunTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']      = [];
		$GLOBALS['wpmind_test_posts']        = [];
		$GLOBALS['wpmind_test_postmeta']     = [];
		$GLOBALS['wpmind_test_post_autoinc'] = 1;
		$GLOBALS['wpmind_test_user_id']      = 1;
		$GLOBALS['wpmind_test_filters']      = [];

		foreach ( [ 201 => 'img-a', 202 => 'img-b' ] as $id => $title ) {
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
		$GLOBALS['wpmind_test_post_autoinc'] = 300;

		require_once WPMIND_PLUGIN_DIR . 'modules/media-intelligence/includes/AltTextGenerator.php';
	}

	public function test_dry_run_writes_proposed_but_not_attachment_alt(): void {
		$GLOBALS['wpmind_test_filters']['wpmind_alt_proposed_override'] = static function ( $override, int $attachment_id ) {
			return 'proposed-for-' . $attachment_id;
		};

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
					[
						'object_id'   => 202,
						'before'      => '',
						'proposed'    => '',
						'item_status' => TaskRepository::ITEM_PENDING,
					],
				],
			]
		);
		$this->assertIsArray( $task );

		$service = new AltBulkService( $repo );
		$result  = $service->dry_run_batch( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 2, $result['processed'] );
		$this->assertSame( TaskRepository::STATUS_DRY_RUN, $result['task']['status'] );

		foreach ( $result['task']['items'] as $item ) {
			$this->assertSame( TaskRepository::ITEM_PREVIEWED, $item['item_status'] );
			$this->assertSame( 'proposed-for-' . $item['object_id'], $item['proposed'] );
			$alt = get_post_meta( (int) $item['object_id'], '_wp_attachment_image_alt', true );
			$this->assertSame( '', $alt, 'attachment alt must stay empty after dry-run' );
			$post = get_post( (int) $item['object_id'] );
			$this->assertNotNull( $post );
			$this->assertSame( 'img-' . ( 201 === (int) $item['object_id'] ? 'a' : 'b' ), $post->post_title );
		}
	}

	public function test_dry_run_records_error_without_writing_alt(): void {
		$GLOBALS['wpmind_test_filters']['wpmind_alt_proposed_override'] = static function () {
			return new WP_Error( 'boom', 'vision failed' );
		};

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

		$result = ( new AltBulkService( $repo ) )->dry_run_batch( (int) $task['id'], 5 );
		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['errors'] );
		$this->assertSame( TaskRepository::ITEM_ERROR, $result['task']['items'][0]['item_status'] );
		$this->assertSame( '', get_post_meta( 201, '_wp_attachment_image_alt', true ) );
	}
}
