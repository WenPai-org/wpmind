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
	 * Checks proxy headers in order of trust, falling back to REMOTE_ADDR.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$headers = [
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ?? '' ) );

			if ( $value === '' ) {
				continue;
			}

			// X-Forwarded-For may contain multiple IPs; take the first.
			if ( $header === 'HTTP_X_FORWARDED_FOR' ) {
				$parts = explode( ',', $value );
				$value = trim( $parts[0] );
			}

			if ( filter_var( $value, FILTER_VALIDATE_IP ) !== false ) {
				return $value;
			}
		}

		return '127.0.0.1';
	}
}