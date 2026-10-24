<?php
/**
 * API Key 轮换：先内存验证，再原子替换，库中始终只有一份明文。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\Catalog\RemoteModelLister;
use WPMind\Provider\Health\HealthChecker;

/**
 * Key rotation helper.
 *
 * @since 0.13.0
 */
final class KeyRotation {

	/**
	 * Action fired after a key fingerprint changes (args: provider id, old fp, new fp).
	 */
	public const ACTION = 'wpmind_api_key_rotated';

	/**
	 * While true, update_option hook skips aftermath (caller already ran it).
	 *
	 * @var bool
	 */
	private static bool $skip_hook_aftermath = false;

	/**
	 * Register option hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'update_option_' . EndpointRepository::OPTION, array( self::class, 'on_endpoints_updated' ), 10, 2 );
	}

	/**
	 * Build non-sensitive key_meta for a plaintext key.
	 *
	 * @param string $api_key Plaintext API key.
	 * @return array{fingerprint:string,set_at:int}
	 */
	public static function make_meta( string $api_key ): array {
		return array(
			'fingerprint' => Secrets::fingerprint( $api_key ),
			'set_at'      => time(),
		);
	}

	/**
	 * Lazy-fill key_meta from an existing key without changing the key itself.
	 *
	 * @param string $provider_id Provider id.
	 * @return array{fingerprint:string,set_at:int}|array{}
	 */
	public static function ensure_key_meta( string $provider_id ): array {
		$repo     = new EndpointRepository();
		$endpoint = $repo->get( $provider_id );
		if ( ! $endpoint->has_api_key() ) {
			return array();
		}

		$meta = $endpoint->key_meta;
		$fp   = is_string( $meta['fingerprint'] ?? null ) ? (string) $meta['fingerprint'] : '';
		if ( '' !== $fp && Secrets::fingerprint( $endpoint->api_key ) === $fp ) {
			return array(
				'fingerprint' => $fp,
				'set_at'      => (int) ( $meta['set_at'] ?? time() ),
			);
		}

		$fresh = self::make_meta( $endpoint->api_key );
		self::persist_key_meta( $provider_id, $fresh );
		return $fresh;
	}

	/**
	 * Persist key_meta into v2 for one provider (does not touch api_key).
	 *
	 * @param string               $provider_id Provider id.
	 * @param array<string, mixed> $meta        key_meta.
	 * @return void
	 */
	public static function persist_key_meta( string $provider_id, array $meta ): void {
		$repo = new EndpointRepository();
		$v2   = $repo->v2();
		if ( null === $v2 ) {
			$providers = array();
			foreach ( ProviderDefinitions::ids() as $id ) {
				$ep    = $repo->get( $id );
				$entry = array(
					'enabled'         => $ep->enabled,
					'custom_base_url' => $ep->custom_base_url,
					'models'          => $ep->models,
					'default_model'   => $ep->default_model,
				);
				if ( $id === $provider_id ) {
					$entry['key_meta'] = $meta;
				} elseif ( [] !== $ep->key_meta ) {
					$entry['key_meta'] = $ep->key_meta;
				}
				if ( $ep->needs_reconfigure ) {
					$entry['needs_reconfigure'] = true;
				}
				$providers[ $id ] = $entry;
			}
			$repo->save_v2( $providers, EndpointRepository::legacy_hash( $repo->raw() ) );
			return;
		}

		$providers                 = is_array( $v2['providers'] ?? null ) ? $v2['providers'] : array();
		$entry                     = is_array( $providers[ $provider_id ] ?? null ) ? $providers[ $provider_id ] : array();
		$entry['key_meta']         = $meta;
		$providers[ $provider_id ] = $entry;
		$repo->save_v2( $providers, (string) ( $v2['legacy_hash'] ?? '' ) );
	}

	/**
	 * Verify new key in memory, then atomically replace stored key.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $new_key     Candidate key (never logged).
	 * @param string $posted_url  Optional custom base URL for probe only.
	 * @return array{ok:bool,message:string,error_class:?string,latency_ms:int,fingerprint?:string,set_at?:int}
	 */
	public function rotate( string $provider_id, string $new_key, string $posted_url = '' ): array {
		$new_key = trim( $new_key );
		if ( '' === $new_key ) {
			return array(
				'ok'          => false,
				'message'     => __( '请填写新的 API Key', 'wpmind' ),
				'error_class' => 'auth_failed',
				'latency_ms'  => 0,
			);
		}

		if ( ! ProviderDefinitions::has( $provider_id ) ) {
			return array(
				'ok'          => false,
				'message'     => __( '服务不存在', 'wpmind' ),
				'error_class' => null,
				'latency_ms'  => 0,
			);
		}

		$repo   = new EndpointRepository();
		$target = $repo->connection_test_target( $provider_id, $new_key, $posted_url );
		if ( is_wp_error( $target ) ) {
			return array(
				'ok'          => false,
				'message'     => $target->get_error_message(),
				'error_class' => $target->get_error_code(),
				'latency_ms'  => 0,
			);
		}

		$checker = new HealthChecker();
		$result  = $checker->probe(
			$provider_id,
			false,
			array(
				'api_key'     => $target['api_key'],
				'base_url'    => $target['base_url'],
				'skip_record' => true,
			)
		);

		if ( ! $result['ok'] ) {
			return array(
				'ok'          => false,
				'message'     => Secrets::redact( $result['message'], array( $new_key ) ),
				'error_class' => $result['error_class'],
				'latency_ms'  => $result['latency_ms'],
			);
		}

		$endpoint = $repo->get( $provider_id );
		$old_fp   = Secrets::fingerprint( $endpoint->api_key );
		$new_fp   = Secrets::fingerprint( $new_key );
		$meta     = self::make_meta( $new_key );

		$this->persist_rotated_key( $provider_id, $new_key, $meta );

		self::$skip_hook_aftermath = true;
		try {
			$this->after_key_changed( $provider_id, $old_fp, $new_fp );
		} finally {
			self::$skip_hook_aftermath = false;
		}

		return array(
			'ok'          => true,
			'message'     => __( 'API Key 已验证并替换', 'wpmind' ),
			'error_class' => null,
			'latency_ms'  => $result['latency_ms'],
			'fingerprint' => $meta['fingerprint'],
			'set_at'      => $meta['set_at'],
		);
	}

	/**
	 * Write new api_key (mirror) + key_meta (v2). Old key is not retained.
	 *
	 * @param string                               $provider_id Provider id.
	 * @param string                               $new_key     New plaintext key.
	 * @param array{fingerprint:string,set_at:int} $meta        Key meta.
	 * @return void
	 */
	private function persist_rotated_key( string $provider_id, string $new_key, array $meta ): void {
		$repo = new EndpointRepository();
		$raw  = $repo->raw();
		$v2   = $repo->v2();

		$current  = $repo->get( $provider_id );
		$v2_entry = array(
			'enabled'         => $current->enabled,
			'custom_base_url' => $current->custom_base_url,
			'models'          => $current->models,
			'default_model'   => $current->default_model,
			'key_meta'        => $meta,
		);

		if ( null === $v2 ) {
			$providers = array();
			foreach ( ProviderDefinitions::ids() as $id ) {
				$ep    = $repo->get( $id );
				$entry = array(
					'enabled'         => $ep->enabled,
					'custom_base_url' => $ep->custom_base_url,
					'models'          => $ep->models,
					'default_model'   => $ep->default_model,
				);
				if ( $id === $provider_id ) {
					$entry['key_meta'] = $meta;
				} elseif ( [] !== $ep->key_meta ) {
					$entry['key_meta'] = $ep->key_meta;
				}
				if ( $id !== $provider_id && $ep->needs_reconfigure ) {
					$entry['needs_reconfigure'] = true;
				}
				$providers[ $id ] = $entry;
			}
		} else {
			$providers = is_array( $v2['providers'] ?? null ) ? $v2['providers'] : array();
			foreach ( ProviderDefinitions::ids() as $id ) {
				if ( ! isset( $providers[ $id ] ) || ! is_array( $providers[ $id ] ) ) {
					$providers[ $id ] = array(
						'enabled'         => false,
						'custom_base_url' => '',
						'models'          => array(),
						'default_model'   => '',
					);
				}
			}
			$prev                      = is_array( $providers[ $provider_id ] ?? null ) ? $providers[ $provider_id ] : array();
			$providers[ $provider_id ] = array_merge( $prev, $v2_entry );
			unset( $providers[ $provider_id ]['needs_reconfigure'] );
			unset( $providers[ $provider_id ]['api_key'] );
		}

		$raw[ $provider_id ] = EndpointRepository::mirror_entry( $provider_id, $providers[ $provider_id ], $new_key );

		$hash = EndpointRepository::legacy_hash( $raw );
		$repo->save_v2( $providers, $hash );
		$repo->save( $raw );
	}

	/**
	 * Aftermath after fingerprint change: reset breaker/health, invalidate remote models, refresh, fire action.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $old_fp      Previous fingerprint (may be empty).
	 * @param string $new_fp      New fingerprint (may be empty if cleared).
	 * @return void
	 */
	public function after_key_changed( string $provider_id, string $old_fp, string $new_fp ): void {
		if ( $old_fp === $new_fp ) {
			return;
		}

		$fm = FailoverManager::instance();
		$fm->reset_provider( $provider_id );

		// Belt-and-suspenders: clear attention states even if breaker was not in the live map.
		( new CircuitBreaker( $provider_id ) )->clear_attention_states();

		( new RemoteModelLister() )->invalidate( $provider_id );

		$fm->refresh();

		/**
		 * Fires after an API key fingerprint changes for a provider.
		 *
		 * Arguments contain fingerprints only — never the plaintext key.
		 *
		 * @param string $provider_id Provider id.
		 * @param string $old_fp      Previous fingerprint.
		 * @param string $new_fp      New fingerprint.
		 */
		do_action( 'wpmind_api_key_rotated', $provider_id, $old_fp, $new_fp );
	}

	/**
	 * Hook: form save / any update of wpmind_custom_endpoints.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public static function on_endpoints_updated( $old_value, $value ): void {
		if ( self::$skip_hook_aftermath ) {
			return;
		}

		$old  = is_array( $old_value ) ? $old_value : array();
		$new  = is_array( $value ) ? $value : array();
		$self = new self();

		foreach ( ProviderDefinitions::ids() as $id ) {
			$old_key = is_array( $old[ $id ] ?? null ) ? (string) ( $old[ $id ]['api_key'] ?? '' ) : '';
			$new_key = is_array( $new[ $id ] ?? null ) ? (string) ( $new[ $id ]['api_key'] ?? '' ) : '';
			$old_fp  = Secrets::fingerprint( $old_key );
			$new_fp  = Secrets::fingerprint( $new_key );
			if ( $old_fp === $new_fp ) {
				continue;
			}
			$self->after_key_changed( $id, $old_fp, $new_fp );
		}
	}
}
