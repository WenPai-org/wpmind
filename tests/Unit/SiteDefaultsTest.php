<?php
/**
 * Multisite new-site defaults vs. legacy upgrade tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Core\SiteDefaults;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/SiteDefaults.php';

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite(): bool {
		return (bool) ( $GLOBALS['wpmind_test_multisite'] ?? false );
	}
}
if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	function is_plugin_active_for_network( string $plugin ): bool {
		return (bool) ( $GLOBALS['wpmind_test_network_active'] ?? false );
	}
}
if ( ! function_exists( 'switch_to_blog' ) ) {
	function switch_to_blog( int $blog_id ): bool {
		$GLOBALS['wpmind_test_blog_stack'][] = $GLOBALS['wpmind_test_blog'];
		$GLOBALS['wpmind_test_blog']         = $blog_id;
		$GLOBALS['wpmind_test_options']      = &$GLOBALS['wpmind_test_site_options'][ $blog_id ];
		$GLOBALS['wpmind_test_options']      = $GLOBALS['wpmind_test_options'] ?? [];
		return true;
	}
}
if ( ! function_exists( 'restore_current_blog' ) ) {
	function restore_current_blog(): bool {
		$prev                           = array_pop( $GLOBALS['wpmind_test_blog_stack'] );
		$GLOBALS['wpmind_test_blog']    = $prev;
		$GLOBALS['wpmind_test_options'] = &$GLOBALS['wpmind_test_site_options'][ $prev ];
		return true;
	}
}

final class SiteDefaultsTest extends TestCase {

	protected function setUp(): void {
		unset( $GLOBALS['wpmind_test_options'] );
		$GLOBALS['wpmind_test_site_options'] = [ 1 => [] ];
		$GLOBALS['wpmind_test_options']      = &$GLOBALS['wpmind_test_site_options'][1];
		$GLOBALS['wpmind_test_blog']         = 1;
		$GLOBALS['wpmind_test_blog_stack']   = [];
		$GLOBALS['wpmind_test_multisite']    = true;
		$GLOBALS['wpmind_test_network_active'] = true;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpmind_test_options'] );
		$GLOBALS['wpmind_test_options']      = [];
		$GLOBALS['wpmind_test_multisite']    = false;
		$GLOBALS['wpmind_test_network_active'] = false;
	}

	private static function enabled_legacy_modules(): array {
		return array_values(
			array_filter(
				SiteDefaults::LEGACY_MODULES,
				static fn( string $id ): bool => false !== get_option( "wpmind_module_{$id}_enabled", false )
			)
		);
	}

	public function test_new_site_while_network_active_is_fresh_install(): void {
		SiteDefaults::on_initialize_site( (object) [ 'blog_id' => 3 ] );

		self::assertSame( 1, $GLOBALS['wpmind_test_blog'], 'current blog restored' );
		self::assertArrayNotHasKey( SiteDefaults::VERSION_OPTION, $GLOBALS['wpmind_test_site_options'][1] );

		switch_to_blog( 3 );
		self::assertSame( WPMIND_VERSION, get_option( SiteDefaults::VERSION_OPTION ) );
		SiteDefaults::maybe_upgrade_module_defaults();
		self::assertSame( [], self::enabled_legacy_modules(), 'no module (incl. GEO) force-enabled on new site' );
		restore_current_blog();
	}

	public function test_legacy_site_without_version_still_upgrades(): void {
		SiteDefaults::maybe_upgrade_module_defaults();
		self::assertSame( SiteDefaults::LEGACY_MODULES, self::enabled_legacy_modules() );
		self::assertSame( WPMIND_VERSION, get_option( SiteDefaults::VERSION_OPTION ) );
	}

	public function test_legacy_upgrade_keeps_saved_choice(): void {
		update_option( 'wpmind_module_geo_enabled', '0' );
		SiteDefaults::maybe_upgrade_module_defaults();
		self::assertSame( '0', get_option( 'wpmind_module_geo_enabled' ) );
	}

	public function test_site_with_version_is_untouched(): void {
		update_option( SiteDefaults::VERSION_OPTION, '0.12.0' );
		SiteDefaults::maybe_upgrade_module_defaults();
		self::assertSame( [], self::enabled_legacy_modules() );
	}

	public function test_existing_subsite_not_touched_by_new_site_creation(): void {
		$GLOBALS['wpmind_test_site_options'][2] = [];
		SiteDefaults::on_initialize_site( (object) [ 'blog_id' => 3 ] );
		self::assertSame( [], $GLOBALS['wpmind_test_site_options'][2] );
	}

	public function test_not_network_active_does_nothing(): void {
		$GLOBALS['wpmind_test_network_active'] = false;
		SiteDefaults::on_initialize_site( (object) [ 'blog_id' => 3 ] );
		self::assertArrayNotHasKey( 3, $GLOBALS['wpmind_test_site_options'] );
	}

	public function test_single_site_does_nothing(): void {
		$GLOBALS['wpmind_test_multisite'] = false;
		SiteDefaults::on_initialize_site( (object) [ 'blog_id' => 3 ] );
		self::assertArrayNotHasKey( 3, $GLOBALS['wpmind_test_site_options'] );
	}
}
