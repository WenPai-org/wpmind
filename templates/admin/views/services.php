<?php
/**
 * AI 服务视图：文本 Provider + 图像 Provider（kit 化）。
 * 保留全部 input name / id / JS 契约类（admin-endpoints.js）。
 *
 * @package WPMind
 */

defined( 'ABSPATH' ) || exit();

use WPMind\Admin\AdminIcons;
use WPMind\Admin\AdminPage;
use WPMind\Modules\CostControl\UsageTracker;

$wpmind_instance  = \WPMind\wpmind();
$endpoints        = $wpmind_instance->get_custom_endpoints();
$default_provider = get_option( 'wpmind_default_provider', '' );
$request_timeout  = get_option( 'wpmind_request_timeout', 60 );

$image_endpoints        = get_option( 'wpmind_image_endpoints', [] );
$default_image_provider = get_option( 'wpmind_default_image_provider', '' );

$available_image_providers = [
	'openai_gpt_image'    => [
		'name'         => 'DALL-E 3 / GPT-Image',
		'display_name' => 'openai-image',
		'description'  => __( 'OpenAI 图像生成，综合最均衡、文字渲染极强', 'wpmind' ),
		'base_url'     => 'https://api.openai.com/v1/',
		'models'       => [ 'dall-e-3', 'gpt-image-1.5' ],
	],
	'google_gemini_image' => [
		'name'         => 'Imagen 3',
		'display_name' => 'google-imagen',
		'description'  => __( 'Google Imagen 图像生成，文字最稳、复杂构图极强', 'wpmind' ),
		'base_url'     => 'https://generativelanguage.googleapis.com/v1beta/',
		'models'       => [ 'imagen-3.0-generate-001' ],
	],
	'tencent_hunyuan'     => [
		'name'         => '混元图像',
		'display_name' => 'hunyuan-image',
		'description'  => __( '腾讯混元，中文理解 & 文字渲染顶级（需 SDK 签名）', 'wpmind' ),
		'base_url'     => 'https://hunyuan.tencentcloudapi.com/',
		'models'       => [ 'hunyuan-image-v3' ],
	],
	'bytedance_doubao'    => [
		'name'         => 'Doubao Image / Seedream',
		'display_name' => 'doubao-image',
		'description'  => __( '火山引擎图像生成，性价比高、中文文化理解强', 'wpmind' ),
		'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3/',
		'models'       => [ 'doubao-image-v1', 'seedream-4.5' ],
	],
	'flux'                => [
		'name'         => 'Flux (Fal.ai)',
		'display_name' => 'flux',
		'description'  => __( 'Black Forest Labs 开源天花板，手部/文字/遵循最强', 'wpmind' ),
		'base_url'     => 'https://fal.run/fal-ai/',
		'models'       => [ 'flux/dev', 'flux/schnell', 'flux-pro-1.1' ],
	],
	'qwen_image'          => [
		'name'         => '通义万相',
		'display_name' => 'wanx',
		'description'  => __( '阿里云通义万相，真实感极强，几乎无AI味', 'wpmind' ),
		'base_url'     => 'https://dashscope.aliyuncs.com/api/v1/',
		'models'       => [ 'wanx-v1', 'wanx-background-generation-v2' ],
	],
];

// Provider 本地图标。
$wpmind_mark = static function ( string $pid ): string {
	$svg = WPMIND_PLUGIN_DIR . 'assets/images/providers/' . $pid . '.svg';
	if ( file_exists( $svg ) ) {
		return '<img src="' . esc_url( WPMIND_PLUGIN_URL . 'assets/images/providers/' . $pid . '.svg' ) . '" alt="" width="18" height="18">';
	}
	return AdminIcons::svg( 'robot' );
};

$wpmind_img_mark = [
	'openai_gpt_image'    => 'services',
	'google_gemini_image' => 'globe',
	'tencent_hunyuan'     => 'image',
	'bytedance_doubao'    => 'zap',
	'flux'                => 'robot',
	'qwen_image'          => 'image',
];

$wpmind_state_labels = [
	'closed'          => __( '正常', 'wpmind' ),
	'open'            => __( '熔断中', 'wpmind' ),
	'half_open'       => __( '恢复测试', 'wpmind' ),
	'needs_attention' => __( '需要处理', 'wpmind' ),
	'quota_exhausted' => __( '额度不足', 'wpmind' ),
];

// page_head aside：简单/高级模式 seg。
$wpmind_mode_seg = '<div class="seg wpmind-mode" role="radiogroup" aria-label="' . esc_attr__( '显示模式', 'wpmind' ) . '">'
	. '<a href="#" class="on" data-mode="simple">' . esc_html__( '简单', 'wpmind' ) . '</a>'
	. '<a href="#" data-mode="advanced">' . esc_html__( '高级', 'wpmind' ) . '</a>'
	. '</div>';

wpmind_page_head(
	__( 'AI 服务', 'wpmind' ),
	__( '配置文本与图像 Provider。简单模式只留启用、Key 与测试；高级模式展开全部配置。', 'wpmind' ),
	$wpmind_mode_seg
);
?>

<div class="wenpai-subnav">
	<div class="seg">
		<a class="on" href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'services' ] ) ); ?>"><?php esc_html_e( 'Provider 服务', 'wpmind' ); ?></a>
		<a href="<?php echo esc_url( AdminPage::url( [ 'tab' => 'routing' ] ) ); ?>"><?php esc_html_e( '智能路由', 'wpmind' ); ?></a>
	</div>
</div>

<div class="wenpai-stack">

<form method="post" action="options.php" id="wpmind-settings-form">
	<?php settings_fields( 'wpmind_settings' ); ?>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '文本服务', 'wpmind' ); ?></h2>
				<p class="card-sub">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: endpoint count, 2: default provider name */
							__( '%1$d 个端点 · 默认：%2$s', 'wpmind' ),
							count( $endpoints ),
							'' !== $default_provider && isset( $endpoints[ $default_provider ] )
								? (string) $endpoints[ $default_provider ]['name']
								: __( '未设置', 'wpmind' )
						)
					);
					?>
				</p>
			</div>
			<span class="pill mute"><?php esc_html_e( 'Key 加密保存', 'wpmind' ); ?></span>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_request_timeout"><?php esc_html_e( '请求超时', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<input type="number" id="wpmind_request_timeout" name="wpmind_request_timeout" value="<?php echo esc_attr( $request_timeout ); ?>" min="10" max="300" step="1" style="max-width:120px">
				<p class="wenpai-field__desc"><?php esc_html_e( '秒（建议 60-120），超时自动故障转移。', 'wpmind' ); ?></p>
			</div>
		</div>
		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_default_provider"><?php esc_html_e( '默认服务', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<select id="wpmind_default_provider" name="wpmind_default_provider" style="max-width:280px">
					<option value=""><?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?></option>
					<?php foreach ( $endpoints as $key => $endpoint ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_provider, $key ); ?>><?php echo esc_html( $endpoint['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="wenpai-field__desc"><?php esc_html_e( '未命中路由策略排序时的兜底 Provider。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-providers">
		<?php foreach ( $endpoints as $key => $endpoint ) : ?>
			<?php
			$has_api_key = $wpmind_instance->has_api_key( $key );
			$enabled     = ! empty( $endpoint['enabled'] );
			$open        = $enabled && $has_api_key;
			$is_default  = (string) $default_provider === (string) $key;

			$wpmind_cb_state   = '';
			$wpmind_cb_details = [];
			if ( class_exists( '\WPMind\Failover\FailoverManager' ) ) {
				$wpmind_breaker = \WPMind\Failover\FailoverManager::instance()->get_circuit_breaker( $key );
				if ( $wpmind_breaker ) {
					$wpmind_cb_state   = $wpmind_breaker->get_state();
					$wpmind_cb_details = $wpmind_breaker->get_status_details();
				}
			}

			$wpmind_key_meta = is_array( $endpoint['key_meta'] ?? null ) ? $endpoint['key_meta'] : [];
			if ( $has_api_key && empty( $wpmind_key_meta['fingerprint'] ) && class_exists( '\WPMind\Provider\KeyRotation' ) ) {
				$wpmind_key_meta = \WPMind\Provider\KeyRotation::ensure_key_meta( $key );
			}
			$wpmind_fp     = is_string( $wpmind_key_meta['fingerprint'] ?? null ) ? (string) $wpmind_key_meta['fingerprint'] : '';
			$wpmind_set_at = isset( $wpmind_key_meta['set_at'] ) ? (int) $wpmind_key_meta['set_at'] : 0;

			$wpmind_recovery = '';
			$wpmind_err_time = '';
			$wpmind_err_label = '';
			$wpmind_reason = is_string( $wpmind_cb_details['reason'] ?? null ) ? (string) $wpmind_cb_details['reason'] : '';
			$wpmind_since  = isset( $wpmind_cb_details['since'] ) ? (int) $wpmind_cb_details['since'] : ( isset( $wpmind_cb_details['last_failure'] ) ? (int) $wpmind_cb_details['last_failure'] : 0 );
			if ( '' !== $wpmind_reason && class_exists( '\WPMind\Provider\Health\ErrorClass' ) && class_exists( '\WPMind\Provider\Health\ErrorCatalog' ) ) {
				try {
					$wpmind_ec  = \WPMind\Provider\Health\ErrorClass::from( $wpmind_reason );
					$wpmind_cat = \WPMind\Provider\Health\ErrorCatalog::entry( $wpmind_ec, $key );
					$wpmind_err_label = $wpmind_cat['label'];
					$wpmind_recovery  = $wpmind_cat['recovery'];
					if ( '' !== $wpmind_cat['credentials_url'] ) {
						$wpmind_recovery .= ' ' . $wpmind_cat['credentials_url'];
					}
				} catch ( \ValueError $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					$wpmind_err_label = $wpmind_reason;
				}
			}
			if ( $wpmind_since > 0 && class_exists( '\WPMind\Provider\Diagnostics' ) ) {
				$wpmind_err_time = \WPMind\Provider\Diagnostics::format_shanghai( $wpmind_since );
			}
			?>
		<details class="wpmind-provider wpmind-endpoint-card" id="endpoint-<?php echo esc_attr( $key ); ?>" <?php echo $open ? 'open' : ''; ?>>
			<summary>
				<span class="wpmind-provider-chevron"><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="wpmind-provider-mark"><?php echo $wpmind_mark( (string) $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="wpmind-provider-name"><?php echo esc_html( (string) $endpoint['name'] ); ?></span>
				<span class="prov"><?php echo esc_html( (string) ( $endpoint['display_name'] ?? $key ) ); ?></span>
				<span class="wpmind-provider-side">
					<?php if ( $has_api_key ) : ?><span class="pill ok"><?php esc_html_e( 'Key 已配置', 'wpmind' ); ?></span><?php endif; ?>
					<?php if ( $is_default ) : ?><span class="pill"><?php esc_html_e( '默认', 'wpmind' ); ?></span><?php endif; ?>
					<?php if ( ! empty( $endpoint['needs_reconfigure'] ) ) : ?><span class="pill warn" title="<?php esc_attr_e( '这家服务的默认接口地址已更换，旧 API Key 可能无法在新地址使用。请填写新的 API Key 或自定义 Base URL 后保存；保存前仍使用原来的地址。', 'wpmind' ); ?>"><?php esc_html_e( '需要重新配置', 'wpmind' ); ?></span><?php endif; ?>
					<?php if ( '' !== $wpmind_cb_state && 'closed' !== $wpmind_cb_state ) : ?>
						<span class="pill warn"><?php echo esc_html( $wpmind_state_labels[ $wpmind_cb_state ] ?? $wpmind_cb_state ); ?></span>
					<?php endif; ?>
					<button type="button" class="btn btn-ghost btn-sm wpmind-test-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试', 'wpmind' ); ?></button>
					<span class="wpmind-test-result" aria-live="polite"></span>
				</span>
			</summary>
			<div class="wpmind-provider-body">

				<div class="wenpai-field wenpai-field--toggle field wenpai-field--row wpmind-field--center">
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-sw-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '启用', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<span class="wenpai-switch">
							<input type="hidden" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][enabled]" value="0">
							<input type="checkbox" role="switch" class="wenpai-switch__input" id="wpmind-sw-<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $enabled ); ?>>
							<span class="wenpai-switch__track" aria-hidden="true"></span>
							<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已启用', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '已停用', 'wpmind' ); ?></span></span>
						</span>
					</div>
				</div>

				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><label class="wenpai-field__label" for="api_key_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'API Key', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="wpmind-api-key-field">
							<input type="password" id="api_key_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][api_key]" value="" autocomplete="new-password" placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
							<button type="button" class="btn btn-sm btn-ghost wpmind-toggle-key" data-target="api_key_<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
								<span class="wpmind-eye wpmind-eye-on"><?php echo AdminIcons::svg( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="wpmind-eye wpmind-eye-off" hidden><?php echo AdminIcons::svg( 'eye-off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							</button>
						</div>
						<?php if ( $has_api_key && '' !== $wpmind_fp ) : ?>
						<p class="wenpai-field__desc wpmind-key-meta" data-provider="<?php echo esc_attr( $key ); ?>">
							<?php
							if ( $wpmind_set_at > 0 ) {
								printf(
									/* translators: 1: key fingerprint, 2: datetime */
									esc_html__( '已配置 · 指纹 %1$s · %2$s 设置。留空表示不修改已保存的 Key。', 'wpmind' ),
									esc_html( $wpmind_fp ),
									esc_html( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $wpmind_set_at ) : gmdate( 'Y-m-d H:i', $wpmind_set_at ) )
								);
							} else {
								printf(
									/* translators: %s: key fingerprint */
									esc_html__( '已配置 · 指纹 %s。留空表示不修改已保存的 Key。', 'wpmind' ),
									esc_html( $wpmind_fp )
								);
							}
							?>
						</p>
						<?php elseif ( ! $has_api_key ) : ?>
						<p class="wenpai-field__desc"><?php esc_html_e( '填写 API Key 后保存。', 'wpmind' ); ?></p>
						<?php endif; ?>
						<?php if ( $has_api_key ) : ?>
						<label class="chk wpmind-clear-key"><input type="checkbox" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]" value="1" class="wpmind-clear-checkbox"> <?php esc_html_e( '清除 API Key', 'wpmind' ); ?></label>
						<?php endif; ?>
						<?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
						<p class="wenpai-field__desc"><?php esc_html_e( '此 API Key 将同步到 WordPress AI Client', 'wpmind' ); ?></p>
						<?php endif; ?>
						<div class="wenpai-actions" style="margin-top:var(--wenpai-space-3)">
							<button type="button" class="btn btn-sm btn-secondary wpmind-test-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试连接', 'wpmind' ); ?></button>
							<button type="button" class="btn btn-sm btn-ghost wpmind-rotate-key" data-provider="<?php echo esc_attr( $key ); ?>" title="<?php esc_attr_e( '用输入框中的新 Key 验证通过后替换已保存的 Key', 'wpmind' ); ?>"><?php esc_html_e( '验证并替换', 'wpmind' ); ?></button>
							<button type="button" class="btn btn-sm btn-ghost wpmind-reset-breaker" data-provider="<?php echo esc_attr( $key ); ?>" title="<?php esc_attr_e( '清除熔断 / 需要处理 / 额度不足状态', 'wpmind' ); ?>"><?php esc_html_e( '重置', 'wpmind' ); ?></button>
							<label class="chk"><input type="checkbox" class="wpmind-deep-test" value="1"> <?php esc_html_e( '深度测试（会产生少量费用）', 'wpmind' ); ?></label>
							<span class="wpmind-test-result" aria-live="polite"></span>
						</div>
					</div>
				</div>

				<?php if ( '' !== $wpmind_err_label || '' !== $wpmind_recovery ) : ?>
				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '最近错误', 'wpmind' ); ?></span></div>
					<div class="field-ctl">
						<div class="wpmind-error">
							<?php if ( '' !== $wpmind_err_label ) : ?>
								<strong><?php echo esc_html( $wpmind_err_label ); ?></strong>
								<?php if ( '' !== $wpmind_err_time ) : ?> <span class="meta">· <?php echo esc_html( $wpmind_err_time ); ?></span><?php endif; ?>
							<?php endif; ?>
							<?php if ( '' !== $wpmind_recovery ) : ?><div class="meta" style="margin-top:4px"><?php echo esc_html( $wpmind_recovery ); ?></div><?php endif; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<details class="wpmind-advanced">
					<summary><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( '高级设置', 'wpmind' ); ?></summary>
					<div class="wpmind-advanced-body">
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="custom_base_url_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="custom_base_url_<?php echo esc_attr( $key ); ?>" name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]" value="<?php echo esc_attr( $endpoint['custom_base_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $endpoint['base_url'] ); ?>">
								<p class="wenpai-field__desc"><?php esc_html_e( '留空使用默认地址。', 'wpmind' ); ?></p>
							</div>
						</div>
						<div class="wenpai-field field wenpai-field--row wpmind-field--center">
							<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></span></div>
							<div class="field-ctl">
								<?php
								$wpmind_display_models = (array) $endpoint['models'];
								if ( [] === $wpmind_display_models ) {
									$wpmind_display_models = \WPMind\Provider\Catalog\ModelCatalog::instance()->recommended_ids( $key );
								}
								foreach ( $wpmind_display_models as $model ) :
									?>
								<span class="prov"><?php echo esc_html( $model ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</details>

			</div><!-- .wpmind-provider-body -->
		</details>
		<?php endforeach; ?>
		</div><!-- .wpmind-providers -->

		<div class="card-foot">
			<span class="meta"><?php esc_html_e( 'API Key 加密存储；测试连接会用已保存或输入中的 Key 实测上游。', 'wpmind' ); ?></span>
			<span class="wenpai-actions">
				<button type="submit" class="btn btn-primary" name="submit"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
			</span>
		</div>
	</section>
</form>

<form method="post" action="options.php" id="wpmind-image-settings-form">
	<?php settings_fields( 'wpmind_image_settings' ); ?>

	<section class="card">
		<div class="card-head">
			<div>
				<h2 class="card-title"><?php esc_html_e( '图像服务', 'wpmind' ); ?></h2>
				<p class="card-sub"><?php echo esc_html( sprintf( /* translators: 1: count, 2: default name */ __( '%1$d 个端点 · 默认：%2$s · 供 AI Experiments 使用', 'wpmind' ), count( $available_image_providers ), '' !== $default_image_provider && isset( $available_image_providers[ $default_image_provider ] ) ? (string) $available_image_providers[ $default_image_provider ]['name'] : __( '未设置', 'wpmind' ) ) ); ?></p>
			</div>
			<span class="pill mute"><?php esc_html_e( 'Key 加密保存', 'wpmind' ); ?></span>
		</div>

		<div class="wenpai-field wenpai-field--row field">
			<div class="field-label"><label class="wenpai-field__label" for="wpmind_default_image_provider"><?php esc_html_e( '默认图像服务', 'wpmind' ); ?></label></div>
			<div class="field-ctl">
				<select id="wpmind_default_image_provider" name="wpmind_default_image_provider" style="max-width:280px">
					<option value=""><?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?></option>
					<?php foreach ( $available_image_providers as $key => $provider ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_image_provider, $key ); ?>><?php echo esc_html( $provider['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="wenpai-field__desc"><?php esc_html_e( '启用智能路由时将自动选择最优服务。', 'wpmind' ); ?></p>
			</div>
		</div>

		<div class="wpmind-providers">
		<?php foreach ( $available_image_providers as $key => $provider ) : ?>
			<?php
			$saved_config = isset( $image_endpoints[ $key ] ) && is_array( $image_endpoints[ $key ] ) ? $image_endpoints[ $key ] : [];
			$is_enabled   = ! empty( $saved_config['enabled'] );
			$has_api_key  = ! empty( $saved_config['api_key'] );
			$open         = $is_enabled && $has_api_key;
			$is_default   = (string) $default_image_provider === (string) $key;
			?>
		<details class="wpmind-provider wpmind-endpoint-card" id="image-endpoint-<?php echo esc_attr( $key ); ?>" <?php echo $open ? 'open' : ''; ?>>
			<summary>
				<span class="wpmind-provider-chevron"><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="wpmind-provider-mark"><?php echo AdminIcons::svg( $wpmind_img_mark[ $key ] ?? 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="wpmind-provider-name"><?php echo esc_html( (string) $provider['name'] ); ?></span>
				<span class="prov"><?php echo esc_html( (string) $provider['display_name'] ); ?></span>
				<span class="wpmind-provider-side">
					<?php if ( $has_api_key ) : ?><span class="pill ok"><?php esc_html_e( 'Key 已配置', 'wpmind' ); ?></span><?php endif; ?>
					<?php if ( $is_default ) : ?><span class="pill"><?php esc_html_e( '默认', 'wpmind' ); ?></span><?php endif; ?>
					<button type="button" class="btn btn-ghost btn-sm wpmind-test-image-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $is_enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试', 'wpmind' ); ?></button>
					<span class="wpmind-test-result" aria-live="polite"></span>
				</span>
			</summary>
			<div class="wpmind-provider-body">

				<div class="wenpai-field wenpai-field--toggle field wenpai-field--row wpmind-field--center">
					<div class="field-label"><label class="wenpai-field__label" for="wpmind-img-sw-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '启用', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<span class="wenpai-switch">
							<input type="hidden" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][enabled]" value="0">
							<input type="checkbox" role="switch" class="wenpai-switch__input" id="wpmind-img-sw-<?php echo esc_attr( $key ); ?>" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $is_enabled ); ?>>
							<span class="wenpai-switch__track" aria-hidden="true"></span>
							<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on"><?php esc_html_e( '已启用', 'wpmind' ); ?></span><span class="wenpai-switch__off"><?php esc_html_e( '已停用', 'wpmind' ); ?></span></span>
						</span>
						<p class="wenpai-field__desc"><?php echo esc_html( (string) $provider['description'] ); ?></p>
					</div>
				</div>

				<div class="wenpai-field field wenpai-field--row">
					<div class="field-label"><label class="wenpai-field__label" for="image_api_key_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'API Key', 'wpmind' ); ?></label></div>
					<div class="field-ctl">
						<div class="wpmind-api-key-field">
							<input type="password" id="image_api_key_<?php echo esc_attr( $key ); ?>" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][api_key]" value="" autocomplete="new-password" placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
							<button type="button" class="btn btn-sm btn-ghost wpmind-toggle-key" data-target="image_api_key_<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
								<span class="wpmind-eye wpmind-eye-on"><?php echo AdminIcons::svg( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="wpmind-eye wpmind-eye-off" hidden><?php echo AdminIcons::svg( 'eye-off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							</button>
						</div>
						<?php if ( $has_api_key ) : ?>
						<p class="wenpai-field__desc"><?php esc_html_e( '已配置。留空表示不修改已保存的 Key。', 'wpmind' ); ?></p>
						<label class="chk wpmind-clear-key"><input type="checkbox" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]" value="1" class="wpmind-clear-checkbox"> <?php esc_html_e( '清除 API Key', 'wpmind' ); ?></label>
						<?php endif; ?>
						<div class="wenpai-actions" style="margin-top:var(--wenpai-space-3)">
							<button type="button" class="btn btn-sm btn-secondary wpmind-test-image-connection" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $is_enabled ? '' : 'disabled'; ?>><?php esc_html_e( '测试连接', 'wpmind' ); ?></button>
							<span class="wpmind-test-result" aria-live="polite"></span>
						</div>
					</div>
				</div>

				<div class="wenpai-field field wenpai-field--row wpmind-field--center">
					<div class="field-label"><span class="wenpai-field__label"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></span></div>
					<div class="field-ctl">
						<?php foreach ( (array) $provider['models'] as $model ) : ?>
							<span class="prov"><?php echo esc_html( $model ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>

				<details class="wpmind-advanced">
					<summary><?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( '高级设置', 'wpmind' ); ?></summary>
					<div class="wpmind-advanced-body">
						<div class="wenpai-field field wenpai-field--row">
							<div class="field-label"><label class="wenpai-field__label" for="image_base_url_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?></label></div>
							<div class="field-ctl">
								<input type="url" id="image_base_url_<?php echo esc_attr( $key ); ?>" name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]" value="<?php echo esc_attr( $saved_config['custom_base_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $provider['base_url'] ); ?>">
							</div>
						</div>
					</div>
				</details>

			</div><!-- .wpmind-provider-body -->
		</details>
		<?php endforeach; ?>
		</div><!-- .wpmind-providers -->

		<div class="card-foot">
			<span class="meta"><?php esc_html_e( '图像 Provider 与文本服务共用加密 Key 存储；测试连接实测上游。', 'wpmind' ); ?></span>
			<span class="wenpai-actions">
				<button type="submit" class="btn btn-primary" name="submit"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
			</span>
		</div>
	</section>
</form>

</div>
