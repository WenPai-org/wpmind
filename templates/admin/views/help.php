<?php
/**
 * 帮助视图：快速开始指引 + 常见问题折叠问答 + 外部资源（遵守白标 display()）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;

( static function (): void {
	$wpmind_display = SuiteIntegration::display();

	wpmind_page_head(
		__( '帮助', 'wpmind' ),
		__( '先连接算力或密钥，再按需启用模块。', 'wpmind' )
	);
	?>

<div class="wenpai-stack">
	<?php
	Wenpai_Admin_UI::card_open(
		[
			'title' => __( '快速开始', 'wpmind' ),
			'sub'   => __( '推荐顺序：AI 服务 → 智能路由 → 模块 → 分析', 'wpmind' ),
			'tile'  => false,
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html'  => AdminIcons::svg( 'key' ),
			'state'      => 'accent',
			'title'      => __( '配置 AI 服务', 'wpmind' ),
			'desc'       => __( '开启官方算力或填入密钥并测试连接。', 'wpmind' ),
			'href'       => AdminPage::url( [ 'tab' => 'services' ] ),
			'right_html' => AdminIcons::svg( 'arrow-right' ),
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html'  => AdminIcons::svg( 'route' ),
			'state'      => 'accent',
			'title'      => __( '智能路由', 'wpmind' ),
			'desc'       => __( '选择路由策略与故障转移优先级。', 'wpmind' ),
			'href'       => AdminPage::url( [ 'tab' => 'routing' ] ),
			'right_html' => AdminIcons::svg( 'arrow-right' ),
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html'  => AdminIcons::svg( 'modules' ),
			'state'      => 'accent',
			'title'      => __( '启用能力模块', 'wpmind' ),
			'desc'       => __( '按需打开 GEO、精确缓存或 API 网关。', 'wpmind' ),
			'href'       => AdminPage::url( [ 'tab' => 'modules' ] ),
			'right_html' => AdminIcons::svg( 'arrow-right' ),
		]
	);
	Wenpai_Admin_UI::row(
		[
			'icon_html'  => AdminIcons::svg( 'analytics' ),
			'state'      => 'accent',
			'title'      => __( '用量与预算', 'wpmind' ),
			'desc'       => __( '查看调用统计并设置每日或每月预算。', 'wpmind' ),
			'href'       => AdminPage::url( [ 'tab' => 'analytics' ] ),
			'right_html' => AdminIcons::svg( 'arrow-right' ),
		]
	);
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::help(
		[
			'groups' => [
				[
					'title'       => __( '常见问题与部署说明', 'wpmind' ),
					'icon'        => 'help',
					'collapsible' => true,
					'open'        => true,
					'numbered'    => false,
					'steps'       => [
						[
							'title' => __( '官方算力与自有 API Key 可以同时启用吗？', 'wpmind' ),
							'body'  => __( '可以。启用后两者会一起参与智能路由，并可在「智能路由」页固定优先顺序。', 'wpmind' ),
						],
						[
							'title' => __( '如何通过 wp-config.php 锁定服务商密钥？', 'wpmind' ),
							'body'  => __( '定义 WPMIND_LOCKED_PROVIDERS 与 WPMIND_API_KEYS 常量后，对应服务商密钥由配置文件托管且后台隐藏输入框。', 'wpmind' ),
						],
						[
							'title' => __( '如何排查某项服务熔断或连接失败？', 'wpmind' ),
							'body'  => __( '在「AI 服务」页展开对应端点点击「测试连接」，或到「工具」页导出脱敏诊断包提交支持。', 'wpmind' ),
						],
					],
				],
			],
		]
	);

	$has_ext = '' !== $wpmind_display['docs_url']
		|| '' !== $wpmind_display['feedback_url']
		|| '' !== $wpmind_display['github_url'];
	if ( $has_ext ) {
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '文档与支持', 'wpmind' ),
				'tile'  => false,
			]
		);
		if ( '' !== $wpmind_display['docs_url'] ) {
			Wenpai_Admin_UI::row(
				[
					'icon_html'  => AdminIcons::svg( 'doc' ),
					'title'      => __( '使用文档', 'wpmind' ),
					'desc'       => __( '配置指引、模块说明与开发接口文档。', 'wpmind' ),
					'right_html' => Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '查看文档', 'wpmind' ),
							'href'    => $wpmind_display['docs_url'],
							'variant' => 'ghost',
							'size'    => 'sm',
							'target'  => '_blank',
						]
					),
				]
			);
		}
		if ( '' !== $wpmind_display['feedback_url'] ) {
			Wenpai_Admin_UI::row(
				[
					'icon_html'  => AdminIcons::svg( 'feedback' ),
					'title'      => __( '技术支持与反馈', 'wpmind' ),
					'desc'       => __( '提交问题时建议附带脱敏诊断包。', 'wpmind' ),
					'right_html' => Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '提交反馈', 'wpmind' ),
							'href'    => $wpmind_display['feedback_url'],
							'variant' => 'ghost',
							'size'    => 'sm',
							'target'  => '_blank',
						]
					),
				]
			);
		}
		if ( '' !== $wpmind_display['github_url'] ) {
			Wenpai_Admin_UI::row(
				[
					'icon_html'  => AdminIcons::svg( 'package' ),
					'title'      => __( '开源仓库', 'wpmind' ),
					'desc'       => __( '查看源代码与各版本更新记录。', 'wpmind' ),
					'right_html' => Wenpai_Admin_UI::button_html(
						[
							'label'   => __( '访问仓库', 'wpmind' ),
							'href'    => $wpmind_display['github_url'],
							'variant' => 'ghost',
							'size'    => 'sm',
							'target'  => '_blank',
						]
					),
				]
			);
		}
		Wenpai_Admin_UI::card_close();
	}
	?>
</div>
	<?php
} )();
