<?php
/**
 * Transient Rate Store
 *
 * Sliding-window rate limiter using WordPress transients as fallback.
 *
 * @package WPMind\Modules\ApiGateway\RateLimit
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\RateLimit;

/**
 * Class TransientRateStore
 *
 * Fallback rate limiter when Redis is unavailable.
 * Uses WordPress transients with a simple spin-lock for atomicity.
 */
final class TransientRateStore implements RateStoreInterface {

	/**
	 * Lock TTL in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TTL = 2;

	/**
	 * {@inheritDoc}
	 */
	public function consume( string $key, int $window_sec, int $cost, int $limit, string $rid, int $now ): RateStoreResult {
		$lock_key = 'wpmind_rl_lock_' . md5( $key );
		$reset    = $now + $window_sec;

		if ( ! $this->acquire_lock( $lock_key ) ) {
			error_log( '[WPMind] TransientRateStore: lock acquisition failed for ' . $key . ', failing open.' );
			return new RateStoreResult( true, $limit, $reset );
		}

		try {
			$entries       = $this->get_entries( $key );
			$entries       = $this->prune_expired( $entries, $now, $window_sec );
			$current_count = $this->sum_costs( $entries );

			if ( $current_count + $cost > $limit ) {
				$this->save_entries( $key, $entries, $window_sec );
				return new RateStoreResult( false, max( 0, $limit - $current_count ), $reset );
			}

			$entries[] = [
				'rid'  => $rid,
				'cost' => $cost,
				'time' => $now,
			];

			$this->save_entries( $key, $entries, $window_sec );

			return new RateStoreResult( true, max( 0, $limit - $current_count - $cost ), $reset );
		} finally {
			$this->release_lock( $lock_key );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function rollback( string $key, string $rid ): void {
		$lock_key = 'wpmind_rl_lock_' . md5( $key );

		if ( ! $this->acquire_lock( $lock_key ) ) {
			return;
		}

		try {
			$entries  = $this->get_entries( $key );
			$filtered = array_values(
				array_filter(
					$entries,
					static fn( array $entry ): bool => $entry['rid'] !== $rid
				)
			);

			if ( count( $filtered ) !== count( $entries ) ) {
				$this->save_entries( $key, $filtered, 120 );
			}
		} finally {
			$this->release_lock( $lock_key );
		}
	}

	/**
	 * Attempt to acquire a transient-based lock.
	 *
	 * @param string $lock_key Transient key for the lock.
	 * @return bool True if lock acquired.
	 */
	private function acquire_lock( string $lock_key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $lock_key, 1, 'wpmind_rate', self::LOCK_TTL );
		}

		global $wpdb;

		return (bool) $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			'_transient_' . $lock_key,
			time()
		) );
	}

	/**
	 * Release a transient-based lock.
	 *
	 * @param string $lock_key Transient key for the lock.
	 */
	private function release_lock( string $lock_key ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $lock_key, 'wpmind_rate' );
			return;
		}

		delete_transient( $lock_key );
	}

	/**
	 * Get stored rate-limit entries from a transient.
	 *
	 * @param string $key Bucket identifier.
	 * @return array<int, array{rid: string, cost: int, time: int}>
	 */
	private function get_entries( string $key ): array {
		$transient_key = 'wpmind_rl_' . md5( $key );
		$data          = get_transient( $transient_key );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Save rate-limit entries to a transient.
	 *
	 * @param string $key        Bucket identifier.
	 * @param array  $entries    Entry list.
	 * @param int    $window_sec TTL for the transient.
	 */
	private function save_entries( string $key, array $entries, int $window_sec ): void {
		$transient_key = 'wpmind_rl_' . md5( $key );
		set_transient( $transient_key, $entries, $window_sec * 2 );
	}

	/**
	 * Remove entries older than the sliding window.
	 *
	 * @param array $entries    Current entries.
	 * @param int   $now        Current timestamp.
	 * @param int   $window_sec Window size in seconds.
	 * @return array Pruned entries.
	 */
	private function prune_expired( array $entries, int $now, int $window_sec ): array {
		$cutoff = $now - $window_sec;

		return array_values(
			array_filter(
				$entries,
				static fn( array $entry ): bool => $entry['time'] > $cutoff
			)
		);
	}

	/**
	 * Sum the cost of all entries.
	 *
	 * @param array $entries Entry list.
	 * @return int Total cost.
	 */
	private function sum_costs( array $entries ): int {
		$total = 0;

		foreach ( $entries as $entry ) {
			$total += (int) $entry['cost'];
		}

		return $total;
	}
}
