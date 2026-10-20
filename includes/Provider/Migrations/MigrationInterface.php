<?php
/**
 * 版本化数据迁移接口
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

/**
 * 一次数据迁移。
 *
 * 每个迁移都必须幂等，并且按数据的形状判断要不要跑（不只看版本号），
 * 这样降级再升级时能重新识别出旧形状。
 *
 * @since 0.13.0
 */
interface MigrationInterface {

	/**
	 * 迁移 ID（写进迁移日志）。
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * 跑完以后的 wpmind_endpoint_schema_version。
	 *
	 * @return int
	 */
	public function version(): int;

	/**
	 * 按数据形状判断是否还需要跑。
	 *
	 * @return bool
	 */
	public function is_needed(): bool;

	/**
	 * 执行迁移。
	 *
	 * @return array{result:string, notes:list<string>} result 取 ok / noop / rolled_back / failed。
	 */
	public function run(): array;
}
