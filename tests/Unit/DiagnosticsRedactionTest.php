<?php
/**
 * Diagnostics redaction unit tests (PR6).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Provider\Diagnostics;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderDefinitions;
use WPMind\Provider\Secrets;

/**
 * @covers \WPMind\Provider\Diagnostics
 */
final class DiagnosticsRedactionTest extends TestCase {

	private const FAKE_KEY = 'sk-fake-diag-aaaaaaaaaaaaaaaa';
	private const FAKE_FP  = 'deadbeefcafe';

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
		$this->seed_provider( 'openai', self::FAKE_KEY, true, 'https://proxy.example.com/v1' );
	}

	/**
	 * @param array<string,mixed> $extra Extra.
	 */
	private function seed_provider( string $id, string $api_key, bool $enabled, string $custom_url = '', array $extra = array() ): void {
		$raw = array();
		$entry = array_merge(
			array(
				'enabled'         => $enabled,
				'custom_base_url' => $custom_url,
				'models'          => array( 'gpt-4o' ),
				'default_model'   => 'gpt-4o',
				'key_meta'        => array(
					'fingerprint' => self::FAKE_FP,
					'set_at'      => 1700000000,
				),
			),
			$extra
		);
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

	public function test_export_strips_keys_fingerprints_and_secret_patterns(): void {
		Diagnostics::push_error(
			array(
				'provider' => 'openai',
				'class'    => 'auth_failed',
				'status'   => 401,
				'message'  => 'Authorization: Bearer abc.DEF-ghi_123 leaked sk-proj-FAKEfake1234567890 and ?key=AIzaFAKEfakeFAKE plus bce-v3/ALTAK-FAKEfake01/abcdef0123456789',
				'at'       => time(),
			)
		);

		$export = Diagnostics::export();
		$json   = (string) wp_json_encode( $export );

		$this->assertStringNotContainsString( self::FAKE_KEY, $json );
		$this->assertStringNotContainsString( self::FAKE_FP, $json );
		$this->assertStringNotContainsString( 'sk-proj-FAKEfake1234567890', $json );
		$this->assertStringNotContainsString( 'AIzaFAKEfakeFAKE', $json );
		$this->assertStringNotContainsString( 'ALTAK-FAKEfake01', $json );
		$this->assertStringNotContainsString( 'abc.DEF-ghi_123', $json );
		$this->assertStringNotContainsString( '"api_key"', $json );
		$this->assertStringNotContainsString( '"fingerprint"', $json );
		$this->assertStringNotContainsString( '"key_meta"', $json );

		$this->assertSame( 'proxy.example.com', $export['providers']['openai']['base_url_host'] ?? null );
		$this->assertStringNotContainsString( '/v1', (string) ( $export['providers']['openai']['base_url_host'] ?? '' ) );
		$this->assertTrue( (bool) ( $export['providers']['openai']['has_api_key'] ?? false ) );
		$this->assertStringContainsString( Secrets::MASK, $json );
	}

	public function test_hostname_only_drops_path_and_query(): void {
		$this->assertSame( 'api.openai.com', Diagnostics::hostname_only( 'https://api.openai.com/v1/chat?key=secret' ) );
		$this->assertSame( '', Diagnostics::hostname_only( '' ) );
	}

	public function test_redact_tree_removes_banned_keys(): void {
		$out = Diagnostics::redact_tree(
			array(
				'ok'          => true,
				'api_key'     => self::FAKE_KEY,
				'fingerprint' => self::FAKE_FP,
				'nested'      => array(
					'Authorization' => 'Bearer tok',
					'note'          => 'hello sk-fake-zzzzzzzzzzzzzzzz',
				),
			),
			array( self::FAKE_KEY )
		);
		$this->assertArrayNotHasKey( 'api_key', $out );
		$this->assertArrayNotHasKey( 'fingerprint', $out );
		$this->assertArrayNotHasKey( 'Authorization', $out['nested'] );
		$this->assertStringNotContainsString( 'sk-fake-zzzzzzzzzzzzzzzz', $out['nested']['note'] );
	}
}
