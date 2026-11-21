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
		// Always register admin save/management AJAX and admin assets so disabling
		// wpmind_auto_meta_enabled does not lock the settings UI.
		$ajax = new AutoMetaAjaxController();
		$ajax->register_hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		if ( get_option( 'wpmind_auto_meta_enabled', '1' ) !== '1' ) {
			return;
		}

		$this->generator = new MetaGenerator();
		$this->generator->register_hooks();

		// Abilities API (read-only preview entry; writes stay in the admin review flow).
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );

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
		if ( ! $this->is_wpmind_admin_page( $hook_suffix ) ) {
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
		if ( ! function_exists( 'wp_register_ability' ) || null === $this->generator ) {
			return;
		}

		wp_register_ability(
			'wpmind/auto-meta-preview',
			[
				'label'               => __( 'WPMind Auto Meta Preview', 'wpmind' ),
				'description'         => __( 'Generate proposed excerpt, SEO description, tags, category and FAQ for a published post without writing anything.', 'wpmind' ),
				'category'            => 'wpmind-content',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [
							'type'        => 'integer',
							'description' => __( 'Published post ID.', 'wpmind' ),
						],
					],
				],
				'output_schema'       => [
					'type'        => 'object',
					'description' => __( 'Proposed meta fields payload.', 'wpmind' ),
				],
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_auto_meta_preview_ability' ],
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
	 * Ability execute callback: preview auto-meta for a post.
	 *
	 * @param mixed ...$args Ability input.
	 * @return array{success:bool, data?:mixed, error?:array{code:string,message:string}}
	 */
	public function execute_auto_meta_preview_ability( ...$args ): array {
		$input   = is_array( $args[0] ?? null ) ? $args[0] : [];
		$post_id = (int) ( $input['post_id'] ?? 0 );

		if ( $post_id <= 0 || null === $this->generator ) {
			return [
				'success' => false,
				'error'   => [
					'code'    => 'wpmind_auto_meta_preview_bad_input',
					'message' => __( 'Invalid post_id.', 'wpmind' ),
				],
			];
		}

		$result = $this->generator->preview_meta( $post_id );
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
			'data'    => $result,
		];
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
