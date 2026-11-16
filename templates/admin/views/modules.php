<?php
/**
 * 模块视图：AI 能力开关网格。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Core\ModuleLoader;

$wpmind_modules = ModuleLoader::instance()->get_modules();
$wpmind_enabled  = count( array_filter( $wpmind_modules, static fn( $m ) => $m['enabled'] ) );
$wpmind_total    = count( $wpmind_modules );

// 模块 id → 产品图标（AdminIcons）。
$wpmind_mod_icons = [
	'analytics'          => 'analytics',
	'api-gateway'        => 'route',
	'auto-meta'          => 'doc',
	'cost-control'       => 'wallet',
	'exact-cache'        => 'zap',
	'geo'                => 'globe',
	'media-intelligence' => 'image',
];

wpmind_page_head(
	__( '模块', 'wpmind' ),
	__( 'AI 能力开关。核心模块不可禁用；「设置」进模块设置页。', 'wpmind' )
);
?>

<div class="wenpai-stack">
	<section class="card wpmind-module-list">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '功能模块', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php esc_html_e( '启用开关即时生效；核心模块不可禁用', 'wpmind' ); ?></p>
			</div>
			<span class="pill ok"><?php echo esc_html( sprintf( /* translators: %1$d enabled, %2$d total */ __( '%1$d / %2$d 已启用', 'wpmind' ), $wpmind_enabled, $wpmind_total ) ); ?></span>
		</div>

		<?php foreach ( $wpmind_modules as $wpmind_mod_id => $wpmind_mod ) : ?>
		<div class="simple-row wpmind-module-card <?php echo $wpmind_mod['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
			<span class="tile <?php echo $wpmind_mod['enabled'] ? 'ok' : ''; ?>"><?php echo AdminIcons::svg( $wpmind_mod_icons[ $wpmind_mod_id ] ?? 'modules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<div class="t">
					<?php echo esc_html( $wpmind_mod['name'] ); ?>
					<?php if ( ! $wpmind_mod['can_disable'] ) : ?>
						<span class="pill mute"><?php esc_html_e( '核心', 'wpmind' ); ?></span>
					<?php endif; ?>
					<span class="meta">v<?php echo esc_html( $wpmind_mod['version'] ); ?></span>
				</div>
				<div class="d"><?php echo esc_html( $wpmind_mod['description'] ); ?></div>
			</div>
			<div class="r">
				<?php if ( ! $wpmind_mod['can_disable'] ) : ?>
					<span class="wenpai-switch" title="<?php esc_attr_e( '核心模块不可禁用', 'wpmind' ); ?>">
						<input type="checkbox" role="switch" class="wenpai-switch__input wpmind-module-switch" data-module-id="<?php echo esc_attr( $wpmind_mod_id ); ?>" <?php checked( $wpmind_mod['enabled'] ); ?> disabled>
						<span class="wenpai-switch__track" aria-hidden="true"></span>
						<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已开启', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '未开启', 'wpmind' ); ?></span></span>
					</span>
				<?php else : ?>
					<span class="wenpai-switch">
						<input type="checkbox" role="switch" class="wenpai-switch__input wpmind-module-switch" data-module-id="<?php echo esc_attr( $wpmind_mod_id ); ?>" aria-label="<?php echo esc_attr( $wpmind_mod['name'] ); ?>" <?php checked( $wpmind_mod['enabled'] ); ?>>
						<span class="wenpai-switch__track" aria-hidden="true"></span>
						<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已开启', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '未开启', 'wpmind' ); ?></span></span>
					</span>
				<?php endif; ?>
				<?php if ( $wpmind_mod['enabled'] && ! empty( $wpmind_mod['settings_tab'] ) ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'module', 'module' => $wpmind_mod_id ] ) ); ?>"><?php esc_html_e( '设置', 'wpmind' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>

		<?php if ( empty( $wpmind_modules ) ) : ?>
		<div class="simple-row">
			<div class="d"><?php esc_html_e( '暂无可用模块', 'wpmind' ); ?></div>
		</div>
		<?php endif; ?>
	</section>
</div>
