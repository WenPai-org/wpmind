<?php
/**
 * SuiteIntegration & AdminPage 4.0 套件协同单元测试。
 *
 * @package WPMind\Tests\Unit
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPMind\Admin\AdminPage;
use WPMind\Admin\SuiteIntegration;
use WPMind\Failover\FailoverManager;
use WPMind\Modules\CostControl\BudgetManager;

/**
 * 验证文派套件协议接入（wpcy_service_status、wpcy_quota_items、wenpai_plugin_display 白标）
 * 以及后台图片端点白名单同步。
 */
final class SuiteIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpmind_test_options']    = [];
		$GLOBALS['wpmind_test_transients'] = [];
		$GLOBALS['wpmind_test_filters']    = [];
		$GLOBALS['wpmind_test_actions']    = [];
		SuiteIntegration::reset_for_tests();
		$this->resetSingletons();
	}

	protected function tearDown(): void {
		$GLOBALS['wpmind_test_options']    = [];
		$GLOBALS['wpmind_test_transients'] = [];
		$GLOBALS['wpmind_test_filters']    = [];
		$GLOBALS['wpmind_test_actions']    = [];
		SuiteIntegration::reset_for_tests();
		$this->resetSingletons();
		parent::tearDown();
	}

	/**
	 * 重置单例与对象缓存，避免测试用例之间串扰。
	 */
	private function resetSingletons(): void {
		foreach ( [ BudgetManager::class, FailoverManager::class ] as $class ) {
			if ( class_exists( $class ) ) {
				$ref = new \ReflectionClass( $class );
				if ( $ref->hasProperty( 'instance' ) ) {
					$prop = $ref->getProperty( 'instance' );
					$prop->setAccessible( true );
					$prop->setValue( null, null );
				}
			}
		}
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'wpmind_usage_stats' );
		}
	}

	public function test_display_defaults(): void {
		$display = SuiteIntegration::display();

		$this->assertSame( '文派心思', $display['brand'] );
		$this->assertSame( 'WPMind', $display['plugin_name'] );
		$this->assertSame( '心思', $display['menu_label'] );
		$this->assertStringContainsString( 'wpcy.com', $display['help_url'] );
		$this->assertStringContainsString( 'wpcy.com', $display['docs_url'] );
		$this->assertStringContainsString( 'wpcy.com', $display['feedback_url'] );
		$this->assertStringContainsString( 'github.com', $display['github_url'] );
		$this->assertFalse( $display['whitelabel'] );
	}

	public function test_display_whitelabel_hides_default_wenpai_links_and_github(): void {
		$GLOBALS['wpmind_test_filters']['wenpai_plugin_display'] = static function ( array $defaults, string $slug ): array {
			if ( 'wpmind' !== $slug ) {
				return $defaults;
			}
			$defaults['whitelabel'] = true;
			return $defaults;
		};

		$display = SuiteIntegration::display();

		$this->assertTrue( $display['whitelabel'] );
		$this->assertSame( '', $display['github_url'] );
		$this->assertSame( '', $display['help_url'] );
		$this->assertSame( '', $display['docs_url'] );
		$this->assertSame( '', $display['feedback_url'] );
	}

	public function test_display_whitelabel_preserves_custom_agency_urls(): void {
		$GLOBALS['wpmind_test_filters']['wenpai_plugin_display'] = static function ( array $defaults, string $slug ): array {
			if ( 'wpmind' !== $slug ) {
				return $defaults;
			}
			$defaults['brand']      = '星河 AI 控制台';
			$defaults['menu_label'] = '星河 AI';
			$defaults['docs_url']   = 'https://agency.example.com/docs';
			$defaults['help_url']   = 'https://agency.example.com/help';
			return $defaults;
		};

		$display = SuiteIntegration::display();

		$this->assertTrue( $display['whitelabel'] );
		$this->assertSame( '星河 AI 控制台', $display['brand'] );
		$this->assertSame( '星河 AI', $display['menu_label'] );
		$this->assertSame( 'https://agency.example.com/docs', $display['docs_url'] );
		$this->assertSame( 'https://agency.example.com/help', $display['help_url'] );
		$this->assertSame( '', $display['feedback_url'] );
		$this->assertSame( '', $display['github_url'] );
	}

	public function test_service_status_reports_warn_when_unconfigured(): void {
		$status = SuiteIntegration::filter_service_status( [ 'cdn' => [ 'state' => 'ok' ] ] );

		$this->assertArrayHasKey( 'cdn', $status );
		$this->assertArrayHasKey( 'ai', $status );
		$this->assertSame( 'warn', $status['ai']['state'] );
		$this->assertSame( '待配置', $status['ai']['pill'] );
		$this->assertNotEmpty( $status['ai']['summary'] );
		$this->assertNotEmpty( $status['ai']['action_label'] );
		$this->assertNotEmpty( $status['ai']['action_url'] );
	}

	public function test_service_status_reports_ok_when_official_cloud_bound(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_enabled'] = '1';
		$GLOBALS['wpmind_test_options']['wpmind_cloud_credits'] = [
			'remaining'  => 2400,
			'used_month' => 600,
			'limit'      => 3000,
		];
		$GLOBALS['wpmind_test_filters']['wenpai_site_binding']  = static function (): array {
			return [
				'bound'     => true,
				'site_uuid' => 'site_test_001',
			];
		};

		$status = SuiteIntegration::filter_service_status( [] );

		$this->assertSame( 'ok', $status['ai']['state'] );
		$this->assertSame( '运行中', $status['ai']['pill'] );
		$this->assertStringContainsString( '心思点数', $status['ai']['summary'] );
		$this->assertStringContainsString( '2,400', $status['ai']['summary'] );
	}

	public function test_quota_items_reports_official_cloud_credits_and_replaces_existing(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_enabled'] = '1';
		$GLOBALS['wpmind_test_options']['wpmind_cloud_credits'] = [
			'remaining'  => 1200,
			'used_month' => 800,
			'limit'      => 2000,
		];
		$GLOBALS['wpmind_test_filters']['wenpai_site_binding']  = static function (): array {
			return [
				'bound'     => true,
				'site_uuid' => 'site_test_002',
			];
		};

		$items = SuiteIntegration::filter_quota_items(
			[
				[ 'id' => 'other', 'name' => '其他服务' ],
				[ 'id' => 'wpmind', 'name' => '旧条目' ],
			]
		);

		$this->assertCount( 2, $items );
		$mind_item = null;
		foreach ( $items as $item ) {
			if ( 'wpmind' === ( $item['id'] ?? '' ) ) {
				$mind_item = $item;
			}
		}

		$this->assertIsArray( $mind_item );
		$this->assertSame( '心思 AI 接口服务', $mind_item['name'] );
		$this->assertStringContainsString( '1,200', $mind_item['usage'] );
		$this->assertStringContainsString( '心思点数', $mind_item['usage'] );
		$this->assertSame( 40, $mind_item['percent'] );
		$this->assertNotEmpty( $mind_item['action_url'] );
	}

	public function test_quota_items_falls_back_to_monthly_budget_when_cloud_disabled(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_enabled']   = '0';
		$GLOBALS['wpmind_test_options']['wpmind_budget_settings'] = [
			'enabled' => true,
			'global'  => [
				'monthly_limit_usd' => 50.0,
				'monthly_limit_cny' => 0.0,
				'alert_threshold'   => 80,
			],
		];

		$month_key = function_exists( 'wp_date' ) ? wp_date( 'Y-m' ) : gmdate( 'Y-m' );
		$GLOBALS['wpmind_test_options']['wpmind_usage_stats'] = [
			'monthly' => [
				$month_key => [
					'cost_usd' => 45.0,
					'cost_cny' => 0.0,
					'requests' => 120,
				],
			],
		];

		$items = SuiteIntegration::filter_quota_items( [] );

		$this->assertCount( 1, $items );
		$this->assertSame( 'wpmind', $items[0]['id'] );
		$this->assertSame( '心思 AI 接口服务', $items[0]['name'] );
		$this->assertSame( 90, $items[0]['percent'] );
		$this->assertStringContainsString( '50', $items[0]['usage'] );
	}

	public function test_quota_items_default_call_count_mode(): void {
		$GLOBALS['wpmind_test_options']['wpmind_cloud_enabled'] = '0';
		$month_key = function_exists( 'wp_date' ) ? wp_date( 'Y-m' ) : gmdate( 'Y-m' );
		$GLOBALS['wpmind_test_options']['wpmind_usage_stats'] = [
			'monthly' => [
				$month_key => [
					'cost_usd' => 0.0,
					'cost_cny' => 0.0,
					'requests' => 37,
				],
			],
		];

		$items = SuiteIntegration::filter_quota_items( [] );

		$this->assertCount( 1, $items );
		$this->assertSame( 'wpmind', $items[0]['id'] );
		$this->assertStringContainsString( '37', $items[0]['usage'] );
		$this->assertNull( $items[0]['percent'] );
	}

	public function test_init_is_idempotent(): void {
		SuiteIntegration::init();
		SuiteIntegration::init();
		$this->assertTrue( true );
	}

	public function test_sanitize_image_endpoints_allows_all_image_router_keys(): void {
		$admin    = AdminPage::instance();
		$expected = [
			'openai_gpt_image',
			'google_gemini_image',
			'tencent_hunyuan',
			'bytedance_reve',
			'bytedance_seedream',
			'bytedance_doubao',
			'flux',
			'midjourney',
			'qwen_image',
		];

		$input = [];
		foreach ( $expected as $key ) {
			$input[ $key ] = [
				'enabled' => '1',
				'api_key' => 'sk-test-' . $key,
			];
		}
		$input['unknown_provider'] = [ 'enabled' => '1', 'api_key' => 'sk-bad' ];

		$sanitized = $admin->sanitize_image_endpoints( $input );

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $sanitized, "Missing image provider key: {$key}" );
			$this->assertTrue( $sanitized[ $key ]['enabled'] );
		}
		$this->assertArrayNotHasKey( 'unknown_provider', $sanitized );
	}
}
