<?php
/**
 * Alt Text Generator
 *
 * Automatically generates alt text, title, and caption for uploaded images
 * using the Vision API.
 *
 * @package WPMind\Modules\MediaIntelligence
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\MediaIntelligence;

/**
 * Class AltTextGenerator
 *
 * Core component for AI-powered image alt text generation.
 */
class AltTextGenerator {

	/**
	 * Constructor — optionally register WordPress hooks.
	 *
	 * @param bool $register_hooks When false, use as a plain helper (workflow dry-run).
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		// Schedule async generation on upload (priority 20, after core processing).
		add_action( 'add_attachment', [ $this, 'schedule_generation' ], 20 );

		// Schedule async generation after WPSSS offloads an attachment to cloud storage.
		add_action( 'wpsss_attachment_offloaded', [ $this, 'on_attachment_offloaded' ], 10, 2 );

		// WP-Cron single event handler.
		add_action( 'wpmind_generate_alt_text', [ $this, 'process_single' ] );

		// Add "Regenerate" field in media edit screen and delegated click listener (no inline onclick).
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_regenerate_field' ], 10, 2 );
		add_action( 'admin_print_footer_scripts', [ $this, 'print_regenerate_script' ] );
	}

	/**
	 * Generate proposed alt text without writing attachment meta / title / caption.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|\WP_Error Proposed alt text or error.
	 */
	public function generate_proposed_alt( int $attachment_id ) {
		$mocked = apply_filters( 'wpmind_alt_proposed_override', null, $attachment_id );
		if ( is_string( $mocked ) ) {
			$mocked = sanitize_text_field( trim( $mocked ) );
			return '' !== $mocked ? $mocked : new \WP_Error( 'wpmind_alt_empty', __( '生成结果为空', 'wpmind' ) );
		}
		if ( is_wp_error( $mocked ) ) {
			return $mocked;
		}

		if ( ! function_exists( 'wpmind_vision' ) ) {
			return new \WP_Error( 'wpmind_vision_missing', __( 'Vision API 不可用', 'wpmind' ) );
		}

		$image_input = $this->resolve_vision_image( $attachment_id );
		if ( ! $image_input ) {
			return new \WP_Error( 'wpmind_alt_no_url', __( '无法获取图片地址', 'wpmind' ) );
		}

		$lang = $this->get_language_label();

		$result = wpmind_vision(
			$image_input,
			sprintf(
				'为这张图片生成简洁的 alt text（%s），直接输出文本，不要引号或前缀。',
				$lang
			),
			[
				'max_tokens'  => 100,
				'temperature' => 0.3,
				'context'     => 'media_alt_text_preview',
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$alt_text = sanitize_text_field( trim( $result['content'] ?? '' ) );
		if ( empty( $alt_text ) ) {
			return new \WP_Error( 'wpmind_alt_empty', __( '生成结果为空', 'wpmind' ) );
		}

		return $alt_text;
	}

	/**
	 * Schedule async alt text generation for a newly uploaded image.
	 *
	 * Does not block the upload flow. Skips if alt text already exists.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function schedule_generation( int $attachment_id ): void {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// Respect manually-set alt text.
		$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $existing_alt ) ) {
			return;
		}

		if ( wp_next_scheduled( 'wpmind_generate_alt_text', [ $attachment_id ] ) ) {
			return;
		}

		wp_schedule_single_event( time(), 'wpmind_generate_alt_text', [ $attachment_id ] );
	}

	/**
	 * Handle WPSSS attachment offloaded event: schedule async alt generation when alt is missing.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $url           Offloaded cloud storage URL.
	 */
	public function on_attachment_offloaded( int $attachment_id, string $url = '' ): void {
		if ( '' !== trim( $url ) ) {
			set_transient( 'wpmind_offloaded_url_' . $attachment_id, esc_url_raw( $url ), HOUR_IN_SECONDS );
		}

		$this->schedule_generation( $attachment_id );
	}

	/**
	 * Process a single attachment: generate alt text (and optionally title/caption).
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function process_single( int $attachment_id ): void {
		if ( ! function_exists( 'wpmind_vision' ) ) {
			return;
		}

		$image_input = $this->resolve_vision_image( $attachment_id );
		if ( ! $image_input ) {
			return;
		}

		$lang = $this->get_language_label();

		$result = wpmind_vision(
			$image_input,
			sprintf(
				'为这张图片生成简洁的 alt text（%s），直接输出文本，不要引号或前缀。',
				$lang
			),
			[
				'max_tokens'  => 100,
				'temperature' => 0.3,
				'context'     => 'media_alt_text',
			]
		);

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_media_error', 'alt_text', $attachment_id, $result );
			return;
		}

		$alt_text = sanitize_text_field( trim( $result['content'] ?? '' ) );
		if ( empty( $alt_text ) ) {
			return;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		// Also generate title and caption if enabled.
		if ( get_option( 'wpmind_media_auto_title', '1' ) === '1' ) {
			$this->generate_title_and_caption( $attachment_id, $image_input );
		}

		// Track statistics.
		$this->increment_stat( 'generated' );
		do_action( 'wpmind_media_generated', 'alt_text', $attachment_id );
	}

	/**
	 * Generate title and caption for an attachment via Vision API.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $image_url     Image URL.
	 */
	private function generate_title_and_caption( int $attachment_id, string $image_url ): void {
		$result = wpmind_vision(
			$image_url,
			'为这张图片生成：1) 简短标题（10字以内）2) 一句话描述。JSON格式：{"title":"...","caption":"..."}',
			[
				'max_tokens'  => 200,
				'temperature' => 0.3,
				'json_mode'   => true,
				'context'     => 'media_title_caption',
			]
		);

		if ( is_wp_error( $result ) ) {
			return;
		}

		$data = json_decode( $result['content'] ?? '', true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$update = [];
		if ( ! empty( $data['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( ! empty( $data['caption'] ) ) {
			$update['post_excerpt'] = sanitize_text_field( $data['caption'] );
		}
		if ( ! empty( $update ) ) {
			$update['ID'] = $attachment_id;
			wp_update_post( $update );
		}
	}

	/**
	 * Add a "Regenerate with AI" field to the media edit screen.
	 *
	 * @param array    $form_fields Existing form fields.
	 * @param \WP_Post $post        Attachment post object.
	 * @return array Modified form fields.
	 */
	public function add_regenerate_field( array $form_fields, \WP_Post $post ): array {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}

		$nonce = wp_create_nonce( 'wpmind_ajax' );
		$attrs = [
			'data-id'      => (string) $post->ID,
			'data-nonce'   => $nonce,
			'data-loading' => __( '生成中...', 'wpmind' ),
			'data-done'    => __( '已生成', 'wpmind' ),
			'data-fail'    => __( '失败', 'wpmind' ),
		];

		if ( class_exists( 'Wenpai_Admin_UI' ) ) {
			$html = \Wenpai_Admin_UI::native_metabox_html(
				[
					'actions' => [
						[
							'label'   => __( '重新生成', 'wpmind' ),
							'variant' => 'secondary',
							'size'    => 'sm',
							'class'   => 'wpmind-regenerate-alt',
							'attrs'   => $attrs,
						],
					],
				]
			);
		} else {
			$html = sprintf(
				'<div class="wpmind-native-field"><button type="button" class="components-button is-secondary is-small wpmind-regenerate-alt" data-id="%d" data-nonce="%s" data-loading="%s" data-done="%s" data-fail="%s">%s</button></div>',
				$post->ID,
				esc_attr( $nonce ),
				esc_attr__( '生成中...', 'wpmind' ),
				esc_attr__( '已生成', 'wpmind' ),
				esc_attr__( '失败', 'wpmind' ),
				esc_html__( '重新生成', 'wpmind' )
			);
		}

		$form_fields['wpmind_regenerate'] = [
			'label' => __( 'AI Alt Text', 'wpmind' ),
			'input' => 'html',
			'html'  => $html,
		];

		return $form_fields;
	}

	/**
	 * Print delegated click handler for the media regenerate button (no inline onclick).
	 */
	public function print_regenerate_script(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->base, [ 'upload', 'post', 'media' ], true ) ) {
			return;
		}
		?>
		<script>
		(function () {
			if (window.__wpmindMediaRegenerateBound) { return; }
			window.__wpmindMediaRegenerateBound = true;
			document.addEventListener('click', function (event) {
				var btn = event.target && event.target.closest ? event.target.closest('.wpmind-regenerate-alt') : null;
				if (!btn || btn.disabled || typeof ajaxurl === 'undefined') { return; }
				btn.disabled = true;
				var origLabel = btn.textContent;
				btn.textContent = btn.getAttribute('data-loading') || origLabel;
				var body = new URLSearchParams();
				body.append('action', 'wpmind_media_regenerate');
				body.append('nonce', btn.getAttribute('data-nonce') || '');
				body.append('attachment_id', btn.getAttribute('data-id') || '');
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (r) {
						if (r && r.success && r.data && r.data.alt_text) {
							var field = document.getElementById('attachments-' + btn.getAttribute('data-id') + '-alt');
							if (field) { field.value = r.data.alt_text; }
							btn.textContent = btn.getAttribute('data-done') || origLabel;
						} else {
							btn.textContent = btn.getAttribute('data-fail') || origLabel;
						}
					})
					.catch(function () {
						btn.textContent = btn.getAttribute('data-fail') || origLabel;
					})
					.finally(function () {
						btn.disabled = false;
					});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Resolve image input for Vision API: prefer base64 data URI for local readable files,
	 * falling back to offloaded or optimized attachment URL.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Data URI or image URL, or null if unavailable.
	 */
	public function resolve_vision_image( int $attachment_id ): ?string {
		if ( class_exists( \WPMind\API\Services\VisionHelper::class ) ) {
			$data_uri = \WPMind\API\Services\VisionHelper::attachment_to_data_uri( $attachment_id );
			if ( is_string( $data_uri ) && '' !== $data_uri ) {
				return $data_uri;
			}
		}

		$offloaded_url = get_transient( 'wpmind_offloaded_url_' . $attachment_id );
		if ( is_string( $offloaded_url ) && '' !== $offloaded_url ) {
			return $offloaded_url;
		}

		return $this->get_optimized_url( $attachment_id );
	}

	/**
	 * Get an optimized (medium-size) image URL to reduce token consumption.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Image URL or null.
	 */
	private function get_optimized_url( int $attachment_id ): ?string {
		$medium = wp_get_attachment_image_src( $attachment_id, 'medium' );
		if ( $medium ) {
			return $medium[0];
		}

		$url = wp_get_attachment_url( $attachment_id );
		return $url ?: null;
	}

	/**
	 * Get the language label for prompts based on saved setting or site locale.
	 *
	 * @return string Language label (e.g. '中文' or 'English').
	 */
	private function get_language_label(): string {
		$setting = get_option( 'wpmind_media_language', 'auto' );
		if ( 'zh' === $setting ) {
			return '中文';
		}
		if ( 'en' === $setting ) {
			return 'English';
		}
		// auto: detect from site locale.
		$locale = get_locale();
		return str_starts_with( $locale, 'zh' ) ? '中文' : 'English';
	}

	/**
	 * Increment a media intelligence statistic counter.
	 *
	 * @param string $key Stat key (e.g. 'generated').
	 */
	private function increment_stat( string $key ): void {
		$stats = get_option( 'wpmind_media_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}
		$stats[ $key ]       = ( $stats[ $key ] ?? 0 ) + 1;
		$month_key           = 'month_' . gmdate( 'Y_m' );
		$stats[ $month_key ] = ( $stats[ $month_key ] ?? 0 ) + 1;
		update_option( 'wpmind_media_stats', $stats, false );
	}
}
