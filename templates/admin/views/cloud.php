<?php
/**
 * 心思 AI 视图（AI 服务独立子标签页）：体验点数引导、四格状态条、混用策略、任务量换算与生态/代理商托管。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;

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

	$mode_defs = [
		'primary'    => [
			'label' => __( '全托管优先', 'wpmind' ),
			'desc'  => __( '优先走心思 AI，免维护第三方 Key；异常时自动回退已配自备 Key。', 'wpmind' ),
		],
		'fallback'   => [
			'label' => __( '自备 Key 优先 · 心思备援', 'wpmind' ),
			'desc'  => __( '平时用你自备的 Key，仅在主 Key 熔断、限流或欠费时由心思 AI 自动顶上不断服。', 'wpmind' ),
		],
		'capability' => [
			'label' => __( '仅补齐缺失能力', 'wpmind' ),
			'desc'  => __( '常规文本走自备 Key，视觉理解、图像生成与深度推理自动交由心思 AI 处理。', 'wpmind' ),
		],
	];
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

	wpmind_page_head(
		__( '心思 AI', 'wpmind' ),
		__( '文派官方托管算力中心，免自配多平台 API Key，支持与自备 Key 混用及正规国内发票。', 'wpmind' ),
		$mode_switch
	);

	Wenpai_Admin_UI::subnav(
		[
			'label' => __( 'AI 服务子导航', 'wpmind' ),
			'items' => [
				[
					'label'   => __( 'Provider 服务', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'services' ] ),
					'current' => false,
				],
				[
					'label'   => __( '心思 AI', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'cloud' ] ),
					'current' => true,
				],
				[
					'label'   => __( '智能路由', 'wpmind' ),
					'href'    => $routing_url,
					'current' => false,
				],
			],
		]
	);

	// 顶部核心四格统计指标.
	$balance_val = null !== $cloud['credits_balance']
		? number_format_i18n( (int) $cloud['credits_balance'] )
		: '—';
	$balance_sub = null !== $cloud['credits_balance']
		? $unit_label
		: ( $cloud_bound ? __( '首次调用后同步', 'wpmind' ) : __( '绑定即领体验点数', 'wpmind' ) );

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
		: __( '文派云通行证', 'wpmind' );

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
				'text'  => __( '已连接 · 免密钥调用', 'wpmind' ),
				'state' => 'ok',
			]
		)
		: ( $cloud_on
			? Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '待绑定站点', 'wpmind' ),
					'state' => 'warn',
				]
			)
			: Wenpai_Admin_UI::pill_html(
				[
					'text'  => __( '未开启', 'wpmind' ),
					'state' => 'mute',
				]
			) );
	?>

<div class="wenpai-stack" data-wenpai-mode-scope="<?php echo esc_attr( $mode ); ?>">
	<?php
	// ---- 0. 未绑定站点时：新站免费体验点数引导 ----
	if ( ! $cloud_bound && $starter_credits > 0 ) {
		$starter_actions = [];
		if ( '' !== $bind_url ) {
			$starter_actions[] = [
				'label'   => __( '绑定站点领取', 'wpmind' ),
				'href'    => $bind_url,
				'variant' => 'primary',
			];
		}
		if ( '' !== $topup_url ) {
			$starter_actions[] = [
				'label'   => sprintf(
					/* translators: %s: billing unit label */
					__( '了解%s', 'wpmind' ),
					$unit_label
				),
				'href'    => $topup_url,
				'variant' => 'ghost',
				'target'  => '_blank',
			];
		}
		Wenpai_Admin_UI::next(
			[
				'state'   => 'accent',
				'icon'    => 'sparkle',
				'title'   => sprintf(
					/* translators: 1: starter credits count, 2: billing unit label */
					__( '绑定文派云站点即可领取 %1$s 体验%2$s', 'wpmind' ),
					number_format_i18n( $starter_credits ),
					$unit_label
				),
				'desc'    => __( '无需信用卡与多平台实名，10 秒跑通文派别名、媒体 Alt、合规复核与笔笙 AI；预付封顶不超扣，支持国内正规发票。', 'wpmind' ),
				'actions' => $starter_actions,
			]
		);
	}

	// ---- 1. 顶部四格核心指标 ----
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

	// ---- 2. 主配置卡：官方算力开关 + 混用路由策略 ----
	?>
	<form method="post" action="options.php" id="wpmind-cloud-settings-form">
		<?php
		settings_fields( 'wpmind_cloud_settings' );
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '文派心思官方算力', 'wpmind' ),
				'sub'               => __( '一个点数账户通吃国产与海外主流模型、视觉理解与生图，绝不强制锁定', 'wpmind' ),
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
				<p class="wenpai-field__desc"><?php esc_html_e( '开启后即可免自配第三方 Key 调用；自备 Key 功能永久免费开放。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '与自备 Key 混用策略', 'wpmind' ); ?></span></div>
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
						esc_html_e( '留空默认使用 https://mind-api.wenpai.net/v1（私有部署或灰度节点可在此覆盖）。', 'wpmind' );
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
					'label'   => __( '前往绑定站点', 'wpmind' ),
					'href'    => $bind_url,
					'variant' => 'secondary',
				]
			);
		}
		if ( '' !== $topup_url ) {
			$cloud_actions .= Wenpai_Admin_UI::button_html(
				[
					'label'   => sprintf(
						/* translators: %s: billing unit label */
						__( '补充%s', 'wpmind' ),
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
				'meta'         => __( '不存站点内容、不用于模型训练 · 预付封顶不超扣 · 支持国内正规发票 · 随时切回自备 Key 无锁定。', 'wpmind' ),
				'actions_html' => $cloud_actions,
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	// ---- 3. 托管模型与任务量换算（人话标尺） ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '托管模型与任务量换算', 'wpmind' ),
			'sub'   => __( '按 WordPress 常见任务估算消耗，不再被复杂 Token 单价困扰', 'wpmind' ),
			'tile'  => false,
		]
	);

	$tiers = [
		[
			'icon'  => 'zap',
			'state' => 'ok',
			'title' => __( '轻量快速档', 'wpmind' ),
			'badge' => __( '别名 · 摘要 · 标签 · 审核', 'wpmind' ),
			'desc'  => __( 'qwen-flash / deepseek-chat · 1,000 点约可生成 2,000 条语义别名或 500 篇摘要，适合 WPSlug 与批量短文本。', 'wpmind' ),
			'pill'  => __( '约 0.5–2 点 / 次', 'wpmind' ),
			'tone'  => 'ok',
		],
		[
			'icon'  => 'sparkle',
			'state' => 'accent',
			'title' => __( '主力写作与结构化档', 'wpmind' ),
			'badge' => __( '笔笙 · GEO · 多语翻译', 'wpmind' ),
			'desc'  => __( 'qwen-plus / deepseek-v3 / gpt-4o-mini · 1,000 点约可完成 100 篇长文润色或 80 次笔笙页面生成。', 'wpmind' ),
			'pill'  => __( '约 8–15 点 / 次', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'target',
			'state' => '',
			'title' => __( '深度推理与合规档', 'wpmind' ),
			'badge' => __( '广告法复核 · 复杂推理', 'wpmind' ),
			'desc'  => __( 'claude-sonnet / gpt-4o / deepseek-r1 · 1,000 点约可完成 150 次整篇广告法极限词语义复核。', 'wpmind' ),
			'pill'  => __( '约 6–20 点 / 次', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'image',
			'state' => '',
			'title' => __( '视觉理解与图像生成', 'wpmind' ),
			'badge' => __( '媒体智能 · Alt Text · 配图', 'wpmind' ),
			'desc'  => __( 'qwen-vl / gpt-image · 1,000 点约可识别 150 张图片 Alt Text 或生成约 40 张高清配图。', 'wpmind' ),
			'pill'  => __( '按张透明计费', 'wpmind' ),
			'tone'  => 'mute',
		],
	];

	foreach ( $tiers as $tier ) {
		$t_html = esc_html( $tier['title'] ) . ' ' . Wenpai_Admin_UI::pill_html(
			[
				'text'  => $tier['badge'],
				'state' => 'mute',
			]
		);
		Wenpai_Admin_UI::row(
			[
				'icon_html'  => AdminIcons::svg( $tier['icon'] ),
				'state'      => $tier['state'],
				'title_html' => $t_html,
				'desc'       => $tier['desc'],
				'pill'       => [
					'text'  => $tier['pill'],
					'state' => $tier['tone'],
				],
			]
		);
	}

	Wenpai_Admin_UI::card_foot(
		[
			'start' => true,
			'meta'  => __( '实际消耗按请求长度实计，预付封顶绝不倒扣；国内节点直连并支持按月对公开具增值税发票。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 4. 生态共用与代理商托管 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '生态共用与代理商托管', 'wpmind' ),
			'sub'   => __( '一次开启，全站文派生态插件共享账本；支持建站团队多站额度交付', 'wpmind' ),
			'tile'  => false,
		]
	);

	Wenpai_Admin_UI::row(
		[
			'icon_html'    => AdminIcons::svg( 'lock' ),
			'state'        => $cloud_bound ? 'ok' : '',
			'title'        => __( '1. 绑定文派云站点通行证', 'wpmind' ),
			'desc'         => $cloud_bound
				? __( '当前站点已完成通行证关联，自动签发短期派生令牌，密钥不落盘。', 'wpmind' )
				: __( '通过文派叶子（WPCY）一键关联站点，无需手工复制维护 API 密钥。', 'wpmind' ),
			'control_html' => '<div class="wenpai-row wenpai-row--tight">'
				. Wenpai_Admin_UI::pill_html(
					[
						'text'  => $cloud_bound ? __( '已完成', 'wpmind' ) : __( '待绑定', 'wpmind' ),
						'state' => $cloud_bound ? 'ok' : 'warn',
					]
				)
				. ( ! $cloud_bound && '' !== $bind_url
					? Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '去绑定', 'wpmind' ),
							'href'    => $bind_url,
							'variant' => 'ghost',
							'size'    => 'sm',
						]
					)
					: '' )
				. '</div>',
		]
	);

	Wenpai_Admin_UI::row(
		[
			'icon_html' => AdminIcons::svg( 'sparkle' ),
			'state'     => $cloud_on ? 'ok' : '',
			'title'     => __( '2. 开启心思 AI 并选择混用策略', 'wpmind' ),
			'desc'      => $cloud_on
				? sprintf(
					/* translators: %s: active hybrid cloud mode label */
					__( '当前模式：%s，已实时接入站点智能路由与故障转移链。', 'wpmind' ),
					$mode_defs[ $cloud_mode ]['label']
				)
				: __( '打开上方开关并按需选择全托管、故障备援或能力补缺模式。', 'wpmind' ),
			'pill'      => [
				'text'  => $cloud_on ? __( '已开启', 'wpmind' ) : __( '未开启', 'wpmind' ),
				'state' => $cloud_on ? 'ok' : 'mute',
			],
		]
	);

	Wenpai_Admin_UI::row(
		[
			'icon_html'    => AdminIcons::svg( 'modules' ),
			'state'        => $eco_count > 0 ? 'ok' : '',
			'title'        => __( '3. 全站文派生态插件共用点数', 'wpmind' ),
			'desc'         => __( '文派别名、笔笙、文派合规、文派文档与文派智储开启 AI 后，按插件清晰记账、共用月度预算。', 'wpmind' ),
			'control_html' => '<div class="wenpai-row wenpai-row--tight">'
				. Wenpai_Admin_UI::pill_html(
					[
						'text'  => sprintf(
							/* translators: %d: integrated ecosystem plugin count */
							__( '%d 个生态已联动', 'wpmind' ),
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
			'title'     => __( '4. 建站工作室多站额度与白标托管', 'wpmind' ),
			'desc'      => __( '团队可在文派云统一采购点数池并按客户站分配月度上限，客户后台不暴露主密钥，配合 WPCY 白标一键交付。', 'wpmind' ),
			'pill'      => [
				'text'  => $cloud_managed ? __( '套件已托管', 'wpmind' ) : __( '适合建站团队', 'wpmind' ),
				'state' => $cloud_managed ? 'ok' : 'mute',
			],
		]
	);

	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
