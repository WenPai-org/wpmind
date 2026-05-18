<?php
/**
 * PHPUnit bootstrap file for WPMind unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

// Plugin constants.
if ( ! defined( 'WPMIND_VERSION' ) ) {
	define( 'WPMIND_VERSION', '0.11.4' );
}
if ( ! defined( 'WPMIND_PLUGIN_FILE' ) ) {
	define( 'WPMIND_PLUGIN_FILE', __DIR__ . '/../wpmind.php' );
}
if ( ! defined( 'WPMIND_PLUGIN_DIR' ) ) {
	define( 'WPMIND_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'WPMIND_PLUGIN_URL' ) ) {
	define( 'WPMIND_PLUGIN_URL', 'http://example.org/wp-content/plugins/wpmind/' );
}
if ( ! defined( 'WPMIND_PLUGIN_BASENAME' ) ) {
	define( 'WPMIND_PLUGIN_BASENAME', 'wpmind/wpmind.php' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

// WP function stubs.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $key, $value, string $deprecated = '', bool $autoload = true ): bool {
		return true;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return 'wpmind/wpmind.php';
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, string $deprecated = '', string $path = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ): true {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ): true {
		return true;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( array $args, array $defaults = [] ): array {
		return array_merge( $defaults, $args );
	}
}

// WP_Error stub.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = [];
		public $error_data = [];

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		public function get_error_message( string $code = '' ): string {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}
	}
}

// Autoload the plugin classes.
require_once WPMIND_PLUGIN_DIR . 'includes/SDK/SDKAdapter.php';
