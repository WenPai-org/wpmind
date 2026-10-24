<?php
/**
 * WPMind 服务配置 Tab
 *
 * 包含：AI 服务端点 + 全局设置
 *
 * @package WPMind
 * @since 2.0.0
 */

// 防止直接访问
defined( 'ABSPATH' ) || exit;

// 获取数据
$wpmind_instance  = \WPMind\wpmind();
$endpoints        = $wpmind_instance->get_custom_endpoints();
$default_provider = get_option( 'wpmind_default_provider', '' );
$request_timeout  = get_option( 'wpmind_request_timeout', 60 );
?>

<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-cloud-line"></span>
		<?php esc_html_e( '服务配置', 'wpmind' ); ?>
	</h2>
</div>

<p class="wpmind-module-desc">
	<?php esc_html_e( '配置 AI 服务端点和 API 密钥，支持 OpenAI、Anthropic、Google 及国内主流 AI 服务。', 'wpmind' ); ?>
</p>

<div class="wpmind-diagnostics-toolbar" style="margin:12px 0 20px;">
	<button type="button" class="button" id="wpmind-export-diagnostics"><?php esc_html_e( '导出诊断包', 'wpmind' ); ?></button>
	<button type="button" class="button" id="wpmind-export-config"><?php esc_html_e( '导出端点配置', 'wpmind' ); ?></button>
	<button type="button" class="button" id="wpmind-import-config"><?php esc_html_e( '导入端点配置', 'wpmind' ); ?></button>
	<input type="file" id="wpmind-import-config-file" accept="application/json,.json" style="display:none;">
	<span class="description"><?php esc_html_e( '诊断包与配置导出均不含 API Key / 指纹。导入会保留本站密钥。', 'wpmind' ); ?></span>
</div>

<form method="post" action="options.php" id="wpmind-settings-form">
	<?php settings_fields( 'wpmind_settings' ); ?>

	<h2 class="title">
		<span class="dashicons ri-settings-3-line"></span>
		<?php esc_html_e( '全局设置', 'wpmind' ); ?>
	</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wpmind_request_timeout">
					<?php esc_html_e( '请求超时', 'wpmind' ); ?>
				</label>
			</th>
			<td>
				<input type="number"
						id="wpmind_request_timeout"
						name="wpmind_request_timeout"
						value="<?php echo esc_attr( $request_timeout ); ?>"
						min="10"
						max="300"
						step="1"
						class="small-text">
				<span class="description">
					<?php esc_html_e( '秒 (建议 60-120)', 'wpmind' ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wpmind_default_provider">
					<?php esc_html_e( '默认服务', 'wpmind' ); ?>
				</label>
			</th>
			<td>
				<select id="wpmind_default_provider" name="wpmind_default_provider">
					<option value="">
						<?php esc_html_e( '— 选择默认服务 —', 'wpmind' ); ?>
					</option>
					<?php foreach ( $endpoints as $key => $endpoint ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"
							<?php selected( $default_provider, $key ); ?>>
							<?php echo esc_html( $endpoint['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<h2 class="title">
		<span class="dashicons ri-cloud-line"></span>
		<?php esc_html_e( 'AI 服务端点', 'wpmind' ); ?>
	</h2>

	<div class="wpmind-endpoints-grid">
		<?php
		foreach ( $endpoints as $key => $endpoint ) :
			$has_api_key = $wpmind_instance->has_api_key( $key );
			$icon_class  = \WPMind\Modules\CostControl\UsageTracker::get_provider_icon( $key );
			$icon_color  = \WPMind\Modules\CostControl\UsageTracker::get_provider_color( $key );
			?>
		<div class="wpmind-endpoint-card<?php echo ( ! empty( $endpoint['enabled'] ) && $has_api_key ) ? '' : ' is-collapsed'; ?>" id="endpoint-<?php echo esc_attr( $key ); ?>">
			<div class="wpmind-endpoint-header">
				<button type="button" class="wpmind-endpoint-toggle" aria-expanded="<?php echo ( ! empty( $endpoint['enabled'] ) && $has_api_key ) ? 'true' : 'false'; ?>">
					<span class="dashicons ri-arrow-down-s-line"></span>
				</button>
				<i class="<?php echo esc_attr( $icon_class ); ?> wpmind-provider-icon" style="color: <?php echo esc_attr( $icon_color ); ?>;"></i>
				<span class="wpmind-endpoint-name">
					<?php echo esc_html( $endpoint['name'] ); ?>
				</span>
				<code class="wpmind-endpoint-key"><?php echo esc_html( $endpoint['display_name'] ?? $key ); ?></code>
				<?php if ( ! empty( $endpoint['enabled'] ) && $has_api_key ) : ?>
					<span class="wpmind-status wpmind-status-active">
						<?php esc_html_e( '已启用', 'wpmind' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $endpoint['needs_reconfigure'] ) ) : ?>
					<span class="wpmind-status wpmind-status-warning" title="<?php esc_attr_e( '这家服务的默认接口地址已更换，旧 API Key 可能无法在新地址使用。请填写新的 API Key 或自定义 Base URL 后保存；保存前仍使用原来的地址。', 'wpmind' ); ?>">
						<?php esc_html_e( '需要重新配置', 'wpmind' ); ?>
					</span>
				<?php endif; ?>
				<?php
				$wpmind_cb_state   = '';
				$wpmind_cb_details = array();
				$wpmind_breaker    = null;
				if ( class_exists( '\WPMind\Failover\FailoverManager' ) ) {
					$wpmind_breaker = \WPMind\Failover\FailoverManager::instance()->get_circuit_breaker( $key );
					if ( $wpmind_breaker ) {
						$wpmind_cb_state   = $wpmind_breaker->get_state();
						$wpmind_cb_details = $wpmind_breaker->get_status_details();
					}
				}
				$wpmind_state_labels = array(
					'closed'          => __( '正常', 'wpmind' ),
					'open'            => __( '熔断中', 'wpmind' ),
					'half_open'       => __( '恢复测试', 'wpmind' ),
					'needs_attention' => __( '需要处理', 'wpmind' ),
					'quota_exhausted' => __( '额度不足', 'wpmind' ),
				);
				if ( '' !== $wpmind_cb_state && 'closed' !== $wpmind_cb_state ) :
					$wpmind_label = $wpmind_state_labels[ $wpmind_cb_state ] ?? $wpmind_cb_state;
					?>
					<span class="wpmind-status wpmind-status-warning">
						<?php echo esc_html( $wpmind_label ); ?>
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
									name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][enabled]"
									value="1"
									<?php checked( ! empty( $endpoint['enabled'] ) ); ?>>
							<span class="wpmind-toggle-slider"></span>
							<span class="wpmind-toggle-label">
								<?php esc_html_e( '启用此服务', 'wpmind' ); ?>
							</span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="api_key_<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( 'API Key', 'wpmind' ); ?>
						</label>
					</th>
					<td>
						<div class="wpmind-api-key-field">
							<input type="password"
									id="api_key_<?php echo esc_attr( $key ); ?>"
									name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][api_key]"
									value=""
									class="regular-text"
									autocomplete="new-password"
									placeholder="<?php echo $has_api_key ? '••••••••••••••••' : esc_attr__( '请输入 API Key', 'wpmind' ); ?>">
							<button type="button"
									class="button wpmind-toggle-key"
									data-target="api_key_<?php echo esc_attr( $key ); ?>"
									aria-label="<?php esc_attr_e( '切换密码显示', 'wpmind' ); ?>">
								<span class="dashicons ri-eye-line"></span>
							</button>
						</div>
						<?php
						$wpmind_key_meta = is_array( $endpoint['key_meta'] ?? null ) ? $endpoint['key_meta'] : array();
						if ( $has_api_key && ( empty( $wpmind_key_meta['fingerprint'] ) ) && class_exists( '\WPMind\Provider\KeyRotation' ) ) {
							$wpmind_key_meta = \WPMind\Provider\KeyRotation::ensure_key_meta( $key );
						}
						$wpmind_fp     = is_string( $wpmind_key_meta['fingerprint'] ?? null ) ? (string) $wpmind_key_meta['fingerprint'] : '';
						$wpmind_set_at = isset( $wpmind_key_meta['set_at'] ) ? (int) $wpmind_key_meta['set_at'] : 0;
						if ( $has_api_key && '' !== $wpmind_fp ) :
							$wpmind_when = $wpmind_set_at > 0
								? ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $wpmind_set_at ) : gmdate( 'Y-m-d H:i', $wpmind_set_at ) )
								: '';
							?>
						<p class="description wpmind-key-meta" data-provider="<?php echo esc_attr( $key ); ?>">
							<?php
							if ( '' !== $wpmind_when ) {
								printf(
									/* translators: 1: key fingerprint, 2: datetime */
									esc_html__( '已配置 · 指纹 %1$s · %2$s 设置', 'wpmind' ),
									esc_html( $wpmind_fp ),
									esc_html( $wpmind_when )
								);
							} else {
								printf(
									/* translators: %s: key fingerprint */
									esc_html__( '已配置 · 指纹 %s', 'wpmind' ),
									esc_html( $wpmind_fp )
								);
							}
							?>
						</p>
						<?php endif; ?>
						<?php if ( $has_api_key ) : ?>
						<label class="wpmind-clear-key">
							<input type="checkbox"
									name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][clear_api_key]"
									value="1"
									class="wpmind-clear-checkbox">
							<?php esc_html_e( '清除 API Key', 'wpmind' ); ?>
						</label>
						<?php endif; ?>
						<?php if ( ! empty( $endpoint['is_official'] ) ) : ?>
						<p class="description">
							<?php esc_html_e( '此 API Key 将同步到 WordPress AI Client', 'wpmind' ); ?>
						</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '连接测试', 'wpmind' ); ?></th>
					<td>
						<button type="button"
								class="button wpmind-test-connection"
								data-provider="<?php echo esc_attr( $key ); ?>"
								<?php echo empty( $endpoint['enabled'] ) ? 'disabled' : ''; ?>>
							<?php esc_html_e( '测试连接', 'wpmind' ); ?>
						</button>
						<button type="button"
								class="button wpmind-rotate-key"
								data-provider="<?php echo esc_attr( $key ); ?>"
								title="<?php esc_attr_e( '用输入框中的新 Key 验证通过后替换已保存的 Key', 'wpmind' ); ?>">
							<?php esc_html_e( '验证并替换', 'wpmind' ); ?>
						</button>
						<button type="button"
								class="button wpmind-reset-breaker"
								data-provider="<?php echo esc_attr( $key ); ?>"
								title="<?php esc_attr_e( '清除熔断 / 需要处理 / 额度不足状态', 'wpmind' ); ?>">
							<?php esc_html_e( '重置', 'wpmind' ); ?>
						</button>
						<label style="margin-left:8px;">
							<input type="checkbox" class="wpmind-deep-test" value="1">
							<?php esc_html_e( '深度测试（会产生少量费用）', 'wpmind' ); ?>
						</label>
						<span class="wpmind-test-result"></span>
					</td>
				</tr>
				<?php
				$wpmind_recovery = '';
				$wpmind_err_time = '';
				$wpmind_err_label = '';
				$wpmind_reason = is_string( $wpmind_cb_details['reason'] ?? null ) ? (string) $wpmind_cb_details['reason'] : '';
				$wpmind_since  = isset( $wpmind_cb_details['since'] ) ? (int) $wpmind_cb_details['since'] : ( isset( $wpmind_cb_details['last_failure'] ) ? (int) $wpmind_cb_details['last_failure'] : 0 );
				if ( '' !== $wpmind_reason && class_exists( '\WPMind\Provider\Health\ErrorClass' ) && class_exists( '\WPMind\Provider\Health\ErrorCatalog' ) ) {
					try {
						$wpmind_ec = \WPMind\Provider\Health\ErrorClass::from( $wpmind_reason );
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
				if ( '' !== $wpmind_err_label || '' !== $wpmind_recovery ) :
					?>
				<tr class="wpmind-recovery-row">
					<th scope="row"><?php esc_html_e( '最近错误', 'wpmind' ); ?></th>
					<td>
						<?php if ( '' !== $wpmind_err_label ) : ?>
							<strong><?php echo esc_html( $wpmind_err_label ); ?></strong>
							<?php if ( '' !== $wpmind_err_time ) : ?>
								<span class="description"> · <?php echo esc_html( $wpmind_err_time ); ?></span>
							<?php endif; ?>
							<br>
						<?php endif; ?>
						<?php if ( '' !== $wpmind_recovery ) : ?>
							<span class="description"><?php echo esc_html( $wpmind_recovery ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>

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
						<label for="custom_base_url_<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( '自定义 Base URL', 'wpmind' ); ?>
						</label>
					</th>
					<td>
						<input type="url"
								id="custom_base_url_<?php echo esc_attr( $key ); ?>"
								name="wpmind_custom_endpoints[<?php echo esc_attr( $key ); ?>][custom_base_url]"
								value="<?php echo esc_attr( $endpoint['custom_base_url'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( $endpoint['base_url'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '支持的模型', 'wpmind' ); ?></th>
					<td class="wpmind-models">
						<?php
						$wpmind_display_models = (array) $endpoint['models'];
						if ( [] === $wpmind_display_models ) {
							$wpmind_display_models = \WPMind\Provider\Catalog\ModelCatalog::instance()->recommended_ids( $key );
						}
						foreach ( $wpmind_display_models as $model ) :
							?>
							<code><?php echo esc_html( $model ); ?></code>
							<?php
						endforeach;
						?>
					</td>
				</tr>
			</table>
			</div><!-- .wpmind-endpoint-body -->
		</div>
		<?php endforeach; ?>
	</div>

	<?php submit_button( __( '保存设置', 'wpmind' ) ); ?>
</form>
