<?php
/**
 * Brand Entity
 *
 * Enriches publisher Schema and outputs Organization JSON-LD on the front page.
 *
 * @package WPMind\Modules\Geo
 * @since 3.11.2
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class BrandEntity
 *
 * Enhances publisher schema with brand identity (sameAs, description, contact)
 * and outputs a standalone Organization JSON-LD block on the front page.
 */
class BrandEntity {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wpmind_article_schema', [ $this, 'enrich_publisher' ], 5, 2 );
		add_action( 'wp_head', [ $this, 'output_organization_schema' ], 2 );
		add_action( 'wp_head', [ $this, 'output_website_schema' ], 2 );
	}

	/**
	 * Enrich the publisher node inside article schema.
	 *
	 * @param array    $schema Schema data.
	 * @param \WP_Post $post   Post object.
	 * @return array Modified schema.
	 */
	public function enrich_publisher( array $schema, \WP_Post $post ): array {
		if ( ! isset( $schema['publisher'] ) ) {
			return $schema;
		}

		$publisher = &$schema['publisher'];

		$url = get_option( 'wpmind_brand_url', '' );
		if ( ! empty( $url ) ) {
			$publisher['url'] = $url;
		}

		$desc = get_option( 'wpmind_brand_description', '' );
		if ( ! empty( $desc ) ) {
			$publisher['description'] = $desc;
		}

		$same_as = $this->get_same_as_urls();
		if ( ! empty( $same_as ) ) {
			$publisher['sameAs'] = $same_as;
		}

		$org_type = get_option( 'wpmind_brand_org_type', 'Organization' );
		if ( $org_type !== 'Organization' ) {
			$publisher['@type'] = $org_type;
		}

		$contact = $this->get_contact_point();
		if ( ! empty( $contact ) ) {
			$publisher['contactPoint'] = $contact;
		}

		return $schema;
	}

	/**
	 * Output standalone Organization JSON-LD on the front page.
	 */
	public function output_organization_schema(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$org_type = get_option( 'wpmind_brand_org_type', 'Organization' );
		$name     = get_option( 'wpmind_brand_name', '' );
		if ( empty( $name ) ) {
			$name = get_bloginfo( 'name' );
		}

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $org_type,
			'name'     => $name,
			'url'      => get_option( 'wpmind_brand_url', '' ) ?: home_url( '/' ),
		];

		$desc = get_option( 'wpmind_brand_description', '' );
		if ( ! empty( $desc ) ) {
			$schema['description'] = $desc;
		}

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( (int) $logo_id, 'full' );
			if ( $logo_url ) {
				$schema['logo'] = [
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				];
			}
		}

		$same_as = $this->get_same_as_urls();
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		$contact = $this->get_contact_point();
		if ( ! empty( $contact ) ) {
			$schema['contactPoint'] = $contact;
		}

		$founding = get_option( 'wpmind_brand_founding_date', '' );
		if ( ! empty( $founding ) ) {
			$schema['foundingDate'] = $founding;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n<!-- WPMind Brand Entity -->\n";
		echo '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output WebSite schema with SearchAction on the front page.
	 */
	public function output_website_schema(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$name = get_option( 'wpmind_brand_name', '' );
		if ( empty( $name ) ) {
			$name = get_bloginfo( 'name' );
		}

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => $name,
			'url'      => home_url( '/' ),
		];

		$description = get_bloginfo( 'description' );
		if ( ! empty( $description ) ) {
			$schema['description'] = $description;
		}

		$schema['potentialAction'] = [
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		];

		$lang = get_locale();
		if ( ! empty( $lang ) ) {
			$schema['inLanguage'] = $lang;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n<!-- WPMind WebSite Schema -->\n";
		echo '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Aggregate all sameAs URLs from social profiles and knowledge graph links.
	 *
	 * @return array List of validated URLs.
	 */
	private function get_same_as_urls(): array {
		$profiles = [
			'wpmind_brand_social_facebook',
			'wpmind_brand_social_twitter',
			'wpmind_brand_social_linkedin',
			'wpmind_brand_social_youtube',
			'wpmind_brand_social_github',
			'wpmind_brand_social_weibo',
			'wpmind_brand_social_zhihu',
			'wpmind_brand_social_wechat',
		];

		$urls = [];
		foreach ( $profiles as $key ) {
			$val = get_option( $key, '' );
			if ( ! empty( $val ) && filter_var( $val, FILTER_VALIDATE_URL ) ) {
				$urls[] = $val;
			}
		}

		$wikidata = get_option( 'wpmind_brand_wikidata_url', '' );
		if ( ! empty( $wikidata ) ) {
			$urls[] = $wikidata;
		}

		$wikipedia = get_option( 'wpmind_brand_wikipedia_url', '' );
		if ( ! empty( $wikipedia ) ) {
			$urls[] = $wikipedia;
		}

		return $urls;
	}

	/**
	 * Build ContactPoint schema from saved settings.
	 *
	 * @return array ContactPoint data or empty array.
	 */
	private function get_contact_point(): array {
		$email = get_option( 'wpmind_brand_contact_email', '' );
		$phone = get_option( 'wpmind_brand_contact_phone', '' );

		if ( empty( $email ) && empty( $phone ) ) {
			return [];
		}

		$contact = [ '@type' => 'ContactPoint' ];
		if ( ! empty( $email ) ) {
			$contact['email'] = $email;
		}
		if ( ! empty( $phone ) ) {
			$contact['telephone'] = $phone;
		}
		$contact['contactType'] = 'customer service';

		return $contact;
	}
}
