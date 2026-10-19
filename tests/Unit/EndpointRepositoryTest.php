<?php
/**
 * EndpointRepository / sanitize_endpoints 测试。
 *
 * 测试里的 key 都是假的占位串，不对应任何真实账号；不发任何网络请求。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Admin\AdminPage;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderDefinitions;

/**
 * @covers \WPMind\Provider\EndpointRepository
 * @covers \WPMind\Provider\Endpoint
 * @covers \WPMind\Admin\AdminPage::sanitize_endpoints
 */
final class EndpointRepositoryTest extends TestCase {

	private const FAKE_KEY = 'sk-fake-saved-444444444444';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']               = [];
		$GLOBALS['wpmind_test_autoload']              = [];
		$GLOBALS['wpmind_test_filters']               = [];
		$GLOBALS['wpmind_test_settings_errors']       = [];
		$GLOBALS['wpmind_test_update_option_filters'] = [];
	}

	private static function repo(): EndpointRepository {
		return new EndpointRepository();
	}

	public function test_resolved_base_url_custom_empty_invalid_and_valid(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => '',
				'models'          => [],
			],
			'qwen'     => [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => 'http://not-https.example.com/v1',
				'models'          => [],
			],
			'zhipu'    => [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => 'https://proxy.example.com/v1/',
				'models'          => [],
			],
		];

		$repo = self::repo();
		self::assertSame( 'https://api.deepseek.com/v1', $repo->resolved_base_url( 'deepseek' ), 'empty custom falls back to default' );
		self::assertSame( 'https://dashscope.aliyuncs.com/compatible-mode/v1', $repo->resolved_base_url( 'qwen' ), 'non-https custom is ignored' );
		self::assertSame( 'https://proxy.example.com/v1', $repo->resolved_base_url( 'zhipu' ), 'valid custom wins, trailing slash dropped' );
		self::assertSame( '', $repo->resolved_base_url( 'not-a-provider' ) );
	}

	public function test_http_custom_url_allowed_only_through_filter(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => 'http://gateway.example.com/v1', 'models' => [] ],
		];

		$GLOBALS['wpmind_test_filters']['wpmind_allow_http_base_url'] = static function ( bool $allow ): bool {
			return true;
		};
		self::assertSame( 'http://gateway.example.com/v1', self::repo()->resolved_base_url( 'deepseek' ) );

		// 保存时同样受过滤器控制，并且仍然拒绝内网地址和查询串。
		self::assertSame( 'http://gateway.example.com/v1', EndpointRepository::validate_base_url( 'http://gateway.example.com/v1', 'deepseek' ) );
		self::assertSame( '', EndpointRepository::validate_base_url( 'http://192.168.1.5/v1', 'deepseek' ) );
		self::assertSame( '', EndpointRepository::validate_base_url( 'https://proxy.example.com/v1?token=abc', 'deepseek' ) );
		self::assertSame( '', EndpointRepository::validate_base_url( 'https://user:pass@proxy.example.com/v1', 'deepseek' ) );
	}

	public function test_needs_reconfigure_keeps_legacy_url_until_custom_is_set(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'baidu' => [
				'enabled'           => true,
				'api_key'           => 'fake-baidu-5555',
				'custom_base_url'   => '',
				'models'            => [],
				'needs_reconfigure' => true,
			],
		];

		self::assertSame(
			ProviderDefinitions::LEGACY_BASE_URLS['baidu'][0],
			self::repo()->resolved_base_url( 'baidu' )
		);
	}

	public function test_v1_saved_option_reads_as_user_choice_only(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'openai' => [
				'name'     => 'OpenAI',
				'base_url' => 'https://api.openai.com/v1',
				'models'   => ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.12.0']['openai'],
				'enabled'  => true,
				'api_key'  => self::FAKE_KEY,
			],
		];

		$endpoint = self::repo()->get( 'openai' );
		self::assertTrue( $endpoint->enabled );
		self::assertSame( self::FAKE_KEY, $endpoint->api_key );
		self::assertSame( [], $endpoint->models, 'historical default copy is not a user choice' );
		self::assertSame( 'https://api.openai.com/v1', self::repo()->resolved_base_url( 'openai' ) );
	}

	public function test_all_and_enabled_cover_known_providers(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => '', 'models' => [] ],
			'qwen'     => [ 'enabled' => true, 'api_key' => '', 'custom_base_url' => '', 'models' => [] ],
		];

		$repo = self::repo();
		self::assertSame( ProviderDefinitions::ids(), array_keys( $repo->all() ) );
		self::assertSame( [ 'deepseek' ], array_keys( $repo->enabled() ) );
	}

	public function test_save_disables_autoload(): void {
		self::repo()->save( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => '', 'models' => [] ] ] );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ EndpointRepository::OPTION ] );
	}

	public function test_sanitize_keeps_valid_custom_base_url_and_drops_metadata(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [
				'enabled' => false,
				'api_key' => self::FAKE_KEY,
				'models'  => [ 'my-finetune' ],
			],
		];

		$out = AdminPage::instance()->sanitize_endpoints(
			[
				'deepseek' => [
					'enabled'         => '1',
					'api_key'         => '',
					'custom_base_url' => ' https://proxy.example.com/v1/ ',
					'name'            => 'Hijacked',
					'base_url'        => 'https://evil.example.com',
					'models'          => [ 'gpt-3.5-turbo' ],
				],
			]
		);

		self::assertArrayNotHasKey( 'name', $out['deepseek'] );
		self::assertArrayNotHasKey( 'base_url', $out['deepseek'] );
		self::assertSame( 'https://proxy.example.com/v1', $out['deepseek']['custom_base_url'] );
		self::assertTrue( $out['deepseek']['enabled'] );
		self::assertSame( self::FAKE_KEY, $out['deepseek']['api_key'], 'blank input keeps the saved key' );
		self::assertSame( [ 'my-finetune' ], $out['deepseek']['models'], 'submitted models are not stored' );
	}

	public function test_sanitize_rejects_invalid_custom_base_url_and_keeps_previous(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'qwen' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => 'https://old.example.com/v1', 'models' => [] ],
		];

		$out = AdminPage::instance()->sanitize_endpoints(
			[ 'qwen' => [ 'enabled' => '1', 'api_key' => '', 'custom_base_url' => 'http://plain.example.com/v1' ] ]
		);

		self::assertSame( 'https://old.example.com/v1', $out['qwen']['custom_base_url'] );
		self::assertNotEmpty( $GLOBALS['wpmind_test_settings_errors'] );
		self::assertStringContainsString( 'qwen', $GLOBALS['wpmind_test_settings_errors'][0]['message'] );
	}

	public function test_sanitize_clears_key_and_lifts_needs_reconfigure(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'baidu' => [
				'enabled'           => true,
				'api_key'           => 'fake-baidu-5555',
				'custom_base_url'   => '',
				'models'            => [],
				'needs_reconfigure' => true,
			],
		];

		$kept = AdminPage::instance()->sanitize_endpoints( [ 'baidu' => [ 'enabled' => '1', 'api_key' => '' ] ] );
		self::assertTrue( $kept['baidu']['needs_reconfigure'] );

		$cleared = AdminPage::instance()->sanitize_endpoints( [ 'baidu' => [ 'enabled' => '1', 'clear_api_key' => '1' ] ] );
		self::assertSame( '', $cleared['baidu']['api_key'] );
		self::assertArrayNotHasKey( 'needs_reconfigure', $cleared['baidu'] );
	}

	public function test_connection_test_rejects_posted_url_with_saved_key(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => '', 'models' => [] ],
		];

		$result = self::repo()->connection_test_target( 'deepseek', '', 'https://evil.example.com/v1' );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'wpmind_unsaved_base_url', $result->get_error_code() );
		self::assertStringNotContainsString( self::FAKE_KEY, $result->get_error_message() );
	}

	public function test_connection_test_allows_saved_url_with_saved_key(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => 'https://proxy.example.com/v1', 'models' => [] ],
		];

		$result = self::repo()->connection_test_target( 'deepseek', '', 'https://proxy.example.com/v1/' );
		self::assertSame(
			[
				'base_url'       => 'https://proxy.example.com/v1',
				'api_key'        => self::FAKE_KEY,
				'uses_saved_key' => true,
			],
			$result
		);
	}

	public function test_connection_test_allows_posted_url_with_new_key(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_KEY, 'custom_base_url' => '', 'models' => [] ],
		];

		$result = self::repo()->connection_test_target( 'deepseek', 'sk-fake-new-666666666666', 'https://new.example.com/v1' );
		self::assertSame( 'https://new.example.com/v1', $result['base_url'] );
		self::assertSame( 'sk-fake-new-666666666666', $result['api_key'] );
		self::assertFalse( $result['uses_saved_key'] );

		$invalid = self::repo()->connection_test_target( 'deepseek', 'sk-fake-new-666666666666', 'http://new.example.com/v1' );
		self::assertInstanceOf( WP_Error::class, $invalid );
		self::assertSame( 'wpmind_invalid_base_url', $invalid->get_error_code() );
	}

	public function test_connection_test_unknown_provider_and_missing_key(): void {
		$unknown = self::repo()->connection_test_target( 'not-a-provider', '', '' );
		self::assertSame( 'wpmind_unknown_provider', $unknown->get_error_code() );

		$missing = self::repo()->connection_test_target( 'deepseek', '', '' );
		self::assertSame( 'wpmind_api_key_missing', $missing->get_error_code() );
	}

	public function test_static_resolve_returns_default_when_nothing_saved(): void {
		self::assertSame(
			'https://api.deepseek.com/v1',
			EndpointRepository::resolve( 'deepseek', 'https://api.deepseek.com/v1' )
		);
	}
}
