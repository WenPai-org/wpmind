<?php
/**
 * Provider Health Tracker - Provider 健康状态追踪
 *
 * 记录每个 Provider 的请求历史和健康分数
 *
 * @package WPMind
 * @since 1.5.0
 */

declare(strict_types=1);

namespace WPMind\Failover;

class ProviderHealthTracker {

	private const TRANSIENT_KEY = 'wpmind_provider_health';
	private const HISTORY_SIZE  = 20;   // 保留最近 20 次请求记录
	private const CACHE_TTL     = 3600; // 缓存 1 小时

	/**
	 * 记录请求结果
	 *
	 * @param string $providerId Provider ID
	 * @param bool   $success    是否成功
	 * @param int    $latencyMs  延迟（毫秒）
	 */
	public static function record( string $providerId, bool $success, int $latencyMs = 0 ): void {
		$health = self::get_all_health();

		if ( ! isset( $health[ $providerId ] ) ) {
			$health[ $providerId ] = [
				'history'     => [],
				'total'       => 0,
				'failures'    => 0,
				'avg_latency' => 0,
			];
		}

		$provider = &$health[ $providerId ];

		// 添加到历史记录
		$provider['history'][] = [
			'success' => $success,
			'latency' => $latencyMs,
			'time'    => time(),
		];

		// 保持历史记录大小
		if ( count( $provider['history'] ) > self::HISTORY_SIZE ) {
			array_shift( $provider['history'] );
		}

		// 更新统计
		++$provider['total'];
		if ( ! $success ) {
			++$provider['failures'];
		}

		// 计算平均延迟（只计算成功的请求）
		$successfulLatencies = [];
		foreach ( $provider['history'] as $record ) {
			if ( $record['success'] ) {
				$successfulLatencies[] = $record['latency'];
			}
		}

		if ( ! empty( $successfulLatencies ) ) {
			$provider['avg_latency'] = (int) round(
				array_sum( $successfulLatencies ) / count( $successfulLatencies )
			);
		}

		$provider['last_updated'] = time();

		set_transient( self::TRANSIENT_KEY, $health, self::CACHE_TTL );
	}

	/**
	 * 获取 Provider 健康分数 (0-100)
	 *
	 * 分数计算：
	 * - 基于最近 N 次请求的成功率
	 * - 无数据时返回 100（假设健康）
	 *
	 * @param string $providerId Provider ID
	 * @return int 健康分数
	 */
	public static function get_health_score( string $providerId ): int {
		$health = self::get_all_health();

		if ( ! isset( $health[ $providerId ] ) || empty( $health[ $providerId ]['history'] ) ) {
			return 100;
		}

		$history         = $health[ $providerId ]['history'];
		$recentSuccesses = array_filter( $history, fn( $h ) => $h['success'] );
		$successRate     = count( $recentSuccesses ) / count( $history );

		return (int) round( $successRate * 100 );
	}

	/**
	 * 获取 Provider 平均延迟
	 *
	 * @param string $providerId Provider ID
	 * @return int 平均延迟（毫秒）
	 */
	public static function get_average_latency( string $providerId ): int {
		$health = self::get_all_health();
		return $health[ $providerId ]['avg_latency'] ?? 0;
	}

	/**
	 * 获取 Provider 详细状态
	 *
	 * @param string $providerId Provider ID
	 * @return array 状态详情
	 */
	public static function get_provider_status( string $providerId ): array {
		$health = self::get_all_health();

		if ( ! isset( $health[ $providerId ] ) ) {
			return [
				'health_score' => 100,
				'avg_latency'  => 0,
				'total'        => 0,
				'failures'     => 0,
				'success_rate' => 100,
			];
		}

		$provider  = $health[ $providerId ];
		$total     = count( $provider['history'] );
		$successes = count( array_filter( $provider['history'], fn( $h ) => $h['success'] ) );

		return [
			'health_score' => self::get_health_score( $providerId ),
			'avg_latency'  => $provider['avg_latency'] ?? 0,
			'total'        => $provider['total'] ?? 0,
			'failures'     => $provider['failures'] ?? 0,
			'success_rate' => $total > 0 ? round( ( $successes / $total ) * 100 ) : 100,
			'last_updated' => $provider['last_updated'] ?? null,
		];
	}

	/**
	 * 获取所有 Provider 的健康状态
	 *
	 * @return array 所有 Provider 的健康数据
	 */
	public static function get_all_health(): array {
		$data = get_transient( self::TRANSIENT_KEY );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * 清除所有健康数据
	 */
	public static function clear_all(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * 清除指定 Provider 的健康数据
	 *
	 * @param string $providerId Provider ID
	 */
	public static function clear_provider( string $providerId ): void {
		$health = self::get_all_health();
		unset( $health[ $providerId ] );
		set_transient( self::TRANSIENT_KEY, $health, self::CACHE_TTL );
	}
}
