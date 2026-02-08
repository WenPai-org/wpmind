<?php
/**
 * AI Summary Manager
 *
 * Provides per-post AI context summaries for better AI citation control.
 *
 * @package WPMind\Modules\Geo
 * @since 3.10.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class AiSummaryManager
 *
 * Adds an "AI Summary" field to the post editor and outputs it
 * as a meta tag and Schema.org abstract for AI crawlers.
 */
class AiSummaryManager {

	/**
	 * Post meta key.
	 */
	private const META_SUMMARY = '_wpmind_ai_summary';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'wpmind_ai_summary_metabox';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', [ $this, 'output_meta_tag' ], 3 );
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ] );
		add_filter( 'wpmind_article_schema', [ $this, 'enrich_schema' ], 10, 2 );
	}

	/**
	 * Output AI summary meta tag in wp_head.
	 */
	public function output_meta_tag(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$summary = $this->get_summary( $post->ID );
		if ( empty( $summary ) ) {
			return;
		}

		echo '<meta name="ai-summary" content="' . esc_attr( $summary ) . '">' . "\n";
	}

	/**
	 * Enrich Article schema with abstract property.
	 *
	 * @param array    $schema Schema data.
	 * @param \WP_Post $post   Post object.
	 * @return array Modified schema.
	 */
	public function enrich_schema( array $schema, \WP_Post $post ): array {
		$summary = $this->get_summary( $post->ID );
		if ( ! empty( $summary ) ) {
			$schema['abstract'] = $summary;
		}
		return $schema;
	}

	/**
	 * Get AI summary for a post.
	 *
	 * Falls back to excerpt if no custom summary is set.
	 *
	 * @param int $post_id Post ID.
	 * @return string Summary text.
	 */
	public function get_summary( int $post_id ): string {
		$summary = get_post_meta( $post_id, self::META_SUMMARY, true );

		if ( ! empty( $summary ) && is_string( $summary ) ) {
			return $summary;
		}

		// Fallback to excerpt.
		$fallback = get_option( 'wpmind_ai_summary_fallback', 'excerpt' );
		if ( 'excerpt' === $fallback ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$excerpt = $post->post_excerpt;
				if ( ! empty( $excerpt ) ) {
					return wp_strip_all_tags( $excerpt );
				}
			}
		}

		return '';
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
				'wpmind-ai-summary',
				__( 'AI 摘要', 'wpmind' ),
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
		wp_nonce_field( self::NONCE_ACTION, '_wpmind_ai_summary_nonce' );

		$summary = get_post_meta( $post->ID, self::META_SUMMARY, true );
		if ( ! is_string( $summary ) ) {
			$summary = '';
		}
		?>
		<div class="wpmind-ai-summary-metabox">
			<p class="description" style="margin-bottom:8px;">
				<?php esc_html_e( '控制 AI 如何描述这篇文章。留空则使用摘要。', 'wpmind' ); ?>
			</p>
			<textarea name="wpmind_ai_summary" rows="4" style="width:100%;"
				placeholder="<?php esc_attr_e( '为 AI 爬虫提供简洁的内容摘要...', 'wpmind' ); ?>"
			><?php echo esc_textarea( $summary ); ?></textarea>
			<p class="description">
				<?php
				printf(
					/* translators: %d: character count */
					esc_html__( '建议 50-160 字符。当前：%d 字符', 'wpmind' ),
					mb_strlen( $summary )
				);
				?>
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
		if ( ! isset( $_POST['_wpmind_ai_summary_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpmind_ai_summary_nonce'] ) ),
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

		$summary = isset( $_POST['wpmind_ai_summary'] )
			? sanitize_textarea_field( wp_unslash( $_POST['wpmind_ai_summary'] ) )
			: '';

		update_post_meta( $post_id, self::META_SUMMARY, $summary );
	}
}
