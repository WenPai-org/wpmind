<?php
/**
 * Markdown Feed (Standalone Mode)
 *
 * Provides standalone Markdown feed when official AI Experiments plugin is not installed.
 *
 * @package WPMind\Modules\Geo
 * @since 3.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class MarkdownFeed
 *
 * Mode B: Standalone Markdown feed implementation.
 * Only activates when official plugin is not installed and user enables it.
 *
 * @since 3.1.0 Refactored to use unified MarkdownProcessor.
 */
class MarkdownFeed {

	/**
	 * HTML to Markdown converter.
	 *
	 * @var HtmlToMarkdown
	 */
	private HtmlToMarkdown $converter;

	/**
	 * Unified Markdown processor.
	 *
	 * @var MarkdownProcessor
	 */
	private MarkdownProcessor $processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->converter = new HtmlToMarkdown();
		$this->processor = new MarkdownProcessor();

		// Use init action with early priority to ensure feed registration.
		add_action( 'init', array( $this, 'maybe_init' ), 1 );
	}

	/**
	 * Initialize if standalone mode should be active.
	 */
	public function maybe_init(): void {
		// Don't activate if official plugin is handling this.
		if ( class_exists( 'AI_Experiments\\Experiments\\Markdown_Feeds' ) ) {
			return;
		}

		// Check if standalone mode is enabled.
		if ( ! get_option( 'wpmind_standalone_markdown_feed', false ) ) {
			return;
		}

		// Register feed - must happen during init.
		add_feed( 'markdown', array( $this, 'render_feed' ) );

		// Register rewrite rules for .md suffix.
		$this->register_rewrite_rules();

		// Handle Accept header content negotiation.
		add_action( 'parse_request', array( $this, 'handle_accept_header' ) );

		// Handle .md suffix rendering.
		add_action( 'template_redirect', array( $this, 'handle_md_template' ) );
	}

	/**
	 * Register rewrite rules for .md suffix.
	 *
	 * Codex review fix: Added proper rewrite rules.
	 */
	private function register_rewrite_rules(): void {
		// Register query var.
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'wpmind_markdown';
				return $vars;
			}
		);

		// Handle .md suffix via request filter (works with any permalink structure).
		add_filter( 'request', array( $this, 'handle_md_request' ) );
	}

	/**
	 * Handle .md suffix in request.
	 *
	 * @param array $query_vars The query variables.
	 * @return array Modified query variables.
	 */
	public function handle_md_request( array $query_vars ): array {
		// Check if request URI ends with .md.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( preg_match( '/\.md(\?.*)?$/', $request_uri ) ) {
			// Remove .md suffix to get the real path.
			$clean_uri = preg_replace( '/\.md(\?.*)?$/', '$1', $request_uri );

			// Parse the clean URI to get post.
			$post_id = url_to_postid( home_url( $clean_uri ) );

			if ( $post_id ) {
				$query_vars['p']               = $post_id;
				$query_vars['wpmind_markdown'] = 1;
				// Clear other query vars that might conflict.
				unset( $query_vars['error'], $query_vars['name'], $query_vars['category_name'] );
			}
		}

		return $query_vars;
	}

	/**
	 * Handle Accept header content negotiation.
	 *
	 * Codex review fix: Added Accept header support.
	 *
	 * @param \WP $wp The WordPress environment instance.
	 */
	public function handle_accept_header( \WP $wp ): void {
		// Check for Accept: text/markdown header.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		if ( false !== strpos( $accept, 'text/markdown' ) ) {
			// Check if this is a singular post request.
			if ( ! empty( $wp->query_vars['name'] ) || ! empty( $wp->query_vars['p'] ) ) {
				$wp->query_vars['wpmind_markdown'] = 1;
			}
		}
	}

	/**
	 * Handle .md template rendering.
	 *
	 * Codex review fix: Added template_redirect handler.
	 */
	public function handle_md_template(): void {
		if ( ! get_query_var( 'wpmind_markdown' ) ) {
			return;
		}

		// Get the current post.
		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Render as Markdown.
		$this->render_singular( $post );
		exit;
	}

	/**
	 * Render the markdown feed.
	 */
	public function render_feed(): void {
		// Set content type.
		header( 'Content-Type: text/markdown; charset=utf-8' );

		// Get posts.
		$posts = get_posts(
			array(
				'numberposts' => apply_filters( 'wpmind_markdown_feed_posts_count', 10 ),
				'post_status' => 'publish',
				'post_type'   => apply_filters( 'wpmind_markdown_feed_post_types', array( 'post' ) ),
			)
		);

		// Feed header.
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown feed served as text/markdown; HTML escaping would corrupt output.
		echo "# {$site_name} - Markdown Feed\n\n";
		echo "来源: {$site_url}\n";
		echo '生成时间: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
		echo "---\n\n";

		// Render each post.
		foreach ( $posts as $post ) {
			echo $this->post_to_markdown( $post );
			echo "\n\n---\n\n";
		}
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render a singular post as Markdown.
	 *
	 * @param \WP_Post $post The post to render.
	 */
	private function render_singular( \WP_Post $post ): void {
		header( 'Content-Type: text/markdown; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown feed served as text/markdown; HTML escaping would corrupt output.
		echo $this->post_to_markdown( $post );
	}

	/**
	 * Convert a post to Markdown.
	 *
	 * @param \WP_Post $post The post to convert.
	 * @return string Markdown content.
	 */
	private function post_to_markdown( \WP_Post $post ): string {
		// Setup post data.
		setup_postdata( $post );

		// Build sections (convert HTML to Markdown).
		$sections = array(
			'title'   => '# ' . esc_html( get_the_title( $post ) ) . "\n\n",
			'content' => $this->converter->convert( $post->post_content ),
		);

		// Use unified processor for GEO enhancements.
		$sections = $this->processor->process( $sections, $post );

		// Reset post data.
		wp_reset_postdata();

		/**
		 * Filter the markdown sections.
		 *
		 * @param array    $sections The content sections.
		 * @param \WP_Post $post     The post object.
		 */
		$sections = apply_filters( 'wpmind_markdown_post_sections', $sections, $post );

		// Combine sections (exclude internal markers).
		$output = '';
		foreach ( $sections as $key => $content ) {
			// Skip internal markers.
			if ( str_starts_with( $key, '_' ) ) {
				continue;
			}
			$output .= $content;
		}

		return $output;
	}

	/**
	 * Flush rewrite rules on activation.
	 */
	public static function activate(): void {
		// Register rules first.
		$instance = new self();
		$instance->register_rewrite_rules();

		// Flush rules.
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
