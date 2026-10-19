<?php
/**
 * Secrets 指纹与脱敏测试。
 *
 * 测试里的 key 都是假的占位串，不对应任何真实账号。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Secrets;

/**
 * @covers \WPMind\Provider\Secrets
 */
final class SecretsTest extends TestCase {

	public function test_fingerprint_is_stable_short_hex_and_not_the_key(): void {
		$key = 'sk-fake-0000000000000000';
		$fp  = Secrets::fingerprint( $key );

		self::assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $fp );
		self::assertSame( $fp, Secrets::fingerprint( $key ) );
		self::assertNotSame( $fp, Secrets::fingerprint( $key . 'x' ) );
		self::assertStringNotContainsString( 'fake', $fp );
	}

	public function test_fingerprint_of_empty_key_is_empty(): void {
		self::assertSame( '', Secrets::fingerprint( '' ) );
	}

	public function test_redact_sk_style_key(): void {
		$out = Secrets::redact( 'auth failed for sk-proj-FAKEfake1234567890 at upstream' );
		self::assertStringNotContainsString( 'FAKEfake1234567890', $out );
		self::assertStringContainsString( Secrets::MASK, $out );
		self::assertStringContainsString( 'auth failed for', $out );
	}

	public function test_redact_baidu_bce_v3_key(): void {
		$out = Secrets::redact( 'key=bce-v3/ALTAK-FAKEfake01/abcdef0123456789 rejected' );
		self::assertStringNotContainsString( 'ALTAK-FAKEfake01', $out );
		self::assertStringNotContainsString( 'abcdef0123456789', $out );
		self::assertStringContainsString( 'rejected', $out );
	}

	public function test_redact_key_in_query_string(): void {
		$out = Secrets::redact( 'GET https://generativelanguage.googleapis.com/v1beta/models?key=AIzaFAKEfakeFAKE&alt=json failed' );
		self::assertStringNotContainsString( 'AIzaFAKEfakeFAKE', $out );
		self::assertStringContainsString( '?key=' . Secrets::MASK, $out );
		self::assertStringContainsString( '&alt=json', $out );
	}

	public function test_redact_bearer_token(): void {
		$out = Secrets::redact( 'Authorization: Bearer abc.DEF-ghi_123~xyz' );
		self::assertSame( 'Authorization: Bearer ' . Secrets::MASK, $out );
	}

	public function test_redact_known_keys_even_without_known_prefix(): void {
		$known = 'fake-doubao-key-0123456789';
		$out   = Secrets::redact( 'upstream echoed ' . $known . ' back', [ $known, 'abc' ] );
		self::assertSame( 'upstream echoed ' . Secrets::MASK . ' back', $out );
	}

	public function test_redact_leaves_plain_text_alone(): void {
		$text = 'Provider deepseek returned HTTP 429 (rate limited)';
		self::assertSame( $text, Secrets::redact( $text ) );
		self::assertSame( '', Secrets::redact( '' ) );
	}
}
