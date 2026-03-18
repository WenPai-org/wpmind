<?php
/**
 * Plugin Name: WPMind
 * Plugin URI: https://wpcy.com/mind
 * Description: 文派心思 - WordPress AI 自定义端点扩展，支持国内外多种 AI 服务
 * Version: 0.11.4
 * Author: 文派心思
 * Author URI: https://wpcy.com/mind
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmind
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Update URI: https://updates.wenpai.net
 *
 * @package WPMind
 */

declare( strict_types=1 );

namespace WPMind;

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 插件常量（防止重复定义）
if ( ! defined( 'WPMIND_VERSION' ) ) {
	define( 'WPMIND_VERSION', '0.11.4' );
}
if ( ! defined( 'WPMIND_PLUGIN_FILE' ) ) {
	define( 'WPMIND_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPMIND_PLUGIN_DIR' ) ) {
	define( 'WPMIND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPMIND_PLUGIN_URL' ) ) {
	define( 'WPMIND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPMIND_PLUGIN_BASENAME' ) ) {
	define( 'WPMIND_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
// Alias for module compatibility.
if ( ! defined( 'WPMIND_PATH' ) ) {
	define( 'WPMIND_PATH', WPMIND_PLUGIN_DIR );
}

/**
 * 插件主类
 *
 * @since 1.0.0
 */
final class WPMind {

	/**
	 * 单例实例
	 *
	 * @var WPMind|null
	 */
	private static ?WPMind $instance = null;

	/**
	 * 自定义端点配置
	 *
	 * @var array
	 */
	private array $custom_endpoints = [];

	/**
	 * 获取单例实例
	 *
	 * @return WPMind
	 */
	public static function instance(): WPMind {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 构造函数
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->load_core();
		$this->load_custom_endpoints();
		$this->load_public_api();
		$this->load_modules();
		$this->init_hooks();
	}

	/**
	 * 加载核心类
	 *
	 * @since 3.2.0
	 */
	private function load_core(): void {
		require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleInterface.php';
		require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleLoader.php';
	}

	/**
	 * 加载公共 API
	 *
	 * @since 2.5.0
	 */
	private function load_public_api(): void {
		// 加载公共 API 类（ErrorHandler 必须在 PublicAPI 之前加载）
		require_once WPMIND_PLUGIN_DIR . 'includes/API/ErrorHandler.php';
		require_once WPMIND_PLUGIN_DIR . 'includes/API/PublicAPI.php';
		require_once WPMIND_PLUGIN_DIR . 'includes/API/functions.php';

		// 初始化 API 单例
		\WPMind\API\PublicAPI::instance();

		// 开发环境：加载测试端点
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( WPMIND_PLUGIN_DIR . 'tests/ajax-test-endpoint.php' ) ) {
			define( 'WPMIND_DEV_MODE', true );
			require_once WPMIND_PLUGIN_DIR . 'tests/ajax-test-endpoint.php';
		}
	}

	/**
	 * 加载模块
	 *
	 * @since 3.2.0
	 */
	private function load_modules(): void {
		$module_loader = \WPMind\Core\ModuleLoader::instance();
		$module_loader->init();

		/**
		 * Fires after all modules are loaded.
		 *
		 * @since 3.2.0
		 */
		do_action( 'wpmind_loaded' );
	}

	/**
	 * 禁止克隆
	 */
	private function __clone() {}

	/**
	 * 禁止反序列化
	 *
	 * @throws \Exception 禁止反序列化
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * 加载翻译文件
	 *
	 * @since 1.1.0
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'wpmind',
			false,
			dirname( plugin_basename( WPMIND_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * 加载自定义端点配置
	 */
	private function load_custom_endpoints(): void {
		$saved    = get_option( 'wpmind_custom_endpoints', [] );
		$defaults = $this->get_default_endpoints();

		// 合并默认配置和保存的配置
		$this->custom_endpoints = [];
		foreach ( $defaults as $key => $default ) {
			$this->custom_endpoints[ $key ] = wp_parse_args(
				$saved[ $key ] ?? [],
				$default
			);
			// 确保 models 是数组
			if ( ! is_array( $this->custom_endpoints[ $key ]['models'] ) ) {
				$this->custom_endpoints[ $key ]['models'] = array_filter(
					array_map( 'trim', explode( ',', (string) $this->custom_endpoints[ $key ]['models'] ) )
				);
			}
		}
	}

	/**
	 * 获取默认端点配置
	 *
	 * @return array
	 */
	public function get_default_endpoints(): array {
		return [
			// WordPress 官方服务
			'openai'      => [
				'name'         => 'OpenAI',
				'display_name' => 'ChatGPT',
				'icon'         => 'openai',
				'base_url'     => 'https://api.openai.com/v1',
				'models'       => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ],
				'enabled'      => false,
				'api_key'      => '',
				'is_official'  => true,
			],
			'anthropic'   => [
				'name'         => 'Anthropic',
				'display_name' => 'Claude',
				'icon'         => 'claude',
				'base_url'     => 'https://api.anthropic.com/v1',
				'models'       => [ 'claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229' ],
				'enabled'      => false,
				'api_key'      => '',
				'is_official'  => true,
			],
			'google'      => [
				'name'         => 'Google AI',
				'display_name' => 'Gemini',
				'icon'         => 'gemini',
				'base_url'     => 'https://generativelanguage.googleapis.com/v1beta',
				'models'       => [ 'gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash' ],
				'enabled'      => false,
				'api_key'      => '',
				'is_official'  => true,
			],

			// 国内 AI 服务
			'deepseek'    => [
				'name'         => 'DeepSeek',
				'display_name' => 'DeepSeek',
				'icon'         => 'deepseek',
				'base_url'     => 'https://api.deepseek.com/v1',
				'models'       => [ 'deepseek-chat', 'deepseek-coder', 'deepseek-reasoner' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'qwen'        => [
				'name'         => '通义千问',
				'display_name' => 'Qwen',
				'icon'         => 'qwen',
				'base_url'     => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
				'models'       => [ 'qwen-turbo', 'qwen-plus', 'qwen-max' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'zhipu'       => [
				'name'         => '智谱 AI',
				'display_name' => 'ChatGLM',
				'icon'         => 'zhipu',
				'base_url'     => 'https://open.bigmodel.cn/api/paas/v4',
				'models'       => [ 'glm-4', 'glm-4-flash', 'glm-4-plus' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'moonshot'    => [
				'name'         => 'Moonshot',
				'display_name' => 'Kimi',
				'icon'         => 'kimi',
				'base_url'     => 'https://api.moonshot.cn/v1',
				'models'       => [ 'moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'doubao'      => [
				'name'         => '豆包',
				'display_name' => 'Doubao',
				'icon'         => 'doubao',
				'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3',
				'models'       => [ 'doubao-pro-4k', 'doubao-pro-32k', 'doubao-pro-128k' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'siliconflow' => [
				'name'         => '硅基流动',
				'display_name' => 'SiliconFlow',
				'icon'         => 'siliconcloud',
				'base_url'     => 'https://api.siliconflow.cn/v1',
				'models'       => [ 'deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'baidu'       => [
				'name'         => '百度文心',
				'display_name' => 'ERNIE',
				'icon'         => 'wenxin',
				'base_url'     => 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop',
				'models'       => [ 'ernie-4.0-8k', 'ernie-3.5-8k', 'ernie-speed-8k' ],
				'enabled'      => false,
				'api_key'      => '',
			],
			'minimax'     => [
				'name'         => 'MiniMax',
				'display_name' => 'MiniMax',
				'icon'         => 'minimax',
				'base_url'     => 'https://api.minimax.chat/v1',
				'models'       => [ 'abab6.5s-chat', 'abab6.5-chat', 'abab5.5-chat' ],
				'enabled'      => false,
				'api_key'      => '',
			],
		];
	}

	/**
	 * 初始化钩子
	 */
	private function init_hooks(): void {
		// 管理后台
		if ( is_admin() || wp_doing_ajax() ) {
			Admin\AdminBoot::instance()->init();
		}

		// AI 过滤器 - 对齐官方 WordPress AI 插件 filter hook
		add_filter( 'ai_experiments_preferred_models_for_text_generation', [ $this, 'filter_preferred_models' ] );
		add_filter( 'wp_ai_client_default_request_timeout', [ $this, 'filter_request_timeout' ] );
		$this->init_mcp_gateway();

		// 图像生成能力
		add_filter( 'ai_experiments_image_generation_handler', [ $this, 'handle_image_generation' ], 10, 2 );

		// HTTP API 钩子 - 追踪 AI 请求结果
		add_action( 'http_api_debug', [ $this, 'track_ai_request_result' ], 10, 5 );

		// 智能路由集成
		$this->init_routing_hooks();
	}

	/**
	 * 初始化智能路由钩子
	 *
	 * @since 3.2.0
	 */
	private function init_routing_hooks(): void {
		// 初始化路由钩子（类通过 autoloader 自动加载）
		\WPMind\Routing\RoutingHooks::instance();
	}


	/**
	 * 过滤首选模型
	 *
	 * 集成故障转移：排除熔断中的 Provider
	 *
	 * @param array $models 现有模型列表
	 * @return array 合并后的模型列表
	 */
	public function filter_preferred_models( array $models ): array {
		$custom_models = [];
		$failover      = Failover\FailoverManager::instance();

		foreach ( $this->custom_endpoints as $key => $endpoint ) {
			if ( empty( $endpoint['enabled'] ) || empty( $endpoint['api_key'] ) ) {
				continue;
			}

			// 检查熔断器状态，排除不可用的 Provider（使用只读方法避免状态转换）
			$breaker = $failover->get_circuit_breaker( $key );
			if ( $breaker && ! $breaker->is_available_read_only() ) {
				continue;
			}

			foreach ( (array) $endpoint['models'] as $model ) {
				$model = trim( $model );
				if ( ! empty( $model ) ) {
					$custom_models[] = [ $key, $model ];
				}
			}
		}

		return array_merge( $custom_models, $models );
	}

	/**
	 * 过滤请求超时
	 *
	 * @param int $timeout 默认超时
	 * @return int 配置的超时值
	 */
	public function filter_request_timeout( int $timeout ): int {
		$custom_timeout = get_option( 'wpmind_request_timeout' );
		return ! empty( $custom_timeout ) ? (int) $custom_timeout : $timeout;
	}

	/**
	 * 初始化 MCP Gateway
	 *
	 * @return void
	 */
	private function init_mcp_gateway(): void {
		if ( ! class_exists( '\WPMind\MCP\Gateway' ) ) {
			return;
		}

		\WPMind\MCP\Gateway::instance()->init();
	}

	/**
	 * 处理图像生成请求
	 *
	 * 连接 AI Experiments Image Generation 能力与 WPMind 图像路由器
	 *
	 * @param mixed  $result 原始结果
	 * @param string $prompt 图像生成提示词
	 * @return array 图像生成结果
	 * @since 2.4.0
	 */
	public function handle_image_generation( $result, string $prompt ): array {
		// 如果已有结果，直接返回
		if ( ! empty( $result ) && is_array( $result ) && ! empty( $result['url'] ) ) {
			return $result;
		}

		// 检查是否有可用的图像服务
		$image_endpoints = get_option( 'wpmind_image_endpoints', [] );
		$has_enabled     = false;

		foreach ( $image_endpoints as $config ) {
			if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
				$has_enabled = true;
				break;
			}
		}

		if ( ! $has_enabled ) {
			return [
				'success' => false,
				'error'   => __( '没有配置图像生成服务', 'wpmind' ),
			];
		}

		// 使用图像路由器
		$router = Providers\Image\ImageRouter::instance();

		return $router->generate(
			$prompt,
			[
				'size' => '1024x1024',
			]
		);
	}

	/**
	 * 追踪 AI 请求结果
	 *
	 * 通过 HTTP API 钩子监控发往 AI 服务的请求，记录成功/失败状态
	 *
	 * @param array|\WP_Error $response HTTP 响应或错误
	 * @param string          $context  请求上下文
	 * @param string          $class    传输类名
	 * @param array           $parsed_args 请求参数
	 * @param string          $url      请求 URL
	 * @since 1.5.0
	 */
	public function track_ai_request_result( $response, string $context, string $class, array $parsed_args, string $url ): void {
		// 只处理响应阶段
		if ( $context !== 'response' ) {
			return;
		}

		// 跳过已标记的请求（避免与手动 record_result 双重计数）
		if ( ! empty( $parsed_args['_wpmind_skip_tracking'] ) ) {
			return;
		}

		// 识别 AI Provider
		$provider = $this->identify_provider_from_url( $url );
		if ( ! $provider ) {
			return;
		}

		// 计算延迟（从请求开始时间，如果可用）
		$latency_ms = 0;
		if ( isset( $parsed_args['_wpmind_start_time'] ) ) {
			$latency_ms = (int) ( ( microtime( true ) - $parsed_args['_wpmind_start_time'] ) * 1000 );
		}

		// 判断成功/失败
		$success = false;
		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			$success     = ( $status_code >= 200 && $status_code < 300 );

			// 记录 Token 用量
			if ( $success ) {
				$this->track_token_usage( $response, $provider, $latency_ms );
			}
		}

		// 记录结果
		Failover\FailoverManager::instance()->record_result( $provider, $success, $latency_ms );
	}

	/**
	 * 追踪 Token 用量
	 *
	 * 使用事件驱动架构，触发 wpmind_usage_record action hook
	 * Cost Control 模块会监听此 hook 并处理用量记录
	 *
	 * @param array  $response HTTP 响应
	 * @param string $provider Provider ID
	 * @param int    $latency_ms 延迟（毫秒）
	 * @since 1.6.0
	 * @since 3.3.0 改用事件驱动架构
	 */
	private function track_token_usage( array $response, string $provider, int $latency_ms ): void {
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		// 提取 usage 信息
		$usage = $data['usage'] ?? null;
		if ( ! $usage ) {
			return;
		}

		// 兼容不同 Provider 的格式
		// OpenAI/国内服务: prompt_tokens / completion_tokens
		// Anthropic: input_tokens / output_tokens
		$input_tokens  = (int) ( $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0 );
		$output_tokens = (int) ( $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0 );

		// 验证 tokens 非负
		$input_tokens  = max( 0, $input_tokens );
		$output_tokens = max( 0, $output_tokens );

		if ( $input_tokens === 0 && $output_tokens === 0 ) {
			return;
		}

		// 提取模型名称
		$model = $data['model'] ?? 'unknown';

		/**
		 * 触发用量记录事件
		 *
		 * Cost Control 模块会监听此 hook 并处理：
		 * - 记录用量统计
		 * - 检查预算限制
		 * - 发送告警通知
		 *
		 * @since 3.3.0
		 *
		 * @param string $provider Provider ID
		 * @param string $model 模型名称
		 * @param int    $input_tokens 输入 tokens
		 * @param int    $output_tokens 输出 tokens
		 * @param int    $latency_ms 延迟（毫秒）
		 */
		do_action( 'wpmind_usage_record', $provider, $model, $input_tokens, $output_tokens, $latency_ms );
	}

	/**
	 * 从 URL 识别 AI Provider
	 *
	 * 支持默认域名和用户自定义的 base_url
	 *
	 * @param string $url 请求 URL
	 * @return string|null Provider ID 或 null
	 */
	private function identify_provider_from_url( string $url ): ?string {
		// 默认域名模式
		$default_patterns = [
			'openai'      => 'api.openai.com',
			'anthropic'   => 'api.anthropic.com',
			'google'      => 'generativelanguage.googleapis.com',
			'deepseek'    => 'api.deepseek.com',
			'qwen'        => 'dashscope.aliyuncs.com',
			'zhipu'       => 'open.bigmodel.cn',
			'moonshot'    => 'api.moonshot.cn',
			'doubao'      => 'ark.cn-beijing.volces.com',
			'siliconflow' => 'api.siliconflow.cn',
			'baidu'       => 'aip.baidubce.com',
			'minimax'     => 'api.minimax.chat',
		];

		// 首先检查用户自定义的 base_url
		foreach ( $this->custom_endpoints as $provider => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}

			// 检查自定义 URL
			if ( ! empty( $config['custom_base_url'] ) && str_contains( $url, wp_parse_url( $config['custom_base_url'], PHP_URL_HOST ) ) ) {
				return $provider;
			}

			// 检查默认 base_url
			if ( ! empty( $config['base_url'] ) && str_contains( $url, wp_parse_url( $config['base_url'], PHP_URL_HOST ) ) ) {
				return $provider;
			}
		}

		// 回退到默认域名模式
		foreach ( $default_patterns as $provider => $pattern ) {
			if ( str_contains( $url, $pattern ) ) {
				if ( isset( $this->custom_endpoints[ $provider ] ) && ! empty( $this->custom_endpoints[ $provider ]['enabled'] ) ) {
					return $provider;
				}
			}
		}

		return null;
	}


	/**
	 * 获取自定义端点配置
	 *
	 * @return array 端点配置
	 */
	public function get_custom_endpoints(): array {
		return $this->custom_endpoints;
	}

	/**
	 * 检查端点是否已配置 API Key
	 *
	 * @param string $endpoint_key 端点标识
	 * @return bool 是否已配置
	 * @since 1.2.0
	 */
	public function has_api_key( string $endpoint_key ): bool {
		return ! empty( $this->custom_endpoints[ $endpoint_key ]['api_key'] );
	}

	/**
	 * 获取指定端点的 API Key
	 *
	 * @param string $endpoint_key 端点标识
	 * @return string API Key
	 * @since 1.1.0
	 */
	public function get_api_key( string $endpoint_key ): string {
		return $this->custom_endpoints[ $endpoint_key ]['api_key'] ?? '';
	}

	/**
	 * 检查端点是否可用
	 *
	 * @param string $endpoint_key 端点标识
	 * @return bool 是否可用
	 * @since 1.1.0
	 */
	public function is_endpoint_available( string $endpoint_key ): bool {
		if ( ! isset( $this->custom_endpoints[ $endpoint_key ] ) ) {
			return false;
		}

		$endpoint = $this->custom_endpoints[ $endpoint_key ];
		return ! empty( $endpoint['enabled'] ) && ! empty( $endpoint['api_key'] );
	}
}

/**
 * 获取插件实例
 *
 * @return WPMind 插件实例
 */
function wpmind(): WPMind {
	return WPMind::instance();
}

/**
 * 插件激活钩子
 *
 * @since 1.1.0
 */
function activate(): void {
	if ( false === get_option( 'wpmind_request_timeout' ) ) {
		add_option( 'wpmind_request_timeout', 60, '', false ); // autoload = false
	}

	// Schedule rewrite rules flush for after plugin is fully loaded.
	// This ensures module routes are registered before flushing.
	add_option( 'wpmind_flush_rewrite_rules', '1' );
}
register_activation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\activate' );

/**
 * 插件停用钩子
 *
 * @since 1.1.0
 */
function deactivate(): void {
	// Flush rewrite rules to remove plugin routes.
	flush_rewrite_rules();
}
register_deactivation_hook( WPMIND_PLUGIN_FILE, __NAMESPACE__ . '\\deactivate' );

// 初始化插件
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpmind' );

// Flush rewrite rules after plugin activation (delayed to ensure routes are registered).
// Use admin_init for admin requests, or a later init priority for frontend.
add_action(
	'admin_init',
	function (): void {
		if ( get_option( 'wpmind_flush_rewrite_rules' ) === '1' ) {
			delete_option( 'wpmind_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}
);

// Also check on frontend requests with high priority.
add_action(
	'wp_loaded',
	function (): void {
		if ( get_option( 'wpmind_flush_rewrite_rules' ) === '1' ) {
			delete_option( 'wpmind_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}
);

/**
 * PSR-4 自动加载器
 *
 * @since 1.3.0
 */
spl_autoload_register(
	function ( string $class ): void {
		// WPMind 根命名空间
		$prefix = 'WPMind\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
		}

		// 获取相对类名
		$relative_class = substr( $class, $len );

		// 转换为文件路径
		$file = WPMIND_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// 加载 WenPai 更新器（跨插件共享组件，不走 PSR-4）
require_once WPMIND_PLUGIN_DIR . 'includes/class-wenpai-updater.php';
new \WenPai_Updater( WPMIND_PLUGIN_BASENAME, WPMIND_VERSION );

// 加载 WenPai 授权客户端
require_once WPMIND_PLUGIN_DIR . 'includes/class-wenpai-license.php';

/**
 * 获取 WPMind 授权实例。
 *
 * @since 4.0.0
 * @return \WenPai_License
 */
function wpmind_license(): \WenPai_License {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new \WenPai_License( 'wpmind' );
	}
	return $instance;
}

// 加载 Provider 注册模块
require_once WPMIND_PLUGIN_DIR . 'includes/Providers/register.php';
