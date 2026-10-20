<?php
/**
 * I2 repro: network-active sites with no WPMind data at all must be treated as fresh installs.
 *
 * Decision (lead, 2026-10-21): when network-active, a site with neither wpmind_installed_version
 * nor any wpmind_* option gets manifest defaults (cost-control, exact-cache on; others off) and the
 * version stamp. A site with any wpmind_* setting takes the legacy upgrade. Sites created while the
 * plugin is only active on a single subsite get no options (unchanged).
 *
 * Tests marked I2-RED fail on main 2b43647 by design. They are skipped as incomplete unless
 * WPMIND_QA_I2_RED=1, so composer check stays green until the fix lands.
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
 * Minimal $wpdb stand-in over the per-site option arrays, in case the fix detects
 * "any wpmind_* option" with a LIKE query instead of get_option().
 */
final class WPMind_QA_I2_Wpdb {
	public string $options = 'wp_options';
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( string $query, ...$args ): string {
		$args = is_array( $args[0] ?? null ) ? $args[0] : $args;
		$i    = 0;
		return (string) preg_replace_callback(
			'/%[sd]/',
			static function () use ( &$i, $args ): string {
				return "'" . (string) ( $args[ $i++ ] ?? '' ) . "'";
			},
			$query
		);
	}
	private function matches( string $query ): array {
		$names = array_keys( $GLOBALS['wpmind_test_options'] ?? [] );
		if ( ! preg_match_all( "/LIKE\s+'([^']*)'/i", $query, $m ) ) {
			return $names;
		}
		$out = [];
		foreach ( $m[1] as $like ) {
			$re = '/^' . strtr( preg_quote( stripslashes( str_replace( [ '\\_', '\\%' ], [ "\x01", "\x02" ], $like ) ), '/' ), [ '%' => '.*', '_' => '.', "\x01" => '_', "\x02" => '%' ] ) . '$/';
			foreach ( $names as $n ) {
				if ( preg_match( $re, (string) $n ) ) {
					$out[] = $n;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}
	public function get_var( string $query ) {
		$hits = $this->matches( $query );
		return stripos( $query, 'COUNT(' ) !== false ? (string) count( $hits ) : ( $hits[0] ?? null );
	}
	public function get_col( string $query ): array {
		return $this->matches( $query );
	}
}

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
		$this->had_wpdb                        = isset( $GLOBALS['wpdb'] );
		if ( ! $this->had_wpdb ) {
			$GLOBALS['wpdb'] = new WPMind_QA_I2_Wpdb();
		}
	}

	private bool $had_wpdb = false;

	protected function tearDown(): void {
		if ( ! $this->had_wpdb ) {
			unset( $GLOBALS['wpdb'] );
		}
		unset( $GLOBALS['wpmind_test_options'] );
		$GLOBALS['wpmind_test_options']        = [];
		$GLOBALS['wpmind_test_multisite']      = false;
		$GLOBALS['wpmind_test_network_active'] = false;
	}

	private function red(): void {
		if ( '1' !== getenv( 'WPMIND_QA_I2_RED' ) ) {
			self::markTestIncomplete( 'I2-RED: expected to fail until the fresh-site detection fix lands (set WPMIND_QA_I2_RED=1 to run).' );
		}
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

	/** (a) Empty subsite that existed before network activation → fresh install. I2-RED on main. */
	public function test_a_empty_preexisting_subsite_is_fresh(): void {
		$this->red();
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
	 * `_transient_timeout_wpmind_llms_txt_content_N` row, see PR #25 F4), then network-activated → fresh. I2-RED on main.
	 */
	public function test_c_cleared_after_uninstall_is_fresh(): void {
		$this->red();
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
}
