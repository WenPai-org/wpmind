<?php
/**
 * Optional online /models discovery (manual refresh only; D3).
 *
 * @package WPMind\Provider\Catalog
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Catalog;

use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Secrets;

/**
 * Fetches OpenAI-compatible model ids from a provider base URL.
 *
 * @since 0.13.0
 */
final class RemoteModelLister {

	private const TRANSIENT_PREFIX = 'wpmind_models_remote_';
	private const TTL              = 43200;

	/**
	 * Read cached remote model list.
	 *
	 * @param string $provider Provider id.
	 * @return array{ids: list<string>, fetched_at: int, base_url_hash: string, key_fp: string}|null
	 */
	public function cached( string $provider ): ?array {
		$raw = get_transient( self::TRANSIENT_PREFIX . $provider );
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * Invalidate cache when key or base URL changes.
	 *
	 * @param string $provider Provider id.
	 * @return void
	 */
	public function invalidate( string $provider ): void {
		delete_transient( self::TRANSIENT_PREFIX . $provider );
	}

	/**
	 * GET resolved base URL /models and cache ids.
	 *
	 * @param string $provider Provider id.
	 * @param string $api_key  API key (Bearer).
	 * @return array{ok: bool, ids: list<string>, error?: string}
	 */
	public function refresh( string $provider, string $api_key ): array {
		$repo     = new EndpointRepository();
		$base_url = $repo->resolved_base_url( $provider );
		if ( '' === $base_url ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => 'missing_base_url',
			];
		}

		$url  = trailingslashit( $base_url ) . 'models';
		$args = [
			'timeout'               => 5,
			'headers'               => [
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			],
			// Skip WPMind usage tracking for discovery.
			'_wpmind_skip_tracking' => true,
		];

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => $response->get_error_code(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => 'http_' . $code,
			];
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ids  = [];
		if ( is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ) {
			foreach ( $body['data'] as $row ) {
				if ( is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
					$ids[] = $row['id'];
				}
			}
		}

		$ids     = array_values( array_unique( $ids ) );
		$key_fp  = Secrets::fingerprint( $api_key );
		$payload = [
			'ids'           => $ids,
			'fetched_at'    => time(),
			'base_url_hash' => hash( 'sha256', $base_url ),
			'key_fp'        => $key_fp,
		];
		set_transient( self::TRANSIENT_PREFIX . $provider, $payload, self::TTL );

		return [
			'ok'  => true,
			'ids' => $ids,
		];
	}

	/**
	 * Return cached ids only if key_fp and base_url still match.
	 *
	 * @param string $provider Provider id.
	 * @param string $api_key  API key used to validate fingerprint.
	 * @return list<string>
	 */
	public function ids_if_valid( string $provider, string $api_key ): array {
		$cached = $this->cached( $provider );
		if ( null === $cached ) {
			return [];
		}
		$repo     = new EndpointRepository();
		$base_url = $repo->resolved_base_url( $provider );
		$hash     = hash( 'sha256', $base_url );
		$fp       = Secrets::fingerprint( $api_key );
		if ( $cached['base_url_hash'] !== $hash || $cached['key_fp'] !== $fp ) {
			$this->invalidate( $provider );
			return [];
		}
		return $cached['ids'];
	}
}
