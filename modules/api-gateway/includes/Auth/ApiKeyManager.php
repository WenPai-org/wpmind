<?php
/**
 * API Key Manager
 *
 * High-level API key creation and authentication logic.
 *
 * @package WPMind\Modules\ApiGateway\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Auth;

/**
 * Class ApiKeyManager
 *
 * Orchestrates key generation, storage, and bearer token authentication.
 */
class ApiKeyManager {

	/**
	 * Regex pattern for parsing a full API key.
	 *
	 * Format: sk_mind_{KEY_ID}_{SECRET}
	 *
	 * @var string
	 */
	private const KEY_PATTERN = '/^sk_mind_([A-Z0-9]{12})_([A-Za-z0-9_-]{43})$/';

	/**
	 * Create a new API key.
	 *
	 * @param array $attrs Optional attributes (name, owner_user_id, etc.).
	 * @return array Contains 'raw_key', 'key_id', 'key_prefix', and 'id'.
	 */
	public static function create_api_key( array $attrs = [] ): array {
		$key_id = self::generate_key_id();
		$secret = self::generate_secret();

		$salt_hex    = ApiKeyHasher::make_salt_hex();
		$secret_hash = ApiKeyHasher::hash_secret( $secret, $salt_hex );
		$key_prefix  = substr( $secret, 0, 8 );

		$now = current_time( 'mysql', true );

		$data = [
			'key_id'       => $key_id,
			'key_prefix'   => $key_prefix,
			'secret_hash'  => $secret_hash,
			'secret_salt'  => $salt_hex,
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		// Merge optional attributes.
		$allowed_fields = [
			'name',
			'owner_user_id',
			'allowed_providers',
			'rpm_limit',
			'tpm_limit',
			'concurrency_limit',
			'monthly_budget_usd',
			'ip_whitelist',
			'status',
			'expires_at',
		];

		foreach ( $allowed_fields as $field ) {
			if ( array_key_exists( $field, $attrs ) ) {
				$value = $attrs[ $field ];
				// Encode arrays to JSON for storage.
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$data[ $field ] = $value;
			}
		}

		$id = ApiKeyRepository::insert_key( $data );

		$raw_key = "sk_mind_{$key_id}_{$secret}";

		return [
			'id'         => $id,
			'key_id'     => $key_id,
			'key_prefix' => $key_prefix,
			'raw_key'    => $raw_key,
		];
	}

	/**
	 * Authenticate a Bearer token from the Authorization header.
	 *
	 * @param string $authorization The full Authorization header value.
	 * @param string $client_ip     The client IP address.
	 * @return ApiKeyAuthResult|\WP_Error Auth result or error.
	 */
	public static function authenticate_bearer_header( string $authorization, string $client_ip ): ApiKeyAuthResult|\WP_Error {
		// Extract Bearer token.
		if ( stripos( $authorization, 'Bearer ' ) !== 0 ) {
			return new \WP_Error(
				'invalid_auth_header',
				'Authorization header must use Bearer scheme.',
				[ 'status' => 401 ]
			);
		}

		$raw_key = substr( $authorization, 7 );
		$parsed  = self::parse_api_key( $raw_key );

		if ( $parsed === null ) {
			return new \WP_Error(
				'invalid_api_key_format',
				'API key format is invalid.',
				[ 'status' => 401 ]
			);
		}

		$key_id = $parsed['key_id'];
		$secret = $parsed['secret'];

		// Look up the key row.
		$row = ApiKeyRepository::find_by_key_id( $key_id );

		// Constant-time verify even when key not found (anti timing enumeration).
		if ( $row === null ) {
			ApiKeyHasher::constant_time_verify(
				$secret,
				ApiKeyHasher::DUMMY_SALT_HEX,
				ApiKeyHasher::DUMMY_HASH_HEX
			);

			return new \WP_Error(
				'api_key_not_found',
				'Invalid API key.',
				[ 'status' => 401 ]
			);
		}

		// Verify secret.
		$valid = ApiKeyHasher::constant_time_verify(
			$secret,
			$row['secret_salt'],
			$row['secret_hash']
		);

		if ( ! $valid ) {
			return new \WP_Error(
				'api_key_invalid_secret',
				'Invalid API key.',
				[ 'status' => 401 ]
			);
		}

		// Check status.
		if ( $row['status'] !== 'active' ) {
			return new \WP_Error(
				'api_key_inactive',
				'API key is ' . $row['status'] . '.',
				[ 'status' => 403 ]
			);
		}

		// Check expiration.
		if ( self::is_key_expired( $row ) ) {
			return new \WP_Error(
				'api_key_expired',
				'API key has expired.',
				[ 'status' => 403 ]
			);
		}

		// Check IP whitelist.
		if ( ! self::is_ip_allowed( $row, $client_ip ) ) {
			return new \WP_Error(
				'api_key_ip_denied',
				'Request IP is not allowed for this API key.',
				[ 'status' => 403 ]
			);
		}

		// Update last used timestamp (fire and forget).
		ApiKeyRepository::update_last_used( $key_id );

		return new ApiKeyAuthResult( $row );
	}

	/**
	 * Parse a raw API key string into its components.
	 *
	 * @param string $raw_key The full key string (sk_mind_...).
	 * @return array|null Array with 'key_id' and 'secret', or null on failure.
	 */
	public static function parse_api_key( string $raw_key ): ?array {
		if ( ! preg_match( self::KEY_PATTERN, $raw_key, $matches ) ) {
			return null;
		}

		return [
			'key_id' => $matches[1],
			'secret' => $matches[2],
		];
	}

	/**
	 * Check if a key row is expired.
	 *
	 * @param array $row Database row.
	 * @return bool True if expired.
	 */
	public static function is_key_expired( array $row ): bool {
		if ( empty( $row['expires_at'] ) ) {
			return false;
		}

		$expires = strtotime( $row['expires_at'] );

		return $expires !== false && $expires < time();
	}

	/**
	 * Check if a client IP is allowed by the key's whitelist.
	 *
	 * @param array  $row       Database row.
	 * @param string $client_ip Client IP address.
	 * @return bool True if allowed.
	 */
	public static function is_ip_allowed( array $row, string $client_ip ): bool {
		if ( empty( $row['ip_whitelist'] ) ) {
			return true;
		}

		$whitelist = json_decode( $row['ip_whitelist'], true );

		if ( ! is_array( $whitelist ) || empty( $whitelist ) ) {
			return true;
		}

		return in_array( $client_ip, $whitelist, true );
	}

	/**
	 * Generate a 12-character base32-like key ID.
	 *
	 * @return string 12-character uppercase alphanumeric string.
	 */
	private static function generate_key_id(): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$len      = strlen( $alphabet );
		$id       = '';

		$bytes = random_bytes( 12 );
		for ( $i = 0; $i < 12; $i++ ) {
			$id .= $alphabet[ ord( $bytes[ $i ] ) % $len ];
		}

		return $id;
	}

	/**
	 * Generate a 43-character base64url secret.
	 *
	 * @return string 43-character base64url string.
	 */
	private static function generate_secret(): string {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}
}
