<?php
/**
 * M013_EndpointsV2 + Migrator 测试（endpoint schema v2，§5.1/§5.2）。
 *
 * 基准是 v0.11.5 正式版；0.12.0 作为附加用例。
 * 测试里的 key 都是假的占位串，不对应任何真实账号；不发任何网络请求。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Admin\AdminPage;
use WPMind\Core\RewriteFlush;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Migrations\M013_EndpointsV2;
use WPMind\Provider\Migrations\Migrator;
use WPMind\Provider\ProviderDefinitions;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/RewriteFlush.php';

/**
 * @covers \WPMind\Provider\Migrations\M013_EndpointsV2
 * @covers \WPMind\Provider\Migrations\Migrator
 * @covers \WPMind\Provider\EndpointRepository
 */
final class MigrationEndpointsV2Test extends TestCase {

	private const FAKE_OPENAI_KEY   = 'sk-fake-openai-000000000000';
	private const FAKE_DEEPSEEK_KEY = 'sk-fake-deepseek-11111111111';
	private const FAKE_BAIDU_KEY    = 'fake-baidu-legacy-key-2222';
	private const FAKE_NEW_KEY      = 'sk-fake-rotated-in-old-9999';

	private const LEGACY_BASE = [
		'openai'      => 'https://api.openai.com/v1',
		'anthropic'   => 'https://api.anthropic.com/v1',
		'google'      => 'https://generativelanguage.googleapis.com/v1beta',
		'deepseek'    => 'https://api.deepseek.com/v1',
		'qwen'        => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
		'zhipu'       => 'https://open.bigmodel.cn/api/paas/v4',
		'moonshot'    => 'https://api.moonshot.cn/v1',
		'doubao'      => 'https://ark.cn-beijing.volces.com/api/v3',
		'siliconflow' => 'https://api.siliconflow.cn/v1',
		'baidu'       => 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop',
		'minimax'     => 'https://api.minimax.chat/v1',
	];

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']               = [];
		$GLOBALS['wpmind_test_autoload']              = [];
		$GLOBALS['wpmind_test_filters']               = [];
		$GLOBALS['wpmind_test_settings_errors']       = [];
		$GLOBALS['wpmind_test_update_option_filters'] = [];
	}

	/**
	 * 按旧版 sanitize_endpoints() 的写法造一份 v1 option（默认 v0.11.5）。
	 *
	 * @param array<string, array<string, mixed>> $overrides
	 * @return array<string, array<string, mixed>>
	 */
	private static function v1_option( array $overrides = [], string $version = '0.11.5' ): array {
		$option = [];
		foreach ( ProviderDefinitions::DEFINITIONS as $id => $definition ) {
			$option[ $id ] = array_merge(
				[
					'name'     => $definition['name'],
					'base_url' => self::LEGACY_BASE[ $id ],
					'models'   => ProviderDefinitions::HISTORICAL_DEFAULT_MODELS[ $version ][ $id ],
					'enabled'  => false,
					'api_key'  => '',
				],
				$overrides[ $id ] ?? []
			);
		}
		return $option;
	}

	/**
	 * 模拟 v0.11.5 读取：wp_parse_args( $saved[$id], $default )。
	 */
	private static function read_like_0115( string $id ): array {
		$saved   = self::stored();
		$default = [
			'name'     => ProviderDefinitions::DEFINITIONS[ $id ]['name'],
			'base_url' => self::LEGACY_BASE[ $id ],
			'models'   => ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.11.5'][ $id ],
			'enabled'  => false,
			'api_key'  => '',
		];
		return array_merge( $default, is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : [] );
	}

	/**
	 * 模拟在 v0.11.5 里点一次「保存设置」：强制写回 name/base_url/默认 models。
	 */
	private static function save_like_0115( array $changes ): void {
		$saved = self::stored();
		foreach ( ProviderDefinitions::ids() as $id ) {
			$current      = self::read_like_0115( $id );
			$saved[ $id ] = [
				'name'     => ProviderDefinitions::DEFINITIONS[ $id ]['name'],
				'base_url' => self::LEGACY_BASE[ $id ],
				'models'   => ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.11.5'][ $id ],
				'enabled'  => (bool) ( $changes[ $id ]['enabled'] ?? $current['enabled'] ),
				'api_key'  => (string) ( $changes[ $id ]['api_key'] ?? $current['api_key'] ),
			];
			// 真实 v0.11.5 表单保存不存 custom_base_url；只有显式传入（模拟直接改 option）才写.
			if ( array_key_exists( 'custom_base_url', $changes[ $id ] ?? [] ) ) {
				$saved[ $id ]['custom_base_url'] = (string) $changes[ $id ]['custom_base_url'];
			}
		}
		self::seed( $saved );
	}

	/**
	 * 模拟 v0.11.5 ChatService 取请求地址：custom_base_url ?? base_url。
	 */
	private static function url_like_0115( string $id ): string {
		$endpoint = self::stored()[ $id ] ?? [];
		return (string) ( $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '' );
	}

	private static function seed( array $option ): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = $option;
	}

	private static function stored(): array {
		return $GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ];
	}

	private static function v2(): array {
		return $GLOBALS['wpmind_test_options'][ EndpointRepository::V2_OPTION ];
	}

	private static function opt( string $name ) {
		return $GLOBALS['wpmind_test_options'][ $name ] ?? null;
	}

	public function test_v0115_upgrade_migrates_into_v2_and_keeps_legacy_untouched(): void {
		$v1 = self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] );
		self::seed( $v1 );

		Migrator::maybe_run();

		self::assertSame( $v1, self::stored(), 'F2: legacy fields are never cleared or deleted' );
		$openai = self::v2()['providers']['openai'];
		self::assertSame( [], $openai['models'], 'default copy is not stored in v2' );
		self::assertTrue( $openai['enabled'] );
		self::assertSame( '', $openai['custom_base_url'] );
		self::assertSame( EndpointRepository::legacy_hash( $v1 ), self::v2()['legacy_hash'] );
		self::assertSame( Migrator::CURRENT_DB_VERSION, self::opt( Migrator::SCHEMA_VERSION_OPTION ) );

		$endpoint = ( new EndpointRepository() )->get( 'openai' );
		self::assertTrue( $endpoint->enabled );
		self::assertSame( self::FAKE_OPENAI_KEY, $endpoint->api_key, 'key is read from the legacy option' );
	}

	public function test_v1_custom_models_are_kept_as_user_choice(): void {
		self::seed(
			self::v1_option(
				[
					'deepseek' => [
						'enabled' => true,
						'api_key' => self::FAKE_DEEPSEEK_KEY,
						'models'  => [ 'deepseek-chat', ' my-finetune ', '' ],
					],
				]
			)
		);

		( new Migrator() )->run();

		self::assertSame( [ 'deepseek-chat', 'my-finetune' ], self::v2()['providers']['deepseek']['models'] );
		self::assertSame( [], self::v2()['providers']['qwen']['models'] );
	}

	public function test_v1_entry_with_missing_fields_is_normalized(): void {
		$option             = self::v1_option();
		$option['moonshot'] = [
			'enabled' => true,
			'api_key' => 'fake-moonshot-key-3333',
		];
		self::seed( $option );

		( new Migrator() )->run();

		$moonshot = self::v2()['providers']['moonshot'];
		self::assertSame( [], $moonshot['models'] );
		self::assertSame( '', $moonshot['custom_base_url'] );
		self::assertTrue( $moonshot['enabled'] );
		self::assertSame( 'fake-moonshot-key-3333', ( new EndpointRepository() )->get( 'moonshot' )->api_key );
	}

	public function test_running_twice_is_idempotent(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );

		( new Migrator() )->run();
		$v2  = self::v2();
		$log = Migrator::log();

		self::assertSame( [], ( new Migrator() )->run(), 'nothing pending after the first run' );
		Migrator::maybe_run_admin();
		self::assertSame( $v2, self::v2() );
		self::assertSame( $log, Migrator::log() );

		$outcome = ( new M013_EndpointsV2() )->run();
		self::assertSame( 'noop', $outcome['result'] );
		self::assertSame( $v2, self::v2() );
	}

	public function test_rollback_to_0115_reads_non_empty_models_and_key(): void {
		self::seed( self::v1_option( [ 'baidu' => [ 'enabled' => true, 'api_key' => self::FAKE_BAIDU_KEY ], 'qwen' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ] ) );
		Migrator::maybe_run();

		// 新版本里保存一次设置：镜像照样写旧字段，没配过 models 就写历史默认值.
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = AdminPage::instance()->sanitize_endpoints(
			[
				'baidu' => [ 'enabled' => '1', 'api_key' => '' ],
				'qwen'  => [ 'enabled' => '1', 'api_key' => '' ],
			]
		);

		foreach ( [ 'baidu', 'qwen' ] as $id ) {
			$old = self::read_like_0115( $id );
			self::assertNotSame( [], $old['models'], $id . ': v0.11.5 must see a non-empty model list' );
			self::assertSame( ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.12.0'][ $id ], $old['models'] );
			self::assertTrue( (bool) $old['enabled'] );
			self::assertNotSame( '', $old['api_key'] );
			self::assertNotSame( '', $old['name'] );
		}
		self::assertSame( [], self::v2()['providers']['qwen']['models'], 'v2 still has no default list' );
		self::assertSame( [], ( new Migrator() )->pending(), 'own mirror write does not trigger a re-migration' );
	}

	public function test_rollback_edit_then_upgrade_remigrates(): void {
		$v1 = self::v1_option( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ] );
		self::seed( $v1 );
		Migrator::maybe_run();

		// 新版本里填了自定义 Base URL.
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = AdminPage::instance()->sanitize_endpoints(
			[ 'deepseek' => [ 'enabled' => '1', 'api_key' => '', 'custom_base_url' => 'https://proxy.example.com/v1' ] ]
		);

		// 回滚到 v0.11.5，关掉 deepseek、打开 openai 并换了 key，再升级回来.
		self::save_like_0115(
			[
				'deepseek' => [ 'enabled' => false ],
				'openai'   => [ 'enabled' => true, 'api_key' => self::FAKE_NEW_KEY ],
			]
		);

		Migrator::maybe_run_admin();

		$providers = self::v2()['providers'];
		self::assertFalse( $providers['deepseek']['enabled'] );
		self::assertTrue( $providers['openai']['enabled'] );
		self::assertSame( 'https://proxy.example.com/v1', $providers['deepseek']['custom_base_url'], 'v2-only field survives' );
		self::assertSame( self::FAKE_NEW_KEY, ( new EndpointRepository() )->get( 'openai' )->api_key );
		self::assertSame( EndpointRepository::legacy_hash( self::stored() ), self::v2()['legacy_hash'] );

		self::assertSame( M013_EndpointsV2::strip_keys( $v1 ), self::opt( M013_EndpointsV2::BACKUP_OPTION ), 'v1 backup is never overwritten' );
		$index = self::opt( M013_EndpointsV2::BACKUP_INDEX_OPTION );
		self::assertCount( 1, $index );
		self::assertStringStartsWith( M013_EndpointsV2::BACKUP_PREFIX, $index[0] );
		self::assertTrue( self::stored()['openai']['enabled'], 'legacy option keeps what the old version saved' );
		self::assertSame( [ 'ok', 'ok' ], array_column( Migrator::log(), 'result' ) );
	}

	public function test_api_key_change_in_old_version_alone_triggers_remigration(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );
		Migrator::maybe_run();

		self::save_like_0115( [ 'openai' => [ 'api_key' => self::FAKE_NEW_KEY ] ] );

		self::assertNotSame( [], ( new Migrator() )->pending(), 'hash includes the key fingerprint' );
	}

	public function test_timestamped_backups_keep_at_most_three(): void {
		$v1 = self::v1_option();
		self::seed( $v1 );
		Migrator::maybe_run();

		for ( $i = 0; $i < 5; $i++ ) {
			self::save_like_0115( [ 'openai' => [ 'enabled' => 0 === $i % 2, 'api_key' => 'sk-fake-loop-' . $i ] ] );
			Migrator::maybe_run_admin();
		}

		$index = self::opt( M013_EndpointsV2::BACKUP_INDEX_OPTION );
		self::assertCount( M013_EndpointsV2::BACKUP_MAX, $index );
		$names = array_filter(
			array_keys( $GLOBALS['wpmind_test_options'] ),
			static fn( string $name ): bool => str_starts_with( $name, M013_EndpointsV2::BACKUP_PREFIX )
		);
		self::assertSame( $index, array_values( $names ), 'oldest backups are deleted' );
		self::assertSame( M013_EndpointsV2::strip_keys( $v1 ), self::opt( M013_EndpointsV2::BACKUP_OPTION ) );
		foreach ( $index as $name ) {
			self::assertFalse( $GLOBALS['wpmind_test_autoload'][ $name ] );
			self::assertStringNotContainsString( 'sk-fake-loop-', (string) json_encode( self::opt( $name ) ) );
		}
	}

	public function test_v2_never_contains_api_key(): void {
		self::seed(
			self::v1_option(
				[
					'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ],
					'baidu'  => [ 'enabled' => true, 'api_key' => self::FAKE_BAIDU_KEY ],
				]
			)
		);
		Migrator::maybe_run();
		$after_migration = (string) json_encode( self::v2() );

		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = AdminPage::instance()->sanitize_endpoints(
			[ 'openai' => [ 'enabled' => '1', 'api_key' => 'sk-fake-typed-in-new-777777' ] ]
		);
		$after_save = (string) json_encode( self::v2() );

		foreach ( [ $after_migration, $after_save ] as $dump ) {
			self::assertStringNotContainsString( 'api_key', $dump );
			self::assertStringNotContainsString( self::FAKE_OPENAI_KEY, $dump );
			self::assertStringNotContainsString( self::FAKE_BAIDU_KEY, $dump );
			self::assertStringNotContainsString( 'sk-fake-typed-in-new-777777', $dump );
		}
		self::assertSame( 'sk-fake-typed-in-new-777777', self::stored()['openai']['api_key'], 'key lives only in the legacy option' );
	}

	public function test_plain_request_does_not_trigger_migration(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );
		Migrator::maybe_run();
		$v2  = self::v2();
		$log = Migrator::log();

		// 旧字段被改了，但普通请求（plugins_loaded）只比较版本号.
		self::save_like_0115( [ 'openai' => [ 'enabled' => false ] ] );
		for ( $i = 0; $i < 5; $i++ ) {
			Migrator::maybe_run();
		}
		self::assertSame( [], ( new Migrator() )->pending( false ) );
		self::assertSame( $v2, self::v2() );
		self::assertSame( $log, Migrator::log() );

		// 不改设置时后台请求也什么都不做.
		self::seed( self::stored() );
		Migrator::maybe_run_admin();
		Migrator::maybe_run_admin();
		self::assertCount( 2, Migrator::log(), 'admin_init re-migrates once for the change, then stays quiet' );
	}

	public function test_v0120_upgrade_additional_case(): void {
		$v1 = self::v1_option( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ], '0.12.0' );
		self::seed( $v1 );

		Migrator::maybe_run();

		self::assertSame( $v1, self::stored() );
		self::assertSame( [], self::v2()['providers']['deepseek']['models'] );
		self::assertTrue( self::v2()['providers']['deepseek']['enabled'] );
		self::assertSame( 1300, self::opt( Migrator::SCHEMA_VERSION_OPTION ) );
	}

	/**
	 * F1 之前的构建：Migrator 把 1300 写进了 wpmind_db_version（之后 RewriteFlush
	 * 可能已经把它纠正成 2），原 option 已是 v2 形状，没有 schema 版本号也没有 v2 option。
	 */
	public function test_pre_split_site_only_gets_version_and_hash(): void {
		foreach ( [ 1300, 2 ] as $db_version ) {
			$this->setUp();
			$pre_split = [
				'openai' => [
					'enabled'         => true,
					'api_key'         => self::FAKE_OPENAI_KEY,
					'custom_base_url' => 'https://proxy.example.com/v1',
					'models'          => [],
					'default_model'   => '',
				],
				'qwen'   => [ 'enabled' => false, 'api_key' => '', 'custom_base_url' => '', 'models' => [ 'qwen-max' ], 'default_model' => '' ],
			];
			self::seed( $pre_split );
			$GLOBALS['wpmind_test_options']['wpmind_db_version'] = $db_version;

			Migrator::maybe_run();

			self::assertSame( $pre_split, self::stored(), 'legacy option untouched' );
			self::assertSame( 1300, self::opt( Migrator::SCHEMA_VERSION_OPTION ) );
			self::assertSame( EndpointRepository::legacy_hash( $pre_split ), self::v2()['legacy_hash'] );
			self::assertSame( 'https://proxy.example.com/v1', self::v2()['providers']['openai']['custom_base_url'] );
			self::assertSame( [ 'qwen-max' ], self::v2()['providers']['qwen']['models'] );
			self::assertNull( self::opt( M013_EndpointsV2::BACKUP_OPTION ), 'no backup' );
			self::assertNull( self::opt( M013_EndpointsV2::BACKUP_INDEX_OPTION ), 'no timestamped backup' );
			self::assertSame( 'noop', Migrator::log()[0]['result'] );
			self::assertSame( [], ( new Migrator() )->pending() );
			self::assertSame( $db_version, self::opt( 'wpmind_db_version' ), 'Migrator never writes wpmind_db_version' );
		}
	}

	public function test_skips_when_lock_is_held(): void {
		$v1 = self::v1_option();
		self::seed( $v1 );
		$GLOBALS['wpmind_test_options'][ Migrator::LOCK_OPTION ] = time() - 10;

		self::assertSame( [], ( new Migrator() )->run() );
		self::assertSame( $v1, self::stored() );
		self::assertNull( self::opt( EndpointRepository::V2_OPTION ) );
		self::assertNull( self::opt( Migrator::SCHEMA_VERSION_OPTION ) );
		self::assertNotNull( self::opt( Migrator::LOCK_OPTION ), 'someone else\'s lock is left alone' );
	}

	public function test_stale_lock_is_taken_over_and_released(): void {
		self::seed( self::v1_option() );
		$GLOBALS['wpmind_test_options'][ Migrator::LOCK_OPTION ] = time() - Migrator::LOCK_TTL - 1;

		$entries = ( new Migrator() )->run();

		self::assertSame( 'ok', $entries[0]['result'] );
		self::assertNull( self::opt( Migrator::LOCK_OPTION ) );
	}

	public function test_backup_has_no_api_key_and_log_has_no_secrets(): void {
		self::seed(
			self::v1_option(
				[
					'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ],
					'baidu'  => [ 'enabled' => true, 'api_key' => self::FAKE_BAIDU_KEY ],
				]
			)
		);

		( new Migrator() )->run();

		$backup = self::opt( M013_EndpointsV2::BACKUP_OPTION );
		self::assertSame( 'OpenAI', $backup['openai']['name'] );
		foreach ( $backup as $entry ) {
			self::assertArrayNotHasKey( 'api_key', $entry );
		}

		$dump = json_encode( [ $backup, Migrator::log() ] );
		self::assertStringNotContainsString( self::FAKE_OPENAI_KEY, $dump );
		self::assertStringNotContainsString( self::FAKE_BAIDU_KEY, $dump );
		self::assertStringNotContainsString( 'https://', json_encode( Migrator::log() ), 'log has no URLs' );
	}

	public function test_new_options_are_not_autoloaded(): void {
		self::seed( self::v1_option() );

		( new Migrator() )->run();

		foreach ( [ EndpointRepository::OPTION, EndpointRepository::V2_OPTION, M013_EndpointsV2::BACKUP_OPTION, Migrator::SCHEMA_VERSION_OPTION, Migrator::LOG_OPTION ] as $option ) {
			self::assertArrayHasKey( $option, $GLOBALS['wpmind_test_autoload'], $option );
			self::assertFalse( $GLOBALS['wpmind_test_autoload'][ $option ], $option );
		}
	}

	public function test_configured_baidu_gets_needs_reconfigure_and_keeps_legacy_url(): void {
		self::seed( self::v1_option( [ 'baidu' => [ 'enabled' => true, 'api_key' => self::FAKE_BAIDU_KEY ] ] ) );

		( new Migrator() )->run();

		self::assertTrue( self::v2()['providers']['baidu']['needs_reconfigure'] );
		self::assertTrue( self::v2()['providers']['baidu']['enabled'], 'enabled is not touched' );
		self::assertSame(
			'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop',
			( new EndpointRepository() )->resolved_base_url( 'baidu' ),
			'no automatic switch to the new address'
		);
		self::assertContains( 'baidu: needs_reconfigure', Migrator::log()[0]['notes'] );
	}

	public function test_unconfigured_baidu_uses_qianfan_v2(): void {
		self::seed( self::v1_option() );

		( new Migrator() )->run();

		self::assertArrayNotHasKey( 'needs_reconfigure', self::v2()['providers']['baidu'] );
		self::assertSame( 'https://qianfan.baidubce.com/v2', ( new EndpointRepository() )->resolved_base_url( 'baidu' ) );
	}

	public function test_verify_failure_restores_v2(): void {
		$v1 = self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] );
		self::seed( $v1 );

		// 模拟写入 v2 时被别的代码改掉了 enabled（过滤器或并发保存）。
		$GLOBALS['wpmind_test_update_option_filters'][ EndpointRepository::V2_OPTION ] = static function ( $value ) {
			if ( is_array( $value ) && isset( $value['providers']['openai'] ) ) {
				$value['providers']['openai']['enabled'] = false;
			}
			return $value;
		};

		$entries = ( new Migrator() )->run();

		self::assertSame( 'rolled_back', $entries[0]['result'] );
		self::assertSame( $v1, self::stored(), 'legacy data untouched' );
		self::assertNull( self::opt( EndpointRepository::V2_OPTION ), 'v2 restored to "not migrated"' );
		self::assertNull( self::opt( Migrator::SCHEMA_VERSION_OPTION ) );
		self::assertNull( self::opt( Migrator::LOCK_OPTION ) );

		// 回滚后进入退避期，不会每个请求都重试。
		self::assertSame( [], ( new Migrator() )->pending() );
	}

	public function test_fresh_install_creates_non_autoloaded_empty_options(): void {
		$entries = ( new Migrator() )->run();

		self::assertSame( 'noop', $entries[0]['result'] );
		self::assertSame( [], self::stored() );
		self::assertSame( [], self::v2()['providers'] );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ EndpointRepository::OPTION ] );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ EndpointRepository::V2_OPTION ] );
		self::assertSame( 1300, self::opt( Migrator::SCHEMA_VERSION_OPTION ) );
		self::assertSame( [], ( new Migrator() )->pending() );
	}

	public function test_log_keeps_at_most_twenty_entries(): void {
		$old = [];
		for ( $i = 0; $i < 25; $i++ ) {
			$old[] = [
				'id'     => 'older',
				'at'     => 1000 + $i,
				'result' => 'ok',
				'notes'  => [],
			];
		}
		$GLOBALS['wpmind_test_options'][ Migrator::LOG_OPTION ] = $old;
		self::seed( self::v1_option() );

		( new Migrator() )->run();

		$log = Migrator::log();
		self::assertCount( Migrator::LOG_MAX, $log );
		self::assertSame( 'm013_endpoints_v2', end( $log )['id'] );
	}

	/**
	 * F1：从 v0.11.5 升级（两个版本号都没有），Migrator 和 RewriteFlush 都要执行；
	 * 0→1300 之后 rewrite 重建仍会执行。
	 */
	public function test_upgrade_runs_both_migrator_and_rewrite_rebuild(): void {
		self::seed( self::v1_option( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ] ) );

		Migrator::maybe_run();
		self::assertSame( 1300, self::opt( 'wpmind_endpoint_schema_version' ) );
		self::assertTrue( RewriteFlush::maybe_upgrade_db( 2 ), 'rewrite rebuild must not be skipped by the schema version' );

		self::assertSame( 'wpmind_endpoint_schema_version', Migrator::SCHEMA_VERSION_OPTION );
		self::assertSame( 2, self::opt( 'wpmind_db_version' ) );
		self::assertSame( '1', self::opt( RewriteFlush::FLAG_OPTION ) );
		self::assertFalse( $GLOBALS['wpmind_test_autoload']['wpmind_endpoint_schema_version'] );
		self::assertFalse( $GLOBALS['wpmind_test_autoload']['wpmind_db_version'] );
	}

	public function test_historical_default_lists_cover_all_released_versions(): void {
		foreach ( [ '0.11.3', '0.11.4', '0.11.5', '0.12.0' ] as $version ) {
			self::assertArrayHasKey( $version, ProviderDefinitions::HISTORICAL_DEFAULT_MODELS );
			self::assertSame(
				ProviderDefinitions::ids(),
				array_keys( ProviderDefinitions::HISTORICAL_DEFAULT_MODELS[ $version ] ),
				$version
			);
		}
		self::assertTrue( ProviderDefinitions::is_historical_default_models( 'openai', [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ] ) );
		self::assertFalse( ProviderDefinitions::is_historical_default_models( 'openai', [ 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' ] ), 'order matters' );
		self::assertFalse( ProviderDefinitions::is_historical_default_models( 'openai', [] ) );
	}

	public function test_f5_custom_base_url_reaches_mirror_and_hash(): void {
		self::seed( self::v1_option( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ] ) );
		Migrator::maybe_run();

		$mirror = AdminPage::instance()->sanitize_endpoints(
			[ 'deepseek' => [ 'enabled' => '1', 'api_key' => '', 'custom_base_url' => 'https://proxy.example.com/v1' ] ]
		);
		self::seed( $mirror );

		self::assertSame( 'https://proxy.example.com/v1', $mirror['deepseek']['custom_base_url'] );
		self::assertArrayNotHasKey( 'custom_base_url', $mirror['openai'], 'empty custom url is not written' );
		self::assertSame( 'https://proxy.example.com/v1', self::url_like_0115( 'deepseek' ), 'v0.11.5 still sends to the relay' );
		self::assertSame( self::FAKE_DEEPSEEK_KEY, self::read_like_0115( 'deepseek' )['api_key'] );

		$without = $mirror;
		unset( $without['deepseek']['custom_base_url'] );
		self::assertNotSame( EndpointRepository::legacy_hash( $without ), EndpointRepository::legacy_hash( $mirror ) );
		self::assertSame( EndpointRepository::legacy_hash( $mirror ), self::v2()['legacy_hash'] );

		// 旧版改了中转地址 → 重新迁移读进 v2；在旧版里清空也要生效.
		self::save_like_0115( [ 'deepseek' => [ 'custom_base_url' => 'https://relay2.example.com/v1' ] ] );
		Migrator::maybe_run_admin();
		self::assertSame( 'https://relay2.example.com/v1', self::v2()['providers']['deepseek']['custom_base_url'] );
		self::save_like_0115( [ 'deepseek' => [ 'custom_base_url' => '' ] ] );
		Migrator::maybe_run_admin();
		self::assertSame( '', self::v2()['providers']['deepseek']['custom_base_url'] );

		$haystack = (string) json_encode( [ self::v2(), Migrator::log() ] );
		foreach ( (array) self::opt( M013_EndpointsV2::BACKUP_INDEX_OPTION ) as $name ) {
			$haystack .= (string) json_encode( self::opt( $name ) );
		}
		$haystack .= (string) json_encode( self::opt( M013_EndpointsV2::BACKUP_OPTION ) );
		self::assertStringNotContainsString( self::FAKE_DEEPSEEK_KEY, (string) $haystack );
	}

	public function test_f5_v0115_custom_base_url_migrates_into_v2(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY, 'custom_base_url' => 'https://relay.example.com/v1' ] ] ) );
		Migrator::maybe_run();

		self::assertSame( 'https://relay.example.com/v1', self::v2()['providers']['openai']['custom_base_url'] );
		self::assertSame( 'https://relay.example.com/v1', self::url_like_0115( 'openai' ) );
	}

	public function test_f7b_rollback_upgrade_without_save_rollback_again_keeps_relay(): void {
		self::seed( self::v1_option( [ 'deepseek' => [ 'enabled' => true, 'api_key' => self::FAKE_DEEPSEEK_KEY ] ] ) );
		Migrator::maybe_run();
		self::seed(
			AdminPage::instance()->sanitize_endpoints(
				[ 'deepseek' => [ 'enabled' => '1', 'api_key' => '', 'custom_base_url' => 'https://proxy.example.com/v1' ] ]
			)
		);

		// 回滚 v0.11.5，在它的表单里保存一次（丢掉 custom_base_url）.
		self::save_like_0115( [ 'qwen' => [ 'enabled' => true ] ] );
		self::assertArrayNotHasKey( 'custom_base_url', self::stored()['deepseek'] );

		// 升级，只来一个 admin 请求，新版里不保存.
		Migrator::maybe_run_admin();
		self::assertSame( 'https://proxy.example.com/v1', self::v2()['providers']['deepseek']['custom_base_url'] );
		self::assertSame( 'https://proxy.example.com/v1', self::stored()['deepseek']['custom_base_url'], 'mirror restored' );
		self::assertSame( EndpointRepository::legacy_hash( self::stored() ), self::v2()['legacy_hash'] );
		self::assertArrayNotHasKey( 'custom_base_url', self::stored()['openai'] );

		// 再次回滚：v0.11.5 仍然发往中转地址；第二个 admin 请求不再迁移.
		self::assertSame( 'https://proxy.example.com/v1', self::url_like_0115( 'deepseek' ) );
		self::assertSame( [], ( new Migrator() )->pending( true ) );
		self::assertStringNotContainsString( self::FAKE_DEEPSEEK_KEY, (string) json_encode( [ self::v2(), Migrator::log() ] ) );
	}

	public function test_f6_key_only_save_in_0115_keeps_v2_custom_models(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );
		Migrator::maybe_run();
		// 新版里用户自配 models（存进 v2），镜像照常重写.
		$v2                                  = self::v2();
		$v2['providers']['openai']['models'] = [ 'my-model-a', 'my-model-b' ];
		( new EndpointRepository() )->save_v2( $v2['providers'], (string) $v2['legacy_hash'] );
		self::seed( AdminPage::instance()->sanitize_endpoints( [ 'openai' => [ 'enabled' => '1', 'api_key' => '' ] ] ) );
		$custom = self::v2()['providers']['openai']['models'];
		self::assertSame( [ 'my-model-a', 'my-model-b' ], $custom );

		// v0.11.5 里只换 key：models 被写回历史默认值.
		self::save_like_0115( [ 'openai' => [ 'api_key' => self::FAKE_NEW_KEY ] ] );
		self::assertSame( ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.11.5']['openai'], self::stored()['openai']['models'] );

		Migrator::maybe_run_admin();

		self::assertSame( $custom, self::v2()['providers']['openai']['models'] );
		self::assertSame( self::FAKE_NEW_KEY, ( new EndpointRepository() )->get( 'openai' )->api_key );
	}
}
