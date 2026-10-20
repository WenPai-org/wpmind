<?php
/**
 * B22: deferred rewrite flush, db_version upgrade and deactivation tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPMind\Core\ModuleLoader;
use WPMind\Core\RewriteFlush;

require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleInterface.php';
require_once WPMIND_PLUGIN_DIR . 'includes/Core/ModuleLoader.php';
require_once WPMIND_PLUGIN_DIR . 'includes/Core/RewriteFlush.php';

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * Stub: a real flush runs WP_Rewrite::rewrite_rules(), which fires
	 * `generate_rewrite_rules`. Count that, record soft/hard and whether the
	 * flag was still stored at flush time.
	 */
	function flush_rewrite_rules( bool $hard = true ): void {
		$GLOBALS['wpmind_test_generate_rewrite_rules'] = ( $GLOBALS['wpmind_test_generate_rewrite_rules'] ?? 0 ) + 1;
		$GLOBALS['wpmind_test_flushes'][]              = [
			'hard'         => $hard,
			'flag_present' => array_key_exists( RewriteFlush::FLAG_OPTION, $GLOBALS['wpmind_test_options'] ),
		];
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $tag, ...$args ): void {
	}
}

final class RewriteFlushTest extends TestCase {

	private const DB_VERSION = 2;

	protected function setUp(): void {
		$GLOBALS['wpmind_test_options']                = [];
		$GLOBALS['wpmind_test_actions']                = [];
		$GLOBALS['wpmind_test_flushes']                = [];
		$GLOBALS['wpmind_test_generate_rewrite_rules'] = 0;
	}

	/**
	 * One simulated request: plugins_loaded/20 upgrade check, then wp_loaded consumer.
	 */
	private function simulate_request(): void {
		RewriteFlush::maybe_upgrade_db( self::DB_VERSION );
		RewriteFlush::maybe_flush();
	}

	private function generate_count(): int {
		return (int) $GLOBALS['wpmind_test_generate_rewrite_rules'];
	}

	public function test_steady_state_requests_never_flush(): void {
		$GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] = self::DB_VERSION;

		for ( $i = 0; $i < 20; $i++ ) {
			$this->simulate_request();
		}

		self::assertSame( 0, $this->generate_count() );
	}

	public function test_flag_lifecycle_deletes_flag_before_soft_flush(): void {
		$GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] = self::DB_VERSION;

		RewriteFlush::request_flush();
		self::assertSame( '1', $GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] );

		RewriteFlush::maybe_flush();
		self::assertArrayNotHasKey( RewriteFlush::FLAG_OPTION, $GLOBALS['wpmind_test_options'] );
		self::assertSame( 1, $this->generate_count() );
		self::assertFalse( $GLOBALS['wpmind_test_flushes'][0]['hard'], 'flush must be soft' );
		self::assertFalse( $GLOBALS['wpmind_test_flushes'][0]['flag_present'], 'flag must be deleted before flushing' );

		// Consumed: the following requests do nothing.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->simulate_request();
		}
		self::assertSame( 1, $this->generate_count() );
	}

	public function test_non_flag_values_do_not_flush(): void {
		$GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] = '0';
		RewriteFlush::maybe_flush();
		self::assertSame( 0, $this->generate_count() );
	}

	public function test_db_version_upgrade_flushes_exactly_once(): void {
		// Missing option = pre-db_version install (0.11.5 / 0.12.0).
		for ( $i = 0; $i < 20; $i++ ) {
			$this->simulate_request();
		}

		self::assertSame( 1, $this->generate_count() );
		self::assertSame( self::DB_VERSION, $GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] );
		self::assertArrayNotHasKey( RewriteFlush::FLAG_OPTION, $GLOBALS['wpmind_test_options'] );
	}

	public function test_db_version_1_upgrades_and_newer_is_left_alone(): void {
		$GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] = 1;
		self::assertTrue( RewriteFlush::maybe_upgrade_db( self::DB_VERSION ) );
		self::assertSame( '1', $GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] );

		$this->setUp();
		$GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] = 3;
		self::assertFalse( RewriteFlush::maybe_upgrade_db( self::DB_VERSION ) );
		self::assertSame( 3, $GLOBALS['wpmind_test_options'][ RewriteFlush::DB_VERSION_OPTION ] );
		self::assertArrayNotHasKey( RewriteFlush::FLAG_OPTION, $GLOBALS['wpmind_test_options'] );
	}

	public function test_deactivation_clears_stored_rules_without_flushing(): void {
		$GLOBALS['wpmind_test_options']['rewrite_rules'] = [ '^llms\.txt$' => 'index.php?wpmind_llms_txt=1' ];

		RewriteFlush::on_deactivate();

		self::assertArrayNotHasKey( 'rewrite_rules', $GLOBALS['wpmind_test_options'] );
		self::assertSame( 0, $this->generate_count() );
	}

	public function test_route_option_listeners_only_set_flag(): void {
		RewriteFlush::register_hooks();

		foreach ( RewriteFlush::ROUTE_OPTIONS as $option ) {
			foreach ( [ 'add_option_', 'update_option_', 'delete_option_' ] as $prefix ) {
				self::assertArrayHasKey( $prefix . $option, $GLOBALS['wpmind_test_actions'] );
			}
		}
		self::assertArrayHasKey( 'wp_loaded', $GLOBALS['wpmind_test_actions'] );
		self::assertArrayNotHasKey( 'admin_init', $GLOBALS['wpmind_test_actions'], 'single consumer on wp_loaded only' );

		$callback = $GLOBALS['wpmind_test_actions']['update_option_wpmind_module_geo_enabled'][0];
		$callback( '0', '1', 'wpmind_module_geo_enabled' );

		self::assertSame( '1', $GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] );
		self::assertSame( 0, $this->generate_count(), 'listeners must never flush in the changing request' );
	}

	public function test_module_toggle_defers_flush(): void {
		$loader   = ModuleLoader::instance();
		$property = new ReflectionProperty( ModuleLoader::class, 'modules' );
		$property->setAccessible( true );
		$original = $property->getValue( $loader );
		$property->setValue(
			$loader,
			[
				'geo' => [
					'enabled'     => false,
					'can_disable' => true,
				],
			]
		);

		try {
			self::assertTrue( $loader->enable_module( 'geo' ) );
			self::assertSame( 0, $this->generate_count() );
			self::assertSame( '1', $GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] );

			RewriteFlush::maybe_flush();
			self::assertSame( 1, $this->generate_count() );

			self::assertTrue( $loader->disable_module( 'geo' ) );
			self::assertSame( 1, $this->generate_count() );
			self::assertSame( '1', $GLOBALS['wpmind_test_options'][ RewriteFlush::FLAG_OPTION ] );
		} finally {
			$property->setValue( $loader, $original );
		}
	}
}
