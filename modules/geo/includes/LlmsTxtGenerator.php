<?php
/**
 * LLMs.txt Generator
 *
 * Generates llms.txt file following the official specification.
 *
 * @package WPMind\Modules\Geo
 * @since 3.1.0
 * @see https://github.com/AnswerDotAI/llms-txt
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class LlmsTxtGenerator
 *
 * Generates llms.txt content following the official specification:
 * - H1: Site name
 * - Blockquote: Short description
 * - Paragraph: Additional context
 * - H2 sections: Grouped content links
 * - ## Optional: Skippable information
 *
 * Note: llms.txt is for content navigation, NOT access control.
 * Access control should use robots.txt.
 */
class LlmsTxtGenerator {

	/**
	 * Cache key prefix for llms.txt content.
	 */
	private const CACHE_KEY_PREFIX = 'wpmind_llms_txt_content_';

	/**
	 * Cache expiration in seconds (1 hour).
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Get cache key for current site.
	 *
	 * @return string Cache key with site ID for multisite support.
	 */
	private function get_cache_key(): string {
		return self::CACHE_KEY_PREFIX . get_current_blog_id();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_routes' ) );

		// Invalidate cache on content changes.
		add_action( 'save_post', array( $this, 'invalidate_cache' ) );
		add_action( 'delete_post', array( $this, 'invalidate_cache' ) );
		add_action( 'created_term', array( $this, 'invalidate_cache' ) );
		add_action( 'edited_term', array( $this, 'invalidate_cache' ) );
		add_action( 'delete_term', array( $this, 'invalidate_cache' ) );
	}

	/**
	 * Register rewrite rules for /llms.txt.
	 */
	public function register_routes(): void {
		// Check if llms.txt is enabled.
		// Support both legacy boolean and new string format.
		$enabled = get_option( 'wpmind_llms_txt_enabled', '1' );
		if ( $enabled !== '1' && $enabled !== true && $enabled !== 1 ) {
			return;
		}

		// Add rewrite rule.
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpmind_llms_txt=1', 'top' );

		// Register query var.
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'wpmind_llms_txt';
				return $vars;
			}
		);

		// Handle request before redirect_canonical (template_redirect/10) can
		// turn /llms.txt into a front-page style /llms.txt/ 301.
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );

		// Fallback for code that runs redirect_canonical() earlier.
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );
	}

	/**
	 * Never canonical-redirect a llms.txt request.
	 *
	 * @param string|false $redirect_url Redirect target computed by core.
	 * @return string|false False for llms.txt requests, otherwise unchanged.
	 */
	public function filter_redirect_canonical( $redirect_url ) {
		if ( get_query_var( 'wpmind_llms_txt' ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Public URL of llms.txt for the current site.
	 *
	 * Plain permalinks have no rewrite rules, so only the query form works there.
	 *
	 * @return string URL.
	 */
	public static function get_public_url(): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'wpmind_llms_txt', '1', home_url( '/' ) );
		}
		return home_url( '/llms.txt' );
	}

	/**
	 * Detect other providers of /llms.txt (detect and warn only, never yield).
	 *
	 * @return string[] Human-readable conflict notices.
	 */
	public static function detect_conflicts(): array {
		global $wp_rewrite;

		$conflicts = array();

		$root = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		if ( file_exists( trailingslashit( $root ) . 'llms.txt' ) ) {
			$conflicts[] = __( '站点根目录已有一个 llms.txt 文件，服务器会直接返回这个文件，WPMind 生成的内容不会生效。', 'wpmind' );
		}

		if ( $wp_rewrite instanceof \WP_Rewrite ) {
			foreach ( $wp_rewrite->extra_rules_top as $regex => $query ) {
				if ( false !== strpos( (string) $regex, 'llms' ) && false === strpos( (string) $query, 'wpmind_llms_txt' ) ) {
					$conflicts[] = __( '另一个插件也注册了 llms.txt 的重写规则，/llms.txt 可能由它响应。', 'wpmind' );
					break;
				}
			}
		}

		return $conflicts;
	}

	/**
	 * Handle llms.txt request.
	 */
	public function handle_request(): void {
		if ( ! get_query_var( 'wpmind_llms_txt' ) ) {
			return;
		}

		$this->render();
		exit;
	}

	/**
	 * Render llms.txt content.
	 */
	public function render(): void {
		// Explicit 200: the matched rule leaves WP_Query looking like the front page.
		// /llms.txt/ is answered here too (no 301 back, cached 301s would loop).
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-WPMind-Llms-Txt: 1' );
		header( 'Link: <' . esc_url_raw( self::get_public_url() ) . '>; rel="canonical"' );

		// Try to get cached content.
		$content = $this->get_cached_content();

		if ( null === $content ) {
			$content = $this->generate_content();
			$this->set_cache( $content );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text llms.txt feed; escaping would corrupt the content.
		echo $content;
	}

	/**
	 * Generate llms.txt content.
	 *
	 * @return string Generated content.
	 */
	private function generate_content(): string {
		$output = '';

		// H1: Site name.
		$output .= $this->get_site_header();

		// Blockquote: Short description.
		$output .= $this->get_site_description();

		// Paragraph: Additional context.
		$output .= $this->get_additional_context();

		// H2 sections: Content groups.
		$output .= $this->get_content_sections();

		// ## Optional: Skippable information.
		$output .= $this->get_optional_section();

		/**
		 * Filter the generated llms.txt content.
		 *
		 * @param string $output The generated content.
		 */
		return apply_filters( 'wpmind_llms_txt_content', $output );
	}

	/**
	 * Get site header (H1).
	 *
	 * @return string Site header.
	 */
	private function get_site_header(): string {
		$site_name = get_bloginfo( 'name' );
		return "# {$site_name}\n\n";
	}

	/**
	 * Get site description (blockquote).
	 *
	 * @return string Site description.
	 */
	private function get_site_description(): string {
		$description = get_bloginfo( 'description' );

		if ( empty( $description ) ) {
			$description = sprintf(
				/* translators: %s: site name */
				__( 'Welcome to %s', 'wpmind' ),
				get_bloginfo( 'name' )
			);
		}

		return "> {$description}\n\n";
	}

	/**
	 * Get additional context paragraph.
	 *
	 * @return string Additional context.
	 */
	private function get_additional_context(): string {
		$context = '';

		// Language information.
		$locale   = get_locale();
		$language = $this->get_language_name( $locale );
		$context .= sprintf( __( 'Primary language: %s.', 'wpmind' ), $language ) . ' ';

		// Content type.
		$post_types = $this->get_public_post_types();
		if ( ! empty( $post_types ) ) {
			$context .= sprintf(
				/* translators: %s: list of content types */
				__( 'Content types: %s.', 'wpmind' ),
				implode( ', ', $post_types )
			) . ' ';
		}

		// Citation preference.
		$context .= __( 'When citing content from this site, please include the author name, article title, and URL.', 'wpmind' );

		return $context . "\n\n";
	}

	/**
	 * Get content sections (H2 groups with links).
	 *
	 * @return string Content sections.
	 */
	private function get_content_sections(): string {
		$output = '';

		// Get categories with posts.
		$categories = get_categories(
			array(
				'hide_empty' => true,
				'number'     => 10,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( ! empty( $categories ) ) {
			$output .= '## ' . __( 'Categories', 'wpmind' ) . "\n\n";

			foreach ( $categories as $category ) {
				$url  = get_category_link( $category->term_id );
				$name = esc_html( $category->name );
				$desc = ! empty( $category->description )
					? esc_html( $category->description )
					: sprintf( __( '%d posts', 'wpmind' ), $category->count );

				$output .= "- [{$name}]({$url}): {$desc}\n";
			}

			$output .= "\n";
		}

		// Get recent posts.
		$recent_posts = get_posts(
			array(
				'numberposts' => 10,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		if ( ! empty( $recent_posts ) ) {
			$output .= '## ' . __( 'Recent Posts', 'wpmind' ) . "\n\n";

			foreach ( $recent_posts as $post ) {
				$url     = get_permalink( $post );
				$title   = esc_html( get_the_title( $post ) );
				$excerpt = wp_trim_words( get_the_excerpt( $post ), 15, '...' );

				$output .= "- [{$title}]({$url}): {$excerpt}\n";
			}

			$output .= "\n";
		}

		// Get pages.
		$pages = get_pages(
			array(
				'number'      => 5,
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
			)
		);

		if ( ! empty( $pages ) ) {
			$output .= '## ' . __( 'Pages', 'wpmind' ) . "\n\n";

			foreach ( $pages as $page ) {
				$url   = get_permalink( $page );
				$title = esc_html( get_the_title( $page ) );
				$desc  = wp_trim_words( get_the_excerpt( $page ), 10, '...' );

				if ( empty( $desc ) ) {
					$desc = __( 'Site page', 'wpmind' );
				}

				$output .= "- [{$title}]({$url}): {$desc}\n";
			}

			$output .= "\n";
		}

		return $output;
	}

	/**
	 * Get optional section.
	 *
	 * @return string Optional section.
	 */
	private function get_optional_section(): string {
		$output = "## Optional\n\n";

		// Feed links.
		$output .= sprintf(
			"- [%s](%s): %s\n",
			__( 'RSS Feed', 'wpmind' ),
			get_feed_link(),
			__( 'Subscribe to updates', 'wpmind' )
		);

		// Markdown feed (if enabled).
		if ( get_option( 'wpmind_standalone_markdown_feed', false ) ) {
			$output .= sprintf(
				"- [%s](%s): %s\n",
				__( 'Markdown Feed', 'wpmind' ),
				home_url( '/?feed=markdown' ),
				__( 'AI-optimized content feed', 'wpmind' )
			);
		}

		// Sitemap.
		$sitemap_url = home_url( '/wp-sitemap.xml' );
		$output     .= sprintf(
			"- [%s](%s): %s\n",
			__( 'Sitemap', 'wpmind' ),
			$sitemap_url,
			__( 'Complete site structure', 'wpmind' )
		);

		return $output;
	}

	/**
	 * Get cached content.
	 *
	 * @return string|null Cached content or null.
	 */
	private function get_cached_content(): ?string {
		$cached = get_transient( $this->get_cache_key() );
		return false === $cached ? null : $cached;
	}

	/**
	 * Set cache.
	 *
	 * @param string $content Content to cache.
	 */
	private function set_cache( string $content ): void {
		set_transient( $this->get_cache_key(), $content, self::CACHE_EXPIRATION );
	}

	/**
	 * Invalidate cache.
	 */
	public function invalidate_cache(): void {
		delete_transient( $this->get_cache_key() );
	}

	/**
	 * Get language name from locale.
	 *
	 * @param string $locale Locale code.
	 * @return string Language name.
	 */
	private function get_language_name( string $locale ): string {
		$languages = array(
			'zh_CN' => '简体中文 (Simplified Chinese)',
			'zh_TW' => '繁體中文 (Traditional Chinese)',
			'en_US' => 'English (US)',
			'en_GB' => 'English (UK)',
			'ja'    => '日本語 (Japanese)',
			'ko_KR' => '한국어 (Korean)',
		);

		return $languages[ $locale ] ?? $locale;
	}

	/**
	 * Get public post types.
	 *
	 * @return array Post type labels.
	 */
	private function get_public_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$labels = array();
		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$labels[] = $post_type->labels->name;
		}

		return $labels;
	}

	/**
	 * Flush rewrite rules on activation.
	 */
	public static function activate(): void {
		// Register rules first.
		$instance = new self();
		$instance->register_routes();

		// Flush rules (soft, no .htaccess write).
		flush_rewrite_rules( false );
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
