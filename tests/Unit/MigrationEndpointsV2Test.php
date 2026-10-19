<?php
/**
 * M013_EndpointsV2 + Migrator 测试。
 *
 * 测试里的 key 都是假的占位串，不对应任何真实账号；不发任何网络请求。
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Migrations\M013_EndpointsV2;
use WPMind\Provider\Migrations\Migrator;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\Secrets;

/**
 * @covers \WPMind\Provider\Migrations\M013_EndpointsV2
 * @covers \WPMind\Provider\Migrations\Migrator
 */
final class MigrationEndpointsV2Test extends TestCase {

	private const FAKE_OPENAI_KEY   = 'sk-fake-openai-000000000000';
	private const FAKE_DEEPSEEK_KEY = 'sk-fake-deepseek-11111111111';
	private const FAKE_BAIDU_KEY    = 'fake-baidu-legacy-key-2222';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']               = [];
		$GLOBALS['wpmind_test_autoload']              = [];
		$GLOBALS['wpmind_test_filters']               = [];
		$GLOBALS['wpmind_test_update_option_filters'] = [];
	}

	/**
	 * 按 0.12.0 sanitize_endpoints() 的写法造一份 v1 option。
	 *
	 * @param array<string, array{enabled?:bool, api_key?:string, models?:list<string>}> $overrides
	 * @return array<string, array<string, mixed>>
	 */
	private static function v1_option( array $overrides = [] ): array {
		$v012_models = ProviderDefinitions::HISTORICAL_DEFAULT_MODELS['0.12.0'];
		$v012_base   = [
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
		$option = [];
		foreach ( ProviderDefinitions::DEFINITIONS as $id => $definition ) {
			$option[ $id ] = array_merge(
				[
					'name'     => $definition['name'],
					'base_url' => $v012_base[ $id ],
					'models'   => $v012_models[ $id ],
					'enabled'  => false,
					'api_key'  => '',
				],
				$overrides[ $id ] ?? []
			);
		}
		return $option;
	}

	private static function seed( array $option ): void {
		$GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ] = $option;
	}

	private static function stored(): array {
		return $GLOBALS['wpmind_test_options'][ EndpointRepository::OPTION ];
	}

	public function test_v1_default_models_are_cleared_and_metadata_dropped(): void {
		self::seed(
			self::v1_option(
				[
					'openai' => [
						'enabled' => true,
						'api_key' => self::FAKE_OPENAI_KEY,
					],
				]
			)
		);

		$entries = ( new Migrator() )->run();

		self::assertCount( 1, $entries );
		self::assertSame( 'ok', $entries[0]['result'] );
		$openai = self::stored()['openai'];
		self::assertSame( [], $openai['models'] );
		self::assertSame( '', $openai['custom_base_url'] );
		self::assertSame( '', $openai['default_model'] );
		foreach ( EndpointRepository::LEGACY_FIELDS as $field ) {
			self::assertArrayNotHasKey( $field, $openai );
		}
		self::assertTrue( $openai['enabled'] );
		self::assertSame( self::FAKE_OPENAI_KEY, $openai['api_key'] );
		self::assertSame( Migrator::CURRENT_DB_VERSION, $GLOBALS['wpmind_test_options'][ Migrator::DB_VERSION_OPTION ] );
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

		self::assertSame( [ 'deepseek-chat', 'my-finetune' ], self::stored()['deepseek']['models'] );
		self::assertSame( [], self::stored()['qwen']['models'] );
	}

	public function test_v1_entry_with_missing_fields_is_normalized(): void {
		$option             = self::v1_option();
		$option['moonshot'] = [
			'enabled' => true,
			'api_key' => 'fake-moonshot-key-3333',
		];
		self::seed( $option );

		( new Migrator() )->run();

		$moonshot = self::stored()['moonshot'];
		self::assertSame( [], $moonshot['models'] );
		self::assertSame( '', $moonshot['custom_base_url'] );
		self::assertTrue( $moonshot['enabled'] );
		self::assertSame( 'fake-moonshot-key-3333', $moonshot['api_key'] );
	}

	public function test_running_twice_is_idempotent(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );

		( new Migrator() )->run();
		$first = self::stored();
		$log   = Migrator::log();

		$second_run = ( new Migrator() )->run();
		self::assertSame( [], $second_run, 'nothing pending after the first run' );
		self::assertSame( $first, self::stored() );
		self::assertSame( $log, Migrator::log() );

		// 直接再跑一次迁移本身也不改数据。
		$outcome = ( new M013_EndpointsV2() )->run();
		self::assertSame( 'noop', $outcome['result'] );
		self::assertSame( $first, self::stored() );
	}

	public function test_downgrade_then_v1_shape_is_migrated_again(): void {
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );
		( new Migrator() )->run();
		self::assertSame( 1300, $GLOBALS['wpmind_test_options'][ Migrator::DB_VERSION_OPTION ] );

		// 降级到 0.12.0 并保存一次：option 又变回 v1 形状，db_version 仍是 1300。
		self::seed( self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] ) );
		self::assertNotSame( [], ( new Migrator() )->pending() );

		Migrator::maybe_run();

		self::assertArrayNotHasKey( 'name', self::stored()['openai'] );
		self::assertSame( [], self::stored()['openai']['models'] );
		self::assertCount( 2, Migrator::log() );
	}

	public function test_skips_when_lock_is_held(): void {
		$v1 = self::v1_option();
		self::seed( $v1 );
		$GLOBALS['wpmind_test_options'][ Migrator::LOCK_OPTION ] = time() - 10;

		self::assertSame( [], ( new Migrator() )->run() );
		self::assertSame( $v1, self::stored() );
		self::assertArrayNotHasKey( Migrator::DB_VERSION_OPTION, $GLOBALS['wpmind_test_options'] );
		self::assertArrayHasKey( Migrator::LOCK_OPTION, $GLOBALS['wpmind_test_options'], 'someone else\'s lock is left alone' );
	}

	public function test_stale_lock_is_taken_over_and_released(): void {
		self::seed( self::v1_option() );
		$GLOBALS['wpmind_test_options'][ Migrator::LOCK_OPTION ] = time() - Migrator::LOCK_TTL - 1;

		$entries = ( new Migrator() )->run();

		self::assertSame( 'ok', $entries[0]['result'] );
		self::assertArrayNotHasKey( Migrator::LOCK_OPTION, $GLOBALS['wpmind_test_options'] );
	}

	public function test_key_fingerprints_and_enabled_are_unchanged(): void {
		$v1 = self::v1_option(
			[
				'openai'   => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ],
				'deepseek' => [ 'enabled' => false, 'api_key' => self::FAKE_DEEPSEEK_KEY ],
			]
		);
		self::seed( $v1 );

		( new Migrator() )->run();

		self::assertSame( M013_EndpointsV2::snapshot( $v1 ), M013_EndpointsV2::snapshot( self::stored() ) );
		foreach ( $v1 as $id => $entry ) {
			self::assertSame( $entry['api_key'], self::stored()[ $id ]['api_key'] );
			self::assertSame( $entry['enabled'], self::stored()[ $id ]['enabled'] );
		}
		self::assertSame(
			Secrets::fingerprint( self::FAKE_DEEPSEEK_KEY ),
			M013_EndpointsV2::snapshot( self::stored() )['deepseek']['key']
		);
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

		$backup = $GLOBALS['wpmind_test_options'][ M013_EndpointsV2::BACKUP_OPTION ];
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

		foreach ( [ EndpointRepository::OPTION, M013_EndpointsV2::BACKUP_OPTION, Migrator::DB_VERSION_OPTION, Migrator::LOG_OPTION ] as $option ) {
			self::assertArrayHasKey( $option, $GLOBALS['wpmind_test_autoload'], $option );
			self::assertFalse( $GLOBALS['wpmind_test_autoload'][ $option ], $option );
		}
	}

	public function test_configured_baidu_gets_needs_reconfigure_and_keeps_legacy_url(): void {
		self::seed( self::v1_option( [ 'baidu' => [ 'enabled' => true, 'api_key' => self::FAKE_BAIDU_KEY ] ] ) );

		( new Migrator() )->run();

		self::assertTrue( self::stored()['baidu']['needs_reconfigure'] );
		self::assertTrue( self::stored()['baidu']['enabled'], 'enabled is not touched' );
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

		self::assertArrayNotHasKey( 'needs_reconfigure', self::stored()['baidu'] );
		self::assertSame( 'https://qianfan.baidubce.com/v2', ( new EndpointRepository() )->resolved_base_url( 'baidu' ) );
	}

	public function test_verify_failure_rolls_back_non_key_fields(): void {
		$v1 = self::v1_option( [ 'openai' => [ 'enabled' => true, 'api_key' => self::FAKE_OPENAI_KEY ] ] );
		self::seed( $v1 );

		// 模拟写入时被别的代码改掉了 enabled（过滤器或并发保存）。
		$GLOBALS['wpmind_test_update_option_filters'][ EndpointRepository::OPTION ] = static function ( $value ) {
			if ( is_array( $value ) && ! array_key_exists( 'name', $value['openai'] ) ) {
				$value['openai']['enabled'] = false;
			}
			return $value;
		};

		$entries = ( new Migrator() )->run();

		self::assertSame( 'rolled_back', $entries[0]['result'] );
		self::assertSame( $v1, self::stored(), 'v1 data restored' );
		self::assertArrayNotHasKey( Migrator::DB_VERSION_OPTION, $GLOBALS['wpmind_test_options'] );
		self::assertArrayNotHasKey( Migrator::LOCK_OPTION, $GLOBALS['wpmind_test_options'] );

		// 回滚后进入退避期，不会每个请求都重试。
		self::assertSame( [], ( new Migrator() )->pending() );
	}

	public function test_fresh_install_creates_non_autoloaded_empty_option(): void {
		$entries = ( new Migrator() )->run();

		self::assertSame( 'noop', $entries[0]['result'] );
		self::assertSame( [], self::stored() );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ EndpointRepository::OPTION ] );
		self::assertSame( 1300, $GLOBALS['wpmind_test_options'][ Migrator::DB_VERSION_OPTION ] );
		self::assertSame( [], ( new Migrator() )->pending() );
	}

	public function test_already_v2_but_old_db_version_only_fixes_autoload(): void {
		$v2 = [
			'openai' => [
				'enabled'         => true,
				'api_key'         => self::FAKE_OPENAI_KEY,
				'custom_base_url' => 'https://proxy.example.com/v1',
				'models'          => [],
				'default_model'   => '',
			],
		];
		self::seed( $v2 );

		$entries = ( new Migrator() )->run();

		self::assertSame( 'noop', $entries[0]['result'] );
		self::assertSame( $v2, self::stored() );
		self::assertFalse( $GLOBALS['wpmind_test_autoload'][ EndpointRepository::OPTION ] );
		self::assertArrayNotHasKey( M013_EndpointsV2::BACKUP_OPTION, $GLOBALS['wpmind_test_options'] );
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
}
