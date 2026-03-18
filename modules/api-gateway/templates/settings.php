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

$base_url = rest_url( 'mind/v1' );
?>

<!-- Header -->
<div class="wpmind-module-header">
	<h2 class="wpmind-module-title">
		<span class="dashicons ri-server-line"></span>
		<?php esc_html_e( 'API Gateway', 'wpmind' ); ?>
	</h2>
	<span class="wpmind-module-badge"><?php echo $gateway_enabled ? 'ON' : 'OFF'; ?></span>
</div>

<div class="wpmind-tab-pane-body">
<p class="wpmind-module-desc">
	<?php esc_html_e( 'OpenAI 兼容的 API 网关，提供 Bearer 鉴权、速率限制、预算控制和 SSE 流式输出。', 'wpmind' ); ?>
</p>

<!-- Stat Cards -->
<div class="wpmind-gw-stats">
	<div class="wpmind-stat-card">
		<div class="wpmind-stat-icon">
			<span class="dashicons ri-key-2-line"></span>
		</div>
		<div class="wpmind-stat-content">
			<div class="wpmind-stat-value"><?php echo esc_html( (string) $total_keys ); ?></div>
			<div class="wpmind-stat-label"><?php esc_html_e( 'API Keys', 'wpmind' ); ?></div>
		</div>
	</div>
	<div class="wpmind-stat-card">
		<div class="wpmind-stat-icon">
			<span class="dashicons ri-checkbox-circle-line"></span>
		</div>
		<div class="wpmind-stat-content">
			<div class="wpmind-stat-value"><?php echo esc_html( (string) $active_keys ); ?></div>
			<div class="wpmind-stat-label"><?php esc_html_e( '活跃 Keys', 'wpmind' ); ?></div>
		</div>
	</div>
	<div class="wpmind-stat-card">
		<div class="wpmind-stat-icon">
			<span class="dashicons ri-bar-chart-line"></span>
		</div>
		<div class="wpmind-stat-content">
			<div class="wpmind-stat-value"><?php echo esc_html( number_format( $month_requests ) ); ?></div>
			<div class="wpmind-stat-label"><?php esc_html_e( '本月请求', 'wpmind' ); ?></div>
		</div>
	</div>
	<div class="wpmind-stat-card">
		<div class="wpmind-stat-icon">
			<span class="dashicons ri-speed-line"></span>
		</div>
		<div class="wpmind-stat-content">
			<div class="wpmind-stat-value"><?php echo esc_html( (string) $default_rpm ); ?></div>
			<div class="wpmind-stat-label"><?php esc_html_e( '默认 RPM', 'wpmind' ); ?></div>
		</div>
	</div>
</div>

<!-- Sub-tab Navigation -->
<div class="wpmind-gw-subtabs">
	<button type="button" class="wpmind-gw-subtab active" data-tab="settings">
		<span class="dashicons ri-settings-3-line"></span>
		<?php esc_html_e( '基础设置', 'wpmind' ); ?>
	</button>
	<button type="button" class="wpmind-gw-subtab" data-tab="keys">
		<span class="dashicons ri-key-2-line"></span>
		<?php esc_html_e( 'API Key 管理', 'wpmind' ); ?>
	</button>
	<button type="button" class="wpmind-gw-subtab" data-tab="docs">
		<span class="dashicons ri-book-open-line"></span>
		<?php esc_html_e( '接入文档', 'wpmind' ); ?>
	</button>
	<button type="button" class="wpmind-gw-subtab" data-tab="logs">
		<span class="dashicons ri-file-list-3-line"></span>
		<?php esc_html_e( '请求日志', 'wpmind' ); ?>
	</button>
</div>

<!-- Panel 1: Settings -->
<div class="wpmind-gw-panel active" data-panel="settings">
	<table class="wpmind-gw-form-table">
		<tr>
			<th><?php esc_html_e( '启用网关', 'wpmind' ); ?></th>
			<td><input type="checkbox" id="gw-enabled" value="1" <?php checked( $gateway_enabled ); ?>></td>
		</tr>
		<tr>
			<th><?php esc_html_e( '默认 RPM 限制', 'wpmind' ); ?></th>
			<td><input type="number" id="gw-rpm" value="<?php echo esc_attr( (string) $default_rpm ); ?>" min="1" max="10000"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( '默认 TPM 限制', 'wpmind' ); ?></th>
			<td><input type="number" id="gw-tpm" value="<?php echo esc_attr( (string) $default_tpm ); ?>" min="1000" max="10000000"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'SSE 全局并发', 'wpmind' ); ?></th>
			<td><input type="number" id="gw-sse" value="<?php echo esc_attr( (string) $sse_global_limit ); ?>" min="1" max="200"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( '请求体上限 (MB)', 'wpmind' ); ?></th>
			<td><input type="number" id="gw-body-mb" value="<?php echo esc_attr( (string) $max_body_mb ); ?>" min="1" max="100"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Max Tokens 上限', 'wpmind' ); ?></th>
			<td><input type="number" id="gw-tokens-cap" value="<?php echo esc_attr( (string) $max_tokens_cap ); ?>" min="256" max="1000000"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( '记录 Prompt 内容', 'wpmind' ); ?></th>
			<td><input type="checkbox" id="gw-log-prompts" value="1" <?php checked( $log_prompts ); ?>></td>
		</tr>
	</table>
	<div style="padding:var(--wpmind-space-4) 0">
		<button type="button" class="wpmind-gw-btn" id="gw-save-settings"><?php esc_html_e( '保存设置', 'wpmind' ); ?></button>
		<span id="gw-save-msg" class="wpmind-gw-save-msg" style="display:none"></span>
	</div>
</div>

<!-- Panel 2: API Key Management -->
<div class="wpmind-gw-panel" data-panel="keys">
	<div style="margin-bottom:var(--wpmind-space-4)">
		<button type="button" class="wpmind-gw-btn" id="gw-show-create">
			<span class="dashicons ri-add-line" style="font-size:16px;width:16px;height:16px"></span>
			<?php esc_html_e( '创建 API Key', 'wpmind' ); ?>
		</button>
	</div>

	<!-- Create Key Form -->
	<div id="gw-create-form" class="wpmind-gw-create-panel" style="display:none">
		<h4><?php esc_html_e( '创建新 API Key', 'wpmind' ); ?></h4>
		<div class="wpmind-gw-edit-grid">
			<div class="wpmind-gw-edit-field">
				<label><?php esc_html_e( '名称', 'wpmind' ); ?></label>
				<input type="text" id="ck-name" placeholder="My App">
			</div>
			<div class="wpmind-gw-edit-field">
				<label>RPM</label>
				<input type="number" id="ck-rpm" value="<?php echo esc_attr( (string) $default_rpm ); ?>" min="1">
			</div>
			<div class="wpmind-gw-edit-field">
				<label>TPM</label>
				<input type="number" id="ck-tpm" value="<?php echo esc_attr( (string) $default_tpm ); ?>" min="1000">
			</div>
			<div class="wpmind-gw-edit-field">
				<label><?php esc_html_e( '并发限制', 'wpmind' ); ?></label>
				<input type="number" id="ck-concurrency" value="2" min="1" max="100">
			</div>
			<div class="wpmind-gw-edit-field">
				<label><?php esc_html_e( '月预算 (USD)', 'wpmind' ); ?></label>
				<input type="number" id="ck-budget" value="0" min="0" step="0.01">
			</div>
			<div class="wpmind-gw-edit-field">
				<label><?php esc_html_e( 'IP 白名单', 'wpmind' ); ?></label>
				<input type="text" id="ck-ips" placeholder="1.2.3.4, 5.6.7.8">
			</div>
			<div class="wpmind-gw-edit-field">
				<label><?php esc_html_e( '过期时间', 'wpmind' ); ?></label>
				<input type="date" id="ck-expires">
			</div>
		</div>
		<div class="wpmind-gw-edit-actions">
			<button type="button" class="wpmind-gw-btn" id="gw-create-key"><?php esc_html_e( '创建', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-gw-btn wpmind-gw-btn-secondary" id="gw-cancel-create"><?php esc_html_e( '取消', 'wpmind' ); ?></button>
		</div>
	</div>

	<!-- New Key Display -->
	<div id="gw-new-key-box" class="wpmind-gw-key-success" style="display:none">
		<strong><?php esc_html_e( 'API Key 创建成功！请立即复制，此 Key 不会再次显示。', 'wpmind' ); ?></strong>
		<div class="wpmind-gw-key-display">
			<code id="gw-raw-key"></code>
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
				<th>RPM / TPM</th>
				<th><?php esc_html_e( '本月请求', 'wpmind' ); ?></th>
				<th>Tokens</th>
				<th><?php esc_html_e( '最后使用', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
			</tr>
		</thead>
		<tbody id="gw-keys-tbody">
			<tr><td colspan="8" style="text-align:center;color:var(--wpmind-gray-400)"><?php esc_html_e( '加载中...', 'wpmind' ); ?></td></tr>
		</tbody>
	</table>
</div>

<!-- Panel 3: API Docs -->
<div class="wpmind-gw-panel" data-panel="docs">
	<div class="wpmind-gw-docs-section">
		<h4>Base URL</h4>
		<div class="wpmind-gw-code-block">
			<pre><?php echo esc_html( $base_url ); ?></pre>
			<button type="button" class="wpmind-gw-copy-btn" data-copy="<?php echo esc_attr( $base_url ); ?>"><?php esc_html_e( '复制', 'wpmind' ); ?></button>
		</div>
	</div>

	<div class="wpmind-gw-docs-section">
		<h4><?php esc_html_e( '可用端点', 'wpmind' ); ?></h4>
		<table class="wpmind-gw-endpoint-list widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( '方法', 'wpmind' ); ?></th>
					<th><?php esc_html_e( '路径', 'wpmind' ); ?></th>
					<th><?php esc_html_e( '说明', 'wpmind' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>POST</code></td>
					<td><code>/chat/completions</code></td>
					<td><?php esc_html_e( '聊天补全（兼容 OpenAI）', 'wpmind' ); ?></td>
				</tr>
				<tr>
					<td><code>GET</code></td>
					<td><code>/models</code></td>
					<td><?php esc_html_e( '列出可用模型', 'wpmind' ); ?></td>
				</tr>
				<tr>
					<td><code>POST</code></td>
					<td><code>/embeddings</code></td>
					<td><?php esc_html_e( '文本向量化', 'wpmind' ); ?></td>
				</tr>
				<tr>
					<td><code>POST</code></td>
					<td><code>/responses</code></td>
					<td><?php esc_html_e( 'Responses API（兼容 OpenAI）', 'wpmind' ); ?></td>
				</tr>
				<tr>
					<td><code>GET</code></td>
					<td><code>/status</code></td>
					<td><?php esc_html_e( '网关状态检查', 'wpmind' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="wpmind-gw-docs-section">
		<h4><?php esc_html_e( 'curl 示例', 'wpmind' ); ?></h4>
		<div class="wpmind-gw-code-block">
			<pre>curl <?php echo esc_html( $base_url ); ?>/chat/completions \
	-H "Authorization: Bearer sk_mind_YOUR_KEY" \
	-H "Content-Type: application/json" \
	-d '{
	"model": "deepseek-chat",
	"messages": [{"role": "user", "content": "Hello"}],
	"stream": true
}'</pre>
			<button type="button" class="wpmind-gw-copy-btn" data-copy-pre="1"><?php esc_html_e( '复制', 'wpmind' ); ?></button>
		</div>
	</div>

	<div class="wpmind-gw-docs-section">
		<h4><?php esc_html_e( '认证方式', 'wpmind' ); ?></h4>
		<p style="color:var(--wpmind-gray-600);font-size:var(--wpmind-text-base)">
			<?php esc_html_e( '在请求头中添加 Authorization: Bearer sk_mind_YOUR_KEY。所有端点均需要有效的 API Key 认证。', 'wpmind' ); ?>
		</p>
	</div>
</div>

<!-- Panel 4: Audit Logs -->
<div class="wpmind-gw-panel" data-panel="logs">
	<div class="wpmind-gw-log-filters">
		<div class="wpmind-gw-filter-group">
			<label>Key</label>
			<select id="log-filter-key">
				<option value=""><?php esc_html_e( '全部', 'wpmind' ); ?></option>
			</select>
		</div>
		<div class="wpmind-gw-filter-group">
			<label><?php esc_html_e( '事件类型', 'wpmind' ); ?></label>
			<select id="log-filter-event">
				<option value=""><?php esc_html_e( '全部', 'wpmind' ); ?></option>
				<option value="api_request">api_request</option>
				<option value="api_stream_request">api_stream_request</option>
				<option value="key_created">key_created</option>
				<option value="key_revoked">key_revoked</option>
			</select>
		</div>
		<div class="wpmind-gw-filter-group">
			<label><?php esc_html_e( '开始日期', 'wpmind' ); ?></label>
			<input type="date" id="log-filter-from">
		</div>
		<div class="wpmind-gw-filter-group">
			<label><?php esc_html_e( '结束日期', 'wpmind' ); ?></label>
			<input type="date" id="log-filter-to">
		</div>
		<div class="wpmind-gw-filter-group">
			<button type="button" class="wpmind-gw-btn wpmind-gw-btn-sm" id="log-filter-apply"><?php esc_html_e( '筛选', 'wpmind' ); ?></button>
		</div>
	</div>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( '时间', 'wpmind' ); ?></th>
				<th>Key</th>
				<th><?php esc_html_e( '事件', 'wpmind' ); ?></th>
				<th><?php esc_html_e( '详情', 'wpmind' ); ?></th>
			</tr>
		</thead>
		<tbody id="gw-logs-tbody">
			<tr><td colspan="4" style="text-align:center;color:var(--wpmind-gray-400)"><?php esc_html_e( '点击"筛选"加载日志', 'wpmind' ); ?></td></tr>
		</tbody>
	</table>

	<div class="wpmind-gw-pagination" id="gw-logs-pagination" style="display:none">
		<span id="gw-logs-info"></span>
		<div class="wpmind-gw-pagination-btns">
			<button type="button" class="wpmind-gw-btn wpmind-gw-btn-sm wpmind-gw-btn-secondary" id="log-prev" disabled><?php esc_html_e( '上一页', 'wpmind' ); ?></button>
			<button type="button" class="wpmind-gw-btn wpmind-gw-btn-sm wpmind-gw-btn-secondary" id="log-next" disabled><?php esc_html_e( '下一页', 'wpmind' ); ?></button>
		</div>
	</div>
</div>
</div>

<script>
( function( $ ) {
	'use strict';
	var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce   = '<?php echo esc_js( wp_create_nonce( 'wpmind_ajax' ) ); ?>';
	var logPage = 1;

	/* ---- Tab switching ---- */
	$( '.wpmind-gw-subtab' ).on( 'click', function() {
		var tab = $( this ).data( 'tab' );
		$( '.wpmind-gw-subtab' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.wpmind-gw-panel' ).removeClass( 'active' );
		$( '[data-panel="' + tab + '"]' ).addClass( 'active' );
		if ( tab === 'keys' ) loadKeys();
	} );

	/* ---- Save settings ---- */
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
			$msg.text( ( r.data && r.data.message ) || '<?php echo esc_js( __( '保存失败', 'wpmind' ) ); ?>' ).show();
			setTimeout( function() { $msg.fadeOut(); }, 3000 );
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Create key form toggle ---- */
	$( '#gw-show-create' ).on( 'click', function() { $( '#gw-create-form' ).slideDown(); } );
	$( '#gw-cancel-create' ).on( 'click', function() { $( '#gw-create-form' ).slideUp(); } );

	/* ---- Create key ---- */
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
				alert( ( r.data && r.data.message ) || '<?php echo esc_js( __( '创建失败', 'wpmind' ) ); ?>' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Copy key ---- */
	$( '#gw-copy-key' ).on( 'click', function() {
		copyText( $( '#gw-raw-key' ).text(), $( this ) );
	} );

	/* ---- Load keys ---- */
	function loadKeys() {
		$.post( ajaxurl, { action: 'wpmind_list_api_keys', nonce: nonce }, function( r ) {
			var $tb = $( '#gw-keys-tbody' );
			$tb.empty();
			if ( ! r.success || ! r.data || ! r.data.keys || ! r.data.keys.length ) {
				$tb.html( '<tr><td colspan="8" style="text-align:center;color:var(--wpmind-gray-400)"><?php echo esc_js( __( '暂无 API Key', 'wpmind' ) ); ?></td></tr>' );
				return;
			}
			// Also populate log filter dropdown.
			var $sel = $( '#log-filter-key' );
			$sel.find( 'option:gt(0)' ).remove();
			$.each( r.data.keys, function( i, k ) {
				$sel.append( '<option value="' + k.key_id + '">sk_mind_' + k.key_prefix + '... (' + ( k.name || '-' ) + ')</option>' );
				var badge = k.status === 'active'
					? '<span class="wpmind-gw-badge wpmind-gw-badge-active">Active</span>'
					: '<span class="wpmind-gw-badge wpmind-gw-badge-revoked">' + k.status + '</span>';
				var lastUsed = k.last_used_at || '-';
				var actions = '';
				if ( k.status === 'active' ) {
					actions = '<button class="wpmind-gw-btn wpmind-gw-btn-sm gw-edit-key" data-kid="' + k.key_id + '"><?php echo esc_js( __( '编辑', 'wpmind' ) ); ?></button> ';
					actions += '<button class="wpmind-gw-btn wpmind-gw-btn-sm wpmind-gw-btn-danger gw-revoke" data-kid="' + k.key_id + '"><?php echo esc_js( __( '吊销', 'wpmind' ) ); ?></button>';
				} else {
					actions = '-';
				}
				var ipw = '';
				try { ipw = k.ip_whitelist ? ( typeof k.ip_whitelist === 'string' ? ( k.ip_whitelist.charAt(0) === '[' ? JSON.parse( k.ip_whitelist ).join( ', ' ) : k.ip_whitelist ) : '' ) : ''; } catch(e) { ipw = k.ip_whitelist || ''; }
				$tb.append(
					'<tr data-key-id="' + k.key_id + '">' +
					'<td><code>sk_mind_' + k.key_prefix + '...</code></td>' +
					'<td>' + ( k.name || '-' ) + '</td>' +
					'<td>' + badge + '</td>' +
					'<td>' + k.rpm_limit + ' / ' + k.tpm_limit + '</td>' +
					'<td>' + ( k.usage_request_count || 0 ) + '</td>' +
					'<td>' + Number( k.usage_total_tokens || 0 ).toLocaleString() + '</td>' +
					'<td>' + lastUsed + '</td>' +
					'<td>' + actions + '</td>' +
					'</tr>'
				);
				// Inline edit row.
				if ( k.status === 'active' ) {
					$tb.append(
						'<tr class="wpmind-gw-edit-row" data-edit-for="' + k.key_id + '" style="display:none"><td colspan="8">' +
						'<div class="wpmind-gw-edit-panel">' +
						'<div class="wpmind-gw-edit-grid">' +
						editField( '<?php echo esc_js( __( '名称', 'wpmind' ) ); ?>', 'text', 'ek-name-' + k.key_id, k.name || '' ) +
						editField( 'RPM', 'number', 'ek-rpm-' + k.key_id, k.rpm_limit ) +
						editField( 'TPM', 'number', 'ek-tpm-' + k.key_id, k.tpm_limit ) +
						editField( '<?php echo esc_js( __( '并发', 'wpmind' ) ); ?>', 'number', 'ek-conc-' + k.key_id, k.concurrency_limit ) +
						editField( '<?php echo esc_js( __( '月预算 (USD)', 'wpmind' ) ); ?>', 'number', 'ek-budget-' + k.key_id, k.monthly_budget_usd || 0 ) +
						editField( 'IP <?php echo esc_js( __( '白名单', 'wpmind' ) ); ?>', 'text', 'ek-ips-' + k.key_id, ipw ) +
						editField( '<?php echo esc_js( __( '过期时间', 'wpmind' ) ); ?>', 'date', 'ek-exp-' + k.key_id, k.expires_at ? k.expires_at.substring( 0, 10 ) : '' ) +
						'</div>' +
						'<div class="wpmind-gw-edit-actions">' +
						'<button class="wpmind-gw-btn wpmind-gw-btn-sm gw-save-edit" data-kid="' + k.key_id + '"><?php echo esc_js( __( '保存', 'wpmind' ) ); ?></button> ' +
						'<button class="wpmind-gw-btn wpmind-gw-btn-sm wpmind-gw-btn-secondary gw-cancel-edit" data-kid="' + k.key_id + '"><?php echo esc_js( __( '取消', 'wpmind' ) ); ?></button>' +
						'</div></div></td></tr>'
					);
				}
			} );
		} );
	}

	function editField( label, type, id, val ) {
		var step = type === 'number' ? ' step="any"' : '';
		return '<div class="wpmind-gw-edit-field"><label>' + label + '</label><input type="' + type + '" id="' + id + '" value="' + val + '"' + step + '></div>';
	}

	/* ---- Toggle inline edit ---- */
	$( document ).on( 'click', '.gw-edit-key', function() {
		var kid = $( this ).data( 'kid' );
		var $row = $( '[data-edit-for="' + kid + '"]' );
		$( '.wpmind-gw-edit-row' ).not( $row ).slideUp();
		$row.slideToggle();
	} );

	$( document ).on( 'click', '.gw-cancel-edit', function() {
		$( '[data-edit-for="' + $( this ).data( 'kid' ) + '"]' ).slideUp();
	} );

	/* ---- Save edit ---- */
	$( document ).on( 'click', '.gw-save-edit', function() {
		var $btn = $( this ), kid = $btn.data( 'kid' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, {
			action: 'wpmind_update_api_key',
			nonce: nonce,
			key_id: kid,
			name: $( '#ek-name-' + kid ).val(),
			rpm_limit: $( '#ek-rpm-' + kid ).val(),
			tpm_limit: $( '#ek-tpm-' + kid ).val(),
			concurrency_limit: $( '#ek-conc-' + kid ).val(),
			monthly_budget_usd: $( '#ek-budget-' + kid ).val(),
			ip_whitelist: $( '#ek-ips-' + kid ).val(),
			expires_at: $( '#ek-exp-' + kid ).val()
		}, function( r ) {
			if ( r.success ) {
				loadKeys();
			} else {
				alert( ( r.data && r.data.message ) || '<?php echo esc_js( __( '更新失败', 'wpmind' ) ); ?>' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Revoke key ---- */
	$( document ).on( 'click', '.gw-revoke', function() {
		if ( ! confirm( '<?php echo esc_js( __( '确定要吊销此 API Key？此操作不可撤销。', 'wpmind' ) ); ?>' ) ) return;
		var $btn = $( this ), kid = $btn.data( 'kid' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, { action: 'wpmind_revoke_api_key', nonce: nonce, key_id: kid }, function( r ) {
			if ( r.success ) { loadKeys(); } else { alert( ( r.data && r.data.message ) || '<?php echo esc_js( __( '吊销失败', 'wpmind' ) ); ?>' ); }
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Audit logs ---- */
	$( '#log-filter-apply' ).on( 'click', function() { logPage = 1; loadLogs(); } );
	$( '#log-prev' ).on( 'click', function() { if ( logPage > 1 ) { logPage--; loadLogs(); } } );
	$( '#log-next' ).on( 'click', function() { logPage++; loadLogs(); } );

	function loadLogs() {
		var data = {
			action: 'wpmind_list_audit_logs',
			nonce: nonce,
			page: logPage,
			key_id: $( '#log-filter-key' ).val(),
			event_type: $( '#log-filter-event' ).val(),
			date_from: $( '#log-filter-from' ).val(),
			date_to: $( '#log-filter-to' ).val()
		};
		$.post( ajaxurl, data, function( r ) {
			var $tb = $( '#gw-logs-tbody' );
			$tb.empty();
			if ( ! r.success || ! r.data || ! r.data.logs || ! r.data.logs.length ) {
				$tb.html( '<tr><td colspan="4" style="text-align:center;color:var(--wpmind-gray-400)"><?php echo esc_js( __( '暂无日志', 'wpmind' ) ); ?></td></tr>' );
				$( '#gw-logs-pagination' ).hide();
				return;
			}
			$.each( r.data.logs, function( i, log ) {
				var detail = '';
				try {
					var d = JSON.parse( log.detail_json || '{}' );
					var parts = [];
					if ( d.model ) parts.push( d.model );
					if ( d.tokens_used ) parts.push( d.tokens_used + ' tokens' );
					if ( d.finish_reason ) parts.push( d.finish_reason );
					if ( d.status_code ) parts.push( 'HTTP ' + d.status_code );
					detail = parts.join( ' | ' );
				} catch(e) { detail = log.detail_json || ''; }
				$tb.append(
					'<tr>' +
					'<td>' + ( log.created_at || '-' ) + '</td>' +
					'<td><code>' + ( log.key_id || '-' ) + '</code></td>' +
					'<td>' + ( log.event_type || '-' ) + '</td>' +
					'<td>' + detail + '</td>' +
					'</tr>'
				);
			} );
			var d = r.data;
			$( '#gw-logs-pagination' ).show();
			$( '#gw-logs-info' ).text( '<?php echo esc_js( __( '共', 'wpmind' ) ); ?> ' + d.total + ' <?php echo esc_js( __( '条，第', 'wpmind' ) ); ?> ' + d.page + ' / ' + d.total_pages + ' <?php echo esc_js( __( '页', 'wpmind' ) ); ?>' );
			$( '#log-prev' ).prop( 'disabled', d.page <= 1 );
			$( '#log-next' ).prop( 'disabled', d.page >= d.total_pages );
		} );
	}

	/* ---- Copy helpers ---- */
	function copyText( text, $btn ) {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( function() {
				var orig = $btn.text();
				$btn.text( '<?php echo esc_js( __( '已复制', 'wpmind' ) ); ?>' );
				setTimeout( function() { $btn.text( orig ); }, 2000 );
			} );
		}
	}

	$( document ).on( 'click', '.wpmind-gw-copy-btn', function() {
		var $btn = $( this );
		var text = $btn.data( 'copy' ) || $btn.closest( '.wpmind-gw-code-block' ).find( 'pre' ).text();
		copyText( text, $btn );
	} );

	/* ---- Auto-load keys ---- */
	loadKeys();
} )( jQuery );
</script>