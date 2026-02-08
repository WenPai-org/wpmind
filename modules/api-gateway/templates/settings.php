<?php
/**
 * API Gateway Settings Tab
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use WPMind\Modules\ApiGateway\Auth\ApiKeyRepository;

$gateway_enabled  = get_option( 'wpmind_gateway_enabled', '0' ) === '1';
$sse_global_limit = (int) get_option( 'wpmind_gateway_sse_global_limit', 20 );
$default_rpm      = (int) get_option( 'wpmind_gateway_default_rpm', 60 );
$default_tpm      = (int) get_option( 'wpmind_gateway_default_tpm', 100000 );
$max_body_mb      = round( (int) get_option( 'wpmind_gateway_max_body_bytes', 10485760 ) / 1048576 );
$max_tokens_cap   = (int) get_option( 'wpmind_gateway_max_tokens_cap', 16384 );
$log_prompts      = get_option( 'wpmind_gateway_log_prompts', '0' ) === '1';

$total_keys     = ApiKeyRepository::count_keys();
$active_keys    = ApiKeyRepository::count_active_keys();
$month_requests = ApiKeyRepository::get_month_total_requests();
?>

<style>
.wpmind-gw-stats{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.wpmind-gw-stat{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;flex:1;min-width:140px}
.wpmind-gw-stat-val{font-size:24px;font-weight:700;color:#1d2327}
.wpmind-gw-stat-lbl{font-size:12px;color:#646970;margin-top:4px}
.wpmind-gw-tabs{display:flex;gap:0;border-bottom:2px solid #e0e0e0;margin-bottom:20px}
.wpmind-gw-tab{padding:10px 20px;cursor:pointer;border:none;background:none;font-size:14px;color:#646970;border-bottom:2px solid transparent;margin-bottom:-2px}
.wpmind-gw-tab.active{color:#2271b1;border-bottom-color:#2271b1;font-weight:600}
.wpmind-gw-panel{display:none}.wpmind-gw-panel.active{display:block}
.wpmind-gw-form label{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f0}
.wpmind-gw-form .lbl{min-width:180px;font-weight:500}
.wpmind-gw-form input[type=number]{width:120px}
.wpmind-gw-btn{background:#2271b1;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px}
.wpmind-gw-btn:hover{background:#135e96}
.wpmind-gw-btn-danger{background:#d63638}.wpmind-gw-btn-danger:hover{background:#a02021}
.wpmind-gw-btn-sm{padding:4px 12px;font-size:12px}
.wpmind-gw-keys-table{width:100%;border-collapse:collapse;margin-top:12px}
</style>

<div class="wpmind-gw-stats">
	<div class="wpmind-gw-stat">
		<div class="wpmind-gw-stat-val"><?php echo $gateway_enabled ? '✓ ON' : '✗ OFF'; ?></div>
		<div class="wpmind-gw-stat-lbl"><?php esc_html_e( '网关状态', 'wpmind' ); ?></div>
	</div>
	<div class="wpmind-gw-stat">
		<div class="wpmind-gw-stat-val"><?php echo esc_html( (string) $total_keys ); ?></div>
		<div class="wpmind-gw-stat-lbl"><?php esc_html_e( 'API Keys', 'wpmind' ); ?></div>
	</div>
	<div class="wpmind-gw-stat">
		<div class="wpmind-gw-stat-val"><?php echo esc_html( (string) $active_keys ); ?></div>
		<div class="wpmind-gw-stat-lbl"><?php esc_html_e( '活跃 Keys', 'wpmind' ); ?></div>
	</div>
	<div class="wpmind-gw-stat">
		<div class="wpmind-gw-stat-val"><?php echo esc_html( number_format( $month_requests ) ); ?></div>
		<div class="wpmind-gw-stat-lbl"><?php esc_html_e( '本月请求', 'wpmind' ); ?></div>
	</div>
</div>

<div class="wpmind-gw-tabs">
	<button type="button" class="wpmind-gw-tab active" data-tab="settings"><?php esc_html_e( '基础设置', 'wpmind' ); ?></button>
	<button type="button" class="wpmind-gw-tab" data-tab="keys"><?php esc_html_e( 'API Key 管理', 'wpmind' ); ?></button>
</div>

<!-- Settings Panel -->
<div class="wpmind-gw-panel active" data-panel="settings">
	<div class="wpmind-gw-form" id="wpmind-gw-settings-form">
		<label>
			<span class="lbl"><?php esc_html_e( '启用网关', 'wpmind' ); ?></span>
			<input type="checkbox" id="gw-enabled" value="1" <?php checked( $gateway_enabled ); ?>>
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( '默认 RPM 限制', 'wpmind' ); ?></span>
			<input type="number" id="gw-rpm" value="<?php echo esc_attr( (string) $default_rpm ); ?>" min="1" max="10000">
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( '默认 TPM 限制', 'wpmind' ); ?></span>
			<input type="number" id="gw-tpm" value="<?php echo esc_attr( (string) $default_tpm ); ?>" min="1000" max="10000000">
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( 'SSE 全局并发', 'wpmind' ); ?></span>
			<input type="number" id="gw-sse" value="<?php echo esc_attr( (string) $sse_global_limit ); ?>" min="1" max="200">
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( '请求体上限 (MB)', 'wpmind' ); ?></span>
			<input type="number" id="gw-body-mb" value="<?php echo esc_attr( (string) $max_body_mb ); ?>" min="1" max="100">
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( 'Max Tokens 上限', 'wpmind' ); ?></span>
			<input type="number" id="gw-tokens-cap" value="<?php echo esc_attr( (string) $max_tokens_cap ); ?>" min="256" max="1000000">
		</label>
		<label>
			<span class="lbl"><?php esc_html_e( '记录 Prompt 内容', 'wpmind' ); ?></span>
			<input type="checkbox" id="gw-log-prompts" value="1" <?php checked( $log_prompts ); ?>>
		</label>
		<div style="padding:16px 0">
			<button type="button" class="wpmind-gw-btn" id="gw-save-settings"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
			<span id="gw-save-msg" style="margin-left:12px;color:#00a32a;display:none"></span>
		</div>
	</div>
</div>

<!-- API Keys Panel -->
<div class="wpmind-gw-panel" data-panel="keys">
	<div style="margin-bottom:16px">
		<button type="button" class="wpmind-gw-btn" id="gw-show-create"><?php esc_html_e( '+ 创建 API Key', 'wpmind' ); ?></button>
	</div>

	<!-- Create Key Form (hidden by default) -->
	<div id="gw-create-form" style="display:none;background:#f6f7f7;padding:16px;border-radius:6px;margin-bottom:16px">
		<h4 style="margin:0 0 12px"><?php esc_html_e( '创建新 API Key', 'wpmind' ); ?></h4>
		<div class="wpmind-gw-form">
			<label><span class="lbl"><?php esc_html_e( '名称', 'wpmind' ); ?></span><input type="text" id="ck-name" placeholder="My App" style="width:200px"></label>
			<label><span class="lbl">RPM</span><input type="number" id="ck-rpm" value="<?php echo esc_attr( (string) $default_rpm ); ?>" min="1"></label>
			<label><span class="lbl">TPM</span><input type="number" id="ck-tpm" value="<?php echo esc_attr( (string) $default_tpm ); ?>" min="1000"></label>
			<label><span class="lbl"><?php esc_html_e( '并发限制', 'wpmind' ); ?></span><input type="number" id="ck-concurrency" value="2" min="1" max="100"></label>
			<label><span class="lbl"><?php esc_html_e( '月预算 (USD)', 'wpmind' ); ?></span><input type="number" id="ck-budget" value="0" min="0" step="0.01"></label>
			<label><span class="lbl"><?php esc_html_e( 'IP 白名单', 'wpmind' ); ?></span><input type="text" id="ck-ips" placeholder="1.2.3.4, 5.6.7.8" style="width:200px"></label>
			<label><span class="lbl"><?php esc_html_e( '过期时间', 'wpmind' ); ?></span><input type="date" id="ck-expires"></label>
		</div>
		<div style="padding:12px 0;display:flex;gap:8px">
			<button type="button" class="wpmind-gw-btn" id="gw-create-key"><?php esc_html_e( '创建', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-gw-btn" style="background:#646970" id="gw-cancel-create"><?php esc_html_e( '取消', 'wpmind' ); ?></button>
		</div>
	</div>

	<!-- New Key Display (hidden) -->
	<div id="gw-new-key-box" style="display:none;background:#e7f5e7;border:1px solid #00a32a;padding:16px;border-radius:6px;margin-bottom:16px">
		<strong><?php esc_html_e( 'API Key 创建成功！请立即复制，此 Key 不会再次显示。', 'wpmind' ); ?></strong>
		<div style="margin-top:8px;display:flex;gap:8px;align-items:center">
			<code id="gw-raw-key" style="padding:8px 12px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:13px;word-break:break-all;flex:1"></code>
			<button type="button" class="wpmind-gw-btn wpmind-gw-btn-sm" id="gw-copy-key"><?php esc_html_e( '复制', 'wpmind' ); ?></button>
		</div>
	</div>

	<!-- Keys Table -->
	<table class="wpmind-gw-keys-table widefat striped">
		<thead>
			<tr>
				<th>Key Prefix</th>
				<th><?php esc_html_e( '名称', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
				<th>RPM/TPM</th>
				<th><?php esc_html_e( '本月请求', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '最后使用', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
			</tr>
		</thead>
		<tbody id="gw-keys-tbody">
			<tr><td colspan="7" style="text-align:center;color:#646970"><?php esc_html_e( '加载中...', 'wpmind' ); ?></td></tr>
		</tbody>
	</table>
</div>

<script>
( function( $ ) {
	'use strict';
	var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce   = '<?php echo esc_js( wp_create_nonce( 'wpmind_ajax' ) ); ?>';

	// Tab switching.
	$( '.wpmind-gw-tab' ).on( 'click', function() {
		var tab = $( this ).data( 'tab' );
		$( '.wpmind-gw-tab' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.wpmind-gw-panel' ).removeClass( 'active' );
		$( '[data-panel="' + tab + '"]' ).addClass( 'active' );
		if ( tab === 'keys' ) loadKeys();
	} );

	// Save settings.
	$( '#gw-save-settings' ).on( 'click', function() {
		var $btn = $( this ), $msg = $( '#gw-save-msg' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, {
			action: 'wpmind_save_gateway_settings',
			nonce: nonce,
			gateway_enabled: $( '#gw-enabled' ).is( ':checked' ) ? '1' : '',
			sse_global_limit: $( '#gw-sse' ).val(),
			default_rpm: $( '#gw-rpm' ).val(),
			default_tpm: $( '#gw-tpm' ).val(),
			max_body_bytes: String( parseInt( $( '#gw-body-mb' ).val() || '10' ) * 1048576 ),
			max_tokens_cap: $( '#gw-tokens-cap' ).val(),
			log_prompts: $( '#gw-log-prompts' ).is( ':checked' ) ? '1' : ''
		}, function( r ) {
			$msg.text( ( r.data && r.data.message ) || '保存失败' ).show();
			setTimeout( function() { $msg.fadeOut(); }, 3000 );
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	// Show/hide create form.
	$( '#gw-show-create' ).on( 'click', function() { $( '#gw-create-form' ).slideDown(); } );
	$( '#gw-cancel-create' ).on( 'click', function() { $( '#gw-create-form' ).slideUp(); } );

	// Create key.
	$( '#gw-create-key' ).on( 'click', function() {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, {
			action: 'wpmind_create_api_key',
			nonce: nonce,
			name: $( '#ck-name' ).val(),
			rpm_limit: $( '#ck-rpm' ).val(),
			tpm_limit: $( '#ck-tpm' ).val(),
			concurrency_limit: $( '#ck-concurrency' ).val(),
			monthly_budget_usd: $( '#ck-budget' ).val(),
			ip_whitelist: $( '#ck-ips' ).val(),
			expires_at: $( '#ck-expires' ).val()
		}, function( r ) {
			if ( r.success ) {
				$( '#gw-raw-key' ).text( r.data.raw_key );
				$( '#gw-new-key-box' ).slideDown();
				$( '#gw-create-form' ).slideUp();
				$( '#ck-name' ).val( '' );
				loadKeys();
			} else {
				alert( ( r.data && r.data.message ) || '创建失败' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	// Copy key.
	$( '#gw-copy-key' ).on( 'click', function() {
		var text = $( '#gw-raw-key' ).text();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( function() {
				$( '#gw-copy-key' ).text( '<?php echo esc_js( __( '已复制', 'wpmind' ) ); ?>' );
				setTimeout( function() { $( '#gw-copy-key' ).text( '<?php echo esc_js( __( '复制', 'wpmind' ) ); ?>' ); }, 2000 );
			} );
		}
	} );

	// Load keys.
	function loadKeys() {
		$.post( ajaxurl, { action: 'wpmind_list_api_keys', nonce: nonce }, function( r ) {
			var $tb = $( '#gw-keys-tbody' );
			$tb.empty();
			if ( ! r.success || ! r.data || ! r.data.keys || ! r.data.keys.length ) {
				$tb.html( '<tr><td colspan="7" style="text-align:center;color:#646970"><?php echo esc_js( __( '暂无 API Key', 'wpmind' ) ); ?></td></tr>' );
				return;
			}
			$.each( r.data.keys, function( i, k ) {
				var statusBadge = k.status === 'active'
					? '<span style="color:#00a32a">Active</span>'
					: '<span style="color:#d63638">' + k.status + '</span>';
				var lastUsed = k.last_used_at || '-';
				var actions = k.status === 'active'
					? '<button class="wpmind-gw-btn wpmind-gw-btn-sm wpmind-gw-btn-danger gw-revoke" data-kid="' + k.key_id + '"><?php echo esc_js( __( '吊销', 'wpmind' ) ); ?></button>'
					: '-';
				$tb.append(
					'<tr>' +
					'<td><code>sk_mind_' + k.key_prefix + '...</code></td>' +
					'<td>' + ( k.name || '-' ) + '</td>' +
					'<td>' + statusBadge + '</td>' +
					'<td>' + k.rpm_limit + ' / ' + k.tpm_limit + '</td>' +
					'<td>' + ( k.usage_request_count || 0 ) + '</td>' +
					'<td>' + lastUsed + '</td>' +
					'<td>' + actions + '</td>' +
					'</tr>'
				);
			} );
		} );
	}

	// Revoke key.
	$( document ).on( 'click', '.gw-revoke', function() {
		if ( ! confirm( '<?php echo esc_js( __( '确定要吊销此 API Key？此操作不可撤销。', 'wpmind' ) ); ?>' ) ) return;
		var $btn = $( this ), kid = $btn.data( 'kid' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, { action: 'wpmind_revoke_api_key', nonce: nonce, key_id: kid }, function( r ) {
			if ( r.success ) { loadKeys(); } else { alert( ( r.data && r.data.message ) || '吊销失败' ); }
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	// Auto-load keys when keys tab is first shown.
	loadKeys();
} )( jQuery );
</script>
