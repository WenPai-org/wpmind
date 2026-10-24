<?php
/**
 * Provider 请求错误分类。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Health;

/**
 * 错误分类枚举（PR4）。
 */
enum ErrorClass: string {
	case AuthFailed       = 'auth_failed';
	case QuotaExhausted   = 'quota_exhausted';
	case RateLimited      = 'rate_limited';
	case Timeout          = 'timeout';
	case Network          = 'network';
	case ServerError      = 'server_error';
	case ModelUnavailable = 'model_unavailable';
	case BadRequest       = 'bad_request';

	/**
	 * 是否应计入熔断失败率。
	 */
	public function counts_toward_circuit(): bool {
		return match ( $this ) {
			self::Timeout, self::Network, self::ServerError => true,
			default => false,
		};
	}

	/**
	 * 是否允许在同一家再试一次（在时间预算内）。
	 */
	public function may_retry_same_provider(): bool {
		return match ( $this ) {
			self::ServerError, self::RateLimited => true,
			default => false,
		};
	}

	/**
	 * 是否应立刻换下一家。
	 */
	public function should_failover(): bool {
		return match ( $this ) {
			self::BadRequest => false,
			default => true,
		};
	}
}
