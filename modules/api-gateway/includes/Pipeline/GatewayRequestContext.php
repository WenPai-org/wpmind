<?php
/**
 * Gateway Request Context
 *
 * Core DTO that flows through every pipeline stage.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

/**
 * Class GatewayRequestContext
 *
 * Immutable-ish value object carrying all state for a single
 * API gateway request through the middleware pipeline.
 */
final class GatewayRequestContext {

	private string $operation;
	private string $request_id;
	private \WP_REST_Request $rest_request;
	private ?object $auth_result              = null;
	private ?array $internal_payload          = null;
	private mixed $internal_result            = null;
	private ?\WP_REST_Response $rest_response = null;
	private ?\WP_Error $error                 = null;
	private ?\Throwable $exception            = null;
	private ?string $client_ip                = null;
	private array $response_headers           = [];
	private int $retry_after_sec              = 0;
	private float $start_time;

	private function __construct() {}

	/**
	 * Create context from a WP REST request.
	 *
	 * @param string           $operation    Operation type (e.g. 'chat.completions').
	 * @param \WP_REST_Request $request      Original REST request.
	 * @return self
	 */
	public static function from_rest_request( string $operation, \WP_REST_Request $request ): self {
		$ctx               = new self();
		$ctx->operation    = $operation;
		$ctx->rest_request = $request;
		$ctx->start_time   = microtime( true );

		// Generate UUID v4.
		$data            = random_bytes( 16 );
		$data[6]         = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8]         = chr( ord( $data[8] ) & 0x3f | 0x80 );
		$ctx->request_id = vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split( bin2hex( $data ), 4 )
		);

		return $ctx;
	}

	/**
	 * Get the operation type.
	 *
	 * @return string
	 */
	public function operation(): string {
		return $this->operation;
	}

	/**
	 * Get the unique request ID (UUID v4).
	 *
	 * @return string
	 */
	public function request_id(): string {
		return $this->request_id;
	}

	/**
	 * Get the original WP REST request.
	 *
	 * @return \WP_REST_Request
	 */
	public function rest_request(): \WP_REST_Request {
		return $this->rest_request;
	}

	/**
	 * Get the raw request body.
	 *
	 * @return string
	 */
	public function raw_body(): string {
		return $this->rest_request->get_body();
	}

	/**
	 * Get the key_id from auth result.
	 *
	 * @return string|null
	 */
	public function key_id(): ?string {
		return $this->auth_result?->key_id ?? null;
	}

	/**
	 * Set the resolved client IP address.
	 *
	 * @param string $ip Client IP address.
	 */
	public function set_client_ip( string $ip ): void {
		$this->client_ip = $ip;
	}

	/**
	 * Get the resolved client IP address.
	 *
	 * @return string|null
	 */
	public function client_ip(): ?string {
		return $this->client_ip;
	}

	/**
	 * Set the authentication result.
	 *
	 * @param object $result Auth result object.
	 */
	public function set_auth_result( object $result ): void {
		$this->auth_result = $result;
	}

	/**
	 * Get the authentication result.
	 *
	 * @return object|null
	 */
	public function auth_result(): ?object {
		return $this->auth_result;
	}

	/**
	 * Set the internal payload (WPMind format).
	 *
	 * @param array $payload Transformed payload.
	 */
	public function set_internal_payload( array $payload ): void {
		$this->internal_payload = $payload;
	}

	/**
	 * Get the internal payload.
	 *
	 * @return array|null
	 */
	public function get_internal_payload(): ?array {
		return $this->internal_payload;
	}

	/**
	 * Set the internal result from PublicAPI.
	 *
	 * @param mixed $result Result data.
	 */
	public function set_internal_result( mixed $result ): void {
		$this->internal_result = $result;
	}

	/**
	 * Get the internal result.
	 *
	 * @return mixed
	 */
	public function get_internal_result(): mixed {
		return $this->internal_result;
	}

	/**
	 * Set an error on the context.
	 *
	 * @param \WP_Error $error WordPress error.
	 */
	public function set_error( \WP_Error $error ): void {
		$this->error = $error;
	}

	/**
	 * Check if the context has an error.
	 *
	 * @return bool
	 */
	public function has_error(): bool {
		return $this->error !== null;
	}

	/**
	 * Get the error.
	 *
	 * @return \WP_Error|null
	 */
	public function error(): ?\WP_Error {
		return $this->error;
	}

	/**
	 * Set an exception on the context.
	 *
	 * @param \Throwable $e The exception.
	 */
	public function set_exception( \Throwable $e ): void {
		$this->exception = $e;
	}

	/**
	 * Check if the context has an exception.
	 *
	 * @return bool
	 */
	public function has_exception(): bool {
		return $this->exception !== null;
	}

	/**
	 * Get the exception.
	 *
	 * @return \Throwable|null
	 */
	public function exception(): ?\Throwable {
		return $this->exception;
	}

	/**
	 * Set a response header.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 */
	public function set_response_header( string $name, string $value ): void {
		$this->response_headers[ $name ] = $value;
	}

	/**
	 * Get all response headers.
	 *
	 * @return array<string, string>
	 */
	public function get_response_headers(): array {
		return $this->response_headers;
	}

	/**
	 * Set retry-after seconds for rate limiting.
	 *
	 * @param int $seconds Seconds to wait.
	 */
	public function set_retry_after( int $seconds ): void {
		$this->retry_after_sec = $seconds;
	}

	/**
	 * Get retry-after seconds.
	 *
	 * @return int
	 */
	public function retry_after_sec(): int {
		return $this->retry_after_sec;
	}

	/**
	 * Set the final REST response.
	 *
	 * @param \WP_REST_Response $response REST response.
	 */
	public function set_rest_response( \WP_REST_Response $response ): void {
		$this->rest_response = $response;
	}

	/**
	 * Build and return the final REST response.
	 *
	 * If a rest_response was explicitly set, returns it.
	 * If an error exists, converts it to a REST response.
	 * Otherwise builds a 200 response from internal_result.
	 *
	 * @return \WP_REST_Response
	 */
	public function to_rest_response(): \WP_REST_Response {
		if ( $this->rest_response !== null ) {
			$response = $this->rest_response;
		} elseif ( $this->error !== null ) {
			$data     = $this->error->get_error_data();
			$status   = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			$response = new \WP_REST_Response(
				[
					'error' => [
						'message' => $this->error->get_error_message(),
						'type'    => $this->error->get_error_code(),
					],
				],
				$status
			);
		} else {
			$response = new \WP_REST_Response( $this->internal_result, 200 );
		}

		// Apply collected headers.
		foreach ( $this->response_headers as $name => $value ) {
			$response->header( $name, $value );
		}

		// Always include request ID.
		$response->header( 'X-Request-Id', $this->request_id );

		return $response;
	}

	/**
	 * Get elapsed time in milliseconds since request start.
	 *
	 * @return int
	 */
	public function elapsed_ms(): int {
		return (int) round( ( microtime( true ) - $this->start_time ) * 1000 );
	}

	/**
	 * Check if this is a management route (e.g. status, models).
	 *
	 * Management routes may skip budget/quota checks.
	 *
	 * @return bool
	 */
	public function is_management_route(): bool {
		return in_array( $this->operation, [ 'status', 'models' ], true );
	}
}
