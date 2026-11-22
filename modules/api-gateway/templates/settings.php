<?php
/**
 * API Gateway 模块设置（kit 化）：URL 驱动 subnav + statbar + field--row + confirm_attrs。
 *
 * @package WPMind\Modules\ApiGateway
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use WPMind\Admin\AdminPage;
use WPMind\Modules\ApiGateway\Auth\ApiKeyRepository;

( static function (): void {
	$gateway_enabled  = get_option( 'wpmind_gateway_enabled', '0' ) === '1';
	$sse_global_limit = (int) get_option( 'wpmind_gateway_sse_global_limit', 20 );
	$default_rpm      = (int) get_option( 'wpmind_gateway_default_rpm', 60 );
	$default_tpm      = (int) get_option( 'wpmind_gateway_default_tpm', 100000 );
	$max_body_mb      = (int) round( (int) get_option( 'wpmind_gateway_max_body_bytes', 10485760 ) / 1048576 );
	$max_tokens_cap   = (int) get_option( 'wpmind_gateway_max_tokens_cap', 16384 );
	$log_prompts      = get_option( 'wpmind_gateway_log_prompts', '0' ) === '1';

	$total_keys     = ApiKeyRepository::count_keys();
	$active_keys    = ApiKeyRepository::count_active_keys();
	$month_requests = ApiKeyRepository::get_month_total_requests();

	$base_url = rest_url( 'mind/v1' );

	$sections = [
		'settings' => __( '基础设置', 'wpmind' ),
		'keys'     => __( 'API Key 管理', 'wpmind' ),
		'docs'     => __( '接入文档', 'wpmind' ),
		'logs'     => __( '请求日志', 'wpmind' ),
	];
	$section  = Wenpai_Admin_UI::section( $sections, 'settings' );

	$subnav_items = [];
	foreach ( $sections as $sec_id => $sec_label ) {
		$subnav_items[] = [
			'id'      => $sec_id,
			'label'   => $sec_label,
			'url'     => AdminPage::url(
				[
					'tab'     => 'module',
					'module'  => 'api-gateway',
					'section' => $sec_id,
				]
			),
			'current' => $section === $sec_id,
		];
	}

	$wpmind_switch = static function ( string $id, bool $checked, string $label, string $desc = '' ): void {
		$switch_html = Wenpai_Admin_Settings::switch_html(
			[
				'id'      => $id,
				'checked' => $checked,
				'on'      => __( '已开启', 'wpmind' ),
				'off'     => __( '未开启', 'wpmind' ),
			]
		);
		echo '<div class="wenpai-field wenpai-field--toggle wenpai-field--row field">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl">'
		. $switch_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. ( '' !== $desc ? '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>' : '' )
		. '</div></div>';
	};

	$wpmind_num = static function ( string $id, int $value, int $min, int $max, string $label ): void {
		echo '<div class="wenpai-field wenpai-field--row field">'
		. '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>'
		. '<div class="field-ctl"><input type="number" class="wpmind-num" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '"></div></div>';
	};

	Wenpai_Admin_UI::subnav(
		[
			'items'   => $subnav_items,
			'current' => $section,
			'label'   => __( 'API 网关分区', 'wpmind' ),
		]
	);

	Wenpai_Admin_UI::card_open();
	Wenpai_Admin_UI::statbar(
		[
			'items' => [
				[
					'label' => __( '密钥总数', 'wpmind' ),
					'value' => (string) $total_keys,
				],
				[
					'label' => __( '活跃密钥', 'wpmind' ),
					'value' => (string) $active_keys,
				],
				[
					'label' => __( '本月请求', 'wpmind' ),
					'value' => number_format( $month_requests ),
				],
				[
					'label' => __( '默认速率上限', 'wpmind' ),
					'value' => $default_rpm . ' / 分',
				],
			],
		]
	);
	Wenpai_Admin_UI::card_close();
	?>

<div class="wenpai-stack">
	<!-- Panel 1: Settings -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'settings', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( '网关设置', 'wpmind' ),
				'sub'               => __( 'OpenAI 兼容接口：Bearer 鉴权、限流与 SSE 流式。', 'wpmind' ),
				'tile'              => false,
				'head_actions_html' => $gateway_enabled
					? Wenpai_Admin_UI::pill_html(
						[
							'text'  => __( '已开启', 'wpmind' ),
							'state' => 'ok',
						]
					)
					: '',
			]
		);
		$wpmind_switch( 'gw-enabled', $gateway_enabled, __( '启用网关', 'wpmind' ), __( '关闭后 REST 网关接口停止响应。', 'wpmind' ) );
		$wpmind_num( 'gw-rpm', $default_rpm, 1, 10000, __( '默认 RPM 限制', 'wpmind' ) );
		$wpmind_num( 'gw-tpm', $default_tpm, 1000, 10000000, __( '默认 TPM 限制', 'wpmind' ) );
		$wpmind_num( 'gw-sse', $sse_global_limit, 1, 200, __( 'SSE 全局并发', 'wpmind' ) );
		$wpmind_num( 'gw-body-mb', $max_body_mb, 1, 100, __( '请求体上限 (MB)', 'wpmind' ) );
		$wpmind_num( 'gw-tokens-cap', $max_tokens_cap, 256, 1000000, __( 'Max Tokens 上限', 'wpmind' ) );
		$wpmind_switch( 'gw-log-prompts', $log_prompts, __( '记录 Prompt 内容', 'wpmind' ), __( '仅在排查问题时开启，将原文记入审计日志。', 'wpmind' ) );
		?>
		<div class="card-foot">
			<span class="wenpai-actions">
				<button type="button" class="btn btn-primary" id="gw-save-settings"><?php esc_html_e( '保存网关设置', 'wpmind' ); ?></button>
				<span id="gw-save-msg" class="meta" hidden></span>
			</span>
		</div>
		<?php Wenpai_Admin_UI::card_close(); ?>
	</div>

	<!-- Panel 2: API Key Management -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'keys', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title'             => __( 'API Key 管理', 'wpmind' ),
				'sub'               => __( '创建、调整限额与吊销网关访问密钥。', 'wpmind' ),
				'tile'              => false,
				'head_control_html' => Wenpai_Admin_UI::button_html(
					[
						'label'   => __( '创建 API Key', 'wpmind' ),
						'variant' => 'primary',
						'size'    => 'sm',
						'attrs'   => [ 'id' => 'gw-show-create' ],
					]
				),
			]
		);
		?>
		<div id="gw-create-form" class="wpmind-gw__panel" hidden>
			<?php Wenpai_Admin_UI::section_title( [ 'title' => __( '创建新 API Key', 'wpmind' ) ] ); ?>
			<div class="wenpai-grid-auto">
				<div class="wpmind-gw__field">
					<label for="ck-name"><?php esc_html_e( '名称', 'wpmind' ); ?></label>
					<input type="text" id="ck-name" placeholder="My App">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-rpm">RPM</label>
					<input type="number" id="ck-rpm" value="<?php echo esc_attr( (string) $default_rpm ); ?>" min="1">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-tpm">TPM</label>
					<input type="number" id="ck-tpm" value="<?php echo esc_attr( (string) $default_tpm ); ?>" min="1000">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-concurrency"><?php esc_html_e( '并发限制', 'wpmind' ); ?></label>
					<input type="number" id="ck-concurrency" value="2" min="1" max="100">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-budget"><?php esc_html_e( '月预算 (USD)', 'wpmind' ); ?></label>
					<input type="number" id="ck-budget" value="0" min="0" step="0.01">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-ips"><?php esc_html_e( 'IP 白名单', 'wpmind' ); ?></label>
					<input type="text" id="ck-ips" placeholder="1.2.3.4, 5.6.7.8">
				</div>
				<div class="wpmind-gw__field">
					<label for="ck-expires"><?php esc_html_e( '过期时间', 'wpmind' ); ?></label>
					<input type="date" id="ck-expires">
				</div>
			</div>
			<div class="wenpai-actions wpmind-gw__actions">
				<button type="button" class="btn btn-sm btn-primary" id="gw-create-key"><?php esc_html_e( '确认创建', 'wpmind' ); ?></button>
				<button type="button" class="btn btn-sm btn-ghost" id="gw-cancel-create"><?php esc_html_e( '取消', 'wpmind' ); ?></button>
			</div>
		</div>

		<div id="gw-new-key-box" class="wpmind-gw__keybox" hidden>
			<p class="meta"><?php esc_html_e( 'API Key 已创建，请立即复制保存，页面刷新后不再显示完整密钥。', 'wpmind' ); ?></p>
			<div class="wenpai-row wenpai-row--tight">
				<code class="wenpai-code" id="gw-raw-key"></code>
				<button type="button" class="btn btn-sm btn-secondary" id="gw-copy-key"><?php esc_html_e( '复制密钥', 'wpmind' ); ?></button>
			</div>
		</div>

		<div class="wenpai-table-wrap">
			<table class="tbl">
				<thead>
					<tr>
						<th>Key Prefix</th>
						<th><?php esc_html_e( '名称', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '状态', 'wpmind' ); ?></th>
						<th>RPM / TPM</th>
						<th class="num"><?php esc_html_e( '本月请求', 'wpmind' ); ?></th>
						<th class="num">Tokens</th>
						<th><?php esc_html_e( '最后使用', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '操作', 'wpmind' ); ?></th>
					</tr>
				</thead>
				<tbody id="gw-keys-tbody">
					<tr><td colspan="8" class="meta"><?php esc_html_e( '正在读取 API Key 列表…', 'wpmind' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php Wenpai_Admin_UI::card_close(); ?>
	</div>

	<!-- Panel 3: API Docs -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'docs', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '接入文档', 'wpmind' ),
				'sub'   => __( 'OpenAI 兼容接口地址与调用示例。', 'wpmind' ),
				'tile'  => false,
			]
		);
		?>
		<div class="wenpai-stack wenpai-stack--sm">
			<?php
			Wenpai_Admin_UI::section_title( [ 'title' => 'Base URL' ] );
			$base_url_block = wpmind_code_block_html(
				[
					'code' => $base_url,
					'lang' => 'text',
					'copy' => true,
				]
			);
			echo $base_url_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			Wenpai_Admin_UI::section_title( [ 'title' => __( '可用端点', 'wpmind' ) ] );
			?>
			<div class="wenpai-table-wrap">
				<table class="tbl">
					<thead>
						<tr>
							<th><?php esc_html_e( '方法', 'wpmind' ); ?></th>
							<th><?php esc_html_e( '路径', 'wpmind' ); ?></th>
							<th><?php esc_html_e( '说明', 'wpmind' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code class="wenpai-code">POST</code></td><td><code class="wenpai-code">/chat/completions</code></td><td><?php esc_html_e( '对话补全（兼容 OpenAI）', 'wpmind' ); ?></td></tr>
						<tr><td><code class="wenpai-code">GET</code></td><td><code class="wenpai-code">/models</code></td><td><?php esc_html_e( '列出可用模型', 'wpmind' ); ?></td></tr>
						<tr><td><code class="wenpai-code">POST</code></td><td><code class="wenpai-code">/embeddings</code></td><td><?php esc_html_e( '文本向量化', 'wpmind' ); ?></td></tr>
						<tr><td><code class="wenpai-code">POST</code></td><td><code class="wenpai-code">/responses</code></td><td><?php esc_html_e( 'Responses 接口（兼容 OpenAI）', 'wpmind' ); ?></td></tr>
						<tr><td><code class="wenpai-code">GET</code></td><td><code class="wenpai-code">/status</code></td><td><?php esc_html_e( '网关健康状态', 'wpmind' ); ?></td></tr>
					</tbody>
				</table>
			</div>
			<?php
			Wenpai_Admin_UI::section_title( [ 'title' => __( 'curl 调用示例', 'wpmind' ) ] );
			$wpmind_curl = 'curl ' . $base_url . "/chat/completions \\\n"
				. "  -H \"Authorization: Bearer sk_mind_YOUR_KEY\" \\\n"
				. "  -H \"Content-Type: application/json\" \\\n"
				. "  -d '{\n"
				. "    \"model\": \"deepseek-chat\",\n"
				. "    \"messages\": [{\"role\": \"user\", \"content\": \"Hello\"}],\n"
				. "    \"stream\": true\n"
				. "  }'";
			$curl_block  = wpmind_code_block_html(
				[
					'code' => $wpmind_curl,
					'lang' => 'bash',
					'copy' => true,
				]
			);
			echo $curl_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<p class="meta"><?php esc_html_e( '认证方式：请求头携带 Authorization: Bearer sk_mind_YOUR_KEY。', 'wpmind' ); ?></p>
		</div>
		<?php Wenpai_Admin_UI::card_close(); ?>
	</div>

	<!-- Panel 4: Audit Logs -->
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'logs', $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			[
				'title' => __( '请求日志', 'wpmind' ),
				'sub'   => __( '按密钥、事件类型与日期筛选审计记录。', 'wpmind' ),
				'tile'  => false,
			]
		);
		?>
		<div class="wenpai-row wpmind-gw__filters">
			<div class="wpmind-gw__field">
				<label for="log-filter-key">Key</label>
				<select id="log-filter-key">
					<option value=""><?php esc_html_e( '全部', 'wpmind' ); ?></option>
				</select>
			</div>
			<div class="wpmind-gw__field">
				<label for="log-filter-event"><?php esc_html_e( '事件类型', 'wpmind' ); ?></label>
				<select id="log-filter-event">
					<option value=""><?php esc_html_e( '全部', 'wpmind' ); ?></option>
					<option value="api_request">api_request</option>
					<option value="api_stream_request">api_stream_request</option>
					<option value="key_created">key_created</option>
					<option value="key_revoked">key_revoked</option>
				</select>
			</div>
			<div class="wpmind-gw__field">
				<label for="log-filter-from"><?php esc_html_e( '开始日期', 'wpmind' ); ?></label>
				<input type="date" id="log-filter-from">
			</div>
			<div class="wpmind-gw__field">
				<label for="log-filter-to"><?php esc_html_e( '结束日期', 'wpmind' ); ?></label>
				<input type="date" id="log-filter-to">
			</div>
			<div class="wpmind-gw__field">
				<button type="button" class="btn btn-sm btn-primary" id="log-filter-apply"><?php esc_html_e( '筛选日志', 'wpmind' ); ?></button>
			</div>
		</div>

		<div class="wenpai-table-wrap">
			<table class="tbl">
				<thead>
					<tr>
						<th><?php esc_html_e( '时间', 'wpmind' ); ?></th>
						<th>Key</th>
						<th><?php esc_html_e( '事件', 'wpmind' ); ?></th>
						<th><?php esc_html_e( '详情', 'wpmind' ); ?></th>
					</tr>
				</thead>
				<tbody id="gw-logs-tbody">
					<tr><td colspan="4" class="meta"><?php esc_html_e( '点击「筛选日志」加载网关请求记录。', 'wpmind' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="card-foot start" id="gw-logs-pagination" hidden>
			<span id="gw-logs-info" class="meta"></span>
			<span class="wenpai-actions">
				<button type="button" class="btn btn-sm btn-ghost" id="log-prev" disabled><?php esc_html_e( '上一页', 'wpmind' ); ?></button>
				<button type="button" class="btn btn-sm btn-ghost" id="log-next" disabled><?php esc_html_e( '下一页', 'wpmind' ); ?></button>
			</span>
		</div>
		<?php Wenpai_Admin_UI::card_close(); ?>
	</div>
</div>

<script>
( function( $ ) {
	'use strict';
	var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce   = '<?php echo esc_js( wp_create_nonce( 'wpmind_ajax' ) ); ?>';
	var logPage = 1;

	function toast( msg, tone ) {
		if ( typeof window.wenpaiToast === 'function' ) {
			window.wenpaiToast( msg, tone || 'ok' );
		} else if ( window.WPMindAdmin && window.WPMindAdmin.Toast ) {
			window.WPMindAdmin.Toast[ tone === 'bad' ? 'error' : 'success' ]( msg );
		}
	}

	function esc( text ) {
		return $( '<div/>' ).text( text == null ? '' : String( text ) ).html();
	}

	/* ---- Save settings ---- */
	$( '#gw-save-settings' ).on( 'click', function() {
		var $btn = $( this );
		var $msg = $( '#gw-save-msg' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, {
			action: 'wpmind_save_gateway_settings',
			nonce: nonce,
			gateway_enabled: $( '#gw-enabled' ).is( ':checked' ) ? '1' : '',
			sse_global_limit: $( '#gw-sse' ).val(),
			default_rpm: $( '#gw-rpm' ).val(),
			default_tpm: $( '#gw-tpm' ).val(),
			max_body_bytes: String( parseInt( $( '#gw-body-mb' ).val() || '10', 10 ) * 1048576 ),
			max_tokens_cap: $( '#gw-tokens-cap' ).val(),
			log_prompts: $( '#gw-log-prompts' ).is( ':checked' ) ? '1' : ''
		}, function( r ) {
			var m = ( r && r.data && r.data.message ) || ( r && r.success ? '<?php echo esc_js( __( '已保存', 'wpmind' ) ); ?>' : '<?php echo esc_js( __( '保存失败', 'wpmind' ) ); ?>' );
			$msg.text( m ).prop( 'hidden', false );
			toast( m, r && r.success ? 'ok' : 'bad' );
			setTimeout( function() { $msg.prop( 'hidden', true ); }, 3000 );
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Create key form toggle ---- */
	$( '#gw-show-create' ).on( 'click', function() { $( '#gw-create-form' ).prop( 'hidden', false ); } );
	$( '#gw-cancel-create' ).on( 'click', function() { $( '#gw-create-form' ).prop( 'hidden', true ); } );

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
			if ( r && r.success ) {
				$( '#gw-raw-key' ).text( r.data.raw_key );
				$( '#gw-new-key-box' ).prop( 'hidden', false );
				$( '#gw-create-form' ).prop( 'hidden', true );
				$( '#ck-name' ).val( '' );
				toast( '<?php echo esc_js( __( 'API Key 已创建', 'wpmind' ) ); ?>', 'ok' );
				loadKeys();
			} else {
				toast( ( r && r.data && r.data.message ) || '<?php echo esc_js( __( '创建失败', 'wpmind' ) ); ?>', 'bad' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Copy key ---- */
	$( '#gw-copy-key' ).on( 'click', function() {
		var text = $( '#gw-raw-key' ).text();
		var $btn = $( this );
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( function() {
				var orig = $btn.text();
				$btn.text( '<?php echo esc_js( __( '已复制', 'wpmind' ) ); ?>' );
				setTimeout( function() { $btn.text( orig ); }, 2000 );
			} );
		}
	} );

	function editField( label, type, id, val ) {
		var step = type === 'number' ? ' step="any"' : '';
		return '<div class="wpmind-gw__field"><label for="' + esc( id ) + '">' + esc( label ) + '</label><input type="' + esc( type ) + '" id="' + esc( id ) + '" value="' + esc( val ) + '"' + step + '></div>';
	}

	/* ---- Load keys ---- */
	function loadKeys() {
		$.post( ajaxurl, { action: 'wpmind_list_api_keys', nonce: nonce }, function( r ) {
			var $tb = $( '#gw-keys-tbody' );
			$tb.empty();
			if ( ! r || ! r.success || ! r.data || ! r.data.keys || ! r.data.keys.length ) {
				$tb.html( '<tr><td colspan="8" class="meta"><?php echo esc_js( __( '还没有 API Key，点击「创建 API Key」生成访问凭证。', 'wpmind' ) ); ?></td></tr>' );
				return;
			}
			var $sel = $( '#log-filter-key' );
			$sel.find( 'option:gt(0)' ).remove();
			$.each( r.data.keys, function( i, k ) {
				$sel.append( '<option value="' + esc( k.key_id ) + '">sk_mind_' + esc( k.key_prefix ) + '… (' + esc( k.name || '-' ) + ')</option>' );
				var stateCls = k.status === 'active' ? 'ok' : 'mute';
				var stateTxt = k.status === 'active' ? '<?php echo esc_js( __( '启用', 'wpmind' ) ); ?>' : esc( k.status );
				var lastUsed = k.last_used_at || '—';
				var actions = '—';
				if ( k.status === 'active' ) {
					actions = '<span class="wenpai-actions">'
						+ '<button type="button" class="btn btn-sm btn-ghost gw-edit-key" data-kid="' + esc( k.key_id ) + '"><?php echo esc_js( __( '编辑', 'wpmind' ) ); ?></button>'
						+ '<button type="button" class="btn btn-sm btn-danger gw-revoke" data-kid="' + esc( k.key_id ) + '" data-wenpai-confirm="<?php echo esc_attr__( '确定要吊销此 API Key？吊销后立即失效且不可恢复。', 'wpmind' ); ?>" data-wenpai-confirm-title="<?php echo esc_attr__( '吊销 API Key', 'wpmind' ); ?>" data-wenpai-confirm-danger="1" data-wenpai-confirm-ok="<?php echo esc_attr__( '确认吊销', 'wpmind' ); ?>"><?php echo esc_js( __( '吊销', 'wpmind' ) ); ?></button>'
						+ '</span>';
				}
				var ipw = '';
				try {
					ipw = k.ip_whitelist ? ( typeof k.ip_whitelist === 'string' ? ( k.ip_whitelist.charAt( 0 ) === '[' ? JSON.parse( k.ip_whitelist ).join( ', ' ) : k.ip_whitelist ) : '' ) : '';
				} catch ( e ) {
					ipw = k.ip_whitelist || '';
				}
				$tb.append(
					'<tr data-key-id="' + esc( k.key_id ) + '">' +
					'<td><code class="wenpai-code">sk_mind_' + esc( k.key_prefix ) + '…</code></td>' +
					'<td>' + esc( k.name || '—' ) + '</td>' +
					'<td><span class="wenpai-micro-tag is-' + stateCls + '">' + stateTxt + '</span></td>' +
					'<td>' + esc( k.rpm_limit ) + ' / ' + esc( k.tpm_limit ) + '</td>' +
					'<td class="num">' + esc( k.usage_request_count || 0 ) + '</td>' +
					'<td class="num">' + Number( k.usage_total_tokens || 0 ).toLocaleString() + '</td>' +
					'<td>' + esc( lastUsed ) + '</td>' +
					'<td>' + actions + '</td>' +
					'</tr>'
				);
				if ( k.status === 'active' ) {
					$tb.append(
						'<tr class="wpmind-gw__editrow" data-edit-for="' + esc( k.key_id ) + '" hidden><td colspan="8">' +
						'<div class="wpmind-gw__panel">' +
						'<div class="wenpai-grid-auto">' +
						editField( '<?php echo esc_js( __( '名称', 'wpmind' ) ); ?>', 'text', 'ek-name-' + k.key_id, k.name || '' ) +
						editField( 'RPM', 'number', 'ek-rpm-' + k.key_id, k.rpm_limit ) +
						editField( 'TPM', 'number', 'ek-tpm-' + k.key_id, k.tpm_limit ) +
						editField( '<?php echo esc_js( __( '并发', 'wpmind' ) ); ?>', 'number', 'ek-conc-' + k.key_id, k.concurrency_limit ) +
						editField( '<?php echo esc_js( __( '月预算 (USD)', 'wpmind' ) ); ?>', 'number', 'ek-budget-' + k.key_id, k.monthly_budget_usd || 0 ) +
						editField( 'IP <?php echo esc_js( __( '白名单', 'wpmind' ) ); ?>', 'text', 'ek-ips-' + k.key_id, ipw ) +
						editField( '<?php echo esc_js( __( '过期时间', 'wpmind' ) ); ?>', 'date', 'ek-exp-' + k.key_id, k.expires_at ? k.expires_at.substring( 0, 10 ) : '' ) +
						'</div>' +
						'<div class="wenpai-actions wpmind-gw__actions">' +
						'<button type="button" class="btn btn-sm btn-primary gw-save-edit" data-kid="' + esc( k.key_id ) + '"><?php echo esc_js( __( '保存修改', 'wpmind' ) ); ?></button>' +
						'<button type="button" class="btn btn-sm btn-ghost gw-cancel-edit" data-kid="' + esc( k.key_id ) + '"><?php echo esc_js( __( '取消', 'wpmind' ) ); ?></button>' +
						'</div></div></td></tr>'
					);
				}
			} );
		} );
	}

	/* ---- Toggle inline edit ---- */
	$( document ).on( 'click', '.gw-edit-key', function() {
		var kid = $( this ).data( 'kid' );
		var $row = $( '[data-edit-for="' + kid + '"]' );
		var wasHidden = $row.prop( 'hidden' );
		$( '.wpmind-gw__editrow' ).prop( 'hidden', true );
		$row.prop( 'hidden', ! wasHidden );
	} );

	$( document ).on( 'click', '.gw-cancel-edit', function() {
		$( '[data-edit-for="' + $( this ).data( 'kid' ) + '"]' ).prop( 'hidden', true );
	} );

	/* ---- Save edit ---- */
	$( document ).on( 'click', '.gw-save-edit', function() {
		var $btn = $( this );
		var kid = $btn.data( 'kid' );
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
			if ( r && r.success ) {
				toast( '<?php echo esc_js( __( '密钥配置已更新', 'wpmind' ) ); ?>', 'ok' );
				loadKeys();
			} else {
				toast( ( r && r.data && r.data.message ) || '<?php echo esc_js( __( '更新失败', 'wpmind' ) ); ?>', 'bad' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Revoke key (confirmed via kit [data-wenpai-confirm]) ---- */
	$( document ).on( 'click', '.gw-revoke', function() {
		var $btn = $( this );
		var kid = $btn.data( 'kid' );
		$btn.prop( 'disabled', true );
		$.post( ajaxurl, { action: 'wpmind_revoke_api_key', nonce: nonce, key_id: kid }, function( r ) {
			if ( r && r.success ) {
				toast( '<?php echo esc_js( __( 'API Key 已吊销', 'wpmind' ) ); ?>', 'ok' );
				loadKeys();
			} else {
				toast( ( r && r.data && r.data.message ) || '<?php echo esc_js( __( '吊销失败', 'wpmind' ) ); ?>', 'bad' );
			}
		} ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ---- Audit logs ---- */
	$( '#log-filter-apply' ).on( 'click', function() { logPage = 1; loadLogs(); } );
	$( '#log-prev' ).on( 'click', function() { if ( logPage > 1 ) { logPage--; loadLogs(); } } );
	$( '#log-next' ).on( 'click', function() { logPage++; loadLogs(); } );

	function loadLogs() {
		$.post( ajaxurl, {
			action: 'wpmind_list_audit_logs',
			nonce: nonce,
			page: logPage,
			key_id: $( '#log-filter-key' ).val(),
			event_type: $( '#log-filter-event' ).val(),
			date_from: $( '#log-filter-from' ).val(),
			date_to: $( '#log-filter-to' ).val()
		}, function( r ) {
			var $tb = $( '#gw-logs-tbody' );
			$tb.empty();
			if ( ! r || ! r.success || ! r.data || ! r.data.logs || ! r.data.logs.length ) {
				$tb.html( '<tr><td colspan="4" class="meta"><?php echo esc_js( __( '还没有符合条件的请求日志。', 'wpmind' ) ); ?></td></tr>' );
				$( '#gw-logs-pagination' ).prop( 'hidden', true );
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
				} catch ( e ) {
					detail = log.detail_json || '';
				}
				$tb.append(
					'<tr>' +
					'<td>' + esc( log.created_at || '—' ) + '</td>' +
					'<td><code class="wenpai-code">' + esc( log.key_id || '—' ) + '</code></td>' +
					'<td>' + esc( log.event_type || '—' ) + '</td>' +
					'<td>' + esc( detail ) + '</td>' +
					'</tr>'
				);
			} );
			var d = r.data;
			$( '#gw-logs-pagination' ).prop( 'hidden', false );
			$( '#gw-logs-info' ).text( '<?php echo esc_js( __( '共', 'wpmind' ) ); ?> ' + d.total + ' <?php echo esc_js( __( '条，第', 'wpmind' ) ); ?> ' + d.page + ' / ' + d.total_pages + ' <?php echo esc_js( __( '页', 'wpmind' ) ); ?>' );
			$( '#log-prev' ).prop( 'disabled', d.page <= 1 );
			$( '#log-next' ).prop( 'disabled', d.page >= d.total_pages );
		} );
	}

	loadKeys();
} )( jQuery );
</script>
	<?php
} )();
