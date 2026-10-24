<?php
/**
 * ErrorClassifier unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Health\ErrorClass;
use WPMind\Provider\Health\ErrorClassifier;

/**
 * @covers \WPMind\Provider\Health\ErrorClassifier
 * @covers \WPMind\Provider\Health\ErrorClass
 */
final class ErrorClassifierTest extends TestCase {

	public function test_classifies_401_as_auth_failed(): void {
		$r = ErrorClassifier::classify( null, 401, '{"error":{"message":"invalid_api_key"}}' );
		$this->assertSame( ErrorClass::AuthFailed, $r['class'] );
	}

	public function test_classifies_403_with_permission_as_auth(): void {
		$r = ErrorClassifier::classify( null, 403, 'permission denied for key' );
		$this->assertSame( ErrorClass::AuthFailed, $r['class'] );
	}

	public function test_classifies_402_and_quota_body(): void {
		$r = ErrorClassifier::classify( null, 402, 'insufficient_quota' );
		$this->assertSame( ErrorClass::QuotaExhausted, $r['class'] );
		$r2 = ErrorClassifier::classify( null, 400, '余额不足 please top up' );
		$this->assertSame( ErrorClass::QuotaExhausted, $r2['class'] );
	}

	public function test_classifies_429_with_retry_after(): void {
		$r = ErrorClassifier::classify( null, 429, 'slow down', array( 'retry-after' => '30' ) );
		$this->assertSame( ErrorClass::RateLimited, $r['class'] );
		$this->assertSame( 30, $r['retry_after'] );
	}

	public function test_classifies_429_without_retry_after(): void {
		$r = ErrorClassifier::classify( null, 429, 'rate_limit_exceeded' );
		$this->assertSame( ErrorClass::RateLimited, $r['class'] );
		$this->assertSame( 0, $r['retry_after'] );
	}

	public function test_classifies_server_errors(): void {
		foreach ( array( 500, 502, 503, 504, 529 ) as $code ) {
			$r = ErrorClassifier::classify( null, $code, 'oops' );
			$this->assertSame( ErrorClass::ServerError, $r['class'], (string) $code );
		}
	}

	public function test_classifies_408_timeout_status(): void {
		$r = ErrorClassifier::classify( null, 408, '' );
		$this->assertSame( ErrorClass::Timeout, $r['class'] );
	}

	public function test_classifies_404_model(): void {
		$r = ErrorClassifier::classify( null, 404, '{"error":{"code":"model_not_found"}}' );
		$this->assertSame( ErrorClass::ModelUnavailable, $r['class'] );
	}

	public function test_classifies_400_bad_request(): void {
		$r = ErrorClassifier::classify( null, 400, 'context_length_exceeded' );
		$this->assertSame( ErrorClass::BadRequest, $r['class'] );
	}

	public function test_classifies_wp_error_timeout_and_dns(): void {
		$timeout = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		$r       = ErrorClassifier::classify( $timeout );
		$this->assertSame( ErrorClass::Timeout, $r['class'] );

		$dns = new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: api.example' );
		$r2  = ErrorClassifier::classify( $dns );
		$this->assertSame( ErrorClass::Network, $r2['class'] );
	}

	public function test_safe_body_redacts_and_truncates(): void {
		$body = 'fail sk-proj-FAKEfake1234567890 ' . str_repeat( 'x', 400 );
		$out  = ErrorClassifier::safe_body( $body );
		$this->assertStringNotContainsString( 'FAKEfake', $out );
		$this->assertLessThanOrEqual( ErrorClassifier::BODY_TRUNCATE, strlen( $out ) );
	}

	public function test_uncertain_4xx_is_server_error_not_auth(): void {
		$r = ErrorClassifier::classify( null, 418, 'teapot' );
		$this->assertSame( ErrorClass::ServerError, $r['class'] );
	}
}
