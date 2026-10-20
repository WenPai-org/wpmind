<?php
/**
 * Plugin Name: WPMind E2E fake updates (tests only)
 * Description: 只给 Playwright 用：开关打开时，用 pre_http_request 伪造 updates.wenpai.net 的响应，并挡掉 api.wordpress.org 的更新检查（测试期间不出网）。只通过 .wp-env.json 的 mappings 挂进 wp-env，不进插件发布包。
 *
 * 开关（都是 option，由用例用 WP-CLI 设置）：
 * - wpmind_e2e_fake_update = '1'      伪造更新源返回 99.0.0
 * - wpmind_e2e_updater_override = '1' 让 wenpai_updater_override 返回 true（模拟 wp-china-yes 接管）
 * 每次命中 updates.wenpai.net 都会记到 wpmind_e2e_fake_update_hits。
 *
 * @package WPMind\Tests
 */

defined( 'ABSPATH' ) || exit;

const WPMIND_E2E_FAKE_VERSION = '99.0.0';

add_filter(
	'wenpai_updater_override',
	static function ( $overridden, $slug = '' ) {
		if ( '1' === get_option( 'wpmind_e2e_updater_override' ) ) {
			return true;
		}
		return $overridden;
	},
	10,
	2
);

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( '1' !== get_option( 'wpmind_e2e_fake_update' ) ) {
			return $pre;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		// 测试期间不出网：挡掉 api.wordpress.org 的插件/主题/核心/翻译检查，返回空结果。
		if ( 'api.wordpress.org' === $host ) {
			return wpmind_e2e_json(
				[
					'plugins'      => [],
					'themes'       => [],
					'translations' => [],
					'no_update'    => [],
					'offers'       => [],
				]
			);
		}

		if ( 'updates.wenpai.net' !== $host ) {
			return $pre;
		}

		$hits   = (array) get_option( 'wpmind_e2e_fake_update_hits', [] );
		$hits[] = $path;
		update_option( 'wpmind_e2e_fake_update_hits', $hits, false );

		$basename = defined( 'WPMIND_PLUGIN_BASENAME' ) ? WPMIND_PLUGIN_BASENAME : 'wpmind/wpmind.php';
		$slug     = dirname( $basename );

		if ( '/api/v1/update-check' === $path ) {
			return wpmind_e2e_json(
				[
					'plugins' => [
						$basename => [
							'id'           => 'updates.wenpai.net/' . $slug,
							'slug'         => $slug,
							'version'      => WPMIND_E2E_FAKE_VERSION,
							'url'          => 'https://wpcy.com/mind',
							'package'      => '',
							'requires'     => '6.4',
							'tested'       => '7.1',
							'requires_php' => '8.1',
						],
					],
				]
			);
		}

		if ( '/api/v1/plugins/' . $slug . '/info' === $path ) {
			return wpmind_e2e_json(
				[
					'name'          => 'WPMind E2E Fake',
					'slug'          => $slug,
					'version'       => WPMIND_E2E_FAKE_VERSION,
					'author'        => 'WenPai E2E',
					'homepage'      => 'https://wpcy.com/mind',
					'download_link' => '',
					'requires'      => '6.4',
					'tested'        => '7.1',
					'requires_php'  => '8.1',
					'last_updated'  => '2026-10-19',
					'sections'      => [
						'description' => 'WPMIND-E2E-FAKE-DESCRIPTION',
						'changelog'   => "## 99.0.0\n- WPMIND-E2E-FAKE-CHANGELOG",
					],
				]
			);
		}

		return wpmind_e2e_json( [ 'error' => 'not found' ], 404 );
	},
	10,
	3
);

/**
 * 组一个 WP_Http 形状的响应。
 *
 * @param array $data 响应体。
 * @param int   $code HTTP 状态码。
 * @return array
 */
function wpmind_e2e_json( array $data, int $code = 200 ): array {
	return [
		'headers'  => [ 'content-type' => 'application/json' ],
		'body'     => wp_json_encode( $data ),
		'response' => [ 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Not Found' ],
		'cookies'  => [],
		'filename' => null,
	];
}
