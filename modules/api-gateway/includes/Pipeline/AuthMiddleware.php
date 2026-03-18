<?php
/**
 * Auth Middleware
 *
 * Pipeline stage that authenticates API gateway requests.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

use WPMind\Modules\ApiGateway\Auth\ApiKeyManager;

/**
 * Class AuthMiddleware
 *
 * Authenticates requests via Bearer API key (for API endpoints)
 * or Cookie+Nonce / Application Password (for management endpoints).
 */
final class AuthMiddleware implements GatewayStageInterface {

	/**
	 * Operation prefixes that require Bearer API key authentication.
	 *
	 * @var array<int, string>
	 */
	private const API_OPERATION_PREFIXES = [
		'chat.',
		'embeddings',
		'responses',
		'models',
		'model_detail',
		'status',
	];

	/**
	 * {@inheritDoc}
	 */
	public function process( GatewayRequestContext $context ): void {
		if ( $this->is_api_operation( $context->operation() ) ) {
			$this->authenticate_api_request( $context );
			return;
		}

		$this->authenticate_management_request( $context );
	}

	/**
	 * Authenticate an API endpoint request via Bearer API key.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function authenticate_api_request( GatewayRequestContext $context ): void {
		$auth_header = $context->rest_request()->get_header( 'authorization' );

		if ( empty( $auth_header ) ) {
			$context->set_error(
				new \WP_Error(
					'missing_auth_header',
					'Missing Authorization header.',
					[ 'status' => 401 ]
				)
			);
			return;
		}

		$client_ip = $this->get_client_ip();
		$result    = ApiKeyManager::authenticate_bearer_header( $auth_header, $client_ip );

		if ( is_wp_error( $result ) ) {
			$context->set_error( $result );
			return;
		}

		$context->set_client_ip( $client_ip );
		$context->set_auth_result( $result );
	}

	/**
	 * Authenticate a management endpoint request via Cookie+Nonce or Application Password.
	 *
	 * @param GatewayRequestContext $context Request context.
	 */
	private function authenticate_management_request( GatewayRequestContext $context ): void {
		if ( ! is_user_logged_in() ) {
			$context->set_error(
				new \WP_Error(
					'not_authenticated',
					'Authentication required.',
					[ 'status' => 401 ]
				)
			);
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$context->set_error(
				new \WP_Error(
					'forbidden',
					'Insufficient permissions.',
					[ 'status' => 403 ]
				)
			);
		}
	}

	/**
	 * Check if the operation is an API endpoint (requires Bearer key).
	 *
	 * @param string $operation Operation identifier.
	 * @return bool
	 */
	private function is_api_operation( string $operation ): bool {
		foreach ( self::API_OPERATION_PREFIXES as $prefix ) {
			if ( str_starts_with( $operation, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine the client IP address from request headers.
	 *
	 * Only trusts proxy headers (X-Forwarded-For, X-Real-IP) when
	 * REMOTE_ADDR matches a configured trusted proxy. Otherwise
	 * falls back to REMOTE_ADDR to prevent IP spoofing.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		if ( $remote_addr === '' ) {
			return '127.0.0.1';
		}

		$trusted_proxies = (array) apply_filters( 'wpmind_gateway_trusted_proxies', [ '127.0.0.1', '::1' ] );

		if ( in_array( $remote_addr, $trusted_proxies, true ) ) {
			$proxy_headers = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ];

			foreach ( $proxy_headers as $header ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ?? '' ) );

				if ( $value === '' ) {
					continue;
				}

				if ( $header === 'HTTP_X_FORWARDED_FOR' ) {
					$parts = explode( ',', $value );
					$value = trim( $parts[0] );
				}

				if ( filter_var( $value, FILTER_VALIDATE_IP ) !== false ) {
					return $value;
				}
			}
		}

		if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) !== false ) {
			return $remote_addr;
		}

		return '127.0.0.1';
	}
}
