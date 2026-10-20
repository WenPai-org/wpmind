<?php
/**
 * 版本化数据迁移调度器
 *
 * 自动更新不会触发激活钩子，所以挂在 plugins_loaded 上懒执行；
 * 激活钩子里也会调一次。多站点下每个子站在自己的请求里各自迁移、各用各的锁。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

/**
 * 迁移调度器。
 *
 * @since 0.13.0
 */
final class Migrator {

	/**
	 * 端点 schema 版本 option（autoload=false）。
	 *
	 * 独立于 RewriteFlush 的 wpmind_db_version（F1：共用时 1300 会让 rewrite 重建被跳过）。
	 */
	public const SCHEMA_VERSION_OPTION = 'wpmind_endpoint_schema_version';

	/**
	 * 兼容旧代码的别名，指向 SCHEMA_VERSION_OPTION。
	 */
	public const DB_VERSION_OPTION = self::SCHEMA_VERSION_OPTION;

	/**
	 * 迁移日志 option（autoload=false，最多 LOG_MAX 条，不含密钥和 URL）。
	 */
	public const LOG_OPTION = 'wpmind_migration_log';

	/**
	 * 迁移锁 option（autoload=false，值是加锁时间戳）。
	 */
	public const LOCK_OPTION = 'wpmind_migration_lock';

	/**
	 * 锁超过这么多秒视为陈旧，可以接管。
	 */
	public const LOCK_TTL = 300;

	/**
	 * 日志最多保留条数。
	 */
	public const LOG_MAX = 20;

	/**
	 * 失败或回滚后，多少秒内不再自动重试（避免每个请求都写库）。
	 */
	public const RETRY_BACKOFF = 3600;

	/**
	 * 当前代码对应的数据版本。
	 */
	public const CURRENT_DB_VERSION = 1300;

	/**
	 * 迁移列表；为空时用默认列表。
	 *
	 * @var list<MigrationInterface>
	 */
	private array $migrations;

	/**
	 * 构造。
	 *
	 * @param list<MigrationInterface>|null $migrations 测试时可以注入.
	 */
	public function __construct( ?array $migrations = null ) {
		$this->migrations = $migrations ?? [ new M013_EndpointsV2() ];
	}

	/**
	 * 有待执行的迁移就执行（plugins_loaded:2 和激活钩子调用）。
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		$migrator = new self();
		if ( [] !== $migrator->pending() ) {
			$migrator->run();
		}
	}

	/**
	 * 当前数据版本。
	 *
	 * @return int
	 */
	public static function db_version(): int {
		return (int) get_option( self::DB_VERSION_OPTION, 0 );
	}

	/**
	 * 需要执行的迁移：版本号比库里新，或者数据形状显示还需要跑。
	 *
	 * @return list<MigrationInterface>
	 */
	public function pending(): array {
		$db_version = self::db_version();
		$pending    = [];
		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() <= $db_version && ! $migration->is_needed() ) {
				continue;
			}
			if ( $this->in_backoff( $migration->id() ) ) {
				continue;
			}
			$pending[] = $migration;
		}
		return $pending;
	}

	/**
	 * 抢锁并执行待执行的迁移。
	 *
	 * @return list<array{id:string, at:int, result:string, notes:list<string>}> 本次写入的日志；没抢到锁返回空数组。
	 */
	public function run(): array {
		$pending = $this->pending();
		if ( [] === $pending ) {
			return [];
		}

		if ( ! $this->acquire_lock() ) {
			// 别的请求正在迁移：本次跳过，不阻塞请求.
			return [];
		}

		$entries = [];
		try {
			$target = self::db_version();
			$all_ok = true;
			foreach ( $pending as $migration ) {
				try {
					$outcome = $migration->run();
				} catch ( \Throwable $e ) {
					$outcome = [
						'result' => 'failed',
						'notes'  => [ 'exception: ' . get_class( $e ) ],
					];
				}

				$entry     = [
					'id'     => $migration->id(),
					'at'     => time(),
					'result' => (string) $outcome['result'],
					'notes'  => array_map( 'strval', $outcome['notes'] ),
				];
				$entries[] = $entry;
				$this->append_log( $entry );

				if ( in_array( $entry['result'], [ 'ok', 'noop' ], true ) ) {
					$target = max( $target, $migration->version() );
				} else {
					$all_ok = false;
					break;
				}
			}

			if ( $target > self::db_version() ) {
				update_option( self::DB_VERSION_OPTION, $target, false );
			} elseif ( $all_ok && false === get_option( self::DB_VERSION_OPTION, false ) ) {
				update_option( self::DB_VERSION_OPTION, $target, false );
			}
		} finally {
			$this->release_lock();
		}

		return $entries;
	}

	/**
	 * 迁移日志。
	 *
	 * @return list<array{id:string, at:int, result:string, notes:list<string>}>
	 */
	public static function log(): array {
		$log = get_option( self::LOG_OPTION, [] );
		return is_array( $log ) ? array_values( $log ) : [];
	}

	/**
	 * 用 add_option 的原子性抢锁；超过 LOCK_TTL 的旧锁可以接管。
	 *
	 * @return bool
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );
		if ( $locked_at > 0 && ( time() - $locked_at ) <= self::LOCK_TTL ) {
			return false;
		}

		// 陈旧锁：删掉后重新原子抢一次，同时到达的请求只有一个能抢到.
		delete_option( self::LOCK_OPTION );
		return add_option( self::LOCK_OPTION, time(), '', false );
	}

	/**
	 * 释放锁。
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * 追加一条日志，只保留最近 LOG_MAX 条。
	 *
	 * @param array{id:string, at:int, result:string, notes:list<string>} $entry 日志.
	 * @return void
	 */
	private function append_log( array $entry ): void {
		$log   = self::log();
		$log[] = $entry;
		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, -self::LOG_MAX );
		}
		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * 这个迁移最近一次是否失败/回滚，且还在退避期内。
	 *
	 * @param string $id 迁移 ID.
	 * @return bool
	 */
	private function in_backoff( string $id ): bool {
		$log = self::log();
		for ( $i = count( $log ) - 1; $i >= 0; $i-- ) {
			if ( ( $log[ $i ]['id'] ?? '' ) !== $id ) {
				continue;
			}
			$failed = in_array( $log[ $i ]['result'] ?? '', [ 'rolled_back', 'failed' ], true );
			return $failed && ( time() - (int) ( $log[ $i ]['at'] ?? 0 ) ) < self::RETRY_BACKOFF;
		}
		return false;
	}
}
