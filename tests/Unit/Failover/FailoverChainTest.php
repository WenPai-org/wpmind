<?php
/**
 * Failover chain ordering tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;

/**
 * @covers \WPMind\Failover\FailoverManager
 */
final class FailoverChainTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
		$this->reset_fm();
	}

	private function reset_fm(): void {
		$ref = new ReflectionClass( FailoverManager::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Inject providers into FailoverManager via reflection (no WPMind bootstrap).
	 *
	 * @param list<string> $ids Provider ids.
	 */
	private function fm_with( array $ids ): FailoverManager {
		$this->reset_fm();
		$fm = FailoverManager::instance();
		$ref = new ReflectionClass( $fm );
		$providers = $ref->getProperty( 'providers' );
		$providers->setAccessible( true );
		$breakers = $ref->getProperty( 'circuitBreakers' );
		$breakers->setAccessible( true );

		$p = array();
		$b = array();
		foreach ( $ids as $id ) {
			$p[ $id ] = array(
				'enabled' => true,
				'api_key' => 'sk-fake-' . $id,
				'name'    => $id,
			);
			$b[ $id ] = new CircuitBreaker( $id );
		}
		$providers->setValue( $fm, $p );
		$breakers->setValue( $fm, $b );
		return $fm;
	}

	public function test_explicit_order_c_a_b(): void {
		update_option( 'wpmind_failover_order', array( 'c', 'a', 'b' ), false );
		$fm = $this->fm_with( array( 'a', 'b', 'c' ) );
		$this->assertSame( array( 'c', 'a', 'b' ), $fm->get_failover_chain() );
	}

	public function test_drops_needs_attention_and_open(): void {
		update_option( 'wpmind_failover_order', array( 'a', 'b', 'c' ), false );
		$fm = $this->fm_with( array( 'a', 'b', 'c' ) );
		$fm->get_circuit_breaker( 'a' )->mark_needs_attention( 'auth_failed' );
		set_transient(
			'wpmind_cb_b',
			array(
				'state'        => CircuitBreaker::STATE_OPEN,
				'transitioned' => time(),
			),
			3600
		);
		$this->assertSame( array( 'c' ), $fm->get_failover_chain() );
	}

	public function test_rate_limited_moved_to_end(): void {
		update_option( 'wpmind_failover_order', array( 'a', 'b', 'c' ), false );
		$fm = $this->fm_with( array( 'a', 'b', 'c' ) );
		$fm->get_circuit_breaker( 'a' )->set_rate_limit_cooldown( 30 );
		$this->assertSame( array( 'b', 'c', 'a' ), $fm->get_failover_chain() );
	}

	public function test_preferred_moved_to_front(): void {
		update_option( 'wpmind_failover_order', array( 'a', 'b', 'c' ), false );
		$fm = $this->fm_with( array( 'a', 'b', 'c' ) );
		$this->assertSame( array( 'c', 'a', 'b' ), $fm->get_failover_chain( 'c' ) );
	}

	public function test_does_not_reorder_by_health_by_default(): void {
		update_option( 'wpmind_failover_order', array( 'low', 'high' ), false );
		$fm = $this->fm_with( array( 'low', 'high' ) );
		// Poison health of "high" — chain must still follow explicit order.
		\WPMind\Failover\ProviderHealthTracker::record( 'high', false, 10 );
		\WPMind\Failover\ProviderHealthTracker::record( 'high', false, 10 );
		\WPMind\Failover\ProviderHealthTracker::record( 'low', true, 10 );
		$this->assertSame( array( 'low', 'high' ), $fm->get_failover_chain() );
	}
}
