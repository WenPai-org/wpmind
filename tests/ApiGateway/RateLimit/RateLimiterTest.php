<?php
/**
 * Tests for RateLimiter
 *
 * @package WPMind\Tests\ApiGateway\RateLimit
 */

declare(strict_types=1);

namespace WPMind\Tests\ApiGateway\RateLimit;

require_once __DIR__ . '/../../../modules/api-gateway/includes/RateLimit/RateStoreResult.php';
require_once __DIR__ . '/../../../modules/api-gateway/includes/RateLimit/RateStoreInterface.php';
require_once __DIR__ . '/../../../modules/api-gateway/includes/RateLimit/RateLimiter.php';

use WPMind\Modules\ApiGateway\RateLimit\RateLimiter;
use WPMind\Modules\ApiGateway\RateLimit\RateStoreInterface;
use WPMind\Modules\ApiGateway\RateLimit\RateStoreResult;
use PHPUnit\Framework\TestCase;

/**
 * Test class for RateLimiter orchestration logic.
 */
class RateLimiterTest extends TestCase {

	/**
	 * Test unlimited limits (rpm=0, tpm=0) always return allowed.
	 */
	public function test_unlimited_returns_allowed(): void {
		$primary  = $this->createMock( RateStoreInterface::class );
		$fallback = $this->createMock( RateStoreInterface::class );

		$primary->expects( $this->never() )->method( 'consume' );
		$fallback->expects( $this->never() )->method( 'consume' );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 0, 0, 100 );

		$this->assertTrue( $result->allowed );
	}

	/**
	 * Test RPM allowed returns the result from the primary store.
	 */
	public function test_rpm_allowed_returns_result(): void {
		$expected = new RateStoreResult( true, 9, time() + 60 );

		$primary = $this->createMock( RateStoreInterface::class );
		$primary->method( 'consume' )->willReturn( $expected );

		$fallback = $this->createMock( RateStoreInterface::class );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 10, 0, 0 );

		$this->assertTrue( $result->allowed );
		$this->assertSame( 9, $result->remaining );
	}

	/**
	 * Test RPM denied returns denied result immediately.
	 */
	public function test_rpm_denied_returns_denied(): void {
		$denied = new RateStoreResult( false, 0, time() + 60 );

		$primary = $this->createMock( RateStoreInterface::class );
		$primary->method( 'consume' )->willReturn( $denied );

		$fallback = $this->createMock( RateStoreInterface::class );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 10, 1000, 50 );

		$this->assertFalse( $result->allowed );
	}

	/**
	 * Test TPM denied triggers RPM rollback.
	 */
	public function test_tpm_denied_rolls_back_rpm(): void {
		$rpm_ok  = new RateStoreResult( true, 9, time() + 60 );
		$tpm_bad = new RateStoreResult( false, 0, time() + 60 );

		$primary = $this->createMock( RateStoreInterface::class );
		$primary->method( 'consume' )
			->willReturnOnConsecutiveCalls( $rpm_ok, $tpm_bad );

		$primary->expects( $this->once() )->method( 'rollback' );

		$fallback = $this->createMock( RateStoreInterface::class );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 10, 1000, 500 );

		$this->assertFalse( $result->allowed );
	}

	/**
	 * Test fallback store is used when primary throws an exception.
	 */
	public function test_fallback_used_when_primary_throws(): void {
		$fallback_result = new RateStoreResult( true, 5, time() + 60 );

		$primary = $this->createMock( RateStoreInterface::class );
		$primary->method( 'consume' )
			->willThrowException( new \RuntimeException( 'Redis down' ) );

		$fallback = $this->createMock( RateStoreInterface::class );
		$fallback->method( 'consume' )->willReturn( $fallback_result );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 10, 0, 0 );

		$this->assertTrue( $result->allowed );
		$this->assertSame( 5, $result->remaining );
	}

	/**
	 * Test most restrictive result is returned when both RPM and TPM pass.
	 */
	public function test_most_restrictive_result_returned(): void {
		$rpm_result = new RateStoreResult( true, 8, time() + 60 );
		$tpm_result = new RateStoreResult( true, 3, time() + 60 );

		$primary = $this->createMock( RateStoreInterface::class );
		$primary->method( 'consume' )
			->willReturnOnConsecutiveCalls( $rpm_result, $tpm_result );

		$fallback = $this->createMock( RateStoreInterface::class );

		$limiter = new RateLimiter( $primary, $fallback );
		$result  = $limiter->check_and_consume( 'key-1', 'req-1', 10, 1000, 100 );

		$this->assertTrue( $result->allowed );
		$this->assertSame( 3, $result->remaining );
	}
}
