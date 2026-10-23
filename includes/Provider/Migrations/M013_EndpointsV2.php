<?php
/**
 * Endpoint schema v2 迁移：wpmind_custom_endpoints（v1）→ wpmind_custom_endpoints_v2
 *
 * 规则见设计说明 §5.2：
 *
 * 1. 原 option `wpmind_custom_endpoints` 是回滚镜像，也是 api_key 唯一的明文位置。
 *    迁移**只读不写**它（只把 autoload 改成 no），旧字段一律不清空、不删除，
 *    回滚到旧版本后仍能直接读到 name/base_url/models/enabled/api_key。
 * 2. 新结构写进 `wpmind_custom_endpoints_v2`（autoload=false，不含 api_key），
 *    同时记下旧字段的 hash（api_key 只按指纹参与计算）。
 * 3. 是否需要迁移：v2 不存在，或者旧字段的 hash 和 v2 里记的不一样
 *    （回滚后在旧版本里改过设置）。hash 只在 admin_init 和升级时计算。
 * 4. 第一次迁移把 v1 原值（去掉 api_key）存进 `_v1_backup`，以后永不覆盖；
 *    之后每次因 hash 变化重新迁移，先另存一份带时间戳的备份，最多保留 BACKUP_MAX 份。
 * 5. models 等于任意历史默认列表 → v2.models=[]（交给目录）；否则 → 用户选择。
 *    v2 里已有的 custom_base_url/default_model/key_meta 在重新迁移时保留（旧版本存不进这些字段）。
 * 6. 写完重读 v2，按 enabled 校验且确认没有 api_key，不一致就恢复迁移前的 v2。
 * 7. 共用 wpmind_db_version 期间（F1 之前）的构建已经把原 option 改成了 v2 形状、
 *    但没有 v2 option：只补写 v2（原样转换）和 hash，不重跑迁移、不生成新备份。
 *
 * 迁移只读 key 是否为空、只算指纹，不复制、不记录 key 原文。
 *
 * @package WPMind
 * @since endpoint schema v2
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderDefinitions;

/**
 * 端点配置 v2 迁移（迁移 id 和类名保持不变，已写进迁移日志）。
 *
 * @since endpoint schema v2
 */
final class M013_EndpointsV2 implements MigrationInterface {

	/**
	 * 第一次迁移时的 v1 备份 option（autoload=false，不含 api_key，永不覆盖）。
	 */
	public const BACKUP_OPTION = 'wpmind_custom_endpoints_v1_backup';

	/**
	 * 之后每次重新迁移的备份 option 名前缀（后接 UTC 时间戳）。
	 */
	public const BACKUP_PREFIX = 'wpmind_custom_endpoints_backup_';

	/**
	 * 带时间戳备份的索引（option 名列表，旧的在前）。
	 */
	public const BACKUP_INDEX_OPTION = 'wpmind_custom_endpoints_backups';

	/**
	 * 带时间戳的备份最多保留几份。
	 */
	public const BACKUP_MAX = 3;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'm013_endpoints_v2';
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): int {
		return 1300;
	}

	/**
	 * {@inheritDoc}
	 *
	 * 会读旧字段并计算 hash，只应在 admin_init 或升级时调用。
	 */
	public function is_needed(): bool {
		$v2 = ( new EndpointRepository() )->v2();
		if ( null === $v2 ) {
			return true;
		}
		$saved = get_option( EndpointRepository::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			return false;
		}
		return EndpointRepository::legacy_hash( $saved ) !== (string) ( $v2['legacy_hash'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): array {
		$repository = new EndpointRepository();
		$before     = get_option( EndpointRepository::OPTION, false );

		if ( false === $before ) {
			// 全新站点：建空的、不自动加载的 option（B20）和空的 v2.
			add_option( EndpointRepository::OPTION, [], '', false );
			$repository->save_v2( [], EndpointRepository::legacy_hash( [] ) );
			return [
				'result' => 'noop',
				'notes'  => [ 'no saved endpoints' ],
			];
		}

		if ( ! is_array( $before ) ) {
			return [
				'result' => 'noop',
				'notes'  => [ 'option is not an array, left untouched' ],
			];
		}

		EndpointRepository::ensure_no_autoload();

		$hash = EndpointRepository::legacy_hash( $before );
		$v2   = $repository->v2();

		if ( null !== $v2 && (string) ( $v2['legacy_hash'] ?? '' ) === $hash ) {
			return [
				'result' => 'noop',
				'notes'  => [ 'legacy settings unchanged' ],
			];
		}

		if ( null === $v2 && self::is_pre_split_v2( $before ) ) {
			$providers = [];
			foreach ( $before as $id => $entry ) {
				if ( is_string( $id ) && is_array( $entry ) ) {
					$providers[ $id ] = EndpointRepository::v2_entry( EndpointRepository::normalize_entry( $id, $entry ) );
				}
			}
			$repository->save_v2( $providers, $hash );
			return [
				'result' => 'noop',
				'notes'  => [ 'pre-split v2 data adopted: version and hash written, no re-migration, no backup' ],
			];
		}

		$notes = [];
		if ( null === $v2 ) {
			if ( false === get_option( self::BACKUP_OPTION, false ) ) {
				add_option( self::BACKUP_OPTION, self::strip_keys( $before ), '', false );
			}
		} else {
			self::store_timestamped_backup( self::strip_keys( $before ) );
			$notes[] = 'legacy settings changed since last migration';
		}

		$previous  = is_array( $v2['providers'] ?? null ) ? $v2['providers'] : [];
		$providers = [];
		foreach ( $before as $id => $entry ) {
			if ( is_string( $id ) && is_array( $entry ) ) {
				$providers[ $id ] = self::transform_entry( $id, $entry, $notes, is_array( $previous[ $id ] ?? null ) ? $previous[ $id ] : [] );
			}
		}

		// F7b：v0.11.5 的设置表单保存时会整个丢掉 custom_base_url 键。
		// 规则：旧条目有这个键 → 以旧值为准（含清空，只有直接改 option 才会出现）；
		// 没有这个键 → 沿用 v2 的地址，并把它写回镜像，否则再次回滚时 v0.11.5 会发到官方地址.
		$original = $before;
		$restored = [];
		foreach ( $providers as $id => $entry ) {
			$custom = (string) ( $entry['custom_base_url'] ?? '' );
			if ( '' !== $custom && is_array( $before[ $id ] ?? null ) && ! array_key_exists( 'custom_base_url', $before[ $id ] ) ) {
				$before[ $id ]['custom_base_url'] = $custom;
				$restored[]                       = $id;
			}
		}
		if ( [] !== $restored ) {
			update_option( EndpointRepository::OPTION, $before, false );
			$hash    = EndpointRepository::legacy_hash( $before );
			$notes[] = 'custom_base_url restored into mirror: ' . implode( ',', $restored );
		}

		$repository->save_v2( $providers, $hash );

		$reread = $repository->v2();
		if ( null === $reread || ! self::verify( $before, is_array( $reread['providers'] ?? null ) ? $reread['providers'] : [] ) ) {
			if ( null === $v2 ) {
				delete_option( EndpointRepository::V2_OPTION );
			} else {
				update_option( EndpointRepository::V2_OPTION, $v2, false );
			}
			if ( [] !== $restored ) {
				update_option( EndpointRepository::OPTION, $original, false );
			}
			$notes[] = 'verify failed: enabled changed or api_key present, v2 restored';
			return [
				'result' => 'rolled_back',
				'notes'  => $notes,
			];
		}

		return [
			'result' => 'ok',
			'notes'  => $notes,
		];
	}

	/**
	 * F1 之前的构建已经把原 option 改成 v2 形状：非空，且没有任何 v1 形状的条目。
	 *
	 * 旧版本（0.11.x/0.12）每次保存都写 name/base_url，所以真正的旧数据不会落进这里。
	 *
	 * @param array<int|string, mixed> $saved 原 option.
	 * @return bool
	 */
	public static function is_pre_split_v2( array $saved ): bool {
		if ( [] === $saved ) {
			return false;
		}
		foreach ( $saved as $id => $entry ) {
			if ( is_string( $id ) && EndpointRepository::is_v1_entry( $id, $entry ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * 从一条旧条目推导 v2 条目（不含 api_key）。
	 *
	 * @param string               $id       Provider ID.
	 * @param array<string, mixed> $entry    原条目.
	 * @param string[]             $notes    日志备注（引用，不含 key 和 URL）.
	 * @param array<string, mixed> $previous 上一次迁移写进 v2 的条目（重新迁移时）.
	 * @return array<string, mixed>
	 */
	public static function transform_entry( string $id, array $entry, array &$notes, array $previous = [] ): array {
		$models = EndpointRepository::normalize_models( $entry['models'] ?? [] );
		if ( ProviderDefinitions::is_historical_default_models( $id, $models ) ) {
			// F6：旧版只改 key 保存会把 models 重置成历史默认值；v2 里已有用户自配的 models 就沿用.
			$kept    = EndpointRepository::normalize_models( $previous['models'] ?? [] );
			$models  = $kept;
			$notes[] = $id . ( [] !== $kept ? ': default models copy ignored, v2 custom models kept' : ': default models copy ignored' );
		} elseif ( [] !== $models ) {
			$notes[] = $id . ': custom models kept';
		}

		$pick = static function ( string $field ) use ( $entry, $previous ): string {
			foreach ( [ $previous, $entry ] as $source ) {
				if ( is_string( $source[ $field ] ?? null ) && '' !== trim( $source[ $field ] ) ) {
					return trim( $source[ $field ] );
				}
			}
			return '';
		};

		// F5：旧版能读写 custom_base_url，旧字段里有这个键就以旧字段为准（包括在旧版里清空）.
		$custom_base_url = array_key_exists( 'custom_base_url', $entry )
			? trim( is_string( $entry['custom_base_url'] ) ? $entry['custom_base_url'] : '' )
			: $pick( 'custom_base_url' );

		$out = [
			'enabled'         => ! empty( $entry['enabled'] ),
			'custom_base_url' => $custom_base_url,
			'models'          => $models,
			'default_model'   => $pick( 'default_model' ),
		];

		$key_meta = is_array( $previous['key_meta'] ?? null ) ? $previous['key_meta'] : ( is_array( $entry['key_meta'] ?? null ) ? $entry['key_meta'] : [] );
		if ( [] !== $key_meta ) {
			$out['key_meta'] = $key_meta;
		}

		$old_base = is_string( $entry['base_url'] ?? null ) ? rtrim( trim( $entry['base_url'] ), '/' ) : '';
		$legacy   = ProviderDefinitions::legacy_base_urls( $id );
		if ( '' !== $old_base
			&& rtrim( ProviderDefinitions::default_base_url( $id ), '/' ) !== $old_base
			&& ! in_array( $old_base, $legacy, true ) ) {
			$notes[] = $id . ': unknown stored base_url ignored';
		}

		$has_key = '' !== ( is_scalar( $entry['api_key'] ?? null ) ? (string) $entry['api_key'] : '' );
		if ( [] !== $legacy && $has_key && '' === $out['custom_base_url'] ) {
			// D7：默认地址换了，旧 key 格式不通用；只标记，不自动启用新地址.
			$out['needs_reconfigure'] = true;
			$notes[]                  = $id . ': needs_reconfigure';
		}

		return $out;
	}

	/**
	 * 校验：每家的 enabled 和原 option 一致，v2 里没有 api_key。
	 *
	 * @param array<int|string, mixed> $legacy    原 option.
	 * @param array<int|string, mixed> $providers v2 providers.
	 * @return bool
	 */
	public static function verify( array $legacy, array $providers ): bool {
		foreach ( $providers as $entry ) {
			if ( is_array( $entry ) && array_key_exists( 'api_key', $entry ) ) {
				return false;
			}
		}
		foreach ( $legacy as $id => $entry ) {
			if ( ! is_string( $id ) || ! is_array( $entry ) ) {
				continue;
			}
			if ( ! is_array( $providers[ $id ] ?? null ) || ! empty( $entry['enabled'] ) !== ! empty( $providers[ $id ]['enabled'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * 另存一份带时间戳的备份，只保留最近 BACKUP_MAX 份（删最旧的是产品行为）。
	 *
	 * @param array<string, mixed> $data 去掉 key 的旧配置.
	 * @return string 新备份的 option 名.
	 */
	public static function store_timestamped_backup( array $data ): string {
		$index = get_option( self::BACKUP_INDEX_OPTION, [] );
		$index = is_array( $index ) ? array_values( array_filter( $index, 'is_string' ) ) : [];

		$base = self::BACKUP_PREFIX . gmdate( 'YmdHis' );
		$name = $base;
		$i    = 1;
		while ( false !== get_option( $name, false ) || in_array( $name, $index, true ) ) {
			$name = $base . '_' . $i;
			++$i;
		}
		add_option( $name, $data, '', false );
		$index[] = $name;

		$excess = count( $index ) - self::BACKUP_MAX;
		for ( $j = 0; $j < $excess; $j++ ) {
			delete_option( (string) array_shift( $index ) );
		}
		update_option( self::BACKUP_INDEX_OPTION, $index, false );

		return $name;
	}

	/**
	 * 去掉 api_key 的副本（备份用）。
	 *
	 * @param array<string, mixed> $data 原配置.
	 * @return array<string, mixed>
	 */
	public static function strip_keys( array $data ): array {
		foreach ( $data as $id => $entry ) {
			if ( is_array( $entry ) ) {
				unset( $entry['api_key'] );
				$data[ $id ] = $entry;
			}
		}
		return $data;
	}
}
