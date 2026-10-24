<?php
/**
 * ConfigTransfer unit tests (PR6).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\ConfigTransfer;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Migrations\M013_FailoverOrder;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\Secrets;

/**
 * @covers \WPMind\Provider\ConfigTransfer
 */
final class ConfigTransferTest extends TestCase {

	private const SITE_A_KEY = 'sk-fake-site-a-aaaaaaaa';
	private const SITE_B_KEY = 'sk-fake-site-b-bbbbbbbb';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
	}

	/**
	 * @param array<string,mixed> $extra Extra endpoint fields.
	 */
	private function seed( string $id, string $api_key, bool $enabled, array $extra = array() ): void {
		$entry = array_merge(
			array(
				'enabled'         => $enabled,
				'custom_base_url' => '',
				'models'          => array(),
				'default_model'   => '',
			),
			$extra
		);
		$raw       = array();
		$providers = array();
		foreach ( ProviderDefinitions::ids() as $pid ) {
			if ( $pid === $id ) {
				$providers[ $pid ] = $entry;
				$raw[ $pid ]       = EndpointRepository::mirror_entry( $pid, $entry, $api_key );
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

	public function test_export_omits_api_key_and_key_meta(): void {
		$this->seed(
			'openai',
			self::SITE_A_KEY,
			true,
			array(
				'custom_base_url' => 'https://proxy.example.com/v1',
				'models'          => array( 'gpt-4o' ),
				'default_model'   => 'gpt-4o',
				'key_meta'        => array( 'fingerprint' => Secrets::fingerprint( self::SITE_A_KEY ), 'set_at' => time() ),
			)
		);
		update_option( M013_FailoverOrder::OPTION, array( 'openai', 'deepseek' ), false );

		$export = ConfigTransfer::export();
		$json   = (string) wp_json_encode( $export );
		$this->assertStringNotContainsString( self::SITE_A_KEY, $json );
		$this->assertStringNotContainsString( 'fingerprint', $json );
		$this->assertStringNotContainsString( 'api_key', $json );
		$this->assertTrue( $export['providers']['openai']['enabled'] );
		$this->assertSame( 'https://proxy.example.com/v1', $export['providers']['openai']['custom_base_url'] );
		$this->assertSame( array( 'openai', 'deepseek' ), $export['failover_order'] );
	}

	public function test_import_preserves_local_key_and_applies_urls(): void {
		// Site B has its own key.
		$this->seed(
			'openai',
			self::SITE_B_KEY,
			false,
			array(
				'custom_base_url' => '',
				'models'          => array(),
				'default_model'   => '',
				'key_meta'        => array( 'fingerprint' => Secrets::fingerprint( self::SITE_B_KEY ), 'set_at' => 111 ),
			)
		);

		$payload = array(
			'schema'         => ConfigTransfer::SCHEMA,
			'failover_order' => array( 'deepseek', 'openai' ),
			'providers'      => array(
				'openai' => array(
					'enabled'         => true,
					'custom_base_url' => 'https://gateway.example.org/v1',
					'models'          => array( 'gpt-4o-mini' ),
					'default_model'   => 'gpt-4o-mini',
					// Malicious: should be ignored.
					'api_key'         => self::SITE_A_KEY,
					'key_meta'        => array( 'fingerprint' => 'should-not-apply' ),
				),
			),
		);

		$dry = ConfigTransfer::import( $payload, false );
		$this->assertTrue( $dry['ok'] );
		$this->assertNotEmpty( $dry['diff']['providers'] );

		$applied = ConfigTransfer::import( $payload, true );
		$this->assertTrue( $applied['ok'], $applied['message'] );

		$ep = ( new EndpointRepository() )->get( 'openai' );
		$this->assertSame( self::SITE_B_KEY, $ep->api_key );
		$this->assertSame( Secrets::fingerprint( self::SITE_B_KEY ), $ep->key_meta['fingerprint'] ?? '' );
		$this->assertTrue( $ep->enabled );
		$this->assertSame( 'https://gateway.example.org/v1', $ep->custom_base_url );
		$this->assertSame( array( 'gpt-4o-mini' ), $ep->models );
		$this->assertSame( array( 'deepseek', 'openai' ), get_option( M013_FailoverOrder::OPTION ) );
	}

	public function test_import_rejects_non_https_custom_base_url(): void {
		$this->seed( 'openai', self::SITE_B_KEY, true );
		$payload = array(
			'schema'         => ConfigTransfer::SCHEMA,
			'failover_order' => array(),
			'providers'      => array(
				'openai' => array(
					'enabled'         => true,
					'custom_base_url' => 'http://insecure.example/v1',
					'models'          => array(),
					'default_model'   => '',
				),
			),
		);
		$result = ConfigTransfer::import( $payload, true );
		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['errors'] );
	}
}
