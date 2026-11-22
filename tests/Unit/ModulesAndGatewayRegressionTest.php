<?php
/**
 * Regression tests for Worker 2: Cost Control ledger, BudgetChecker, CostEstimator,
 * API Gateway (cost/SQLite/allowed_providers/SSE), GEO cross-plugin contracts,
 * AutoMeta SEO description, Media async/offload, and module self-lock fixes.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace {
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	$GLOBALS['wpmind_test_object_cache']     = [];
	$GLOBALS['wpmind_test_scheduled_events'] = [];
	$GLOBALS['wpmind_test_is_singular']      = false;
	$GLOBALS['wpmind_test_queried_id']       = 0;

	if ( ! function_exists( 'sanitize_email' ) ) {
		function sanitize_email( string $email ): string {
			return trim( $email );
		}
	}

	if ( ! function_exists( 'get_the_author_meta' ) ) {
		function get_the_author_meta( string $field = '', $user_id = false ): string {
			return 'display_name' === $field ? 'Test Author' : '';
		}
	}

	if ( ! function_exists( 'get_the_date' ) ) {
		function get_the_date( string $format = 'Y-m-d', $post = null ): string {
			return '2026-11-21';
		}
	}

	if ( ! function_exists( 'get_the_category' ) ) {
		function get_the_category( int $post_id = 0 ): array {
			return [];
		}
	}

	if ( ! function_exists( 'get_the_tags' ) ) {
		function get_the_tags( int $post_id = 0 ) {
			return false;
		}
	}

	if ( ! function_exists( 'wp_date' ) ) {
		function wp_date( string $format, ?int $timestamp = null ): string {
			return gmdate( $format, $timestamp ?? time() );
		}
	}

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( string $type, bool $gmt = false ) {
			if ( 'mysql' === $type ) {
				return gmdate( 'Y-m-d H:i:s' );
			}
			if ( 'Y-m-d' === $type ) {
				return gmdate( 'Y-m-d' );
			}
			return gmdate( $type );
		}
	}

	if ( ! function_exists( 'wp_cache_get' ) ) {
		function wp_cache_get( string $key, string $group = '' ) {
			$bucket = $GLOBALS['wpmind_test_object_cache'][ $group ] ?? [];
			return array_key_exists( $key, $bucket ) ? $bucket[ $key ] : false;
		}
	}

	if ( ! function_exists( 'wp_cache_set' ) ) {
		function wp_cache_set( string $key, $value, string $group = '', int $expire = 0 ): bool {
			$GLOBALS['wpmind_test_object_cache'][ $group ][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'wp_cache_delete' ) ) {
		function wp_cache_delete( string $key, string $group = '' ): bool {
			unset( $GLOBALS['wpmind_test_object_cache'][ $group ][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
			$text = strip_tags( (string) $text );
			if ( $remove_breaks ) {
				$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
			}
			return trim( $text );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( string $url ): string {
			return $url;
		}
	}

	if ( ! function_exists( 'esc_xml' ) ) {
		function esc_xml( string $text ): string {
			return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( string $show = '' ): string {
			return 'description' === $show ? 'Test Site Description' : 'Test Site';
		}
	}

	if ( ! function_exists( 'get_locale' ) ) {
		function get_locale(): string {
			return 'zh_CN';
		}
	}

	if ( ! function_exists( 'home_url' ) ) {
		function home_url( string $path = '' ): string {
			return 'https://example.org' . ( '' !== $path ? '/' . ltrim( $path, '/' ) : '' );
		}
	}

	if ( ! function_exists( 'get_feed_link' ) ) {
		function get_feed_link(): string {
			return 'https://example.org/feed/';
		}
	}

	if ( ! function_exists( 'get_categories' ) ) {
		function get_categories( array $args = [] ): array {
			return [];
		}
	}

	if ( ! function_exists( 'get_pages' ) ) {
		function get_pages( array $args = [] ): array {
			return get_posts(
				[
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => (int) ( $args['number'] ?? 5 ),
				]
			);
		}
	}

	if ( ! function_exists( 'get_post_types' ) ) {
		function get_post_types( array $args = [], string $output = 'names' ): array {
			if ( 'objects' === $output ) {
				return [
					'post' => (object) [
						'name'   => 'post',
						'labels' => (object) [ 'name' => 'Posts' ],
					],
				];
			}
			return [
				'post' => 'post',
				'page' => 'page',
			];
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post ): string {
			$id = is_object( $post ) ? (int) $post->ID : (int) $post;
			return 'https://example.org/?p=' . $id;
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post ): string {
			return is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
		}
	}

	if ( ! function_exists( 'get_the_excerpt' ) ) {
		function get_the_excerpt( $post ): string {
			return is_object( $post ) ? (string) ( $post->post_excerpt ?? '' ) : '';
		}
	}

	if ( ! function_exists( 'get_the_modified_date' ) ) {
		function get_the_modified_date( string $format = 'c', $post = null ): string {
			return '2026-11-21T00:00:00+00:00';
		}
	}

	if ( ! function_exists( 'wp_trim_words' ) ) {
		function wp_trim_words( string $text, int $num_words = 55, string $more = '...' ): string {
			return trim( $text );
		}
	}

	if ( ! function_exists( 'setup_postdata' ) ) {
		function setup_postdata( $post ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'wp_reset_postdata' ) ) {
		function wp_reset_postdata(): void {}
	}

	if ( ! function_exists( 'is_singular' ) ) {
		function is_singular(): bool {
			return (bool) ( $GLOBALS['wpmind_test_is_singular'] ?? false );
		}
	}

	if ( ! function_exists( 'get_queried_object_id' ) ) {
		function get_queried_object_id(): int {
			return (int) ( $GLOBALS['wpmind_test_queried_id'] ?? 0 );
		}
	}

	if ( ! function_exists( 'wp_attachment_is_image' ) ) {
		function wp_attachment_is_image( int $attachment_id ): bool {
			$post = get_post( $attachment_id );
			return null !== $post && str_starts_with( (string) $post->post_mime_type, 'image/' );
		}
	}

	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
			$GLOBALS['wpmind_test_scheduled_events'][] = [
				'timestamp' => $timestamp,
				'hook'      => $hook,
				'args'      => $args,
			];
			return true;
		}
	}

	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			private array $params  = [];
			private array $headers = [];
			private string $body   = '';

			public function __construct( string $method = 'POST', string $route = '' ) {}

			public function set_param( string $key, $value ): void {
				$this->params[ $key ] = $value;
			}

			public function get_param( string $key ) {
				return $this->params[ $key ] ?? null;
			}

			public function set_header( string $key, string $value ): void {
				$this->headers[ strtolower( $key ) ] = $value;
			}

			public function get_header( string $key ): ?string {
				return $this->headers[ strtolower( $key ) ] ?? null;
			}

			public function get_body(): string {
				return $this->body;
			}
		}
	}

	if ( ! class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {
			public $data;
			public int $status;
			public array $headers = [];

			public function __construct( $data = null, int $status = 200 ) {
				$this->data   = $data;
				$this->status = $status;
			}

			public function get_data() {
				return $this->data;
			}

			public function get_status(): int {
				return $this->status;
			}

			public function header( string $key, string $value ): void {
				$this->headers[ $key ] = $value;
			}
		}
	}
}

namespace WPMind\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use WPMind\Cache\ExactCache;
	use WPMind\Modules\ApiGateway\ApiGatewayModule;
	use WPMind\Modules\ApiGateway\Auth\ApiKeyAuthResult;
	use WPMind\Modules\ApiGateway\Pipeline\ErrorMiddleware;
	use WPMind\Modules\ApiGateway\Pipeline\GatewayRequestContext;
	use WPMind\Modules\ApiGateway\Pipeline\LogMiddleware;
	use WPMind\Modules\ApiGateway\Pipeline\RouteMiddleware;
	use WPMind\Modules\ApiGateway\SchemaManager;
	use WPMind\Modules\ApiGateway\Stream\StreamResult;
	use WPMind\Modules\AutoMeta\AutoMetaModule;
	use WPMind\Modules\AutoMeta\MetaGenerator;
	use WPMind\Modules\CostControl\BudgetAlert;
	use WPMind\Modules\CostControl\BudgetChecker;
	use WPMind\Modules\CostControl\BudgetManager;
	use WPMind\Modules\CostControl\CostControlModule;
	use WPMind\Modules\CostControl\UsageTracker;
	use WPMind\Modules\ExactCache\CostEstimator;
	use WPMind\Modules\Geo\AiSitemapGenerator;
	use WPMind\Modules\Geo\CrawlerTracker;
	use WPMind\Modules\Geo\GeoModule;
	use WPMind\Modules\Geo\LlmsTxtGenerator;
	use WPMind\Modules\Geo\MarkdownFeed;
	use WPMind\Modules\Geo\SchemaGenerator;
	use WPMind\Modules\MediaIntelligence\AltTextGenerator;
	use WPMind\Modules\MediaIntelligence\NsfwDetector;
	use WPMind\Usage\Pricing;

	require_once WPMIND_PLUGIN_DIR . 'includes/Usage/Pricing.php';
	require_once WPMIND_PLUGIN_DIR . 'includes/Cache/ExactCache.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/cost-control/CostControlModule.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/exact-cache/includes/CostEstimator.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/api-gateway/ApiGatewayModule.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/geo/GeoModule.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/auto-meta/AutoMetaModule.php';
	require_once WPMIND_PLUGIN_DIR . 'modules/media-intelligence/MediaIntelligenceModule.php';

	/**
	 * Fake $wpdb supporting UPDATE-then-INSERT verification for API Gateway usage & audit tables.
	 */
	final class GatewayUsageFakeWpdb {
		public string $prefix     = 'wp_';
		public string $last_error = '';

		/** @var array<string, array<string, mixed>> */
		public array $usage_rows = [];

		/** @var list<array<string, mixed>> */
		public array $audit_rows = [];

		/** @var list<string> */
		public array $queries = [];

		public function prepare( string $query, ...$args ): string {
			$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
			$i    = 0;
			return (string) preg_replace_callback(
				'/%[isdf]/',
				static function ( array $m ) use ( $args, &$i ): string {
					$v = $args[ $i++ ] ?? '';
					return match ( $m[0] ) {
						'%i' => (string) $v,
						'%d' => (string) (int) $v,
						'%f' => sprintf( '%.6f', (float) $v ),
						default => "'" . addslashes( (string) $v ) . "'",
					};
				},
				$query
			);
		}

		public function query( string $sql ): int {
			$this->queries[] = $sql;
			if ( preg_match(
				"/UPDATE\s+\S+wpmind_api_key_usage.*?input_tokens\s*=\s*input_tokens\s*\+\s*(\d+).*?output_tokens\s*=\s*output_tokens\s*\+\s*(\d+).*?total_tokens\s*=\s*total_tokens\s*\+\s*(\d+).*?total_cost_usd\s*=\s*total_cost_usd\s*\+\s*([0-9.]+).*?WHERE\s+key_id\s*=\s*'([^']+)'\s+AND\s+window_month\s*=\s*'([^']+)'/s",
				$sql,
				$m
			) ) {
				$k = $m[5] . '|' . $m[6];
				if ( ! isset( $this->usage_rows[ $k ] ) ) {
					return 0;
				}
				++$this->usage_rows[ $k ]['request_count'];
				$this->usage_rows[ $k ]['input_tokens']   += (int) $m[1];
				$this->usage_rows[ $k ]['output_tokens']  += (int) $m[2];
				$this->usage_rows[ $k ]['total_tokens']   += (int) $m[3];
				$this->usage_rows[ $k ]['total_cost_usd'] += (float) $m[4];
				return 1;
			}
			return 0;
		}

		public function insert( string $table, array $data, array $format = [] ): int {
			if ( str_ends_with( $table, 'wpmind_api_key_usage' ) ) {
				$k                      = $data['key_id'] . '|' . $data['window_month'];
				$this->usage_rows[ $k ] = $data;
				return 1;
			}
			if ( str_ends_with( $table, 'wpmind_api_audit_log' ) ) {
				$this->audit_rows[] = $data;
				return 1;
			}
			return 1;
		}
	}

	final class ModulesAndGatewayRegressionTest extends TestCase {

		protected function setUp(): void {
			$GLOBALS['wpmind_test_options']          = [];
			$GLOBALS['wpmind_test_transients']       = [];
			$GLOBALS['wpmind_test_actions']          = [];
			$GLOBALS['wpmind_test_filters']          = [];
			$GLOBALS['wpmind_test_object_cache']     = [];
			$GLOBALS['wpmind_test_posts']            = [];
			$GLOBALS['wpmind_test_postmeta']         = [];
			$GLOBALS['wpmind_test_post_autoinc']     = 1;
			$GLOBALS['wpmind_test_scheduled_events'] = [];
			$GLOBALS['wpmind_test_is_singular']      = false;
			$GLOBALS['wpmind_test_queried_id']       = 0;

			// Reset BudgetManager singleton cache between tests.
			$ref = new \ReflectionProperty( BudgetManager::class, 'settings' );
			$ref->setAccessible( true );
			$ref->setValue( BudgetManager::instance(), null );
		}

		public function test_usage_tracker_delegates_pricing_and_attributes_context_and_payer(): void {
			UsageTracker::record( 'deepseek', 'deepseek-chat', 1_000_000, 500_000, 120, 'wpslug_seo_slug' );
			UsageTracker::record( 'wenpai_cloud', 'gpt-4o-mini', 1000, 500, 80, 'bisheng_page_generate', '', 25 );
			UsageTracker::record( 'openai', 'gpt-4o-mini', 1000, 1000, 50, 'api_gateway:sk_123456' );

			$stats   = UsageTracker::get_stats();
			$history = UsageTracker::get_history( 10 );
			$ctxs    = UsageTracker::get_context_stats();

			// Pricing delegates to Pricing::calculate_cost (ModelCatalog-backed).
			$expected_ds = Pricing::calculate_cost( 'deepseek', 'deepseek-chat', 1_000_000, 500_000 );
			self::assertEquals( $expected_ds, $stats['providers']['deepseek']['total_cost'] );

			// Context & caller_plugin attribution.
			self::assertSame( 'wpslug', $ctxs['wpslug_seo_slug']['caller_plugin'] );
			self::assertSame( 'byok', $ctxs['wpslug_seo_slug']['payer'] );

			self::assertSame( 'bisheng', $ctxs['bisheng_page_generate']['caller_plugin'] );
			self::assertSame( 'wenpai_cloud', $ctxs['bisheng_page_generate']['payer'] );
			self::assertSame( 25, $ctxs['bisheng_page_generate']['credits'] );

			self::assertSame( 'api-gateway', $ctxs['api_gateway:sk_123456']['caller_plugin'] );

			// Additional prefix mappings.
			self::assertSame( 'wpicp', UsageTracker::resolve_caller_plugin( 'wpicp_ad_law_rewrite' ) );
			self::assertSame( 'wpdocs', UsageTracker::resolve_caller_plugin( 'wpdocs_summary' ) );
			self::assertSame( 'wpsss', UsageTracker::resolve_caller_plugin( 'wpsss_ci_audit' ) );

			// History entries store context, caller_plugin, payer, credits.
			self::assertCount( 3, $history );
			self::assertSame( 'api_gateway:sk_123456', $history[0]['context'] );
			self::assertSame( 'api-gateway', $history[0]['caller_plugin'] );
			self::assertSame( 'byok', $history[0]['payer'] );
			self::assertSame( 25, $history[1]['credits'] );
			self::assertSame( 'wenpai_cloud', $history[1]['payer'] );
		}

		public function test_usage_tracker_does_not_delete_unowned_lock(): void {
			$GLOBALS['wpmind_test_transients']['wpmind_usage_stats_lock'] = true;

			UsageTracker::record( 'openai', 'gpt-4o-mini', 100, 100, 10, 'wpslug_test' );

			// Lock belonged to another process; must still be present.
			self::assertTrue( (bool) get_transient( 'wpmind_usage_stats_lock' ) );
		}

		public function test_cost_control_module_forwards_extended_usage_args(): void {
			$module = new CostControlModule();
			$module->record_usage( 'wenpai_cloud', 'gpt-4o-mini', 500, 500, 42, 'wpicp_check', '', 12 );

			$ctxs = UsageTracker::get_context_stats();
			self::assertArrayHasKey( 'wpicp_check', $ctxs );
			self::assertSame( 'wpicp', $ctxs['wpicp_check']['caller_plugin'] );
			self::assertSame( 'wenpai_cloud', $ctxs['wpicp_check']['payer'] );
			self::assertSame( 12, $ctxs['wpicp_check']['credits'] );
		}

		public function test_budget_checker_three_enforcement_modes(): void {
			// Record $5.00 USD today.
			$today = wp_date( 'Y-m-d' );
			$month = wp_date( 'Y-m' );
			$stats = [
				'providers' => [],
				'daily'     => [
					$today => [
						'input_tokens'  => 1000,
						'output_tokens' => 1000,
						'cost_usd'      => 5.0,
						'cost_cny'      => 0.0,
						'requests'      => 1,
					],
				],
				'monthly'   => [
					$month => [
						'input_tokens'  => 1000,
						'output_tokens' => 1000,
						'cost_usd'      => 5.0,
						'cost_cny'      => 0.0,
						'requests'      => 1,
					],
				],
				'total'     => [
					'input_tokens'  => 1000,
					'output_tokens' => 1000,
					'cost_usd'      => 5.0,
					'cost_cny'      => 0.0,
					'requests'      => 1,
				],
			];
			update_option( 'wpmind_usage_stats', $stats, false );
			wp_cache_set( 'wpmind_usage_stats', $stats, '', 300 );

			// 1. Not exceeded -> allowed=true, fallback_model=null.
			BudgetManager::instance()->save_settings(
				[
					'enabled'          => true,
					'enforcement_mode' => BudgetManager::MODE_DISABLE,
					'global'           => [ 'daily_limit_usd' => 10.0 ],
				]
			);
			$ok = BudgetChecker::instance()->can_make_request( 'openai', 'wpslug_gen' );
			self::assertTrue( $ok['allowed'] );
			self::assertSame( BudgetManager::MODE_DISABLE, $ok['mode'] );
			self::assertNull( $ok['reason'] );
			self::assertNull( $ok['fallback_model'] );

			// 2. Exceeded + disable -> allowed=false.
			BudgetManager::instance()->save_settings(
				[
					'enabled'          => true,
					'enforcement_mode' => BudgetManager::MODE_DISABLE,
					'global'           => [ 'daily_limit_usd' => 2.0 ],
				]
			);
			$blocked = BudgetChecker::instance()->can_make_request( 'openai', 'wpslug_gen' );
			self::assertFalse( $blocked['allowed'] );
			self::assertSame( BudgetManager::MODE_DISABLE, $blocked['mode'] );
			self::assertNotEmpty( $blocked['reason'] );
			self::assertNull( $blocked['fallback_model'] );

			// 3. Exceeded + downgrade -> allowed=true + fallback_model.
			BudgetManager::instance()->save_settings(
				[
					'enabled'          => true,
					'enforcement_mode' => BudgetManager::MODE_DOWNGRADE,
					'global'           => [ 'daily_limit_usd' => 2.0 ],
				]
			);
			$downgraded = BudgetChecker::instance()->can_make_request( 'openai', 'wpslug_gen' );
			self::assertTrue( $downgraded['allowed'] );
			self::assertSame( BudgetManager::MODE_DOWNGRADE, $downgraded['mode'] );
			self::assertIsString( $downgraded['fallback_model'] );
			self::assertNotSame( '', $downgraded['fallback_model'] );

			// 4. Exceeded + alert -> allowed=true and stores alert notice.
			BudgetManager::instance()->save_settings(
				[
					'enabled'          => true,
					'enforcement_mode' => BudgetManager::MODE_ALERT,
					'global'           => [ 'daily_limit_usd' => 2.0 ],
					'notifications'    => [ 'admin_notice' => true ],
				]
			);
			$alerted = BudgetChecker::instance()->can_make_request( 'openai', 'wpslug_gen' );
			self::assertTrue( $alerted['allowed'] );
			self::assertSame( BudgetManager::MODE_ALERT, $alerted['mode'] );
			self::assertNull( $alerted['fallback_model'] );
			self::assertNotEmpty( BudgetAlert::get_pending_notices() );
		}

		public function test_cost_estimator_calculates_both_usd_and_cny_savings(): void {
			update_option( 'wpmind_exact_cache_stats', [ 'hits' => 4, 'misses' => 1, 'writes' => 1 ], false );

			// Reset ExactCache pending stats so get_stats reads fresh option.
			$ref = new \ReflectionProperty( ExactCache::class, 'pending_stats' );
			$ref->setAccessible( true );
			$ref->setValue( ExactCache::instance(), [] );

			$usage = [
				'total' => [
					'cost_usd' => 2.0,
					'cost_cny' => 10.0,
					'requests' => 10,
				],
			];
			update_option( 'wpmind_usage_stats', $usage, false );
			wp_cache_set( 'wpmind_usage_stats', $usage, '', 300 );

			$savings = CostEstimator::get_estimated_savings();
			// avg USD = 0.2 * 4 hits = 0.8; avg CNY = 1.0 * 4 hits = 4.0.
			self::assertEquals( 0.8, $savings['saved_usd'] );
			self::assertEquals( 4.0, $savings['saved_cny'] );
			self::assertEquals( 0.8, $savings['total_usd'] );
			self::assertEquals( 4.0, $savings['total_cny'] );
		}

		public function test_gateway_allowed_providers_blocks_disallowed_provider_with_403_openai_error(): void {
			$req = new \WP_REST_Request( 'POST', '/mind/v1/chat/completions' );
			$ctx = GatewayRequestContext::from_rest_request( 'chat.completions', $req );
			$ctx->set_auth_result(
				new ApiKeyAuthResult(
					[
						'key_id'            => 'k12345678901',
						'key_prefix'        => 'sk_abc12',
						'allowed_providers' => wp_json_encode( [ 'deepseek' ] ),
					]
				)
			);
			$ctx->set_internal_payload(
				[
					'operation' => 'chat.completions',
					'provider'  => 'openai',
					'model'     => 'gpt-4o',
					'messages'  => [ [ 'role' => 'user', 'content' => 'hi' ] ],
					'options'   => [
						'provider' => 'openai',
						'model'    => 'gpt-4o',
					],
					'stream'    => false,
				]
			);

			( new RouteMiddleware() )->process( $ctx );
			self::assertTrue( $ctx->has_error() );
			self::assertSame( 'provider_not_allowed', $ctx->error()->get_error_code() );

			( new ErrorMiddleware() )->process( $ctx );
			$resp = $ctx->to_rest_response();
			self::assertSame( 403, $resp->get_status() );
			$body = $resp->get_data();
			self::assertSame( 'provider_not_allowed', $body['error']['code'] );
			self::assertSame( 'invalid_request_error', $body['error']['type'] );
		}

		public function test_gateway_allowed_providers_sets_failover_providers_for_auto(): void {
			$req = new \WP_REST_Request( 'POST', '/mind/v1/chat/completions' );
			$ctx = GatewayRequestContext::from_rest_request( 'chat.completions', $req );
			$ctx->set_auth_result(
				new ApiKeyAuthResult(
					[
						'key_id'            => 'k12345678901',
						'key_prefix'        => 'sk_auto1',
						'allowed_providers' => wp_json_encode( [ 'deepseek', 'qwen' ] ),
					]
				)
			);

			$route  = new RouteMiddleware();
			$method = new \ReflectionMethod( RouteMiddleware::class, 'apply_provider_policy' );
			$method->setAccessible( true );
			$normalized = $method->invoke( $route, $ctx, [ 'provider' => 'auto', 'model' => 'auto' ] );

			self::assertIsArray( $normalized );
			self::assertArrayNotHasKey( 'provider', $normalized );
			self::assertSame( [ 'deepseek', 'qwen' ], $normalized['failover_providers'] );
		}

		public function test_gateway_cost_calculation_sqlite_upsert_and_sse_logging(): void {
			$fake_wpdb       = new GatewayUsageFakeWpdb();
			$GLOBALS['wpdb'] = $fake_wpdb;

			// 1. SchemaManager SQL must not contain CHARACTER SET ascii COLLATE ascii_bin.
			$rm = new \ReflectionMethod( SchemaManager::class, 'get_api_keys_sql' );
			$rm->setAccessible( true );
			$sql_keys = $rm->invoke( null, 'wp_', '' );
			self::assertStringNotContainsString( 'CHARACTER SET', $sql_keys );
			self::assertStringNotContainsString( 'ascii_bin', $sql_keys );

			// 2. Non-streaming LogMiddleware calculates CNY->USD via Pricing (default 7.2) and upserts via UPDATE then INSERT.
			$req = new \WP_REST_Request( 'POST', '/mind/v1/chat/completions' );
			$ctx = GatewayRequestContext::from_rest_request( 'chat.completions', $req );
			$ctx->set_auth_result(
				new ApiKeyAuthResult(
					[
						'key_id'     => 'k_cost_00001',
						'key_prefix' => 'sk_cost1',
					]
				)
			);
			$ctx->set_internal_payload(
				[
					'operation' => 'chat.completions',
					'provider'  => 'deepseek',
					'model'     => 'deepseek-chat',
				]
			);
			$ctx->set_internal_result(
				[
					'provider' => 'deepseek',
					'model'    => 'deepseek-chat',
					'usage'    => [
						'prompt_tokens'     => 1_000_000,
						'completion_tokens' => 0,
						'total_tokens'      => 1_000_000,
					],
				]
			);

			( new LogMiddleware() )->process( $ctx );

			$month_key = 'k_cost_00001|' . gmdate( 'Y-m' );
			self::assertArrayHasKey( $month_key, $fake_wpdb->usage_rows );
			$expected_usd = Pricing::calculate_cost_usd( 'deepseek', 'deepseek-chat', 1_000_000, 0 );
			self::assertGreaterThan( 0.0, $expected_usd );
			self::assertEqualsWithDelta( $expected_usd, $fake_wpdb->usage_rows[ $month_key ]['total_cost_usd'], 0.000002 );
			// Non-streaming LogMiddleware must NOT fire duplicate wpmind_usage_record.
			self::assertArrayNotHasKey( 'wpmind_usage_record', $GLOBALS['wpmind_test_actions'] );
			// No ON DUPLICATE KEY UPDATE used.
			foreach ( $fake_wpdb->queries as $q ) {
				self::assertStringNotContainsString( 'ON DUPLICATE KEY', $q );
			}

			// 3. SSE completion updates existing row via UPDATE and emits wpmind_usage_record with api_gateway:<prefix>.
			$stream_result = new StreamResult( 500, 'stop', null, 'openai', 'gpt-4o-mini', 1000, 500, 320 );
			( new ApiGatewayModule() )->log_sse_completion( 'req-sse-1', 'k_cost_00001', $stream_result, $ctx );

			self::assertSame( 2, $fake_wpdb->usage_rows[ $month_key ]['request_count'] );
			self::assertArrayHasKey( 'wpmind_usage_record', $GLOBALS['wpmind_test_actions'] );
			$emitted = $GLOBALS['wpmind_test_actions']['wpmind_usage_record'][0];
			self::assertSame( 'openai', $emitted[0] );
			self::assertSame( 'gpt-4o-mini', $emitted[1] );
			self::assertSame( 1000, $emitted[2] );
			self::assertSame( 500, $emitted[3] );
			self::assertSame( 'api_gateway:sk_cost1', $emitted[5] );
			self::assertSame( 'byok', $emitted[6] );
		}

		public function test_llms_txt_excludes_noai_and_applies_sections_filter(): void {
			$public_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Public AI Guide',
					'post_excerpt' => 'Visible post',
				]
			);
			$hidden_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Secret NoAI Draft',
					'post_excerpt' => 'Hidden post',
				]
			);
			update_post_meta( $hidden_id, '_wpmind_ai_indexing', 'noai' );

			$GLOBALS['wpmind_test_filters']['wpmind_llms_txt_sections'] = static function ( array $sections, string $mode ): array {
				$sections['docs'] = [
					'title' => 'Documentation (' . $mode . ')',
					'items' => [
						[
							'title' => 'WPDocs Handbook',
							'url'   => 'https://example.org/docs/handbook',
							'desc'  => 'Official guide',
						],
					],
				];
				return $sections;
			};

			$gen     = new LlmsTxtGenerator();
			$content = $gen->generate_content( 'full' );

			self::assertStringContainsString( 'Public AI Guide', $content );
			self::assertStringNotContainsString( 'Secret NoAI Draft', $content );
			self::assertStringContainsString( '## Documentation (full)', $content );
			self::assertStringContainsString( '[WPDocs Handbook](https://example.org/docs/handbook): Official guide', $content );
			self::assertGreaterThan( 0, $public_id );
		}

		public function test_markdown_feed_uses_raw_markdown_filter(): void {
			update_option( 'wpmind_geo_signals', '0' );
			$post = new \WP_Post(
				[
					'ID'           => 77,
					'post_title'   => 'Synced Doc',
					'post_content' => '',
				]
			);

			$GLOBALS['wpmind_test_filters']['wpmind_raw_markdown_for_post'] = static function ( $raw, \WP_Post $p ): string {
				return "## Raw Markdown Body\n\nFrom external doc #" . $p->ID;
			};

			$feed = new MarkdownFeed();
			$md   = $feed->post_to_markdown( $post );

			self::assertStringContainsString( '# Synced Doc', $md );
			self::assertStringContainsString( '## Raw Markdown Body', $md );
			self::assertStringContainsString( 'From external doc #77', $md );
		}

		public function test_ai_sitemap_excludes_noai_and_uses_wpcy_namespace(): void {
			$visible_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Included Post',
					'post_excerpt' => 'Summary text',
				]
			);
			$blocked_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Excluded Post',
					'post_excerpt' => 'Should not appear',
				]
			);
			update_post_meta( $blocked_id, '_wpmind_ai_indexing', 'noai' );

			$xml = ( new AiSitemapGenerator() )->generate_xml();

			self::assertStringContainsString( 'xmlns:ai="https://wpcy.com/mind/ai-sitemap/1.0"', $xml );
			self::assertStringContainsString( '?p=' . $visible_id, $xml );
			self::assertStringNotContainsString( '?p=' . $blocked_id, $xml );
		}

		public function test_schema_generator_cjk_word_count_and_slim_seo_list(): void {
			self::assertSame( 6, SchemaGenerator::count_words( '<p>文派心思测试</p>' ) );
			self::assertSame( 6, SchemaGenerator::count_words( 'WordPress 文派心思 7.0' ) );

			$ref     = new \ReflectionClassConstant( SchemaGenerator::class, 'SEO_PLUGINS' );
			$plugins = $ref->getValue();
			self::assertContains( 'slim-seo/slim-seo.php', $plugins );
		}

		public function test_crawler_tracker_hooks_llms_and_sitemap_and_throttles(): void {
			new CrawlerTracker();
			self::assertArrayHasKey( 'wpmind_llms_txt_accessed', $GLOBALS['wpmind_test_actions'] );
			self::assertArrayHasKey( 'wpmind_ai_sitemap_accessed', $GLOBALS['wpmind_test_actions'] );

			$_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
			$_SERVER['REQUEST_URI']     = '/llms.txt';
			$_SERVER['REMOTE_ADDR']     = '1.2.3.4';

			$tracker = new CrawlerTracker();
			$tracker->track_access();
			$tracker->track_access(); // Throttled within 60s window.

			$stats = $tracker->get_stats();
			self::assertSame( 1, $stats['GPTBot']['total_hits'] );
		}

		public function test_auto_meta_outputs_description_and_slim_seo_fallback(): void {
			$post_id = wp_insert_post(
				[
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'SEO Test',
				]
			);
			update_post_meta( $post_id, '_wpmind_seo_description', 'WPMind generated SEO summary.' );

			$GLOBALS['wpmind_test_is_singular'] = true;
			$GLOBALS['wpmind_test_queried_id']  = $post_id;

			$gen = new MetaGenerator();

			// No SEO plugin -> prints meta description.
			ob_start();
			$gen->output_meta_description();
			$html = (string) ob_get_clean();
			self::assertStringContainsString( '<meta name="description" content="WPMind generated SEO summary.">', $html );

			// Slim SEO fallback: returns WPMind desc when empty, preserves custom Slim SEO desc when non-empty.
			self::assertSame( 'WPMind generated SEO summary.', $gen->filter_slim_seo_description( '', $post_id ) );
			self::assertSame( 'Custom Slim SEO', $gen->filter_slim_seo_description( 'Custom Slim SEO', $post_id ) );
		}

		public function test_media_offload_and_nsfw_are_async(): void {
			$att_id = wp_insert_post(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/png',
				]
			);

			$alt = new AltTextGenerator( true );
			self::assertArrayHasKey( 'wpsss_attachment_offloaded', $GLOBALS['wpmind_test_actions'] );
			$alt->on_attachment_offloaded( $att_id, 'https://cdn.example.org/img.png' );

			$nsfw = new NsfwDetector( true );
			self::assertArrayHasKey( 'wpmind_check_nsfw_attachment', $GLOBALS['wpmind_test_actions'] );
			$nsfw->schedule_check( $att_id );

			$hooks = array_column( $GLOBALS['wpmind_test_scheduled_events'], 'hook' );
			self::assertContains( 'wpmind_generate_alt_text', $hooks );
			self::assertContains( 'wpmind_check_nsfw_attachment', $hooks );
		}

		public function test_geo_and_auto_meta_keep_admin_save_ajax_when_disabled(): void {
			// GEO disabled: save AJAX still registered, frontend components not initialized.
			update_option( 'wpmind_geo_enabled', '0' );
			$geo = new GeoModule();
			$geo->init();
			self::assertArrayHasKey( 'wp_ajax_wpmind_save_geo_settings', $GLOBALS['wpmind_test_actions'] );
			self::assertSame( [], $geo->get_components() );

			// AutoMeta disabled: save AJAX still registered, generator hooks not initialized.
			$GLOBALS['wpmind_test_actions'] = [];
			update_option( 'wpmind_auto_meta_enabled', '0' );
			$auto_meta = new AutoMetaModule();
			$auto_meta->init();
			self::assertArrayHasKey( 'wp_ajax_wpmind_save_auto_meta_settings', $GLOBALS['wpmind_test_actions'] );
			self::assertNull( $auto_meta->get_generator() );
			self::assertArrayNotHasKey( 'transition_post_status', $GLOBALS['wpmind_test_actions'] );
		}
	}
}
