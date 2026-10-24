<?php
/**
 * Provider diagnostics export (no keys / fingerprints / raw bodies).
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\Compat\AiClientEnvironment;
use WPMind\Provider\Health\ErrorCatalog;
use WPMind\Provider\Health\ErrorClass;
use WPMind\Provider\Migrations\Migrator;

/**
 * Diagnostics payload builder and recent-error ring buffer.
 */
final class Diagnostics {

	public const RECENT_TRANSIENT = 'wpmind_recent_provider_errors';

	public const RECENT_MAX = 20;

	/** Diagnostic sample body truncate (design section 10). */
	public const BODY_TRUNCATE = 120;

	/**
	 * Record a redacted recent error (transient; no new option).
	 *
	 * @param array{provider?:string, class?:string, status?:int, message?:string, at?:int} $sample Sample.
	 */
	public static function push_error( array $sample ): void {
		$provider = sanitize_key( (string) ( $sample['provider'] ?? '' ) );
		$class    = (string) ( $sample['class'] ?? '' );
		$status   = (int) ( $sample['status'] ?? 0 );
		$message  = self::sanitize_message( (string) ( $sample['message'] ?? '' ) );
		$at       = (int) ( $sample['at'] ?? time() );

		$known   = self::known_keys();
		$message = Secrets::redact( $message, $known );

		$entry = array(
			'provider' => $provider,
			'class'    => $class,
			'status'   => $status,
			'message'  => $message,
			'at'       => $at,
		);

		$list = get_transient( self::RECENT_TRANSIENT );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		array_unshift( $list, $entry );
		$list = array_slice( $list, 0, self::RECENT_MAX );
		set_transient( self::RECENT_TRANSIENT, $list, 604800 );
	}

	/**
	 * @return list<array{provider:string, class:string, status:int, message:string, at:int}>
	 */
	public static function recent_errors(): array {
		$list = get_transient( self::RECENT_TRANSIENT );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Build diagnostics export (redacted; no key / fingerprint / full body).
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$repo     = new EndpointRepository();
		$failover = class_exists( FailoverManager::class ) ? FailoverManager::instance() : null;
		$providers = array();
		$known     = self::known_keys();

		foreach ( ProviderDefinitions::ids() as $id ) {
			$ep         = $repo->get( $id );
			$base       = $repo->resolved_base_url( $id );
			$host       = self::hostname_only( $base );
			$cb         = $failover ? $failover->get_circuit_breaker( $id ) : null;
			$cb_details = $cb ? $cb->get_status_details() : array();
			$reason     = is_string( $cb_details['reason'] ?? null ) ? (string) $cb_details['reason'] : '';
			$error_entry = null;
			if ( '' !== $reason ) {
				try {
					$ec          = ErrorClass::from( $reason );
					$error_entry = ErrorCatalog::entry( $ec, $id );
				} catch ( \ValueError $e ) {
					$error_entry = array(
						'class'           => $reason,
						'message'         => $reason,
						'recovery'        => '',
						'credentials_url' => ErrorCatalog::credentials_url( $id ),
					);
				}
			}

			$providers[ $id ] = array(
				'enabled'       => (bool) $ep->enabled,
				'has_api_key'   => '' !== $ep->api_key,
				'base_url_host' => $host,
				'models'        => array_values( $ep->models ),
				'default_model' => $ep->default_model,
				'circuit'       => array(
					'state'          => $cb_details['state'] ?? CircuitBreaker::STATE_CLOSED,
					'state_label'    => $cb_details['state_label'] ?? '',
					'reason'         => '' !== $reason ? $reason : null,
					'since'          => $cb_details['since'] ?? null,
					'last_failure'   => $cb_details['last_failure'] ?? null,
					'cooldown_until' => $cb_details['cooldown_until'] ?? null,
					'recovery_in'    => $cb_details['recovery_in'] ?? null,
				),
				'catalog'       => $error_entry,
			);
		}

		$order = get_option( 'wpmind_failover_order', array() );
		if ( ! is_array( $order ) ) {
			$order = array();
		}

		$mig = get_option( Migrator::LOG_OPTION, array() );
		if ( ! is_array( $mig ) ) {
			$mig = array();
		}
		$mig = array_slice( array_values( $mig ), 0, Migrator::LOG_MAX );

		$recent = self::recent_errors();
		$recent = array_map(
			static function ( array $row ) use ( $known ): array {
				return array(
					'provider' => (string) $row['provider'],
					'class'    => (string) $row['class'],
					'status'   => (int) $row['status'],
					'message'  => Secrets::redact( (string) $row['message'], $known ),
					'at'       => (int) $row['at'],
				);
			},
			$recent
		);

		$payload = array(
			'schema'          => 'wpmind_diagnostics_v1',
			'exported_at'     => gmdate( 'c' ),
			'exported_at_cst' => self::format_shanghai( time() ),
			'environment'     => array(
				'php'       => PHP_VERSION,
				'wp'        => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
				'wpmind'    => defined( 'WPMIND_VERSION' ) ? (string) WPMIND_VERSION : '',
				'ai_client' => class_exists( AiClientEnvironment::class ) ? AiClientEnvironment::detect() : array(),
			),
			'failover_order'  => array_values( array_map( 'strval', $order ) ),
			'providers'       => $providers,
			'migration_log'   => $mig,
			'recent_errors'   => $recent,
		);

		return self::redact_tree( $payload, $known );
	}

	/**
	 * Extract hostname from a URL (no path / query).
	 */
	public static function hostname_only( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		return (string) $parts['host'];
	}

	/**
	 * Asia/Shanghai display.
	 */
	public static function format_shanghai( int $ts ): string {
		if ( $ts <= 0 ) {
			return '';
		}
		try {
			$dt = ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( new \DateTimeZone( 'Asia/Shanghai' ) );
			return $dt->format( 'Y-m-d H:i:s' ) . ' Asia/Shanghai';
		} catch ( \Exception $e ) {
			return gmdate( 'Y-m-d H:i:s', $ts ) . ' UTC';
		}
	}

	/**
	 * Recursive redaction; drop sensitive keys.
	 *
	 * @param mixed        $data       Data.
	 * @param array<mixed> $known_keys Known keys.
	 * @return mixed
	 */
	public static function redact_tree( $data, array $known_keys = array() ) {
		$banned = array(
			'api_key'       => true,
			'key_meta'      => true,
			'fingerprint'   => true,
			'authorization' => true,
			'secret'        => true,
			'secret_key'    => true,
			'access_token'  => true,
			'token'         => true,
			'password'      => true,
		);

		if ( is_string( $data ) ) {
			return Secrets::redact( $data, $known_keys );
		}
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$out = array();
		foreach ( $data as $key => $value ) {
			$key_str = (string) $key;
			$lower   = strtolower( $key_str );
			if ( isset( $banned[ $lower ] ) ) {
				continue;
			}
			if ( is_string( $value ) && ( str_contains( $lower, 'key' ) || str_contains( $lower, 'secret' ) || str_contains( $lower, 'token' ) ) ) {
				if ( 'has_api_key' !== $lower && 'base_url_host' !== $lower ) {
					$out[ $key ] = Secrets::redact( $value, $known_keys );
					continue;
				}
			}
			$out[ $key ] = self::redact_tree( $value, $known_keys );
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private static function known_keys(): array {
		$keys = array();
		try {
			$repo = new EndpointRepository();
			foreach ( ProviderDefinitions::ids() as $id ) {
				$ep = $repo->get( $id );
				if ( '' !== $ep->api_key ) {
					$keys[] = $ep->api_key;
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Ignore early boot / incomplete test stack.
		}
		return $keys;
	}

	private static function sanitize_message( string $message ): string {
		$message = trim( $message );
		if ( strlen( $message ) > self::BODY_TRUNCATE ) {
			$message = substr( $message, 0, self::BODY_TRUNCATE ) . '…';
		}
		return $message;
	}
}
