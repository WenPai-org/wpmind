<?php
/**
 * ProviderClient records result once (no double count).
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\ProviderClient;

/**
 * @covers \WPMind\Provider\ProviderClient
 */
final class ProviderClientRecordOnceTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']    = array();
		$GLOBALS['wpmind_test_transients'] = array();
		$GLOBALS['wpmind_test_actions']    = array();
		$ref = new ReflectionClass( FailoverManager::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	public function test_success_records_once_and_fires_usage(): void {
		$fm = FailoverManager::instance();
		$ref = new ReflectionClass( $fm );
		$providers = $ref->getProperty( 'providers' );
		$providers->setAccessible( true );
		$breakers = $ref->getProperty( 'circuitBreakers' );
		$breakers->setAccessible( true );
		$providers->setValue( $fm, array( 'openai' => array( 'enabled' => true, 'api_key' => 'sk-fake-openai-0001', 'name' => 'OpenAI' ) ) );
		$breakers->setValue( $fm, array( 'openai' => new CircuitBreaker( 'openai' ) ) );

		$calls = 0;
		$GLOBALS['wpmind_test_http_handler'] = static function ( string $url, array $args ) use ( &$calls ) {
			++$calls;
			self::assertTrue( ! empty( $args['_wpmind_skip_tracking'] ) );
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'model'   => 'gpt-4o',
						'usage'   => array(
							'prompt_tokens'     => 3,
							'completion_tokens' => 1,
							'total_tokens'      => 4,
						),
						'choices' => array(
							array(
								'message' => array( 'content' => 'hi' ),
							),
						),
					)
				),
				'headers'  => array(),
			);
		};

		$client = new ProviderClient();
		$out    = $client->request(
			'openai',
			'POST',
			'chat/completions',
			array(
				'api_key'  => 'sk-fake-openai-0001',
				'base_url' => 'https://api.openai.com/v1',
				'body'     => array( 'model' => 'gpt-4o' ),
			)
		);
		$this->assertTrue( $out['ok'] );
		$this->assertSame( 1, $calls );
		$usage = $GLOBALS['wpmind_test_actions']['wpmind_usage_record'] ?? array();
		$this->assertCount( 1, $usage );
		$details = $fm->get_circuit_breaker( 'openai' )->get_status_details();
		$this->assertSame( 1, $details['successes'] );
	}
}
