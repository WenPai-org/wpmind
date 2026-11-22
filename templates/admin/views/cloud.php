<?php
/**
 * 心思 AI 视图（AI 服务默认首屏）：四格点数状态条、官方算力开关与混用策略、折叠式换算与生态托管说明。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Provider\WenpaiCloud;

( static function (): void {
	$mode            = class_exists( 'Wenpai_Admin_UI', false ) ? Wenpai_Admin_UI::mode() : 'simple';
	$cloud           = SuiteIntegration::cloud_status();
	$cloud_on        = ! empty( $cloud['enabled'] );
	$cloud_mode      = (string) ( $cloud['mode'] ?? get_option( 'wpmind_cloud_mode', 'primary' ) );
	$cloud_bound     = ! empty( $cloud['bound'] );
	$cloud_ready     = $cloud_on && $cloud_bound;
	$cloud_managed   = ! empty( $cloud['managed_by_suite'] );
	$starter_credits = (int) ( $cloud['starter_credits'] ?? 500 );
	$unit_label      = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );
	$base_url        = (string) ( $cloud['base_url'] ?? 'https://mind-api.wenpai.net/v1' );
	$saved_base      = (string) get_option( 'wpmind_cloud_base_url', '' );
	$bind_url        = (string) ( $cloud['bind_url'] ?? '' );
	$topup_url       = (string) ( $cloud['topup_url'] ?? 'https://wenpai.net/mind-credits' );
	$site_uuid       = (string) ( $cloud['site_uuid'] ?? '' );
	$is_const        = defined( 'WPMIND_CLOUD_BASE_URL' ) && '' !== trim( (string) constant( 'WPMIND_CLOUD_BASE_URL' ) );

	$gateway_host = (string) wp_parse_url( $base_url, PHP_URL_HOST );
	if ( '' === $gateway_host ) {
		$gateway_host = 'mind-api.wenpai.net';
	}

	$ecosystems  = SuiteIntegration::ecosystem_integrations();
	$eco_count   = count( $ecosystems );
	$modules_url = AdminPage::url( [ 'tab' => 'modules' ] );
	$routing_url = AdminPage::url( [ 'tab' => 'routing' ] );

	$mode_defs = WenpaiCloud::mode_options();

	if ( ! isset( $mode_defs[ $cloud_mode ] ) ) {
		$cloud_mode = 'primary';
	}
	$mode_items = [];
	foreach ( $mode_defs as $mkey => $mdef ) {
		$mode_items[ $mkey ] = $mdef['label'];
	}

	$mode_switch = Wenpai_Admin_UI::mode_switch_html(
		[
			'wrap'  => false,
			'label' => __( '显示模式', 'wpmind' ),
			'items' => [
				[
					'label'   => __( '简单', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'  => 'cloud',
							'mode' => 'simple',
						]
					),
					'current' => 'simple' === $mode,
				],
				[
					'label'   => __( '高级', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'  => 'cloud',
							'mode' => 'advanced',
						]
					),
					'current' => 'advanced' === $mode,
				],
			],
		]
	);

	$page_lede = ( ! $cloud_bound && $starter_credits > 0 )
		? sprintf(
			/* translators: 1: starter credits count, 2: billing unit label */
			__( '文派官方托管算力，绑定站点即领 %1$s 体验%2$s，支持与自备 Key 混用。', 'wpmind' ),
			number_format_i18n( $starter_credits ),
			$unit_label
		)
		: __( '文派官方托管算力，免配多平台密钥，支持与自备 Key 混用。', 'wpmind' );

	wpmind_page_head(
		__( '心思 AI', 'wpmind' ),
		$page_lede,
		$mode_switch
	);

	Wenpai_Admin_UI::subnav(
		[
			'label' => __( 'AI 服务子导航', 'wpmind' ),
			'items' => [
				[
					'label'   => __( '心思 AI', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'cloud' ] ),
					'current' => true,
				],
				[
					'label'   => __( 'Provider 服务', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'services' ] ),
					'current' => false,
				],
				[
					'label'   => __( '智能路由', 'wpmind' ),
					'href'    => $routing_url,
					'current' => false,
				],
			],
		]
	);

	// 顶部核心四格指标.
	$balance_val = null !== $cloud['credits_balance']
		? number_format_i18n( (int) $cloud['credits_balance'] )
		: '—';
	if ( null !== $cloud['credits_balance'] ) {
		$balance_sub = $unit_label;
	} elseif ( $cloud_bound ) {
		$balance_sub = __( '调用后同步', 'wpmind' );
	} elseif ( $starter_credits > 0 ) {
		$balance_sub = sprintf(
			/* translators: %s: starter credits count */
			__( '绑定领 %s 点', 'wpmind' ),
			number_format_i18n( $starter_credits )
		);
	} else {
		$balance_sub = $unit_label;
	}

	$used_month = (int) ( $cloud['credits_used_month'] ?? 0 );
	$used_limit = $cloud['credits_limit_month'] ?? null;
	$used_sub   = null !== $used_limit && (int) $used_limit > 0
		? sprintf(
			/* translators: %s: monthly credit limit */
			__( '上限 %s', 'wpmind' ),
			number_format_i18n( (int) $used_limit )
		)
		: $unit_label;

	$site_val = $cloud_bound ? __( '已绑定', 'wpmind' ) : __( '未绑定', 'wpmind' );
	$site_sub = $cloud_bound && '' !== $site_uuid
		? substr( $site_uuid, 0, 8 ) . '…'
		: __( '文派云', 'wpmind' );

	$stat_cells = [
		[
			'icon'  => 'sparkle',
			'label' => sprintf(
				/* translators: %s: billing unit label */
				__( '剩余%s', 'wpmind' ),
				$unit_label
			),
			'value' => $balance_val,
			'unit'  => $balance_sub,
		],
		[
			'icon'  => 'pulse',
			'label' => __( '本月消耗', 'wpmind' ),
			'value' => number_format_i18n( $used_month ),
			'unit'  => $used_sub,
		],
		[
			'icon'  => 'globe',
			'label' => __( '托管网关', 'wpmind' ),
			'value' => __( '国内直连', 'wpmind' ),
			'unit'  => 'mind-api.wenpai.net' === $gateway_host ? 'mind-api' : $gateway_host,
		],
		[
			'icon'  => 'lock',
			'label' => __( '站点通行证', 'wpmind' ),
			'value' => $site_val,
			'unit'  => $site_sub,
		],
	];

	$status_pill = $cloud_ready
		? Wenpai_Admin_UI::pill_html(
			[
				'text'  => __( '已连接', 'wpmind' ),
				'state' => 'ok',
			]
		)
		: ( $cloud_on
			? Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '待绑定', 'wpmind' ),
					'state' => 'warn',
				]
			)
			: Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '未开启', 'wpmind' ),
					'state' => 'mute',
				]
			) );

	$chevron_svg = class_exists( 'Wenpai_Admin_Icons', false ) && \Wenpai_Admin_Icons::has( 'chevron-down' )
		? \Wenpai_Admin_Icons::svg( 'chevron-down', 'wenpai-ico' )
		: '';
	?>

<div class="wenpai-stack" data-wenpai-mode-scope="<?php echo esc_attr( $mode ); ?>">
	<?php
	// ---- 1. 顶部四格状态条 ----
	Wenpai_Admin_UI::card_open();
	?>
	<div class="wpmind-ov__stats">
		<?php foreach ( $stat_cells as $cell ) : ?>
			<div class="wpmind-ov__stat">
				<span class="wpmind-ov__stat-ico"><?php echo AdminIcons::svg( $cell['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div class="wpmind-ov__stat-text">
					<div class="wpmind-ov__stat-k"><?php echo esc_html( $cell['label'] ); ?></div>
					<div class="wpmind-ov__stat-v"><?php echo esc_html( $cell['value'] ); ?><small><?php echo esc_html( $cell['unit'] ); ?></small></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	Wenpai_Admin_UI::card_close();

	// ---- 2. 主设置卡：开关 + 混用策略 ----
	?>
	<form method="post" action="options.php" id="wpmind-cloud-settings-form">
		<?php
		settings_fields( 'wpmind_cloud_settings' );
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '文派心思官方算力', 'wpmind' ),
				'sub'               => __( '免海外信用卡与多平台实名，预付封顶不超扣，支持国内发票', 'wpmind' ),
				'icon'              => 'sparkle',
				'state'             => 'accent',
				'head_actions_html' => $status_pill,
			]
		);
		?>
		<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind-cloud-enabled"><?php esc_html_e( '启用心思 AI', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<?php
				$cloud_sw = Wenpai_Admin_Settings::switch_html(
					[
						'id'      => 'wpmind-cloud-enabled',
						'name'    => 'wpmind_cloud_enabled',
						'checked' => $cloud_on,
						'on'      => __( '已开启', 'wpmind' ),
						'off'     => __( '未开启', 'wpmind' ),
					]
				);
				echo $cloud_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
				?>
				<p class="wenpai-field__desc"><?php esc_html_e( '开启后免自配 Key 直接调用；自备 Key 永久免费。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '混用策略', 'wpmind' ); ?></span></div>
			<div class="field-ctl">
				<?php
				Wenpai_Admin_UI::seg_radio(
					[
						'name'  => 'wpmind_cloud_mode',
						'value' => $cloud_mode,
						'items' => $mode_items,
						'label' => __( '与自备 Key 混用策略', 'wpmind' ),
					]
				);
				?>
				<p class="wenpai-field__desc"><?php echo esc_html( $mode_defs[ $cloud_mode ]['desc'] ); ?></p>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_cloud_base_url"><?php esc_html_e( '自定义网关地址', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<input type="url" id="wpmind_cloud_base_url" name="wpmind_cloud_base_url" value="<?php echo esc_attr( $saved_base ); ?>" placeholder="<?php echo esc_attr( $base_url ); ?>" <?php disabled( $is_const ); ?>>
				<p class="wenpai-field__desc">
					<?php
					if ( $is_const ) {
						esc_html_e( '当前由 wp-config.php 常量 WPMIND_CLOUD_BASE_URL 锁定。', 'wpmind' );
					} else {
						esc_html_e( '留空默认使用 https://mind-api.wenpai.net/v1。', 'wpmind' );
					}
					?>
				</p>
			</div>
		</div>

		<?php
		$cloud_actions = '<span class="wenpai-actions">';
		if ( ! $cloud_bound && '' !== $bind_url ) {
			$cloud_actions .= Wenpai_Admin_UI::button_html(
				[
					'label'   => __( '绑定站点领体验点', 'wpmind' ),
					'href'    => $bind_url,
					'variant' => 'secondary',
				]
			);
		}
		if ( '' !== $topup_url ) {
			$cloud_actions .= Wenpai_Admin_UI::button_html(
				[
					'label'   => $cloud_bound
						? sprintf(
							/* translators: %s: billing unit label */
							__( '补充%s', 'wpmind' ),
							$unit_label
						)
						: sprintf(
							/* translators: %s: billing unit label */
							__( '了解%s', 'wpmind' ),
							$unit_label
						),
					'href'    => $topup_url,
					'variant' => $cloud_bound ? 'secondary' : 'ghost',
					'target'  => '_blank',
				]
			);
		}
		$cloud_actions .= Wenpai_Admin_UI::button_html(
			[
				'label'   => __( '保存设置', 'wpmind' ),
				'type'    => 'submit',
				'name'    => 'submit',
				'variant' => 'primary',
			]
		);
		$cloud_actions .= '</span>';

		Wenpai_Admin_UI::card_foot(
			[
				'start'        => true,
				'meta'         => __( '内容零留存、不用于训练 · 随时切回自备 Key', 'wpmind' ),
				'actions_html' => $cloud_actions,
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	// ---- 3. 折叠参考卡 1：任务量与点数参考（渐进式披露，默认收起） ----
	$tiers = [
		[
			'icon'  => 'zap',
			'state' => 'ok',
			'title' => __( '轻量快速档', 'wpmind' ),
			'desc'  => __( 'qwen-flash / deepseek-chat · 1,000 点约 2,000 条别名或 500 篇摘要', 'wpmind' ),
			'pill'  => __( '0.5–2 点 / 次', 'wpmind' ),
			'tone'  => 'ok',
		],
		[
			'icon'  => 'sparkle',
			'state' => 'accent',
			'title' => __( '主力写作档', 'wpmind' ),
			'desc'  => __( 'qwen-plus / gpt-4o-mini · 1,000 点约 100 篇长文润色或 80 次笔笙生成', 'wpmind' ),
			'pill'  => __( '8–15 点 / 次', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'target',
			'state' => '',
			'title' => __( '深度推理档', 'wpmind' ),
			'desc'  => __( 'claude-sonnet / gpt-4o / deepseek-r1 · 1,000 点约 150 次合规复核', 'wpmind' ),
			'pill'  => __( '6–20 点 / 次', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'image',
			'state' => '',
			'title' => __( '视觉与生图', 'wpmind' ),
			'desc'  => __( 'qwen-vl / gpt-image · 1,000 点约 150 张 Alt 识别或 40 张高清配图', 'wpmind' ),
			'pill'  => __( '按张计费', 'wpmind' ),
			'tone'  => 'mute',
		],
	];
	?>
	<details class="card wpmind-ov__fold">
		<summary>
			<span class="wpmind-ov__fold-ico"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit SVG. ?></span>
			<span class="section-title"><?php esc_html_e( '任务量与点数参考', 'wpmind' ); ?></span>
			<span class="meta"><?php esc_html_e( '4 档模型 · 约 0.5–20 点 / 次', 'wpmind' ); ?></span>
		</summary>
		<div class="wpmind-ov__fold-body">
			<div>
				<?php
				foreach ( $tiers as $tier ) {
					Wenpai_Admin_UI::row(
						[
							'icon_html' => AdminIcons::svg( $tier['icon'] ),
							'state'     => $tier['state'],
							'title'     => $tier['title'],
							'desc'      => $tier['desc'],
							'pill'      => [
								'text'  => $tier['pill'],
								'state' => $tier['tone'],
							],
						]
					);
				}
				?>
			</div>
			<?php
			Wenpai_Admin_UI::card_foot(
				[
					'start' => true,
					'meta'  => __( '按实际请求长度计费，预付封顶不超扣；支持按月对公开具增值税发票。', 'wpmind' ),
				]
			);
			?>
		</div>
	</details>

	<?php
	// ---- 4. 折叠参考卡 2：生态共用与多站托管（默认收起） ----
	?>
	<details class="card wpmind-ov__fold">
		<summary>
			<span class="wpmind-ov__fold-ico"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit SVG. ?></span>
			<span class="section-title"><?php esc_html_e( '生态共用与多站托管', 'wpmind' ); ?></span>
			<span class="meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: integrated ecosystem plugin count */
						__( '已联动 %d 个插件 · 支持建站团队多站额度', 'wpmind' ),
						$eco_count
					)
				);
				?>
			</span>
		</summary>
		<div class="wpmind-ov__fold-body">
			<div>
				<?php
				Wenpai_Admin_UI::row(
					[
						'icon_html'    => AdminIcons::svg( 'modules' ),
						'state'        => $eco_count > 0 ? 'ok' : '',
						'title'        => __( '全站文派生态插件共用点数', 'wpmind' ),
						'desc'         => __( '文派别名、笔笙、文派合规、文派文档与文派智储共享同一额度，按插件独立记账。', 'wpmind' ),
						'control_html' => '<div class="wenpai-row wenpai-row--tight">'
							. Wenpai_Admin_UI::pill_html(
								[
									'text'  => sprintf(
										/* translators: %d: integrated ecosystem plugin count */
										__( '%d 个已联动', 'wpmind' ),
										$eco_count
									),
									'state' => $eco_count > 0 ? 'ok' : 'mute',
								]
							)
							. Wenpai_Admin_UI::button_html(
								[
									'label'   => __( '查看模块', 'wpmind' ),
									'href'    => $modules_url,
									'variant' => 'ghost',
									'size'    => 'sm',
								]
							)
							. '</div>',
					]
				);

				Wenpai_Admin_UI::row(
					[
						'icon_html' => AdminIcons::svg( 'layers' ),
						'state'     => $cloud_managed ? 'ok' : '',
						'title'     => __( '建站工作室多站额度与白标托管', 'wpmind' ),
						'desc'      => __( '在文派云统一采购点数池并按客户站分配月度上限，客户后台不暴露主密钥。', 'wpmind' ),
						'pill'      => [
							'text'  => $cloud_managed ? __( '套件已托管', 'wpmind' ) : __( '支持白标交付', 'wpmind' ),
							'state' => $cloud_managed ? 'ok' : 'mute',
						],
					]
				);
				?>
			</div>
		</div>
	</details>
</div>
	<?php
} )();
