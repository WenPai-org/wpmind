<?php
/**
 * Failover Manager - 故障转移管理器
 *
 * 明确顺序回退：以 wpmind_failover_order 为唯一顺序来源，默认不按健康分重排。
 *
 * @package WPMind
 * @since 1.5.0
 * @since 0.13.0 explicit order + error-class aware recording
 */

declare(strict_types=1);

namespace WPMind\Failover;

use WPMind\Provider\Catalog\ModelCatalog;
use WPMind\Provider\Health\ErrorClass;

class FailoverManager {

	private static ?FailoverManager $instance = null;

	/** @var array<string, CircuitBreaker> */
	private array $circuitBreakers = array();

	/** @var array<string, array<string, mixed>> */
	private array $providers = array();

	public static function instance(): FailoverManager {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( function_exists( 'WPMind\\wpmind' ) ) {
			$endpoints = \WPMind\wpmind()->get_custom_endpoints();
			foreach ( $endpoints as $id => $config ) {
				if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
					$this->providers[ $id ]       = $config;
					$this->circuitBreakers[ $id ] = new CircuitBreaker( $id );
				}
			}
		}
	}

	/**
	 * 选择最佳可用 Provider（可选健康分策略，默认用明确顺序链首）。
	 *
	 * @param string|null $preferredProvider 首选 Provider ID
	 * @param bool        $by_health         是否按健康分（路由策略 D4，默认 false）
	 */
	public function select_provider( ?string $preferredProvider = null, bool $by_health = false ): ?string {
		if ( $by_health ) {
			$available = $this->get_available_providers();
			if ( empty( $available ) ) {
				return null;
			}
			if ( $preferredProvider && in_array( $preferredProvider, $available, true ) ) {
				return $preferredProvider;
			}
			usort(
				$available,
				function ( $a, $b ) {
					$scoreA = ProviderHealthTracker::get_health_score( $a );
					$scoreB = ProviderHealthTracker::get_health_score( $b );
					if ( $scoreA === $scoreB ) {
						return ProviderHealthTracker::get_average_latency( $a ) - ProviderHealthTracker::get_average_latency( $b );
					}
					return $scoreB - $scoreA;
				}
			);
			return $available[0];
		}

		$chain = $this->get_failover_chain( $preferredProvider );
		return $chain[0] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public function get_available_providers(): array {
		$available = array();
		foreach ( $this->circuitBreakers as $providerId => $breaker ) {
			if ( $breaker->is_available() ) {
				$available[] = $providerId;
			}
		}
		return $available;
	}

	/**
	 * 明确顺序回退链。
	 *
	 * @param string|null $preferredProvider 首选（可用则置前）
	 * @param string|null $capability        所需能力（vision/embeddings/tools/json/text），null 不过滤
	 * @return list<string>
	 */
	public function get_failover_chain( ?string $preferredProvider = null, ?string $capability = null ): array {
		$order = $this->get_explicit_order();

		// 顺序中没有的已启用 provider 追加到末尾，保证不丢家.
		foreach ( array_keys( $this->providers ) as $id ) {
			if ( ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}

		$chain           = array();
		$rate_limited    = array();

		foreach ( $order as $providerId ) {
			if ( ! isset( $this->providers[ $providerId ] ) ) {
				continue;
			}
			$config = $this->providers[ $providerId ];
			if ( empty( $config['enabled'] ) || empty( $config['api_key'] ) ) {
				continue;
			}

			if ( null !== $capability && ! $this->provider_supports_capability( $providerId, $capability ) ) {
				continue;
			}

			$breaker = $this->circuitBreakers[ $providerId ] ?? new CircuitBreaker( $providerId );
			$this->circuitBreakers[ $providerId ] = $breaker;

			$state = $breaker->get_state();
			if ( CircuitBreaker::STATE_NEEDS_ATTENTION === $state ) {
				continue;
			}
			if ( CircuitBreaker::STATE_QUOTA_EXHAUSTED === $state ) {
				$details = $breaker->get_status_details();
				$until   = (int) ( $details['cooldown_until'] ?? 0 );
				if ( $until > time() ) {
					continue;
				}
			}
			if ( CircuitBreaker::STATE_OPEN === $state && ! $breaker->is_available_read_only() ) {
				continue;
			}
			if ( ! $breaker->is_available_read_only() && CircuitBreaker::STATE_HALF_OPEN !== $state && CircuitBreaker::STATE_QUOTA_EXHAUSTED !== $state ) {
				// half_open / quota cooling-finished still need probe lock at request time.
				if ( CircuitBreaker::STATE_CLOSED !== $state ) {
					continue;
				}
			}

			if ( $breaker->is_rate_limited_cooling() ) {
				$rate_limited[] = $providerId;
				continue;
			}

			$chain[] = $providerId;
		}

		foreach ( $rate_limited as $id ) {
			if ( ! in_array( $id, $chain, true ) ) {
				$chain[] = $id;
			}
		}

		if ( $preferredProvider && in_array( $preferredProvider, $chain, true ) ) {
			$chain = array_values( array_diff( $chain, array( $preferredProvider ) ) );
			array_unshift( $chain, $preferredProvider );
		}

		return $chain;
	}

	/**
	 * @return list<string>
	 */
	private function get_explicit_order(): array {
		$order = get_option( 'wpmind_failover_order', array() );
		if ( is_array( $order ) && array() !== $order ) {
			return array_values( array_filter( $order, 'is_string' ) );
		}

		if ( class_exists( '\\WPMind\\Routing\\IntelligentRouter' ) ) {
			$router = \WPMind\Routing\IntelligentRouter::instance();
			$manual = $router->get_manual_priority();
			if ( ! empty( $manual ) ) {
				return $manual;
			}
		}

		$default = (string) get_option( 'wpmind_default_provider', '' );
		$ids     = array_keys( $this->providers );
		if ( '' !== $default && in_array( $default, $ids, true ) ) {
			$ids = array_values( array_diff( $ids, array( $default ) ) );
			array_unshift( $ids, $default );
		}
		return $ids;
	}

	private function provider_supports_capability( string $providerId, string $capability ): bool {
		if ( ! class_exists( ModelCatalog::class ) ) {
			return true;
		}
		$models = ModelCatalog::instance()->for_provider( $providerId, $capability, false );
		return array() !== $models;
	}

	/**
	 * @param string          $providerId Provider ID
	 * @param bool            $success    是否成功
	 * @param int             $latencyMs  延迟（毫秒）
	 * @param ErrorClass|null $errorClass 错误分类
	 * @param int             $retryAfter rate_limited 的 Retry-After 秒数
	 */
	public function record_result( string $providerId, bool $success, int $latencyMs = 0, ?ErrorClass $errorClass = null, int $retryAfter = 0 ): void {
		if ( ! isset( $this->circuitBreakers[ $providerId ] ) ) {
			$this->circuitBreakers[ $providerId ] = new CircuitBreaker( $providerId );
		}
		$breaker = $this->circuitBreakers[ $providerId ];

		if ( $success ) {
			$breaker->record_success();
		} else {
			if ( ErrorClass::RateLimited === $errorClass && $retryAfter > 0 ) {
				$breaker->set_rate_limit_cooldown( $retryAfter );
			}
			$breaker->record_failure( $errorClass );
		}

		ProviderHealthTracker::record( $providerId, $success, $latencyMs );
	}

	public function get_status_summary(): array {
		$summary = array();

		foreach ( $this->circuitBreakers as $providerId => $breaker ) {
			$cbStatus     = $breaker->get_status_details();
			$healthStatus = ProviderHealthTracker::get_provider_status( $providerId );

			$summary[ $providerId ] = array(
				'name'         => $this->providers[ $providerId ]['name'] ?? $providerId,
				'display_name' => $this->providers[ $providerId ]['display_name'] ?? $providerId,
				'state'        => $cbStatus['state'],
				'state_label'  => $cbStatus['state_label'],
				'reason'       => $cbStatus['reason'] ?? null,
				'available'    => $breaker->is_available_read_only(),
				'health_score' => $healthStatus['health_score'],
				'avg_latency'  => $healthStatus['avg_latency'],
				'success_rate' => $healthStatus['success_rate'],
				'recovery_in'  => $cbStatus['recovery_in'],
			);
		}

		return $summary;
	}

	public function has_available_provider(): bool {
		return ! empty( $this->get_available_providers() );
	}

	public function reset_provider( string $providerId ): void {
		if ( isset( $this->circuitBreakers[ $providerId ] ) ) {
			$this->circuitBreakers[ $providerId ]->reset();
		}
		ProviderHealthTracker::clear_provider( $providerId );
	}

	public function reset_all(): void {
		foreach ( $this->circuitBreakers as $breaker ) {
			$breaker->reset();
		}
		ProviderHealthTracker::clear_all();
	}

	public function get_circuit_breaker( string $providerId ): ?CircuitBreaker {
		return $this->circuitBreakers[ $providerId ] ?? null;
	}

	public function refresh(): void {
		self::$instance = null;
		self::instance();
	}
}
