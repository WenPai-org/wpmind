<?php
/**
 * PR1 commit 2 接线测试：8 个 SDK Provider 的 baseUrl()、降级到 0.12 的读取兼容、uninstall 清理列表。
 *
 * 测试里的 key 都是假的占位串；不发任何网络请求。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Migrations\Migrator;
use WPMind\Provider\ProviderDefinitions;

/**
 * @coversNothing
 */
final class EndpointSchemaWiringTest extends TestCase {

	private const FAKE_KEY = 'sk-fake-wiring-555555555555';

	/**
	 * Provider ID => SDK Provider 类。
	 *
	 * @var array<string, class-string>
	 */
	private const SDK_PROVIDERS = [
		'baidu'       => \WPMind\Providers\Baidu\BaiduProvider::class,
		'deepseek'    => \WPMind\Providers\DeepSeek\DeepSeekProvider::class,
		'doubao'      => \WPMind\Providers\Doubao\DoubaoProvider::class,
		'minimax'     => \WPMind\Providers\MiniMax\MiniMaxProvider::class,
		'moonshot'    => \WPMind\Providers\Moonshot\MoonshotProvider::class,
		'qwen'        => \WPMind\Providers\Qwen\QwenProvider::class,
		'siliconflow' => \WPMind\Providers\SiliconFlow\SiliconFlowProvider::class,
		'zhipu'       => \WPMind\Providers\Zhipu\ZhipuProvider::class,
	];

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']               = [];
		$GLOBALS['wpmind_test_autoload']              = [];
		$GLOBALS['wpmind_test_filters']               = [];
		$GLOBALS['wpmind_test_settings_errors']       = [];
		$GLOBALS['wpmind_test_update_option_filters'] = [];
	}

	/**
	 * SDK 的 AbstractApiProvider 不在单测的自动加载里，所以按源码核对 baseUrl() 的接线，
	 * 运行时行为由 EndpointRepository::resolve() 的测试覆盖。
	 */
	private static function provider_source( string $class ): string {
		$rel = str_replace( [ 'WPMind\\', '\\' ], [ '', '/' ], $class ) . '.php';
		$rel = 'includes/' . $rel;
		$src = file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
		self::assertIsString( $src, $rel );
		return $src;
	}

	public function test_sdk_providers_resolve_through_repository_with_matching_fallback(): void {
		foreach ( self::SDK_PROVIDERS as $id => $class ) {
			$src = self::provider_source( $class );
			self::assertMatchesRegularExpression( "/return\\s+'" . preg_quote( $id, '/' ) . "'\\s*;/", $src, $id . ' providerId()' );
			self::assertSame(
				1,
				preg_match( "/EndpointRepository::resolve\\(\\s*static::providerId\\(\\),\\s*'([^']+)'\\s*\\)/", $src, $m ),
				$id . ' baseUrl() must go through EndpointRepository::resolve()'
			);
			self::assertSame( rtrim( ProviderDefinitions::default_base_url( $id ), '/' ), rtrim( $m[1], '/' ), $id . ' fallback must equal ProviderDefinitions default' );
		}
	}

	public function test_resolve_uses_saved_custom_for_every_sdk_provider(): void {
		$saved = [];
		foreach ( array_keys( self::SDK_PROVIDERS ) as $id ) {
			$saved[ $id ] = [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => 'https://gw.example.com/' . $id . '/v1/',
				'models'          => [],
			];
		}
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = $saved;
		foreach ( array_keys( self::SDK_PROVIDERS ) as $id ) {
			self::assertSame( 'https://gw.example.com/' . $id . '/v1', EndpointRepository::resolve( $id, 'https://fallback.invalid' ), $id );
		}
	}

	public function test_resolve_ignores_invalid_custom_and_keeps_legacy_when_reconfigure(): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = [
			'qwen'  => [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => 'https://gw.example.com/v1?key=leak',
			],
			'baidu' => [
				'enabled'           => true,
				'api_key'           => self::FAKE_KEY,
				'needs_reconfigure' => true,
			],
		];
		self::assertSame( 'https://dashscope.aliyuncs.com/compatible-mode/v1', EndpointRepository::resolve( 'qwen' ) );
		self::assertSame( 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop', EndpointRepository::resolve( 'baidu' ) );
	}

	/**
	 * 降级到 0.12.0：0.12 读取时 wp_parse_args( saved, default )。v2 条目缺 models/base_url，
	 * 合并后必须仍是 0.12 能用的形状，且 key 与 enabled 不丢。
	 */
	public function test_v2_entry_is_readable_by_012_loader(): void {
		foreach ( ProviderDefinitions::ids() as $id ) {
			$historical = ProviderDefinitions::historical_default_models( $id )[0] ?? null;
			self::assertNotNull( $historical, $id );
			$v012_default = [
				'name'     => 'x',
				'base_url' => 'https://old.example.com/v1',
				'models'   => $historical,
				'enabled'  => false,
				'api_key'  => '',
			];
			$v2 = [
				'enabled'         => true,
				'api_key'         => self::FAKE_KEY,
				'custom_base_url' => '',
				'models'          => [],
				'default_model'   => '',
			];
			$merged = wp_parse_args( $v2, $v012_default );
			self::assertTrue( $merged['enabled'], $id );
			self::assertSame( self::FAKE_KEY, $merged['api_key'], $id );
			self::assertSame( 'https://old.example.com/v1', $merged['base_url'], $id );
			self::assertIsArray( $merged['models'], $id );
		}
	}

	/**
	 * 降级后 0.12 再保存一次（写回 v1 形状、带默认 models），再升级要被识别成 v1 并重新迁移。
	 */
	public function test_012_resave_shape_is_detected_as_v1(): void {
		foreach ( ProviderDefinitions::ids() as $id ) {
			$historical = ProviderDefinitions::historical_default_models( $id )[0] ?? null;
			self::assertNotNull( $historical, $id );
			self::assertTrue(
				EndpointRepository::is_v1_entry(
					$id,
					[
						'name'     => 'x',
						'base_url' => ProviderDefinitions::default_base_url( $id ),
						'models'   => $historical,
						'enabled'  => true,
						'api_key'  => self::FAKE_KEY,
					]
				),
				$id
			);
			self::assertFalse(
				EndpointRepository::is_v1_entry(
					$id,
					[
						'enabled'         => true,
						'api_key'         => self::FAKE_KEY,
						'custom_base_url' => '',
						'models'          => [],
					]
				),
				$id . ' v2 entry must not be re-migrated'
			);
		}
	}

	public function test_uninstall_lists_every_new_option(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );
		$expected = [
			EndpointRepository::OPTION,
			'wpmind_endpoint_schema_version',
			'wpmind_db_version',
			'wpmind_migration_log',
			'wpmind_migration_lock',
			'wpmind_custom_endpoints_v1_backup',
		];
		foreach ( $expected as $option ) {
			self::assertStringContainsString( "'" . $option . "'", $source, $option . ' must be cleaned on uninstall' );
		}
		self::assertStringContainsString( "'offset'", $source, 'multisite cleanup must page through get_sites() (B15)' );
	}

	public function test_migrator_option_names_match_uninstall_list(): void {
		$ref = new ReflectionClass( Migrator::class );
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );
		foreach ( $ref->getConstants() as $name => $value ) {
			if ( is_string( $value ) && str_starts_with( $value, 'wpmind_' ) ) {
				self::assertStringContainsString( "'" . $value . "'", $source, 'Migrator::' . $name );
			}
		}
	}
}
