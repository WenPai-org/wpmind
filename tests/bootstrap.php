<?php
/**
 * PHPUnit bootstrap file for WPMind unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

// Plugin constants.
if ( ! defined( 'WPMIND_VERSION' ) ) {
	define( 'WPMIND_VERSION', '0.12.0' );
}
if ( ! defined( 'WPMIND_PLUGIN_FILE' ) ) {
	define( 'WPMIND_PLUGIN_FILE', __DIR__ . '/../wpmind.php' );
}
if ( ! defined( 'WPMIND_PLUGIN_DIR' ) ) {
	define( 'WPMIND_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'WPMIND_PATH' ) ) {
	define( 'WPMIND_PATH', WPMIND_PLUGIN_DIR );
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
$GLOBALS['wpmind_test_options'] = [];
$GLOBALS['wpmind_test_actions'] = [];
$GLOBALS['wpmind_test_autoload'] = [];
$GLOBALS['wpmind_test_filters']  = [];
$GLOBALS['wpmind_test_settings_errors']       = [];
$GLOBALS['wpmind_test_update_option_filters'] = [];
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
		return array_key_exists( $key, $GLOBALS['wpmind_test_options'] ) ? $GLOBALS['wpmind_test_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value, $autoload = null ): bool {
		// 测试可以用 $GLOBALS['wpmind_test_update_option_filters'][ $key ] 模拟 pre_update_option 过滤器或并发写入。
		if ( isset( $GLOBALS['wpmind_test_update_option_filters'][ $key ] ) ) {
			$value = ( $GLOBALS['wpmind_test_update_option_filters'][ $key ] )( $value );
		}
		$GLOBALS['wpmind_test_options'][ $key ] = $value;
		if ( func_num_args() >= 3 && null !== $autoload ) {
			$GLOBALS['wpmind_test_autoload'][ $key ] = (bool) $autoload;
		}
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $key, $value, string $deprecated = '', bool $autoload = true ): bool {
		if ( array_key_exists( $key, $GLOBALS['wpmind_test_options'] ) ) {
			return false;
		}
		$GLOBALS['wpmind_test_options'][ $key ]  = $value;
		$GLOBALS['wpmind_test_autoload'][ $key ] = (bool) $autoload;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $key ): bool {
		unset( $GLOBALS['wpmind_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache(): bool {
		return (bool) ( $GLOBALS['wpmind_test_ext_cache'] ?? false );
	}
}

if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( string $key, $value, string $group = '', int $expire = 0 ): bool {
		return (bool) ( $GLOBALS['wpmind_test_cache_add_result'] ?? false );
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
		// 测试可以用 $GLOBALS['wpmind_test_filters'][ $tag ] = callable 挂一个过滤器。
		if ( isset( $GLOBALS['wpmind_test_filters'][ $tag ] ) ) {
			return ( $GLOBALS['wpmind_test_filters'][ $tag ] )( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ): true {
		$GLOBALS['wpmind_test_actions'][ $tag ][] = $callback;
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

// endpoint schema v2 / 迁移相关的共享桩（PR1 wpmind-endpoint-schema）。
if ( ! function_exists( 'wp_set_option_autoload' ) ) {
	function wp_set_option_autoload( string $option, $autoload ): bool {
		$GLOBALS['wpmind_test_autoload'][ $option ] = (bool) $autoload;
		return true;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return 'wpmind-unit-test-salt-' . $scheme;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	// 近似 WP：只放行允许的协议，去掉首尾空白。
	function esc_url_raw( string $url, ?array $protocols = null ): string {
		$url       = trim( $url );
		$protocols = $protocols ?? [ 'http', 'https' ];
		$scheme    = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( '' === $url || ! in_array( $scheme, $protocols, true ) ) {
			return '';
		}
		return str_replace( ' ', '%20', $url );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	// 近似 WP：只允许 http/https、有主机、非内网/回环地址、端口 80/443/8080。
	function wp_http_validate_url( string $url ) {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true ) ) {
			return false;
		}
		$host = strtolower( $parts['host'] );
		if ( 'localhost' === $host || preg_match( '/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host ) ) {
			return false;
		}
		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], [ 80, 443, 8080 ], true ) ) {
			return false;
		}
		return $url;
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['wpmind_test_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

// Autoload the plugin classes.
require_once WPMIND_PLUGIN_DIR . 'includes/SDK/SDKAdapter.php';
