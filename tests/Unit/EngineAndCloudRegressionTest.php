<?php
/**
 * WPMind 核心引擎与官方算力回归测试（Worker 1）。
 *
 * 覆盖：auto 进路由、预算 disable 拦截与 downgrade 换模、WenpaiCloud 状态与响应头解析、
 * 锁 Key、WP 7 Connectors 回退、ProviderRegistrar、ProviderClient 熔断保护与真实状态码、
 * ExactCache 模块联动、契约修复、MCP 开关与内容能力、filter 弱耦合包装。
 *
 * 测试里的 Key / Token 均为假占位串，不发起任何真实网络请求。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\API\PublicAPI;
use WPMind\API\Services\ChatService;
use WPMind\API\Services\EmbeddingService;
use WPMind\API\Services\VisionHelper;
use WPMind\Cache\ExactCache;
use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\MCP\Gateway;
use WPMind\Provider\Catalog\ModelCatalog;
use WPMind\Provider\Diagnostics;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderClient;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\WenpaiCloud;
use WPMind\Providers\Image\GeminiImageProvider;
use WPMind\Providers\Image\ImageRouter;
use WPMind\Providers\Image\OpenAIImageProvider;
use WPMind\Providers\ProviderRegistrar;
use WPMind\Routing\IntelligentRouter;
use WPMind\Routing\RoutingHooks;

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null ): string {
		return gmdate( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'http://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = [] ) {
		$args['method'] = 'POST';
		return wp_remote_request( $url, $args );
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( int $min = 0, int $max = 0 ): int {
		return $min;
	}
}

/**
 * 核心引擎与官方算力回归测试集。
 */
final class EngineAndCloudRegressionTest extends TestCase {

	private const FAKE_OPENAI_KEY   = 'sk-fake-openai-engine-0001';
	private const FAKE_DEEPSEEK_KEY = 'sk-fake-deepseek-engine-0002';
	private const FAKE_QWEN_KEY     = 'sk-fake-qwen-engine-0003';
	private const FAKE_LOCKED_KEY   = 'sk-fake-locked-agency-9999';
	private const FAKE_CONN_KEY     = 'sk-fake-connector-wp7-7777';
	private const FAKE_CLOUD_TOKEN  = 'wmc_fake_cloud_token_123456';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = [];
		$GLOBALS['wpmind_test_transients'] = [];
		$GLOBALS['wpmind_test_actions']    = [];
		$GLOBALS['wpmind_test_autoload']   = [];
		$GLOBALS['wpmind_test_filters']    = [];
		unset( $GLOBALS['wpmind_test_http_handler'] );

		$this->reset_singletons();
	}

	protected function tearDown(): void {
		$GLOBALS['wpmind_test_options']    = [];
		$GLOBALS['wpmind_test_transients'] = [];
		$GLOBALS['wpmind_test_actions']    = [];
		$GLOBALS['wpmind_test_filters']    = [];
		unset( $GLOBALS['wpmind_test_http_handler'] );

		$this->reset_singletons();
	}

	private function reset_singletons(): void {
		$singletons = [
			FailoverManager::class   => 'instance',
			IntelligentRouter::class => 'instance',
			RoutingHooks::class      => 'instance',
			ExactCache::class        => 'instance',
			ImageRouter::class       => 'instance',
			Gateway::class           => 'instance',
		];
		foreach ( $singletons as $class => $prop_name ) {
			if ( class_exists( $class ) ) {
				$ref  = new ReflectionClass( $class );
				$prop = $ref->getProperty( $prop_name );
				$prop->setAccessible( true );
				$prop->setValue( null, null );
			}
		}

		if ( class_exists( '\\WPMind\\Modules\\CostControl\\BudgetChecker' ) ) {
			$ref  = new ReflectionClass( '\\WPMind\\Modules\\CostControl\\BudgetChecker' );
			$prop = $ref->getProperty( 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}
		if ( class_exists( '\\WPMind\\Modules\\CostControl\\BudgetManager' ) ) {
			$ref  = new ReflectionClass( '\\WPMind\\Modules\\CostControl\\BudgetManager' );
			$prop = $ref->getProperty( 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}

		ModelCatalog::reset_instance();
	}

	private function load_cost_control_classes(): void {
		require_once WPMIND_PLUGIN_DIR . 'modules/cost-control/includes/BudgetManager.php';
		require_once WPMIND_PLUGIN_DIR . 'modules/cost-control/includes/UsageTracker.php';
		require_once WPMIND_PLUGIN_DIR . 'modules/cost-control/includes/BudgetChecker.php';
	}

	private function seed_endpoints( array $entries ): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = $entries;
		$this->reset_singletons();
	}

	/**
	 * 1. auto 进路由：'auto' 原样交给 wpmind_select_provider；过滤后若仍为 auto/空/不可用，依次回退 default 与首个可用端点。
	 */
	public function test_auto_provider_reaches_routing_filter_and_falls_back_cleanly(): void {
		$this->seed_endpoints(
			[
				'openai'   => [
					'enabled' => true,
					'api_key' => self::FAKE_OPENAI_KEY,
				],
				'deepseek' => [
					'enabled' => true,
					'api_key' => self::FAKE_DEEPSEEK_KEY,
				],
			]
		);
		update_option( 'wpmind_default_provider', 'openai' );
		update_option(
			'wpmind_routing_settings',
			[
				'enabled'  => true,
				'strategy' => 'economic',
			]
		);
		$this->reset_singletons();

		$seen_input = null;
		$hooks      = RoutingHooks::instance();
		$GLOBALS['wpmind_test_filters']['wpmind_select_provider'] = static function ( $provider, $context ) use ( &$seen_input, $hooks ) {
			$seen_input = $provider;
			return $hooks->select_provider( (string) $provider, (string) $context );
		};

		$service = new ChatService();
		$method  = new ReflectionMethod( ChatService::class, 'resolve_provider' );
		$method->setAccessible( true );

		$selected = $method->invoke( $service, 'auto', 'blog_post' );
		self::assertSame( 'auto', $seen_input, 'resolve_provider must pass literal auto to wpmind_select_provider' );
		// economic 策略下 deepseek 价格远低于 openai，应真实按策略打分选中 deepseek 而非硬退 default_provider(openai).
		self::assertSame( 'deepseek', $selected );

		// Filter 返回 'auto' 且 default 不可用时，应回退首个可用端点。
		update_option( 'wpmind_default_provider', 'qwen' ); // 未配置.
		$GLOBALS['wpmind_test_filters']['wpmind_select_provider'] = static fn( $p ) => 'auto';
		$fallback = $method->invoke( $service, 'auto', 'blog_post' );
		self::assertSame( 'openai', $fallback );

		// Filter 返回不可用端点时，应先回退可用的 default_provider。
		update_option( 'wpmind_default_provider', 'deepseek' );
		$GLOBALS['wpmind_test_filters']['wpmind_select_provider'] = static fn( $p ) => 'zhipu';
		$fallback_default = $method->invoke( $service, 'auto', 'blog_post' );
		self::assertSame( 'deepseek', $fallback_default );
	}

	/**
	 * 2a. 预算 disable 模式超限：拦截上游请求，触发 wpmind_budget_exceeded 并返回 WP_Error(wpmind_budget_exceeded)。
	 */
	public function test_budget_disable_blocks_chat_and_embed_before_upstream(): void {
		$this->load_cost_control_classes();
		$this->seed_endpoints(
			[
				'openai' => [
					'enabled' => true,
					'api_key' => self::FAKE_OPENAI_KEY,
				],
			]
		);
		update_option( 'wpmind_default_provider', 'openai' );
		update_option( 'wpmind_exact_cache_enabled', '0' );

		$today = wp_date( 'Y-m-d' );
		update_option(
			'wpmind_budget_settings',
			[
				'enabled'          => true,
				'enforcement_mode' => 'disable',
				'global'           => [
					'daily_limit_usd' => 1.0,
				],
			]
		);
		update_option(
			'wpmind_usage_stats',
			[
				'daily' => [
					$today => [
						'cost_usd' => 2.5,
					],
				],
			]
		);

		$http_calls = 0;
		$GLOBALS['wpmind_test_http_handler'] = static function () use ( &$http_calls ) {
			++$http_calls;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => '{}',
				'headers'  => [],
			];
		};

		$chat   = new ChatService();
		$result = $chat->chat( 'hello', [ 'context' => 'wpslug_test', 'cache_ttl' => -1 ] );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'wpmind_budget_exceeded', $result->get_error_code() );
		self::assertSame( 0, $http_calls, 'upstream must not be called when budget disables request' );
		self::assertNotEmpty( $GLOBALS['wpmind_test_actions']['wpmind_budget_exceeded'] ?? [] );
		self::assertSame( 'wpslug_test', $GLOBALS['wpmind_test_actions']['wpmind_budget_exceeded'][0][1] );

		// Embed 路径同样受预算拦截。
		$embed_service = new EmbeddingService();
		$embed_res     = $embed_service->embed( 'hello world', [ 'context' => 'wpdocs_rag' ] );
		self::assertInstanceOf( WP_Error::class, $embed_res );
		self::assertSame( 'wpmind_budget_exceeded', $embed_res->get_error_code() );
		self::assertSame( 0, $http_calls );
	}

	/**
	 * 2b. 预算 downgrade 模式超限：允许请求但本次强制改用 fallback_model，同时发 7 参数用量事件。
	 */
	public function test_budget_downgrade_swaps_model_and_fires_7_arg_usage_event(): void {
		$this->load_cost_control_classes();
		$this->seed_endpoints(
			[
				'openai' => [
					'enabled' => true,
					'api_key' => self::FAKE_OPENAI_KEY,
					'models'  => [ 'gpt-4o' ],
				],
			]
		);
		update_option( 'wpmind_default_provider', 'openai' );
		update_option( 'wpmind_exact_cache_enabled', '0' );

		$today = wp_date( 'Y-m-d' );
		update_option(
			'wpmind_budget_settings',
			[
				'enabled'          => true,
				'enforcement_mode' => 'downgrade',
				'global'           => [
					'daily_limit_usd' => 1.0,
				],
			]
		);
		update_option(
			'wpmind_usage_stats',
			[
				'daily' => [
					$today => [
						'cost_usd' => 2.5,
					],
				],
			]
		);

		$sent_model = null;
		$GLOBALS['wpmind_test_http_handler'] = static function ( string $url, array $args ) use ( &$sent_model ) {
			$payload    = json_decode( (string) ( $args['body'] ?? '' ), true );
			$sent_model = $payload['model'] ?? null;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						'model'   => $sent_model,
						'usage'   => [
							'prompt_tokens'     => 10,
							'completion_tokens' => 5,
							'total_tokens'      => 15,
						],
						'choices' => [
							[ 'message' => [ 'content' => 'ok' ] ],
						],
					]
				),
				'headers'  => [],
			];
		};

		$chat   = new ChatService();
		$result = $chat->chat(
			'hello',
			[
				'provider'  => 'openai',
				'model'     => 'gpt-4o',
				'context'   => 'bisheng_draft',
				'cache_ttl' => -1,
			]
		);

		self::assertIsArray( $result );
		self::assertSame( 'gpt-4o-mini', $sent_model, 'downgrade mode must send fallback_model upstream' );

		$usage_events = $GLOBALS['wpmind_test_actions']['wpmind_usage_record'] ?? [];
		self::assertCount( 1, $usage_events );
		self::assertCount( 7, $usage_events[0] );
		self::assertSame( 'openai', $usage_events[0][0] );
		self::assertSame( 'gpt-4o-mini', $usage_events[0][1] );
		self::assertSame( 'bisheng_draft', $usage_events[0][5] );
		self::assertSame( 'byok', $usage_events[0][6] );
	}

	/**
	 * 3. WenpaiCloud 状态、令牌派生、响应头余额解析与 ProviderClient 联动。
	 */
	public function test_wenpai_cloud_status_headers_and_client_integration(): void {
		self::assertSame( 'https://mind-api.wenpai.net/v1', WenpaiCloud::base_url() );
		self::assertSame( '心思点数', WenpaiCloud::unit_label() );
		self::assertFalse( WenpaiCloud::is_enabled() );
		self::assertFalse( WenpaiCloud::is_available() );

		// 过滤器覆盖网关地址并去掉末尾斜杠。
		$GLOBALS['wpmind_test_filters']['wpmind_cloud_base_url'] = static fn() => 'https://custom-mind.wenpai.net/v1/';
		self::assertSame( 'https://custom-mind.wenpai.net/v1', WenpaiCloud::base_url() );

		// 启用但无 token 时不可用、不进 enabled() / 路由。
		update_option( WenpaiCloud::OPTION_ENABLED, '1' );
		self::assertTrue( WenpaiCloud::is_enabled() );
		self::assertFalse( WenpaiCloud::is_available() );
		self::assertArrayNotHasKey( WenpaiCloud::PROVIDER_ID, ( new EndpointRepository() )->enabled() );

		// option 令牌回退与 wenpai_sign_cloud_request 优先级。
		update_option( WenpaiCloud::OPTION_TOKEN, 'wmc_option_fallback' );
		self::assertSame( 'wmc_option_fallback', WenpaiCloud::derive_request_token() );

		$GLOBALS['wpmind_test_filters']['wenpai_sign_cloud_request'] = static function ( $token, $args ) {
			self::assertSame( 'wpmind:gateway', $args['scope'] ?? '' );
			self::assertSame( 'https://custom-mind.wenpai.net/v1', $args['target'] ?? '' );
			return self::FAKE_CLOUD_TOKEN;
		};
		self::assertSame( self::FAKE_CLOUD_TOKEN, WenpaiCloud::derive_request_token() );
		self::assertTrue( WenpaiCloud::is_available() );
		self::assertArrayHasKey( WenpaiCloud::PROVIDER_ID, ( new EndpointRepository() )->enabled() );

		// 响应头解析：同时验证数组与类似 CaseInsensitiveDictionary 的对象，且 autoload=false。
		WenpaiCloud::update_balance_from_headers(
			[
				'X-WPMind-Credits-Remaining' => '4200',
				'X-WPMind-Credits-Limit'     => '10000',
			]
		);
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ WenpaiCloud::OPTION_CREDITS ] );

		$dict_stub = new ArrayObject(
			[
				'x-wpmind-credits-remaining' => '3980',
				'x-wpmind-credits-limit'     => '10000',
			]
		);
		WenpaiCloud::update_balance_from_headers( $dict_stub );

		// get_status() 零远程请求，返回全部 11 个固定键。
		$GLOBALS['wpmind_test_filters']['wenpai_site_binding'] = static fn() => [
			'bound'      => true,
			'managed'    => true,
			'site_uuid'  => 'site-uuid-abc',
			'manage_url' => 'https://example.org/wp-admin/admin.php?page=wpcy-suite',
		];

		$status = WenpaiCloud::get_status();
		$keys   = [
			'enabled',
			'bound',
			'managed_by_suite',
			'site_uuid',
			'credits_balance',
			'credits_used_month',
			'credits_limit_month',
			'unit_label',
			'base_url',
			'bind_url',
			'topup_url',
		];
		foreach ( $keys as $key ) {
			self::assertArrayHasKey( $key, $status );
		}
		self::assertTrue( $status['enabled'] );
		self::assertTrue( $status['bound'] );
		self::assertTrue( $status['managed_by_suite'] );
		self::assertSame( 'site-uuid-abc', $status['site_uuid'] );
		self::assertSame( 3980, $status['credits_balance'] );
		self::assertSame( 10000, $status['credits_limit_month'] );
		self::assertSame( '心思点数', $status['unit_label'] );
		self::assertSame( 'https://example.org/wp-admin/admin.php?page=wpcy-suite', $status['bind_url'] );
		self::assertSame( 'https://wenpai.net/mind-credits', $status['topup_url'] );

		// ProviderClient 调用 wenpai_cloud：回写响应头余额 + payer=wenpai_cloud。
		$GLOBALS['wpmind_test_http_handler'] = static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						'model' => 'auto',
						'usage' => [
							'prompt_tokens'     => 20,
							'completion_tokens' => 10,
						],
					]
				),
				'headers'  => [
					'x-wpmind-credits-remaining' => '3950',
				],
			];
		};

		$client = new ProviderClient();
		$res    = $client->request( WenpaiCloud::PROVIDER_ID, 'POST', 'chat/completions', [ 'context' => 'wpicp_rewrite' ] );
		self::assertTrue( $res['ok'] );
		self::assertSame( 3950, WenpaiCloud::get_status()['credits_balance'] );

		$usage = $GLOBALS['wpmind_test_actions']['wpmind_usage_record'] ?? [];
		self::assertCount( 1, $usage );
		self::assertSame( 'wenpai_cloud', $usage[0][6] );
		self::assertSame( 'wpicp_rewrite', $usage[0][5] );

		// ProviderDefinitions 接入且不注册到 WP 7 SDK provider map。
		$def = ProviderDefinitions::get( WenpaiCloud::PROVIDER_ID );
		self::assertNotNull( $def );
		self::assertTrue( (bool) ( $def['is_cloud'] ?? false ) );
		self::assertSame( '文派心思官方算力', $def['name'] );
		self::assertSame( '心思点数', $def['display_name'] );
		self::assertNotContains( WenpaiCloud::PROVIDER_ID, ProviderRegistrar::getSupportedProviderIds() );
	}

	/**
	 * 4. 代理商锁 Key 与 Key 优先级（locked > saved > Connectors）+ Connectors 全链路打通。
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_key_precedence_locked_saved_and_connectors_fallback(): void {
		if ( ! class_exists( 'WP_Connector_Registry' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Isolated subprocess stub for WP 7.x Connectors.
			eval( 'class WP_Connector_Registry {}' );
		}

		// 仅 Connectors 存了 deepseek key（wpmind 自己没配）。
		update_option( 'connectors_ai_deepseek_api_key', self::FAKE_CONN_KEY );
		$this->reset_singletons();

		$repo = new EndpointRepository();
		self::assertTrue( $repo->has_api_key( 'deepseek' ) );
		self::assertSame( self::FAKE_CONN_KEY, $repo->get_api_key( 'deepseek' ) );
		self::assertTrue( PublicAPI::instance()->is_available() );
		self::assertContains( 'deepseek', FailoverManager::instance()->get_failover_chain() );
		self::assertSame( 'deepseek', IntelligentRouter::instance()->route() );

		// wpmind 自存优先于 Connectors。
		update_option(
			EndpointRepository::OPTION,
			[
				'deepseek' => [
					'enabled' => true,
					'api_key' => self::FAKE_DEEPSEEK_KEY,
				],
			]
		);
		self::assertSame( self::FAKE_DEEPSEEK_KEY, $repo->get_api_key( 'deepseek' ) );

		// locked_api_key 优先于 wpmind 自存与 Connectors。
		$GLOBALS['wpmind_test_filters']['wpmind_locked_providers'] = static fn() => [
			'deepseek' => self::FAKE_LOCKED_KEY,
			'qwen',
		];
		self::assertTrue( WenpaiCloud::is_provider_locked( 'deepseek' ) );
		self::assertTrue( WenpaiCloud::is_provider_locked( 'qwen' ) );
		self::assertFalse( WenpaiCloud::is_provider_locked( 'openai' ) );
		self::assertSame( self::FAKE_LOCKED_KEY, WenpaiCloud::locked_api_key( 'deepseek' ) );
		self::assertSame( self::FAKE_LOCKED_KEY, $repo->get_api_key( 'deepseek' ) );
	}

	/**
	 * 5. ProviderClient 非 2xx：skip_record=true 不污染熔断器；skip_record=false 诊断缓冲记录真实 HTTP 状态码。
	 */
	public function test_provider_client_skip_record_and_real_http_status_in_diagnostics(): void {
		$this->seed_endpoints(
			[
				'openai' => [
					'enabled' => true,
					'api_key' => self::FAKE_OPENAI_KEY,
				],
			]
		);

		$GLOBALS['wpmind_test_http_handler'] = static function () {
			return [
				'response' => [ 'code' => 401 ],
				'body'     => '{"error":{"message":"invalid api key"}}',
				'headers'  => [],
			];
		};

		$client = new ProviderClient();
		$probe  = $client->request(
			'openai',
			'GET',
			'models',
			[
				'api_key'     => 'sk-fake-bad-probe',
				'skip_record' => true,
			]
		);
		self::assertFalse( $probe['ok'] );
		self::assertSame( 401, $probe['status'] );
		self::assertSame( CircuitBreaker::STATE_CLOSED, ( new CircuitBreaker( 'openai' ) )->get_state() );
		self::assertSame( [], Diagnostics::recent_errors() );

		// 正常请求（skip_record=false）应写入真实状态码 401。
		$real = $client->request( 'openai', 'GET', 'models' );
		self::assertFalse( $real['ok'] );
		$errors = Diagnostics::recent_errors();
		self::assertNotEmpty( $errors );
		self::assertSame( 401, $errors[0]['status'] ?? null );
	}

	/**
	 * 6. ExactCache::is_enabled() 联动 ModuleLoader 模块开关。
	 */
	public function test_exact_cache_respects_module_loader_enabled_state(): void {
		require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleInterface.php';
		require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleLoader.php';

		update_option( 'wpmind_exact_cache_enabled', '1' );
		update_option( 'wpmind_module_exact-cache_enabled', '0' );
		self::assertFalse( ExactCache::instance()->is_enabled() );

		update_option( 'wpmind_module_exact-cache_enabled', '1' );
		self::assertTrue( ExactCache::instance()->is_enabled() );
	}

	/**
	 * 7. 契约修复 + MCP 开关 + filter 弱耦合包装验证。
	 */
	public function test_contracts_vision_mcp_and_filter_wrappers(): void {
		// 品牌名契约。
		self::assertSame( '千问', ProviderDefinitions::DEFINITIONS['qwen']['name'] );
		$openai_img = new OpenAIImageProvider( 'sk-fake' );
		$gemini_img = new GeminiImageProvider( 'sk-fake' );
		self::assertSame( 'GPT Image', $openai_img->getName() );
		self::assertSame( 'Gemini Image', $gemini_img->getName() );

		// ImageRouter 无服务商时返回 WP_Error。
		$img_res = ImageRouter::instance()->generate( 'a cat' );
		self::assertInstanceOf( WP_Error::class, $img_res );

		// EmbeddingService 不允许 deepseek 进入嵌入故障转移。
		$this->seed_endpoints(
			[
				'deepseek' => [
					'enabled' => true,
					'api_key' => self::FAKE_DEEPSEEK_KEY,
				],
			]
		);
		update_option( 'wpmind_default_provider', 'deepseek' );
		$embed_err = ( new EmbeddingService() )->embed( 'hello' );
		self::assertInstanceOf( WP_Error::class, $embed_err );
		self::assertSame( 'wpmind_embed_not_supported', $embed_err->get_error_code() );

		// VisionHelper 走 ModelCatalog 且本地文件转 data URI。
		self::assertSame( 'gemini-2.0-flash', VisionHelper::get_vision_model( 'google' ) );
		self::assertTrue( VisionHelper::is_local_or_intranet_url( 'http://localhost:8894/img.png' ) );
		self::assertTrue( VisionHelper::is_local_or_intranet_url( 'http://127.0.0.1/img.png' ) );
		self::assertTrue( VisionHelper::is_local_or_intranet_url( 'https://demo.local/img.png' ) );
		self::assertTrue( VisionHelper::is_local_or_intranet_url( 'https://site.test/img.png' ) );
		self::assertFalse( VisionHelper::is_local_or_intranet_url( 'https://cdn.example.com/img.png' ) );

		$tmp_png = sys_get_temp_dir() . '/wpmind-vision-test-' . uniqid( '', true ) . '.png';
		file_put_contents( $tmp_png, 'fake-png-bytes' );
		try {
			$uri = VisionHelper::normalize_image_url( $tmp_png );
			self::assertStringStartsWith( 'data:image/png;base64,', $uri );
		} finally {
			@unlink( $tmp_png );
		}

		// MCP 总开关关闭时不注册任何钩子。
		$GLOBALS['wpmind_test_filters']['wpmind_mcp_enabled'] = static fn() => false;
		$GLOBALS['wpmind_test_actions']                       = [];
		Gateway::instance()->init();
		self::assertArrayNotHasKey( 'mcp_adapter_init', $GLOBALS['wpmind_test_actions'] );

		// functions.php 具备全部 8 个新增同名 filter 弱耦合包装且含防递归守卫。
		$functions_src = (string) file_get_contents( WPMIND_PLUGIN_DIR . 'includes/API/functions.php' );
		foreach (
			[
				'wpmind_structured',
				'wpmind_pinyin',
				'wpmind_summarize',
				'wpmind_moderate',
				'wpmind_vision',
				'wpmind_embed',
				'wpmind_generate_image',
				'wpmind_get_status',
			] as $tag
		) {
			self::assertStringContainsString( "'" . $tag . "'", $functions_src, "missing filter wrapper for {$tag}" );
		}
		self::assertStringContainsString( '$in_filter', $functions_src );

		// ProviderRegistrar 无 custom_base_url 时不要求 enabled+key 即注册（源码契约核验，无 WP7 SDK 依赖）。
		$registrar_src = (string) file_get_contents( WPMIND_PLUGIN_DIR . 'includes/Providers/ProviderRegistrar.php' );
		self::assertStringNotContainsString( "empty( \$endpoints[ \$key ]['enabled'] )", $registrar_src );
		self::assertStringContainsString( "empty( \$endpoints[ \$key ]['custom_base_url'] )", $registrar_src );
	}

	/**
	 * 8. 方案 A 绑定与充值：WPCY 优先（未绑定但有预生成 UUID 时不得误判已绑定）、独立站网页授权换票、留空不清空令牌与带参充值链接。
	 */
	public function test_plan_a_binding_sources_connect_exchange_and_topup_url(): void {
		// 8a. 安装 WPCY 但尚未绑定：WPCY 已预生成 site_uuid 但 bound=false，此时不得误判已绑定，且 bind_url 指向 WPCY manage_url。
		$GLOBALS['wpmind_test_filters']['wenpai_site_binding'] = static fn() => [
			'managed'    => true,
			'bound'      => false,
			'site_uuid'  => '11111111-2222-4333-8444-555555555555',
			'manage_url' => 'https://example.org/wp-admin/admin.php?page=wpcy-services',
		];

		$status_wpcy_unbound = WenpaiCloud::get_status();
		self::assertTrue( $status_wpcy_unbound['managed_by_suite'] );
		self::assertFalse( $status_wpcy_unbound['bound'] );
		self::assertSame( 'none', $status_wpcy_unbound['binding_source'] );
		self::assertSame( 'https://example.org/wp-admin/admin.php?page=wpcy-services', $status_wpcy_unbound['bind_url'] );

		// 8b. 未安装 WPCY（独立站）：生成 connect_url、保存独立令牌（autoload=false）、识别为 standalone、解除绑定后恢复 none。
		unset( $GLOBALS['wpmind_test_filters']['wenpai_site_binding'] );
		$connect_url = WenpaiCloud::connect_url( 'state-xyz-123', 'https://example.org/wp-admin/admin-post.php?action=wpmind_cloud_callback' );
		self::assertStringStartsWith( 'https://wenpai.net/connect/wpmind', $connect_url );
		self::assertStringContainsString( 'state=state-xyz-123', $connect_url );
		self::assertStringContainsString( 'plugin=wpmind', $connect_url );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ WenpaiCloud::OPTION_SITE_UUID ] );

		$status_unbound = WenpaiCloud::get_status();
		self::assertFalse( $status_unbound['managed_by_suite'] );
		self::assertFalse( $status_unbound['bound'] );
		self::assertSame( 'none', $status_unbound['binding_source'] );

		self::assertTrue( WenpaiCloud::save_standalone_token( self::FAKE_CLOUD_TOKEN ) );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ WenpaiCloud::OPTION_TOKEN ] );

		$status_standalone = WenpaiCloud::get_status();
		self::assertTrue( $status_standalone['bound'] );
		self::assertSame( 'standalone', $status_standalone['binding_source'] );
		self::assertNotSame( '', $status_standalone['token_fingerprint'] );
		self::assertStringNotContainsString( self::FAKE_CLOUD_TOKEN, $status_standalone['token_fingerprint'] );

		// 8c. AdminPage::sanitize_cloud_token 留空保留原有令牌，非空覆盖。
		$admin_page = \WPMind\Admin\AdminPage::instance();
		self::assertSame( self::FAKE_CLOUD_TOKEN, $admin_page->sanitize_cloud_token( '' ) );
		self::assertSame( 'wmc_new_token_999', $admin_page->sanitize_cloud_token( '  wmc_new_token_999  ' ) );

		// 8d. 授权码换票 POST {base_url}/auth/exchange 并解析返回令牌与余额。
		$captured_url = '';
		$GLOBALS['wpmind_test_http_handler'] = static function ( string $url ) use ( &$captured_url ) {
			$captured_url = $url;
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						'token'               => 'wmc_exchanged_token_777',
						'site_uuid'           => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
						'credits_balance'     => 500,
						'credits_limit_month' => 5000,
					]
				),
				'headers'  => [],
			];
		};

		$exchanged = WenpaiCloud::exchange_connect_code( 'auth-code-abc' );
		self::assertIsArray( $exchanged );
		self::assertSame( 'https://mind-api.wenpai.net/v1/auth/exchange', $captured_url );
		self::assertSame( 'wmc_exchanged_token_777', $exchanged['token'] );
		self::assertSame( 500, $exchanged['credits_balance'] );
		self::assertSame( 5000, $exchanged['credits_limit_month'] );

		// 8e. 充值地址一律跳 wenpai.net 并携带 from=wpmind 与 site_uuid。
		$topup = WenpaiCloud::topup_url( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' );
		self::assertStringStartsWith( 'https://wenpai.net/mind-credits', $topup );
		self::assertStringContainsString( 'from=wpmind', $topup );
		self::assertStringContainsString( 'site_uuid=aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $topup );

		// 8f. 解除绑定后清空独立令牌与点数缓存。
		WenpaiCloud::clear_standalone_binding();
		self::assertFalse( WenpaiCloud::has_standalone_token() );
		self::assertSame( 'none', WenpaiCloud::get_status()['binding_source'] );
	}
}
