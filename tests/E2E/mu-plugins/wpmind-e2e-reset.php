<?php
/**
 * Plugin Name: WPMind E2E reset endpoint (tests only)
 * Description: 只给 Playwright 用：提供 wpmind-e2e/v1 REST 端点，替代 `wp-env run cli`（playground runtime 不支持 run）。只通过 .wp-env.json 的 mappings 挂进测试站点，不进插件发布包。
 *
 * 三道门槛同时满足才注册路由：
 * 1. 测试环境：wp_get_environment_type() === 'local'，并且定义了 WP_TESTS_DOMAIN（docker 和 playground 两种 wp-env runtime 都会写这个常量）；
 * 2. 测试专用常量 WPMIND_E2E_RESET === true（由 .wp-env.json 的 config 写入 wp-config / playground blueprint）；
 * 3. permission_callback 要求 manage_options（REST cookie 认证需带 X-WP-Nonce）。
 * option 只允许 wpmind_ 前缀（含测试用的 wpmind_e2e_*）。
 *
 * @package WPMind\Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * 是否允许注册 E2E 复位端点（门槛 1 + 2；门槛 3 在 permission_callback 里）。
 *
 * @return bool
 */
function wpmind_e2e_reset_allowed() {
	return 'local' === wp_get_environment_type()
		&& defined( 'WP_TESTS_DOMAIN' )
		&& defined( 'WPMIND_E2E_RESET' )
		&& true === WPMIND_E2E_RESET;
}

/**
 * Option 名是否在白名单内。
 *
 * @param mixed $name Option 名。
 * @return bool
 */
function wpmind_e2e_option_allowed( $name ) {
	return is_string( $name ) && 1 === preg_match( '/^wpmind_[a-z0-9_\-]+$/', $name );
}

/**
 * 复位成「新装站点」：删掉模块开关、网关开关和 E2E 开关，写入当前版本号。
 *
 * @return array
 */
function wpmind_e2e_reset_fresh_install() {
	$ids = array( 'analytics', 'api-gateway', 'auto-meta', 'cost-control', 'exact-cache', 'geo', 'media-intelligence' );
	foreach ( $ids as $id ) {
		delete_option( "wpmind_module_{$id}_enabled" );
	}
	foreach ( array( 'wpmind_gateway_enabled', 'wpmind_e2e_fake_update', 'wpmind_e2e_updater_override', 'wpmind_e2e_fake_update_hits' ) as $name ) {
		delete_option( $name );
	}
	delete_site_transient( 'update_plugins' );
	update_option( 'wpmind_installed_version', defined( 'WPMIND_VERSION' ) ? WPMIND_VERSION : '', false );
	return array( 'reset' => true );
}

/**
 * 先写（null = 删除）再读 option。
 *
 * @param WP_REST_Request $request 请求。
 * @return array|WP_Error
 */
function wpmind_e2e_rest_options( WP_REST_Request $request ) {
	$set = (array) $request->get_param( 'set' );
	$get = (array) $request->get_param( 'get' );

	foreach ( array_merge( array_keys( $set ), $get ) as $name ) {
		if ( ! wpmind_e2e_option_allowed( $name ) ) {
			return new WP_Error( 'wpmind_e2e_option_not_allowed', 'option 不在白名单（只允许 wpmind_ 前缀）', array( 'status' => 400 ) );
		}
	}

	foreach ( $set as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value, false );
		}
	}

	$out = array();
	foreach ( $get as $name ) {
		$out[ $name ] = get_option( $name, null );
	}
	return array( 'options' => (object) $out );
}

/**
 * 插件信息（版本、basename、更新器接管过滤器、本 mu-plugin 目录是否挂上）。
 *
 * @return array
 */
function wpmind_e2e_rest_info() {
	$basename = defined( 'WPMIND_PLUGIN_BASENAME' ) ? WPMIND_PLUGIN_BASENAME : '';
	$slug     = dirname( $basename );
	return array(
		'version'  => defined( 'WPMIND_VERSION' ) ? WPMIND_VERSION : null,
		'basename' => $basename,
		'slug'     => $slug,
		'override' => (bool) apply_filters( 'wenpai_updater_override', false, $slug ),
		'mu'       => function_exists( 'wpmind_e2e_json' ),
		// 多站点下插件更新行只出现在网络后台 plugins.php.
		'multisite' => is_multisite(),
	);
}

/**
 * 强制跑一次插件更新检查，返回有更新的插件版本和伪造更新源的命中记录。
 *
 * @return array
 */
function wpmind_e2e_rest_update_check() {
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	$transient = get_site_transient( 'update_plugins' );
	$response  = array();
	foreach ( (array) ( $transient->response ?? array() ) as $file => $update ) {
		$response[ $file ] = $update->new_version ?? '';
	}
	return array(
		'response' => (object) $response,
		'hits'     => get_option( 'wpmind_e2e_fake_update_hits', array() ),
	);
}

add_action(
	'rest_api_init',
	static function () {
		if ( ! wpmind_e2e_reset_allowed() ) {
			return;
		}

		$permission = static function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'wpmind-e2e/v1',
			'/reset',
			array(
				'methods'             => 'POST',
				'callback'            => 'wpmind_e2e_reset_fresh_install',
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'wpmind-e2e/v1',
			'/options',
			array(
				'methods'             => 'POST',
				'callback'            => 'wpmind_e2e_rest_options',
				'permission_callback' => $permission,
				'args'                => array(
					'get' => array(
						'type'    => 'array',
						'default' => array(),
					),
					'set' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			'wpmind-e2e/v1',
			'/info',
			array(
				'methods'             => 'GET',
				'callback'            => 'wpmind_e2e_rest_info',
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'wpmind-e2e/v1',
			'/update-check',
			array(
				'methods'             => 'POST',
				'callback'            => 'wpmind_e2e_rest_update_check',
				'permission_callback' => $permission,
			)
		);
	}
);
