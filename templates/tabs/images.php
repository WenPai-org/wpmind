<?php
/**
 * WPMind 图像服务 Tab
 *
 * 包含：图像生成 AI 服务端点配置
 *
 * @package WPMind
 * @since 2.4.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取已保存的配置
$image_endpoints  = get_option( 'wpmind_image_endpoints', [] );
$default_image_provider = get_option( 'wpmind_default_image_provider', '' );

// 图像生成服务商定义（已通过 Gemini CLI 核实 2026-02-01）
$available_providers = [
	'openai_gpt_image' => [
		'name'         => 'DALL-E 3 / GPT-Image',
		'display_name' => 'openai-image',
		'description'  => 'OpenAI 图像生成，综合最均衡、文字渲染极强',
		'base_url'     => 'https://api.openai.com/v1/',
		'models'       => [ 'dall-e-3', 'gpt-image-1.5' ],
	],
	'google_gemini_image' => [
		'name'         => 'Imagen 3',
		'display_name' => 'google-imagen',
		'description'  => 'Google Imagen 图像生成，文字最稳、复杂构图极强',
		'base_url'     => 'https://generativelanguage.googleapis.com/v1beta/',
		'models'       => [ 'imagen-3.0-generate-001' ],
	],
	'tencent_hunyuan' => [
		'name'         => '混元图像',
		'display_name' => 'hunyuan-image',
		'description'  => '腾讯混元，中文理解 & 文字渲染顶级（需 SDK 签名）',
		'base_url'     => 'https://hunyuan.tencentcloudapi.com/',
		'models'       => [ 'hunyuan-image-v3' ],
	],
	'bytedance_doubao' => [
		'name'         => 'Doubao Image / Seedream',
		'display_name' => 'doubao-image',
		'description'  => '火山引擎图像生成，性价比高、中文文化理解强',
		'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3/',
		'models'       => [ 'doubao-image-v1', 'seedream-4.5' ],
	],
	'flux' => [
		'name'         => 'Flux (Fal.ai)',
		'display_name' => 'flux',
		'description'  => 'Black Forest Labs 开源天花板，手部/文字/遵循最强',
		'base_url'     => 'https://fal.run/fal-ai/',
		'models'       => [ 'flux/dev', 'flux/schnell', 'flux-pro-1.1' ],
	],

	'qwen_image' => [
		'name'         => '通义万相',
		'display_name' => 'wanx',
		'description'  => '阿里云通义万相，真实感极强，几乎无AI味',
		'base_url'     => 'https://dashscope.aliyuncs.com/api/v1/',
		'models'       => [ 'wanx-v1', 'wanx-background-generation-v2' ],
	],
];

// 图标映射（单色风格，与文本服务一致）
$provider_icons = [
	'openai_gpt_image'    => [ 'icon' => 'ri-openai-line', 'color' => '#50575e' ],
	'google_gemini_image' => [ 'icon' => 'ri-gemini-line', 'color' => '#50575e' ],
	'tencent_hunyuan'     => [ 'icon' => 'ri-cloud-line', 'color' => '#50575e' ],
	'bytedance_doubao'    => [ 'icon' => 'ri-fire-line', 'color' => '#50575e' ],
	'flux'                => [ 'icon' => 'ri-sparkling-2-line', 'color' => '#50575e' ],

	'qwen_image'          => [ 'icon' => 'ri-rainbow-line', 'color' => '#50575e' ],
];

/**
 * 检查是否有 API Key
 */
function wpmind_image_has_api_key( $key, $endpoints ) {
	return ! empty( $endpoints[ $key ]['api_key'] );
}
?>

<div class="wpmind-geo-header">
	<h2 class="wpmind-geo-title">
		<span class="dashicons ri-image-line"></span>
		<?php esc_html_e( '图片生成', 'wpmind' ); ?>
	</h2>
</div>

<p class="wpmind-geo-desc">
	<?php esc_html_e( '配置 AI 图像生成服务，支持 DALL-E、Imagen、混元、通义万相等多个平台。', 'wpmind' ); ?>
</p>

<form method="post" action="options.php" id="wpmind-image-settings-form">
	<?php settings_fields( 'wpmind_image_settings' ); ?>

	<h2 class="title">
		<span class="dashicons ri-settings-3-line"></span>
		<?php esc_html_e( '全局设置', 'wpmind' ); ?>
	</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wpmind_default_image_provider">
					<?php esc_html_e( '默认图像服务', 'wpmind' ); ?>
				</label>
			</th>
			<td>
				<select id="wpmind_default_image_provider" name="wpmind_default_image_provider">
					<option value="">
						<?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?>
					</option>
					<?php foreach ( $available_providers as $key => $provider ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"
							<?php selected( $default_image_provider, $key ); ?>>
							<?php echo esc_html( $provider['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( '启用智能路由时将自动选择最优服务', 'wpmind' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2 class="title">
		<span class="dashicons ri-image-line"></span>
		<?php esc_html_e( '图像生成服务', 'wpmind' ); ?>
	</h2>
	<p class="description" style="margin-bottom: 16px;">
		<?php esc_html_e( '配置图像生成 AI 服务商。启用后可在 AI Experiments 中使用 Image Generation 功能。', 'wpmind' ); ?>
	</p>

	<div class="wpmind-endpoints-grid">
		<?php foreach ( $available_providers as $key => $provider ) :
			$saved_config = $image_endpoints[ $key ] ?? [];
			$is_enabled   = ! empty( $saved_config['enabled'] );
			$has_api_key  = wpmind_image_has_api_key( $key, $image_endpoints );
			$icon_class   = $provider_icons[ $key ]['icon'] ?? 'ri-image-line';
			$icon_color   = $provider_icons[ $key ]['color'] ?? '#6b7280';
		?>
		<div class="wpmind-endpoint-card<?php echo ( $is_enabled && $has_api_key ) ? '' : ' is-collapsed'; ?>" id="image-endpoint-<?php echo esc_attr( $key ); ?>">
			<div class="wpmind-endpoint-header">
				<button type="button" class="wpmind-endpoint-toggle" aria-expanded="<?php echo ( $is_enabled && $has_api_key ) ? 'true' : 'false'; ?>">
					<span class="dashicons ri-arrow-down-s-line"></span>
				</button>
				<i class="<?php echo esc_attr( $icon_class ); ?> wpmind-provider-icon" style="color: <?php echo esc_attr( $icon_color ); ?>;"></i>
				<span class="wpmind-endpoint-name">
					<?php echo esc_html( $provider['name'] ); ?>
				</span>
				<code class="wpmind-endpoint-key"><?php echo esc_html( $provider['display_name'] ); ?></code>
				<?php if ( $is_enabled && $has_api_key ) : ?>
					<span class="wpmind-status wpmind-status-active">
						<?php esc_html_e( '已启用', 'wpmind' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="wpmind-endpoint-body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '启用', 'wpmind' ); ?></th>
					<td>
						<label class="wpmind-toggle">
							<input type="checkbox"
								   name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][enabled]"
								   value="1"
								   <?php checked( $is_enabled ); ?>>
							<span class="wpmind-toggle-slider"></span>
							<span class="wpmind-toggle-label">
								<?php esc_html_e( '启用此服务', 'wpmind' ); ?>
							</span>
						</label>
						<p class="description"><?php echo esc_html( $provider['description'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="image_api_key_<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( 'API Key', 'wpmind' ); ?>
						</label>
					</th>
					<td>
						<div class="wpmind-api-key-field">
							<input type="password"
								   id="image_api_key_<?php echo esc_attr( $key ); ?>"
								   name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][api_key]"
								   value=""
								   class="regular-text"
								   autocomplete="new-password"
								   placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
							<button type="button"
									class="button wpmind-toggle-key"
									data-target="image_api_key_<?php echo esc_attr( $key ); ?>"
									aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
								<span class="dashicons ri-eye-line"></span>
							</button>
						</div>
						<?php if ( $has_api_key ) : ?>
						<label class="wpmind-clear-key">
							<input type="checkbox"
								   name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]"
								   value="1"
								   class="wpmind-clear-checkbox">
							<?php esc_html_e( '清除 API Key', 'wpmind' ); ?>
						</label>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '连接测试', 'wpmind' ); ?></th>
					<td>
						<button type="button"
								class="button wpmind-test-image-connection"
								data-provider="<?php echo esc_attr( $key ); ?>"
								<?php echo ! $is_enabled ? 'disabled' : ''; ?>>
							<?php esc_html_e( '测试连接', 'wpmind' ); ?>
						</button>
						<span class="wpmind-test-result"></span>
					</td>
				</tr>
				<tr class="wpmind-advanced-row">
					<td colspan="2">
						<button type="button" class="button button-link wpmind-toggle-advanced">
							<span class="dashicons ri-arrow-down-s-line"></span>
							<?php esc_html_e( '高级设置', 'wpmind' ); ?>
						</button>
					</td>
				</tr>
			</table>

			<!-- 高级设置（默认隐藏） -->
			<table class="form-table wpmind-advanced-settings" style="display: none;">
				<tr>
					<th scope="row">
						<label for="image_base_url_<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?>
						</label>
					</th>
					<td>
						<input type="url"
							   id="image_base_url_<?php echo esc_attr( $key ); ?>"
							   name="wpmind_image_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]"
							   value="<?php echo esc_attr( $saved_config['custom_base_url'] ?? '' ); ?>"
							   class="regular-text"
							   placeholder="<?php echo esc_attr( $provider['base_url'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></th>
					<td class="wpmind-models">
						<?php foreach ( (array) $provider['models'] as $model ) : ?>
							<code><?php echo esc_html( $model ); ?></code>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
			</div><!-- .wpmind-endpoint-body -->
		</div>
		<?php endforeach; ?>
	</div>

	<?php submit_button( __( '保存设置', 'wpmind' ) ); ?>
</form>
