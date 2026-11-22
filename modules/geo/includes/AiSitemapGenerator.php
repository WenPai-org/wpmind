<?php
/**
 * AI Sitemap Generator
 *
 * Generates /ai-sitemap.xml with AI-specific metadata per URL.
 *
 * @package WPMind\Modules\Geo
 * @since 3.9.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class AiSitemapGenerator
 *
 * Produces an XML sitemap tailored for AI crawlers, enriched with
 * ai:declaration, ai:summary, and automatic noai exclusion.
 */
class AiSitemapGenerator {

	/**
	 * Cache key prefix.
	 */
	private const CACHE_KEY_PREFIX = 'wpmind_ai_sitemap_';

	/**
	 * Cache expiration in seconds (1 hour).
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Custom XML namespace for AI metadata.
	 */
	private const AI_NS = 'https://wpcy.com/mind/ai-sitemap/1.0';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_routes' ] );
		add_action( 'save_post', [ $this, 'invalidate_cache' ] );
		add_action( 'delete_post', [ $this, 'invalidate_cache' ] );
	}

	/**
	 * Register rewrite rules for /ai-sitemap.xml.
	 */
	public function register_routes(): void {
		add_rewrite_rule( '^ai-sitemap\.xml$', 'index.php?wpmind_ai_sitemap=1', 'top' );

		add_filter(
			'query_vars',
			function ( array $vars ): array {
				$vars[] = 'wpmind_ai_sitemap';
				return $vars;
			}
		);

		// Before redirect_canonical (template_redirect/10), which would 301 to /ai-sitemap.xml/.
		add_action( 'template_redirect', [ $this, 'handle_request' ], 1 );

		// Fallback for code that runs redirect_canonical() earlier.
		add_filter( 'redirect_canonical', [ $this, 'filter_redirect_canonical' ] );
	}

	/**
	 * Never canonical-redirect an ai-sitemap.xml request.
	 *
	 * @param string|false $redirect_url Redirect target computed by core.
	 * @return string|false False for ai-sitemap.xml requests, otherwise unchanged.
	 */
	public function filter_redirect_canonical( $redirect_url ) {
		if ( get_query_var( 'wpmind_ai_sitemap' ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Public URL of ai-sitemap.xml for the current site.
	 *
	 * @return string URL.
	 */
	public static function get_public_url(): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'wpmind_ai_sitemap', '1', home_url( '/' ) );
		}
		return home_url( '/ai-sitemap.xml' );
	}

	/**
	 * Handle ai-sitemap.xml request.
	 */
	public function handle_request(): void {
		if ( ! get_query_var( 'wpmind_ai_sitemap' ) ) {
			return;
		}

		$this->render();
		exit;
	}

	/**
	 * Render the AI sitemap XML.
	 */
	public function render(): void {
		do_action( 'wpmind_ai_sitemap_accessed' );

		// Explicit 200; /ai-sitemap.xml/ is answered here too (no 301).
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Link: <' . esc_url_raw( self::get_public_url() ) . '>; rel="canonical"' );

		$content = $this->get_cached_content();

		if ( null === $content ) {
			$content = $this->generate_xml();
			$this->set_cache( $content );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML sitemap output; escaping would produce invalid XML.
		echo $content;
	}

	/**
	 * Generate the full XML sitemap.
	 *
	 * @return string XML content.
	 */
	public function generate_xml(): string {
		$max_entries = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );

		$posts = get_posts(
			[
				'numberposts' => $max_entries,
				'post_status' => 'publish',
				'post_type'   => $this->get_included_post_types(),
				'orderby'     => 'modified',
				'order'       => 'DESC',
			]
		);

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:ai="' . self::AI_NS . '">' . "\n";

		foreach ( $posts as $post ) {
			// Skip posts excluded from AI indexing (read post meta directly).
			if ( $this->is_post_excluded( $post ) ) {
				continue;
			}

			$xml .= $this->build_url_entry( $post );
		}

		$xml .= '</urlset>' . "\n";

		/** @var string */
		return apply_filters( 'wpmind_ai_sitemap_xml', $xml );
	}

	/**
	 * Build a single <url> entry.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string XML fragment.
	 */
	private function build_url_entry( \WP_Post $post ): string {
		$loc     = esc_url( get_permalink( $post ) );
		$lastmod = get_the_modified_date( 'c', $post );

		$xml  = "\t<url>\n";
		$xml .= "\t\t<loc>{$loc}</loc>\n";
		$xml .= "\t\t<lastmod>{$lastmod}</lastmod>\n";

		// AI declaration (read post meta directly with global fallback).
		$declaration = $this->get_post_declaration( $post->ID );
		$xml        .= "\t\t<ai:declaration>" . esc_xml( $declaration ) . "</ai:declaration>\n";

		// AI summary (excerpt, max 200 chars).
		$summary = $this->get_summary( $post );
		if ( ! empty( $summary ) ) {
			$xml .= "\t\t<ai:summary>" . esc_xml( $summary ) . "</ai:summary>\n";
		}

		$xml .= "\t</url>\n";

		return $xml;
	}

	/**
	 * Check whether a post is excluded from AI sitemap by post meta or global post-type rule.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool True if excluded.
	 */
	private function is_post_excluded( \WP_Post $post ): bool {
		if ( 'noai' === get_post_meta( $post->ID, '_wpmind_ai_indexing', true ) ) {
			return true;
		}
		if ( '1' === get_post_meta( $post->ID, '_wpmind_ai_noindex', true ) ) {
			return true;
		}
		if ( '1' === get_post_meta( $post->ID, '_wpmind_ai_nollm', true ) ) {
			return true;
		}

		$excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
		if ( is_array( $excluded_types ) && in_array( $post->post_type, $excluded_types, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve AI content declaration directly from post meta with global fallback.
	 *
	 * @param int $post_id Post ID.
	 * @return string Declaration value.
	 */
	private function get_post_declaration( int $post_id ): string {
		$valid       = [ 'original', 'ai-assisted', 'ai-generated' ];
		$declaration = (string) get_post_meta( $post_id, '_wpmind_ai_declaration', true );
		if ( '' !== $declaration && in_array( $declaration, $valid, true ) ) {
			return $declaration;
		}

		return (string) get_option( 'wpmind_ai_default_declaration', 'original' );
	}

	/**
	 * Get a short summary for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Summary text.
	 */
	private function get_summary( \WP_Post $post ): string {
		$excerpt = get_the_excerpt( $post );

		if ( ! empty( $excerpt ) ) {
			return wp_trim_words( $excerpt, 30, '...' );
		}

		// Fall back to trimmed content.
		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 30, '...' );
	}

	/**
	 * Get post types to include in the sitemap.
	 *
	 * @return array Post type names.
	 */
	private function get_included_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );

		/** @var array */
		return apply_filters( 'wpmind_ai_sitemap_post_types', array_values( $types ) );
	}

	/**
	 * Get cache key.
	 *
	 * @return string
	 */
	private function get_cache_key(): string {
		return self::CACHE_KEY_PREFIX . get_current_blog_id();
	}

	/**
	 * Get cached content.
	 *
	 * @return string|null
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
	 * Flush rewrite rules on activation.
	 */
	public static function activate(): void {
		$instance = new self();
		$instance->register_routes();
		flush_rewrite_rules( false );
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
