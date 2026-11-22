<?php
/**
 * GEO 优化模块设置（kit 化）：URL 驱动 subnav + statbar + wenpai-split + view_tabs + field--row。
 * 保留全部 input name 供 admin-geo.js 统一收集提交。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;

( static function (): void {
	$is_enabled = static function ( string $option, string $fallback = '1' ): bool {
		$value = get_option( $option, $fallback );
		return '1' === $value || true === $value || 1 === $value;
	};

	$standalone_feed   = $is_enabled( 'wpmind_standalone_markdown_feed', '0' );
	$geo_enabled       = $is_enabled( 'wpmind_geo_enabled', '1' );
	$chinese_optimize  = $is_enabled( 'wpmind_chinese_optimize', '1' );
	$geo_signals       = $is_enabled( 'wpmind_geo_signals', '1' );
	$crawler_tracking  = $is_enabled( 'wpmind_crawler_tracking', '1' );
	$llms_txt_enabled  = $is_enabled( 'wpmind_llms_txt_enabled', '1' );
	$schema_enabled    = $is_enabled( 'wpmind_schema_enabled', '1' );
	$schema_mode       = (string) get_option( 'wpmind_schema_mode', 'auto' );
	$ai_indexing       = $is_enabled( 'wpmind_ai_indexing_enabled', '0' );
	$ai_declaration    = (string) get_option( 'wpmind_ai_default_declaration', 'original' );
	$ai_excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
	if ( ! is_array( $ai_excluded_types ) ) {
		$ai_excluded_types = [];
	}
	$ai_sitemap_enabled = $is_enabled( 'wpmind_ai_sitemap_enabled', '0' );
	$ai_sitemap_max     = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );
	$robots_ai_enabled  = $is_enabled( 'wpmind_robots_ai_enabled', '0' );
	$robots_ai_rules    = get_option( 'wpmind_robots_ai_rules', [] );
	if ( ! is_array( $robots_ai_rules ) ) {
		$robots_ai_rules = [];
	}
	$ai_summary_enabled    = $is_enabled( 'wpmind_ai_summary_enabled', '0' );
	$ai_summary_fallback   = (string) get_option( 'wpmind_ai_summary_fallback', 'excerpt' );
	$entity_linker_enabled = $is_enabled( 'wpmind_entity_linker_enabled', '0' );
	$brand_entity_enabled  = $is_enabled( 'wpmind_brand_entity_enabled', '0' );
	$brand_org_type        = (string) get_option( 'wpmind_brand_org_type', 'Organization' );

	$official_installed = class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' );

	$crawler_tracker = new \WPMind\Modules\Geo\CrawlerTracker();
	$crawler_stats   = $crawler_tracker->get_stats();
	$ai_summary_data = $crawler_tracker->get_ai_summary();

	$display        = SuiteIntegration::display();
	$learn_more_url = $display['docs_url'];

	$sections = [
		'basics'   => __( '基础设置', 'wpmind' ),
		'content'  => __( '内容输出', 'wpmind' ),
		'schema'   => __( '结构化数据', 'wpmind' ),
		'control'  => __( 'AI 索引', 'wpmind' ),
		'crawlers' => __( '爬虫管理', 'wpmind' ),
		'brand'    => __( '品牌实体', 'wpmind' ),
	];
	$section  = Wenpai_Admin_UI::section( $sections, 'basics' );

	$subnav_items = [];
	foreach ( $sections as $sec_id => $sec_label ) {
		$subnav_items[] = [
			'id'      => $sec_id,
			'label'   => $sec_label,
			'url'     => AdminPage::url(
				[
					'tab'     => 'module',
					'module'  => 'geo',
					'section' => $sec_id,
				]
			),
			'current' => $section === $sec_id,
		];
	}

	$wpmind_switch = static function ( string $id, string $name, bool $checked, string $label, string $desc = '', bool $disabled = false ): void {
		$switch_html = Wenpai_Admin_Settings::switch_html(
			[
				'id'       => $id,
				'name'     => $name,
				'checked'  => $checked,
				'disabled' => $disabled,
				'on'       => __( '已开启', 'wpmind' ),
				'off'      => __( '未开启', 'wpmind' ),
			]
		);
		echo '<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl">'
		. $switch_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. ( '' !== $desc ? '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>' : '' )
		. '</div></div>';
	};

	$wpmind_info_card = static function ( string $title, array $items, string $more_url = '' ): void {
		Wenpai_Admin_UI::card_open(
			[
				'title' => $title,
				'tile'  => false,
			]
		);
		$feed_items = [];
		foreach ( $items as $idx => $line ) {
			$feed_items[] = [
				'tag'   => sprintf( '%02d', $idx + 1 ),
				'title' => $line,
			];
		}
		Wenpai_Admin_UI::tag_feed(
			[
				'class' => 'wpmind-info-feed',
				'items' => $feed_items,
			]
		);
		if ( '' !== $more_url ) {
			Wenpai_Admin_UI::card_foot(
				[
					'actions' => [
						[
							'label'   => __( '查看文档', 'wpmind' ),
							'href'    => $more_url,
							'variant' => 'ghost',
							'size'    => 'sm',
							'target'  => '_blank',
						],
					],
				]
			);
		}
		Wenpai_Admin_UI::card_close();
	};
	?>

<div class="wpmind-geo-panel wenpai-stack">
	<?php
	Wenpai_Admin_UI::subnav(
		[
			'items'   => $subnav_items,
			'current' => $section,
			'label'   => __( 'GEO 优化分区', 'wpmind' ),
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label' => __( 'AI 爬虫访问', 'wpmind' ),
					'value' => number_format_i18n( (int) ( $ai_summary_data['total_ai_hits'] ?? 0 ) ),
				],
				[
					'label' => __( '搜索引擎访问', 'wpmind' ),
					'value' => number_format_i18n( (int) ( $ai_summary_data['total_search_hits'] ?? 0 ) ),
				],
				[
					'label' => __( '已识别爬虫', 'wpmind' ),
					'value' => (string) count( (array) ( $ai_summary_data['ai_crawlers'] ?? [] ) ),
				],
				[
					'label' => 'Markdown Feed',
					'value' => ( $standalone_feed || get_option( 'ai_experiments_markdown_feed_enabled', false ) )
						? __( '已开启', 'wpmind' )
						: __( '未开启', 'wpmind' ),
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>

	<!-- ========== 基础设置 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'basics', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( 'GEO 增强', 'wpmind' ),
						'sub'   => __( '优化内容结构与引用信号，提升 AI 搜索可见度。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-geo-enabled', 'wpmind_geo_enabled', $geo_enabled, __( '启用 GEO 增强', 'wpmind' ), __( '控制前台 GEO 信号与结构化输出。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-geo-zh', 'wpmind_chinese_optimize', $chinese_optimize, __( '中文内容优化', 'wpmind' ), __( '优化中英文混排、标点与段落结构。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-geo-signals', 'wpmind_geo_signals', $geo_signals, __( 'GEO 信号注入', 'wpmind' ), __( '注入作者、发布日期与引用权威信号。', 'wpmind' ) );
				$wpmind_switch( 'wpmind-geo-crawler', 'wpmind_crawler_tracking', $crawler_tracking, __( 'AI 爬虫追踪', 'wpmind' ), __( '统计 GPTBot、ClaudeBot 等爬虫访问。', 'wpmind' ) );
				Wenpai_Admin_UI::card_close();
				?>
			</div>
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( 'AI 爬虫统计', 'wpmind' ),
						'tile'  => false,
					]
				);
				if ( empty( $crawler_stats ) ) {
					Wenpai_Admin_UI::empty_state(
						[
							'text' => __( '还没有爬虫访问记录', 'wpmind' ),
							'meta' => __( '开启追踪后，AI 爬虫访问会统计在这里。', 'wpmind' ),
						]
					);
				} else {
					$bot_items = [];
					foreach ( $crawler_stats as $crawler => $data ) {
						$bot_items[] = [
							'tag'   => ! empty( $data['is_ai'] ) ? 'AI' : __( '搜索', 'wpmind' ),
							'tone'  => ! empty( $data['is_ai'] ) ? 'ok' : '',
							'title' => (string) $crawler,
							'note'  => (string) ( $data['company'] ?? '' ),
							'when'  => number_format( (int) ( $data['total_hits'] ?? 0 ) ) . ' ' . __( '次', 'wpmind' ),
						];
					}
					Wenpai_Admin_UI::tag_feed(
						[
							'scrollable' => true,
							'items'      => $bot_items,
						]
					);
				}
				Wenpai_Admin_UI::card_close();

				$wpmind_info_card(
					__( '什么是 GEO', 'wpmind' ),
					[
						__( '输出结构化 Markdown，便于 AI 引擎解析。', 'wpmind' ),
						__( '注入作者、日期与品牌实体权威信号。', 'wpmind' ),
						__( '精细管理 AI 爬虫抓取与索引权限。', 'wpmind' ),
					],
					$learn_more_url
				);
				?>
			</div>
		</div>
	</div>

	<!-- ========== 内容输出 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'content', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => 'Markdown Feed',
						'sub'   => __( '提供纯净 Markdown 格式供 AI 爬虫读取。', 'wpmind' ),
						'tile'  => false,
					]
				);
				if ( $official_installed ) {
					wpmind_notice(
						[
							'level' => 'info',
							'wrap'  => false,
							'text'  => __( '已检测到官方 AI Experiments 插件，将自动增强其 Markdown Feed。', 'wpmind' ),
						]
					);
				}
				$wpmind_switch( 'wpmind-md-feed', 'wpmind_standalone_markdown_feed', $standalone_feed, __( '启用独立 Markdown Feed', 'wpmind' ), __( '提供 /?feed=markdown 与 .md 路径。', 'wpmind' ), $official_installed );
				if ( $standalone_feed ) :
					?>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '访问地址', 'wpmind' ); ?></span></div>
						<div class="field-ctl">
							<div class="wenpai-row wenpai-row--tight">
								<?php echo wpmind_code_inline_html( [ 'code' => home_url( '/?feed=markdown' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo wpmind_code_inline_html( [ 'code' => home_url( '/your-post-slug.md' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
					<?php
				endif;
				Wenpai_Admin_UI::card_close();

				Wenpai_Admin_UI::card_open(
					[
						'title' => 'llms.txt',
						'sub'   => __( '在 /llms.txt 提供站点导航与内容索引。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-llms-txt', 'wpmind_llms_txt_enabled', $llms_txt_enabled, __( '启用 llms.txt', 'wpmind' ), __( '为 AI 引擎提供站点结构索引。', 'wpmind' ) );
	if ( $llms_txt_enabled ) :
		?>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '访问地址', 'wpmind' ); ?></span></div>
						<div class="field-ctl">
				<?php echo wpmind_code_inline_html( [ 'code' => \WPMind\Modules\Geo\LlmsTxtGenerator::get_public_url() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
								<p class="wenpai-field__desc"><?php esc_html_e( '朴素固定链接下请使用上方参数地址。', 'wpmind' ); ?></p>
							<?php endif; ?>
				<?php foreach ( \WPMind\Modules\Geo\LlmsTxtGenerator::detect_conflicts() as $conflict_msg ) : ?>
								<p class="wenpai-field__desc"><?php echo esc_html( $conflict_msg ); ?></p>
							<?php endforeach; ?>
						</div>
					</div>
					<?php
				endif;
				Wenpai_Admin_UI::card_close();

				Wenpai_Admin_UI::card_open(
					[
						'title' => 'AI Sitemap',
						'sub'   => __( '在 /ai-sitemap.xml 输出 AI 专用站点地图。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-ai-sitemap', 'wpmind_ai_sitemap_enabled', $ai_sitemap_enabled, __( '启用 AI Sitemap', 'wpmind' ), __( '包含内容声明与摘要元数据。', 'wpmind' ) );
	?>
				<div class="wenpai-field wenpai-field--row field"<?php echo $ai_sitemap_enabled ? '' : ' hidden'; ?>>
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-sitemap-max"><?php esc_html_e( '最大条目数', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="field-ctl-row">
							<input type="number" class="wpmind-num" id="wpmind-ai-sitemap-max" name="wpmind_ai_sitemap_max_entries" value="<?php echo esc_attr( (string) $ai_sitemap_max ); ?>" min="10" max="5000" step="10">
							<span class="meta">10–5000</span>
						</div>
						<p class="wenpai-field__desc"><?php echo wpmind_code_inline_html( [ 'code' => \WPMind\Modules\Geo\AiSitemapGenerator::get_public_url() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					</div>
				</div>
				<?php
				Wenpai_Admin_UI::card_close();

				Wenpai_Admin_UI::card_open(
					[
						'title' => __( 'AI 摘要', 'wpmind' ),
						'sub'   => __( '主动提供面向 AI 的文章摘要描述。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-ai-summary', 'wpmind_ai_summary_enabled', $ai_summary_enabled, __( '启用 AI 摘要', 'wpmind' ), __( '输出为 meta 标签与 Schema abstract。', 'wpmind' ) );
				?>
				<div class="wenpai-field wenpai-field--row field"<?php echo $ai_summary_enabled ? '' : ' hidden'; ?>>
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-summary-fallback"><?php esc_html_e( '无摘要时回退', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<select id="wpmind-ai-summary-fallback" name="wpmind_ai_summary_fallback">
							<option value="excerpt" <?php selected( $ai_summary_fallback, 'excerpt' ); ?>><?php esc_html_e( '使用文章摘要', 'wpmind' ); ?></option>
							<option value="none" <?php selected( $ai_summary_fallback, 'none' ); ?>><?php esc_html_e( '不输出', 'wpmind' ); ?></option>
						</select>
					</div>
				</div>
				<?php Wenpai_Admin_UI::card_close(); ?>
			</div>
			<div class="wenpai-stack">
				<?php
				$wpmind_info_card(
					__( 'AI 内容分发', 'wpmind' ),
					[
						__( 'Markdown Feed 去除页面导航噪音。', 'wpmind' ),
						__( 'llms.txt 引导 AI 快速发现核心栏目。', 'wpmind' ),
						__( 'AI Sitemap 自动跳过 noai 标记文章。', 'wpmind' ),
					],
					$learn_more_url
				);
				?>
			</div>
		</div>
	</div>

	<!-- ========== 结构化数据 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'schema', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => 'Schema.org',
						'sub'   => __( '自动输出 Article、Breadcrumb 与 WebSite JSON-LD。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-schema-enabled', 'wpmind_schema_enabled', $schema_enabled, __( '启用 Schema.org', 'wpmind' ), __( '为文章与页面注入语义结构化数据。', 'wpmind' ) );
				?>
				<div class="wenpai-field wenpai-field--row field"<?php echo $schema_enabled ? '' : ' hidden'; ?>>
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-schema-mode"><?php esc_html_e( '兼容模式', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<select id="wpmind-schema-mode" name="wpmind_schema_mode">
							<option value="auto" <?php selected( $schema_mode, 'auto' ); ?>><?php esc_html_e( '自动（检测到 SEO 插件时不输出）', 'wpmind' ); ?></option>
							<option value="force" <?php selected( $schema_mode, 'force' ); ?>><?php esc_html_e( '强制输出', 'wpmind' ); ?></option>
						</select>
					</div>
				</div>
				<?php
				Wenpai_Admin_UI::card_close();

				if ( $schema_enabled ) :
					$preview_brand_name = get_option( 'wpmind_brand_name', '' ) ?: get_bloginfo( 'name' );
					$preview_brand_url  = get_option( 'wpmind_brand_url', '' ) ?: home_url( '/' );
					$current_user       = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
					$author_name        = ( $current_user && ! empty( $current_user->display_name ) ) ? $current_user->display_name : 'Author';
					$preview_json       = (string) wp_json_encode(
						[
							'@context'      => 'https://schema.org',
							'@type'         => 'BlogPosting',
							'headline'      => __( '示例文章标题', 'wpmind' ),
							'author'        => [
								'@type' => 'Person',
								'name'  => $author_name,
							],
							'datePublished' => function_exists( 'wp_date' ) ? wp_date( 'c' ) : gmdate( 'c' ),
							'publisher'     => [
								'@type' => $brand_org_type,
								'name'  => $preview_brand_name,
								'url'   => $preview_brand_url,
							],
							'inLanguage'    => function_exists( 'get_locale' ) ? get_locale() : 'zh_CN',
						],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
					);
					$preview_bc_json    = (string) wp_json_encode(
						[
							'@context'        => 'https://schema.org',
							'@type'           => 'BreadcrumbList',
							'itemListElement' => [
								[
									'@type'    => 'ListItem',
									'position' => 1,
									'name'     => get_bloginfo( 'name' ),
									'item'     => home_url( '/' ),
								],
								[
									'@type'    => 'ListItem',
									'position' => 2,
									'name'     => __( '示例文章标题', 'wpmind' ),
								],
							],
						],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
					);
					$preview_site_json  = (string) wp_json_encode(
						[
							'@context'        => 'https://schema.org',
							'@type'           => 'WebSite',
							'name'            => $preview_brand_name,
							'url'             => home_url( '/' ),
							'potentialAction' => [
								'@type'       => 'SearchAction',
								'target'      => home_url( '/?s={search_term_string}' ),
								'query-input' => 'required name=search_term_string',
							],
						],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
					);

					$schema_tabs = Wenpai_Admin_UI::view_tabs_html(
						[
							'group'      => 'wpmind-geo-schema',
							'aria_label' => __( 'JSON-LD 预览类型', 'wpmind' ),
							'items'      => [
								[
									'id'     => 'article',
									'label'  => 'Article',
									'active' => true,
								],
								[
									'id'    => 'breadcrumb',
									'label' => 'Breadcrumb',
								],
								[
									'id'    => 'website',
									'label' => 'WebSite',
								],
							],
						]
					);

					Wenpai_Admin_UI::card_open(
						[
							'title'             => __( 'JSON-LD 预览', 'wpmind' ),
							'tile'              => false,
							'head_control_html' => $schema_tabs,
						]
					);
					Wenpai_Admin_UI::view_panes(
						[
							'group' => 'wpmind-geo-schema',
							'panes' => [
								[
									'id'     => 'article',
									'active' => true,
									'html'   => wpmind_code_block_html(
										[
											'code' => $preview_json,
											'lang' => 'json',
										]
									),
								],
								[
									'id'   => 'breadcrumb',
									'html' => wpmind_code_block_html(
										[
											'code' => $preview_bc_json,
											'lang' => 'json',
										]
									),
								],
								[
									'id'   => 'website',
									'html' => wpmind_code_block_html(
										[
											'code' => $preview_site_json,
											'lang' => 'json',
										]
									),
								],
							],
						]
					);
					Wenpai_Admin_UI::card_close();
				endif;
				?>
			</div>
			<div class="wenpai-stack">
				<?php
				$wpmind_info_card(
					__( 'Schema.org 说明', 'wpmind' ),
					[
						__( '自动兼容 Yoast、Rank Math 与 Slim SEO。', 'wpmind' ),
						__( '中文字数与英文词数混合精确统计。', 'wpmind' ),
						__( '品牌实体开启后自动丰富 publisher。', 'wpmind' ),
					]
				);
				?>
			</div>
		</div>
	</div>

	<!-- ========== AI 索引 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'control', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( 'AI 索引指令', 'wpmind' ),
						'sub'   => __( '控制 AI 爬虫对站点内容的索引与训练权限。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-ai-indexing', 'wpmind_ai_indexing_enabled', $ai_indexing, __( '启用 AI 索引指令', 'wpmind' ), __( '输出 noai / nollm 标签与 HTTP 响应头。', 'wpmind' ) );
				?>
				<div<?php echo $ai_indexing ? '' : ' hidden'; ?>>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-ai-declaration"><?php esc_html_e( '默认内容声明', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<select id="wpmind-ai-declaration" name="wpmind_ai_default_declaration">
								<option value="original" <?php selected( $ai_declaration, 'original' ); ?>><?php esc_html_e( '原创内容 (original)', 'wpmind' ); ?></option>
								<option value="ai-assisted" <?php selected( $ai_declaration, 'ai-assisted' ); ?>><?php esc_html_e( 'AI 辅助创作 (ai-assisted)', 'wpmind' ); ?></option>
								<option value="ai-generated" <?php selected( $ai_declaration, 'ai-generated' ); ?>><?php esc_html_e( 'AI 生成内容 (ai-generated)', 'wpmind' ); ?></option>
							</select>
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '排除的内容类型', 'wpmind' ); ?></span></div>
						<div class="field-ctl">
							<ul class="wenpai-checkgroup__list">
								<?php
								$public_types = get_post_types( [ 'public' => true ], 'objects' );
								foreach ( $public_types as $type ) :
									?>
									<li><label class="chk">
										<input type="checkbox" name="wpmind_ai_excluded_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $ai_excluded_types, true ) ); ?>>
										<span class="wenpai-checkgroup__text"><?php echo esc_html( $type->labels->name ); ?> (<?php echo esc_html( $type->name ); ?>)</span>
									</label></li>
								<?php endforeach; ?>
							</ul>
							<p class="wenpai-field__desc"><?php esc_html_e( '勾选类型将输出 noai 与 nollm 指令。', 'wpmind' ); ?></p>
						</div>
					</div>
				</div>
				<?php Wenpai_Admin_UI::card_close(); ?>
			</div>
			<div class="wenpai-stack">
				<?php
				$wpmind_info_card(
					__( '索引权限说明', 'wpmind' ),
					[
						__( 'noai 禁止 AI 搜索引擎索引页面。', 'wpmind' ),
						__( 'nollm 声明内容不用于模型训练。', 'wpmind' ),
						__( '单篇文章可在编辑器侧边栏单独覆盖。', 'wpmind' ),
					],
					$learn_more_url
				);
				?>
			</div>
		</div>
	</div>

	<!-- ========== 爬虫管理 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'crawlers', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( 'robots.txt AI 管理', 'wpmind' ),
						'sub'   => __( '按爬虫注入 Allow 或 Disallow 规则，不改物理文件。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-robots-ai', 'wpmind_robots_ai_enabled', $robots_ai_enabled, __( '启用 robots.txt 管理', 'wpmind' ), __( '动态注入 AI 爬虫访问规则。', 'wpmind' ) );
				if ( $robots_ai_enabled ) :
					?>
					<div class="wenpai-table-wrap">
						<table class="tbl">
							<thead>
								<tr>
									<th><?php esc_html_e( '爬虫', 'wpmind' ); ?></th>
									<th><?php esc_html_e( '机构', 'wpmind' ); ?></th>
									<th><?php esc_html_e( '规则', 'wpmind' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( \WPMind\Modules\Geo\RobotsTxtManager::AI_CRAWLERS as $bot => $info ) : ?>
									<tr>
										<td>
											<div class="t"><span class="mono"><?php echo esc_html( $bot ); ?></span></div>
											<div class="d"><?php echo esc_html( $info['description'] ); ?></div>
										</td>
										<td><?php echo esc_html( $info['company'] ); ?></td>
										<td>
											<select name="wpmind_robots_ai_rules[<?php echo esc_attr( $bot ); ?>]" class="wpmind-num">
												<option value="" <?php selected( $robots_ai_rules[ $bot ] ?? '', '' ); ?>><?php esc_html_e( '不管理', 'wpmind' ); ?></option>
												<option value="allow" <?php selected( $robots_ai_rules[ $bot ] ?? '', 'allow' ); ?>>Allow</option>
												<option value="disallow" <?php selected( $robots_ai_rules[ $bot ] ?? '', 'disallow' ); ?>>Disallow</option>
											</select>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="wenpai-field__desc"><?php esc_html_e( '预览地址：', 'wpmind' ); ?> <?php echo wpmind_code_inline_html( [ 'code' => home_url( '/robots.txt' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php
				endif;
				Wenpai_Admin_UI::card_close();
				?>
			</div>
			<div class="wenpai-stack">
				<?php
				$wpmind_info_card(
					__( 'AI 爬虫规则', 'wpmind' ),
					[
						__( '覆盖国内外主流 20 余种 AI 爬虫。', 'wpmind' ),
						__( '通过 WordPress 过滤器注入，安全可逆。', 'wpmind' ),
					],
					$learn_more_url
				);
				?>
			</div>
		</div>
	</div>

	<!-- ========== 品牌实体 ========== -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'brand', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="wenpai-split">
			<div class="wenpai-stack">
				<?php
				Wenpai_Admin_UI::card_open(
					[
						'title' => __( '组织与品牌实体', 'wpmind' ),
						'sub'   => __( '增强 publisher Schema 并在首页输出 Organization JSON-LD。', 'wpmind' ),
						'tile'  => false,
					]
				);
				$wpmind_switch( 'wpmind-brand-entity', 'wpmind_brand_entity_enabled', $brand_entity_enabled, __( '启用品牌实体', 'wpmind' ), __( '输出品牌身份、社交主页与知识图谱链接。', 'wpmind' ) );
				?>
				<div<?php echo $brand_entity_enabled ? '' : ' hidden'; ?>>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-org-type"><?php esc_html_e( '组织类型', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<select id="wpmind-brand-org-type" name="wpmind_brand_org_type">
								<option value="Organization" <?php selected( $brand_org_type, 'Organization' ); ?>>Organization</option>
								<option value="Corporation" <?php selected( $brand_org_type, 'Corporation' ); ?>>Corporation</option>
								<option value="LocalBusiness" <?php selected( $brand_org_type, 'LocalBusiness' ); ?>>LocalBusiness</option>
								<option value="OnlineBusiness" <?php selected( $brand_org_type, 'OnlineBusiness' ); ?>>OnlineBusiness</option>
								<option value="NewsMediaOrganization" <?php selected( $brand_org_type, 'NewsMediaOrganization' ); ?>>NewsMediaOrganization</option>
							</select>
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-name"><?php esc_html_e( '品牌名称', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="text" id="wpmind-brand-name" name="wpmind_brand_name" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_name', '' ) ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>">
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-desc"><?php esc_html_e( '品牌描述', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<textarea id="wpmind-brand-desc" name="wpmind_brand_description" rows="3" placeholder="<?php esc_attr_e( '简要介绍品牌或组织', 'wpmind' ); ?>"><?php echo esc_textarea( (string) get_option( 'wpmind_brand_description', '' ) ); ?></textarea>
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-url"><?php esc_html_e( '品牌网址', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="url" id="wpmind-brand-url" name="wpmind_brand_url" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_url', '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-founding"><?php esc_html_e( '创立日期', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="text" class="wpmind-num" id="wpmind-brand-founding" name="wpmind_brand_founding_date" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_founding_date', '' ) ); ?>" placeholder="2020-01-01">
						</div>
					</div>

					<?php
					$social_fields = [
						'wpmind_brand_social_facebook' => 'Facebook',
						'wpmind_brand_social_twitter'  => 'X (Twitter)',
						'wpmind_brand_social_linkedin' => 'LinkedIn',
						'wpmind_brand_social_youtube'  => 'YouTube',
						'wpmind_brand_social_github'   => 'GitHub',
						'wpmind_brand_social_weibo'    => __( '微博', 'wpmind' ),
						'wpmind_brand_social_zhihu'    => __( '知乎', 'wpmind' ),
						'wpmind_brand_social_wechat'   => __( '微信公众号', 'wpmind' ),
					];
					foreach ( $social_fields as $field_name => $label ) :
						$field_id = 'wpmind-brand-' . sanitize_html_class( str_replace( 'wpmind_brand_social_', '', $field_name ) );
						?>
						<div class="wenpai-field wenpai-field--row field">
							<div class="field-label"><label class="wenpai-field__label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) get_option( $field_name, '' ) ); ?>" placeholder="https://">
							</div>
						</div>
					<?php endforeach; ?>

					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-contact-email"><?php esc_html_e( '联系邮箱', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="email" id="wpmind-brand-contact-email" name="wpmind_brand_contact_email" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_contact_email', '' ) ); ?>" placeholder="hello@example.com">
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-contact-phone"><?php esc_html_e( '联系电话', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="tel" id="wpmind-brand-contact-phone" name="wpmind_brand_contact_phone" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_contact_phone', '' ) ); ?>" placeholder="+86-xxx-xxxx-xxxx">
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-wikidata">Wikidata URL</label></div>
						<div class="field-ctl">
							<input type="url" id="wpmind-brand-wikidata" name="wpmind_brand_wikidata_url" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_wikidata_url', '' ) ); ?>" placeholder="https://www.wikidata.org/wiki/Q...">
						</div>
					</div>
					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-brand-wikipedia">Wikipedia URL</label></div>
						<div class="field-ctl">
							<input type="url" id="wpmind-brand-wikipedia" name="wpmind_brand_wikipedia_url" value="<?php echo esc_attr( (string) get_option( 'wpmind_brand_wikipedia_url', '' ) ); ?>" placeholder="https://en.wikipedia.org/wiki/...">
						</div>
					</div>
					<?php $wpmind_switch( 'wpmind-entity-linker', 'wpmind_entity_linker_enabled', $entity_linker_enabled, __( '文章实体关联', 'wpmind' ), __( '在编辑器关联 Wikidata 实体输出 about.sameAs。', 'wpmind' ) ); ?>
				</div>
				<?php Wenpai_Admin_UI::card_close(); ?>
			</div>
			<div class="wenpai-stack">
				<?php
				$wpmind_info_card(
					__( '品牌实体说明', 'wpmind' ),
					[
						__( '首页输出 Organization JSON-LD 身份声明。', 'wpmind' ),
						__( 'sameAs 关联社交档案强化可信度。', 'wpmind' ),
						__( 'Wikidata 链接协助 AI 消除语义歧义。', 'wpmind' ),
					],
					$learn_more_url
				);
				?>
			</div>
		</div>
	</div>

	<?php
	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::card_foot(
		[
			'start'   => true,
			'meta'    => __( '一次保存全部六个分区的 GEO 设置。', 'wpmind' ),
			'actions' => [
				[
					'label'   => __( '保存 GEO 设置', 'wpmind' ),
					'variant' => 'primary',
					'attrs'   => [ 'id' => 'wpmind-save-geo' ],
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>
	<?php
} )();
