<?php
/**
 * Redis Rate Store
 *
 * Sliding-window rate limiter backed by Redis sorted sets.
 *
 * @package WPMind\Modules\ApiGateway\RateLimit
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\RateLimit;

/**
 * Class RedisRateStore
 *
 * Uses a Lua script for atomic sliding-window rate limiting via Redis.
 * Requires a Redis object cache drop-in (e.g. object-cache.php with Redis).
 */
final class RedisRateStore implements RateStoreInterface {

	private \Redis $redis;

	/**
	 * Lua script for atomic sliding-window consume.
	 *
	 * KEYS[1] = sorted set key
	 * ARGV[1] = window start (now - window_sec)
	 * ARGV[2] = now (score for new entry)
	 * ARGV[3] = member value "{rid}:{cost}"
	 * ARGV[4] = limit
	 * ARGV[5] = TTL in seconds
	 * ARGV[6] = cost
	 *
	 * Members are stored as "{rid}:{cost}" so we can sum actual costs.
	 * Returns: { allowed (0|1), remaining, reset_epoch }
	 */
	private const LUA_CONSUME = <<<'LUA'
redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', ARGV[1])
local members = redis.call('ZRANGE', KEYS[1], 0, -1)
local used = 0
for _, m in ipairs(members) do
    local c = tonumber(string.match(m, ':(%d+)$')) or 1
    used = used + c
end
local limit = tonumber(ARGV[4])
local cost  = tonumber(ARGV[6])
if used + cost <= limit then
    redis.call('ZADD', KEYS[1], ARGV[2], ARGV[3])
    redis.call('EXPIRE', KEYS[1], tonumber(ARGV[5]))
    return {1, limit - used - cost, tonumber(ARGV[2]) + tonumber(ARGV[5])}
end
return {0, limit - used, tonumber(ARGV[2]) + tonumber(ARGV[5])}
LUA;

	public function __construct() {
		$this->redis = $this->get_redis_instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function consume( string $key, int $window_sec, int $cost, int $limit, string $rid, int $now ): RateStoreResult {
		if ( str_contains( $rid, ':' ) ) {
			throw new \InvalidArgumentException( 'Request ID must not contain colons.' );
		}

		$window_start = $now - $window_sec;
		$member       = "{$rid}:{$cost}";
		$ttl          = $window_sec * 2;

		/** @var array $result */
		$result = $this->redis->eval(
			self::LUA_CONSUME,
			[ $key, (string) $window_start, (string) $now, $member, (string) $limit, (string) $ttl, (string) $cost ],
			1
		);

		if ( ! is_array( $result ) || count( $result ) < 3 ) {
			return new RateStoreResult( true, $limit, $now + $window_sec );
		}

		return new RateStoreResult(
			(bool) $result[0],
			max( 0, (int) $result[1] ),
			(int) $result[2]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function rollback( string $key, string $rid ): void {
		$iterator = null;

		// Scan sorted set members to find entries matching the request ID prefix.
		while ( $members = $this->redis->zScan( $key, $iterator, "{$rid}:*", 100 ) ) {
			foreach ( array_keys( $members ) as $member ) {
				$this->redis->zRem( $key, $member );
			}

			if ( $iterator === 0 ) {
				break;
			}
		}
	}

	/**
	 * Obtain the underlying Redis instance from the WordPress object cache.
	 *
	 * @return \Redis
	 * @throws \RuntimeException If Redis is not available.
	 */
	private function get_redis_instance(): \Redis {
		global $wp_object_cache;

		// Try common Redis object cache drop-in patterns.
		if ( isset( $wp_object_cache->redis ) && $wp_object_cache->redis instanceof \Redis ) {
			return $wp_object_cache->redis;
		}

		if ( method_exists( $wp_object_cache, 'get_redis' ) ) {
			$redis = $wp_object_cache->get_redis();
			if ( $redis instanceof \Redis ) {
				return $redis;
			}
		}

		throw new \RuntimeException( 'Redis object cache is not available.' );
	}
}
