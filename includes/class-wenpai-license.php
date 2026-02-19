<?php
/**
 * WenPai License Client
 *
 * Handles license verification, activation, and deactivation
 * for WenPai commercial plugins.
 *
 * @package WPMind
 * @since   4.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WenPai License client.
 *
 * @since 4.0.0
 */
class WenPai_License {

	/**
	 * License server API URL.
	 *
	 * @var string
	 */
	private string $api_url = 'https://license.wenpai.net';

	/**
	 * Product slug.
	 *
	 * @var string
	 */
	private string $product_slug;

	/**
	 * WordPress option key for the license key.
	 *
	 * @var string
	 */
	private string $option_key;

	/**
	 * Transient key for cached verification.
	 *
	 * @var string
	 */
	private string $cache_key;

	/**
	 * Constructor.
	 *
	 * @param string $product_slug Product identifier.
	 * @param string $api_url      Optional custom API URL.
	 */
	public function __construct( string $product_slug, string $api_url = '' ) {
		$this->product_slug = $product_slug;
		$this->option_key   = 'wenpai_license_key_' . $product_slug;
		$this->cache_key    = 'wenpai_license_' . $product_slug;

		if ( $api_url !== '' ) {
			$this->api_url = rtrim( $api_url, '/' );
		}
	}

	/**
	 * Get the stored license key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return (string) get_option( $this->option_key, '' );
	}

	/**
	 * Save a license key.
	 *
	 * @param string $key License key.
	 */
	public function set_key( string $key ): void {
		update_option( $this->option_key, sanitize_text_field( $key ) );
		delete_transient( $this->cache_key );
	}

	/**
	 * Verify the license remotely. Results are cached for 24 hours.
	 *
	 * @param bool $force_refresh Skip cache.
	 * @return array{valid: bool, plan: string, expires_at: string|null, features: array, cache_ttl: int}
	 */
	public function verify( bool $force_refresh = false ): array {
		$key = $this->get_key();
		if ( $key === '' ) {
			return $this->free_response();
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = $this->api_request( '/api/v1/license/verify', [
			'license_key' => $key,
			'site_url'    => home_url(),
		] );

		if ( is_wp_error( $response ) ) {
			// Grace period: use last known good state for up to 7 days.
			$grace = get_option( $this->cache_key . '_grace' );
			if ( is_array( $grace ) ) {
				$grace_expires = (int) ( $grace['_grace_until'] ?? 0 );
				if ( $grace_expires > time() ) {
					return $grace;
				}
			}
			return $this->free_response();
		}

		$data = $this->parse_response( $response );

		// Cache the result.
		$ttl = (int) ( $data['cache_ttl'] ?? 86400 );
		set_transient( $this->cache_key, $data, $ttl );

		// Store grace period copy (7 days).
		$data['_grace_until'] = time() + ( 7 * DAY_IN_SECONDS );
		update_option( $this->cache_key . '_grace', $data );

		unset( $data['_grace_until'] );
		return $data;
	}

	/**
	 * Activate the current site.
	 *
	 * @return bool True on success.
	 */
	public function activate(): bool {
		$key = $this->get_key();
		if ( $key === '' ) {
			return false;
		}

		$response = $this->api_request( '/api/v1/license/activate', [
			'license_key' => $key,
			'site_url'    => home_url(),
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = $this->parse_response( $response );

		// Refresh cache with activation result.
		if ( ! empty( $data['valid'] ) ) {
			$ttl = (int) ( $data['cache_ttl'] ?? 86400 );
			set_transient( $this->cache_key, $data, $ttl );
		}

		return ! empty( $data['valid'] );
	}

	/**
	 * Deactivate the current site.
	 *
	 * @return bool True on success.
	 */
	public function deactivate(): bool {
		$key = $this->get_key();
		if ( $key === '' ) {
			return false;
		}

		$response = $this->api_request( '/api/v1/license/deactivate', [
			'license_key' => $key,
			'site_url'    => home_url(),
		] );

		delete_transient( $this->cache_key );
		delete_option( $this->cache_key . '_grace' );

		return ! is_wp_error( $response );
	}

	/**
	 * Get the current plan name.
	 *
	 * @return string free|pro|enterprise
	 */
	public function plan(): string {
		$data = $this->verify();
		if ( empty( $data['valid'] ) ) {
			return 'free';
		}
		return $data['plan'] ?? 'free';
	}

	/**
	 * Check if a specific feature is available.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function can( string $feature ): bool {
		$data = $this->verify();
		if ( empty( $data['valid'] ) || empty( $data['features'] ) ) {
			return false;
		}
		return isset( $data['features'][ $feature ] ) && $data['features'][ $feature ] !== 0;
	}

	/**
	 * Get the quota for a feature. Returns -1 for unlimited.
	 *
	 * @param string $feature Feature key.
	 * @return int
	 */
	public function quota( string $feature ): int {
		$data = $this->verify();
		if ( empty( $data['valid'] ) || empty( $data['features'] ) ) {
			$free = $this->free_features();
			return $free[ $feature ] ?? 0;
		}
		return (int) ( $data['features'][ $feature ] ?? 0 );
	}

	/**
	 * Check if the license is valid (includes 7-day grace period).
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		$data = $this->verify();
		return ! empty( $data['valid'] );
	}

	/**
	 * Make an API request to the license server.
	 *
	 * @param string $endpoint API path.
	 * @param array  $body     Request body.
	 * @return array|\WP_Error
	 */
	private function api_request( string $endpoint, array $body ) {
		$url = $this->api_url . $endpoint;

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'license_api_error', 'License API returned ' . $code );
		}

		return $response;
	}

	/**
	 * Parse the JSON response body.
	 *
	 * @param array $response wp_remote_post response.
	 * @return array
	 */
	private function parse_response( $response ): array {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Default response for free/unlicensed users.
	 *
	 * @return array
	 */
	private function free_response(): array {
		return [
			'valid'     => false,
			'plan'      => 'free',
			'features'  => $this->free_features(),
			'cache_ttl' => 3600,
		];
	}

	/**
	 * Free tier feature limits.
	 *
	 * @return array<string, int>
	 */
	private function free_features(): array {
		return [
			'analytics_days'   => 7,
			'cache_limit'      => 100,
			'auto_meta_daily'  => 10,
		];
	}
}
