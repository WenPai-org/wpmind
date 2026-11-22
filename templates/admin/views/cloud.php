<?php
/**
 * 心思 AI 视图（AI 服务独立子标签页）：官方算力状态、心思点数账本、托管模型矩阵与接入进度。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;

( static function (): void {
	$mode        = class_exists( 'Wenpai_Admin_UI', false ) ? Wenpai_Admin_UI::mode() : 'simple';
	$cloud       = SuiteIntegration::cloud_status();
	$cloud_on    = ! empty( $cloud['enabled'] );
	$cloud_bound = ! empty( $cloud['bound'] );
	$cloud_ready = $cloud_on && $cloud_bound;
	$unit_label  = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );
	$base_url    = (string) ( $cloud['base_url'] ?? 'https://mind-api.wenpai.net/v1' );
	$saved_base  = (string) get_option( 'wpmind_cloud_base_url', '' );
	$bind_url    = (string) ( $cloud['bind_url'] ?? '' );
	$topup_url   = (string) ( $cloud['topup_url'] ?? 'https://wenpai.net/mind-credits' );
	$site_uuid   = (string) ( $cloud['site_uuid'] ?? '' );
	$is_const    = defined( 'WPMIND_CLOUD_BASE_URL' ) && '' !== trim( (string) constant( 'WPMIND_CLOUD_BASE_URL' ) );

	$gateway_host = (string) wp_parse_url( $base_url, PHP_URL_HOST );
	if ( '' === $gateway_host ) {
		$gateway_host = 'mind-api.wenpai.net';
	}

	$ecosystems  = SuiteIntegration::ecosystem_integrations();
	$eco_count   = count( $ecosystems );
	$modules_url = AdminPage::url( [ 'tab' => 'modules' ] );
	$routing_url = AdminPage::url( [ 'tab' => 'routing' ] );

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
		__( '文派官方托管算力中心，免自配第三方 API Key，统一按心思点数结算。', 'wpmind' ),
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
		: ( $cloud_bound ? __( '首次调用后同步', 'wpmind' ) : __( '绑定站点后同步', 'wpmind' ) );

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

	// ---- 2. 主配置卡：官方算力开关与网关 ----
	?>
	<form method="post" action="options.php" id="wpmind-cloud-settings-form">
		<?php
		settings_fields( 'wpmind_cloud_settings' );
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '文派心思官方算力', 'wpmind' ),
				'sub'               => __( '一站接入国内与海外主流模型，免去多平台注册与额度维护', 'wpmind' ),
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
				<p class="wenpai-field__desc"><?php esc_html_e( '开启后心思 AI 将作为免密钥通道加入智能路由，可单独使用或与自备 Key 混用。', 'wpmind' ); ?></p>
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
				'meta'         => $cloud_bound
					? __( '站点已通过文派云通行证鉴权，请求经派生令牌直连网关，密钥不落盘。', 'wpmind' )
					: __( '绑定文派云通行证（WPCY）后即可免密钥调用，支持按量计费与月度包。', 'wpmind' ),
				'actions_html' => $cloud_actions,
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	// ---- 3. 托管模型与点数档位 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '托管模型与点数档位', 'wpmind' ),
			'sub'   => __( '根据任务复杂度自动选路，兼顾速度、成本与输出质量', 'wpmind' ),
			'tile'  => false,
		]
	);

	$tiers = [
		[
			'icon'  => 'zap',
			'state' => 'ok',
			'title' => __( '轻量快速档', 'wpmind' ),
			'badge' => __( '别名 · 摘要 · 标签 · 审核', 'wpmind' ),
			'desc'  => __( '覆盖 qwen-flash、deepseek-chat 等高速模型，适合 WPSlug、Auto-Meta 与批量短文本处理。', 'wpmind' ),
			'pill'  => __( '低耗优先', 'wpmind' ),
			'tone'  => 'ok',
		],
		[
			'icon'  => 'sparkle',
			'state' => 'accent',
			'title' => __( '主力写作与结构化档', 'wpmind' ),
			'badge' => __( '笔笙 · GEO · 多语翻译', 'wpmind' ),
			'desc'  => __( '覆盖 qwen-plus、deepseek-v3、gpt-4o-mini 等主力模型，兼顾长文语境与 JSON 结构稳定度。', 'wpmind' ),
			'pill'  => __( '默认均衡', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'target',
			'state' => '',
			'title' => __( '深度推理与合规档', 'wpmind' ),
			'badge' => __( '广告法复核 · 复杂推理', 'wpmind' ),
			'desc'  => __( '覆盖 claude-sonnet、gpt-4o、deepseek-r1 等旗舰模型，面向高准确率复核与复杂决策。', 'wpmind' ),
			'pill'  => __( '高精度', 'wpmind' ),
			'tone'  => 'mute',
		],
		[
			'icon'  => 'image',
			'state' => '',
			'title' => __( '视觉理解与图像生成', 'wpmind' ),
			'badge' => __( '媒体智能 · Alt Text · 配图', 'wpmind' ),
			'desc'  => __( '覆盖 qwen-vl、gpt-image 等多模态接口，直连媒体库 Alt 自动生成与安全检测。', 'wpmind' ),
			'pill'  => __( '按次结算', 'wpmind' ),
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
			'meta'  => __( '实时模型目录与费率由 mind-api.wenpai.net 统一下发；与自备 Key 混用时可到「智能路由」调整优先级。', 'wpmind' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	// ---- 4. 接入进度与生态共用 ----
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '接入进度与生态共用', 'wpmind' ),
			'sub'   => __( '一次开启，全站文派生态插件共享同一份心思点数与月度预算', 'wpmind' ),
			'tile'  => false,
		]
	);

	Wenpai_Admin_UI::row(
		[
			'icon_html'    => AdminIcons::svg( 'lock' ),
			'state'        => $cloud_bound ? 'ok' : '',
			'title'        => __( '1. 绑定文派云站点通行证', 'wpmind' ),
			'desc'         => $cloud_bound
				? __( '当前站点已完成通行证关联，可自动签发短期调用凭证。', 'wpmind' )
				: __( '通过文派叶子（WPCY）关联站点通行证，无需手工维护 API 密钥。', 'wpmind' ),
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
			'title'     => __( '2. 开启心思 AI 算力通道', 'wpmind' ),
			'desc'      => $cloud_on
				? __( '已加入当前站点智能路由，自动承担文本与多模态请求。', 'wpmind' )
				: __( '打开上方「启用心思 AI」开关并保存，即可启用免密钥通道。', 'wpmind' ),
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
			'desc'         => __( '文派别名、笔笙、文派合规、文派文档与文派智储开启 AI 后，自动汇总消耗与预算。', 'wpmind' ),
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

	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
