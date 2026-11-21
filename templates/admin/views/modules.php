<?php
/**
 * 模块视图：上方已启用模块 module_grid + 下方其余模块 row 列表（保留权益徽标槽位）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;

( static function (): void {
	$wpmind_modules  = ModuleLoader::instance()->get_modules();
	$wpmind_on_list  = [];
	$wpmind_off_list = [];
	foreach ( $wpmind_modules as $mod_id => $mod ) {
		if ( ! empty( $mod['enabled'] ) ) {
			$wpmind_on_list[ $mod_id ] = $mod;
		} else {
			$wpmind_off_list[ $mod_id ] = $mod;
		}
	}

	$wpmind_total   = count( $wpmind_modules );
	$wpmind_enabled = count( $wpmind_on_list );

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
		. '</span>';
	};

	$wpmind_entitlement_badge = static function ( string $mod_id, array $mod ): string {
		$badge = function_exists( 'apply_filters' )
		? (string) apply_filters( 'wpmind_module_badge', '', $mod_id, $mod )
		: '';
		if ( '' === $badge ) {
			return '';
		}
		return Wenpai_Admin_UI::pill_html(
			[
				'text'  => $badge,
				'state' => 'mute',
			]
		);
	};

	wpmind_page_head(
		__( '模块', 'wpmind' ),
		sprintf(
		/* translators: 1: enabled count, 2: total count */
			__( '已开启 %1$d / %2$d 个模块，开关即点即存。', 'wpmind' ),
			$wpmind_enabled,
			$wpmind_total
		)
	);
	?>

<div class="wenpai-stack">
	<?php if ( ! empty( $wpmind_on_list ) ) : ?>
		<?php
		$grid_items = [];
		$card_index = 0;
		foreach ( $wpmind_on_list as $mod_id => $mod ) {
			$mod_id  = (string) $mod_id;
			$is_core = empty( $mod['can_disable'] );
			$badge   = function_exists( 'apply_filters' )
				? (string) apply_filters( 'wpmind_module_badge', '', $mod_id, $mod )
				: '';
			$status  = $is_core ? __( '核心模块', 'wpmind' ) : __( '已开启', 'wpmind' );
			if ( '' !== $badge ) {
				$status .= ' · ' . $badge;
			} elseif ( ! empty( $mod['version'] ) ) {
				$status .= ' · v' . (string) $mod['version'];
			}

			$item = [
				'title'        => (string) ( $mod['name'] ?? $mod_id ),
				'desc'         => (string) ( $mod['description'] ?? '' ),
				'icon_html'    => AdminIcons::svg( (string) ( $mod['icon'] ?? 'modules' ) ) ?: AdminIcons::svg( 'modules' ),
				'tone'         => $card_index < 3 ? 'ok' : '',
				'control_html' => $wpmind_module_switch( $mod_id, $mod ),
				'status'       => $status,
				'status_tone'  => 'ok',
				'class'        => 'wpmind-module-item is-enabled',
				'attrs'        => [ 'data-module-card' => $mod_id ],
			];
			if ( ! empty( $mod['settings_tab'] ) ) {
				$item['action_label']   = __( '配置', 'wpmind' );
				$item['action_url']     = AdminPage::url(
					[
						'tab'    => 'module',
						'module' => $mod_id,
					]
				);
				$item['action_variant'] = 'ghost';
			}
			$grid_items[] = $item;
			++$card_index;
		}
		Wenpai_Admin_UI::module_grid( [ 'items' => $grid_items ] );
		?>
	<?php endif; ?>

	<?php if ( ! empty( $wpmind_off_list ) ) : ?>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '更多可用模块', 'wpmind' ),
				'tile'              => false,
				'head_actions_html' => Wenpai_Admin_UI::pill_html(
					[
						'text'  => sprintf( /* translators: %d: inactive module count */ __( '%d 项未开启', 'wpmind' ), count( $wpmind_off_list ) ),
						'state' => 'mute',
					]
				),
			]
		);
		foreach ( $wpmind_off_list as $mod_id => $mod ) {
			$mod_id     = (string) $mod_id;
			$badge_html = $wpmind_entitlement_badge( $mod_id, $mod );
			$title_html = esc_html( (string) ( $mod['name'] ?? $mod_id ) );
			if ( '' !== $badge_html ) {
				$title_html .= ' ' . $badge_html;
			}
			if ( ! empty( $mod['version'] ) ) {
				$title_html .= ' <span class="meta">v' . esc_html( (string) $mod['version'] ) . '</span>';
			}

			Wenpai_Admin_UI::row(
				[
					'icon_html'    => AdminIcons::svg( (string) ( $mod['icon'] ?? 'modules' ) ) ?: AdminIcons::svg( 'modules' ),
					'title_html'   => $title_html,
					'desc'         => (string) ( $mod['description'] ?? '' ),
					'control_html' => $wpmind_module_switch( $mod_id, $mod ),
					'class'        => 'wpmind-module-item is-disabled',
					'attrs'        => [ 'data-module-card' => $mod_id ],
				]
			);
		}
		Wenpai_Admin_UI::card_close();
		?>
	<?php elseif ( empty( $wpmind_modules ) ) : ?>
		<?php
		Wenpai_Admin_UI::card_open();
		Wenpai_Admin_UI::empty_state(
			[
				'text' => __( '未发现可用模块', 'wpmind' ),
				'meta' => __( '请检查插件 modules 目录是否完整。', 'wpmind' ),
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	<?php endif; ?>
</div>
	<?php
} )();
