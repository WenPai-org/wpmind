<?php
/**
 * 把 HTTP / WP_Error 响应归入 ErrorClass。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Health;

use WP_Error;
use WPMind\Provider\Secrets;

/**
 * 错误分类器。
 */
final class ErrorClassifier {

	/**
	 * 响应体写入状态/日志前的最大长度。
	 */
	public const BODY_TRUNCATE = 300;

	/**
	 * Retry-After 冷却上限（秒）。
	 */
	public const RETRY_AFTER_CAP = 60;

	/**
	 * 分类 HTTP / WP_Error。
	 *
	 * @param WP_Error|array|null $response wp_remote_* 返回值.
	 * @param int                 $status   已解析的状态码（0 表示未知）.
	 * @param string              $body     响应体.
	 * @param array<string,mixed> $headers  响应头（小写键可选）.
	 * @return array{class: ErrorClass, retry_after: int, message: string, redacted_body: string}
	 */
	public static function classify( $response, int $status = 0, string $body = '', array $headers = array() ): array {
		if ( $response instanceof WP_Error ) {
			return self::from_wp_error( $response );
		}

		if ( $status <= 0 && is_array( $response ) ) {
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( '' === $body ) {
				$body = (string) wp_remote_retrieve_body( $response );
			}
			if ( array() === $headers ) {
				$headers = self::normalize_headers( (array) wp_remote_retrieve_headers( $response ) );
			}
		}

		$redacted = self::safe_body( $body );
		$lower    = strtolower( $body );

		if ( 401 === $status || ( 403 === $status && self::looks_like_auth( $lower ) ) ) {
			return self::pack( ErrorClass::AuthFailed, 0, $redacted, $status );
		}

		if ( 402 === $status || self::looks_like_quota( $lower ) ) {
			return self::pack( ErrorClass::QuotaExhausted, 0, $redacted, $status );
		}

		if ( 429 === $status ) {
			$retry = self::parse_retry_after( $headers, $body );
			return self::pack( ErrorClass::RateLimited, $retry, $redacted, $status );
		}

		if ( in_array( $status, array( 500, 502, 503, 504, 529 ), true ) ) {
			return self::pack( ErrorClass::ServerError, 0, $redacted, $status );
		}

		if ( 404 === $status && self::looks_like_model( $lower ) ) {
			return self::pack( ErrorClass::ModelUnavailable, 0, $redacted, $status );
		}

		if ( in_array( $status, array( 400, 413, 422 ), true ) || self::looks_like_bad_request( $lower ) ) {
			return self::pack( ErrorClass::BadRequest, 0, $redacted, $status );
		}

		if ( 408 === $status ) {
			return self::pack( ErrorClass::Timeout, 0, $redacted, $status );
		}

		// 拿不准时归 server_error，避免误判成 auth_failed 锁死.
		if ( $status >= 400 ) {
			return self::pack( ErrorClass::ServerError, 0, $redacted, $status );
		}

		return self::pack( ErrorClass::ServerError, 0, $redacted, $status );
	}

	/**
	 * 仅根据状态码判断是否可重试（兼容 ErrorHandler::should_retry）。
	 *
	 * @param int $http_code HTTP status.
	 */
	public static function should_retry_status( int $http_code ): bool {
		$class = self::classify( null, $http_code, '', array() )['class'];
		return $class->may_retry_same_provider() || ErrorClass::Timeout === $class;
	}

	/**
	 * @param WP_Error $error WP_Error.
	 * @return array{class: ErrorClass, retry_after: int, message: string, redacted_body: string}
	 */
	private static function from_wp_error( WP_Error $error ): array {
		$msg      = Secrets::redact( $error->get_error_message() );
		$lower    = strtolower( $msg );
		$code     = $error->get_error_code();
		$redacted = self::safe_body( $msg );
		$data     = $error->error_data[ $code ] ?? array();
		$status   = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;

		if ( str_contains( $lower, 'timed out' ) || str_contains( $lower, 'timeout' ) || 408 === $status ) {
			return self::pack( ErrorClass::Timeout, 0, $redacted, 0, $msg );
		}

		if (
			str_contains( $lower, 'resolve' )
			|| str_contains( $lower, 'could not resolve' )
			|| str_contains( $lower, 'name or service not known' )
			|| str_contains( $lower, 'failed to connect' )
			|| str_contains( $lower, 'connection refused' )
			|| str_contains( $lower, 'ssl' )
			|| str_contains( $lower, 'certificate' )
			|| str_contains( $code, 'http_request_failed' )
		) {
			return self::pack( ErrorClass::Network, 0, $redacted, 0, $msg );
		}

		return self::pack( ErrorClass::Network, 0, $redacted, 0, $msg );
	}

	/**
	 * @param array<string,mixed> $headers Headers.
	 * @return array<string,string>
	 */
	private static function normalize_headers( array $headers ): array {
		$out = array();
		foreach ( $headers as $k => $v ) {
			$key         = strtolower( (string) $k );
			$out[ $key ] = is_array( $v ) ? (string) ( $v[0] ?? '' ) : (string) $v;
		}
		return $out;
	}

	/**
	 * @param array<string,string> $headers Headers.
	 * @param string               $body    Body.
	 */
	private static function parse_retry_after( array $headers, string $body ): int {
		$raw = $headers['retry-after'] ?? '';
		if ( '' === $raw && preg_match( '/retry[-_ ]?after["\':\s]+(\d+)/i', $body, $m ) ) {
			$raw = $m[1];
		}
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return 0;
		}
		return max( 0, min( self::RETRY_AFTER_CAP, (int) $raw ) );
	}

	private static function looks_like_auth( string $lower ): bool {
		foreach ( array( 'invalid api key', 'invalid_api_key', 'incorrect api key', 'authentication', 'unauthorized', 'permission', 'invalid key' ) as $needle ) {
			if ( str_contains( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function looks_like_quota( string $lower ): bool {
		foreach ( array( 'insufficient_quota', 'insufficient quota', 'quota exceeded', 'quota_exceeded', '余额不足', '额度不足', 'billing soft' ) as $needle ) {
			if ( str_contains( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function looks_like_model( string $lower ): bool {
		foreach ( array( 'model_not_found', 'model not found', 'does not exist', 'no such model', 'unknown model' ) as $needle ) {
			if ( str_contains( $lower, $needle ) ) {
				return true;
			}
		}
		return str_contains( $lower, 'model' );
	}

	private static function looks_like_bad_request( string $lower ): bool {
		foreach ( array( 'context_length', 'context length', 'maximum context', 'invalid_request', 'payload too large' ) as $needle ) {
			if ( str_contains( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 脱敏并截断 body。
	 *
	 * @param string       $body       Raw body.
	 * @param array<mixed> $known_keys Known keys.
	 */
	public static function safe_body( string $body, array $known_keys = array() ): string {
		$redacted = Secrets::redact( $body, $known_keys );
		if ( strlen( $redacted ) > self::BODY_TRUNCATE ) {
			return substr( $redacted, 0, self::BODY_TRUNCATE );
		}
		return $redacted;
	}

	/**
	 * @return array{class: ErrorClass, retry_after: int, message: string, redacted_body: string}
	 */
	private static function pack( ErrorClass $class, int $retry_after, string $redacted_body, int $status, string $message = '' ): array {
		if ( '' === $message ) {
			$message = $class->value . ( $status > 0 ? " (HTTP {$status})" : '' );
		}
		return array(
			'class'         => $class,
			'retry_after'   => $retry_after,
			'message'       => Secrets::redact( $message ),
			'redacted_body' => $redacted_body,
		);
	}
}
