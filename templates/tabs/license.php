<?php
/**
 * WPMind 授权设置 Tab
 *
 * @package WPMind
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit();

$license  = \WPMind\wpmind_license();
$key      = $license->get_key();
$data     = $license->verify();
$is_valid = ! empty( $data['valid'] );
$plan     = $data['plan'] ?? 'free';
?>

<div class="wpmind-license-settings">
	<h2><?php esc_html_e( '授权管理', 'wpmind' ); ?></h2>
	<p class="description">
		<?php esc_html_e( '输入您的授权密钥以解锁 Pro/Enterprise 功能。', 'wpmind' ); ?>
	</p>

	<!-- 状态卡片 -->
	<div class="wpmind-license-status" style="margin: 16px 0; padding: 16px; background: <?php echo $is_valid ? '#f0fdf4' : '#fefce8'; ?>; border-left: 4px solid <?php echo $is_valid ? '#22c55e' : '#eab308'; ?>; border-radius: 4px;">
		<strong><?php esc_html_e( '当前状态', 'wpmind' ); ?>:</strong>
		<?php if ( $is_valid ) : ?>
			<span style="color: #16a34a;">
				<?php
				printf(
					/* translators: %s: plan name */
					esc_html__( '已激活 — %s', 'wpmind' ),
					esc_html( strtoupper( $plan ) )
				);
				?>
			</span>
			<?php if ( ! empty( $data['expires_at'] ) ) : ?>
				<br><small>
					<?php
					printf(
						/* translators: %s: expiration date */
						esc_html__( '到期时间: %s', 'wpmind' ),
						esc_html( wp_date( 'Y-m-d', strtotime( $data['expires_at'] ) ) )
					);
					?>
				</small>
			<?php endif; ?>
		<?php else : ?>
			<span style="color: #ca8a04;">
				<?php esc_html_e( '免费版', 'wpmind' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- License Key 输入 -->
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wpmind-license-key"><?php esc_html_e( 'License Key', 'wpmind' ); ?></label>
			</th>
			<td>
				<input type="text" id="wpmind-license-key" class="regular-text"
					value="<?php echo esc_attr( $key ); ?>"
					placeholder="wenpai_wpmind_pro_xxxxxxxx"
					<?php echo $is_valid ? 'readonly' : ''; ?>
				/>
				<p class="description">
					<?php esc_html_e( '在 wenpai.net 购买后获取授权密钥。', 'wpmind' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- 操作按钮 -->
	<p class="submit">
		<?php if ( $is_valid ) : ?>
			<button type="button" id="wpmind-license-deactivate" class="button button-secondary">
				<?php esc_html_e( '停用授权', 'wpmind' ); ?>
			</button>
		<?php else : ?>
			<button type="button" id="wpmind-license-activate" class="button button-primary">
				<?php esc_html_e( '激活授权', 'wpmind' ); ?>
			</button>
		<?php endif; ?>
		<span id="wpmind-license-spinner" class="spinner" style="float: none;"></span>
		<span id="wpmind-license-message" style="margin-left: 8px;"></span>
	</p>

	<?php wp_nonce_field( 'wpmind_license_action', 'wpmind_license_nonce' ); ?>
</div>

<script>
(function() {
	const activateBtn = document.getElementById('wpmind-license-activate');
	const deactivateBtn = document.getElementById('wpmind-license-deactivate');
	const keyInput = document.getElementById('wpmind-license-key');
	const spinner = document.getElementById('wpmind-license-spinner');
	const message = document.getElementById('wpmind-license-message');
	const nonce = document.getElementById('wpmind_license_nonce').value;

	function doAction(action) {
		spinner.classList.add('is-active');
		message.textContent = '';

		const data = new FormData();
		data.append('action', 'wpmind_license');
		data.append('license_action', action);
		data.append('license_key', keyInput.value);
		data.append('_wpnonce', nonce);

		fetch(ajaxurl, { method: 'POST', body: data })
			.then(r => r.json())
			.then(res => {
				spinner.classList.remove('is-active');
				if (res.success) {
					message.style.color = '#16a34a';
					message.textContent = res.data.message || 'OK';
					setTimeout(() => location.reload(), 1000);
				} else {
					message.style.color = '#dc2626';
					message.textContent = res.data.message || 'Error';
				}
			})
			.catch(() => {
				spinner.classList.remove('is-active');
				message.style.color = '#dc2626';
				message.textContent = '<?php esc_html_e( '请求失败', 'wpmind' ); ?>';
			});
	}

	if (activateBtn) activateBtn.addEventListener('click', () => doAction('activate'));
	if (deactivateBtn) deactivateBtn.addEventListener('click', () => doAction('deactivate'));
})();
</script>
