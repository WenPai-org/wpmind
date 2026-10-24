<?php
/**
 * 统一 Provider 出站 HTTP 客户端。
 *
 * 所有对 provider 的 HTTP 从此发出：带 _wpmind_skip_tracking、错误分类、
 * 单次 record_result / usage，消除双计。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

use WP_Error;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\Diagnostics;
use WPMind\Provider\Health\ErrorClass;
use WPMind\Provider\Health\ErrorClassifier;

/**
 * Provider HTTP client.
 */
final class ProviderClient {

	/**
	 * @param string               $provider Provider id.
	 * @param string               $method   GET|POST.
	 * @param string               $path     Relative path (e.g. chat/completions) or absolute URL.
	 * @param array<string, mixed> $args {
	 *     @type array|string|null $body
	 *     @type array             $headers
	 *     @type int               $timeout
	 *     @type string|null       $api_key
	 *     @type string|null       $base_url
	 *     @type bool              $record_usage  Default true on success.
	 *     @type bool              $skip_record   Skip FailoverManager recording (e.g. key rotation probe).
	 *     @type string|null       $model         Hint for usage event.
	 * }
	 * @return array{ok:bool, status:int, body:string, data:?array, headers:array, latency_ms:int, error_class:?ErrorClass, retry_after:int, wp_error:?WP_Error, response:mixed}
	 */
	public function request( string $provider, string $method, string $path, array $args = array() ): array {
		$repo     = new EndpointRepository();
		$base_url = isset( $args['base_url'] ) && is_string( $args['base_url'] ) && '' !== $args['base_url']
			? untrailingslashit( $args['base_url'] )
			: $repo->resolved_base_url( $provider );

		$api_key = isset( $args['api_key'] ) && is_string( $args['api_key'] )
			? $args['api_key']
			: (string) $repo->get( $provider )->api_key;

		if ( '' === $api_key && empty( $args['allow_empty_key'] ) ) {
			$err = new WP_Error(
				'wpmind_api_key_missing',
				/* translators: %s: provider id */
				sprintf( __( '服务商 %s 未配置 API Key', 'wpmind' ), $provider )
			);
			return $this->error_result( $err, ErrorClass::AuthFailed );
		}

		$skip_record = ! empty( $args['skip_record'] );

		$url = str_starts_with( $path, 'http://' ) || str_starts_with( $path, 'https://' )
			? $path
			: trailingslashit( $base_url ) . ltrim( $path, '/' );

		$headers = array_merge(
			array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			is_array( $args['headers'] ?? null ) ? $args['headers'] : array()
		);

		$timeout = (int) ( $args['timeout'] ?? get_option( 'wpmind_request_timeout', 60 ) );
		$timeout = max( 1, min( 300, $timeout ) );

		$body = $args['body'] ?? null;
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
		}

		$request_args = array(
			'method'                => strtoupper( $method ),
			'headers'               => $headers,
			'timeout'               => $timeout,
			'_wpmind_skip_tracking' => true,
			'_wpmind_start_time'    => microtime( true ),
		);
		if ( null !== $body ) {
			$request_args['body'] = $body;
		}

		$start    = microtime( true );
		$response = wp_remote_request( $url, $request_args );
		$latency  = (int) ( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_classified = ErrorClassifier::classify( $response );
			$wp_error   = new WP_Error(
				'wpmind_request_failed',
				/* translators: %s: error message */
				sprintf( __( '请求失败: %s', 'wpmind' ), $error_classified['message'] ),
				array(
					'status'      => 0,
					'error_class' => $error_classified['class']->value,
					'body'        => $error_classified['redacted_body'],
				)
			);
			$this->record( $provider, false, $latency, $error_classified['class'], $error_classified['retry_after'], $skip_record );
			return array(
				'ok'          => false,
				'status'      => 0,
				'body'        => '',
				'data'        => null,
				'headers'     => array(),
				'latency_ms'  => $latency,
				'error_class' => $error_classified['class'],
				'retry_after' => $error_classified['retry_after'],
				'wp_error'    => $wp_error,
				'response'    => $response,
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$headers = array();
		$raw_h   = wp_remote_retrieve_headers( $response );
		if ( is_array( $raw_h ) ) {
			$headers = $raw_h;
		} elseif ( is_object( $raw_h ) ) {
			/** @var array<string, mixed> $headers */
			$headers = (array) $raw_h;
		}

		if ( $status < 200 || $status >= 300 ) {
			$error_classified = ErrorClassifier::classify( $response, $status, $raw, $headers );
			$wp_error   = new WP_Error(
				'wpmind_api_error',
				/* translators: 1: HTTP status 2: error message */
				sprintf( __( 'API 错误 (%1$d): %2$s', 'wpmind' ), $status, $error_classified['message'] ),
				array(
					'status'      => $status,
					'error_class' => $error_classified['class']->value,
					'body'        => $error_classified['redacted_body'],
					'retry_after' => $error_classified['retry_after'],
				)
			);
			$this->record( $provider, false, $latency, $error_classified['class'], $error_classified['retry_after'] );
			return array(
				'ok'          => false,
				'status'      => $status,
				'body'        => $error_classified['redacted_body'],
				'data'        => json_decode( $raw, true ),
				'headers'     => $headers,
				'latency_ms'  => $latency,
				'error_class' => $error_classified['class'],
				'retry_after' => $error_classified['retry_after'],
				'wp_error'    => $wp_error,
				'response'    => $response,
			);
		}

		$data = json_decode( $raw, true );
		$this->record( $provider, true, $latency, null, 0, $skip_record );

		$record_usage = ! isset( $args['record_usage'] ) || (bool) $args['record_usage'];
		if ( $record_usage && is_array( $data ) ) {
			$this->fire_usage( $provider, $data, (string) ( $args['model'] ?? '' ), $latency );
		}

		return array(
			'ok'          => true,
			'status'      => $status,
			'body'        => $raw,
			'data'        => is_array( $data ) ? $data : null,
			'headers'     => $headers,
			'latency_ms'  => $latency,
			'error_class' => null,
			'retry_after' => 0,
			'wp_error'    => null,
			'response'    => $response,
		);
	}

	/**
	 * @return array{ok:bool, status:int, body:string, data:?array, headers:array, latency_ms:int, error_class:?ErrorClass, retry_after:int, wp_error:?WP_Error, response:mixed}
	 */
	private function error_result( WP_Error $err, ErrorClass $error_class ): array {
		return array(
			'ok'          => false,
			'status'      => 0,
			'body'        => '',
			'data'        => null,
			'headers'     => array(),
			'latency_ms'  => 0,
			'error_class' => $error_class,
			'retry_after' => 0,
			'wp_error'    => $err,
			'response'    => $err,
		);
	}

	/**
	 * Record success/failure into FailoverManager unless $skip is true.
	 *
	 * @param string          $provider    Provider id.
	 * @param bool            $success     Whether the request succeeded.
	 * @param int             $latency     Latency in ms.
	 * @param ErrorClass|null $error_class Classified error or null.
	 * @param int             $retry_after Retry-After seconds.
	 * @param bool            $skip        When true, do not touch FailoverManager.
	 */
	private function record( string $provider, bool $success, int $latency, ?ErrorClass $error_class, int $retry_after, bool $skip = false ): void {
		if ( $skip ) {
			return;
		}
		if ( class_exists( FailoverManager::class ) ) {
			FailoverManager::instance()->record_result( $provider, $success, $latency, $error_class, $retry_after );
		}
		if ( ! $success && null !== $error_class && class_exists( Diagnostics::class ) ) {
			$entry = \WPMind\Provider\Health\ErrorCatalog::entry( $error_class, $provider );
			Diagnostics::push_error(
				array(
					'provider' => $provider,
					'class'    => $error_class->value,
					'status'   => 0,
					'message'  => $entry['message'],
					'at'       => time(),
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $data Response JSON.
	 */
	private function fire_usage( string $provider, array $data, string $model_hint, int $latency ): void {
		$usage = $data['usage'] ?? null;
		if ( ! is_array( $usage ) ) {
			return;
		}
		$input  = (int) ( $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0 );
		$output = (int) ( $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0 );
		$input  = max( 0, $input );
		$output = max( 0, $output );
		if ( 0 === $input && 0 === $output ) {
			return;
		}
		$model = (string) ( $data['model'] ?? $model_hint );
		if ( '' === $model ) {
			$model = 'unknown';
		}
		do_action( 'wpmind_usage_record', $provider, $model, $input, $output, $latency );
	}
}
