<?php
/**
 * 文派心思官方算力（Wenpai Cloud）与代理商锁 Key 配置中心。
 *
 * 负责官方托管算力端点参数、令牌派生、状态聚合、响应头余额回写，
 * 以及代理商/运维在 wp-config.php 中锁定 Provider 与注入 Key 的统一读取。
 *
 * @package WPMind\Provider
 * @since 0.14.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

/**
 * 官方托管算力与锁定凭证辅助类。
 *
 * @since 0.14.0
 */
final class WenpaiCloud {

	/**
	 * 官方算力 Provider 标识。
	 */
	public const PROVIDER_ID = 'wenpai_cloud';

	/**
	 * 官方算力默认网关地址。
	 */
	public const DEFAULT_BASE_URL = 'https://mind-api.wenpai.net/v1';

	/**
	 * 心思点数补充页地址。
	 */
	public const TOPUP_URL = 'https://wenpai.net/mind-credits';

	/**
	 * 启用状态 option 名。
	 */
	public const OPTION_ENABLED = 'wpmind_cloud_enabled';

	/**
	 * 混用路由策略 option 名（primary / fallback / capability）。
	 */
	public const OPTION_MODE = 'wpmind_cloud_mode';

	/**
	 * 自定义网关地址 option 名。
	 */
	public const OPTION_BASE_URL = 'wpmind_cloud_base_url';

	/**
	 * 混用模式：全托管优先（优先走心思 AI，异常可回退自备 Key）。
	 */
	public const MODE_PRIMARY = 'primary';

	/**
	 * 混用模式：自备 Key 优先，心思 AI 作为熔断/缺省备援。
	 */
	public const MODE_FALLBACK = 'fallback';

	/**
	 * 混用模式：仅补齐缺失能力（常规文本走自备 Key，视觉/图像/高阶推理走心思 AI）。
	 */
	public const MODE_CAPABILITY = 'capability';

	/**
	 * 本地回退令牌 option 名。
	 */
	public const OPTION_TOKEN = 'wpmind_cloud_token';

	/**
	 * 心思点数缓存 option 名（autoload=false）。
	 */
	public const OPTION_CREDITS = 'wpmind_cloud_credits';

	/**
	 * 获取官方网关 Base URL（去除结尾斜杠）。
	 *
	 * 优先级：常量 WPMIND_CLOUD_BASE_URL > option wpmind_cloud_base_url > filter wpmind_cloud_base_url > 默认地址。
	 *
	 * @return string
	 */
	public static function base_url(): string {
		if ( defined( 'WPMIND_CLOUD_BASE_URL' ) ) {
			$const_url = constant( 'WPMIND_CLOUD_BASE_URL' );
			if ( is_string( $const_url ) && '' !== trim( $const_url ) ) {
				return rtrim( trim( $const_url ), '/' );
			}
		}

		$option_url = get_option( self::OPTION_BASE_URL, '' );
		$default    = is_string( $option_url ) && '' !== trim( $option_url )
			? trim( $option_url )
			: self::DEFAULT_BASE_URL;

		$filtered = apply_filters( 'wpmind_cloud_base_url', $default );
		$url      = is_string( $filtered ) && '' !== trim( $filtered )
			? trim( $filtered )
			: $default;

		return rtrim( $url, '/' );
	}

	/**
	 * 获取心思 AI 与自备 Key 的混用路由模式。
	 *
	 * @return string primary|fallback|capability
	 */
	public static function routing_mode(): string {
		$raw = get_option( self::OPTION_MODE, self::MODE_PRIMARY );
		$raw = is_string( $raw ) ? sanitize_key( $raw ) : self::MODE_PRIMARY;

		if ( in_array( $raw, [ self::MODE_PRIMARY, self::MODE_FALLBACK, self::MODE_CAPABILITY ], true ) ) {
			return $raw;
		}

		return self::MODE_PRIMARY;
	}

	/**
	 * 获取三种混用模式的展示文案。
	 *
	 * @return array<string, array{label: string, desc: string}>
	 */
	public static function mode_options(): array {
		return [
			self::MODE_PRIMARY    => [
				'label' => __( '全托管优先', 'wpmind' ),
				'desc'  => __( '优先走心思 AI，免维护第三方 Key；异常时自动回退自备 Key。', 'wpmind' ),
			],
			self::MODE_FALLBACK   => [
				'label' => __( '自备优先 · 心思备援', 'wpmind' ),
				'desc'  => __( '平时走自备 Key，仅在主服务熔断或限流时由心思 AI 自动顶上。', 'wpmind' ),
			],
			self::MODE_CAPABILITY => [
				'label' => __( '按能力补充', 'wpmind' ),
				'desc'  => __( '常规文本走自备 Key，视觉、生图与深度推理自动走心思 AI。', 'wpmind' ),
			],
		];
	}

	/**
	 * 获取计费单位文案。
	 *
	 * @return string
	 */
	public static function unit_label(): string {
		return __( '心思点数', 'wpmind' );
	}

	/**
	 * 判断官方算力是否已启用。
	 *
	 * 优先级：常量 WPMIND_CLOUD_ENABLED > option wpmind_cloud_enabled === '1'。
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( defined( 'WPMIND_CLOUD_ENABLED' ) ) {
			return (bool) constant( 'WPMIND_CLOUD_ENABLED' );
		}

		$raw = get_option( self::OPTION_ENABLED, '0' );
		return '1' === $raw || 1 === $raw || true === $raw;
	}

	/**
	 * 派生本次请求使用的官方算力令牌。
	 *
	 * 优先级：常量 WPMIND_CLOUD_TOKEN > filter wenpai_sign_cloud_request > option wpmind_cloud_token。
	 *
	 * @return string
	 */
	public static function derive_request_token(): string {
		if ( defined( 'WPMIND_CLOUD_TOKEN' ) ) {
			$const_token = constant( 'WPMIND_CLOUD_TOKEN' );
			if ( is_string( $const_token ) && '' !== trim( $const_token ) ) {
				return trim( $const_token );
			}
		}

		$signed = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite hook.
			'wenpai_sign_cloud_request',
			'',
			[
				'scope'  => 'wpmind:gateway',
				'target' => self::base_url(),
			]
		);

		if ( is_string( $signed ) && '' !== trim( $signed ) ) {
			return trim( $signed );
		}

		if ( is_array( $signed ) && isset( $signed['token'] ) && is_string( $signed['token'] ) && '' !== trim( $signed['token'] ) ) {
			return trim( $signed['token'] );
		}

		$option_token = get_option( self::OPTION_TOKEN, '' );
		return is_string( $option_token ) ? trim( $option_token ) : '';
	}

	/**
	 * 判断官方算力当前是否可用（已启用且有可用令牌）。
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( '' !== self::locked_api_key( self::PROVIDER_ID ) ) {
			return true;
		}

		return '' !== self::derive_request_token();
	}

	/**
	 * 根据 Provider ID 返回用量事件归属方（wenpai_cloud 或 byok）。
	 *
	 * @param string $provider Provider ID.
	 * @return string
	 */
	public static function payer_for( string $provider ): string {
		return self::PROVIDER_ID === $provider ? 'wenpai_cloud' : 'byok';
	}

	/**
	 * 获取官方算力状态摘要（渲染期零远程请求）。
	 *
	 * @return array{
	 *     enabled: bool,
	 *     mode: string,
	 *     bound: bool,
	 *     managed_by_suite: bool,
	 *     site_uuid: string,
	 *     credits_balance: ?int,
	 *     credits_used_month: int,
	 *     credits_limit_month: ?int,
	 *     starter_credits: int,
	 *     unit_label: string,
	 *     base_url: string,
	 *     bind_url: string,
	 *     topup_url: string
	 * }
	 */
	public static function get_status(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite hook.
		$binding = apply_filters( 'wenpai_site_binding', [] );
		$binding = is_array( $binding ) ? $binding : [];
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite hook.
		$entitlements = apply_filters( 'wenpai_site_entitlements', [] );
		$entitlements = is_array( $entitlements ) ? $entitlements : [];
		$credits      = get_option( self::OPTION_CREDITS, [] );
		$credits      = is_array( $credits ) ? $credits : [];

		$site_uuid        = isset( $binding['site_uuid'] ) && is_string( $binding['site_uuid'] ) ? trim( $binding['site_uuid'] ) : '';
		$bound            = ! empty( $binding['bound'] ) || '' !== $site_uuid;
		$managed_by_suite = ! empty( $binding['managed'] ) || ! empty( $binding['managed_by_suite'] );

		$entitlement_mind = is_array( $entitlements['wpmind'] ?? null ) ? $entitlements['wpmind'] : [];

		$credits_balance = self::extract_nullable_int(
			[
				$credits['remaining'] ?? null,
				$credits['balance'] ?? null,
				$credits['credits_balance'] ?? null,
				$entitlements['credits_balance'] ?? null,
				$entitlements['mind_credits_balance'] ?? null,
				$entitlement_mind['credits_balance'] ?? null,
				$entitlement_mind['balance'] ?? null,
			]
		);

		$credits_limit_month = self::extract_nullable_int(
			[
				$credits['limit'] ?? null,
				$credits['limit_month'] ?? null,
				$credits['credits_limit_month'] ?? null,
				$entitlements['credits_limit_month'] ?? null,
				$entitlements['mind_credits_limit'] ?? null,
				$entitlement_mind['credits_limit_month'] ?? null,
				$entitlement_mind['limit_month'] ?? null,
			]
		);

		$used_raw           = self::extract_nullable_int(
			[
				$credits['used_month'] ?? null,
				$credits['credits_used_month'] ?? null,
				$entitlements['credits_used_month'] ?? null,
				$entitlement_mind['credits_used_month'] ?? null,
			]
		);
		$credits_used_month = null !== $used_raw ? max( 0, $used_raw ) : 0;

		if ( class_exists( 'Wenpai_Admin_Menu' ) ) {
			$default_bind_url = \Wenpai_Admin_Menu::url( 'wpcy-connect' );
		} elseif ( function_exists( 'menu_page_url' ) ) {
			$default_bind_url = (string) menu_page_url( 'wpcy-connect', false );
		} else {
			$default_bind_url = '';
		}
		if ( '' === $default_bind_url && function_exists( 'add_query_arg' ) ) {
			$default_bind_url = add_query_arg( 'page', 'wpcy-connect', function_exists( 'self_admin_url' ) ? self_admin_url() : '' );
		}
		$bind_url = isset( $binding['manage_url'] ) && is_string( $binding['manage_url'] ) && '' !== trim( $binding['manage_url'] )
			? trim( $binding['manage_url'] )
			: $default_bind_url;

		$starter_credits = (int) apply_filters( 'wpmind_cloud_starter_credits', 500 );

		return [
			'enabled'             => self::is_enabled(),
			'mode'                => self::routing_mode(),
			'bound'               => $bound,
			'managed_by_suite'    => $managed_by_suite,
			'site_uuid'           => $site_uuid,
			'credits_balance'     => $credits_balance,
			'credits_used_month'  => $credits_used_month,
			'credits_limit_month' => $credits_limit_month,
			'starter_credits'     => max( 0, $starter_credits ),
			'unit_label'          => self::unit_label(),
			'base_url'            => self::base_url(),
			'bind_url'            => $bind_url,
			'topup_url'           => self::TOPUP_URL,
		];
	}

	/**
	 * 从官方网关响应头解析心思点数余额并持久化到 option（autoload=false）。
	 *
	 * 兼容普通数组与 Requests CaseInsensitiveDictionary。
	 *
	 * @param mixed $headers HTTP 响应头集合.
	 * @return void
	 */
	public static function update_balance_from_headers( $headers ): void {
		$map = self::normalize_headers( $headers );

		$has_remaining = array_key_exists( 'x-wpmind-credits-remaining', $map ) && is_numeric( $map['x-wpmind-credits-remaining'] );
		$has_limit     = array_key_exists( 'x-wpmind-credits-limit', $map ) && is_numeric( $map['x-wpmind-credits-limit'] );

		if ( ! $has_remaining && ! $has_limit ) {
			return;
		}

		$stored = get_option( self::OPTION_CREDITS, [] );
		$stored = is_array( $stored ) ? $stored : [];

		if ( $has_remaining ) {
			$stored['remaining'] = max( 0, (int) $map['x-wpmind-credits-remaining'] );
		}

		if ( $has_limit ) {
			$stored['limit'] = max( 0, (int) $map['x-wpmind-credits-limit'] );
		}

		$stored['updated_at'] = time();

		update_option( self::OPTION_CREDITS, $stored, false );
	}

	/**
	 * 判断指定 Provider 是否被站点配置锁定（代理商/wp-config 锁 Key）。
	 *
	 * @param string $id Provider ID.
	 * @return bool
	 */
	public static function is_provider_locked( string $id ): bool {
		$id = trim( $id );
		if ( '' === $id ) {
			return false;
		}

		$locked = self::base_locked_providers();

		/**
		 * 过滤被站点配置锁定的 Provider 列表或 Provider => Key 映射。
		 *
		 * @since 0.14.0
		 * @param array<int|string, mixed> $locked 锁定的 Provider ID 列表.
		 */
		$filtered = apply_filters( 'wpmind_locked_providers', $locked );
		// @phpstan-ignore function.alreadyNarrowedType
		if ( ! is_array( $filtered ) ) {
			return false;
		}

		foreach ( $filtered as $key => $value ) {
			if ( is_int( $key ) && is_string( $value ) ) {
				$item = trim( $value );
				if ( '*' === $item || 'all' === $item || $item === $id ) {
					return true;
				}
				continue;
			}

			if ( is_string( $key ) && trim( $key ) === $id && false !== $value && '' !== $value && null !== $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 读取被站点配置锁定的 API Key。
	 *
	 * 优先级：WPMIND_API_KEYS 常量 > wpmind_locked_providers 关联数组值。
	 *
	 * @param string $id Provider ID.
	 * @return string 未锁定时返回空串。
	 */
	public static function locked_api_key( string $id ): string {
		$id = trim( $id );
		if ( '' === $id ) {
			return '';
		}

		if ( defined( 'WPMIND_API_KEYS' ) ) {
			$const_keys = constant( 'WPMIND_API_KEYS' );
			if ( is_array( $const_keys ) && isset( $const_keys[ $id ] ) && is_string( $const_keys[ $id ] ) ) {
				$key = trim( $const_keys[ $id ] );
				if ( '' !== $key ) {
					return $key;
				}
			}
		}

		$filtered = apply_filters( 'wpmind_locked_providers', self::base_locked_providers() );
		if ( is_array( $filtered ) && array_key_exists( $id, $filtered ) && is_string( $filtered[ $id ] ) ) {
			$key = trim( $filtered[ $id ] );
			if ( '' !== $key ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * 收集常量中声明的锁定 Provider 列表。
	 *
	 * @return list<string>
	 */
	private static function base_locked_providers(): array {
		$locked = [];

		if ( defined( 'WPMIND_LOCKED_PROVIDERS' ) ) {
			$raw = constant( 'WPMIND_LOCKED_PROVIDERS' );
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}
			if ( is_array( $raw ) ) {
				foreach ( $raw as $item ) {
					if ( is_string( $item ) && '' !== trim( $item ) ) {
						$locked[] = trim( $item );
					}
				}
			}
		}

		if ( defined( 'WPMIND_API_KEYS' ) ) {
			$const_keys = constant( 'WPMIND_API_KEYS' );
			if ( is_array( $const_keys ) ) {
				foreach ( $const_keys as $provider_id => $api_key ) {
					if ( is_string( $provider_id ) && is_string( $api_key ) && '' !== trim( $provider_id ) && '' !== trim( $api_key ) ) {
						$locked[] = trim( $provider_id );
					}
				}
			}
		}

		return array_values( array_unique( $locked ) );
	}

	/**
	 * 从候选值中提取第一个数值并转为 int，全部为空时返回 null。
	 *
	 * @param array<int, mixed> $candidates 候选值列表.
	 * @return int|null
	 */
	private static function extract_nullable_int( array $candidates ): ?int {
		foreach ( $candidates as $value ) {
			if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
				return (int) $value;
			}
		}
		return null;
	}

	/**
	 * 将数组或 CaseInsensitiveDictionary 规范化为小写键 => 标量值映射。
	 *
	 * @param mixed $headers 原始响应头.
	 * @return array<string, string>
	 */
	private static function normalize_headers( $headers ): array {
		$entries = [];

		if ( is_array( $headers ) ) {
			$entries = $headers;
		} elseif ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$all = $headers->getAll();
			if ( is_array( $all ) ) {
				$entries = $all;
			}
		} elseif ( $headers instanceof \Traversable ) {
			foreach ( $headers as $k => $v ) {
				$entries[ $k ] = $v;
			}
		}

		$map = [];
		foreach ( $entries as $key => $value ) {
			$lower = strtolower( (string) $key );
			if ( is_array( $value ) ) {
				$value = end( $value );
			}
			if ( is_scalar( $value ) ) {
				$map[ $lower ] = trim( (string) $value );
			}
		}

		// 兜底：若对象仅实现了 ArrayAccess（如部分自定义字典桩），直接按目标键读取.
		if ( [] === $map && $headers instanceof \ArrayAccess ) {
			foreach ( [ 'x-wpmind-credits-remaining', 'x-wpmind-credits-limit' ] as $target ) {
				if ( isset( $headers[ $target ] ) ) {
					$val = $headers[ $target ];
					if ( is_array( $val ) ) {
						$val = end( $val );
					}
					if ( is_scalar( $val ) ) {
						$map[ $target ] = trim( (string) $val );
					}
				}
			}
		}

		return $map;
	}
}
