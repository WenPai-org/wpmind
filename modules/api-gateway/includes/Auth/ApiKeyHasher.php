<?php
/**
 * API Key Hasher
 *
 * Handles secret hashing and constant-time verification for API keys.
 *
 * @package WPMind\Modules\ApiGateway\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Auth;

/**
 * Class ApiKeyHasher
 *
 * Cryptographic utilities for API key secrets.
 */
class ApiKeyHasher {

	/**
	 * Dummy salt hex for timing-safe lookups.
	 *
	 * Used when key_id is not found to prevent timing enumeration.
	 *
	 * @var string
	 */
	public const DUMMY_SALT_HEX = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

	/**
	 * Dummy hash hex for timing-safe lookups.
	 *
	 * @var string
	 */
	public const DUMMY_HASH_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

	/**
	 * Generate a random 32-character hex salt.
	 *
	 * @return string 32-character hex string.
	 */
	public static function make_salt_hex(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Hash a secret with the given salt.
	 *
	 * @param string $secret   The plaintext secret.
	 * @param string $salt_hex The hex-encoded salt.
	 * @return string 64-character hex hash.
	 */
	public static function hash_secret( string $secret, string $salt_hex ): string {
		return hash( 'sha256', $salt_hex . $secret );
	}

	/**
	 * Constant-time verification of a secret against an expected hash.
	 *
	 * @param string $secret   The plaintext secret to verify.
	 * @param string $salt_hex The hex-encoded salt.
	 * @param string $expected The expected hash to compare against.
	 * @return bool True if the secret matches.
	 */
	public static function constant_time_verify( string $secret, string $salt_hex, string $expected ): bool {
		$computed = self::hash_secret( $secret, $salt_hex );
		return hash_equals( $expected, $computed );
	}
}
