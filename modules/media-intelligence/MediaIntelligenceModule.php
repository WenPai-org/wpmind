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

		// Register AJAX handlers.
		$ajax = new MediaAjaxController();
		$ajax->register_hooks();

		// Abilities API (read-only preview entry; writes stay in the admin review flow).
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );

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
	 * Enqueue admin assets for the Media Intelligence tab.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! $this->is_wpmind_admin_page( $hook_suffix ) ) {
			return;
		}
		if ( 'module' !== \WPMind\Admin\AdminPage::current_view() || 'media-intelligence' !== \WPMind\Admin\AdminPage::current_module() ) {
			return;
		}
		wp_enqueue_script(
			'wpmind-admin-media-intelligence',
			WPMIND_PLUGIN_URL . 'assets/js/admin-media-intelligence.js',
			[ 'jquery', 'wpmind-admin-boot' ],
			WPMIND_VERSION,
			true
		);
	}

	/**
	 * Check whether the current admin page belongs to WPMind.
	 *
	 * @param string $hook_suffix Current page hook suffix.
	 * @return bool
	 */
	private function is_wpmind_admin_page( string $hook_suffix ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page scope check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' !== $page && class_exists( 'Wenpai_Admin_Menu' ) && \Wenpai_Admin_Menu::owns_page( 'wpmind' ) ) {
			return 'wpmind' === $page || str_starts_with( $page, 'wpmind-' ) || str_starts_with( $page, 'wpmind_' );
		}
		return str_ends_with( $hook_suffix, '_page_wpmind' );
	}

	/**
	 * Register the content-abilities category.
	 */
	public function register_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'wpmind-content' ) ) {
			return;
		}
		wp_register_ability_category(
			'wpmind-content',
			[
				'label'       => __( 'WPMind Content', 'wpmind' ),
				'description' => __( 'Content automation preview abilities (auto meta, media).', 'wpmind' ),
			]
		);
	}

	/**
	 * Register read-only preview abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'wpmind/media-alt-text-preview',
			[
				'label'               => __( 'WPMind Media Alt Text Preview', 'wpmind' ),
				'description'         => __( 'Generate a proposed alt text for an image attachment without writing anything.', 'wpmind' ),
				'category'            => 'wpmind-content',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'attachment_id' ],
					'properties' => [
						'attachment_id' => [
							'type'        => 'integer',
							'description' => __( 'Image attachment post ID.', 'wpmind' ),
						],
					],
				],
				'output_schema'       => [
					'type'        => 'object',
					'description' => __( 'Proposed alt text payload.', 'wpmind' ),
				],
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_media_alt_text_preview_ability' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}

	/**
	 * Ability execute callback: preview alt text for an attachment.
	 *
	 * @param mixed ...$args Ability input.
	 * @return array{success:bool, data?:mixed, error?:array{code:string,message:string}}
	 */
	public function execute_media_alt_text_preview_ability( ...$args ): array {
		$input         = is_array( $args[0] ?? null ) ? $args[0] : [];
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );

		if ( $attachment_id <= 0 ) {
			return [
				'success' => false,
				'error'   => [
					'code'    => 'wpmind_media_alt_preview_bad_input',
					'message' => __( 'Invalid attachment_id.', 'wpmind' ),
				],
			];
		}

		$generator = new AltTextGenerator( false );
		$result    = $generator->generate_proposed_alt( $attachment_id );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'error'   => [
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				],
			];
		}

		return [
			'success' => true,
			'data'    => [ 'alt_text' => $result ],
		];
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
