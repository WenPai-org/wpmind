<?php
/**
 * AI Indexing Manager
 *
 * Controls AI crawler indexing directives and content declarations.
 *
 * @package WPMind\Modules\Geo
 * @since 3.9.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class AiIndexingManager
 *
 * Manages noai/nollm meta robots, ai-content-declaration meta,
 * X-Robots-Tag HTTP headers, and per-post metabox overrides.
 */
class AiIndexingManager {

	/**
	 * Content declaration constants.
	 */
	public const DECLARATION_ORIGINAL     = 'original';
	public const DECLARATION_AI_ASSISTED  = 'ai-assisted';
	public const DECLARATION_AI_GENERATED = 'ai-generated';

	/**
	 * Valid declarations.
	 */
	private const VALID_DECLARATIONS = [
		self::DECLARATION_ORIGINAL,
		self::DECLARATION_AI_ASSISTED,
		self::DECLARATION_AI_GENERATED,
	];

	/**
	 * Post meta keys.
	 */
	private const META_NOINDEX     = '_wpmind_ai_noindex';
	private const META_NOLLM       = '_wpmind_ai_nollm';
	private const META_DECLARATION = '_wpmind_ai_declaration';

	/**
	 * Nonce action for metabox.
	 */
	private const NONCE_ACTION = 'wpmind_ai_indexing_metabox';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', [ $this, 'output_meta_tags' ], 2 );
		add_action( 'send_headers', [ $this, 'output_http_headers' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ] );
	}

	/**
	 * Output AI indexing meta tags in wp_head.
	 */
	public function output_meta_tags(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$excluded = $this->is_excluded( $post->ID );

		if ( $excluded ) {
			$directives = [];
			if ( $this->has_noai( $post->ID ) ) {
				$directives[] = 'noai';
			}
			if ( $this->has_nollm( $post->ID ) ) {
				$directives[] = 'nollm';
			}
			if ( ! empty( $directives ) ) {
				echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '">' . "\n";
			}
		}

		$declaration = $this->get_declaration( $post->ID );
		if ( ! empty( $declaration ) ) {
			echo '<meta name="ai-content-declaration" content="' . esc_attr( $declaration ) . '">' . "\n";
		}
	}

	/**
	 * Output X-Robots-Tag HTTP header.
	 */
	public function output_http_headers(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		if ( ! $this->is_excluded( $post_id ) ) {
			return;
		}

		$directives = [];
		if ( $this->has_noai( $post_id ) ) {
			$directives[] = 'noai';
		}
		if ( $this->has_nollm( $post_id ) ) {
			$directives[] = 'nollm';
		}

		if ( ! empty( $directives ) && ! headers_sent() ) {
			header( 'X-Robots-Tag: ' . implode( ', ', $directives ) );
		}
	}

	/**
	 * Register metabox on supported post types.
	 */
	public function register_metabox(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'wpmind-ai-indexing',
				__( 'AI 索引指令', 'wpmind' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the metabox UI.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, '_wpmind_ai_indexing_nonce' );

		$noindex     = get_post_meta( $post->ID, self::META_NOINDEX, true ) === '1';
		$nollm       = get_post_meta( $post->ID, self::META_NOLLM, true ) === '1';
		$declaration = get_post_meta( $post->ID, self::META_DECLARATION, true );

		// Check if globally excluded by post type.
		$excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
		if ( ! is_array( $excluded_types ) ) {
			$excluded_types = [];
		}
		$globally_excluded = in_array( get_post_type( $post ), $excluded_types, true );
		?>
		<div class="wpmind-ai-indexing-metabox">
			<?php if ( $globally_excluded ) : ?>
			<p class="description" style="color:#d63638;margin-bottom:8px;">
				<?php esc_html_e( '此内容类型已被全局排除 AI 索引。', 'wpmind' ); ?>
			</p>
			<?php endif; ?>

			<p>
				<label>
					<input type="checkbox" name="wpmind_ai_noindex" value="1" <?php checked( $noindex ); ?>>
					<?php esc_html_e( '排除 AI 索引 (noai)', 'wpmind' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="wpmind_ai_nollm" value="1" <?php checked( $nollm ); ?>>
					<?php esc_html_e( '排除 LLM 训练 (nollm)', 'wpmind' ); ?>
				</label>
			</p>
			<p>
				<label for="wpmind_ai_declaration"><?php esc_html_e( '内容声明：', 'wpmind' ); ?></label>
				<select name="wpmind_ai_declaration" id="wpmind_ai_declaration" style="width:100%;margin-top:4px;">
					<option value="" <?php selected( $declaration, '' ); ?>><?php esc_html_e( '使用全局默认', 'wpmind' ); ?></option>
					<option value="original" <?php selected( $declaration, 'original' ); ?>><?php esc_html_e( '原创内容', 'wpmind' ); ?></option>
					<option value="ai-assisted" <?php selected( $declaration, 'ai-assisted' ); ?>><?php esc_html_e( 'AI 辅助创作', 'wpmind' ); ?></option>
					<option value="ai-generated" <?php selected( $declaration, 'ai-generated' ); ?>><?php esc_html_e( 'AI 生成内容', 'wpmind' ); ?></option>
				</select>
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
		if ( ! isset( $_POST['_wpmind_ai_indexing_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpmind_ai_indexing_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save noai.
		$noindex = isset( $_POST['wpmind_ai_noindex'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_NOINDEX, $noindex );

		// Save nollm.
		$nollm = isset( $_POST['wpmind_ai_nollm'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_NOLLM, $nollm );

		// Save declaration.
		$declaration = isset( $_POST['wpmind_ai_declaration'] )
			? sanitize_key( wp_unslash( $_POST['wpmind_ai_declaration'] ) )
			: '';

		if ( '' !== $declaration && ! in_array( $declaration, self::VALID_DECLARATIONS, true ) ) {
			$declaration = '';
		}

		update_post_meta( $post_id, self::META_DECLARATION, $declaration );
	}

	/**
	 * Check if a post is excluded from AI indexing.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if excluded.
	 */
	public function is_excluded( int $post_id ): bool {
		// Check per-post meta.
		if ( get_post_meta( $post_id, self::META_NOINDEX, true ) === '1' ) {
			return true;
		}
		if ( get_post_meta( $post_id, self::META_NOLLM, true ) === '1' ) {
			return true;
		}

		// Check global post type exclusion.
		$excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
		if ( ! is_array( $excluded_types ) ) {
			$excluded_types = [];
		}

		$post_type = get_post_type( $post_id );
		if ( $post_type && in_array( $post_type, $excluded_types, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the content declaration for a post.
	 *
	 * Per-post override takes precedence over global default.
	 *
	 * @param int $post_id Post ID.
	 * @return string Declaration value.
	 */
	public function get_declaration( int $post_id ): string {
		// Check per-post override.
		$declaration = get_post_meta( $post_id, self::META_DECLARATION, true );

		if ( ! empty( $declaration ) && in_array( $declaration, self::VALID_DECLARATIONS, true ) ) {
			return $declaration;
		}

		// Fall back to global default.
		return get_option( 'wpmind_ai_default_declaration', self::DECLARATION_ORIGINAL );
	}

	/**
	 * Check if post has noai directive.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if noai.
	 */
	private function has_noai( int $post_id ): bool {
		// Per-post meta.
		if ( get_post_meta( $post_id, self::META_NOINDEX, true ) === '1' ) {
			return true;
		}

		// Global post type exclusion implies both noai and nollm.
		$excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
		if ( ! is_array( $excluded_types ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		return $post_type && in_array( $post_type, $excluded_types, true );
	}

	/**
	 * Check if post has nollm directive.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if nollm.
	 */
	private function has_nollm( int $post_id ): bool {
		// Per-post meta.
		if ( get_post_meta( $post_id, self::META_NOLLM, true ) === '1' ) {
			return true;
		}

		// Global post type exclusion implies both noai and nollm.
		$excluded_types = get_option( 'wpmind_ai_excluded_post_types', [] );
		if ( ! is_array( $excluded_types ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		return $post_type && in_array( $post_type, $excluded_types, true );
	}
}
