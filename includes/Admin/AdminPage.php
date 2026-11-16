<?php
/**
 * Admin page rendering and settings.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

use WPMind\Provider\EndpointRepository;
use WPMind\Provider\KeyRotation;
use WPMind\Provider\Secrets;
use WPMind\Provider\ProviderDefinitions;
use WPMind\WPMind;

/**
 * Class AdminPage
 */
final class AdminPage {

	/**
	 * Singleton instance.
	 *
	 * @var AdminPage|null
	 */
	private static ?AdminPage $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AdminPage
	 */
	public static function instance(): AdminPage {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * 一级视图（?page=wpmind&tab=）。模块设置走 tab=module&module=<id>。
	 *
	 * @var list<string>
	 */
	private const VIEWS = [
		'overview',
		'services',
		'routing',
		'modules',
		'module',
		'workflow',
		'tools',
		'help',
	];

	/**
	 * 尚未 kit 化的模块 id（module 视图按模块门控 legacy 资产）。
	 *
	 * @var list<string>
	 */
	private const LEGACY_MODULES = [];

	/**
	 * 模块设置模板是否仍走 legacy 资产。
	 *
	 * @param string $module_id Module id.
	 * @return bool
	 */
	public static function is_legacy_module( string $module_id ): bool {
		return in_array( $module_id, self::LEGACY_MODULES, true );
	}

	/**
	 * 旧 hash tab → 新视图（外部链接/书签兼容）。
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_TAB_MAP = [
		'overview'          => 'overview',
		'services'          => 'services',
		'images'            => 'services',
		'routing'           => 'routing',
		'analytics'         => 'analytics',
		'dashboard'         => 'analytics',
		'budget'            => 'analytics',
		'workflow'          => 'workflow',
		'modules'           => 'modules',
		'geo'               => 'module',
		'api-gateway'       => 'module',
		'exact-cache'       => 'module',
		'media-intelligence'=> 'module',
		'auto-meta'         => 'module',
		'cost-control'      => 'module',
	];

	/**
	 * 后台 URL（kit Menu 优先，退回 options 页 slug）。
	 *
	 * @param array<string, string> $args Query args.
	 * @return string
	 */
	public static function url( array $args = [] ): string {
		if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return Wenpai_Admin_Menu::url( 'wpmind', $args );
		}
		$base = menu_page_url( 'wpmind', '' );
		return $base ? add_query_arg( $args, $base ) : '';
	}

	/**
	 * 当前请求解析出的视图 id。
	 *
	 * @return string
	 */
	public static function current_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$raw = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$view = self::LEGACY_TAB_MAP[ $raw ] ?? $raw;
		if ( ! in_array( $view, self::VIEWS, true ) ) {
			return 'overview';
		}
		if ( 'module' === $view ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$module = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
			if ( '' === $module && isset( self::LEGACY_TAB_MAP[ $raw ] ) && 'module' === self::LEGACY_TAB_MAP[ $raw ] ) {
				$module = $raw; // 旧链接 ?tab=geo 直接当模块 id。
			}
			$loader  = \WPMind\Core\ModuleLoader::instance();
			$modules = $loader->get_modules();
			if ( '' === $module || ! isset( $modules[ $module ] ) ) {
				return 'modules';
			}
		}
		return $view;
	}

	/**
	 * 当前模块设置视图的模块 id（tab=module&module=<id> 或旧 tab=<module-id>）。
	 *
	 * @return string
	 */
	public static function current_module(): string {
		if ( 'module' !== self::current_view() ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$module = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		if ( '' === $module ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			if ( isset( self::LEGACY_TAB_MAP[ $raw ] ) && 'module' === self::LEGACY_TAB_MAP[ $raw ] ) {
				$module = $raw;
			}
		}
		return $module;
	}

	/**
	 * 添加管理菜单（kit Settings 子菜单；kit 未加载时退回 options 页）
	 */
	public function add_admin_menu(): void {
		if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
			Wenpai_Admin_Menu::register(
				[
					'slug'    => 'wpmind',
					'label'   => __( '心思', 'wpmind' ),
					'title'   => __( '文派心思', 'wpmind' ),
					'render'  => [ $this, 'render_settings_page' ],
				]
			);
			return;
		}
		add_options_page(
			__( '文派心思', 'wpmind' ),
			__( '心思', 'wpmind' ),
			'manage_options',
			'wpmind',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * 注册设置
	 */
	public function register_settings(): void {
		register_setting(
			'wpmind_settings',
			'wpmind_custom_endpoints',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_endpoints' ],
				'default'           => [],
			]
		);

		register_setting(
			'wpmind_settings',
			'wpmind_request_timeout',
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_timeout' ],
				'default'           => 60,
			]
		);

		register_setting(
			'wpmind_settings',
			'wpmind_exact_cache_enabled',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_exact_cache_enabled' ],
				'default'           => '1',
			]
		);

		register_setting(
			'wpmind_settings',
			'wpmind_exact_cache_default_ttl',
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_exact_cache_ttl' ],
				'default'           => 900,
			]
		);

		register_setting(
			'wpmind_settings',
			'wpmind_exact_cache_max_entries',
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_exact_cache_max_entries' ],
				'default'           => 500,
			]
		);

		register_setting(
			'wpmind_settings',
			'wpmind_default_provider',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_default_provider' ],
				'default'           => '',
			]
		);

		// 图像服务设置
		register_setting(
			'wpmind_image_settings',
			'wpmind_image_endpoints',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_image_endpoints' ],
				'default'           => [],
			]
		);

		register_setting(
			'wpmind_image_settings',
			'wpmind_default_image_provider',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		// GEO settings are managed by GeoModule via AJAX.
	}

	/**
	 * 清理默认提供者（允许列表校验）
	 *
	 * @param mixed $input 输入值
	 * @return string 清理后的值
	 * @since 1.2.0
	 */
	public function sanitize_default_provider( $input ): string {
		$key = sanitize_key( $input );

		// 允许空值
		if ( empty( $key ) ) {
			return '';
		}

		// 只允许已定义的端点
		$allowed = array_keys( WPMind::instance()->get_default_endpoints() );
		return in_array( $key, $allowed, true ) ? $key : '';
	}

	/**
	 * 清理端点配置
	 *
	 * @param mixed $input 输入数据
	 * @return array 清理后的数据
	 * @since 1.1.0
	 */
	public function sanitize_endpoints( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$repository = new EndpointRepository();
		$sanitized  = [];
		$providers  = [];

		// 和 get_default_endpoints() 的键相同，直接用元数据定义，避免依赖插件主类实例.
		foreach ( ProviderDefinitions::ids() as $key ) {
			$endpoint_input = is_array( $input[ $key ] ?? null ) ? $input[ $key ] : [];
			$current        = $repository->get( $key );
			$previous       = [
				'custom_base_url'   => $current->custom_base_url,
				'models'            => $current->models,
				'default_model'     => $current->default_model,
				'key_meta'          => $current->key_meta,
				'needs_reconfigure' => $current->needs_reconfigure,
			];
			$api_key        = $this->sanitize_api_key( $endpoint_input, $key );

			// v2 只保存用户的选择（写进 wpmind_custom_endpoints_v2，不含 key）；
			// 原 option 写回滚镜像（name/base_url/models/enabled/api_key），旧版本能直接读.
			$entry = [
				'enabled'         => ! empty( $endpoint_input['enabled'] ),
				'custom_base_url' => $this->sanitize_custom_base_url( $endpoint_input, $key, $previous['custom_base_url'] ),
				// 模型选择 UI 在 PR2；这里沿用库里已有的用户选择（空 = 目录默认）.
				'models'          => $previous['models'],
				'default_model'   => $previous['default_model'],
			];
			// key_meta: rewrite on fingerprint change; drop when cleared; else keep or lazy-fill.
			$old_fp = Secrets::fingerprint( $current->api_key );
			$new_fp = Secrets::fingerprint( $api_key );
			if ( '' !== $api_key ) {
				if ( $old_fp !== $new_fp || [] === $previous['key_meta'] ) {
					$entry['key_meta'] = KeyRotation::make_meta( $api_key );
				} else {
					$entry['key_meta'] = $previous['key_meta'];
				}
			}

			// 「需要重新配置」在管理员填了新 key、清除 key 或填了自定义地址后解除.
			$new_key = '' !== trim( (string) ( $endpoint_input['api_key'] ?? '' ) );
			if ( $previous['needs_reconfigure']
				&& ! $new_key
				&& empty( $endpoint_input['clear_api_key'] )
				&& '' === $entry['custom_base_url'] ) {
				$entry['needs_reconfigure'] = true;
			}

			$providers[ $key ] = $entry;
			$sanitized[ $key ] = EndpointRepository::mirror_entry( $key, $entry, $api_key );
		}

		// 自己写的镜像先记下 hash，admin_init 时就不会被当成「旧版本改过」而重新迁移.
		$repository->save_v2( $providers, EndpointRepository::legacy_hash( $sanitized ) );

		return $sanitized;
	}

	/**
	 * 清理自定义 Base URL（B1）
	 *
	 * 只允许 https；http 需要 `wpmind_allow_http_base_url` 过滤器放开。
	 * 提交了不合法的地址时提示错误，并保留原来保存的值。
	 *
	 * @param array  $endpoint_input 端点输入数据.
	 * @param string $endpoint_key   端点标识.
	 * @param string $previous       原来保存的值.
	 * @return string
	 * @since endpoint schema v2
	 */
	private function sanitize_custom_base_url( array $endpoint_input, string $endpoint_key, string $previous ): string {
		if ( ! array_key_exists( 'custom_base_url', $endpoint_input ) ) {
			return $previous;
		}

		$raw = trim( (string) $endpoint_input['custom_base_url'] );
		if ( '' === $raw ) {
			return '';
		}

		$valid = EndpointRepository::validate_base_url( $raw, $endpoint_key, true );
		if ( '' === $valid ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					EndpointRepository::OPTION,
					'wpmind_invalid_base_url_' . $endpoint_key,
					sprintf(
						/* translators: %s: provider id */
						__( '%s 的自定义 Base URL 无效：只允许 https 地址，不能带账号、查询参数或锚点。已保留原来的设置。', 'wpmind' ),
						$endpoint_key
					)
				);
			}
			return $previous;
		}

		return $valid;
	}

	/**
	 * 清理 API Key（支持清除功能）
	 *
	 * @param array  $endpoint_input 端点输入数据
	 * @param string $endpoint_key   端点标识
	 * @return string 处理后的 API Key
	 * @since 1.2.0
	 */
	private function sanitize_api_key( array $endpoint_input, string $endpoint_key ): string {
		// 如果勾选了清除，返回空字符串
		if ( ! empty( $endpoint_input['clear_api_key'] ) ) {
			return '';
		}

		$api_key = trim( (string) ( $endpoint_input['api_key'] ?? '' ) );

		// 如果为空，保留原有值
		if ( $api_key === '' ) {
			$existing = get_option( 'wpmind_custom_endpoints', [] );
			return $existing[ $endpoint_key ]['api_key'] ?? '';
		}

		return sanitize_text_field( $api_key );
	}

	/**
	 * 清理图像端点配置
	 *
	 * @param mixed $input 输入数据
	 * @return array 清理后的图像端点配置
	 * @since 2.4.0
	 */
	public function sanitize_image_endpoints( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized           = [];
		$available_providers = [
			'openai_gpt_image',
			'google_gemini_image',
			'tencent_hunyuan',
			'bytedance_doubao',
			'flux',
			'qwen_image',
		];

		foreach ( $available_providers as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$provider_input = $input[ $key ];

			// 处理清除 API Key
			$api_key = $this->sanitize_image_api_key( $provider_input, $key );
			if ( ! empty( $provider_input['clear_api_key'] ) ) {
				$api_key = '';
			}

			$sanitized[ $key ] = [
				'enabled'         => ! empty( $provider_input['enabled'] ),
				'api_key'         => $api_key,
				'custom_base_url' => esc_url_raw( $provider_input['custom_base_url'] ?? '' ),
			];
		}

		return $sanitized;
	}

	/**
	 * 清理图像服务 API Key
	 *
	 * @param array  $provider_input 服务商输入数据
	 * @param string $provider_key   服务商标识
	 * @return string 处理后的 API Key
	 * @since 2.4.0
	 */
	private function sanitize_image_api_key( array $provider_input, string $provider_key ): string {
		$api_key = trim( (string) ( $provider_input['api_key'] ?? '' ) );

		// 如果是掩码值（********），保留原有值
		if ( $api_key === '' || $api_key === '********' ) {
			$existing = get_option( 'wpmind_image_endpoints', [] );
			return $existing[ $provider_key ]['api_key'] ?? '';
		}

		return sanitize_text_field( $api_key );
	}

	/**
	 * 清理超时设置
	 *
	 * @param mixed $input 输入值
	 * @return int 清理后的超时值
	 * @since 1.1.0
	 */
	public function sanitize_timeout( $input ): int {
		$timeout = absint( $input );
		return max( 10, min( 300, $timeout ) );
	}

	/**
	 * 清理 Exact Cache 开关
	 *
	 * @param mixed $input 输入值
	 * @return string
	 */
	public function sanitize_exact_cache_enabled( $input ): string {
		if ( ! empty( $input ) && in_array( (string) $input, [ '1', 'true', 'on', 'yes' ], true ) ) {
			return '1';
		}

		return '0';
	}

	/**
	 * 清理 Exact Cache 默认 TTL
	 *
	 * @param mixed $input 输入值
	 * @return int
	 */
	public function sanitize_exact_cache_ttl( $input ): int {
		$ttl = (int) $input;
		return max( 0, min( 86400, $ttl ) );
	}

	/**
	 * 清理 Exact Cache 最大条目
	 *
	 * @param mixed $input 输入值
	 * @return int
	 */
	public function sanitize_exact_cache_max_entries( $input ): int {
		$entries = (int) $input;

		if ( $entries <= 0 ) {
			return 500;
		}

		return min( 50000, max( 100, $entries ) );
	}

	/**
	 * 渲染设置页面（kit 壳 + 视图路由）
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 显示保存成功消息
		settings_errors( 'wpmind_messages' );

		$view = self::current_view();
		$template = WPMIND_PLUGIN_DIR . 'templates/admin/views/' . $view . '.php';
		if ( ! file_exists( $template ) ) {
			$template = WPMIND_PLUGIN_DIR . 'templates/admin/views/overview.php';
		}

		include WPMIND_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * 插件操作链接
	 *
	 * @param array $links 现有链接
	 * @return array 修改后的链接
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::url() ),
			esc_html__( '设置', 'wpmind' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * 插件行元信息
	 *
	 * @param array  $links 现有链接
	 * @param string $file  插件文件
	 * @return array 修改后的链接
	 * @since 1.1.0
	 */
	public function plugin_row_meta( array $links, string $file ): array {
		if ( plugin_basename( WPMIND_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://linuxjoy.com/plugins/wpmind/docs' ),
			esc_html__( '文档', 'wpmind' )
		);

		return $links;
	}
}
