<?php
/**
 * Gateway safety tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Modules\ApiGateway\ApiGatewayModule;
use WPMind\Modules\ApiGateway\RateLimit\TransientRateStore;

require_once WPMIND_PLUGIN_DIR . 'modules/api-gateway/ApiGatewayModule.php';

final class GatewaySafetyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']          = [];
		$GLOBALS['wpmind_test_actions']          = [];
		$GLOBALS['wpmind_test_ext_cache']        = true;
		$GLOBALS['wpmind_test_cache_add_result'] = false;
	}

	public function test_disabled_gateway_does_not_register_rest_runtime(): void {
		$GLOBALS['wpmind_test_options']['wpmind_gateway_enabled'] = '0';

		$module = new ApiGatewayModule();
		$module->init();

		self::assertArrayNotHasKey( 'rest_api_init', $GLOBALS['wpmind_test_actions'] );
		self::assertArrayNotHasKey( 'wpmind_gateway_sse_complete', $GLOBALS['wpmind_test_actions'] );
	}

	public function test_transient_lock_failure_denies_request(): void {
		$store  = new TransientRateStore();
		$result = $store->consume( 'test-key', 60, 1, 10, 'request-1', 1000 );

		self::assertFalse( $result->allowed );
		self::assertSame( 0, $result->remaining );
		self::assertSame( 1060, $result->reset_epoch );
	}
}
