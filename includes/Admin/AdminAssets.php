<?php
/**
 * Admin assets loader.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

/**
 * Class AdminAssets
 */
final class AdminAssets {

	/**
	 * Singleton instance.
	 *
	 * @var AdminAssets|null
	 */
	private static ?AdminAssets $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AdminAssets
	 */
	public static function instance(): AdminAssets {
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
	 * 加载管理后台资源
	 *
	 * @param string $hook_suffix 当前页面钩子后缀
	 * @since 1.1.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page check.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$owns_page    = 'wpmind' === $current_page
			&& class_exists( 'Wenpai_Admin_Menu', false )
			&& \Wenpai_Admin_Menu::owns_page( 'wpmind' );

		if ( ! $owns_page ) {
			$kit_hook = class_exists( 'Wenpai_Admin_Menu', false )
				? \Wenpai_Admin_Menu::hook( 'wpmind' )
				: 'toplevel_page_wpmind';
			if ( ! in_array( $hook_suffix, [ $kit_hook, 'toplevel_page_wpmind', 'settings_page_wpmind' ], true ) ) {
				return;
			}
		}

		// wenpai-admin-ui kit（与其它文派插件共享同一份 CSS/JS，版本最高者生效）
		$css_deps = [];
		if ( class_exists( 'Wenpai_Admin_Loader', false ) ) {
			\Wenpai_Admin_Loader::enqueue(
				[
					'full' => true,
					'body' => 'wpmind-admin',
				]
			);
			$css_deps[] = \Wenpai_Admin_Loader::STYLE_HANDLE;
		}

		// 产品补充样式（在 kit 组件之上；仅保留产品专属轻量样式）
		wp_enqueue_style(
			'wpmind-admin-ui',
			WPMIND_PLUGIN_URL . 'assets/css/wpmind-admin.css',
			$css_deps,
			WPMIND_VERSION
		);

		// JS 行为层（admin-*.js）按元素 ID 绑定，随视图加载。

		// Chart.js 图表库（本地打包，无外部 CDN 依赖）
		wp_register_script(
			'chartjs',
			WPMIND_PLUGIN_URL . 'assets/js/vendor/chartjs/chart.umd.min.js',
			[],
			'4.5.0',
			true
		);

		wp_enqueue_script(
			'wpmind-admin-ui',
			WPMIND_PLUGIN_URL . 'assets/js/admin-ui.js',
			[ 'jquery' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-boot',
			WPMIND_PLUGIN_URL . 'assets/js/admin-boot.js',
			[ 'wpmind-admin-ui' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-endpoints',
			WPMIND_PLUGIN_URL . 'assets/js/admin-endpoints.js',
			[ 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-routing',
			WPMIND_PLUGIN_URL . 'assets/js/admin-routing.js',
			[ 'wpmind-admin-boot', 'jquery-ui-sortable' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-analytics',
			WPMIND_PLUGIN_URL . 'assets/js/admin-analytics.js',
			[ 'wpmind-admin-boot', 'chartjs' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-budget',
			WPMIND_PLUGIN_URL . 'assets/js/admin-budget.js',
			[ 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-geo',
			WPMIND_PLUGIN_URL . 'assets/js/admin-geo.js',
			[ 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmind-admin-modules',
			WPMIND_PLUGIN_URL . 'assets/js/admin-modules.js',
			[ 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);

		// 完整的国际化字符串
		wp_localize_script(
			'wpmind-admin-boot',
			'wpmindL10n',
			[
				'testSuccess'    => __( '连接成功！', 'wpmind' ),
				'testFailed'     => __( '连接失败：', 'wpmind' ),
				'testing'        => __( '测试中...', 'wpmind' ),
				'enabled'        => __( '已启用', 'wpmind' ),
				'apiKeyRequired' => __( '请为已启用的服务填写 API Key', 'wpmind' ),
				'apiKeySet'      => __( '已设置', 'wpmind' ),
				'apiKeyCleared'  => __( 'API Key 将被清除', 'wpmind' ),
			]
		);

		// 为 AJAX 添加数据
		wp_localize_script(
			'wpmind-admin-boot',
			'wpmindData',
			[
				'nonce'   => wp_create_nonce( 'wpmind_ajax' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'version' => WPMIND_VERSION,
				'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
			]
		);
	}
}
