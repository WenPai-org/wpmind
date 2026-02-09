<?php
/**
 * Media Intelligence Module
 *
 * AI-powered media analysis: auto alt text, title, caption, NSFW detection.
 *
 * @package WPMind\Modules\MediaIntelligence
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\MediaIntelligence;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/AltTextGenerator.php';
require_once __DIR__ . '/includes/NsfwDetector.php';
require_once __DIR__ . '/includes/MediaAjaxController.php';

/**
 * Class MediaIntelligenceModule
 *
 * Main entry point for the Media Intelligence module.
 */
final class MediaIntelligenceModule implements ModuleInterface {

	/**
	 * Component instances.
	 *
	 * @var array<string, object>
	 */
	private array $components = [];

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'media-intelligence';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( '媒体智能', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'AI 媒体智能 - 自动生成 alt text、标题、描述，NSFW 检测', 'wpmind' );
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
		return true;
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'media-intelligence';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		$this->init_components();

		// Register settings tab.
		add_filter( 'wpmind_settings_tabs', [ $this, 'register_settings_tab' ] );

		// Register AJAX handlers.
		$ajax = new MediaAjaxController();
		$ajax->register_hooks();

		// Admin assets (only on WPMind settings page).
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		/**
		 * Fires when Media Intelligence module is initialized.
		 *
		 * @param MediaIntelligenceModule $this Module instance.
		 */
		do_action( 'wpmind_media_intelligence_init', $this );
	}

	/**
	 * Initialize module components based on settings.
	 */
	private function init_components(): void {
		// Alt Text Generator (core feature, enabled by default).
		if ( get_option( 'wpmind_media_auto_alt', '1' ) === '1' ) {
			$this->components['alt_text'] = new AltTextGenerator();
		}

		// NSFW Detector (optional, disabled by default).
		if ( get_option( 'wpmind_media_nsfw_enabled', '0' ) === '1' ) {
			$this->components['nsfw'] = new NsfwDetector();
		}
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['media-intelligence'] = [
			'title'    => __( '媒体智能', 'wpmind' ),
			'icon'     => 'ri-image-ai-line',
			'template' => __DIR__ . '/templates/settings.php',
			'priority' => 25,
		];
		return $tabs;
	}

	/**
	 * Enqueue admin assets for the Media Intelligence tab.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_wpmind' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'wpmind-media-intelligence',
			WPMIND_PLUGIN_URL . 'assets/css/pages/media-intelligence.css',
			[ 'wpmind-admin' ],
			WPMIND_VERSION
		);
		wp_enqueue_script(
			'wpmind-admin-media-intelligence',
			WPMIND_PLUGIN_URL . 'assets/js/admin-media-intelligence.js',
			[ 'jquery', 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);
	}

	/**
	 * Get a component instance.
	 *
	 * @param string $name Component name.
	 * @return object|null Component instance or null.
	 */
	public function get_component( string $name ): ?object {
		return $this->components[ $name ] ?? null;
	}
}
