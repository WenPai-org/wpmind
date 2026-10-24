<?php
/**
 * Provider endpoint config export / import (no api_key / key_meta).
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

use WPMind\Provider\Migrations\M013_FailoverOrder;

/**
 * Provider config transfer (PR6).
 */
final class ConfigTransfer {

	public const SCHEMA = 'wpmind_provider_config_v1';

	/**
	 * Export transferable fields. No api_key / key_meta.
	 *
	 * @return array{
	 *   schema:string,
	 *   failover_order:list<string>,
	 *   providers:array<string, array{enabled:bool, custom_base_url:string, models:list<string>, default_model:string}>
	 * }
	 */
	public static function export(): array {
		$repo      = new EndpointRepository();
		$providers = array();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$ep                 = $repo->get( $id );
			$providers[ $id ] = array(
				'enabled'         => (bool) $ep->enabled,
				'custom_base_url' => (string) $ep->custom_base_url,
				'models'          => array_values( $ep->models ),
				'default_model'   => (string) $ep->default_model,
			);
		}

		$order = get_option( M013_FailoverOrder::OPTION, array() );
		if ( ! is_array( $order ) ) {
			$order = array();
		}
		$order = array_values(
			array_filter(
				array_map( 'strval', $order ),
				static fn( string $id ): bool => in_array( $id, ProviderDefinitions::ids(), true )
			)
		);

		return array(
			'schema'         => self::SCHEMA,
			'failover_order' => $order,
			'providers'      => $providers,
		);
	}

	/**
	 * Dry-run: validate and compute diff without writing.
	 *
	 * @param array<string, mixed> $payload Import payload.
	 * @return array{ok:bool, errors:list<string>, diff:array<string, mixed>, normalized:?array}
	 */
	public static function dry_run( array $payload ): array {
		$parsed = self::normalize_payload( $payload );
		if ( ! $parsed['ok'] ) {
			return array(
				'ok'         => false,
				'errors'     => $parsed['errors'],
				'diff'       => array(),
				'normalized' => null,
			);
		}

		/** @var array{failover_order:list<string>, providers:array<string, array{enabled:bool, custom_base_url:string, models:list<string>, default_model:string}>} $norm */
		$norm    = $parsed['data'];
		$current = self::export();
		$diff    = array(
			'failover_order' => array(
				'from' => $current['failover_order'],
				'to'   => $norm['failover_order'],
			),
			'providers'      => array(),
		);

		foreach ( ProviderDefinitions::ids() as $id ) {
			$before = $current['providers'][ $id ] ?? null;
			$after  = $norm['providers'][ $id ] ?? null;
			if ( null === $after || null === $before ) {
				continue;
			}
			if ( $before !== $after ) {
				$diff['providers'][ $id ] = array(
					'from' => $before,
					'to'   => $after,
				);
			}
		}

		return array(
			'ok'         => true,
			'errors'     => array(),
			'diff'       => $diff,
			'normalized' => $norm,
		);
	}

	/**
	 * Import. confirm=false is dry-run; confirm=true writes and keeps local keys.
	 *
	 * @param array<string, mixed> $payload Import payload.
	 * @param bool                 $confirm Whether to apply.
	 * @return array{ok:bool, message:string, errors:list<string>, diff:array<string, mixed>}
	 */
	public static function import( array $payload, bool $confirm = false ): array {
		$dry = self::dry_run( $payload );
		if ( ! $dry['ok'] || null === $dry['normalized'] ) {
			return array(
				'ok'      => false,
				'message' => __( '导入校验失败', 'wpmind' ),
				'errors'  => $dry['errors'],
				'diff'    => array(),
			);
		}

		if ( ! $confirm ) {
			return array(
				'ok'      => true,
				'message' => __( '预检通过，请确认后导入', 'wpmind' ),
				'errors'  => array(),
				'diff'    => $dry['diff'],
			);
		}

		$norm = $dry['normalized'];
		$repo = new EndpointRepository();

		$raw_mirror = get_option( EndpointRepository::OPTION, array() );
		if ( ! is_array( $raw_mirror ) ) {
			$raw_mirror = array();
		}
		$v2_providers = array();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$ep       = $repo->get( $id );
			$incoming = $norm['providers'][ $id ] ?? null;
			$entry    = array(
				'enabled'         => $ep->enabled,
				'custom_base_url' => $ep->custom_base_url,
				'models'          => $ep->models,
				'default_model'   => $ep->default_model,
			);
			if ( is_array( $incoming ) ) {
				$entry['enabled']         = (bool) $incoming['enabled'];
				$entry['custom_base_url'] = (string) $incoming['custom_base_url'];
				$entry['models']          = array_values( (array) $incoming['models'] );
				$entry['default_model']   = (string) $incoming['default_model'];
			}
			if ( [] !== $ep->key_meta ) {
				$entry['key_meta'] = $ep->key_meta;
			}
			$v2_providers[ $id ] = $entry;

			$mirror_entry = is_array( $raw_mirror[ $id ] ?? null ) ? $raw_mirror[ $id ] : array();
			$api_key      = is_string( $mirror_entry['api_key'] ?? null ) ? (string) $mirror_entry['api_key'] : $ep->api_key;
			$raw_mirror[ $id ] = EndpointRepository::mirror_entry( $id, $entry, $api_key );
		}

		$repo->save_v2( $v2_providers, EndpointRepository::legacy_hash( $raw_mirror ) );
		$repo->save( $raw_mirror );

		update_option( M013_FailoverOrder::OPTION, $norm['failover_order'], false );

		return array(
			'ok'      => true,
			'message' => __( '配置已导入（本站 API Key 已保留）', 'wpmind' ),
			'errors'  => array(),
			'diff'    => $dry['diff'],
		);
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return array{ok:bool, errors:list<string>, data?:array}
	 */
	private static function normalize_payload( array $payload ): array {
		$errors = array();
		$schema = (string) ( $payload['schema'] ?? '' );
		if ( '' !== $schema && self::SCHEMA !== $schema ) {
			$errors[] = sprintf(
				/* translators: %s: schema id */
				__( '不支持的配置 schema：%s', 'wpmind' ),
				$schema
			);
		}

		$order_in = $payload['failover_order'] ?? array();
		if ( ! is_array( $order_in ) ) {
			$errors[] = __( 'failover_order 必须是数组', 'wpmind' );
			$order_in = array();
		}
		$order = array();
		foreach ( $order_in as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}
			$id = sanitize_key( $id );
			if ( in_array( $id, ProviderDefinitions::ids(), true ) && ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}

		$providers_in = $payload['providers'] ?? null;
		if ( ! is_array( $providers_in ) ) {
			$maybe = array();
			foreach ( ProviderDefinitions::ids() as $id ) {
				if ( isset( $payload[ $id ] ) && is_array( $payload[ $id ] ) ) {
					$maybe[ $id ] = $payload[ $id ];
				}
			}
			$providers_in = $maybe;
		}

		$providers = array();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$row = is_array( $providers_in[ $id ] ?? null ) ? $providers_in[ $id ] : array();
			$url = isset( $row['custom_base_url'] ) ? trim( (string) $row['custom_base_url'] ) : '';
			if ( '' !== $url ) {
				$validated = EndpointRepository::validate_base_url( $url, $id, true );
				if ( '' === $validated ) {
					$errors[] = sprintf(
						/* translators: %s: provider id */
						__( '%s 的 custom_base_url 无效（仅允许 https，且不能带查询参数）', 'wpmind' ),
						$id
					);
					continue;
				}
				$url = $validated;
			}
			$providers[ $id ] = array(
				'enabled'         => ! empty( $row['enabled'] ),
				'custom_base_url' => $url,
				'models'          => EndpointRepository::normalize_models( $row['models'] ?? array() ),
				'default_model'   => is_string( $row['default_model'] ?? null ) ? trim( (string) $row['default_model'] ) : '',
			);
		}

		if ( array() !== $errors ) {
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}

		return array(
			'ok'     => true,
			'errors' => array(),
			'data'   => array(
				'failover_order' => $order,
				'providers'      => $providers,
			),
		);
	}
}
