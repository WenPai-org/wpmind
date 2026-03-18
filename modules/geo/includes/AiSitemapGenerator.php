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
	private const AI_NS = 'https://wpmind.com/ai-sitemap/1.0';

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

		add_action( 'template_redirect', [ $this, 'handle_request' ] );
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
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

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
	private function generate_xml(): string {
		$max_entries = (int) get_option( 'wpmind_ai_sitemap_max_entries', 500 );
		$ai_indexing = $this->get_ai_indexing_manager();

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
			// Skip posts excluded from AI indexing.
			if ( $ai_indexing && $ai_indexing->is_excluded( $post->ID ) ) {
				continue;
			}

			$xml .= $this->build_url_entry( $post, $ai_indexing );
		}

		$xml .= '</urlset>' . "\n";

		/** @var string */
		return apply_filters( 'wpmind_ai_sitemap_xml', $xml );
	}

	/**
	 * Build a single <url> entry.
	 *
	 * @param \WP_Post               $post        Post object.
	 * @param AiIndexingManager|null $ai_indexing AI indexing manager.
	 * @return string XML fragment.
	 */
	private function build_url_entry( \WP_Post $post, ?AiIndexingManager $ai_indexing ): string {
		$loc     = esc_url( get_permalink( $post ) );
		$lastmod = get_the_modified_date( 'c', $post );

		$xml  = "\t<url>\n";
		$xml .= "\t\t<loc>{$loc}</loc>\n";
		$xml .= "\t\t<lastmod>{$lastmod}</lastmod>\n";

		// AI declaration.
		$declaration = $ai_indexing
			? $ai_indexing->get_declaration( $post->ID )
			: get_option( 'wpmind_ai_default_declaration', 'original' );
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
	 * Get the AiIndexingManager instance from the GEO module.
	 *
	 * @return AiIndexingManager|null
	 */
	private function get_ai_indexing_manager(): ?AiIndexingManager {
		if ( ! function_exists( 'wpmind' ) ) {
			return null;
		}

		$plugin = wpmind();
		if ( ! method_exists( $plugin, 'get_module_loader' ) ) {
			return null;
		}

		$loader = $plugin->get_module_loader();
		if ( ! $loader ) {
			return null;
		}

		$geo = $loader->get_module( 'geo' );
		if ( ! $geo instanceof GeoModule ) {
			return null;
		}

		$component = $geo->get_component( 'ai_indexing' );
		return $component instanceof AiIndexingManager ? $component : null;
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
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
