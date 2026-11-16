<?php
/**
 * WPMind 后台页壳：kit wenpai-app + 顶栏导航 + 视图路由。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;

// kit 缺席时的展示层兜底：壳/组件 helper 的等价纯 HTML 输出。
if ( ! function_exists( 'wpmind_page_head' ) ) {
	function wpmind_page_head( $title, $lede = '', $aside = '' ): void {
		if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
			Wenpai_Admin_Shell::page_head( $title, $lede, $aside );
			return;
		}
		echo '<div class="wenpai-page-head"><div><h1 class="wenpai-h1">' . esc_html( $title ) . '</h1>';
		if ( '' !== $lede ) {
			echo '<p class="wenpai-lede">' . esc_html( $lede ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $aside ) {
			echo '<div class="mode">' . $aside . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML, same as kit.
		}
		echo '</div>';
	}
}
if ( ! function_exists( 'wpmind_code_block_html' ) ) {
	function wpmind_code_block_html( array $args ): string {
		if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
			return Wenpai_Admin_UI::code_block_html( $args );
		}
		return '<pre class="wenpai-code"><code>' . esc_html( (string) ( $args['code'] ?? '' ) ) . '</code></pre>';
	}
}
if ( ! function_exists( 'wpmind_code_inline_html' ) ) {
	function wpmind_code_inline_html( array $args ): string {
		if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
			return Wenpai_Admin_UI::code_inline_html( $args );
		}
		return '<code class="wenpai-code">' . esc_html( (string) ( $args['code'] ?? '' ) ) . '</code>';
	}
}
if ( ! function_exists( 'wpmind_notice' ) ) {
	function wpmind_notice( array $args ): void {
		if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
			Wenpai_Admin_UI::notice( $args );
			return;
		}
		echo '<div class="notice ' . esc_attr( (string) ( $args['level'] ?? 'info' ) ) . '"><p>' . esc_html( (string) ( $args['text'] ?? '' ) ) . '</p></div>';
	}
}

$wpmind_view   = AdminPage::current_view();
$wpmind_module = AdminPage::current_module();

$wpmind_url = static function ( string $tab, array $args = [] ): string {
	return AdminPage::url( array_merge( [ 'tab' => $tab ], $args ) );
};

$wpmind_icon = static function ( string $name ): string {
	return AdminIcons::svg( $name );
};

$wpmind_kit_icon = static function ( string $name ): string {
	return class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( $name ) : '';
};

$wpmind_tabs = [
	[ 'id' => 'overview',  'label' => __( '概览', 'wpmind' ),  'url' => $wpmind_url( 'overview' ),  'icon' => 'overview' ],
	[ 'id' => 'services',  'label' => __( 'AI 服务', 'wpmind' ), 'url' => $wpmind_url( 'services' ),  'icon' => 'services' ],
	[ 'id' => 'modules',   'label' => __( '模块', 'wpmind' ),   'url' => $wpmind_url( 'modules' ),   'icon' => 'modules' ],
	[ 'id' => 'analytics', 'label' => __( '分析', 'wpmind' ),   'url' => $wpmind_url( 'analytics' ), 'icon' => 'analytics' ],
	[ 'id' => 'workflow',  'label' => __( '任务', 'wpmind' ),   'url' => $wpmind_url( 'workflow' ),  'icon' => 'workflow' ],
	[ 'id' => 'tools',     'label' => __( '工具', 'wpmind' ),   'url' => $wpmind_url( 'tools' ),     'icon' => 'tools' ],
];

// 二级页不进顶栏：路由归 AI 服务、模块设置归模块。
$wpmind_current = 'routing' === $wpmind_view ? 'services' : ( 'module' === $wpmind_view ? 'modules' : $wpmind_view );

if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
	Wenpai_Admin_Shell::open(
		[
			'slug'       => 'wpmind',
			'name'       => __( '文派心思', 'wpmind' ),
			'mark_html'  => $wpmind_icon( 'services' ),
			'home_url'   => $wpmind_url( 'overview' ),
			'current'    => 'help' === $wpmind_view ? 'help' : $wpmind_current,
			'tabs'       => array_map(
				static function ( array $t ) use ( $wpmind_icon ): array {
					$t['icon_html'] = $wpmind_icon( $t['icon'] );
					unset( $t['icon'] );
					return $t;
				},
				$wpmind_tabs
			),
			'help_url'        => $wpmind_url( 'help' ),
			'help_label'      => __( '帮助', 'wpmind' ),
			'help_icon_html'  => $wpmind_kit_icon( 'help' ),
			'feedback_url'    => 'https://github.com/WenPai-org/wpmind/issues',
			'feedback_label'  => __( '反馈', 'wpmind' ),
			'feedback_icon_html' => $wpmind_kit_icon( 'feedback' ),
			'new_tab_label'   => __( '（在新标签页打开）', 'wpmind' ),
		]
	);
} else {
	// kit 未加载时的兜底：裸 .wenpai-app 结构，样式由产品 CSS 兜底。
	echo '<div class="wenpai-app">';
}

$wpmind_view_file = WPMIND_PLUGIN_DIR . 'templates/admin/views/' . $wpmind_view . '.php';
if ( file_exists( $wpmind_view_file ) ) {
	include $wpmind_view_file;
} else {
	include WPMIND_PLUGIN_DIR . 'templates/admin/views/overview.php';
}

if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
	Wenpai_Admin_Shell::close(
		[
			'version'       => 'v' . WPMIND_VERSION,
			'links'         => [
				[ 'url' => 'https://wpcy.com/mind/', 'label' => __( '文档', 'wpmind' ) ],
				[ 'url' => 'https://github.com/WenPai-org/wpmind', 'label' => __( 'GitHub', 'wpmind' ) ],
			],
			'new_tab_label' => __( '（在新标签页打开）', 'wpmind' ),
		]
	);
} else {
	echo '</div>';
}
