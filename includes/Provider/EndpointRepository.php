<?php
/**
 * 端点配置仓库
 *
 * `wpmind_custom_endpoints` 的唯一读写入口：负责读取、规范化（兼容 v1 形状）、
 * 解析实际使用的 Base URL，以及校验自定义地址。
 *
 * @package WPMind
 * @since endpoint schema v2
 */

declare(strict_types=1);

namespace WPMind\Provider;

use WP_Error;

/**
 * 端点配置仓库。
 *
 * @since endpoint schema v2
 */
final class EndpointRepository {

	/**
	 * 原 option：回滚镜像（旧版本直接读它），也是 api_key 唯一的明文位置。
	 */
	public const OPTION = 'wpmind_custom_endpoints';

	/**
	 * Endpoint schema v2 的新结构（autoload=false，不含 api_key）。
	 */
	public const V2_OPTION = 'wpmind_custom_endpoints_v2';

	/**
	 * V2 结构版本。
	 */
	public const V2_SCHEMA = 2;

	/**
	 * 回滚镜像里 models 为空时写入的历史默认列表版本。
	 */
	public const MIRROR_DEFAULT_MODELS_FROM = '0.12.0';

	/**
	 * 旧版 v1（0.12 及以前）写进 option、v2 不再保存的元数据字段。
	 */
	public const LEGACY_FIELDS = [ 'name', 'base_url', 'display_name', 'icon', 'is_official' ];

	/**
	 * 读取全部原始配置。
	 *
	 * @return array<string, mixed>
	 */
	public function raw(): array {
		$saved = get_option( self::OPTION, [] );
		return is_array( $saved ) ? $saved : [];
	}

	/**
	 * 取一家的配置。未知或未保存的 Provider 返回全默认值。
	 *
	 * @param string $id Provider ID.
	 * @return Endpoint
	 */
	public function get( string $id ): Endpoint {
		return Endpoint::from_array( $id, $this->entry( $id, $this->raw(), $this->v2() ) );
	}

	/**
	 * V2 option；不存在或结构不对时返回 null（还没迁移）。
	 *
	 * @return array<string, mixed>|null 含 schema、legacy_hash、providers。
	 */
	public function v2(): ?array {
		$v2 = get_option( self::V2_OPTION, false );
		return ( is_array( $v2 ) && is_array( $v2['providers'] ?? null ) ) ? $v2 : null;
	}

	/**
	 * 一家的规范化配置：迁移后读 v2（api_key 取自原 option），迁移前按原 option 现场转换（不写库）。
	 *
	 * @param string                    $id  Provider ID.
	 * @param array<string, mixed>      $raw 原 option.
	 * @param array<string, mixed>|null $v2  v2 option.
	 * @return array<string, mixed>
	 */
	private function entry( string $id, array $raw, ?array $v2 ): array {
		$legacy = is_array( $raw[ $id ] ?? null ) ? $raw[ $id ] : [];
		if ( null === $v2 ) {
			return self::normalize_entry( $id, $legacy );
		}
		$entry            = is_array( $v2['providers'][ $id ] ?? null ) ? $v2['providers'][ $id ] : [];
		$entry['api_key'] = $legacy['api_key'] ?? '';
		return self::normalize_entry( $id, $entry );
	}

	/**
	 * 写 v2（autoload=false）。任何 api_key 字段都会被去掉。
	 *
	 * @param array<string, mixed> $providers   每家的 v2 条目.
	 * @param string               $legacy_hash 原 option 旧字段的 hash.
	 * @return bool
	 */
	public function save_v2( array $providers, string $legacy_hash ): bool {
		foreach ( $providers as $id => $entry ) {
			if ( is_array( $entry ) ) {
				unset( $entry['api_key'] );
				$providers[ $id ] = $entry;
			}
		}
		return update_option(
			self::V2_OPTION,
			[
				'schema'      => self::V2_SCHEMA,
				'legacy_hash' => $legacy_hash,
				'providers'   => $providers,
			],
			false
		);
	}

	/**
	 * 规范化结果 → 存进 v2 的条目（去掉 api_key 和空的可选字段）。
	 *
	 * @param array<string, mixed> $normalized normalize_entry() 的结果.
	 * @return array<string, mixed>
	 */
	public static function v2_entry( array $normalized ): array {
		$out = [
			'enabled'         => ! empty( $normalized['enabled'] ),
			'custom_base_url' => (string) ( $normalized['custom_base_url'] ?? '' ),
			'models'          => self::normalize_models( $normalized['models'] ?? [] ),
			'default_model'   => (string) ( $normalized['default_model'] ?? '' ),
		];
		if ( is_array( $normalized['key_meta'] ?? null ) && [] !== $normalized['key_meta'] ) {
			$out['key_meta'] = $normalized['key_meta'];
		}
		if ( ! empty( $normalized['needs_reconfigure'] ) ) {
			$out['needs_reconfigure'] = true;
		}
		return $out;
	}

	/**
	 * 回滚镜像条目：旧版本能直接读的 name/base_url/models/enabled/api_key。
	 *
	 * Models：用户配过就写用户的，没配过写历史默认列表（保证旧版本 models 非空）。
	 *
	 * @param string               $id       Provider ID.
	 * @param array<string, mixed> $v2_entry v2 条目.
	 * @param string               $api_key  API key（只在原 option 里保存）.
	 * @return array{name:string, base_url:string, models:list<string>, enabled:bool, api_key:string}
	 */
	public static function mirror_entry( string $id, array $v2_entry, string $api_key ): array {
		$definition = ProviderDefinitions::get( $id ) ?? [];
		$models     = self::normalize_models( $v2_entry['models'] ?? [] );
		if ( [] === $models ) {
			$models = ProviderDefinitions::HISTORICAL_DEFAULT_MODELS[ self::MIRROR_DEFAULT_MODELS_FROM ][ $id ] ?? [];
		}
		$legacy   = ProviderDefinitions::legacy_base_urls( $id );
		$base_url = ( ! empty( $v2_entry['needs_reconfigure'] ) && [] !== $legacy ) ? $legacy[0] : ProviderDefinitions::default_base_url( $id );

		$mirror = [
			'name'     => (string) ( $definition['name'] ?? $id ),
			'base_url' => $base_url,
			'models'   => $models,
			'enabled'  => ! empty( $v2_entry['enabled'] ),
			'api_key'  => $api_key,
		];

		// F5：v0.11.5 取地址用 custom_base_url ?? base_url，自定义地址必须进镜像（也就进了 legacy_hash）.
		$custom = trim( (string) ( $v2_entry['custom_base_url'] ?? '' ) );
		if ( '' !== $custom ) {
			$mirror['custom_base_url'] = $custom;
		}

		return $mirror;
	}

	/**
	 * 原 option 旧字段的 hash：所有字段都参与，api_key 只按指纹参与。
	 *
	 * @param array<string, mixed> $legacy 原 option.
	 * @return string
	 */
	public static function legacy_hash( array $legacy ): string {
		$normalized = [];
		foreach ( $legacy as $id => $entry ) {
			if ( is_array( $entry ) ) {
				if ( array_key_exists( 'api_key', $entry ) ) {
					$key              = $entry['api_key'];
					$entry['api_key'] = Secrets::fingerprint( is_scalar( $key ) ? (string) $key : '' );
				}
				ksort( $entry );
			}
			$normalized[ (string) $id ] = $entry;
		}
		ksort( $normalized );
		return hash( 'sha256', (string) serialize( $normalized ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * 全部已知 Provider 的配置（按 ProviderDefinitions 的顺序）。
	 *
	 * @return array<string, Endpoint>
	 */
	public function all(): array {
		$raw = $this->raw();
		$v2  = $this->v2();
		$all = [];
		foreach ( ProviderDefinitions::ids() as $id ) {
			$all[ $id ] = Endpoint::from_array( $id, $this->entry( $id, $raw, $v2 ) );
		}
		return $all;
	}

	/**
	 * 已启用且配置了 key 的 Provider。
	 *
	 * @return array<string, Endpoint>
	 */
	public function enabled(): array {
		return array_filter(
			$this->all(),
			static function ( Endpoint $endpoint ): bool {
				return $endpoint->is_usable();
			}
		);
	}

	/**
	 * 实际使用的 Base URL（不带结尾斜杠）。
	 *
	 * 1. custom_base_url 非空并且校验通过 → 用它；
	 * 2. 默认地址已更换且站点被标记 needs_reconfigure → 继续用旧默认地址（不自动切换）；
	 * 3. 否则用代码里的默认地址。未知 Provider 返回空串。
	 *
	 * @param string $id Provider ID.
	 * @return string
	 */
	public function resolved_base_url( string $id ): string {
		$endpoint = $this->get( $id );

		if ( '' !== $endpoint->custom_base_url ) {
			$custom = self::validate_base_url( $endpoint->custom_base_url, $id, false );
			if ( '' !== $custom ) {
				return $custom;
			}
		}

		if ( $endpoint->needs_reconfigure ) {
			$legacy = ProviderDefinitions::legacy_base_urls( $id );
			if ( [] !== $legacy ) {
				return self::untrailing( $legacy[0] );
			}
		}

		return self::untrailing( ProviderDefinitions::default_base_url( $id ) );
	}

	/**
	 * 静态便捷方法：给 SDK Provider 类的 baseUrl() 用。
	 *
	 * @param string $id       Provider ID.
	 * @param string $fallback 取不到时的回退值（原来写死的常量）.
	 * @return string
	 */
	public static function resolve( string $id, string $fallback = '' ): string {
		if ( ! function_exists( 'get_option' ) ) {
			return $fallback;
		}
		$url = ( new self() )->resolved_base_url( $id );
		return '' !== $url ? $url : $fallback;
	}

	/**
	 * 保存整份配置（autoload=false，B20）。
	 *
	 * @param array<string, mixed> $data v2 配置.
	 * @return bool
	 */
	public function save( array $data ): bool {
		$result = update_option( self::OPTION, $data, false );
		self::ensure_no_autoload();
		return $result;
	}

	/**
	 * 把主存储改成不自动加载（含明文 key，不该每个请求都进内存）。
	 *
	 * 注意：update_option() 在值没变时不会更新 autoload，所以单独调一次。
	 *
	 * @return void
	 */
	public static function ensure_no_autoload(): void {
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, false );
		}
	}

	/**
	 * 把一条已保存的配置（v1 或 v2）规范化成 v2 结构。
	 *
	 * 不做任何网络/数据库操作；api_key 原样返回（只转成字符串）。
	 *
	 * @param string $id    Provider ID.
	 * @param mixed  $entry 已保存的条目.
	 * @return array{enabled:bool, api_key:string, custom_base_url:string, models:list<string>, default_model:string, key_meta:array<string,mixed>, needs_reconfigure:bool}
	 */
	public static function normalize_entry( string $id, $entry ): array {
		$entry  = is_array( $entry ) ? $entry : [];
		$models = self::normalize_models( $entry['models'] ?? [] );

		if ( ProviderDefinitions::is_historical_default_models( $id, $models ) ) {
			// 0.12 强制写进库的默认副本，不是用户的选择.
			$models = [];
		}

		$api_key = $entry['api_key'] ?? '';

		return [
			'enabled'           => ! empty( $entry['enabled'] ),
			'api_key'           => is_scalar( $api_key ) ? (string) $api_key : '',
			'custom_base_url'   => is_string( $entry['custom_base_url'] ?? null ) ? trim( $entry['custom_base_url'] ) : '',
			'models'            => $models,
			'default_model'     => is_string( $entry['default_model'] ?? null ) ? trim( $entry['default_model'] ) : '',
			'key_meta'          => is_array( $entry['key_meta'] ?? null ) ? $entry['key_meta'] : [],
			'needs_reconfigure' => ! empty( $entry['needs_reconfigure'] ),
		];
	}

	/**
	 * 条目是不是 v1 形状。
	 *
	 * 有 name/base_url 等元数据字段，或者 models 和任意一份历史默认列表完全相等。
	 *
	 * @param string $id    Provider ID.
	 * @param mixed  $entry 已保存的条目.
	 * @return bool
	 */
	public static function is_v1_entry( string $id, $entry ): bool {
		if ( ! is_array( $entry ) ) {
			return false;
		}
		foreach ( self::LEGACY_FIELDS as $field ) {
			if ( array_key_exists( $field, $entry ) ) {
				return true;
			}
		}
		if ( array_key_exists( 'models', $entry ) && ! is_array( $entry['models'] ) ) {
			// 更早的版本可能存成逗号分隔字符串.
			return true;
		}
		return ProviderDefinitions::is_historical_default_models( $id, self::normalize_models( $entry['models'] ?? [] ) );
	}

	/**
	 * 规范化模型列表：接受数组或逗号分隔字符串，去空白、去空、去重，保持顺序。
	 *
	 * @param mixed $models 原始值.
	 * @return list<string>
	 */
	public static function normalize_models( $models ): array {
		if ( is_string( $models ) ) {
			$models = explode( ',', $models );
		}
		if ( ! is_array( $models ) ) {
			return [];
		}
		$out = [];
		foreach ( $models as $model ) {
			if ( ! is_string( $model ) ) {
				continue;
			}
			$model = trim( $model );
			if ( '' !== $model && ! in_array( $model, $out, true ) ) {
				$out[] = $model;
			}
		}
		return $out;
	}

	/**
	 * 校验自定义 Base URL。
	 *
	 * 只允许 https；http 需要 `wpmind_allow_http_base_url` 过滤器显式放开（比如内网网关）。
	 * 不允许带用户名密码、查询串和锚点（会被拼进请求路径，也可能夹带密钥）。
	 * $strict=true（保存时）再额外过 esc_url_raw() + wp_http_validate_url()；
	 * 读取时用 $strict=false，避免每个请求都做 DNS 解析。
	 *
	 * @param string $url    待校验地址.
	 * @param string $id     Provider ID（传给过滤器）.
	 * @param bool   $strict 是否做保存时的完整校验.
	 * @return string 合法时返回去掉结尾斜杠的地址，否则空串。
	 */
	public static function validate_base_url( string $url, string $id = '', bool $strict = true ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( $strict ) {
			$url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url, [ 'https', 'http' ] ) : $url;
			if ( '' === $url ) {
				return '';
			}
		}

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( 'https' !== $scheme ) {
			/**
			 * 是否允许 http:// 的自定义 Base URL（默认不允许）。
			 *
			 * 只建议对内网网关放开；注意 wp_http_validate_url() 默认还会拒绝
			 * 内网地址和非 80/443/8080 端口，需要配合 `http_request_host_is_external`
			 * / `http_allowed_safe_ports` 过滤器。
			 *
			 * @since endpoint schema v2
			 *
			 * @param bool   $allow 是否允许.
			 * @param string $url   待校验地址.
			 * @param string $id    Provider ID.
			 */
			$allow_http = (bool) apply_filters( 'wpmind_allow_http_base_url', false, $url, $id );
			if ( 'http' !== $scheme || ! $allow_http ) {
				return '';
			}
		}

		if ( $strict && function_exists( 'wp_http_validate_url' ) && false === wp_http_validate_url( $url ) ) {
			return '';
		}

		return self::untrailing( $url );
	}

	/**
	 * 决定「测试连接」用哪个地址和哪个 key（B13）。
	 *
	 * - 没有提交新 key（用已保存的 key）：只能测已保存的地址。提交的 URL 和已保存的
	 *   custom_base_url 不一致就拒绝，防止已保存的 key 被发到任意地址。
	 * - 提交了新 key：可以连同提交的 URL 一起测，URL 走保存时同样的校验。
	 *
	 * @param string $id         Provider ID.
	 * @param string $posted_key POST 里的 key（可能为空）.
	 * @param string $posted_url POST 里的 URL（可能为空）.
	 * @return array{base_url:string, api_key:string, uses_saved_key:bool}|WP_Error
	 */
	public function connection_test_target( string $id, string $posted_key, string $posted_url ) {
		if ( ! ProviderDefinitions::has( $id ) ) {
			return new WP_Error( 'wpmind_unknown_provider', __( '服务不存在', 'wpmind' ) );
		}

		$endpoint   = $this->get( $id );
		$posted_key = trim( $posted_key );
		$posted_url = trim( $posted_url );

		if ( '' === $posted_key ) {
			if ( ! $endpoint->has_api_key() ) {
				return new WP_Error( 'wpmind_api_key_missing', __( '请先配置 API Key', 'wpmind' ) );
			}
			if ( '' !== $posted_url && self::untrailing( $posted_url ) !== self::untrailing( $endpoint->custom_base_url ) ) {
				return new WP_Error(
					'wpmind_unsaved_base_url',
					__( '使用已保存的 API Key 时只能测试已保存的 Base URL。请先保存设置，或同时填写新的 API Key。', 'wpmind' )
				);
			}
			return [
				'base_url'       => $this->resolved_base_url( $id ),
				'api_key'        => $endpoint->api_key,
				'uses_saved_key' => true,
			];
		}

		$base_url = $this->resolved_base_url( $id );
		if ( '' !== $posted_url ) {
			$base_url = self::validate_base_url( $posted_url, $id, true );
			if ( '' === $base_url ) {
				return new WP_Error( 'wpmind_invalid_base_url', __( 'Base URL 无效：只允许 https 地址，且不能带查询参数。', 'wpmind' ) );
			}
		}

		return [
			'base_url'       => $base_url,
			'api_key'        => $posted_key,
			'uses_saved_key' => false,
		];
	}

	/**
	 * 去掉结尾斜杠。
	 *
	 * @param string $url 地址.
	 * @return string
	 */
	private static function untrailing( string $url ): string {
		return rtrim( $url, '/\\' );
	}
}
