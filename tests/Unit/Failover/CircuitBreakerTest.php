<?php
/**
 * CircuitBreaker unit tests (PR4).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Failover\CircuitBreaker;
use WPMind\Provider\Health\ErrorClass;

/**
 * @covers \WPMind\Failover\CircuitBreaker
 */
final class CircuitBreakerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
	}

	public function test_needs_attention_does_not_auto_recover(): void {
		$cb = new CircuitBreaker( 'openai' );
		$cb->mark_needs_attention( 'auth_failed' );
		$this->assertSame( CircuitBreaker::STATE_NEEDS_ATTENTION, $cb->get_state() );
		$this->assertFalse( $cb->is_available() );
		$this->assertFalse( $cb->is_available_read_only() );
	}

	public function test_needs_attention_clears_on_reset_after_key_change(): void {
		$cb = new CircuitBreaker( 'openai' );
		$cb->mark_needs_attention( 'auth_failed' );
		$cb->reset();
		$this->assertSame( CircuitBreaker::STATE_CLOSED, $cb->get_state() );
		$this->assertTrue( $cb->is_available() );
	}

	public function test_quota_exhausted_blocks_until_cooldown_then_probe_lock(): void {
		$cb = new CircuitBreaker( 'deepseek' );
		$cb->mark_quota_exhausted( 3600, 'quota_exhausted' );
		$this->assertFalse( $cb->is_available() );

		// Simulate cooldown elapsed.
		$data = get_transient( 'wpmind_cb_deepseek' );
		$data['cooldown_until'] = time() - 1;
		set_transient( 'wpmind_cb_deepseek', $data, 3600 );

		$this->assertTrue( $cb->acquire_probe_lock() );
		$this->assertFalse( $cb->acquire_probe_lock(), 'second concurrent probe must fail closed' );
		$cb->release_probe_lock();
	}

	public function test_half_open_probe_lock_fail_closed(): void {
		$cb = new CircuitBreaker( 'qwen' );
		// Force open then half-open transition window elapsed.
		set_transient(
			'wpmind_cb_qwen',
			array(
				'state'        => CircuitBreaker::STATE_OPEN,
				'transitioned' => time() - 1300,
			),
			3600
		);
		$this->assertTrue( $cb->is_available( true ) );
		// Lock already held by is_available; another acquire should fail.
		$this->assertFalse( ( new CircuitBreaker( 'qwen' ) )->acquire_probe_lock() || false );
		// Actually first is_available acquired lock; second CircuitBreaker same id shares lock key.
		$cb2 = new CircuitBreaker( 'qwen' );
		$this->assertFalse( $cb2->acquire_probe_lock() );
		$cb->release_probe_lock();
	}

	public function test_rate_limited_does_not_trip_and_sets_cooldown(): void {
		$cb = new CircuitBreaker( 'zhipu' );
		$cb->set_rate_limit_cooldown( 30 );
		$this->assertTrue( $cb->is_rate_limited_cooling() );
		$cb->record_failure( ErrorClass::RateLimited );
		$this->assertSame( CircuitBreaker::STATE_CLOSED, $cb->get_state() );
	}

	public function test_auth_failed_marks_needs_attention(): void {
		$cb = new CircuitBreaker( 'moonshot' );
		$cb->record_failure( ErrorClass::AuthFailed );
		$this->assertSame( CircuitBreaker::STATE_NEEDS_ATTENTION, $cb->get_state() );
	}

	public function test_model_unavailable_does_not_trip(): void {
		$cb = new CircuitBreaker( 'openai' );
		$cb->record_failure( ErrorClass::ModelUnavailable );
		$this->assertSame( CircuitBreaker::STATE_CLOSED, $cb->get_state() );
	}
}
