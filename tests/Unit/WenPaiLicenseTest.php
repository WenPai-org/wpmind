<?php

declare( strict_types=1 );

namespace WPMind\Tests\Unit;

use Yoast\WPTestUtils\BrainMonkey\TestCase;
use Brain\Monkey\Functions;

/**
 * @covers \WenPai_License
 */
class WenPaiLicenseTest extends TestCase {

	private \WenPai_License $license;

	protected function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-wenpai-license.php';

		$this->license = new \WenPai_License( 'wpmind', 'https://license.test' );
	}

	public function test_get_key_returns_empty_when_no_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'wenpai_license_key_wpmind', '' )
			->andReturn( '' );

		$this->assertSame( '', $this->license->get_key() );
	}

	public function test_get_key_returns_stored_value(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'wenpai_license_key_wpmind', '' )
			->andReturn( 'wenpai_wpmind_pro_abc123' );

		$this->assertSame( 'wenpai_wpmind_pro_abc123', $this->license->get_key() );
	}

	public function test_set_key_updates_option_and_clears_cache(): void {
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 'test_key' )
			->andReturn( 'test_key' );

		Functions\expect( 'update_option' )
			->once()
			->with( 'wenpai_license_key_wpmind', 'test_key' );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'wenpai_license_wpmind' );

		$this->license->set_key( 'test_key' );
	}

	public function test_verify_returns_free_when_no_key(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( '' );

		$result = $this->license->verify();

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'free', $result['plan'] );
	}

	public function test_verify_uses_cached_result(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( 'wenpai_wpmind_pro_abc123' );

		$cached = [
			'valid'    => true,
			'plan'     => 'pro',
			'features' => [ 'analytics_days' => 90 ],
			'cache_ttl' => 86400,
		];

		Functions\expect( 'get_transient' )
			->once()
			->with( 'wenpai_license_wpmind' )
			->andReturn( $cached );

		$result = $this->license->verify();

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'pro', $result['plan'] );
	}

	public function test_plan_returns_free_when_invalid(): void {
		Functions\expect( 'get_option' )
			->andReturn( '' );

		$this->assertSame( 'free', $this->license->plan() );
	}
}
