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
	function update_option( string $key, $value, bool $autoload = true ): bool {
		$GLOBALS['wpmind_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $key, $value, string $deprecated = '', bool $autoload = true ): bool {
		if ( array_key_exists( $key, $GLOBALS['wpmind_test_options'] ) ) {
			return false;
		}
		$GLOBALS['wpmind_test_options'][ $key ] = $value;
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

// Autoload the plugin classes.
require_once WPMIND_PLUGIN_DIR . 'includes/SDK/SDKAdapter.php';

if ( ! class_exists( 'WPMind_Test_WPDB' ) ) {
	/**
	 * Minimal $wpdb double backed by $GLOBALS['wpmind_test_options'].
	 *
	 * Supports esc_like(), prepare() with %s/%d and get_var() for
	 * "SELECT option_name FROM {options} WHERE option_name LIKE %s LIMIT 1".
	 * Not installed as $GLOBALS['wpdb'] by default; tests opt in.
	 */
	class WPMind_Test_WPDB {
		/** @var string */
		public $options = 'wp_options';

		/** @var list<string> Queries passed to get_var(). */
		public $queries = [];

		/** @var list<string> Raw LIKE patterns seen by prepare(). */
		public $like_args = [];

		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}

		public function prepare( string $query, ...$args ): string {
			$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
			foreach ( $args as $arg ) {
				$this->like_args[] = (string) $arg;
			}
			$i = 0;
			return (string) preg_replace_callback(
				'/%[sd]/',
				static function ( array $m ) use ( $args, &$i ): string {
					$value = $args[ $i++ ] ?? '';
					return '%d' === $m[0] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'";
				},
				$query
			);
		}

		/**
		 * @return string|null
		 */
		public function get_var( string $query ) {
			$this->queries[] = $query;
			if ( ! preg_match( "/option_name LIKE '((?:[^'\\\\]|\\\\.)*)'/", $query, $m ) ) {
				return null;
			}
			$pattern = stripslashes( $m[1] );
			$regex   = '';
			$len     = strlen( $pattern );
			for ( $k = 0; $k < $len; $k++ ) {
				$c = $pattern[ $k ];
				if ( '\\' === $c && $k + 1 < $len ) {
					$regex .= preg_quote( $pattern[ ++$k ], '/' );
				} elseif ( '%' === $c ) {
					$regex .= '.*';
				} elseif ( '_' === $c ) {
					$regex .= '.';
				} else {
					$regex .= preg_quote( $c, '/' );
				}
			}
			foreach ( array_keys( $GLOBALS['wpmind_test_options'] ) as $name ) {
				if ( preg_match( '/^' . $regex . '$/s', (string) $name ) ) {
					return (string) $name;
				}
			}
			return null;
		}
	}
}
