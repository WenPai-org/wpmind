<?php
/**
 * Provider registration compatibility contract tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RegisterTest extends TestCase {

	private string $source;

	protected function setUp(): void {
		$this->source = (string) file_get_contents( WPMIND_PLUGIN_DIR . 'includes/Providers/register.php' );
	}

	public function test_wordpress_connector_options_are_never_written_or_deleted(): void {
		self::assertStringNotContainsString( 'connectors_ai_', $this->source );
		self::assertStringNotContainsString( 'sync_api_key_to_connector', $this->source );
		self::assertStringNotContainsString( 'wp_connectors_init', $this->source );
		self::assertStringNotContainsString( 'register_wpmind_connectors', $this->source );
	}

	public function test_legacy_credentials_bridge_is_disabled_when_connectors_exist(): void {
		self::assertStringContainsString(
			"if ( ! class_exists( 'WP_Connector_Registry' ) )",
			$this->source
		);
		self::assertStringContainsString( 'pre_option_wp_ai_client_provider_credentials', $this->source );
	}

	public function test_provider_registry_registration_is_preserved(): void {
		self::assertStringContainsString( 'ProviderRegistrar::registerProviders', $this->source );
		self::assertStringContainsString( "add_action( 'init'", $this->source );
	}
}
