<?php
/**
 * 模块视图（按 2026-11-16 过审原型：单卡功能模块列表，即时开关 + 设置入口 + 权益徽标槽位）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;

( static function (): void {
	$wpmind_modules = ModuleLoader::instance()->get_modules();
	$wpmind_total   = count( $wpmind_modules );
	$wpmind_enabled = count(
		array_filter(
			$wpmind_modules,
			static fn( $m ) => ! empty( $m['enabled'] )
		)
	);

	// 核心模块置顶，其余按已启用优先、再按名称排序.
	uasort(
		$wpmind_modules,
		static function ( array $a, array $b ): int {
			$a_core = empty( $a['can_disable'] ) ? 0 : 1;
			$b_core = empty( $b['can_disable'] ) ? 0 : 1;
			if ( $a_core !== $b_core ) {
				return $a_core <=> $b_core;
			}
			$a_on = ! empty( $a['enabled'] ) ? 0 : 1;
			$b_on = ! empty( $b['enabled'] ) ? 0 : 1;
			if ( $a_on !== $b_on ) {
				return $a_on <=> $b_on;
			}
			return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
		}
	);

	$wpmind_module_switch = static function ( string $mod_id, array $mod ): string {
		$locked  = empty( $mod['can_disable'] );
		$checked = ! empty( $mod['enabled'] );
		$name    = (string) ( $mod['name'] ?? $mod_id );
		$title   = $locked ? ' title="' . esc_attr__( '核心模块不可禁用', 'wpmind' ) . '"' : '';

		return '<span class="wenpai-switch"' . $title . '>'
			. '<input type="checkbox" role="switch" class="wenpai-switch__input wpmind-module-switch"'
			. ' data-module-id="' . esc_attr( $mod_id ) . '"'
			. ' aria-label="' . esc_attr( $name ) . '"'
			. ( $checked ? ' checked' : '' )
			. ( $locked ? ' disabled' : '' ) . '>'
			. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
			. '<span class="wenpai-switch__state" aria-hidden="true">'
			. '<span class="wenpai-switch__on">' . esc_html__( '已开启', 'wpmind' ) . '</span>'
			. '<span class="wenpai-switch__off">' . esc_html__( '未开启', 'wpmind' ) . '</span>'
			. '</span>'
			. '</span>';
	};

	$wpmind_module_icon = static function ( string $mod_id, array $mod ): string {
		$icon_map = [
			'cost-control'       => 'dollar',
			'analytics'          => 'analytics',
			'exact-cache'        => 'database',
			'geo'                => 'globe',
			'api-gateway'        => 'cloud',
			'media-intelligence' => 'image',
			'auto-meta'          => 'sparkle',
		];
		$key      = $icon_map[ $mod_id ] ?? (string) ( $mod['icon'] ?? 'modules' );
		return AdminIcons::svg( $key ) ?: AdminIcons::svg( 'modules' );
	};

	wpmind_page_head(
		__( '模块', 'wpmind' ),
		__( 'AI 能力开关。核心模块（费用控制）不可禁用；「设置」进模块设置页。', 'wpmind' )
	);
	?>

<div class="wenpai-stack">
	<?php
	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '功能模块', 'wpmind' ),
			'sub'               => __( '启用开关即时生效；核心模块不可禁用', 'wpmind' ),
			'tile'              => false,
			'head_actions_html' => Wenpai_Admin_UI::pill_html(
				[
					'text'  => sprintf(
						/* translators: 1: enabled count, 2: total count */
						__( '%1$d / %2$d 已开启', 'wpmind' ),
						$wpmind_enabled,
						$wpmind_total
					),
					'state' => $wpmind_enabled > 0 ? 'ok' : 'mute',
				]
			),
		]
	);

	foreach ( $wpmind_modules as $mod_id => $mod ) {
		$mod_id     = (string) $mod_id;
		$enabled    = ! empty( $mod['enabled'] );
		$is_core    = empty( $mod['can_disable'] );
		$badge_text = function_exists( 'apply_filters' )
			? (string) apply_filters( 'wpmind_module_badge', '', $mod_id, $mod )
			: '';

		$title_html = esc_html( (string) ( $mod['name'] ?? $mod_id ) );
		if ( $is_core ) {
			$title_html .= ' ' . Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '核心', 'wpmind' ),
					'state' => 'mute',
				]
			);
		}
		if ( '' !== $badge_text ) {
			$title_html .= ' ' . Wenpai_Admin_UI::pill_html(
				[
					'text'  => $badge_text,
					'state' => 'mute',
				]
			);
		}

		$action_btn = '';
		if ( 'cost-control' === $mod_id ) {
			$action_btn = Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '预算与用量', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'analytics' ] ),
					'variant' => 'ghost',
					'size'    => 'sm',
				]
			);
		} elseif ( 'analytics' === $mod_id ) {
			$action_btn = Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '查看分析', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'analytics' ] ),
					'variant' => 'ghost',
					'size'    => 'sm',
				]
			);
		} elseif ( ! empty( $mod['settings_tab'] ) ) {
			$action_btn = Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '设置', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'    => 'module',
							'module' => $mod_id,
						]
					),
					'variant' => 'ghost',
					'size'    => 'sm',
				]
			);
		}

		$control_html = '<div class="wenpai-row wenpai-row--tight">'
			. $wpmind_module_switch( $mod_id, $mod )
			. $action_btn
			. '</div>';

		Wenpai_Admin_UI::row(
			[
				'icon_html'    => $wpmind_module_icon( $mod_id, $mod ),
				'state'        => $enabled ? 'ok' : '',
				'title_html'   => $title_html,
				'desc'         => (string) ( $mod['description'] ?? '' ),
				'control_html' => $control_html,
				'class'        => 'wpmind-module-item ' . ( $enabled ? 'is-enabled' : 'is-disabled' ),
				'attrs'        => [ 'data-module-card' => $mod_id ],
			]
		);
	}

	Wenpai_Admin_UI::card_foot(
		[
			'start' => true,
			'meta'  => __( '各模块详细配置统一收在「设置」子页，避免主导航过长。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
