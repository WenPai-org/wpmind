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
	 * Constructor — register WordPress hooks.
	 */
	public function __construct() {
		// Schedule async generation on upload (priority 20, after core processing).
		add_action( 'add_attachment', [ $this, 'schedule_generation' ], 20 );

		// WP-Cron single event handler.
		add_action( 'wpmind_generate_alt_text', [ $this, 'process_single' ] );

		// Add "Regenerate" field in media edit screen.
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_regenerate_field' ], 10, 2 );
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

		wp_schedule_single_event( time(), 'wpmind_generate_alt_text', [ $attachment_id ] );
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

		$image_url = $this->get_optimized_url( $attachment_id );
		if ( ! $image_url ) {
			return;
		}

		$lang = $this->get_language_label();

		$result = wpmind_vision( $image_url, sprintf(
			'为这张图片生成简洁的 alt text（%s），直接输出文本，不要引号或前缀。',
			$lang
		), [
			'max_tokens'  => 100,
			'temperature' => 0.3,
			'context'     => 'media_alt_text',
		] );

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
			$this->generate_title_and_caption( $attachment_id, $image_url );
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
		$result = wpmind_vision( $image_url, '为这张图片生成：1) 简短标题（10字以内）2) 一句话描述。JSON格式：{"title":"...","caption":"..."}', [
			'max_tokens'  => 200,
			'temperature' => 0.3,
			'json_mode'   => true,
			'context'     => 'media_title_caption',
		] );

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
		$form_fields['wpmind_regenerate'] = [
			'label' => __( 'AI Alt Text', 'wpmind' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button button-small wpmind-regenerate-alt" data-id="%d" data-nonce="%s" onclick="(function(btn){if(btn.disabled)return;btn.disabled=true;btn.textContent=\'%s\';jQuery.post(ajaxurl,{action:\'wpmind_media_regenerate\',nonce:btn.dataset.nonce,attachment_id:btn.dataset.id},function(r){if(r.success){var f=jQuery(\'#attachments-\'+btn.dataset.id+\'-alt\');if(f.length)f.val(r.data.alt_text);btn.textContent=\'%s\';}else{btn.textContent=\'%s\';}btn.disabled=false;});})(this)">%s</button>',
				$post->ID,
				esc_attr( $nonce ),
				esc_js( __( '生成中...', 'wpmind' ) ),
				esc_js( __( '已生成', 'wpmind' ) ),
				esc_js( __( '失败', 'wpmind' ) ),
				esc_html__( '重新生成', 'wpmind' )
			),
		];

		return $form_fields;
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
		$stats[ $key ] = ( $stats[ $key ] ?? 0 ) + 1;
		$month_key     = 'month_' . gmdate( 'Y_m' );
		$stats[ $month_key ] = ( $stats[ $month_key ] ?? 0 ) + 1;
		update_option( 'wpmind_media_stats', $stats, false );
	}
}
