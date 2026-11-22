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
	 * 独立站点网页授权绑定入口地址（未安装 WPCY 时使用）。
	 */
	public const CONNECT_URL = 'https://wenpai.net/connect/wpmind';

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
	 * 独立站点 UUID option 名（未安装 WPCY 时按需生成）。
	 */
	public const OPTION_SITE_UUID = 'wpmind_cloud_site_uuid';

	/**
	 * 独立授权一次性 state transient 前缀。
	 */
	public const CONNECT_STATE_PREFIX = 'wpmind_cloud_connect_state_';

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
	 * 判断是否存在独立站点授权令牌（常量或本地 option）。
	 *
	 * @return bool
	 */
	public static function has_standalone_token(): bool {
		if ( defined( 'WPMIND_CLOUD_TOKEN' ) ) {
			$const_token = constant( 'WPMIND_CLOUD_TOKEN' );
			if ( is_string( $const_token ) && '' !== trim( $const_token ) ) {
				return true;
			}
		}

		$option_token = get_option( self::OPTION_TOKEN, '' );
		return is_string( $option_token ) && '' !== trim( $option_token );
	}

	/**
	 * 返回独立授权令牌的脱敏指纹（不包含原文）。
	 *
	 * @return string
	 */
	public static function standalone_token_fingerprint(): string {
		$raw = '';
		if ( defined( 'WPMIND_CLOUD_TOKEN' ) ) {
			$const_token = constant( 'WPMIND_CLOUD_TOKEN' );
			if ( is_string( $const_token ) && '' !== trim( $const_token ) ) {
				$raw = trim( $const_token );
			}
		}
		if ( '' === $raw ) {
			$opt = get_option( self::OPTION_TOKEN, '' );
			if ( is_string( $opt ) && '' !== trim( $opt ) ) {
				$raw = trim( $opt );
			}
		}

		return '' !== $raw ? Secrets::fingerprint( $raw ) : '';
	}

	/**
	 * 保存独立站点授权令牌（禁止 autoload，避免敏感信息常驻全站内存）。
	 *
	 * @param string $token 授权令牌原文.
	 * @return bool
	 */
	public static function save_standalone_token( string $token ): bool {
		$token = trim( $token );
		if ( '' === $token ) {
			return false;
		}
		update_option( self::OPTION_TOKEN, $token, false );
		return true;
	}

	/**
	 * 清除独立站点授权令牌与点数缓存。
	 *
	 * @return void
	 */
	public static function clear_standalone_binding(): void {
		delete_option( self::OPTION_TOKEN );
		delete_option( self::OPTION_CREDITS );
	}

	/**
	 * 获取或生成独立站点 UUID（优先复用 WPCY 套件提供的 UUID）。
	 *
	 * @param string $suite_uuid WPCY 提供的 site_uuid（可为空）.
	 * @return string
	 */
	public static function ensure_site_uuid( string $suite_uuid = '' ): string {
		$suite_uuid = trim( $suite_uuid );
		if ( '' !== $suite_uuid ) {
			return $suite_uuid;
		}

		$stored = get_option( self::OPTION_SITE_UUID, '' );
		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return trim( $stored );
		}

		$uuid = self::generate_uuid_v4();
		update_option( self::OPTION_SITE_UUID, $uuid, false );
		return $uuid;
	}

	/**
	 * 构造独立站点跳转 wenpai.net 网页授权绑定地址。
	 *
	 * @param string $state      一次性防 CSRF 状态值.
	 * @param string $return_url WP 后台回调地址.
	 * @return string
	 */
	public static function connect_url( string $state, string $return_url ): string {
		$base = apply_filters( 'wpmind_cloud_connect_url', self::CONNECT_URL );
		$base = is_string( $base ) && '' !== trim( $base ) ? trim( $base ) : self::CONNECT_URL;

		$site_uuid = self::ensure_site_uuid();
		$site_url  = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$version   = defined( 'WPMIND_VERSION' ) ? (string) constant( 'WPMIND_VERSION' ) : '';

		$params = [
			'plugin'         => 'wpmind',
			'site_url'       => $site_url,
			'site_uuid'      => $site_uuid,
			'site_name'      => $site_name,
			'return_url'     => $return_url,
			'state'          => $state,
			'plugin_version' => $version,
		];

		if ( function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( $params, $base );
		}

		$sep = false === strpos( $base, '?' ) ? '?' : '&';
		return $base . $sep . http_build_query( $params );
	}

	/**
	 * 构造带站点上下文的心思点数充值/了解网址（一律跳转 wenpai.net 网站）。
	 *
	 * @param string $site_uuid 当前站点 UUID（可选）.
	 * @return string
	 */
	public static function topup_url( string $site_uuid = '' ): string {
		$base = apply_filters( 'wpmind_cloud_topup_url', self::TOPUP_URL );
		$base = is_string( $base ) && '' !== trim( $base ) ? trim( $base ) : '';
		if ( '' === $base ) {
			return '';
		}

		$params = [ 'from' => 'wpmind' ];
		$uuid   = trim( $site_uuid );
		if ( '' !== $uuid ) {
			$params['site_uuid'] = $uuid;
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( $params, $base );
		}

		$sep = false === strpos( $base, '?' ) ? '?' : '&';
		return $base . $sep . http_build_query( $params );
	}

	/**
	 * 用网站授权回调的临时 code 向官方网关换取站点长期令牌（服务端直连，令牌不落浏览器地址栏）。
	 *
	 * @param string $code      一次性授权码.
	 * @param string $site_uuid 站点 UUID（可选）.
	 * @return array{token: string, site_uuid: string, credits_balance: ?int, credits_limit_month: ?int}|\WP_Error
	 */
	public static function exchange_connect_code( string $code, string $site_uuid = '' ) {
		$code = trim( $code );
		if ( '' === $code ) {
			return new \WP_Error( 'wpmind_cloud_missing_code', __( '缺少授权码。', 'wpmind' ) );
		}

		$endpoint = self::base_url() . '/auth/exchange';
		$payload  = [
			'code'           => $code,
			'site_uuid'      => self::ensure_site_uuid( $site_uuid ),
			'site_url'       => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '',
			'plugin_version' => defined( 'WPMIND_VERSION' ) ? (string) constant( 'WPMIND_VERSION' ) : '',
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) ) {
			return new \WP_Error(
				'wpmind_cloud_exchange_failed',
				__( '文派云授权换取令牌失败，请重试。', 'wpmind' ),
				[ 'status' => $status_code ]
			);
		}

		$token = isset( $body['token'] ) && is_string( $body['token'] ) ? trim( $body['token'] ) : '';
		if ( '' === $token ) {
			return new \WP_Error( 'wpmind_cloud_empty_token', __( '文派云未返回有效令牌。', 'wpmind' ) );
		}

		return [
			'token'               => $token,
			'site_uuid'           => isset( $body['site_uuid'] ) && is_string( $body['site_uuid'] ) ? trim( $body['site_uuid'] ) : '',
			'credits_balance'     => isset( $body['credits_balance'] ) && is_numeric( $body['credits_balance'] ) ? max( 0, (int) $body['credits_balance'] ) : null,
			'credits_limit_month' => isset( $body['credits_limit_month'] ) && is_numeric( $body['credits_limit_month'] ) ? max( 0, (int) $body['credits_limit_month'] ) : null,
		];
	}

	/**
	 * 生成 RFC 4122 UUID v4。
	 *
	 * @return string
	 */
	private static function generate_uuid_v4(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return (string) wp_generate_uuid4();
		}

		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );

		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20, 12 )
		);
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
	 *     binding_source: string,
	 *     managed_by_suite: bool,
	 *     site_uuid: string,
	 *     token_fingerprint: string,
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

		$suite_uuid       = isset( $binding['site_uuid'] ) && is_string( $binding['site_uuid'] ) ? trim( $binding['site_uuid'] ) : '';
		$managed_by_suite = ! empty( $binding['managed'] ) || ! empty( $binding['managed_by_suite'] );
		$suite_bound      = array_key_exists( 'bound', $binding )
			? (bool) $binding['bound']
			: '' !== $suite_uuid;

		$standalone_token = self::has_standalone_token();
		if ( $managed_by_suite && $suite_bound ) {
			$binding_source = 'suite';
		} elseif ( ! $managed_by_suite && $standalone_token ) {
			$binding_source = 'standalone';
		} elseif ( $suite_bound ) {
			$binding_source = 'suite';
		} else {
			$binding_source = 'none';
		}

		$bound = 'none' !== $binding_source;

		$stored_uuid = get_option( self::OPTION_SITE_UUID, '' );
		$site_uuid   = '' !== $suite_uuid
			? $suite_uuid
			: ( is_string( $stored_uuid ) ? trim( $stored_uuid ) : '' );

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

		if ( $managed_by_suite && isset( $binding['manage_url'] ) && is_string( $binding['manage_url'] ) && '' !== trim( $binding['manage_url'] ) ) {
			$bind_url = trim( $binding['manage_url'] );
		} elseif ( isset( $binding['manage_url'] ) && is_string( $binding['manage_url'] ) && '' !== trim( $binding['manage_url'] ) ) {
			$bind_url = trim( $binding['manage_url'] );
		} elseif ( $managed_by_suite ) {
			if ( class_exists( 'Wenpai_Admin_Menu' ) ) {
				$bind_url = \Wenpai_Admin_Menu::url( 'wpcy-services' );
			} elseif ( function_exists( 'menu_page_url' ) ) {
				$bind_url = (string) menu_page_url( 'wpcy-services', false );
			} else {
				$bind_url = '';
			}
		} elseif ( function_exists( 'admin_url' ) && function_exists( 'wp_nonce_url' ) ) {
			// 未安装 WPCY：走本插件 admin-post 发起 wenpai.net 一次性 state 授权回跳.
			$bind_url = (string) wp_nonce_url(
				admin_url( 'admin-post.php?action=wpmind_cloud_connect' ),
				'wpmind_cloud_connect'
			);
		} else {
			$bind_url = self::CONNECT_URL;
		}

		$starter_credits = (int) apply_filters( 'wpmind_cloud_starter_credits', 500 );

		return [
			'enabled'             => self::is_enabled(),
			'mode'                => self::routing_mode(),
			'bound'               => $bound,
			'binding_source'      => $binding_source,
			'managed_by_suite'    => $managed_by_suite,
			'site_uuid'           => $site_uuid,
			'token_fingerprint'   => 'standalone' === $binding_source ? self::standalone_token_fingerprint() : '',
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
