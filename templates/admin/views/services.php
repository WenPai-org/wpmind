<?php
/**
 * AI 服务视图：官方算力卡 + 文本 Provider fold_list + 图像 Provider fold_list + savebar。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;

( static function (): void {
	$wpmind_instance  = \WPMind\wpmind();
	$endpoints        = $wpmind_instance->get_custom_endpoints();
	$default_provider = (string) get_option( 'wpmind_default_provider', '' );
	$mode             = class_exists( 'Wenpai_Admin_UI', false ) ? Wenpai_Admin_UI::mode() : 'simple';

	$image_endpoints = get_option( 'wpmind_image_endpoints', [] );
	if ( ! is_array( $image_endpoints ) ) {
		$image_endpoints = [];
	}
	$default_image_provider = (string) get_option( 'wpmind_default_image_provider', '' );

	$available_image_providers = [
		'openai_gpt_image'    => [
			'name'         => 'GPT Image',
			'display_name' => 'openai-image',
			'description'  => __( 'OpenAI 图像生成，文字渲染清晰。', 'wpmind' ),
			'base_url'     => 'https://api.openai.com/v1/',
			'models'       => [ 'gpt-image-1.5', 'dall-e-3' ],
		],
		'google_gemini_image' => [
			'name'         => 'Gemini Image',
			'display_name' => 'google-imagen',
			'description'  => __( 'Google 图像模型，复杂构图稳定。', 'wpmind' ),
			'base_url'     => 'https://generativelanguage.googleapis.com/v1beta/',
			'models'       => [ 'imagen-3.0-generate-001' ],
		],
		'tencent_hunyuan'     => [
			'name'         => __( '混元图像', 'wpmind' ),
			'display_name' => 'hunyuan-image',
			'description'  => __( '腾讯混元，中文语义理解出色。', 'wpmind' ),
			'base_url'     => 'https://hunyuan.tencentcloudapi.com/',
			'models'       => [ 'hunyuan-image-v3' ],
		],
		'bytedance_reve'      => [
			'name'         => 'Reve Image',
			'display_name' => 'bytedance-reve',
			'description'  => __( '指令遵循与排版细节表现均衡。', 'wpmind' ),
			'base_url'     => 'https://api.reve.com/v1/',
			'models'       => [ 'reve-image-1' ],
		],
		'bytedance_seedream'  => [
			'name'         => 'Seedream',
			'display_name' => 'seedream',
			'description'  => __( '火山引擎图像模型，中文场景友好。', 'wpmind' ),
			'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3/',
			'models'       => [ 'seedream-4.5' ],
		],
		'flux'                => [
			'name'         => 'Flux (Fal.ai)',
			'display_name' => 'flux',
			'description'  => __( '开源图像模型，细节质感丰富。', 'wpmind' ),
			'base_url'     => 'https://fal.run/fal-ai/',
			'models'       => [ 'flux/dev', 'flux/schnell', 'flux-pro-1.1' ],
		],
		'midjourney'          => [
			'name'         => 'Midjourney',
			'display_name' => 'midjourney',
			'description'  => __( '艺术风格与光影表现突出。', 'wpmind' ),
			'base_url'     => 'https://api.midjourney.com/v1/',
			'models'       => [ 'midjourney-v6' ],
		],
		'qwen_image'          => [
			'name'         => __( '通义万相', 'wpmind' ),
			'display_name' => 'wanx',
			'description'  => __( '阿里云通义万相，写实风格自然。', 'wpmind' ),
			'base_url'     => 'https://dashscope.aliyuncs.com/api/v1/',
			'models'       => [ 'wanx-v1', 'wanx-background-generation-v2' ],
		],
	];

	$wpmind_mark = static function ( string $pid ): string {
		$alias = [
			'openai_gpt_image'    => 'openai',
			'google_gemini_image' => 'google',
			'qwen_image'          => 'qwen',
			'bytedance_seedream'  => 'doubao',
			'bytedance_reve'      => 'doubao',
		];
		$slug  = $alias[ $pid ] ?? $pid;
		$svg   = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $slug . '.svg';
		if ( file_exists( $svg ) ) {
			return '<img src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $slug . '.svg' ) . '" alt="" width="20" height="20">';
		}
		return AdminIcons::svg( 'robot' );
	};

	$wpmind_state_labels = [
		'closed'          => __( '正常', 'wpmind' ),
		'open'            => __( '熔断中', 'wpmind' ),
		'half_open'       => __( '恢复探测', 'wpmind' ),
		'needs_attention' => __( '需要处理', 'wpmind' ),
		'quota_exhausted' => __( '额度不足', 'wpmind' ),
	];

	$wpmind_mode_switch = Wenpai_Admin_UI::mode_switch_html(
		[
			'wrap'  => false,
			'label' => __( '显示模式', 'wpmind' ),
			'items' => [
				[
					'label'   => __( '简单', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'  => 'services',
							'mode' => 'simple',
						]
					),
					'current' => 'simple' === $mode,
				],
				[
					'label'   => __( '高级', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'  => 'services',
							'mode' => 'advanced',
						]
					),
					'current' => 'advanced' === $mode,
				],
			],
		]
	);

	wpmind_page_head(
		__( 'AI 服务', 'wpmind' ),
		__( '配置文本与图像 Provider。简单模式只留启用、Key 与测试；高级模式展开全部配置。', 'wpmind' ),
		$wpmind_mode_switch
	);

	Wenpai_Admin_UI::subnav(
		[
			'label' => __( 'AI 服务子导航', 'wpmind' ),
			'items' => [
				[
					'label'   => __( '心思 AI', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'cloud' ] ),
					'current' => false,
				],
				[
					'label'   => __( 'Provider 服务', 'wpmind' ),
					'href'    => AdminPage::url(
						[
							'tab'  => 'services',
							'mode' => $mode,
						]
					),
					'current' => true,
				],
				[
					'label'   => __( '智能路由', 'wpmind' ),
					'href'    => AdminPage::url( [ 'tab' => 'routing' ] ),
					'current' => false,
				],
			],
		]
	);
	?>

<div class="wenpai-stack" data-wenpai-mode-scope="<?php echo esc_attr( $mode ); ?>">
	<form method="post" action="options.php" id="wpmind-settings-form">
		<?php settings_fields( 'wpmind_settings' ); ?>

		<div class="wenpai-stack">
			<?php
			// ---- 文本服务 Provider 卡（渐进式披露：已配置置顶，未配置默认折叠收纳） ----
			$byok_endpoints = array_filter(
				$endpoints,
				static fn( $ep, $k ) => 'wenpai_cloud' !== (string) $k && empty( $ep['is_cloud'] ),
				ARRAY_FILTER_USE_BOTH
			);

			$render_unconfigured_drawer = static function ( string $drawer_id, string $title, array $items, string $icon_key ): void {
				if ( empty( $items ) ) {
					return;
				}
				$names   = array_map( static fn( array $it ): string => (string) ( $it['title'] ?? '' ), $items );
				$preview = implode( '、', array_slice( $names, 0, 4 ) );
				if ( count( $names ) > 4 ) {
					$preview .= sprintf(
						/* translators: %d: remaining provider count */
						__( ' 等 %d 个服务', 'wpmind' ),
						count( $names )
					);
				}
				$chevron = class_exists( 'Wenpai_Admin_Icons', false ) && \Wenpai_Admin_Icons::has( 'chevron-down' )
					? \Wenpai_Admin_Icons::svg( 'chevron-down', 'wenpai-ico wenpai-fold__chevron' )
					: '';
				$pill    = Wenpai_Admin_UI::pill_html(
					[
						'text'  => sprintf(
							/* translators: %d: unconfigured provider count */
							__( '%d 个未配置', 'wpmind' ),
							count( $items )
						),
						'state' => 'mute',
					]
				);
				$tile    = Wenpai_Admin_UI::tile_html( [ 'icon_html' => AdminIcons::svg( $icon_key ) ] );
				?>
				<div class="wenpai-folds">
					<details class="wenpai-fold" id="<?php echo esc_attr( $drawer_id ); ?>">
						<summary>
							<?php echo $tile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped. ?>
							<span class="wenpai-fold__text">
								<span class="wenpai-fold__title"><?php echo esc_html( $title ); ?></span>
								<span class="wenpai-fold__desc"><?php echo esc_html( $preview ); ?></span>
							</span>
							<span class="wenpai-fold__side"><?php echo $pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped. ?></span>
							<?php echo $chevron; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit SVG. ?>
						</summary>
						<div class="wenpai-fold__body">
							<?php Wenpai_Admin_UI::fold_list( [ 'items' => $items ] ); ?>
						</div>
					</details>
				</div>
				<?php
			};

			$configured_text_items   = [];
			$unconfigured_text_items = [];
	foreach ( $byok_endpoints as $key => $endpoint ) {
		$key         = (string) $key;
		$has_api_key = $wpmind_instance->has_api_key( $key );
		$enabled     = ! empty( $endpoint['enabled'] );
		$is_locked   = SuiteIntegration::is_provider_locked( $key );
		$is_default  = $default_provider === $key;

		$cb_state   = '';
		$cb_details = [];
		if ( class_exists( '\WPMind\Failover\FailoverManager' ) ) {
			$breaker = \WPMind\Failover\FailoverManager::instance()->get_circuit_breaker( $key );
			if ( $breaker ) {
				$cb_state   = $breaker->get_state();
				$cb_details = $breaker->get_status_details();
			}
		}

		$needs_attention = ! empty( $endpoint['needs_reconfigure'] ) || ( '' !== $cb_state && 'closed' !== $cb_state );
		$is_configured   = $enabled || $has_api_key || $is_locked || $needs_attention;
		// 仅在有异常需处理时默认展开，正常配置项保持单行折叠摘要，点按展开.
		$open = $needs_attention;

		$key_meta = is_array( $endpoint['key_meta'] ?? null ) ? $endpoint['key_meta'] : [];
		if ( $has_api_key && ! $is_locked && empty( $key_meta['fingerprint'] ) && class_exists( '\WPMind\Provider\KeyRotation' ) ) {
			$key_meta = \WPMind\Provider\KeyRotation::ensure_key_meta( $key );
		}
		$fp     = is_string( $key_meta['fingerprint'] ?? null ) ? (string) $key_meta['fingerprint'] : '';
		$set_at = isset( $key_meta['set_at'] ) ? (int) $key_meta['set_at'] : 0;

		$recovery  = '';
		$err_time  = '';
		$err_label = '';
		$reason    = is_string( $cb_details['reason'] ?? null ) ? (string) $cb_details['reason'] : '';
		$since     = isset( $cb_details['since'] ) ? (int) $cb_details['since'] : ( isset( $cb_details['last_failure'] ) ? (int) $cb_details['last_failure'] : 0 );
		if ( '' !== $reason && class_exists( '\WPMind\Provider\Health\ErrorClass' ) && class_exists( '\WPMind\Provider\Health\ErrorCatalog' ) ) {
			try {
				$ec        = \WPMind\Provider\Health\ErrorClass::from( $reason );
				$cat       = \WPMind\Provider\Health\ErrorCatalog::entry( $ec, $key );
				$err_label = $cat['label'];
				$recovery  = $cat['recovery'];
				if ( '' !== $cat['credentials_url'] ) {
					$recovery .= ' ' . $cat['credentials_url'];
				}
			} catch ( \ValueError $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				$err_label = $reason;
			}
		}
		if ( $since > 0 && class_exists( '\WPMind\Provider\Diagnostics' ) ) {
			$err_time = \WPMind\Provider\Diagnostics::format_shanghai( $since );
		}

		// 折叠行摘要胶囊.
		if ( $is_locked ) {
			$fold_pill = [
				'text'  => __( '已由站点配置锁定', 'wpmind' ),
				'state' => 'ok',
			];
		} elseif ( '' !== $cb_state && 'closed' !== $cb_state ) {
			$fold_pill = [
				'text'  => $wpmind_state_labels[ $cb_state ] ?? $cb_state,
				'state' => 'warn',
			];
		} elseif ( ! empty( $endpoint['needs_reconfigure'] ) ) {
			$fold_pill = [
				'text'  => __( '需要重新配置', 'wpmind' ),
				'state' => 'warn',
			];
		} elseif ( $enabled && $has_api_key ) {
			$fold_pill = [
				'text'  => $is_default ? __( '已开启 · 默认', 'wpmind' ) : __( '已开启', 'wpmind' ),
				'state' => 'ok',
			];
		} elseif ( $has_api_key ) {
			$fold_pill = [
				'text'  => __( 'Key 已配置', 'wpmind' ),
				'state' => 'mute',
			];
		} else {
			$fold_pill = [
				'text'  => __( '未开启', 'wpmind' ),
				'state' => 'mute',
			];
		}

		ob_start();
		?>
				<div class="wpmind-endpoint-card" data-provider="<?php echo esc_attr( $key ); ?>">
					<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="wpmind-sw-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '启用', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
					<?php
					$wpmind_sw = Wenpai_Admin_Settings::switch_html(
						[
							'id'      => 'wpmind-sw-' . $key,
							'name'    => 'wpmind_custom_endpoints[' . $key . '][enabled]',
							'checked' => $enabled,
							'on'      => __( '已开启', 'wpmind' ),
							'off'     => __( '未开启', 'wpmind' ),
						]
					);
					echo $wpmind_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
						</div>
					</div>

					<div class="wenpai-field wenpai-field--row field">
						<div class="field-label"><label class="wenpai-field__label" for="api_key_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'API Key', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<?php if ( $is_locked ) : ?>
								<div class="wenpai-row wenpai-row--tight">
									<?php
									Wenpai_Admin_UI::pill(
										[
											'text'  => __( '已由站点配置锁定', 'wpmind' ),
											'state' => 'ok',
										]
									);
									?>
									<span class="meta"><?php esc_html_e( '密钥由站点配置文件托管，后台不可修改。', 'wpmind' ); ?></span>
								</div>
								<div class="wenpai-actions">
									<button type="button" class="btn btn-sm btn-secondary wpmind-test-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试连接', 'wpmind' ); ?></button>
									<button type="button" class="btn btn-sm btn-ghost wpmind-reset-breaker" data-provider="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '重置状态', 'wpmind' ); ?></button>
									<span class="wpmind-key__result" aria-live="polite"></span>
								</div>
							<?php else : ?>
								<div class="wpmind-key">
									<input type="password" id="api_key_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][api_key]" value="" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
									<button type="button" class="btn btn-sm btn-ghost wpmind-toggle-key" data-target="api_key_<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
										<span class="wpmind-key__eye-on"><?php echo AdminIcons::svg( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="wpmind-key__eye-off" hidden><?php echo AdminIcons::svg( 'eye-off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</button>
								</div>
								<?php if ( $has_api_key && '' !== $fp ) : ?>
									<p class="wenpai-field__desc wpmind-key-meta" data-provider="<?php echo esc_attr( $key ); ?>">
										<?php
										if ( $set_at > 0 ) {
											printf(
												/* translators: 1: key fingerprint, 2: datetime */
												esc_html__( '指纹 %1$s · %2$s 设置，留空表示不修改已保存的值。', 'wpmind' ),
												esc_html( $fp ),
												esc_html( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $set_at ) : gmdate( 'Y-m-d H:i', $set_at ) )
											);
										} else {
											printf(
												/* translators: %s: key fingerprint */
												esc_html__( '指纹 %s，留空表示不修改已保存的值。', 'wpmind' ),
												esc_html( $fp )
											);
										}
										?>
									</p>
								<?php else : ?>
									<p class="wenpai-field__desc"><?php esc_html_e( '填写密钥后点击底部保存。', 'wpmind' ); ?></p>
								<?php endif; ?>
								<?php if ( $has_api_key ) : ?>
									<div class="chk"><label><input type="checkbox" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]" value="1" class="wpmind-clear-checkbox"> <?php esc_html_e( '清除已保存的 API Key', 'wpmind' ); ?></label></div>
								<?php endif; ?>
								<div class="wenpai-actions">
									<button type="button" class="btn btn-sm btn-secondary wpmind-test-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试连接', 'wpmind' ); ?></button>
									<button type="button" class="btn btn-sm btn-ghost wpmind-rotate-key" data-provider="<?php echo esc_attr( $key ); ?>"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '验证并替换', 'wpmind' ); ?></button>
									<button type="button" class="btn btn-sm btn-ghost wpmind-reset-breaker" data-provider="<?php echo esc_attr( $key ); ?>"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( '重置状态', 'wpmind' ); ?></button>
									<span class="chk"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><label><input type="checkbox" class="wpmind-deep-test" value="1"> <?php esc_html_e( '深度测试', 'wpmind' ); ?></label></span>
									<span class="wpmind-key__result" aria-live="polite"></span>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( '' !== $err_label || '' !== $recovery ) : ?>
						<div class="wenpai-field wenpai-field--row field">
							<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '最近错误', 'wpmind' ); ?></span></div>
							<div class="field-ctl">
								<?php
								Wenpai_Admin_UI::notice(
									[
										'level' => 'bad',
										'wrap'  => false,
										'text'  => trim( $err_label . ( '' !== $err_time ? ' · ' . $err_time : '' ) . ( '' !== $recovery ? ' — ' . $recovery : '' ) ),
									]
								);
								?>
							</div>
						</div>
					<?php endif; ?>

					<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="field-label"><label class="wenpai-field__label" for="models_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="text" id="models_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][models]" value="<?php echo esc_attr( implode( ', ', (array) $endpoint['models'] ) ); ?>" placeholder="<?php esc_attr_e( '留空使用目录推荐模型', 'wpmind' ); ?>">
							<p class="wenpai-field__desc"><?php esc_html_e( '多个模型以英文逗号分隔，留空用默认列表。', 'wpmind' ); ?></p>
						</div>
					</div>

					<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="field-label"><label class="wenpai-field__label" for="default_model_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '默认模型', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="text" id="default_model_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][default_model]" value="<?php echo esc_attr( (string) ( $endpoint['default_model'] ?? '' ) ); ?>" placeholder="auto">
							<div class="wenpai-actions">
								<button type="button" class="btn btn-sm btn-ghost wpmind-fetch-models" data-provider="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '拉取远程模型', 'wpmind' ); ?></button>
								<span class="meta wpmind-fetch-models-hint"><?php esc_html_e( '从接口读取可用对话模型并追加。', 'wpmind' ); ?></span>
							</div>
						</div>
					</div>

					<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="field-label"><label class="wenpai-field__label" for="custom_base_url_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '自定义接口地址', 'wpmind' ); ?></label></div>
						<div class="field-ctl">
							<input type="url" id="custom_base_url_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]" value="<?php echo esc_attr( (string) ( $endpoint['custom_base_url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $endpoint['base_url'] ?? '' ) ); ?>">
							<p class="wenpai-field__desc"><?php esc_html_e( '留空使用官方默认接口地址。', 'wpmind' ); ?></p>
						</div>
					</div>
				</div>
				<?php
				$body_html = (string) ob_get_clean();

				$fold_entry = [
					'id'        => 'endpoint-' . $key,
					'logo_html' => $wpmind_mark( $key ),
					'title'     => (string) $endpoint['name'],
					'desc'      => (string) ( $endpoint['display_name'] ?? $key ),
					'pill'      => $fold_pill,
					'open'      => $open,
					'body_html' => $body_html,
				];
				if ( $is_configured ) {
					$configured_text_items[] = $fold_entry;
				} else {
					$unconfigured_text_items[] = $fold_entry;
				}
	}

			Wenpai_Admin_UI::card_open(
				[
					'title'             => __( '文本服务', 'wpmind' ),
					'sub'               => sprintf(
						/* translators: 1: configured count, 2: total endpoint count, 3: default provider name */
						__( '已配置 %1$d / %2$d 个端点 · 默认：%3$s', 'wpmind' ),
						count( $configured_text_items ),
						count( $byok_endpoints ),
						'' !== $default_provider && isset( $endpoints[ $default_provider ] )
							? (string) $endpoints[ $default_provider ]['name']
							: __( '自动按路由选择', 'wpmind' )
					),
					'tile'              => false,
					'head_actions_html' => Wenpai_Admin_UI::pill_html(
						[
							'text'  => __( 'Key 加密保存', 'wpmind' ),
							'state' => 'mute',
						]
					),
				]
			);
	if ( ! empty( $configured_text_items ) ) {
		Wenpai_Admin_UI::fold_list( [ 'items' => $configured_text_items ] );
	}
			$render_unconfigured_drawer(
				'wpmind-unconfigured-text',
				empty( $configured_text_items )
					? __( '展开配置文本服务商', 'wpmind' )
					: __( '更多未配置的文本服务', 'wpmind' ),
				$unconfigured_text_items,
				'services'
			);

			Wenpai_Admin_UI::card_foot(
				[
					'start'   => true,
					'meta'    => __( '密钥加密保存；未配置的服务默认折叠，按需展开填入 Key 后保存。', 'wpmind' ),
					'actions' => [
						[
							'label'   => __( '保存服务设置', 'wpmind' ),
							'type'    => 'submit',
							'name'    => 'submit',
							'variant' => 'primary',
						],
					],
				]
			);
			Wenpai_Admin_UI::card_close();
	?>
		</div>
	</form>

	<?php
	// ---- 图像服务 Provider 卡（渐进式披露：已配置置顶，未配置默认折叠收纳） ----
	$configured_image_items   = [];
	$unconfigured_image_items = [];
	foreach ( $available_image_providers as $key => $provider ) {
		$saved_config  = isset( $image_endpoints[ $key ] ) && is_array( $image_endpoints[ $key ] ) ? $image_endpoints[ $key ] : [];
		$is_enabled    = ! empty( $saved_config['enabled'] );
		$has_api_key   = ! empty( $saved_config['api_key'] );
		$is_default    = $default_image_provider === $key;
		$is_configured = $is_enabled || $has_api_key;

		$img_pill = ( $is_enabled && $has_api_key )
			? [
				'text'  => $is_default ? __( '已开启 · 默认', 'wpmind' ) : __( '已开启', 'wpmind' ),
				'state' => 'ok',
			]
			: ( $has_api_key
				? [
					'text'  => __( 'Key 已配置', 'wpmind' ),
					'state' => 'mute',
				]
				: [
					'text'  => __( '未配置', 'wpmind' ),
					'state' => 'mute',
				] );

		ob_start();
		?>
		<div class="wpmind-endpoint-card" data-provider="<?php echo esc_attr( $key ); ?>">
			<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">
				<div class="field-label"><label class="wenpai-field__label" for="wpmind-img-sw-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '启用', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<?php
					$wpmind_sw = Wenpai_Admin_Settings::switch_html(
						[
							'id'      => 'wpmind-img-sw-' . $key,
							'name'    => 'wpmind_image_endpoints[' . $key . '][enabled]',
							'checked' => $is_enabled,
							'on'      => __( '已开启', 'wpmind' ),
							'off'     => __( '未开启', 'wpmind' ),
						]
					);
					echo $wpmind_sw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<p class="wenpai-field__desc"><?php echo esc_html( (string) $provider['description'] ); ?></p>
				</div>
			</div>

			<div class="wenpai-field wenpai-field--row field">
				<div class="field-label"><label class="wenpai-field__label" for="image_api_key_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'API Key', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<div class="wpmind-key">
						<input type="password" id="image_api_key_<?php echo esc_attr( $key ); ?>" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][api_key]" value="" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
						<button type="button" class="btn btn-sm btn-ghost wpmind-toggle-key" data-target="image_api_key_<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
							<span class="wpmind-key__eye-on"><?php echo AdminIcons::svg( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="wpmind-key__eye-off" hidden><?php echo AdminIcons::svg( 'eye-off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</button>
					</div>
					<?php if ( $has_api_key ) : ?>
						<p class="wenpai-field__desc"><?php esc_html_e( '留空表示不修改已保存的值。', 'wpmind' ); ?></p>
						<div class="chk"><label><input type="checkbox" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]" value="1" class="wpmind-clear-checkbox"> <?php esc_html_e( '清除已保存的 API Key', 'wpmind' ); ?></label></div>
					<?php endif; ?>
					<div class="wenpai-actions">
						<button type="button" class="btn btn-sm btn-secondary wpmind-test-image-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $is_enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试连接', 'wpmind' ); ?></button>
						<span class="wpmind-key__result" aria-live="polite"></span>
					</div>
				</div>
			</div>

			<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '支持模型', 'wpmind' ); ?></span></div>
				<div class="field-ctl">
					<div class="wenpai-row wenpai-row--tight">
						<?php foreach ( (array) $provider['models'] as $img_model ) : ?>
							<?php echo wpmind_code_inline_html( [ 'code' => (string) $img_model ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="wenpai-field wenpai-field--row field"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="field-label"><label class="wenpai-field__label" for="image_base_url_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '自定义接口地址', 'wpmind' ); ?></label></div>
				<div class="field-ctl">
					<input type="url" id="image_base_url_<?php echo esc_attr( $key ); ?>" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]" value="<?php echo esc_attr( (string) ( $saved_config['custom_base_url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $provider['base_url'] ); ?>">
				</div>
			</div>
		</div>
		<?php
		$img_body  = (string) ob_get_clean();
		$img_entry = [
			'id'        => 'image-endpoint-' . $key,
			'logo_html' => $wpmind_mark( $key ),
			'title'     => (string) $provider['name'],
			'desc'      => (string) $provider['display_name'],
			'pill'      => $img_pill,
			'open'      => false,
			'body_html' => $img_body,
		];
		if ( $is_configured ) {
			$configured_image_items[] = $img_entry;
		} else {
			$unconfigured_image_items[] = $img_entry;
		}
	}
	?>
	<form method="post" action="options.php" id="wpmind-image-settings-form">
		<?php settings_fields( 'wpmind_image_settings' ); ?>

		<?php
		$cloud_image_ready = class_exists( '\WPMind\Provider\WenpaiCloud' ) && \WPMind\Provider\WenpaiCloud::is_available();
		$default_img_label = __( '未设置', 'wpmind' );
		if ( 'wenpai_cloud' === $default_image_provider ) {
			$default_img_label = __( '文派心思官方算力', 'wpmind' );
		} elseif ( '' !== $default_image_provider && isset( $available_image_providers[ $default_image_provider ] ) ) {
			$default_img_label = (string) $available_image_providers[ $default_image_provider ]['name'];
		} elseif ( $cloud_image_ready ) {
			$default_img_label = __( '文派心思官方算力（自动）', 'wpmind' );
		}
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '图像服务', 'wpmind' ),
				'sub'               => sprintf(
					/* translators: 1: configured image count, 2: total provider count, 3: default name */
					__( '已配置 %1$d / %2$d 个图像端点 · 默认：%3$s', 'wpmind' ),
					count( $configured_image_items ),
					count( $available_image_providers ),
					$default_img_label
				),
				'tile'              => false,
				'head_actions_html' => Wenpai_Admin_UI::pill_html(
					[
						'text'  => __( 'Key 加密保存', 'wpmind' ),
						'state' => 'mute',
					]
				),
			]
		);
		?>
		<div class="wenpai-field wenpai-field--row field"<?php echo ( empty( $configured_image_items ) && ! $cloud_image_ready ) ? Wenpai_Admin_UI::mode_attrs( 'advanced' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_default_image_provider"><?php esc_html_e( '默认图像服务', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<select id="wpmind_default_image_provider" name="wpmind_default_image_provider">
					<option value=""><?php esc_html_e( '— 自动按路由选择 —', 'wpmind' ); ?></option>
					<?php if ( $cloud_image_ready || 'wenpai_cloud' === $default_image_provider ) : ?>
						<option value="wenpai_cloud" <?php selected( $default_image_provider, 'wenpai_cloud' ); ?>><?php esc_html_e( '文派心思官方算力', 'wpmind' ); ?></option>
					<?php endif; ?>
					<?php
					foreach ( $available_image_providers as $key => $provider ) :
						$saved_img = isset( $image_endpoints[ $key ] ) && is_array( $image_endpoints[ $key ] ) ? $image_endpoints[ $key ] : [];
						$img_ready = ! empty( $saved_img['enabled'] ) && ! empty( $saved_img['api_key'] );
						if ( ! $img_ready && $default_image_provider !== $key ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_image_provider, $key ); ?>><?php echo esc_html( (string) $provider['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<?php
		if ( ! empty( $configured_image_items ) ) {
			Wenpai_Admin_UI::fold_list( [ 'items' => $configured_image_items ] );
		}
		$render_unconfigured_drawer(
			'wpmind-unconfigured-image',
			empty( $configured_image_items )
				? __( '展开配置图像生成服务', 'wpmind' )
				: __( '更多未配置的图像服务', 'wpmind' ),
			$unconfigured_image_items,
			'image'
		);

		Wenpai_Admin_UI::card_foot(
			[
				'start'   => true,
				'meta'    => __( '图像密钥独立加密保存；未配置的图像服务默认折叠，按需展开配置。', 'wpmind' ),
				'actions' => [
					[
						'label'   => __( '保存图像设置', 'wpmind' ),
						'type'    => 'submit',
						'name'    => 'submit',
						'variant' => 'secondary',
					],
				],
			]
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	Wenpai_Admin_UI::savebar(
		[
			'form'         => 'wpmind-settings-form',
			'message'      => __( '有未保存的服务配置更改', 'wpmind' ),
			'submit_label' => __( '保存服务设置', 'wpmind' ),
		]
	);
	?>
</div>
	<?php
} )();
