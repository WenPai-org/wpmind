<?php
/**
 * ErrorCatalog unit tests (PR6).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Health\ErrorCatalog;
use WPMind\Provider\Health\ErrorClass;

/**
 * @covers \WPMind\Provider\Health\ErrorCatalog
 */
final class ErrorCatalogTest extends TestCase {

	public function test_rows_cover_every_error_class(): void {
		$rows = ErrorCatalog::rows();
		foreach ( ErrorClass::cases() as $case ) {
			$this->assertArrayHasKey( $case->value, $rows, 'Missing catalog mapping for ' . $case->value );
			$this->assertNotSame( '', $rows[ $case->value ]['label'] );
			$this->assertNotSame( '', $rows[ $case->value ]['message'] );
			$this->assertNotSame( '', $rows[ $case->value ]['recovery'] );
		}
	}

	public function test_entry_includes_credentials_url_for_known_provider(): void {
		$entry = ErrorCatalog::entry( ErrorClass::AuthFailed, 'deepseek' );
		$this->assertSame( 'auth_failed', $entry['class'] );
		$this->assertStringContainsString( 'http', $entry['credentials_url'] );
		$this->assertNotSame( '', $entry['message'] );
	}

	public function test_class_from_http_maps_common_codes(): void {
		$this->assertSame( ErrorClass::AuthFailed, ErrorCatalog::class_from_http( 401 ) );
		$this->assertSame( ErrorClass::QuotaExhausted, ErrorCatalog::class_from_http( 402 ) );
		$this->assertSame( ErrorClass::RateLimited, ErrorCatalog::class_from_http( 429 ) );
		$this->assertSame( ErrorClass::Timeout, ErrorCatalog::class_from_http( 408 ) );
		$this->assertSame( ErrorClass::ServerError, ErrorCatalog::class_from_http( 503 ) );
		$this->assertSame( ErrorClass::BadRequest, ErrorCatalog::class_from_http( 400 ) );
	}

	public function test_error_handler_prefers_catalog(): void {
		$msg = \WPMind\ErrorHandler::get_error_message( 401, 'openai' );
		$this->assertSame( ErrorCatalog::message( ErrorClass::AuthFailed, 'openai' ), $msg );
	}
}
