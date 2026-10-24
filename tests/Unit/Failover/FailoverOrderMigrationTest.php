<?php
/**
 * M013_FailoverOrder migration tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Migrations\M013_FailoverOrder;

/**
 * @covers \WPMind\Provider\Migrations\M013_FailoverOrder
 */
final class FailoverOrderMigrationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']  = array();
		$GLOBALS['wpmind_test_autoload'] = array();
	}

	public function test_imports_manual_priority(): void {
		update_option(
			'wpmind_routing_settings',
			array(
				'provider_priority' => array( 'deepseek', 'openai' ),
			)
		);
		$m = new M013_FailoverOrder();
		$this->assertTrue( $m->is_needed() );
		$out = $m->run();
		$this->assertSame( 'ok', $out['result'] );
		$this->assertSame( array( 'deepseek', 'openai' ), get_option( 'wpmind_failover_order' ) );
		$this->assertFalse( $GLOBALS['wpmind_test_autoload']['wpmind_failover_order'] ?? true );
	}

	public function test_noop_when_already_set(): void {
		update_option( 'wpmind_failover_order', array( 'openai' ), false );
		$m = new M013_FailoverOrder();
		$this->assertFalse( $m->is_needed() );
		$this->assertSame( 'noop', $m->run()['result'] );
	}
}
