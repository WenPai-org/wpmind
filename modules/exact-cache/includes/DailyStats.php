<?php
/**
 * Daily Stats - 日维度缓存统计（7 天滚动）
 *
 * 通过 shutdown hook 批量写入，使用 retry-once transient lock 处理并发。
 *
 * @package WPMind\Modules\ExactCache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ExactCache;

final class DailyStats {

	private const OPTION_KEY = 'wpmind_exact_cache_daily_stats';
	private const LOCK_KEY   = 'wpmind_daily_stats_lock';
	private const MAX_DAYS   = 7;

	/** @var array<string, array{hits: int, misses: int, writes: int}> */
	private static array $pending = [];

	private static bool $shutdown_registered = false;

	public static function record_hit(): void {
		self::buffer( 'hits' );
	}

	public static function record_miss(): void {
		self::buffer( 'misses' );
	}

	public static function record_write(): void {
		self::buffer( 'writes' );
	}

	private static function buffer( string $metric ): void {
		$today = wp_date( 'Y-m-d' );
		if ( ! isset( self::$pending[ $today ] ) ) {
			self::$pending[ $today ] = [
				'hits'   => 0,
				'misses' => 0,
				'writes' => 0,
			];
		}
		++self::$pending[ $today ][ $metric ];

		if ( ! self::$shutdown_registered ) {
			add_action( 'shutdown', [ self::class, 'flush_pending' ] );
			self::$shutdown_registered = true;
		}
	}

	public static function flush_pending(): void {
		if ( empty( self::$pending ) ) {
			return;
		}

		// Retry-once lock: 首次失败后等 100ms 重试一次
		if ( ! self::acquire_lock() ) {
			usleep( 100000 ); // 100ms
			if ( ! self::acquire_lock() ) {
				// 仍然失败，接受丢失（统计非关键数据）
				return;
			}
		}

		$data = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		foreach ( self::$pending as $date => $metrics ) {
			if ( ! isset( $data[ $date ] ) ) {
				$data[ $date ] = [
					'hits'   => 0,
					'misses' => 0,
					'writes' => 0,
				];
			}
			$data[ $date ]['hits']   += $metrics['hits'];
			$data[ $date ]['misses'] += $metrics['misses'];
			$data[ $date ]['writes'] += $metrics['writes'];
		}

		// 滚动清理：只保留最近 7 天
		ksort( $data );
		while ( count( $data ) > self::MAX_DAYS ) {
			array_shift( $data );
		}

		update_option( self::OPTION_KEY, $data, false );
		delete_transient( self::LOCK_KEY );
		self::$pending = [];
	}

	private static function acquire_lock(): bool {
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}
		set_transient( self::LOCK_KEY, 1, 5 );
		return true;
	}

	/**
	 * 获取最近 7 天的统计数据（无数据的天填 0）
	 *
	 * @return array<string, array{hits: int, misses: int, writes: int}>
	 */
	public static function get_daily_data(): array {
		$data = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $data ) ) {
			return [];
		}

		// 补齐最近 7 天
		$result = [];
		for ( $i = 6; $i >= 0; $i-- ) {
			$date            = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$result[ $date ] = $data[ $date ] ?? [
				'hits'   => 0,
				'misses' => 0,
				'writes' => 0,
			];
		}
		return $result;
	}

	public static function reset(): void {
		delete_option( self::OPTION_KEY );
	}
}
