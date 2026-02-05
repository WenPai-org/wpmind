<?php
/**
 * GEO Signal Injector
 *
 * Injects GEO (Generative Engine Optimization) signals into Markdown content.
 *
 * @package WPMind\Modules\Geo
 * @since 3.0.0
 */

namespace WPMind\Modules\Geo;

/**
 * Class GeoSignalInjector
 *
 * Handles injection of GEO signals including:
 * - Authority signals (author, dates)
 * - Citation format
 * - Structured metadata
 */
class GeoSignalInjector {

	/**
	 * Inject GEO signals into sections.
	 *
	 * Uses array_merge instead of array_unshift to preserve associative keys.
	 * This fixes the Codex review issue about breaking associative arrays.
	 *
	 * @param array    $sections The content sections.
	 * @param \WP_Post $post     The post object.
	 * @return array Sections with GEO signals injected.
	 */
	public function inject( array $sections, \WP_Post $post ): array {
		// Generate signals.
		$authority = $this->generate_authority_signal( $post );
		$citation  = $this->generate_citation_format( $post );

		// Use array_merge to preserve keys (Codex review fix).
		// Prepend authority signal with a unique key.
		$result = array_merge(
			array( 'wpmind_authority' => $authority ),
			$sections,
			array( 'wpmind_citation' => $citation )
		);

		return $result;
	}

	/**
	 * Generate authority signal (front matter).
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Authority signal in YAML front matter format.
	 */
	private function generate_authority_signal( \WP_Post $post ): string {
		$author   = get_the_author_meta( 'display_name', $post->post_author );
		$date     = get_the_date( 'Y-m-d', $post );
		$modified = get_the_modified_date( 'Y-m-d', $post );

		// Get categories and tags.
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		$signal = "---\n";
		$signal .= sprintf( "作者: %s\n", $this->escape_yaml_value( $author ) );
		$signal .= sprintf( "发布日期: %s\n", $date );
		$signal .= sprintf( "最后更新: %s\n", $modified );

		if ( ! empty( $categories ) ) {
			$escaped_cats = array_map( array( $this, 'escape_yaml_value' ), $categories );
			$signal .= sprintf( "分类: %s\n", implode( ', ', $escaped_cats ) );
		}

		if ( ! empty( $tags ) ) {
			$escaped_tags = array_map( array( $this, 'escape_yaml_value' ), $tags );
			$signal .= sprintf( "标签: %s\n", implode( ', ', $escaped_tags ) );
		}

		$signal .= "---\n\n";

		return $signal;
	}

	/**
	 * Escape a value for safe YAML output.
	 *
	 * @param string $value The value to escape.
	 * @return string Escaped value.
	 */
	private function escape_yaml_value( string $value ): string {
		// Remove newlines and escape colons.
		$value = str_replace( array( "\n", "\r" ), ' ', $value );
		// If value contains special YAML characters, quote it.
		if ( preg_match( '/[:\[\]{}#&*!|>\'"%@`]/', $value ) ) {
			$value = '"' . str_replace( '"', '\\"', $value ) . '"';
		}
		return $value;
	}

	/**
	 * Generate citation format.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Citation format section.
	 */
	private function generate_citation_format( \WP_Post $post ): string {
		$author = get_the_author_meta( 'display_name', $post->post_author );
		$title  = get_the_title( $post );
		$url    = get_permalink( $post );
		$site   = get_bloginfo( 'name' );
		$date   = get_the_date( 'Y-m-d', $post );

		$citation = "\n\n---\n\n";
		$citation .= "## 引用本文\n\n";

		// APA style citation.
		$citation .= sprintf(
			"**APA**: %s. (%s). %s. *%s*. %s\n\n",
			esc_html( $author ),
			$date,
			esc_html( $title ),
			esc_html( $site ),
			esc_url( $url )
		);

		// Simple citation.
		$citation .= sprintf(
			"**简单引用**: %s - %s (%s)\n",
			esc_html( $title ),
			esc_html( $site ),
			esc_url( $url )
		);

		return $citation;
	}

	/**
	 * Generate structured data for AI parsing.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Structured data array.
	 */
	public function get_structured_data( \WP_Post $post ): array {
		return array(
			'@type'         => 'Article',
			'headline'      => get_the_title( $post ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			),
			'url'           => get_permalink( $post ),
		);
	}
}
