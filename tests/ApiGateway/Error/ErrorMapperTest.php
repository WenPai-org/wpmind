<?php
/**
 * Tests for ErrorMapper
 *
 * @package WPMind\Tests\ApiGateway\Error
 */

declare(strict_types=1);

namespace WPMind\Tests\ApiGateway\Error;

require_once __DIR__ . '/../../../modules/api-gateway/includes/Error/ErrorMapper.php';

use WPMind\Modules\ApiGateway\Error\ErrorMapper;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ErrorMapper error code mapping.
 */
class ErrorMapperTest extends TestCase {

	/**
	 * Test map returns 401 for authentication error codes.
	 */
	public function test_map_returns_correct_status_for_auth_errors(): void {
		$auth_codes = [
			'missing_auth_header',
			'invalid_auth_header',
			'invalid_api_key_format',
			'api_key_not_found',
			'api_key_invalid_secret',
			'not_authenticated',
		];

		foreach ( $auth_codes as $code ) {
			$result = ErrorMapper::map( $code );
			$this->assertSame( 401, $result['status'], "Expected 401 for code: {$code}" );
		}
	}

	/**
	 * Test map returns 403 for inactive, expired, and IP-denied keys.
	 */
	public function test_map_returns_403_for_inactive_and_expired(): void {
		$forbidden_codes = [
			'api_key_inactive',
			'api_key_expired',
			'api_key_ip_denied',
		];

		foreach ( $forbidden_codes as $code ) {
			$result = ErrorMapper::map( $code );
			$this->assertSame( 403, $result['status'], "Expected 403 for code: {$code}" );
		}
	}

	/**
	 * Test map returns 429 for rate limit and quota errors.
	 */
	public function test_map_returns_429_for_rate_limit(): void {
		$rate_codes = [
			'insufficient_quota',
			'rate_limit_exceeded',
		];

		foreach ( $rate_codes as $code ) {
			$result = ErrorMapper::map( $code );
			$this->assertSame( 429, $result['status'], "Expected 429 for code: {$code}" );
		}
	}

	/**
	 * Test map returns 400 for model_not_found.
	 */
	public function test_map_returns_400_for_model_not_found(): void {
		$result = ErrorMapper::map( 'model_not_found' );

		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 'model_not_found', $result['code'] );
	}

	/**
	 * Test map returns 413 for request_too_large.
	 */
	public function test_map_returns_413_for_request_too_large(): void {
		$result = ErrorMapper::map( 'request_too_large' );

		$this->assertSame( 413, $result['status'] );
		$this->assertSame( 'request_too_large', $result['code'] );
	}

	/**
	 * Test map returns 500 for unknown error codes.
	 */
	public function test_map_returns_500_for_unknown_code(): void {
		$result = ErrorMapper::map( 'totally_unknown_error_code' );

		$this->assertSame( 500, $result['status'] );
		$this->assertSame( 'server_error', $result['type'] );
		$this->assertSame( 'internal_error', $result['code'] );
	}

	/**
	 * Test format_openai_error returns the correct JSON structure.
	 */
	public function test_format_openai_error_structure(): void {
		$result = ErrorMapper::format_openai_error( 'Something went wrong', 'invalid_request_error', 'invalid_api_key' );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'Something went wrong', $result['error']['message'] );
		$this->assertSame( 'invalid_request_error', $result['error']['type'] );
		$this->assertNull( $result['error']['param'] );
		$this->assertSame( 'invalid_api_key', $result['error']['code'] );
	}
}
