<?php
/**
 * KeyRotation unit tests (PR5).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\KeyRotation;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\Secrets;

/**
 * @covers \WPMind\Provider\KeyRotation
 */
final class KeyRotationTest extends TestCase {

	private const OLD_KEY = 'sk-fake-old-key-aaaaaaaa';
	private const NEW_KEY = 'sk-fake-new-key-bbbbbbbb';
	private const BAD_KEY = 'sk-fake-bad-key-cccccccc';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
		$GLOBALS['wpmind_test_actions']    = array();
		unset( $GLOBALS['wpmind_test_http_handler'] );
		$this->reset_fm();
		$this->seed_provider( 'openai', self::OLD_KEY, true );
	}

	private function reset_fm(): void {
		$ref  = new ReflectionClass( FailoverManager::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * @param array<string,mixed> $extra Extra endpoint fields.
	 */
	private function seed_provider( string $id, string $api_key, bool $enabled, array $extra = array() ): void {
		$raw = get_option( EndpointRepository::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$entry = array_merge(
			array(
				'enabled'         => $enabled,
				'custom_base_url' => '',
				'models'          => array(),
				'default_model'   => '',
			),
			$extra
		);
		$raw[ $id ] = EndpointRepository::mirror_entry( $id, $entry, $api_key );
		$providers  = array();
		foreach ( ProviderDefinitions::ids() as $pid ) {
			if ( $pid === $id ) {
				$providers[ $pid ] = $entry;
			} else {
				$providers[ $pid ] = array(
					'enabled'         => false,
					'custom_base_url' => '',
					'models'          => array(),
					'default_model'   => '',
				);
			}
		}
		$repo = new EndpointRepository();
		$repo->save_v2( $providers, EndpointRepository::legacy_hash( $raw ) );
		$repo->save( $raw );
	}

	private function http_ok(): void {
		$GLOBALS['wpmind_test_http_handler'] = static function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"data":[{"id":"gpt-4o"}]}',
				'headers'  => array(),
			);
		};
	}

	private function http_unauthorized(): void {
		$GLOBALS['wpmind_test_http_handler'] = static function () {
			return array(
				'response' => array( 'code' => 401 ),
				'body'     => '{"error":{"message":"invalid key sk-should-not-leak"}}',
				'headers'  => array(),
			);
		};
	}

	public function test_rotate_failure_does_not_persist_new_key(): void {
		$this->http_unauthorized();
		$result = ( new KeyRotation() )->rotate( 'openai', self::BAD_KEY );
		$this->assertFalse( $result['ok'] );

		$ep = ( new EndpointRepository() )->get( 'openai' );
		$this->assertSame( self::OLD_KEY, $ep->api_key );
		$this->assertStringNotContainsString( self::BAD_KEY, wp_json_encode( get_option( EndpointRepository::OPTION ) ) );
		$this->assertStringNotContainsString( self::BAD_KEY, wp_json_encode( get_option( EndpointRepository::V2_OPTION ) ) );
	}

	public function test_rotate_success_replaces_key_and_writes_meta(): void {
		$this->http_ok();
		$result = ( new KeyRotation() )->rotate( 'openai', self::NEW_KEY );
		$this->assertTrue( $result['ok'], $result['message'] ?? '' );

		$ep = ( new EndpointRepository() )->get( 'openai' );
		$this->assertSame( self::NEW_KEY, $ep->api_key );
		$this->assertSame( Secrets::fingerprint( self::NEW_KEY ), $ep->key_meta['fingerprint'] ?? '' );
		$this->assertNotEmpty( $ep->key_meta['set_at'] ?? null );

		$encoded = wp_json_encode( get_option( EndpointRepository::OPTION ) );
		$this->assertStringNotContainsString( self::OLD_KEY, $encoded );
		$this->assertStringContainsString( self::NEW_KEY, $encoded );
	}

	public function test_rotate_aftermath_resets_breaker_and_fires_action_with_fps_only(): void {
		$cb = new CircuitBreaker( 'openai' );
		$cb->mark_needs_attention( 'auth_failed' );
		set_transient(
			'wpmind_models_remote_openai',
			array(
				'ids'           => array( 'gpt-4o' ),
				'fetched_at'    => time(),
				'base_url_hash' => 'x',
				'key_fp'        => Secrets::fingerprint( self::OLD_KEY ),
			),
			3600
		);

		$this->http_ok();
		$old_fp = Secrets::fingerprint( self::OLD_KEY );
		$new_fp = Secrets::fingerprint( self::NEW_KEY );
		$result = ( new KeyRotation() )->rotate( 'openai', self::NEW_KEY );
		$this->assertTrue( $result['ok'] );

		$this->assertSame( CircuitBreaker::STATE_CLOSED, ( new CircuitBreaker( 'openai' ) )->get_state() );
		$this->assertFalse( get_transient( 'wpmind_models_remote_openai' ) );

		$fired = $GLOBALS['wpmind_test_actions'][ KeyRotation::ACTION ] ?? array();
		$this->assertNotEmpty( $fired );
		$args = $fired[0];
		$this->assertSame( 'openai', $args[0] );
		$this->assertSame( $old_fp, $args[1] );
		$this->assertSame( $new_fp, $args[2] );
		$blob = wp_json_encode( $fired );
		$this->assertStringNotContainsString( self::OLD_KEY, $blob );
		$this->assertStringNotContainsString( self::NEW_KEY, $blob );
	}

	public function test_form_save_hook_runs_aftermath_on_fingerprint_change(): void {
		KeyRotation::register_hooks();
		$cb = new CircuitBreaker( 'openai' );
		$cb->mark_needs_attention( 'auth_failed' );

		$old = get_option( EndpointRepository::OPTION );
		$new = $old;
		$new['openai']['api_key'] = self::NEW_KEY;

		// Simulate WP update_option hook (our stub does not fire hooks).
		KeyRotation::on_endpoints_updated( $old, $new );

		$this->assertSame( CircuitBreaker::STATE_CLOSED, ( new CircuitBreaker( 'openai' ) )->get_state() );
		$fired = $GLOBALS['wpmind_test_actions'][ KeyRotation::ACTION ] ?? array();
		$this->assertNotEmpty( $fired );
		$this->assertSame( Secrets::fingerprint( self::OLD_KEY ), $fired[0][1] );
		$this->assertSame( Secrets::fingerprint( self::NEW_KEY ), $fired[0][2] );
	}

	public function test_ensure_key_meta_lazy_backfill(): void {
		// Seed without key_meta.
		$this->seed_provider( 'openai', self::OLD_KEY, true );
		$ep = ( new EndpointRepository() )->get( 'openai' );
		$this->assertSame( array(), $ep->key_meta );

		$meta = KeyRotation::ensure_key_meta( 'openai' );
		$this->assertSame( Secrets::fingerprint( self::OLD_KEY ), $meta['fingerprint'] );
		$this->assertNotEmpty( $meta['set_at'] );

		$ep2 = ( new EndpointRepository() )->get( 'openai' );
		$this->assertSame( $meta['fingerprint'], $ep2->key_meta['fingerprint'] ?? '' );
		$this->assertSame( self::OLD_KEY, $ep2->api_key );
	}

	public function test_make_meta_fingerprint_matches_secrets(): void {
		$meta = KeyRotation::make_meta( self::NEW_KEY );
		$this->assertSame( Secrets::fingerprint( self::NEW_KEY ), $meta['fingerprint'] );
		$this->assertIsInt( $meta['set_at'] );
	}
}
