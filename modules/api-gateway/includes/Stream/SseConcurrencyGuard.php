<?php
/**
 * SSE Concurrency Guard
 *
 * Controls concurrent SSE connections per API key and globally
 * using WordPress transients for slot tracking.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

/**
 * Class SseConcurrencyGuard
 *
 * Manages a finite pool of SSE connection slots to prevent
 * resource exhaustion. Slots are tracked via transients with
 * TTL-based expiry for automatic cleanup of stale connections.
 */
final class SseConcurrencyGuard {

	/**
	 * Transient key for global SSE slot list.
	 *
	 * @var string
	 */
	private const GLOBAL_SLOTS_KEY = 'wpmind_sse_global_slots';

	/**
	 * Transient key prefix for per-key SSE slot lists.
	 *
	 * @var string
	 */
	private const KEY_SLOTS_PREFIX = 'wpmind_sse_key_';

	/**
	 * Lock transient prefix for atomic operations.
	 *
	 * @var string
	 */
	private const LOCK_PREFIX = 'wpmind_sse_lock_';

	/**
	 * Lock timeout in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 5;

	/**
	 * Attempt to acquire an SSE concurrency slot.
	 *
	 * Checks both global and per-key limits. On success returns an
	 * SseSlot; on failure returns a WP_Error with HTTP 429 status.
	 *
	 * @param string $key_id        API key identifier.
	 * @param string $request_id    Unique request ID.
	 * @param int    $per_key_limit Maximum concurrent streams for this key.
	 * @param int    $ttl           Slot TTL in seconds (default 120).
	 * @return SseSlot|\WP_Error Acquired slot or error.
	 */
	public function acquire_slot( string $key_id, string $request_id, int $per_key_limit, int $ttl = 120 ): SseSlot|\WP_Error {
		$global_limit = (int) get_option( 'wpmind_gateway_sse_global_limit', 20 );
		$slot_key     = $key_id . ':' . $request_id;

		if ( ! $this->acquire_lock( 'global' ) ) {
			return new \WP_Error(
				'sse_lock_timeout',
				__( 'Could not acquire concurrency lock. Please retry.', 'wpmind' ),
				[ 'status' => 503 ]
			);
		}

		try {
			// Check global limit.
			$global_slots = $this->get_slots( self::GLOBAL_SLOTS_KEY );
			$global_slots = $this->purge_expired( $global_slots );

			if ( count( $global_slots ) >= $global_limit ) {
				return new \WP_Error(
					'sse_concurrency_exceeded',
					sprintf(
						/* translators: %d: global SSE connection limit */
						__( 'Global SSE connection limit (%d) reached. Please retry later.', 'wpmind' ),
						$global_limit
					),
					[ 'status' => 429 ]
				);
			}

			// Check per-key limit.
			$key_transient = self::KEY_SLOTS_PREFIX . $key_id . '_slots';
			$key_slots     = $this->get_slots( $key_transient );
			$key_slots     = $this->purge_expired( $key_slots );

			if ( count( $key_slots ) >= $per_key_limit ) {
				return new \WP_Error(
					'sse_concurrency_exceeded',
					sprintf(
						/* translators: %d: per-key SSE connection limit */
						__( 'Per-key SSE connection limit (%d) reached. Please retry later.', 'wpmind' ),
						$per_key_limit
					),
					[ 'status' => 429 ]
				);
			}

			// Register the slot.
			$now = time();

			$global_slots[ $slot_key ] = $now + $ttl;
			set_transient( self::GLOBAL_SLOTS_KEY, $global_slots, $ttl + 60 );

			$key_slots[ $slot_key ] = $now + $ttl;
			set_transient( $key_transient, $key_slots, $ttl + 60 );
		} finally {
			$this->release_lock( 'global' );
		}

		return new SseSlot( $key_id, $request_id, $slot_key );
	}

	/**
	 * Refresh the TTL on an active slot to prevent expiry.
	 *
	 * Should be called periodically during long-running streams.
	 *
	 * @param SseSlot $slot Active slot to heartbeat.
	 * @param int     $ttl  New TTL in seconds (default 120).
	 */
	public function heartbeat_slot( SseSlot $slot, int $ttl = 120 ): void {
		$now = time();

		$global_slots = $this->get_slots( self::GLOBAL_SLOTS_KEY );
		if ( isset( $global_slots[ $slot->slot_key ] ) ) {
			$global_slots[ $slot->slot_key ] = $now + $ttl;
			set_transient( self::GLOBAL_SLOTS_KEY, $global_slots, $ttl + 60 );
		}

		$key_transient = self::KEY_SLOTS_PREFIX . $slot->key_id . '_slots';
		$key_slots     = $this->get_slots( $key_transient );
		if ( isset( $key_slots[ $slot->slot_key ] ) ) {
			$key_slots[ $slot->slot_key ] = $now + $ttl;
			set_transient( $key_transient, $key_slots, $ttl + 60 );
		}
	}

	/**
	 * Release an SSE slot, freeing it for other connections.
	 *
	 * @param SseSlot $slot Slot to release.
	 */
	public function release_slot( SseSlot $slot ): void {
		$global_slots = $this->get_slots( self::GLOBAL_SLOTS_KEY );
		unset( $global_slots[ $slot->slot_key ] );

		if ( empty( $global_slots ) ) {
			delete_transient( self::GLOBAL_SLOTS_KEY );
		} else {
			set_transient( self::GLOBAL_SLOTS_KEY, $global_slots, 180 );
		}

		$key_transient = self::KEY_SLOTS_PREFIX . $slot->key_id . '_slots';
		$key_slots     = $this->get_slots( $key_transient );
		unset( $key_slots[ $slot->slot_key ] );

		if ( empty( $key_slots ) ) {
			delete_transient( $key_transient );
		} else {
			set_transient( $key_transient, $key_slots, 180 );
		}
	}

	/**
	 * Get slot array from a transient.
	 *
	 * @param string $transient_key Transient key.
	 * @return array<string, int> Map of slot_key => expiry timestamp.
	 */
	private function get_slots( string $transient_key ): array {
		$slots = get_transient( $transient_key );

		return is_array( $slots ) ? $slots : [];
	}

	/**
	 * Remove expired slots from a slot array.
	 *
	 * @param array<string, int> $slots Map of slot_key => expiry timestamp.
	 * @return array<string, int> Filtered slots with only active entries.
	 */
	private function purge_expired( array $slots ): array {
		$now = time();

		return array_filter(
			$slots,
			static fn( int $expiry ): bool => $expiry > $now
		);
	}

	/**
	 * Acquire a simple transient-based lock.
	 *
	 * @param string $name Lock name.
	 * @return bool True if lock acquired.
	 */
	private function acquire_lock( string $name ): bool {
		$lock_key  = self::LOCK_PREFIX . $name;
		$attempts  = 0;
		$max_tries = 10;

		while ( $attempts < $max_tries ) {
			// set_transient returns false if the key already exists when
			// using an external object cache. For the DB-based default,
			// we use a get-then-set pattern.
			$existing = get_transient( $lock_key );

			if ( $existing === false ) {
				set_transient( $lock_key, time(), self::LOCK_TIMEOUT );
				return true;
			}

			// If the lock is stale (older than timeout), force acquire.
			if ( is_numeric( $existing ) && ( time() - (int) $existing ) > self::LOCK_TIMEOUT ) {
				set_transient( $lock_key, time(), self::LOCK_TIMEOUT );
				return true;
			}

			usleep( 50000 ); // 50ms.
			++$attempts;
		}

		return false;
	}

	/**
	 * Release a transient-based lock.
	 *
	 * @param string $name Lock name.
	 */
	private function release_lock( string $name ): void {
		delete_transient( self::LOCK_PREFIX . $name );
	}
}
