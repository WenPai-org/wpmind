<?php
/**
 * API Gateway Module
 *
 * OpenAI-compatible AI API gateway module for WPMind.
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/SchemaManager.php';
require_once __DIR__ . '/includes/Auth/ApiKeyHasher.php';
require_once __DIR__ . '/includes/Auth/ApiKeyRepository.php';
require_once __DIR__ . '/includes/Auth/ApiKeyAuthResult.php';
require_once __DIR__ . '/includes/Auth/ApiKeyManager.php';
require_once __DIR__ . '/includes/GatewayRequestSchema.php';
require_once __DIR__ . '/includes/Pipeline/GatewayStageInterface.php';
require_once __DIR__ . '/includes/Pipeline/GatewayRequestContext.php';
require_once __DIR__ . '/includes/Pipeline/GatewayPipeline.php';

/**
 * Class ApiGatewayModule
 *
 * Main entry point for the API Gateway module.
 */
class ApiGatewayModule implements ModuleInterface {

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'api-gateway';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'API Gateway', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'OpenAI 兼容的 AI API 网关 — 将 WordPress 变为自托管 AI 代理', 'wpmind' );
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Check dependencies.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		return version_compare( PHP_VERSION, '8.1', '>=' );
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'api-gateway';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		// Ensure database schema is up to date.
		SchemaManager::maybe_upgrade();

		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Register settings tab.
		add_filter( 'wpmind_settings_tabs', array( $this, 'register_settings_tab' ) );

		/**
		 * Fires when API Gateway module is initialized.
		 *
		 * @param ApiGatewayModule $this Module instance.
		 */
		do_action( 'wpmind_api_gateway_init', $this );
	}

	/**
	 * Register REST API routes.
	 *
	 * Placeholder — routes will be added in Phase 8.
	 */
	public function register_rest_routes(): void {
		// Routes will be registered in Phase 8.
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['api-gateway'] = array(
			'title'    => __( 'API Gateway', 'wpmind' ),
			'icon'     => 'ri-server-line',
			'template' => WPMIND_PATH . 'modules/api-gateway/templates/settings.php',
			'priority' => 35,
		);
		return $tabs;
	}
}
