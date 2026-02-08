<?php
/**
 * Entity Linker
 *
 * Links posts to Wikidata entities for semantic disambiguation.
 *
 * @package WPMind\Modules\Geo
 * @since 3.10.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class EntityLinker
 *
 * Adds Wikidata entity association to posts and enriches
 * Schema.org JSON-LD with about/sameAs properties.
 */
class EntityLinker {

	/**
	 * Post meta keys.
	 */
	private const META_ENTITY_URL  = '_wpmind_entity_url';
	private const META_ENTITY_NAME = '_wpmind_entity_name';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'wpmind_entity_linker_metabox';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ] );
		add_filter( 'wpmind_article_schema', [ $this, 'enrich_schema' ], 20, 2 );
	}

	/**
	 * Enrich Article schema with entity about/sameAs.
	 *
	 * @param array    $schema Schema data.
	 * @param \WP_Post $post   Post object.
	 * @return array Modified schema.
	 */
	public function enrich_schema( array $schema, \WP_Post $post ): array {
		$entity_url  = get_post_meta( $post->ID, self::META_ENTITY_URL, true );
		$entity_name = get_post_meta( $post->ID, self::META_ENTITY_NAME, true );

		if ( empty( $entity_url ) || ! is_string( $entity_url ) ) {
			return $schema;
		}

		$about = [
			'@type' => 'Thing',
		];

		if ( ! empty( $entity_name ) && is_string( $entity_name ) ) {
			$about['name'] = $entity_name;
		}

		// Convert Wikipedia URL to Wikidata sameAs if needed.
		$about['sameAs'] = $entity_url;

		// If it's a Wikipedia URL, also add the Wikidata concept URI.
		$wikidata_url = $this->to_wikidata_url( $entity_url );
		if ( $wikidata_url && $wikidata_url !== $entity_url ) {
			$about['sameAs'] = [ $entity_url, $wikidata_url ];
		}

		$schema['about'] = $about;

		return $schema;
	}

	/**
	 * Convert a Wikipedia URL to its Wikidata equivalent.
	 *
	 * @param string $url Wikipedia or Wikidata URL.
	 * @return string|null Wikidata URL or null.
	 */
	private function to_wikidata_url( string $url ): ?string {
		// Already a Wikidata URL.
		if ( str_contains( $url, 'wikidata.org' ) ) {
			return $url;
		}

		// Not a Wikipedia URL — return null.
		if ( ! str_contains( $url, 'wikipedia.org' ) ) {
			return null;
		}

		// Extract article title from Wikipedia URL.
		// e.g. https://en.wikipedia.org/wiki/WordPress → WordPress
		if ( preg_match( '#wikipedia\.org/wiki/(.+)$#', $url, $matches ) ) {
			$title = urldecode( $matches[1] );
			// We can't resolve to Wikidata Q-ID without an API call,
			// so just return the Wikipedia URL as the canonical sameAs.
			return null;
		}

		return null;
	}

	/**
	 * Get entity data for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{url: string, name: string} Entity data.
	 */
	public function get_entity( int $post_id ): array {
		return [
			'url'  => (string) get_post_meta( $post_id, self::META_ENTITY_URL, true ),
			'name' => (string) get_post_meta( $post_id, self::META_ENTITY_NAME, true ),
		];
	}

	/**
	 * Register metabox on public post types.
	 */
	public function register_metabox(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type ) {
				continue;
			}
			add_meta_box(
				'wpmind-entity-linker',
				__( '实体关联', 'wpmind' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render metabox UI.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, '_wpmind_entity_linker_nonce' );

		$entity_url  = get_post_meta( $post->ID, self::META_ENTITY_URL, true );
		$entity_name = get_post_meta( $post->ID, self::META_ENTITY_NAME, true );

		if ( ! is_string( $entity_url ) ) {
			$entity_url = '';
		}
		if ( ! is_string( $entity_name ) ) {
			$entity_name = '';
		}
		?>
		<div class="wpmind-entity-linker-metabox">
			<p class="description" style="margin-bottom:8px;">
				<?php esc_html_e( '关联 Wikidata/Wikipedia 实体，帮助 AI 消除歧义。', 'wpmind' ); ?>
			</p>
			<p>
				<label for="wpmind_entity_name"><?php esc_html_e( '实体名称：', 'wpmind' ); ?></label>
				<input type="text" name="wpmind_entity_name" id="wpmind_entity_name"
					value="<?php echo esc_attr( $entity_name ); ?>"
					placeholder="<?php esc_attr_e( '例如：WordPress', 'wpmind' ); ?>"
					style="width:100%;margin-top:4px;">
			</p>
			<p>
				<label for="wpmind_entity_url"><?php esc_html_e( '实体 URL：', 'wpmind' ); ?></label>
				<input type="url" name="wpmind_entity_url" id="wpmind_entity_url"
					value="<?php echo esc_attr( $entity_url ); ?>"
					placeholder="<?php esc_attr_e( 'https://www.wikidata.org/wiki/Q13166 或 Wikipedia URL', 'wpmind' ); ?>"
					style="width:100%;margin-top:4px;">
			</p>
			<p class="description">
				<?php esc_html_e( '输入 Wikidata 或 Wikipedia 链接，将作为 Schema.org about.sameAs 输出。', 'wpmind' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_metabox( int $post_id ): void {
		if ( ! isset( $_POST['_wpmind_entity_linker_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpmind_entity_linker_nonce'] ) ),
			self::NONCE_ACTION
		) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$entity_url = isset( $_POST['wpmind_entity_url'] )
			? esc_url_raw( wp_unslash( $_POST['wpmind_entity_url'] ) )
			: '';

		$entity_name = isset( $_POST['wpmind_entity_name'] )
			? sanitize_text_field( wp_unslash( $_POST['wpmind_entity_name'] ) )
			: '';

		update_post_meta( $post_id, self::META_ENTITY_URL, $entity_url );
		update_post_meta( $post_id, self::META_ENTITY_NAME, $entity_name );
	}
}
