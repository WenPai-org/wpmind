<?php
/**
 * 0.13 迁移：wpmind_custom_endpoints v1 → v2
 *
 * 旧版 v1（0.12 及以前）每次保存都把 name/base_url/models 默认副本写进 option；
 * v2 只保存用户的选择。规则见设计说明 §5「迁移规则」：
 *
 * 1. 由 Migrator 用 add_option 抢锁；
 * 2. 按形状识别 v1：有 name/base_url 等字段，或 models 等于任意一份历史默认列表；
 * 3. models 等于历史默认 → 置空（交给目录）；不等 → 原样保留成用户选择；
 *    删掉 name/base_url/display_name/icon/is_official；api_key、enabled 完全不动；
 * 4. 旧 base_url 只是默认值副本，直接丢弃；默认地址换过的 Provider（见
 *    ProviderDefinitions::LEGACY_BASE_URLS）在已配置 key 时标记 needs_reconfigure；
 * 5. 写之前存去掉 api_key 的备份；写完重读，按 enabled 和 key 指纹校验，
 *    不一致就把除 key 以外的字段回滚；
 * 6. 由 Migrator 写 wpmind_endpoint_schema_version=1300 和迁移日志、释放锁。
 *
 * 迁移只读 key 是否为空、只算指纹，不复制、不记录 key 原文。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\Secrets;

/**
 * 端点配置 v2 迁移。
 *
 * @since 0.13.0
 */
final class M013_EndpointsV2 implements MigrationInterface {

	/**
	 * 旧版 v1 备份 option（autoload=false，不含 api_key）。
	 */
	public const BACKUP_OPTION = 'wpmind_custom_endpoints_v1_backup';

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
	 */
	public function is_needed(): bool {
		$saved = get_option( EndpointRepository::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			return false;
		}
		foreach ( $saved as $id => $entry ) {
			if ( is_string( $id ) && EndpointRepository::is_v1_entry( $id, $entry ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): array {
		$before = get_option( EndpointRepository::OPTION, false );

		if ( false === $before ) {
			// 全新站点：先建一个空的、不自动加载的 option，以后的保存都沿用 autoload=false（B20）.
			add_option( EndpointRepository::OPTION, [], '', false );
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

		$notes = [];
		$is_v1 = false;
		foreach ( $before as $id => $entry ) {
			if ( is_string( $id ) && EndpointRepository::is_v1_entry( $id, $entry ) ) {
				$is_v1 = true;
				break;
			}
		}

		if ( ! $is_v1 ) {
			// 已经是 v2：只确保不自动加载（B20），不改值.
			EndpointRepository::ensure_no_autoload();
			return [
				'result' => 'noop',
				'notes'  => [ 'already v2' ],
			];
		}

		update_option( self::BACKUP_OPTION, self::strip_keys( $before ), false );

		$after = [];
		foreach ( $before as $id => $entry ) {
			$after[ $id ] = ( is_string( $id ) && is_array( $entry ) )
				? self::transform_entry( $id, $entry, $notes )
				: $entry;
		}

		$snapshot = self::snapshot( $before );
		( new EndpointRepository() )->save( $after );

		$reread = get_option( EndpointRepository::OPTION, [] );
		$reread = is_array( $reread ) ? $reread : [];
		if ( self::snapshot( $reread ) !== $snapshot ) {
			// 校验失败：把除 key 以外的字段回滚到迁移前，key 保留当前库里的值.
			$restore = $before;
			foreach ( $restore as $id => $entry ) {
				if ( is_array( $entry ) && is_array( $reread[ $id ] ?? null ) && array_key_exists( 'api_key', $reread[ $id ] ) ) {
					$restore[ $id ]['api_key'] = $reread[ $id ]['api_key'];
				}
			}
			update_option( EndpointRepository::OPTION, $restore, false );
			$notes[] = 'verify failed: enabled/key fingerprint changed, non-key fields rolled back';
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
	 * 把一条 v1（或混合形状）条目转换成 v2。
	 *
	 * @param string               $id    Provider ID.
	 * @param array<string, mixed> $entry 原条目.
	 * @param string[]             $notes 日志备注（引用，不含 key 和 URL）.
	 * @return array<string, mixed>
	 */
	public static function transform_entry( string $id, array $entry, array &$notes ): array {
		$out = $entry; // enabled、api_key 及其他未知字段原样保留.

		foreach ( EndpointRepository::LEGACY_FIELDS as $field ) {
			unset( $out[ $field ] );
		}

		$models = EndpointRepository::normalize_models( $entry['models'] ?? [] );
		if ( ProviderDefinitions::is_historical_default_models( $id, $models ) ) {
			$out['models'] = [];
			$notes[]       = $id . ': default models copy cleared';
		} elseif ( [] !== $models ) {
			$out['models'] = $models;
			$notes[]       = $id . ': custom models kept';
		} else {
			$out['models'] = [];
		}

		$out['custom_base_url'] = is_string( $entry['custom_base_url'] ?? null ) ? trim( $entry['custom_base_url'] ) : '';
		$out['default_model']   = is_string( $entry['default_model'] ?? null ) ? trim( $entry['default_model'] ) : '';

		$old_base = is_string( $entry['base_url'] ?? null ) ? rtrim( trim( $entry['base_url'] ), '/' ) : '';
		$legacy   = ProviderDefinitions::legacy_base_urls( $id );
		if ( '' !== $old_base
			&& rtrim( ProviderDefinitions::default_base_url( $id ), '/' ) !== $old_base
			&& ! in_array( $old_base, $legacy, true ) ) {
			$notes[] = $id . ': unknown stored base_url dropped';
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

	/**
	 * 校验快照：每家的 enabled 原值和 key 指纹。
	 *
	 * @param array<string, mixed> $data 配置.
	 * @return array<string, array{enabled:mixed, key:string}>
	 */
	public static function snapshot( array $data ): array {
		$snapshot = [];
		foreach ( $data as $id => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$key             = $entry['api_key'] ?? '';
			$snapshot[ $id ] = [
				'enabled' => $entry['enabled'] ?? null,
				'key'     => Secrets::fingerprint( is_scalar( $key ) ? (string) $key : '' ),
			];
		}
		ksort( $snapshot );
		return $snapshot;
	}
}
