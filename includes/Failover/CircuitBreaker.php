<?php
/**
 * Circuit Breaker - 熔断器
 *
 * 状态：closed / open / half_open / needs_attention / quota_exhausted
 *
 * @package WPMind
 * @since 1.5.0
 * @since 0.13.0 needs_attention / quota_exhausted + 半开探测锁
 */

declare(strict_types=1);

namespace WPMind\Failover;

use WPMind\Provider\Health\ErrorClass;

class CircuitBreaker {

	public const STATE_CLOSED          = 'closed';
	public const STATE_OPEN            = 'open';
	public const STATE_HALF_OPEN       = 'half_open';
	public const STATE_NEEDS_ATTENTION = 'needs_attention';
	public const STATE_QUOTA_EXHAUSTED = 'quota_exhausted';

	private const FAILURE_THRESHOLD  = 5;
	private const FAILURE_RATE       = 0.4;
	private const WINDOW_SIZE        = 60;
	private const RECOVERY_TIME      = 1200;
	private const HALF_OPEN_REQUESTS = 3;
	private const MIN_REQUESTS       = 10;
	private const DEFAULT_QUOTA_COOLDOWN = 3600;
	private const RATE_LIMIT_CAP     = 60;
	private const WRITE_LOCK_TTL     = 5;

	private string $providerId;
	private string $transientKey;

	public function __construct( string $providerId ) {
		$this->providerId   = $providerId;
		$this->transientKey = 'wpmind_cb_' . sanitize_key( $providerId );
	}

	public function get_state(): string {
		$data = $this->get_data();
		return $data['state'] ?? self::STATE_CLOSED;
	}

	/**
	 * 是否允许请求通过。
	 *
	 * @param bool $allowTransition 是否允许 open→half_open.
	 */
	public function is_available( bool $allowTransition = true ): bool {
		$state = $this->get_state();

		if ( self::STATE_NEEDS_ATTENTION === $state ) {
			return false;
		}

		if ( self::STATE_QUOTA_EXHAUSTED === $state ) {
			$data = $this->get_data();
			$until = (int) ( $data['cooldown_until'] ?? 0 );
			if ( $until > time() ) {
				return false;
			}
			// 冷却结束：只放 1 个探测（靠锁）.
			return $allowTransition ? $this->acquire_probe_lock() : false;
		}

		if ( self::STATE_CLOSED === $state ) {
			return true;
		}

		if ( self::STATE_OPEN === $state ) {
			if ( $this->should_transition_to_half_open() ) {
				if ( $allowTransition ) {
					$this->transition_to( self::STATE_HALF_OPEN );
					return $this->acquire_probe_lock();
				}
				return false;
			}
			return false;
		}

		if ( self::STATE_HALF_OPEN === $state ) {
			if ( ! $this->can_allow_half_open_request() ) {
				return false;
			}
			return $allowTransition ? $this->acquire_probe_lock() : false;
		}

		return false;
	}

	public function is_available_read_only(): bool {
		return $this->is_available( false );
	}

	/**
	 * 是否处于 rate_limited 冷却（链上挪末尾）。
	 */
	public function is_rate_limited_cooling(): bool {
		$data = $this->get_data();
		$until = (int) ( $data['rate_limit_until'] ?? 0 );
		return $until > time();
	}

	public function record_success(): void {
		$this->release_probe_lock();
		$data  = $this->get_data();
		$now   = time();
		$state = $data['state'] ?? self::STATE_CLOSED;

		if ( self::STATE_NEEDS_ATTENTION === $state || self::STATE_QUOTA_EXHAUSTED === $state ) {
			// 成功探测可清掉人工/额度状态.
			$this->transition_to( self::STATE_CLOSED );
			$data  = $this->get_data();
			$state = self::STATE_CLOSED;
		}

		if ( self::STATE_OPEN === $state && $this->should_transition_to_half_open() ) {
			$this->transition_to( self::STATE_HALF_OPEN );
			$data  = $this->get_data();
			$state = self::STATE_HALF_OPEN;
		}

		$data['requests'][] = array(
			'success' => true,
			'time'    => $now,
		);
		$data['requests']   = $this->filter_recent_requests( $data['requests'] ?? array(), $now );

		$data['successes']            = ( $data['successes'] ?? 0 ) + 1;
		$data['last_success']         = $now;
		$data['consecutive_failures'] = 0;
		unset( $data['rate_limit_until'], $data['cooldown_until'], $data['reason'] );

		if ( self::STATE_HALF_OPEN === $state ) {
			$data['half_open_successes'] = ( $data['half_open_successes'] ?? 0 ) + 1;
			if ( $data['half_open_successes'] >= self::HALF_OPEN_REQUESTS ) {
				$this->save_data( $data );
				$this->transition_to( self::STATE_CLOSED );
				return;
			}
		}

		$this->save_data( $data );
	}

	/**
	 * @param ErrorClass|null $error_class 可选错误分类，决定是否计入熔断 / 特殊状态.
	 */
	public function record_failure( ?ErrorClass $error_class = null ): void {
		$this->release_probe_lock();

		if ( ErrorClass::AuthFailed === $error_class ) {
			$this->mark_needs_attention( 'auth_failed' );
			return;
		}

		if ( ErrorClass::QuotaExhausted === $error_class ) {
			$cooldown = (int) apply_filters( 'wpmind_quota_exhausted_cooldown', self::DEFAULT_QUOTA_COOLDOWN, $this->providerId );
			$this->mark_quota_exhausted( max( 60, $cooldown ), 'quota_exhausted' );
			return;
		}

		if ( ErrorClass::RateLimited === $error_class ) {
			// 不计入熔断失败率；冷却由调用方 set_rate_limit_cooldown 写入.
			return;
		}

		if ( ErrorClass::ModelUnavailable === $error_class || ErrorClass::BadRequest === $error_class ) {
			return;
		}

		if ( null !== $error_class && ! $error_class->counts_toward_circuit() ) {
			return;
		}

		$data  = $this->get_data();
		$now   = time();
		$state = $data['state'] ?? self::STATE_CLOSED;

		if ( in_array( $state, array( self::STATE_NEEDS_ATTENTION, self::STATE_QUOTA_EXHAUSTED ), true ) ) {
			return;
		}

		if ( self::STATE_OPEN === $state && $this->should_transition_to_half_open() ) {
			$this->transition_to( self::STATE_HALF_OPEN );
			$data  = $this->get_data();
			$state = self::STATE_HALF_OPEN;
		}

		$data['requests'][] = array(
			'success' => false,
			'time'    => $now,
		);
		$data['requests']   = $this->filter_recent_requests( $data['requests'] ?? array(), $now );

		$data['failures']             = ( $data['failures'] ?? 0 ) + 1;
		$data['consecutive_failures'] = ( $data['consecutive_failures'] ?? 0 ) + 1;
		$data['last_failure']         = $now;

		if ( self::STATE_HALF_OPEN === $state ) {
			$data['half_open_failures'] = ( $data['half_open_failures'] ?? 0 ) + 1;
			$this->save_data( $data );
			$this->transition_to( self::STATE_OPEN );
			return;
		}

		if ( $this->should_trip( $data ) ) {
			$this->save_data( $data );
			$this->transition_to( self::STATE_OPEN );
			return;
		}

		$this->save_data( $data );
	}

	public function mark_needs_attention( string $reason = 'auth_failed' ): void {
		$data = $this->get_data();
		$data['state']           = self::STATE_NEEDS_ATTENTION;
		$data['reason']          = $reason;
		$data['since']           = time();
		$data['transitioned']    = time();
		unset( $data['cooldown_until'] );
		$this->save_data( $data );
	}

	public function mark_quota_exhausted( int $cooldown_seconds, string $reason = 'quota_exhausted' ): void {
		$data = $this->get_data();
		$data['state']           = self::STATE_QUOTA_EXHAUSTED;
		$data['reason']          = $reason;
		$data['since']           = time();
		$data['cooldown_until']  = time() + $cooldown_seconds;
		$data['transitioned']    = time();
		$this->save_data( $data );
	}

	public function set_rate_limit_cooldown( int $seconds ): void {
		$seconds = max( 0, min( self::RATE_LIMIT_CAP, $seconds ) );
		if ( $seconds <= 0 ) {
			return;
		}
		$data = $this->get_data();
		$data['rate_limit_until'] = time() + $seconds;
		$this->save_data( $data );
	}

	/**
	 * 手动测试连接 / 换 key 后恢复.
	 */
	public function clear_attention_states(): void {
		$state = $this->get_state();
		if ( in_array( $state, array( self::STATE_NEEDS_ATTENTION, self::STATE_QUOTA_EXHAUSTED, self::STATE_OPEN, self::STATE_HALF_OPEN ), true ) ) {
			$this->reset();
		}
	}

	public function reset(): void {
		$this->release_probe_lock();
		delete_transient( $this->transientKey );
	}

	public function get_status_details(): array {
		$data  = $this->get_data();
		$state = $data['state'] ?? self::STATE_CLOSED;

		return array(
			'provider_id'          => $this->providerId,
			'state'                => $state,
			'state_label'          => $this->get_state_label( $state ),
			'reason'               => $data['reason'] ?? null,
			'since'                => $data['since'] ?? null,
			'cooldown_until'       => $data['cooldown_until'] ?? null,
			'rate_limit_until'     => $data['rate_limit_until'] ?? null,
			'failures'             => $data['failures'] ?? 0,
			'successes'            => $data['successes'] ?? 0,
			'consecutive_failures' => $data['consecutive_failures'] ?? 0,
			'last_failure'         => $data['last_failure'] ?? null,
			'last_success'         => $data['last_success'] ?? null,
			'transitioned_at'      => $data['transitioned'] ?? null,
			'recovery_in'          => $this->get_recovery_time_remaining( $data ),
		);
	}

	/**
	 * 半开 / 额度探测锁：抢到才放行.
	 */
	public function acquire_probe_lock(): bool {
		$key = $this->probe_lock_key();
		$ttl = $this->probe_lock_ttl();
		if ( add_option( $key, time(), '', false ) ) {
			return true;
		}
		$locked_at = (int) get_option( $key, 0 );
		if ( $locked_at > 0 && ( time() - $locked_at ) <= $ttl ) {
			return false;
		}
		delete_option( $key );
		return add_option( $key, time(), '', false );
	}

	public function release_probe_lock(): void {
		delete_option( $this->probe_lock_key() );
	}

	private function probe_lock_key(): string {
		return 'wpmind_cb_probe_' . sanitize_key( $this->providerId );
	}

	private function probe_lock_ttl(): int {
		$hop = (int) apply_filters( 'wpmind_probe_lock_ttl', 40, $this->providerId );
		return max( 15, $hop );
	}

	private function should_trip( array $data ): bool {
		$consecutiveFailures = $data['consecutive_failures'] ?? 0;
		if ( $consecutiveFailures >= self::FAILURE_THRESHOLD ) {
			return true;
		}

		$requests = $data['requests'] ?? array();
		if ( count( $requests ) >= self::MIN_REQUESTS ) {
			$failures    = count( array_filter( $requests, fn( $r ) => ! $r['success'] ) );
			$failureRate = $failures / count( $requests );
			if ( $failureRate >= self::FAILURE_RATE ) {
				return true;
			}
		}

		return false;
	}

	private function filter_recent_requests( array $requests, int $now ): array {
		$cutoff = $now - self::WINDOW_SIZE;
		return array_values(
			array_filter(
				$requests,
				fn( $r ) => ( $r['time'] ?? 0 ) >= $cutoff
			)
		);
	}

	private function should_transition_to_half_open(): bool {
		$data         = $this->get_data();
		$transitioned = $data['transitioned'] ?? 0;
		return ( time() - $transitioned ) >= self::RECOVERY_TIME;
	}

	private function can_allow_half_open_request(): bool {
		$data             = $this->get_data();
		$halfOpenRequests = ( $data['half_open_successes'] ?? 0 ) + ( $data['half_open_failures'] ?? 0 );
		return $halfOpenRequests < self::HALF_OPEN_REQUESTS;
	}

	private function transition_to( string $newState ): void {
		$data = array(
			'state'                => $newState,
			'failures'             => 0,
			'successes'            => 0,
			'consecutive_failures' => 0,
			'transitioned'         => time(),
		);

		if ( self::STATE_HALF_OPEN === $newState ) {
			$data['half_open_successes'] = 0;
			$data['half_open_failures']  = 0;
		}

		$this->save_data( $data );
	}

	private function get_recovery_time_remaining( array $data ): ?int {
		$state = $data['state'] ?? self::STATE_CLOSED;
		if ( self::STATE_OPEN === $state ) {
			$transitioned = $data['transitioned'] ?? time();
			$remaining    = self::RECOVERY_TIME - ( time() - $transitioned );
			return max( 0, $remaining );
		}
		if ( self::STATE_QUOTA_EXHAUSTED === $state ) {
			$until = (int) ( $data['cooldown_until'] ?? 0 );
			return max( 0, $until - time() );
		}
		return null;
	}

	private function get_state_label( string $state ): string {
		return match ( $state ) {
			self::STATE_CLOSED          => __( '正常', 'wpmind' ),
			self::STATE_OPEN            => __( '熔断中', 'wpmind' ),
			self::STATE_HALF_OPEN       => __( '恢复测试', 'wpmind' ),
			self::STATE_NEEDS_ATTENTION => __( '需要处理', 'wpmind' ),
			self::STATE_QUOTA_EXHAUSTED => __( '额度不足', 'wpmind' ),
			default                     => __( '未知', 'wpmind' ),
		};
	}

	private function get_data(): array {
		$data = get_transient( $this->transientKey );
		return is_array( $data ) ? $data : array();
	}

	private function save_data( array $data ): void {
		$lock_key = 'wpmind_cb_wlock_' . sanitize_key( $this->providerId );
		if ( ! $this->try_acquire_option_lock( $lock_key, self::WRITE_LOCK_TTL ) ) {
			// 抢不到写锁：丢弃本次统计更新，不阻塞业务.
			return;
		}
		try {
			set_transient( $this->transientKey, $data, self::RECOVERY_TIME * 2 );
		} finally {
			delete_option( $lock_key );
		}
	}

	/**
	 * @phpstan-impure
	 */
	private function try_acquire_option_lock( string $lock_key, int $ttl ): bool {
		if ( add_option( $lock_key, time(), '', false ) ) {
			return true;
		}
		$locked_at = (int) get_option( $lock_key, 0 );
		if ( $locked_at > 0 && ( time() - $locked_at ) <= $ttl ) {
			return false;
		}
		delete_option( $lock_key );
		return (bool) add_option( $lock_key, time(), '', false );
	}
}
