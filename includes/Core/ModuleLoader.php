<?php
/**
 * Module Loader
 *
 * Discovers, loads, and manages WPMind modules.
 *
 * @package WPMind\Core
 * @since 3.2.0
 */

namespace WPMind\Core;

/**
 * Class ModuleLoader
 *
 * Handles module discovery, loading, and lifecycle management.
 */
class ModuleLoader {

	/**
	 * Singleton instance.
	 *
	 * @var ModuleLoader|null
	 */
	private static ?ModuleLoader $instance = null;

	/**
	 * Registered modules.
	 *
	 * @var array<string, array>
	 */
	private array $modules = [];

	/**
	 * Loaded module instances.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $instances = [];

	/**
	 * Modules directory path.
	 *
	 * @var string
	 */
	private string $modules_dir;

	/**
	 * Get singleton instance.
	 *
	 * @return ModuleLoader
	 */
	public static function instance(): ModuleLoader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->modules_dir = WPMIND_PATH . 'modules/';
	}

	/**
	 * Initialize the module loader.
	 */
	public function init(): void {
		$this->discover_modules();
		$this->load_enabled_modules();

		// Register AJAX handlers.
		add_action( 'wp_ajax_wpmind_toggle_module', array( $this, 'ajax_toggle_module' ) );
	}

	/**
	 * Discover available modules.
	 */
	private function discover_modules(): void {
		if ( ! is_dir( $this->modules_dir ) ) {
			return;
		}

		$dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );

		foreach ( $dirs as $dir ) {
			$module_id   = basename( $dir );
			$module_file = $dir . '/module.json';
			$class_file  = $dir . '/' . $this->get_module_class_filename( $module_id );

			if ( ! file_exists( $module_file ) || ! file_exists( $class_file ) ) {
				continue;
			}

			$meta = json_decode( file_get_contents( $module_file ), true );

			if ( ! $meta || ! isset( $meta['id'], $meta['name'], $meta['version'] ) ) {
				continue;
			}

			$this->modules[ $module_id ] = array(
				'id'          => $meta['id'],
				'name'        => $meta['name'],
				'description' => $meta['description'] ?? '',
				'version'     => $meta['version'],
				'author'      => $meta['author'] ?? '',
				'icon'        => $meta['icon'] ?? 'dashicons-admin-plugins',
				'class'       => $meta['class'] ?? $this->get_module_class_name( $module_id ),
				'class_file'  => $class_file,
				'path'        => $dir,
				'enabled'     => $this->is_module_enabled( $module_id ),
				'can_disable' => $meta['can_disable'] ?? true,
			);
		}

		/**
		 * Filter discovered modules.
		 *
		 * @param array $modules Discovered modules.
		 */
		$this->modules = apply_filters( 'wpmind_discovered_modules', $this->modules );
	}

	/**
	 * Get module class filename from module ID.
	 *
	 * @param string $module_id Module ID.
	 * @return string Class filename.
	 */
	private function get_module_class_filename( string $module_id ): string {
		// geo -> GeoModule.php
		$parts = explode( '-', $module_id );
		$name  = implode( '', array_map( 'ucfirst', $parts ) );
		return $name . 'Module.php';
	}

	/**
	 * Get module class name from module ID.
	 *
	 * @param string $module_id Module ID.
	 * @return string Fully qualified class name.
	 */
	private function get_module_class_name( string $module_id ): string {
		$parts = explode( '-', $module_id );
		$name  = implode( '', array_map( 'ucfirst', $parts ) );
		return "WPMind\\Modules\\{$name}\\{$name}Module";
	}

	/**
	 * Load enabled modules.
	 */
	private function load_enabled_modules(): void {
		foreach ( $this->modules as $module_id => $module ) {
			if ( ! $module['enabled'] ) {
				continue;
			}

			$this->load_module( $module_id );
		}

		/**
		 * Fires after all enabled modules are loaded.
		 *
		 * @param array $instances Loaded module instances.
		 */
		do_action( 'wpmind_modules_loaded', $this->instances );
	}

	/**
	 * Load a specific module.
	 *
	 * @param string $module_id Module ID.
	 * @return bool True if loaded successfully.
	 */
	public function load_module( string $module_id ): bool {
		if ( ! isset( $this->modules[ $module_id ] ) ) {
			return false;
		}

		if ( isset( $this->instances[ $module_id ] ) ) {
			return true; // Already loaded.
		}

		$module = $this->modules[ $module_id ];

		// Load class file.
		require_once $module['class_file'];

		$class = $module['class'];

		if ( ! class_exists( $class ) ) {
			return false;
		}

		// Instantiate module.
		$instance = new $class();

		if ( ! $instance instanceof ModuleInterface ) {
			return false;
		}

		// Check dependencies.
		if ( ! $instance->check_dependencies() ) {
			return false;
		}

		// Initialize module.
		$instance->init();

		$this->instances[ $module_id ] = $instance;

		/**
		 * Fires when a module is loaded.
		 *
		 * @param string          $module_id Module ID.
		 * @param ModuleInterface $instance  Module instance.
		 */
		do_action( 'wpmind_module_loaded', $module_id, $instance );
		do_action( "wpmind_module_{$module_id}_loaded", $instance );

		return true;
	}

	/**
	 * Check if a module is enabled.
	 *
	 * @param string $module_id Module ID.
	 * @return bool True if enabled.
	 */
	public function is_module_enabled( string $module_id ): bool {
		return (bool) get_option( "wpmind_module_{$module_id}_enabled", true );
	}

	/**
	 * Enable a module.
	 *
	 * @param string $module_id Module ID.
	 * @return bool True if enabled successfully.
	 */
	public function enable_module( string $module_id ): bool {
		if ( ! isset( $this->modules[ $module_id ] ) ) {
			return false;
		}

		update_option( "wpmind_module_{$module_id}_enabled", true );
		$this->modules[ $module_id ]['enabled'] = true;

		/**
		 * Fires when a module is enabled.
		 *
		 * @param string $module_id Module ID.
		 */
		do_action( 'wpmind_module_enabled', $module_id );

		return true;
	}

	/**
	 * Disable a module.
	 *
	 * @param string $module_id Module ID.
	 * @return bool True if disabled successfully.
	 */
	public function disable_module( string $module_id ): bool {
		if ( ! isset( $this->modules[ $module_id ] ) ) {
			return false;
		}

		if ( ! $this->modules[ $module_id ]['can_disable'] ) {
			return false;
		}

		update_option( "wpmind_module_{$module_id}_enabled", false );
		$this->modules[ $module_id ]['enabled'] = false;

		/**
		 * Fires when a module is disabled.
		 *
		 * @param string $module_id Module ID.
		 */
		do_action( 'wpmind_module_disabled', $module_id );

		return true;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array<string, array> Modules array.
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * Get a specific module info.
	 *
	 * @param string $module_id Module ID.
	 * @return array|null Module info or null.
	 */
	public function get_module( string $module_id ): ?array {
		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Get a loaded module instance.
	 *
	 * @param string $module_id Module ID.
	 * @return ModuleInterface|null Module instance or null.
	 */
	public function get_instance( string $module_id ): ?ModuleInterface {
		return $this->instances[ $module_id ] ?? null;
	}

	/**
	 * Get all loaded module instances.
	 *
	 * @return array<string, ModuleInterface> Module instances.
	 */
	public function get_instances(): array {
		return $this->instances;
	}

	/**
	 * AJAX handler for toggling module status.
	 */
	public function ajax_toggle_module(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		$module_id = sanitize_key( $_POST['module_id'] ?? '' );
		$enable    = filter_var( $_POST['enable'] ?? false, FILTER_VALIDATE_BOOLEAN );

		if ( empty( $module_id ) ) {
			wp_send_json_error( array( 'message' => __( '无效的模块 ID', 'wpmind' ) ) );
		}

		$module = $this->get_module( $module_id );

		if ( ! $module ) {
			wp_send_json_error( array( 'message' => __( '模块不存在', 'wpmind' ) ) );
		}

		if ( ! $enable && ! $module['can_disable'] ) {
			wp_send_json_error( array( 'message' => __( '此模块不能被禁用', 'wpmind' ) ) );
		}

		if ( $enable ) {
			$this->enable_module( $module_id );
			$message = sprintf( __( '模块 %s 已启用', 'wpmind' ), $module['name'] );
		} else {
			$this->disable_module( $module_id );
			$message = sprintf( __( '模块 %s 已禁用', 'wpmind' ), $module['name'] );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'enabled' => $enable,
				'reload'  => true,
			)
		);
	}
}
