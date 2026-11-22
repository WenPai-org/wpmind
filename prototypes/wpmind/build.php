<?php
/**
 * WPMind（文派心思）4.0 离线静态原型生成器 — 直接复用真实 templates/admin/page.php 与模块模板单一真源。
 *
 * Usage:
 *   php prototypes/wpmind/build.php
 *   php prototypes/wpmind/build.php --check
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace {
	$plugin_root = dirname( __DIR__, 2 );
	$kit_root    = $plugin_root . '/lib/wenpai-admin-ui';
	$out_dir     = __DIR__;
	$check       = in_array( '--check', array_slice( $argv, 1 ), true );

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $plugin_root . '/' );
	}
	if ( ! defined( 'WPMIND_PLUGIN_DIR' ) ) {
		define( 'WPMIND_PLUGIN_DIR', $plugin_root . '/' );
	}
	if ( ! defined( 'WPMIND_PLUGIN_URL' ) ) {
		define( 'WPMIND_PLUGIN_URL', '../../' );
	}
	if ( ! defined( 'WPMIND_VERSION' ) ) {
		define( 'WPMIND_VERSION', '0.14.0' );
	}

	$GLOBALS['_wpmind_proto_now']     = 1795255200; // 2026-11-22 10:00:00 UTC (deterministic).
	$GLOBALS['_wpmind_proto_options'] = [
		'wpmind_cloud_enabled'             => '1',
		'wpmind_default_provider'          => 'deepseek',
		'wpmind_default_image_provider'    => 'qwen_image',
		'wpmind_exact_cache_enabled'       => '1',
		'wpmind_exact_cache_default_ttl'   => 900,
		'wpmind_exact_cache_max_entries'   => 500,
		'wpmind_exact_cache_scope_mode'    => 'role',
		'wpmind_gateway_enabled'           => '1',
		'wpmind_gateway_rate_limit'        => 60,
		'wpmind_gateway_log_retention'     => 30,
		'wpmind_geo_enabled'               => '1',
		'wpmind_chinese_optimize'          => '1',
		'wpmind_geo_signals'               => '1',
		'wpmind_crawler_tracking'          => '1',
		'wpmind_standalone_markdown_feed'  => '1',
		'wpmind_llms_txt_enabled'          => '1',
		'wpmind_ai_sitemap_enabled'        => '1',
		'wpmind_ai_sitemap_max_entries'    => 500,
		'wpmind_ai_summary_enabled'        => '1',
		'wpmind_ai_summary_fallback'       => 'excerpt',
		'wpmind_schema_enabled'            => '1',
		'wpmind_schema_mode'               => 'auto',
		'wpmind_entity_linker_enabled'     => '1',
		'wpmind_brand_entity_enabled'      => '1',
		'wpmind_brand_org_type'            => 'Organization',
		'wpmind_brand_name'                => '示例技术博客',
		'wpmind_brand_description'         => '专注 WordPress 与中文内容生态的实践笔记。',
		'wpmind_brand_url'                 => 'https://example.com',
		'wpmind_ai_indexing_enabled'       => '1',
		'wpmind_ai_default_declaration'    => 'original',
		'wpmind_ai_excluded_post_types'    => [ 'attachment' ],
		'wpmind_robots_ai_enabled'         => '1',
		'wpmind_robots_ai_rules'           => [
			'GPTBot'          => 'allow',
			'ClaudeBot'       => 'allow',
			'PerplexityBot'   => 'allow',
			'Google-Extended' => 'default',
			'Bytespider'      => 'disallow',
		],
		'wpmind_media_auto_alt'            => '1',
		'wpmind_media_auto_title'          => '1',
		'wpmind_media_nsfw_enabled'        => '0',
		'wpmind_media_language'            => 'zh',
		'wpmind_media_stats'               => [
			'generated'             => 186,
			'month_' . gmdate( 'Y_m' ) => 42,
		],
		'wpmind_auto_meta_excerpt'         => '1',
		'wpmind_auto_meta_tags'            => '1',
		'wpmind_auto_meta_category'        => '0',
		'wpmind_auto_meta_faq'             => '1',
		'wpmind_auto_meta_seo_desc'        => '1',
		'wpmind_auto_meta_post_types'      => [ 'post' ],
		'wpmind_auto_meta_stats'           => [
			'generated'             => 94,
			'month_' . gmdate( 'Y_m' ) => 18,
		],
		'wpmind_routing_strategy'          => 'composite',
		'wpmind_provider_priority'         => [ 'wenpai_cloud', 'deepseek', 'qwen', 'openai' ],
		'wpmind_api_key_meta'              => [
			'deepseek' => [ 'fingerprint' => 'sk-...9a2f', 'updated_at' => 1794650400 ],
			'openai'   => [ 'fingerprint' => 'sk-...4c8e', 'updated_at' => 1794045600 ],
		],
	];

	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url_raw( $s ) {
		return (string) $s;
	}
	function esc_textarea( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_js( $s ) {
		return str_replace( [ '\\', "'", '"', "\n", "\r" ], [ '\\\\', "\\'", '&quot;', '\\n', '' ], (string) $s );
	}
	function esc_html__( $s, $d = '' ) {
		unset( $d );
		return esc_html( $s );
	}
	function esc_attr__( $s, $d = '' ) {
		unset( $d );
		return esc_attr( $s );
	}
	function esc_html_e( $s, $d = '' ) {
		unset( $d );
		echo esc_html( $s );
	}
	function esc_attr_e( $s, $d = '' ) {
		unset( $d );
		echo esc_attr( $s );
	}
	function esc_html_x( $s, $c = '', $d = '' ) {
		unset( $c, $d );
		return esc_html( $s );
	}
	function esc_attr_x( $s, $c = '', $d = '' ) {
		unset( $c, $d );
		return esc_attr( $s );
	}
	function __( $s, $d = '' ) {
		unset( $d );
		return (string) $s;
	}
	function _e( $s, $d = '' ) {
		unset( $d );
		echo (string) $s;
	}
	function _x( $s, $c = '', $d = '' ) {
		unset( $c, $d );
		return (string) $s;
	}
	function _n( $s, $p, $n, $d = '' ) {
		unset( $d );
		return 1 === (int) $n ? (string) $s : (string) $p;
	}
	function absint( $v ) {
		return abs( (int) $v );
	}
	function wp_parse_args( $args, $defaults = [] ) {
		if ( is_array( $args ) ) {
			$r = $args;
		} elseif ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} else {
			parse_str( (string) $args, $r );
		}
		return array_merge( (array) $defaults, $r );
	}
	function wp_strip_all_tags( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_key( $s ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
	}
	function sanitize_html_class( $s ) {
		return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s );
	}
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_textarea_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function wp_unslash( $s ) {
		return $s;
	}
	function wp_kses( $s, $allowed = [] ) {
		unset( $allowed );
		return (string) $s;
	}
	function wp_kses_post( $s ) {
		return (string) $s;
	}
	function wp_kses_allowed_html( $ctx = '' ) {
		unset( $ctx );
		return [];
	}
	function is_admin() {
		return true;
	}
	function current_user_can( $cap ) {
		unset( $cap );
		return true;
	}
	function add_action() {}
	function add_filter() {}
	function do_action() {}
	function apply_filters( $tag, $val ) {
		if ( 'wpmind_ecosystem_integrations' === $tag ) {
			return [
				[
					'slug'         => 'wpslug',
					'name'         => '文派别名（WPSlug）',
					'desc'         => '文章、分类与媒体别名转换已接入文派心思，复用站点路由与预算。',
					'feature'      => '语义拼音 / AI 翻译',
					'icon'         => 'globe',
					'version'      => '1.3.3',
					'settings_url' => 'services.html',
					'requests'     => 110,
					'tokens'       => 70000,
					'active'       => true,
				],
				[
					'slug'         => 'bisheng-html',
					'name'         => '笔笙 HTML（Bisheng）',
					'desc'         => '页面与代码生成已连接文派心思公共接口。',
					'feature'      => 'AI 页面生成',
					'icon'         => 'sparkle',
					'version'      => '0.6.0',
					'settings_url' => 'services.html',
					'requests'     => 48,
					'tokens'       => 142000,
					'active'       => true,
				],
			];
		}
		return $val;
	}
	function register_setting() {}
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( (string) $group ) . '">';
		echo '<input type="hidden" name="action" value="update">';
		echo '<input type="hidden" name="_wpnonce" value="proto">';
	}
	function get_option( $k, $default = false ) {
		return array_key_exists( $k, $GLOBALS['_wpmind_proto_options'] ) ? $GLOBALS['_wpmind_proto_options'][ $k ] : $default;
	}
	function update_option( $k, $v ) {
		$GLOBALS['_wpmind_proto_options'][ $k ] = $v;
		return true;
	}
	function get_transient( $k ) {
		unset( $k );
		return false;
	}
	function get_settings_errors() {
		return [];
	}
	function settings_errors() {}
	function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
		unset( $a, $r );
		$h = '<input type="hidden" name="' . esc_attr( (string) $n ) . '" value="proto">';
		if ( $e ) {
			echo $h;
		}
		return $h;
	}
	function wp_create_nonce( $a = -1 ) {
		unset( $a );
		return 'proto';
	}
	function menu_page_url( $slug, $display = true ) {
		$url = 'wpmind' === $slug ? 'overview.html' : $slug . '.html';
		if ( $display ) {
			echo esc_url( $url );
		}
		return $url;
	}
	function admin_url( $p = '' ) {
		return (string) $p;
	}
	function rest_url( $p = '' ) {
		return 'https://example.com/wp-json/' . ltrim( (string) $p, '/' );
	}
	function home_url( $p = '' ) {
		return 'https://example.com' . (string) $p;
	}
	function get_site_url() {
		return 'https://example.com';
	}
	function get_bloginfo( $show = '' ) {
		if ( 'name' === $show ) {
			return '示例技术博客';
		}
		if ( 'version' === $show ) {
			return '6.7.2';
		}
		return 'https://example.com';
	}
	function get_posts( $args = [] ) {
		if ( isset( $args['post_type'] ) && 'wpmind_task' === $args['post_type'] ) {
			return [ 12 ];
		}
		return [];
	}
	function get_post_types( $args = [], $output = 'names' ) {
		unset( $args );
		$types = [
			'post' => (object) [ 'name' => 'post', 'label' => '文章', 'labels' => (object) [ 'name' => '文章' ] ],
			'page' => (object) [ 'name' => 'page', 'label' => '页面', 'labels' => (object) [ 'name' => '页面' ] ],
		];
		return 'objects' === $output ? $types : array_keys( $types );
	}
	function human_time_diff( $from, $to = 0 ) {
		unset( $to );
		$diff = abs( (int) $GLOBALS['_wpmind_proto_now'] - (int) $from );
		if ( $diff < 60 ) {
			return '1 分钟';
		}
		if ( $diff < 3600 ) {
			return max( 1, (int) round( $diff / 60 ) ) . ' 分钟';
		}
		if ( $diff < 86400 ) {
			return max( 1, (int) round( $diff / 3600 ) ) . ' 小时';
		}
		return max( 1, (int) round( $diff / 86400 ) ) . ' 天';
	}
	function number_format_i18n( $n, $dec = 0 ) {
		return number_format( (float) $n, (int) $dec );
	}
	function wp_date( $format, $timestamp = null ) {
		$ts = null === $timestamp ? (int) $GLOBALS['_wpmind_proto_now'] : (int) $timestamp;
		return gmdate( (string) $format, $ts + 8 * 3600 );
	}
	function selected( $a, $b, $e = true ) {
		$r = (string) $a === (string) $b ? ' selected="selected"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function checked( $a, $b = true, $e = true ) {
		$r = (bool) $a === (bool) $b ? ' checked="checked"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function disabled( $a, $b = true, $e = true ) {
		$r = (bool) $a === (bool) $b ? ' disabled="disabled"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function wp_json_encode( $d, $flags = 0 ) {
		return (string) json_encode( $d, $flags );
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, (int) $component );
	}
	function add_query_arg( $args, $url = '' ) {
		if ( ! is_array( $args ) ) {
			return (string) $url;
		}
		$tab_files = [
			'overview'  => 'overview.html',
			'services'  => 'services.html',
			'cloud'     => 'cloud.html',
			'routing'   => 'routing.html',
			'modules'   => 'modules.html',
			'analytics' => 'analytics.html',
			'workflow'  => 'workflow.html',
			'tools'     => 'tools.html',
			'help'      => 'help.html',
		];
		$module_files = [
			'geo'         => 'module-geo.html',
			'api-gateway' => 'module-gateway.html',
			'exact-cache' => 'module-cache.html',
		];
		$params = $args;
		unset( $params['page'] );
		$tab = isset( $params['tab'] ) ? (string) $params['tab'] : '';
		unset( $params['tab'] );
		if ( 'module' === $tab ) {
			$mod = isset( $params['module'] ) ? (string) $params['module'] : '';
			unset( $params['module'] );
			$base = $module_files[ $mod ] ?? 'modules.html';
			return $base . ( empty( $params ) ? '' : '?' . http_build_query( $params ) );
		}
		if ( '' !== $tab && isset( $tab_files[ $tab ] ) ) {
			$base = $tab_files[ $tab ];
			return $base . ( empty( $params ) ? '' : '?' . http_build_query( $params ) );
		}
		$base = '' !== (string) $url ? (string) $url : 'overview.html';
		$base = preg_replace( '/\?.*$/', '', $base ) ?? $base;
		return $base . ( empty( $params ) ? '' : '?' . http_build_query( $params ) );
	}

	foreach ( [ 'loader', 'icons', 'menu', 'ui', 'action', 'settings', 'tools', 'shell' ] as $part ) {
		$path = $kit_root . '/php/class-wenpai-admin-' . $part . '.php';
		if ( is_file( $path ) ) {
			require_once $path;
		}
	}

	require_once $plugin_root . '/includes/Admin/AdminIcons.php';
	require_once $plugin_root . '/includes/Admin/AdminPage.php';
	require_once $plugin_root . '/includes/Admin/SuiteIntegration.php';
}

namespace WPMind\Provider {
	final class WenpaiCloud {
		public const PROVIDER_ID = 'wenpai_cloud';
		public static function is_enabled(): bool {
			return true;
		}
		public static function mode_options(): array {
			return [
				'primary'    => [
					'label' => '全托管优先',
					'desc'  => '优先走心思 AI，免维护第三方 Key；异常时自动回退自备 Key。',
				],
				'fallback'   => [
					'label' => '自备优先 · 心思备援',
					'desc'  => '平时走自备 Key，仅在主服务熔断或限流时由心思 AI 自动顶上。',
				],
				'capability' => [
					'label' => '按能力补充',
					'desc'  => '常规文本走自备 Key，视觉、生图与深度推理自动走心思 AI。',
				],
			];
		}
		public static function get_status(): array {
			return [
				'enabled'             => true,
				'bound'               => true,
				'managed_by_suite'    => true,
				'site_uuid'           => 'site_demo_wpcy_01',
				'credits_balance'     => 4820,
				'credits_used_month'  => 1180,
				'credits_limit_month' => 6000,
				'unit_label'          => '心思点数',
				'base_url'            => 'https://mind-api.wenpai.net/v1',
				'bind_url'            => 'services.html',
				'topup_url'           => 'https://wenpai.net/mind-credits',
			];
		}
		public static function is_provider_locked( string $id ): bool {
			return 'qwen' === $id;
		}
	}

	final class Diagnostics {
		public static function format_shanghai( int $ts ): string {
			return gmdate( 'Y-m-d H:i', $ts + 8 * 3600 );
		}
	}
}

namespace WPMind {
	final class WPMind {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_custom_endpoints(): array {
			return [
				'deepseek'  => [
					'name'            => 'DeepSeek',
					'display_name'    => 'DeepSeek 深度求索',
					'enabled'         => true,
					'api_key'         => 'sk-demo-deepseek-key-9a2f',
					'base_url'        => 'https://api.deepseek.com/v1',
					'custom_base_url' => '',
					'models'          => [ 'deepseek-chat', 'deepseek-reasoner' ],
					'default_model'   => 'deepseek-chat',
				],
				'qwen'      => [
					'name'            => '通义千问',
					'display_name'    => '阿里云百炼通义千问',
					'enabled'         => true,
					'api_key'         => 'sk-locked-qwen',
					'base_url'        => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
					'custom_base_url' => '',
					'models'          => [ 'qwen-plus', 'qwen-max', 'qwen-turbo' ],
					'default_model'   => 'qwen-plus',
				],
				'openai'    => [
					'name'            => 'OpenAI',
					'display_name'    => 'OpenAI 官方接口',
					'enabled'         => true,
					'api_key'         => 'sk-demo-openai-key-4c8e',
					'base_url'        => 'https://api.openai.com/v1',
					'custom_base_url' => '',
					'models'          => [ 'gpt-4o-mini', 'gpt-4o' ],
					'default_model'   => 'gpt-4o-mini',
				],
				'anthropic' => [
					'name'            => 'Anthropic Claude',
					'display_name'    => 'Claude 文本与推理',
					'enabled'         => false,
					'api_key'         => '',
					'base_url'        => 'https://api.anthropic.com/v1',
					'custom_base_url' => '',
					'models'          => [ 'claude-3-5-sonnet-latest' ],
					'default_model'   => 'claude-3-5-sonnet-latest',
				],
			];
		}
		public function get_image_endpoints(): array {
			return [
				'qwen_image'         => [
					'name'            => '通义万相',
					'display_name'    => '阿里云通义万相图像生成',
					'enabled'         => true,
					'api_key'         => 'sk-demo-wanx',
					'base_url'        => 'https://dashscope.aliyuncs.com/api/v1',
					'custom_base_url' => '',
					'models'          => [ 'wanx2.1-t2i-turbo', 'wanx2.1-t2i-plus' ],
					'default_model'   => 'wanx2.1-t2i-turbo',
				],
				'openai_gpt_image'   => [
					'name'            => 'OpenAI GPT Image',
					'display_name'    => 'OpenAI 图像接口',
					'enabled'         => false,
					'api_key'         => '',
					'base_url'        => 'https://api.openai.com/v1',
					'custom_base_url' => '',
					'models'          => [ 'gpt-image-1' ],
					'default_model'   => 'gpt-image-1',
				],
				'bytedance_seedream' => [
					'name'            => '字节豆包 Seedream',
					'display_name'    => '火山引擎豆包文生图',
					'enabled'         => false,
					'api_key'         => '',
					'base_url'        => 'https://ark.cn-beijing.volces.com/api/v3',
					'custom_base_url' => '',
					'models'          => [ 'doubao-seedream-3-0-t2i' ],
					'default_model'   => 'doubao-seedream-3-0-t2i',
				],
			];
		}
		public function get_default_endpoints(): array {
			return $this->get_custom_endpoints();
		}
		public function has_api_key( string $key ): bool {
			$all = array_merge( $this->get_custom_endpoints(), $this->get_image_endpoints() );
			return ! empty( $all[ $key ]['api_key'] );
		}
	}

	function wpmind(): WPMind {
		return WPMind::instance();
	}
}

namespace WPMind\Failover {
	final class CircuitBreakerStub {
		private string $state;
		public function __construct( string $state = 'closed' ) {
			$this->state = $state;
		}
		public function get_state(): string {
			return $this->state;
		}
		public function get_status_details(): array {
			return [
				'state'             => $this->state,
				'failure_count'     => 'closed' === $this->state ? 0 : 3,
				'last_error'        => '',
				'last_error_reason' => '',
				'last_error_at'     => 0,
			];
		}
		public function get_stats(): array {
			return [
				'state'          => $this->state,
				'failure_count'  => 'closed' === $this->state ? 0 : 3,
				'last_error'     => '',
				'last_error_at'  => 0,
			];
		}
	}

	final class FailoverManager {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_all_status(): array {
			return [
				'wenpai_cloud' => [
					'name'               => '文派心思官方算力',
					'enabled'            => true,
					'has_api_key'        => true,
					'available'          => true,
					'state'              => 'closed',
					'state_display'      => '正常',
					'avg_latency_ms'     => 280,
					'success_rate'       => 99.6,
				],
				'deepseek'     => [
					'name'               => 'DeepSeek',
					'enabled'            => true,
					'has_api_key'        => true,
					'available'          => true,
					'state'              => 'closed',
					'state_display'      => '正常',
					'avg_latency_ms'     => 420,
					'success_rate'       => 98.9,
				],
				'qwen'         => [
					'name'               => '通义千问',
					'enabled'            => true,
					'has_api_key'        => true,
					'available'          => true,
					'state'              => 'closed',
					'state_display'      => '正常',
					'avg_latency_ms'     => 510,
					'success_rate'       => 99.1,
				],
				'openai'       => [
					'name'               => 'OpenAI',
					'enabled'            => true,
					'has_api_key'        => true,
					'available'          => true,
					'state'              => 'closed',
					'state_display'      => '正常',
					'avg_latency_ms'     => 860,
					'success_rate'       => 97.4,
				],
			];
		}
		public function get_status_summary(): array {
			return $this->get_all_status();
		}
		public function get_circuit_breaker( string $id ): CircuitBreakerStub {
			unset( $id );
			return new CircuitBreakerStub( 'closed' );
		}
	}
}

namespace WPMind\Routing {
	final class IntelligentRouter {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_current_strategy(): string {
			return 'composite';
		}
		public function get_available_strategies(): array {
			return [
				'composite' => [ 'display_name' => '综合最优' ],
				'cost'      => [ 'display_name' => '成本优先' ],
				'latency'   => [ 'display_name' => '低延迟优先' ],
				'quality'   => [ 'display_name' => '质量优先' ],
			];
		}
		public function get_manual_priority(): array {
			return [ 'wenpai_cloud', 'deepseek', 'qwen', 'openai' ];
		}
		public function get_status_summary(): array {
			return [
				'recommended'     => 'wenpai_cloud',
				'failover_chain'  => [ 'wenpai_cloud', 'deepseek', 'qwen', 'openai' ],
				'provider_scores' => [
					'wenpai_cloud' => [ 'name' => '文派心思官方算力', 'score' => 94.8, 'rank' => 1 ],
					'deepseek'     => [ 'name' => 'DeepSeek', 'score' => 89.2, 'rank' => 2 ],
					'qwen'         => [ 'name' => '通义千问', 'score' => 85.6, 'rank' => 3 ],
					'openai'       => [ 'name' => 'OpenAI', 'score' => 78.4, 'rank' => 4 ],
				],
			];
		}
	}
}

namespace WPMind\Core {
	final class ModuleLoader {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_modules(): array {
			return [
				'cost-control'       => [
					'id'           => 'cost-control',
					'name'         => '费用控制',
					'description'  => '实时用量追踪、日/月预算上限与超限降级策略。',
					'enabled'      => true,
					'can_disable'  => false,
					'has_settings' => true,
				],
				'exact-cache'        => [
					'id'           => 'exact-cache',
					'name'         => '精确缓存',
					'description'  => '缓存相同 AI 请求结果，按角色隔离并估算节省费用。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => true,
				],
				'geo'                => [
					'id'           => 'geo',
					'name'         => 'GEO 优化',
					'description'  => '生成 llms.txt、AI Sitemap、结构化 Schema 与爬虫追踪。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => true,
				],
				'api-gateway'        => [
					'id'           => 'api-gateway',
					'name'         => 'API 网关',
					'description'  => '对外提供 OpenAI 兼容 REST 接口、密钥管理与限流日志。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => true,
				],
				'analytics'          => [
					'id'           => 'analytics',
					'name'         => '数据分析',
					'description'  => '7/30 天调用趋势图、模型占比与多维费用报表。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => false,
				],
				'auto-meta'          => [
					'id'           => 'auto-meta',
					'name'         => '自动元数据',
					'description'  => '自动生成文章摘要、标签、SEO 描述与 FAQ 问答。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => true,
				],
				'media-intelligence' => [
					'id'           => 'media-intelligence',
					'name'         => '媒体智能',
					'description'  => '上传图片自动补齐 Alt 文本、标题与内容安全检测。',
					'enabled'      => true,
					'can_disable'  => true,
					'has_settings' => true,
				],
				'content-assistant'  => [
					'id'           => 'content-assistant',
					'name'         => '内容写作助手',
					'description'  => '在区块编辑器内提供润色、扩写与多语翻译辅助。',
					'enabled'      => false,
					'can_disable'  => true,
					'has_settings' => false,
				],
			];
		}
		public function is_module_enabled( string $id ): bool {
			$mods = $this->get_modules();
			return ! empty( $mods[ $id ]['enabled'] );
		}
	}
}

namespace WPMind\Modules\CostControl {
	final class UsageTracker {
		public static function get_stats(): array {
			return [
				'last_updated' => (int) $GLOBALS['_wpmind_proto_now'] - 300,
				'total'        => [
					'input_tokens'  => 1940200,
					'output_tokens' => 812400,
					'cost_usd'      => 12.40,
					'cost_cny'      => 138.20,
					'requests'      => 9820,
				],
				'providers'    => [
					'deepseek' => [
						'total_input_tokens'  => 1120000,
						'total_output_tokens' => 480000,
						'total_cost'          => 86.40,
						'request_count'       => 5420,
					],
					'qwen'     => [
						'total_input_tokens'  => 540200,
						'total_output_tokens' => 210400,
						'total_cost'          => 51.80,
						'request_count'       => 2890,
					],
					'openai'   => [
						'total_input_tokens'  => 280000,
						'total_output_tokens' => 122000,
						'total_cost'          => 12.40,
						'request_count'       => 1510,
					],
				],
			];
		}
		public static function get_today_stats(): array {
			return [
				'input_tokens'  => 128400,
				'output_tokens' => 55800,
				'cost_usd'      => 0.18,
				'cost_cny'      => 3.24,
				'requests'      => 142,
			];
		}
		public static function get_week_stats(): array {
			return [
				'input_tokens'  => 682000,
				'output_tokens' => 294000,
				'cost_usd'      => 1.12,
				'cost_cny'      => 16.80,
				'requests'      => 864,
			];
		}
		public static function get_month_stats(): array {
			return [
				'input_tokens'  => 1420000,
				'output_tokens' => 610000,
				'cost_usd'      => 4.20,
				'cost_cny'      => 48.60,
				'requests'      => 3410,
			];
		}
		public static function get_context_stats(): array {
			return [
				'auto-meta'          => [ 'requests' => 1480, 'input_tokens' => 620000, 'output_tokens' => 240000, 'cost_cny' => 18.40, 'cost_usd' => 0.0 ],
				'media-intelligence' => [ 'requests' => 920, 'input_tokens' => 410000, 'output_tokens' => 160000, 'cost_cny' => 14.20, 'cost_usd' => 0.0 ],
				'api-gateway'        => [ 'requests' => 640, 'input_tokens' => 250000, 'output_tokens' => 130000, 'cost_cny' => 0.0, 'cost_usd' => 3.10 ],
				'geo'                => [ 'requests' => 260, 'input_tokens' => 98000, 'output_tokens' => 52000, 'cost_cny' => 9.80, 'cost_usd' => 0.0 ],
				'wp-slug'            => [ 'requests' => 110, 'input_tokens' => 42000, 'output_tokens' => 28000, 'cost_cny' => 6.20, 'cost_usd' => 1.10 ],
			];
		}
		public static function get_history( int $limit = 10 ): array {
			return self::get_recent_history( $limit );
		}
		public static function get_recent_history( int $limit = 10 ): array {
			$now = (int) $GLOBALS['_wpmind_proto_now'];
			$rows = [
				[ 'provider' => 'wenpai_cloud', 'model' => 'deepseek-chat', 'context' => 'auto-meta', 'input_tokens' => 1240, 'output_tokens' => 380, 'latency_ms' => 310, 'timestamp' => $now - 180 ],
				[ 'provider' => 'deepseek', 'model' => 'deepseek-chat', 'context' => 'media-intelligence', 'input_tokens' => 860, 'output_tokens' => 120, 'latency_ms' => 415, 'timestamp' => $now - 540 ],
				[ 'provider' => 'qwen', 'model' => 'qwen-plus', 'context' => 'geo', 'input_tokens' => 2100, 'output_tokens' => 540, 'latency_ms' => 490, 'timestamp' => $now - 1260 ],
				[ 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'context' => 'api-gateway', 'input_tokens' => 980, 'output_tokens' => 420, 'latency_ms' => 820, 'timestamp' => $now - 2400 ],
				[ 'provider' => 'wenpai_cloud', 'model' => 'qwen-plus', 'context' => 'auto-meta', 'input_tokens' => 1520, 'output_tokens' => 410, 'latency_ms' => 295, 'timestamp' => $now - 3600 ],
			];
			return array_slice( $rows, 0, $limit );
		}
		public static function format_tokens( int $tokens ): string {
			if ( $tokens >= 1000000 ) {
				return number_format( $tokens / 1000000, 2 ) . 'M';
			}
			if ( $tokens >= 1000 ) {
				return number_format( $tokens / 1000, 1 ) . 'K';
			}
			return (string) $tokens;
		}
		public static function format_cost( float $cost, string $currency = 'USD' ): string {
			$sym = 'CNY' === strtoupper( $currency ) ? '¥' : '$';
			return $sym . number_format( $cost, 2 );
		}
		public static function format_cost_by_currency( float $usd, float $cny ): string {
			$parts = [];
			if ( $cny > 0 ) {
				$parts[] = '¥' . number_format( $cny, 2 );
			}
			if ( $usd > 0 ) {
				$parts[] = '$' . number_format( $usd, 2 );
			}
			return empty( $parts ) ? '$0.00' : implode( ' / ', $parts );
		}
		public static function get_currency( string $provider ): string {
			return in_array( $provider, [ 'deepseek', 'qwen', 'zhipu', 'wenpai_cloud' ], true ) ? 'CNY' : 'USD';
		}
		public static function get_provider_display_name( string $provider ): string {
			$map = [
				'wenpai_cloud' => '文派心思官方算力',
				'deepseek'     => 'DeepSeek',
				'qwen'         => '通义千问',
				'openai'       => 'OpenAI',
				'anthropic'    => 'Anthropic Claude',
			];
			return $map[ $provider ] ?? $provider;
		}
	}

	final class BudgetManager {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_settings(): array {
			return [
				'enabled'          => true,
				'global'           => [
					'daily_limit_usd'   => 2.0,
					'monthly_limit_usd' => 30.0,
					'daily_limit_cny'   => 20.0,
					'monthly_limit_cny' => 200.0,
					'alert_threshold'   => 80,
				],
				'enforcement_mode' => 'alert',
				'notifications'    => [
					'admin_notice'  => true,
					'email_alert'   => false,
					'email_address' => 'ops@example.com',
				],
			];
		}
		public static function get_mode_options(): array {
			return [
				'alert'     => '仅告警提醒',
				'downgrade' => '自动降级经济模型',
				'block'     => '拦截超限请求',
			];
		}
	}

	final class BudgetChecker {}
}

namespace WPMind\Cache {
	final class ExactCache {
		private static ?self $inst = null;
		public static function instance(): self {
			return self::$inst ?? ( self::$inst = new self() );
		}
		public function get_stats(): array {
			return [
				'hits'    => 612,
				'misses'  => 248,
				'entries' => 184,
			];
		}
	}
}

namespace WPMind\Modules\ExactCache {
	final class CostEstimator {
		public static function get_estimated_savings(): array {
			return [
				'saved_usd' => 3.85,
				'saved_cny' => 12.40,
				'total_usd' => 3.85,
			];
		}
	}
}

namespace WPMind\Workflow {
	final class AltBulkService {
		public const MAX_CANDIDATES = 50;
	}
	final class MetaBulkService {
		public const MAX_CANDIDATES = 50;
	}
	final class TaskRepository {
		public function list_tasks( array $args = [] ): array {
			unset( $args );
			return [
				[
					'id'         => 12,
					'type'       => 'alt_bulk',
					'status'     => 'pending_review',
					'created_at' => '2026-11-22 09:20:00',
					'summary'    => [
						'candidates' => 12,
						'previewed'  => 12,
						'approved'   => 8,
						'rejected'   => 1,
						'applied'    => 0,
						'errors'     => 0,
					],
				],
				[
					'id'         => 11,
					'type'       => 'meta_bulk',
					'status'     => 'applied',
					'created_at' => '2026-11-21 16:45:00',
					'summary'    => [
						'candidates' => 15,
						'previewed'  => 15,
						'approved'   => 15,
						'rejected'   => 0,
						'applied'    => 15,
						'errors'     => 0,
					],
				],
			];
		}
	}
}

namespace WPMind\Modules\Geo {
	final class CrawlerTracker {
		public function get_stats(): array {
			return [
				'GPTBot'          => [ 'is_ai' => true, 'company' => 'OpenAI', 'total_hits' => 124 ],
				'ClaudeBot'       => [ 'is_ai' => true, 'company' => 'Anthropic', 'total_hits' => 86 ],
				'PerplexityBot'   => [ 'is_ai' => true, 'company' => 'Perplexity', 'total_hits' => 62 ],
				'Google-Extended' => [ 'is_ai' => true, 'company' => 'Google', 'total_hits' => 34 ],
				'Googlebot'       => [ 'is_ai' => false, 'company' => 'Google', 'total_hits' => 210 ],
			];
		}
		public function get_ai_summary(): array {
			return [
				'total_ai_hits'     => 306,
				'total_search_hits' => 210,
				'ai_crawlers'       => [ 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended' ],
			];
		}
	}
	final class LlmsTxtGenerator {
		public static function get_public_url(): string {
			return 'https://example.com/llms.txt';
		}
		public static function detect_conflicts(): array {
			return [];
		}
	}
	final class AiSitemapGenerator {
		public static function get_public_url(): string {
			return 'https://example.com/ai-sitemap.xml';
		}
	}
	final class RobotsTxtManager {
		public const AI_CRAWLERS = [
			'GPTBot'          => [ 'name' => 'GPTBot', 'company' => 'OpenAI', 'description' => 'OpenAI 检索与训练爬虫' ],
			'ClaudeBot'       => [ 'name' => 'ClaudeBot', 'company' => 'Anthropic', 'description' => 'Anthropic 内容索引爬虫' ],
			'PerplexityBot'   => [ 'name' => 'PerplexityBot', 'company' => 'Perplexity', 'description' => 'Perplexity AI 搜索索引' ],
			'Google-Extended' => [ 'name' => 'Google-Extended', 'company' => 'Google', 'description' => 'Google Gemini 语料控制标记' ],
			'Bytespider'      => [ 'name' => 'Bytespider', 'company' => 'ByteDance', 'description' => '字节跳动大模型索引爬虫' ],
		];
	}
}

namespace WPMind\Modules\ApiGateway\Auth {
	final class ApiKeyRepository {
		public static function count_keys(): int {
			return 3;
		}
		public static function count_active_keys(): int {
			return 2;
		}
		public static function get_month_total_requests(): int {
			return 1284;
		}
	}
}

namespace {
	function wpmind_proto_wrap( string $title, string $body ): string {
		$h  = "<!DOCTYPE html>\n";
		$h .= "<!-- 由 prototypes/wpmind/build.php 经真实 templates/admin/page.php 生成，请勿手工修改。 -->\n";
		$h .= "<html lang=\"zh-CN\">\n<head>\n<meta charset=\"utf-8\">\n";
		$h .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
		$h .= '<title>' . esc_html( $title ) . "</title>\n";
		foreach ( [ 'wenpai-tokens.css', 'wenpai-admin.css', 'wenpai-admin-wp.css' ] as $css ) {
			$h .= '<link rel="stylesheet" href="../../lib/wenpai-admin-ui/css/' . $css . "\">\n";
		}
		$h .= "<link rel=\"stylesheet\" href=\"../../assets/css/wpmind-admin.css\">\n";
		$h .= "</head>\n<body class=\"wp-admin wp-core-ui wenpai-ui-page wpmind-admin\">\n";
		$h .= $body . "\n";
		$h .= "<script src=\"../../lib/wenpai-admin-ui/js/wenpai-admin.js\"></script>\n";
		$h .= "</body>\n</html>\n";
		return $h;
	}

	function wpmind_proto_render( array $get ): string {
		$prev = $_GET;
		$_GET = $get;
		ob_start();
		include WPMIND_PLUGIN_DIR . 'templates/admin/page.php';
		$html = (string) ob_get_clean();
		$_GET = $prev;
		return $html;
	}

	function wpmind_proto_index( array $catalog ): string {
		ob_start();
		Wenpai_Admin_Shell::open(
			[
				'brand_name' => '文派心思 4.0 原型索引',
				'brand_sub'  => '静态离线预览',
				'version'    => 'v' . WPMIND_VERSION,
				'main_class' => 'wenpai-measure-wide',
				'tabs'       => [
					[
						'slug'    => 'index',
						'label'   => '页面索引',
						'url'     => 'index.html',
						'current' => true,
					],
					[
						'slug'  => 'overview',
						'label' => '进入概览',
						'url'   => 'overview.html',
					],
				],
			]
		);
		Wenpai_Admin_Shell::page_head(
			'文派心思 4.0 静态原型',
			'由真实模板与 kit v0.13.7 单一真源生成，共 11 个视图。'
		);
		echo '<div class="wenpai-stack">';
		Wenpai_Admin_UI::card_open(
			[
				'title' => '原型页面目录',
				'sub'   => '点击任意条目在浏览器中检视对应页面。',
				'tile'  => false,
			]
		);
		foreach ( $catalog as $file => $meta ) {
			Wenpai_Admin_UI::row(
				[
					'title'      => $meta['title'],
					'desc'       => $meta['desc'],
					'right_html' => Wenpai_Admin_UI::button_html(
						[
							'label'   => '打开',
							'href'    => $file,
							'variant' => 'secondary',
							'size'    => 'sm',
						]
					),
				]
			);
		}
		Wenpai_Admin_UI::card_close();
		echo '</div>';
		Wenpai_Admin_Shell::close(
			[
				'footer_left'  => '文派心思 WPMind v' . WPMIND_VERSION,
				'footer_right' => 'kit wenpai-admin-ui v0.13.7 · php prototypes/wpmind/build.php',
			]
		);
		return (string) ob_get_clean();
	}

	$catalog = [
		'overview.html'       => [
			'title' => '概览',
			'desc'  => '1:1 过审版概览：内缩四格带蓝图标统计条、AI 服务动态对称双栏（Provider 健康 + 单行最近活动）与底部卡级模块健康折叠。',
			'get'   => [ 'tab' => 'overview' ],
		],
		'cloud.html'          => [
			'title' => 'AI 服务 · 心思 AI',
			'desc'  => 'AI 服务默认首屏：四格点数状态条、官方算力开关与混用策略卡、两张折叠式换算与生态说明卡。',
			'get'   => [ 'tab' => 'cloud', 'mode' => 'simple' ],
		],
		'services.html'       => [
			'title' => 'AI 服务 · Provider 服务',
			'desc'  => '清爽渐进式披露：已配置文本/图像 Provider 置顶，未配置服务默认收纳折叠，支持简单/高级模式与底部 savebar。',
			'get'   => [ 'tab' => 'services', 'mode' => 'simple' ],
		],
		'routing.html'        => [
			'title' => 'AI 服务 · 智能路由',
			'desc'  => '顶部策略条 + 路由参数卡 + 运行状态（当前路由与故障转移链）+ 优先级排序与策略得分排名。',
			'get'   => [ 'tab' => 'routing' ],
		],
		'modules.html'        => [
			'title' => '模块',
			'desc'  => '上方内置模块 module_grid 三列卡片网格 + 下方生态联动插件列表卡。',
			'get'   => [ 'tab' => 'modules' ],
		],
		'module-geo.html'     => [
			'title' => '模块：GEO 优化',
			'desc'  => 'URL 驱动 6 分区 subnav + 爬虫汇总 statbar + llms.txt / AI Sitemap / Schema / 品牌实体 / 爬虫规则。',
			'get'   => [ 'tab' => 'module', 'module' => 'geo', 'section' => 'basics' ],
		],
		'module-gateway.html' => [
			'title' => '模块：API 网关',
			'desc'  => 'URL 驱动 4 分区 subnav + 网关 statbar + 密钥管理 + 接口文档 + 日志。',
			'get'   => [ 'tab' => 'module', 'module' => 'api-gateway', 'section' => 'settings' ],
		],
		'module-cache.html'   => [
			'title' => '模块：精确缓存',
			'desc'  => '缓存 statbar + 7 天趋势 chart_frame + 参数设置与确认门清理。',
			'get'   => [ 'tab' => 'module', 'module' => 'exact-cache' ],
		],
		'analytics.html'      => [
			'title' => '分析',
			'desc'  => '基础用量统计 + URL 驱动趋势图表 / 预算管理 subnav + 4 张 chart_frame。',
			'get'   => [ 'tab' => 'analytics', 'section' => 'charts' ],
		],
		'workflow.html'       => [
			'title' => '任务',
			'desc'  => 'steps 执行步骤 + progressbar + statbar + 任务列表与审核明细确认门。',
			'get'   => [ 'tab' => 'workflow' ],
		],
		'tools.html'          => [
			'title' => '工具',
			'desc'  => '诊断包、端点配置导出、两步确认导入与运行环境版本信息。',
			'get'   => [ 'tab' => 'tools' ],
		],
		'help.html'           => [
			'title' => '帮助',
			'desc'  => '快捷文档入口与折叠式常见问题排查步骤。',
			'get'   => [ 'tab' => 'help' ],
		],
	];

	$outputs = [];
	foreach ( $catalog as $file => $meta ) {
		$outputs[ $file ] = wpmind_proto_wrap( $meta['title'] . ' — 文派心思', wpmind_proto_render( $meta['get'] ) );
	}
	$outputs['index.html'] = wpmind_proto_wrap( '原型索引 — 文派心思 4.0', wpmind_proto_index( $catalog ) );

	if ( $check ) {
		$stale = [];
		foreach ( $outputs as $file => $expected ) {
			$target = $out_dir . '/' . $file;
			if ( ! is_file( $target ) || file_get_contents( $target ) !== $expected ) {
				$stale[] = $file;
			}
		}
		if ( ! empty( $stale ) ) {
			fwrite( STDERR, 'STALE prototypes: ' . implode( ', ', $stale ) . PHP_EOL );
			exit( 1 );
		}
		echo 'OK: ' . count( $outputs ) . ' prototype files are up to date.' . PHP_EOL;
		exit( 0 );
	}

	foreach ( $outputs as $file => $html ) {
		file_put_contents( $out_dir . '/' . $file, $html );
	}
	echo 'Generated ' . count( $outputs ) . ' prototype files in ' . $out_dir . PHP_EOL;
	exit( 0 );
}
