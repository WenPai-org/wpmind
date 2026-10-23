<?php
/**
 * I2 repro: network-active sites with no WPMind data at all must be treated as fresh installs.
 *
 * Decision (lead, 2026-10-21): when network-active, a site with neither wpmind_installed_version
 * nor any wpmind_* option gets manifest defaults (cost-control, exact-cache on; others off) and the
 * version stamp. A site with any wpmind_* setting takes the legacy upgrade. Sites created while the
 * plugin is only active on a single subsite get no options (unchanged).
 *
 * Cases (a) and (c) failed on main 2b43647 and pass with PR #30 (a5ee5cb). The site probe goes
 * through the shared WPMind_Test_WPDB double from tests/bootstrap.php.
 *
 * @package WPMind
 * @group i2
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Core\SiteDefaults;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/SiteDefaults.php';
require_once __DIR__ . '/SiteDefaultsTest.php'; // Multisite stubs (is_multisite, switch_to_blog, …).

/**
 * @covers \WPMind\Core\SiteDefaults
 */
final class SiteDefaultsFreshDetectTest extends TestCase {

	private const DEFAULT_ON = [ 'cost-control', 'exact-cache' ];

	protected function setUp(): void {
		unset( $GLOBALS['wpmind_test_options'] );
		$GLOBALS['wpmind_test_site_options']   = [ 1 => [] ];
		$GLOBALS['wpmind_test_options']        = &$GLOBALS['wpmind_test_site_options'][1];
		$GLOBALS['wpmind_test_blog']           = 1;
		$GLOBALS['wpmind_test_blog_stack']     = [];
		$GLOBALS['wpmind_test_multisite']      = true;
		$GLOBALS['wpmind_test_network_active'] = true;
		$this->previous_wpdb                   = $GLOBALS['wpdb'] ?? null;
		$this->db                              = new WPMind_Test_WPDB();
		$GLOBALS['wpdb']                       = $this->db;
	}

	/** @var mixed */
	private $previous_wpdb;

	private WPMind_Test_WPDB $db;

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;
		unset( $GLOBALS['wpmind_test_options'] );
		$GLOBALS['wpmind_test_options']        = [];
		$GLOBALS['wpmind_test_multisite']      = false;
		$GLOBALS['wpmind_test_network_active'] = false;
	}

	/** Run the per-request hook on blog $id the way plugins_loaded does. */
	private static function load_on( int $id ): void {
		switch_to_blog( $id );
		SiteDefaults::maybe_upgrade_module_defaults();
		restore_current_blog();
	}

	private static function opt( int $id, string $name ) {
		return $GLOBALS['wpmind_test_site_options'][ $id ][ $name ] ?? null;
	}

	private static function assert_fresh( int $id ): void {
		self::assertSame( WPMIND_VERSION, self::opt( $id, SiteDefaults::VERSION_OPTION ), "blog $id stamped" );
		foreach ( SiteDefaults::LEGACY_MODULES as $m ) {
			$v = self::opt( $id, "wpmind_module_{$m}_enabled" );
			if ( in_array( $m, self::DEFAULT_ON, true ) ) {
				self::assertContains( $v, [ null, '1' ], "blog $id $m follows manifest (on)" );
			} else {
				self::assertContains( $v, [ null, '0' ], "blog $id $m follows manifest (off), not legacy-forced" );
			}
		}
	}

	/** (a) Empty subsite that existed before network activation → fresh install (was I2-RED on main). */
	public function test_a_empty_preexisting_subsite_is_fresh(): void {
		$GLOBALS['wpmind_test_site_options'][2] = [ 'blogname' => 'S2' ];
		self::load_on( 2 );
		self::assert_fresh( 2 );
	}

	/** (b) Subsite with any wpmind_* setting → legacy upgrade, saved choice kept. */
	public function test_b_subsite_with_settings_takes_legacy_upgrade(): void {
		$GLOBALS['wpmind_test_site_options'][3] = [ 'wpmind_module_geo_enabled' => '0' ];
		self::load_on( 3 );
		self::assertSame( '0', self::opt( 3, 'wpmind_module_geo_enabled' ) );
		foreach ( array_diff( SiteDefaults::LEGACY_MODULES, [ 'geo' ] ) as $m ) {
			self::assertSame( '1', self::opt( 3, "wpmind_module_{$m}_enabled" ), $m );
		}
		self::assertSame( WPMIND_VERSION, self::opt( 3, SiteDefaults::VERSION_OPTION ) );
	}

	/** (b') Non-module wpmind setting (e.g. saved endpoints) also counts as an old site. */
	public function test_b2_subsite_with_other_wpmind_option_takes_legacy_upgrade(): void {
		$GLOBALS['wpmind_test_site_options'][3] = [ 'wpmind_request_timeout' => 60 ];
		self::load_on( 3 );
		self::assertSame( '1', self::opt( 3, 'wpmind_module_geo_enabled' ) );
	}

	/**
	 * (c) Site used before, uninstalled (options cleared; uninstall leaves only the
	 * `_transient_timeout_wpmind_llms_txt_content_N` row, see PR #25 F4), then network-activated → fresh (was I2-RED on main).
	 */
	public function test_c_cleared_after_uninstall_is_fresh(): void {
		$GLOBALS['wpmind_test_site_options'][4] = [ '_transient_timeout_wpmind_llms_txt_content_4' => time() + 3600 ];
		self::load_on( 4 );
		self::assert_fresh( 4 );
	}

	/** (d) Main site with version stamp is untouched; loading subsites does not touch it. */
	public function test_d_main_site_untouched(): void {
		$GLOBALS['wpmind_test_site_options'][1] = [ SiteDefaults::VERSION_OPTION => '0.12.0', 'wpmind_module_geo_enabled' => '1' ];
		$GLOBALS['wpmind_test_options']         = &$GLOBALS['wpmind_test_site_options'][1];
		$before                                 = $GLOBALS['wpmind_test_site_options'][1];
		SiteDefaults::maybe_upgrade_module_defaults();
		$GLOBALS['wpmind_test_site_options'][2] = [];
		self::load_on( 2 );
		self::assertSame( $before, $GLOBALS['wpmind_test_site_options'][1] );
		self::assertSame( 1, $GLOBALS['wpmind_test_blog'] );
	}

	/** (e) Plugin active on a single subsite only: a new site gets no wpmind option. */
	public function test_e_new_site_when_only_site_active_gets_nothing(): void {
		$GLOBALS['wpmind_test_network_active'] = false;
		SiteDefaults::on_initialize_site( (object) [ 'blog_id' => 6 ] );
		$opts = $GLOBALS['wpmind_test_site_options'][6] ?? [];
		self::assertSame( [], array_filter( array_keys( $opts ), static fn( $k ) => str_starts_with( (string) $k, 'wpmind_' ) ) );
	}

	/** (a) again: the probe runs once per site; later requests on a stamped site do not query. */
	public function test_a_second_request_on_fresh_subsite_does_not_probe(): void {
		$GLOBALS['wpmind_test_site_options'][2] = [ 'blogname' => 'S2' ];
		self::load_on( 2 );
		self::load_on( 2 );
		self::load_on( 2 );
		self::assertCount( 1, $this->db->queries );
		self::assertSame( [ 'wpmind\\_%' ], $this->db->like_args );
	}

	/** The probe looks at the current blog's options only (other sites' data does not leak in). */
	public function test_probe_is_per_site(): void {
		$GLOBALS['wpmind_test_site_options'][1] = [ 'wpmind_request_timeout' => 60, SiteDefaults::VERSION_OPTION => '0.12.0' ];
		$GLOBALS['wpmind_test_site_options'][2] = [];
		$GLOBALS['wpmind_test_site_options'][3] = [ 'wpmind_routing_settings' => [] ];
		self::load_on( 2 );
		self::load_on( 3 );
		self::assert_fresh( 2 );
		self::assertSame( '1', self::opt( 3, 'wpmind_module_geo_enabled' ) );
	}

	/**
	 * Single site: a v0.11.5 install that has any WPMind setting takes the legacy upgrade,
	 * and modules the user switched off stay off.
	 *
	 * @dataProvider v0115_single_site_options
	 */
	public function test_single_site_v0115_with_any_setting_takes_legacy_upgrade( array $stored ): void {
		$GLOBALS['wpmind_test_multisite']      = false;
		$GLOBALS['wpmind_test_network_active'] = false;
		$GLOBALS['wpmind_test_site_options'][1] = $stored + [ 'wpmind_module_analytics_enabled' => '0' ];
		$GLOBALS['wpmind_test_options']         = &$GLOBALS['wpmind_test_site_options'][1];

		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertSame( '0', self::opt( 1, 'wpmind_module_analytics_enabled' ), 'user choice kept' );
		foreach ( array_diff( SiteDefaults::LEGACY_MODULES, [ 'analytics' ] ) as $m ) {
			self::assertSame( '1', self::opt( 1, "wpmind_module_{$m}_enabled" ), $m );
		}
		self::assertSame( WPMIND_VERSION, self::opt( 1, SiteDefaults::VERSION_OPTION ) );
	}

	/** Only one v0.11.5 option present, no module switch at all → still a legacy upgrade. */
	public function test_single_site_v0115_activation_only_option_takes_legacy_upgrade(): void {
		$GLOBALS['wpmind_test_multisite']      = false;
		$GLOBALS['wpmind_test_network_active'] = false;
		$GLOBALS['wpmind_test_site_options'][1] = [ 'wpmind_request_timeout' => 60 ];
		$GLOBALS['wpmind_test_options']         = &$GLOBALS['wpmind_test_site_options'][1];

		SiteDefaults::maybe_upgrade_module_defaults();

		foreach ( SiteDefaults::LEGACY_MODULES as $m ) {
			self::assertSame( '1', self::opt( 1, "wpmind_module_{$m}_enabled" ), $m );
		}
	}

	/** @return array<string, array{0: array<string, mixed>}> Options v0.11.5 writes (activation, settings pages, stats). */
	public static function v0115_single_site_options(): array {
		return [
			'request timeout (activation)' => [ [ 'wpmind_request_timeout' => 60 ] ],
			'flush flag'                   => [ [ 'wpmind_flush_rewrite_rules' => '1' ] ],
			'endpoints'                    => [ [ 'wpmind_custom_endpoints' => [ 'openai' => [ 'api_key' => 'sk-qa-FAKE-0001' ] ] ] ],
			'routing'                      => [ [ 'wpmind_routing_settings' => [ 'strategy' => 'cost' ] ] ],
			'brand'                        => [ [ 'wpmind_brand_contact_email' => 'qa@example.invalid' ] ],
			'stats only'                   => [ [ 'wpmind_crawler_stats' => [] ] ],
		];
	}

	/** No $wpdb available → treat as old site (upgrade), never as fresh. */
	public function test_without_wpdb_subsite_takes_legacy_upgrade(): void {
		$GLOBALS['wpdb']                        = null;
		$GLOBALS['wpmind_test_site_options'][2] = [];
		self::load_on( 2 );
		self::assertSame( '1', self::opt( 2, 'wpmind_module_geo_enabled' ) );
	}
}
