<?php
/**
 * 模块视图：上方已启用模块 module_grid + 更多可用模块列表 + 下方生态联动插件列表。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Core\ModuleLoader;
use WPMind\Modules\CostControl\UsageTracker;

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

	$wpmind_total      = count( $wpmind_modules );
	$wpmind_enabled    = count( $wpmind_on_list );
	$ecosystem_plugins = SuiteIntegration::ecosystem_integrations();

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

	$lede = sprintf(
		/* translators: 1: enabled count, 2: total count */
		__( '已开启 %1$d / %2$d 个内置模块，开关即点即存；已启用文派心思联动的生态插件会显示在下方列表。', 'wpmind' ),
		$wpmind_enabled,
		$wpmind_total
	);
	wpmind_page_head( __( '模块', 'wpmind' ), $lede );
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
				'icon_html'    => $wpmind_module_icon( $mod_id, $mod ),
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
			} elseif ( 'cost-control' === $mod_id ) {
				$item['action_label']   = __( '预算与用量', 'wpmind' );
				$item['action_url']     = AdminPage::url( [ 'tab' => 'analytics' ] );
				$item['action_variant'] = 'ghost';
			} elseif ( 'analytics' === $mod_id ) {
				$item['action_label']   = __( '查看分析', 'wpmind' );
				$item['action_url']     = AdminPage::url( [ 'tab' => 'analytics' ] );
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
						'text'  => sprintf(
							/* translators: %d: inactive module count */
							__( '%d 项未开启', 'wpmind' ),
							count( $wpmind_off_list )
						),
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
					'icon_html'    => $wpmind_module_icon( $mod_id, $mod ),
					'title_html'   => $title_html,
					'desc'         => (string) ( $mod['description'] ?? '' ),
					'control_html' => $wpmind_module_switch( $mod_id, $mod ),
					'class'        => 'wpmind-module-item is-disabled',
					'attrs'        => [ 'data-module-card' => $mod_id ],
				]
			);
		}
		Wenpai_Admin_UI::card_foot(
			[
				'start' => true,
				'meta'  => __( '按需启用可减少非必要加载，核心模块不可关闭。', 'wpmind' ),
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	<?php endif; ?>

	<?php
	// ---- 列表卡：已安装并开启文派心思联动的生态插件 ----
	$eco_count = count( $ecosystem_plugins );
	Wenpai_Admin_UI::card_open(
		[
			'title'             => __( '生态联动插件', 'wpmind' ),
			'sub'               => __( '当前站点已安装并开启文派心思 AI 能力的文派生态插件', 'wpmind' ),
			'tile'              => false,
			'head_actions_html' => Wenpai_Admin_UI::pill_html(
				[
					'text'  => sprintf(
						/* translators: %d: integrated ecosystem plugin count */
						__( '%d 个已联动', 'wpmind' ),
						$eco_count
					),
					'state' => $eco_count > 0 ? 'ok' : 'mute',
				]
			),
		]
	);

	if ( empty( $ecosystem_plugins ) ) {
		echo '<p class="meta">' . esc_html__( '当前未检测到已开启文派心思联动的生态插件。在文派别名、笔笙、文派合规、文派文档或文派智储中启用 AI 能力后，会自动显示在此列表。', 'wpmind' ) . '</p>';
	} else {
		foreach ( $ecosystem_plugins as $eco ) {
			$title_html = esc_html( $eco['name'] );
			if ( '' !== $eco['feature'] ) {
				$title_html .= ' ' . Wenpai_Admin_UI::pill_html(
					[
						'text'  => $eco['feature'],
						'state' => 'mute',
					]
				);
			}
			if ( '' !== $eco['version'] ) {
				$title_html .= ' <span class="meta">v' . esc_html( $eco['version'] ) . '</span>';
			}

			$desc_parts = [];
			if ( '' !== $eco['desc'] ) {
				$desc_parts[] = $eco['desc'];
			}
			if ( $eco['requests'] > 0 ) {
				$tok_fmt      = class_exists( UsageTracker::class )
					? UsageTracker::format_tokens( $eco['tokens'] )
					: number_format_i18n( $eco['tokens'] );
				$desc_parts[] = sprintf(
					/* translators: 1: request count, 2: token count */
					__( '累计 %1$s 次调用 · %2$s tok', 'wpmind' ),
					number_format_i18n( $eco['requests'] ),
					$tok_fmt
				);
			}

			$right_html = '<div class="wenpai-row wenpai-row--tight">'
				. Wenpai_Admin_UI::pill_html(
					[
						'text'  => __( '已联动', 'wpmind' ),
						'state' => 'ok',
					]
				);
			if ( '' !== $eco['settings_url'] ) {
				$right_html .= Wenpai_Admin_UI::button_html(
					[
						'label'   => __( '设置', 'wpmind' ),
						'href'    => $eco['settings_url'],
						'variant' => 'ghost',
						'size'    => 'sm',
					]
				);
			}
			$right_html .= '</div>';

			Wenpai_Admin_UI::row(
				[
					'icon_html'    => AdminIcons::svg( $eco['icon'] ) ?: AdminIcons::svg( 'package' ),
					'state'        => 'ok',
					'title_html'   => $title_html,
					'desc'         => implode( ' · ', $desc_parts ),
					'control_html' => $right_html,
				]
			);
		}
	}

	Wenpai_Admin_UI::card_foot(
		[
			'start' => true,
			'meta'  => __( '生态插件共用当前站点的服务商路由、预算与心思点数账本。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
