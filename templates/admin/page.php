<?php
/**
 * WPMind 后台页壳：kit wenpai-app + 顶栏导航 + 视图路由。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Core\ModuleLoader;
use WPMind\Workflow\TaskRepository;

// kit 缺席时的展示层兜底：壳/组件 helper 的等价纯 HTML 输出。
if ( ! function_exists( 'wpmind_page_head' ) ) {
	function wpmind_page_head( string $title, string $lede = '', string $aside = '', string $lede_extra_html = '' ): void {
		if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
			Wenpai_Admin_Shell::page_head( $title, $lede, $aside, $lede_extra_html );
			return;
		}
		echo '<div class="wenpai-page-head"><div><h1 class="wenpai-h1">' . esc_html( $title ) . '</h1>';
		if ( '' !== $lede || '' !== $lede_extra_html ) {
			echo '<p class="wenpai-lede">' . esc_html( $lede ) . $lede_extra_html . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML, same as kit.
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
if ( ! function_exists( 'wpmind_confirm_attrs' ) ) {
	/**
	 * Output data-wenpai-confirm attributes for kit-managed confirmation modal.
	 *
	 * @param string               $text Confirmation message.
	 * @param array<string, mixed> $args Optional kit confirm args.
	 * @return string
	 */
	function wpmind_confirm_attrs( string $text, array $args = [] ): string {
		if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
			return Wenpai_Admin_UI::confirm_attrs( $text, $args );
		}
		return ' data-wenpai-confirm="' . esc_attr( $text ) . '"';
	}
}

$wpmind_view    = AdminPage::current_view();
$wpmind_module  = AdminPage::current_module();
$wpmind_display = SuiteIntegration::display();

$wpmind_url = static function ( string $tab, array $args = [] ): string {
	return AdminPage::url( array_merge( [ 'tab' => $tab ], $args ) );
};

$wpmind_icon = static function ( string $name ): string {
	return AdminIcons::svg( $name );
};

$wpmind_kit_icon = static function ( string $name ): string {
	return class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( $name ) : '';
};

// 判断是否露出「任务」顶栏项：auto-meta / media-intelligence 启用，或已有 wpmind_task 记录。
$wpmind_show_workflow_tab = false;
if ( class_exists( ModuleLoader::class ) ) {
	$wpmind_loader            = ModuleLoader::instance();
	$wpmind_show_workflow_tab = $wpmind_loader->is_module_enabled( 'auto-meta' )
		|| $wpmind_loader->is_module_enabled( 'media-intelligence' );
}
if ( ! $wpmind_show_workflow_tab && class_exists( TaskRepository::class ) ) {
	$wpmind_existing_tasks    = ( new TaskRepository() )->list_tasks( [ 'limit' => 1 ] );
	$wpmind_show_workflow_tab = ! empty( $wpmind_existing_tasks );
}

$wpmind_tabs = [
	[ 'id' => 'overview',  'label' => __( '概览', 'wpmind' ),  'url' => $wpmind_url( 'overview' ),  'icon' => 'overview' ],
	[ 'id' => 'services',  'label' => __( 'AI 服务', 'wpmind' ), 'url' => $wpmind_url( 'cloud' ),     'icon' => 'services' ],
	[ 'id' => 'modules',   'label' => __( '模块', 'wpmind' ),   'url' => $wpmind_url( 'modules' ),   'icon' => 'modules' ],
	[ 'id' => 'analytics', 'label' => __( '分析', 'wpmind' ),   'url' => $wpmind_url( 'analytics' ), 'icon' => 'analytics' ],
	[ 'id' => 'tools',     'label' => __( '工具', 'wpmind' ),   'url' => $wpmind_url( 'tools' ),     'icon' => 'tools' ],
];

if ( $wpmind_show_workflow_tab ) {
	$wpmind_tabs[] = [ 'id' => 'workflow', 'label' => __( '任务', 'wpmind' ), 'url' => $wpmind_url( 'workflow' ), 'icon' => 'workflow' ];
}

// 二级页不进顶栏：心思 AI、路由归 AI 服务，模块设置归模块。
$wpmind_current = in_array( $wpmind_view, [ 'cloud', 'routing' ], true )
	? 'services'
	: ( 'module' === $wpmind_view ? 'modules' : $wpmind_view );

$wpmind_shell_args = [
	'slug'           => 'wpmind',
	'name'           => $wpmind_display['brand'],
	'mark_html'      => $wpmind_icon( 'services' ),
	'home_url'       => $wpmind_url( 'overview' ),
	'current'        => 'help' === $wpmind_view ? 'help' : $wpmind_current,
	'main_class'     => 'wenpai-measure-wide',
	'tabs'           => array_map(
		static function ( array $t ) use ( $wpmind_icon ): array {
			$t['icon_html'] = $wpmind_icon( $t['icon'] );
			unset( $t['icon'] );
			return $t;
		},
		$wpmind_tabs
	),
	'help_url'       => $wpmind_url( 'help' ),
	'help_label'     => __( '帮助', 'wpmind' ),
	'help_icon_html' => $wpmind_kit_icon( 'help' ),
	'new_tab_label'  => __( '（在新标签页打开）', 'wpmind' ),
];

if ( '' !== $wpmind_display['feedback_url'] ) {
	$wpmind_shell_args['feedback_url']       = $wpmind_display['feedback_url'];
	$wpmind_shell_args['feedback_label']     = __( '反馈', 'wpmind' );
	$wpmind_shell_args['feedback_icon_html'] = $wpmind_kit_icon( 'feedback' );
}

if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
	Wenpai_Admin_Shell::open( $wpmind_shell_args );
} else {
	echo '<div class="wenpai-app">';
}

$wpmind_view_file = WPMIND_PLUGIN_DIR . 'templates/admin/views/' . $wpmind_view . '.php';
if ( file_exists( $wpmind_view_file ) ) {
	include $wpmind_view_file;
} else {
	include WPMIND_PLUGIN_DIR . 'templates/admin/views/overview.php';
}

$wpmind_footer_links = [];
if ( '' !== $wpmind_display['docs_url'] ) {
	$wpmind_footer_links[] = [
		'url'   => $wpmind_display['docs_url'],
		'label' => __( '文档', 'wpmind' ),
	];
}
if ( '' !== $wpmind_display['github_url'] ) {
	$wpmind_footer_links[] = [
		'url'   => $wpmind_display['github_url'],
		'label' => 'GitHub',
	];
}

if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
	Wenpai_Admin_Shell::close(
		[
			'version'       => $wpmind_display['brand'] . ' v' . WPMIND_VERSION,
			'links'         => $wpmind_footer_links,
			'new_tab_label' => __( '（在新标签页打开）', 'wpmind' ),
			'whitelabel'    => ! empty( $wpmind_display['whitelabel'] ),
			'feedback'      => [
				'category_id'    => 15,
				'tags'           => [ 'wpmind', 'bug' ],
				'plugin_slug'    => 'wpmind',
				'plugin_version' => WPMIND_VERSION,
				'label'          => __( '问题反馈', 'wpmind' ),
			],
		]
	);
} else {
	echo '</div>';
}
