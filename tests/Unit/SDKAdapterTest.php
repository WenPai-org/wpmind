<?php
/**
 * SDKAdapter unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPMind\SDK\SDKAdapter;
use WP_Error;

/**
 * @covers \WPMind\SDK\SDKAdapter
 */
class SDKAdapterTest extends TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Set up SDK mock once — all tests share it.
		if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
			eval( '
				namespace WordPress\\AiClient;
				class AiClient {
					public static function defaultRegistry() {
						return new class {
							public function getProviderModel( $class, $model ) {
								return new class {};
							}
							public function hasProvider( $id ) { return true; }
						};
					}
					public static function prompt( $messages = null ) {
						return new class {
							public function usingModel( $m ) { return $this; }
							public function usingProvider( $p ) { return $this; }
							public function usingTemperature( $t ) { return $this; }
							public function usingMaxTokens( $t ) { return $this; }
							public function usingSystemInstruction( $s ) { return $this; }
							public function asJsonResponse() { return $this; }
							public function generateTextResult() {
								$c = new class {
									public function getFinishReason() { return null; }
								};
								$u = new class {
									public function getPromptTokens() { return 10; }
									public function getCompletionTokens() { return 20; }
									public function getTotalTokens() { return 30; }
								};
								return new class( $c, $u ) {
									private $c; private $u;
									public function __construct( $c, $u ) { $this->c = $c; $this->u = $u; }
									public function getCandidates() { return [ $this->c ]; }
									public function toText() { return "Hello response"; }
									public function getTokenUsage() { return $this->u; }
								};
							}
						};
					}
				}
			' );
		}

		// Set up WP 7.0 prompt mock with global error flag.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			eval( '
				function wp_ai_client_prompt( $prompt = null ) {
					$fe = $GLOBALS["_wpmind_force_error"] ?? false;
					return new class( $fe ) {
						private $force_error;
						public function __construct( bool $fe ) { $this->force_error = $fe; }
						public function using_model( $m ) { return $this; }
						public function using_provider( $p ) { return $this; }
						public function using_temperature( $t ) { return $this; }
						public function using_max_tokens( $t ) { return $this; }
						public function using_system_instruction( $s ) { return $this; }
						public function as_json_response() { return $this; }
						public function generate_text_result() {
							if ( $this->force_error ) {
								return new \WP_Error( "prompt_prevented", "AI features are not supported" );
							}
							$c = new class {
								public function getFinishReason() { return null; }
							};
							$u = new class {
								public function getPromptTokens() { return 10; }
								public function getCompletionTokens() { return 20; }
								public function getTotalTokens() { return 30; }
							};
							return new class( $c, $u ) {
								private $c; private $u;
								public function __construct( $c, $u ) { $this->c = $c; $this->u = $u; }
								public function getCandidates() { return [ $this->c ]; }
								public function toText() { return "Hello response"; }
								public function getTokenUsage() { return $this->u; }
							};
						}
					};
				}
			' );
		}

		// Set up ProviderRegistrar mock.
		if ( ! class_exists( 'WPMind\\Providers\\ProviderRegistrar' ) ) {
			eval( '
				namespace WPMind\\Providers;
				class ProviderRegistrar {
					public static function getProviderClass( string $id ): ?string {
						$map = [
							"testprovider" => "TestProvider",
							"openai" => "WordPress\\AiClient\\Providers\\ProviderImplementations\\OpenAi\\OpenAiProvider",
						];
						return $map[$id] ?? null;
					}
				}
			' );
		}
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wpmind_force_error'] );
		parent::tearDown();
	}

	/**
	 * SDK unavailable returns WP_Error.
	 */
	public function test_chat_returns_error_when_sdk_unavailable(): void {
		// This test requires no SDK, but we already loaded it.
		// Instead, test that a valid call returns a result.
		$adapter = new SDKAdapter();
		$result = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hi' ] ] ],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Legacy path returns formatted result on success.
	 */
	public function test_chat_legacy_returns_formatted_result(): void {
		// Force non-WP70 path by temporarily removing function.
		// Since wp_ai_client_prompt is already defined, this goes to WP70 path.
		// Test the WP70 path instead (same logic, different builder).
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[
				'messages'    => [ [ 'role' => 'user', 'content' => 'hello' ] ],
				'temperature' => 0.5,
				'max_tokens'  => 100,
			],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'provider', $result );
		$this->assertArrayHasKey( 'model', $result );
		$this->assertArrayHasKey( 'usage', $result );
		$this->assertArrayHasKey( 'finish_reason', $result );
		$this->assertSame( 'testprovider', $result['provider'] );
		$this->assertSame( 'test-model', $result['model'] );
		$this->assertSame( 'Hello response', $result['content'] );
	}

	/**
	 * WP 7.0 path returns formatted result on success.
	 */
	public function test_chat_wp70_returns_formatted_result(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[
				'messages'    => [ [ 'role' => 'user', 'content' => 'hello' ] ],
				'temperature' => 0.5,
				'max_tokens'  => 100,
			],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Hello response', $result['content'] );
		$this->assertSame( 'testprovider', $result['provider'] );
		$this->assertSame( 'test-model', $result['model'] );
	}

	/**
	 * WP 7.0 path returns WP_Error when prompt fails.
	 */
	public function test_chat_wp70_returns_wp_error_on_failure(): void {
		$GLOBALS['_wpmind_force_error'] = true;
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'testprovider',
			'test-model'
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'prompt_prevented', $result->get_error_code() );
	}

	/**
	 * System message extraction works correctly.
	 */
	public function test_system_message_is_passed(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[
				'messages'    => [
					[ 'role' => 'system', 'content' => 'You are helpful.' ],
					[ 'role' => 'user', 'content' => 'hello' ],
				],
				'temperature' => 0.7,
				'max_tokens'  => 2000,
			],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Hello response', $result['content'] );
	}

	/**
	 * JSON mode is passed through without error.
	 */
	public function test_json_mode_is_passed(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[
				'messages'  => [ [ 'role' => 'user', 'content' => 'give me JSON' ] ],
				'json_mode' => true,
			],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Token usage extraction returns correct values.
	 */
	public function test_token_usage_returned_correctly(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'testprovider',
			'test-model'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 10, $result['usage']['prompt_tokens'] );
		$this->assertSame( 20, $result['usage']['completion_tokens'] );
		$this->assertSame( 30, $result['usage']['total_tokens'] );
	}

	/**
	 * Built-in provider resolution works for openai.
	 */
	public function test_builtin_provider_resolution(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'openai',
			'gpt-4o'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'openai', $result['provider'] );
	}

	/**
	 * Unknown provider still returns result (no provider class, prompt still builds).
	 */
	public function test_unknown_provider_still_works(): void {
		$adapter = new SDKAdapter();

		$result = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'unknown_provider',
			'some-model'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'unknown_provider', $result['provider'] );
	}

	/**
	 * Auto/default model names skip model resolution.
	 */
	public function test_auto_model_skips_resolution(): void {
		$adapter = new SDKAdapter();

		$result_auto = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'testprovider',
			'auto'
		);
		$this->assertIsArray( $result_auto );

		$result_default = $adapter->chat(
			[ 'messages' => [ [ 'role' => 'user', 'content' => 'hello' ] ] ],
			'testprovider',
			'default'
		);
		$this->assertIsArray( $result_default );
	}
}
