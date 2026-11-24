<?php
/**
 * Regression tests for ChatService empty-failover null guard and MetaGenerator availability guard.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace {
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
}

namespace WPMind\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use ReflectionClass;
	use WP_Error;
	use WP_Post;
	use WPMind\API\PublicAPI;
	use WPMind\API\Services\ChatService;
	use WPMind\Failover\FailoverManager;
	use WPMind\Modules\AutoMeta\MetaGenerator;

	/**
	 * @covers \WPMind\API\Services\ChatService
	 * @covers \WPMind\Modules\AutoMeta\MetaGenerator
	 */
	final class ChatServiceEmptyFailoverTest extends TestCase {

		private const FAKE_API_KEY = 'sk-fake-openai-empty-0001';

		protected function setUp(): void {
			parent::setUp();
			$GLOBALS['wpmind_test_options']          = [];
			$GLOBALS['wpmind_test_transients']       = [];
			$GLOBALS['wpmind_test_actions']          = [];
			$GLOBALS['wpmind_test_filters']          = [];
			$GLOBALS['wpmind_test_posts']            = [];
			$GLOBALS['wpmind_test_postmeta']         = [];
			$GLOBALS['wpmind_test_post_autoinc']     = 1;
			$GLOBALS['wpmind_test_scheduled_events'] = [];
			unset( $GLOBALS['wpmind_test_http_handler'] );

			require_once WPMIND_PLUGIN_DIR . 'modules/auto-meta/includes/MetaGenerator.php';
			$this->reset_failover_manager();
		}

		protected function tearDown(): void {
			unset( $GLOBALS['wpmind_test_http_handler'] );
			$GLOBALS['wpmind_test_filters']          = [];
			$GLOBALS['wpmind_test_scheduled_events'] = [];
			$this->reset_failover_manager();
			parent::tearDown();
		}

		private function reset_failover_manager(): void {
			$ref  = new ReflectionClass( FailoverManager::class );
			$prop = $ref->getProperty( 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}

		public function test_chat_returns_wp_error_with_empty_failover_chain_and_filter_hooked(): void {
			$http_calls                           = 0;
			$GLOBALS['wpmind_test_http_handler']  = static function () use ( &$http_calls ) {
				++$http_calls;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '{}',
					'headers'  => [],
				];
			};

			$chat                                                    = new ChatService();
			$GLOBALS['wpmind_test_filters']['wpmind_chat_response']  = [ $chat, 'filter_chat_response' ];

			$result = $chat->chat(
				'hello',
				[
					'context'   => 'auto_meta',
					'cache_ttl' => -1,
				]
			);

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'wpmind_no_available_provider', $result->get_error_code() );
			self::assertSame( 0, $http_calls, 'upstream must not be called when failover chain is empty' );
		}

		public function test_chat_returns_wp_error_when_all_configured_providers_tripped_circuit_breaker(): void {
			update_option(
				'wpmind_custom_endpoints',
				[
					'openai' => [
						'enabled' => true,
						'api_key' => self::FAKE_API_KEY,
					],
				]
			);
			update_option( 'wpmind_default_provider', 'openai' );
			$this->reset_failover_manager();

			$fm = FailoverManager::instance();
			$fm->get_circuit_breaker( 'openai' )->mark_needs_attention( 'auth_failed' );

			$chat                                                    = new ChatService();
			$GLOBALS['wpmind_test_filters']['wpmind_chat_response']  = [ $chat, 'filter_chat_response' ];

			$result = $chat->chat( 'hello', [ 'cache_ttl' => -1 ] );

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'wpmind_no_available_provider', $result->get_error_code() );
		}

		public function test_chat_preserves_no_supported_provider_when_filter_removes_all(): void {
			update_option(
				'wpmind_custom_endpoints',
				[
					'openai' => [
						'enabled' => true,
						'api_key' => self::FAKE_API_KEY,
					],
				]
			);
			update_option( 'wpmind_default_provider', 'openai' );
			$this->reset_failover_manager();

			$chat                                                    = new ChatService();
			$GLOBALS['wpmind_test_filters']['wpmind_chat_response']  = [ $chat, 'filter_chat_response' ];

			$result = $chat->chat(
				'describe image',
				[
					'failover_providers' => [ 'anthropic' ],
					'cache_ttl'          => -1,
				]
			);

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'wpmind_no_supported_provider', $result->get_error_code() );
		}

		public function test_meta_generator_process_single_noops_when_no_provider_available(): void {
			update_option( 'wpmind_auto_meta_post_types', [ 'post' ] );
			update_option( 'wpmind_auto_meta_excerpt', '1' );
			self::assertFalse( PublicAPI::instance()->is_available() );

			$post_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'No Provider Post',
					'post_content' => 'Content body for testing safe no-op.',
				]
			);

			$http_calls                          = 0;
			$GLOBALS['wpmind_test_http_handler'] = static function () use ( &$http_calls ) {
				++$http_calls;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '{}',
					'headers'  => [],
				];
			};

			$generator = new MetaGenerator();
			$generator->process_single( $post_id );

			self::assertSame( 0, $http_calls );
			self::assertSame( '', get_post_meta( $post_id, '_wpmind_auto_meta_lock', true ) );
			self::assertSame( '', get_post_meta( $post_id, '_wpmind_auto_meta_source', true ) );
			self::assertSame( '', get_post_meta( $post_id, '_wpmind_seo_description', true ) );
		}

		public function test_meta_generator_scheduling_skips_when_unavailable_and_schedules_when_available(): void {
			update_option( 'wpmind_auto_meta_post_types', [ 'post' ] );
			self::assertFalse( PublicAPI::instance()->is_available() );

			$post_id = wp_insert_post(
				[
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Scheduled Check Post',
					'post_content' => 'Updated body content.',
				]
			);
			$post    = get_post( $post_id );
			self::assertInstanceOf( WP_Post::class, $post );

			$generator = new MetaGenerator();
			$generator->on_publish( 'publish', 'draft', $post );
			$generator->on_update( $post_id, $post, $post );

			self::assertSame( [], $GLOBALS['wpmind_test_scheduled_events'] );

			// Once a provider is configured and enabled, scheduling resumes normally.
			update_option(
				'wpmind_custom_endpoints',
				[
					'openai' => [
						'enabled' => true,
						'api_key' => self::FAKE_API_KEY,
					],
				]
			);
			self::assertTrue( PublicAPI::instance()->is_available() );

			$generator->on_publish( 'publish', 'draft', $post );

			self::assertCount( 1, $GLOBALS['wpmind_test_scheduled_events'] );
			self::assertSame( 'wpmind_generate_auto_meta', $GLOBALS['wpmind_test_scheduled_events'][0]['hook'] );
			self::assertSame( [ $post_id ], $GLOBALS['wpmind_test_scheduled_events'][0]['args'] );
		}
	}
}
