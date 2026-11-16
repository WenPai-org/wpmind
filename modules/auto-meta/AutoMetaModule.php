<?php
/**
 * Auto-Meta Module
 *
 * AI-powered metadata generation: excerpt, tags, category, FAQ Schema, SEO description.
 *
 * @package WPMind\Modules\AutoMeta
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\AutoMeta;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/MetaGenerator.php';
require_once __DIR__ . '/includes/AutoMetaAjaxController.php';

/**
 * Class AutoMetaModule
 *
 * Main entry point for the Auto-Meta module.
 */
final class AutoMetaModule implements ModuleInterface {

	/**
	 * MetaGenerator instance.
	 *
	 * @var MetaGenerator|null
	 */
	private ?MetaGenerator $generator = null;

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'auto-meta';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Auto-Meta', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( '发布时自动生成摘要、标签、分类、FAQ Schema、SEO 描述', 'wpmind' );
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
		return function_exists( 'wpmind_structured' );
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'auto-meta';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		if ( get_option( 'wpmind_auto_meta_enabled', '1' ) !== '1' ) {
			return;
		}

		$this->generator = new MetaGenerator();
		$this->generator->register_hooks();

		// Register AJAX handlers.
		$ajax = new AutoMetaAjaxController();
		$ajax->register_hooks();

		// Admin assets (only on WPMind settings page).
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		/**
		 * Fires when Auto-Meta module is initialized.
		 *
		 * @param AutoMetaModule $this Module instance.
		 */
		do_action( 'wpmind_auto_meta_init', $this );
	}

	/**
	 * Enqueue admin assets for the Auto-Meta tab.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'settings_page_wpmind', 'toplevel_page_wpmind' ], true ) ) {
			return;
		}
		if ( 'module' !== \WPMind\Admin\AdminPage::current_view() || 'auto-meta' !== \WPMind\Admin\AdminPage::current_module() ) {
			return;
		}
		wp_enqueue_script(
			'wpmind-admin-auto-meta',
			WPMIND_PLUGIN_URL . 'assets/js/admin-auto-meta.js',
			[ 'jquery', 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);
	}

	/**
	 * Get the MetaGenerator instance.
	 *
	 * @return MetaGenerator|null
	 */
	public function get_generator(): ?MetaGenerator {
		return $this->generator;
	}
}
