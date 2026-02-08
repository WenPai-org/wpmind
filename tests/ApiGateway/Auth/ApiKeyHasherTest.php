<?php
/**
 * Tests for ApiKeyHasher
 *
 * @package WPMind\Tests\ApiGateway\Auth
 */

declare(strict_types=1);

namespace WPMind\Tests\ApiGateway\Auth;

require_once __DIR__ . '/../../../modules/api-gateway/includes/Auth/ApiKeyHasher.php';

use WPMind\Modules\ApiGateway\Auth\ApiKeyHasher;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ApiKeyHasher cryptographic utilities.
 */
class ApiKeyHasherTest extends TestCase {

	/**
	 * Test make_salt_hex returns a 32-character hex string.
	 */
	public function test_make_salt_hex_returns_32_char_hex(): void {
		$salt = ApiKeyHasher::make_salt_hex();

		$this->assertSame( 32, strlen( $salt ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $salt );
	}

	/**
	 * Test make_salt_hex produces different values on each call.
	 */
	public function test_make_salt_hex_is_random(): void {
		$salt_a = ApiKeyHasher::make_salt_hex();
		$salt_b = ApiKeyHasher::make_salt_hex();

		$this->assertNotSame( $salt_a, $salt_b );
	}

	/**
	 * Test hash_secret returns a 64-character hex string (SHA-256).
	 */
	public function test_hash_secret_returns_64_char_hex(): void {
		$hash = ApiKeyHasher::hash_secret( 'my-secret', 'abcdef0123456789abcdef0123456789' );

		$this->assertSame( 64, strlen( $hash ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $hash );
	}

	/**
	 * Test hash_secret is deterministic: same input produces same output.
	 */
	public function test_hash_secret_is_deterministic(): void {
		$salt   = 'abcdef0123456789abcdef0123456789';
		$secret = 'my-secret';

		$hash_a = ApiKeyHasher::hash_secret( $secret, $salt );
		$hash_b = ApiKeyHasher::hash_secret( $secret, $salt );

		$this->assertSame( $hash_a, $hash_b );
	}

	/**
	 * Test hash_secret produces different hashes with different salts.
	 */
	public function test_hash_secret_differs_with_different_salt(): void {
		$secret = 'my-secret';
		$salt_a = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa1';
		$salt_b = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa2';

		$hash_a = ApiKeyHasher::hash_secret( $secret, $salt_a );
		$hash_b = ApiKeyHasher::hash_secret( $secret, $salt_b );

		$this->assertNotSame( $hash_a, $hash_b );
	}

	/**
	 * Test constant_time_verify returns true for a correct secret.
	 */
	public function test_constant_time_verify_returns_true_for_correct_secret(): void {
		$secret = 'test-secret-key';
		$salt   = ApiKeyHasher::make_salt_hex();
		$hash   = ApiKeyHasher::hash_secret( $secret, $salt );

		$this->assertTrue( ApiKeyHasher::constant_time_verify( $secret, $salt, $hash ) );
	}

	/**
	 * Test constant_time_verify returns false for a wrong secret.
	 */
	public function test_constant_time_verify_returns_false_for_wrong_secret(): void {
		$salt = ApiKeyHasher::make_salt_hex();
		$hash = ApiKeyHasher::hash_secret( 'correct-secret', $salt );

		$this->assertFalse( ApiKeyHasher::constant_time_verify( 'wrong-secret', $salt, $hash ) );
	}

	/**
	 * Test constant_time_verify returns false against DUMMY_HASH_HEX.
	 */
	public function test_constant_time_verify_returns_false_against_dummy_hash(): void {
		$result = ApiKeyHasher::constant_time_verify(
			'any-secret',
			ApiKeyHasher::DUMMY_SALT_HEX,
			ApiKeyHasher::DUMMY_HASH_HEX
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test dummy constants have the correct lengths.
	 */
	public function test_dummy_constants_have_correct_length(): void {
		$this->assertSame( 32, strlen( ApiKeyHasher::DUMMY_SALT_HEX ) );
		$this->assertSame( 64, strlen( ApiKeyHasher::DUMMY_HASH_HEX ) );
	}
}