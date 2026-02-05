<?php
/**
 * Schema Generator
 *
 * Generates Schema.org structured data for WordPress content.
 *
 * @package WPMind\GEO
 * @since 3.1.0
 */

namespace WPMind\Modules\Geo;

/**
 * Class SchemaGenerator
 *
 * Generates Article schema following Google's structured data guidelines.
 * Supports compatibility modes to avoid conflicts with SEO plugins.
 */
class SchemaGenerator {

	/**
	 * Schema output mode.
	 */
	public const MODE_AUTO  = 'auto';   // Detect SEO plugins, skip if found.
	public const MODE_MERGE = 'merge';  // Merge with existing @graph.
	public const MODE_FORCE = 'force';  // Force output (may duplicate).

	/**
	 * Known SEO plugins that output schema.
	 */
	private const SEO_PLUGINS = array(
		'wordpress-seo/wp-seo.php',                     // Yoast SEO.
		'seo-by-rank-math/rank-math.php',               // Rank Math.
		'all-in-one-seo-pack/all_in_one_seo_pack.php',  // AIOSEO.
		'the-seo-framework/autodescription.php',        // The SEO Framework.
		'squirrly-seo/squirrly.php',                    // Squirrly SEO.
	);

	/**
	 * Current output mode.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->mode = get_option( 'wpmind_schema_mode', self::MODE_AUTO );

		add_action( 'wp_head', array( $this, 'maybe_output_schema' ), 1 );
	}

	/**
	 * Maybe output schema based on mode and context.
	 */
	public function maybe_output_schema(): void {
		// Check if schema is enabled.
		if ( ! get_option( 'wpmind_schema_enabled', true ) ) {
			return;
		}

		// Only output on singular content pages.
		if ( ! is_singular() ) {
			return;
		}

		// Check if we should output based on mode.
		if ( ! $this->should_output_schema() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->output_schema( $post );
	}

	/**
	 * Check if schema should be output based on mode.
	 *
	 * @return bool True if should output.
	 */
	private function should_output_schema(): bool {
		// Force mode always outputs.
		if ( self::MODE_FORCE === $this->mode ) {
			return true;
		}

		// Check for active SEO plugins.
		if ( $this->has_seo_plugin_active() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if any known SEO plugin is active.
	 *
	 * @return bool True if SEO plugin is active.
	 */
	private function has_seo_plugin_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::SEO_PLUGINS as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Output schema for a post.
	 *
	 * @param \WP_Post $post The post object.
	 */
	private function output_schema( \WP_Post $post ): void {
		$schema = $this->generate_article_schema( $post );

		if ( empty( $schema ) ) {
			return;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		echo "\n<!-- WPMind Schema.org -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo $json . "\n";
		echo '</script>' . "\n";
	}

	/**
	 * Generate Article schema for a post.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Schema data.
	 */
	private function generate_article_schema( \WP_Post $post ): array {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => $this->get_article_type( $post ),
		);

		// Headline (required).
		$schema['headline'] = wp_strip_all_tags( get_the_title( $post ) );

		// Author (recommended).
		$author = $this->get_author_schema( $post );
		if ( ! empty( $author ) ) {
			$schema['author'] = $author;
		}

		// Dates (recommended).
		$schema['datePublished'] = get_the_date( 'c', $post );
		$schema['dateModified']  = get_the_modified_date( 'c', $post );

		// Publisher (recommended).
		$publisher = $this->get_publisher_schema();
		if ( ! empty( $publisher ) ) {
			$schema['publisher'] = $publisher;
		}

		// Image (recommended).
		$image = $this->get_image_schema( $post );
		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}

		// Main entity of page.
		$schema['mainEntityOfPage'] = array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post ),
		);

		// Description (optional but helpful).
		$excerpt = get_the_excerpt( $post );
		if ( ! empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// Word count (optional).
		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		if ( $word_count > 0 ) {
			$schema['wordCount'] = $word_count;
		}

		// Article section (category).
		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$schema['articleSection'] = $categories[0]->name;
		}

		// Keywords (tags).
		$tags = get_the_tags( $post->ID );
		if ( ! empty( $tags ) ) {
			$keywords = array_map(
				function ( $tag ) {
					return $tag->name;
				},
				$tags
			);
			$schema['keywords'] = implode( ', ', $keywords );
		}

		// Language (helps AI understand content language).
		$schema['inLanguage'] = get_locale();

		/**
		 * Filter the generated schema.
		 *
		 * @param array    $schema The schema data.
		 * @param \WP_Post $post   The post object.
		 */
		return apply_filters( 'wpmind_article_schema', $schema, $post );
	}

	/**
	 * Get the most specific Article type.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Article type.
	 */
	private function get_article_type( \WP_Post $post ): string {
		// Check post type.
		$post_type = get_post_type( $post );

		// News-like content.
		if ( 'post' === $post_type ) {
			// Check if it's recent (within 48 hours).
			$post_time = get_post_time( 'U', true, $post );
			$now       = time();

			if ( ( $now - $post_time ) < 172800 ) {
				return 'NewsArticle';
			}

			return 'BlogPosting';
		}

		// Pages and other content.
		return 'Article';
	}

	/**
	 * Get author schema.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null Author schema or null.
	 */
	private function get_author_schema( \WP_Post $post ): ?array {
		$author_id = $post->post_author;

		if ( empty( $author_id ) ) {
			return null;
		}

		$author = get_userdata( $author_id );

		if ( ! $author ) {
			return null;
		}

		$schema = array(
			'@type' => 'Person',
			'name'  => $author->display_name,
		);

		// Author URL.
		$author_url = get_author_posts_url( $author_id );
		if ( ! empty( $author_url ) ) {
			$schema['url'] = $author_url;
		}

		return $schema;
	}

	/**
	 * Get publisher schema.
	 *
	 * @return array Publisher schema.
	 */
	private function get_publisher_schema(): array {
		$schema = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		);

		// Try to get site logo.
		$logo_id = get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );

			if ( $logo_url ) {
				$schema['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		return $schema;
	}

	/**
	 * Get image schema.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string|null Image URL or null.
	 */
	private function get_image_schema( \WP_Post $post ): ?string {
		// Try featured image first.
		if ( has_post_thumbnail( $post ) ) {
			return get_the_post_thumbnail_url( $post, 'large' );
		}

		// Try to find first image in content.
		preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $matches );

		if ( ! empty( $matches[1] ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Get current mode.
	 *
	 * @return string Current mode.
	 */
	public function get_mode(): string {
		return $this->mode;
	}

	/**
	 * Set mode.
	 *
	 * @param string $mode New mode.
	 */
	public function set_mode( string $mode ): void {
		if ( in_array( $mode, array( self::MODE_AUTO, self::MODE_MERGE, self::MODE_FORCE ), true ) ) {
			$this->mode = $mode;
		}
	}
}
