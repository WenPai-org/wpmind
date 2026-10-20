<?php
/**
 * I2: sites without installed_version and without any wpmind_ option are fresh installs.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Core\SiteDefaults;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/SiteDefaults.php';

final class SiteDefaultsSettingsProbeTest extends TestCase {

	/** @var mixed */
	private $previous_wpdb;

	private WPMind_Test_WPDB $db;

	protected function setUp(): void {
		$this->previous_wpdb            = $GLOBALS['wpdb'] ?? null;
		$this->db                       = new WPMind_Test_WPDB();
		$GLOBALS['wpdb']                = $this->db;
		$GLOBALS['wpmind_test_options'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb']                = $this->previous_wpdb;
		$GLOBALS['wpmind_test_options'] = [];
	}

	private static function stored_module_options(): array {
		return array_values(
			array_filter(
				array_keys( $GLOBALS['wpmind_test_options'] ),
				static fn( string $name ): bool => str_starts_with( $name, 'wpmind_module_' )
			)
		);
	}

	public function test_empty_subsite_is_fresh_install(): void {
		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertSame( [], self::stored_module_options(), 'manifest defaults, no module switch written' );
		self::assertSame( WPMIND_VERSION, get_option( SiteDefaults::VERSION_OPTION ) );
		self::assertCount( 1, $this->db->queries );
		self::assertSame( [ 'wpmind\\_%' ], $this->db->like_args, 'LIKE must escape the underscore' );
	}

	public function test_only_leftover_transients_is_fresh_install(): void {
		$GLOBALS['wpmind_test_options'] = [
			'_transient_wpmind_llms_txt_cache'          => 'x',
			'_transient_timeout_wpmind_llms_txt_cache'  => 123,
			'_transient_timeout_wpmind_cb_deepseek'     => 456,
			'_site_transient_wpmind_update'             => 'x',
			'wpmindx_other_plugin'                      => '1',
		];

		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertSame( [], self::stored_module_options() );
		self::assertSame( WPMIND_VERSION, get_option( SiteDefaults::VERSION_OPTION ) );
	}

	public function test_site_with_wpmind_settings_takes_legacy_upgrade(): void {
		$GLOBALS['wpmind_test_options'] = [ 'wpmind_request_timeout' => 60 ];

		SiteDefaults::maybe_upgrade_module_defaults();

		foreach ( SiteDefaults::LEGACY_MODULES as $module_id ) {
			self::assertSame( '1', get_option( "wpmind_module_{$module_id}_enabled" ), $module_id );
		}
		self::assertSame( WPMIND_VERSION, get_option( SiteDefaults::VERSION_OPTION ) );
	}

	public function test_legacy_site_keeps_saved_choice(): void {
		$GLOBALS['wpmind_test_options'] = [ 'wpmind_module_geo_enabled' => '0' ];

		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertSame( '0', get_option( 'wpmind_module_geo_enabled' ) );
		self::assertSame( '1', get_option( 'wpmind_module_analytics_enabled' ) );
	}

	public function test_installed_version_skips_options_query(): void {
		$GLOBALS['wpmind_test_options'] = [ SiteDefaults::VERSION_OPTION => '0.12.0' ];

		for ( $i = 0; $i < 5; $i++ ) {
			SiteDefaults::maybe_upgrade_module_defaults();
		}

		self::assertSame( [], $this->db->queries, 'no options-table query once the version is stored' );
		self::assertSame( [], self::stored_module_options() );
	}

	public function test_second_request_after_fresh_stamp_does_not_query(): void {
		SiteDefaults::maybe_upgrade_module_defaults();
		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertCount( 1, $this->db->queries );
	}

	public function test_without_wpdb_falls_back_to_legacy_upgrade(): void {
		$GLOBALS['wpdb'] = null;

		SiteDefaults::maybe_upgrade_module_defaults();

		self::assertSame( '1', get_option( 'wpmind_module_geo_enabled' ) );
	}
}
