<?php
/**
 * Build the remote model catalog payload for updates.wenpai.net/wpmind/models.json.
 *
 * Run inside a WordPress context with WPMind active:
 *
 *   wp eval-file tools/build-models-catalog.php > models-remote.json
 *   # or with wp-cli on the build host:
 *   WPMIND_CAT_KEY_QWEN=sk-... wp eval-file tools/build-models-catalog.php
 *
 * For each provider it GETs <base_url>/models with the env key
 * WPMIND_CAT_KEY_<PROVIDER_ID> (uppercased). Providers without a key keep
 * their bundled block untouched. Newly seen model ids are appended with
 * minimal meta (no capabilities → consumers fall back to name heuristics);
 * bundled meta always wins for ids already known. default_model is never
 * overridden by this script — review and set it by hand if needed.
 *
 * No `declare(strict_types=1)` here: `wp eval-file` runs this file through
 * eval(), where a declare statement is a fatal error.
 *
 * @package WPMind
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI build script: reads a bundled file and writes progress to STDERR.

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

( static function (): void {
	$bundled_path = dirname( __DIR__ ) . '/includes/Provider/Catalog/models.json';
	$bundled      = json_decode( (string) file_get_contents( $bundled_path ), true );
	if ( ! is_array( $bundled ) || ! isset( $bundled['providers'] ) ) {
		fwrite( STDERR, "Cannot read bundled models.json\n" );
		exit( 1 );
	}

	$out                    = $bundled;
	$out['catalog_version'] = 'remote-' . gmdate( 'Y.m.d' );
	$out['generated_at']    = gmdate( 'Y-m-d' );
	$out['notes']           = 'Auto-merged remote /models ids; bundled meta preserved.';

	$fetch = static function ( string $url, string $key ): array {
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return [];
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return [];
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : [];
	};

	$env = static function ( string $name ): string {
		return isset( $_ENV[ $name ] ) ? (string) $_ENV[ $name ] : (string) getenv( $name );
	};

	foreach ( array_keys( $out['providers'] ) as $provider_id ) {
		$api_key  = $env( 'WPMIND_CAT_KEY_' . strtoupper( $provider_id ) );
		$base_url = $env( 'WPMIND_CAT_URL_' . strtoupper( $provider_id ) );
		if ( '' === $base_url ) {
			$base_url = \WPMind\Provider\ProviderDefinitions::default_base_url( $provider_id );
		}
		if ( '' === $api_key || '' === $base_url ) {
			fwrite( STDERR, "skip {$provider_id}: no key or base url\n" );
			continue;
		}

		$rows = $fetch( untrailingslashit( $base_url ) . '/models', $api_key );
		if ( [] === $rows ) {
			fwrite( STDERR, "skip {$provider_id}: empty /models\n" );
			continue;
		}

		$block  = $out['providers'][ $provider_id ];
		$models = isset( $block['models'] ) && is_array( $block['models'] ) ? $block['models'] : [];
		$added  = 0;
		foreach ( $rows as $row ) {
			$model_id = is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) ? trim( $row['id'] ) : '';
			if ( '' === $model_id || isset( $models[ $model_id ] ) ) {
				continue;
			}
			// No capabilities: consumers fall back to the name heuristic.
			$models[ $model_id ] = [
				'name'        => $model_id,
				'status'      => 'active',
				'remote_seen' => true,
			];
			++$added;
		}
		if ( $added > 0 ) {
			$block['models']                  = $models;
			$out['providers'][ $provider_id ] = $block;
		}
		fwrite( STDERR, "{$provider_id}: +{$added} models\n" );
	}

	echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
} )();
