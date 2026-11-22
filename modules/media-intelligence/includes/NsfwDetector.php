<?php
/**
 * NSFW Detector
 *
 * Checks uploaded images for inappropriate content using the Vision API.
 *
 * @package WPMind\Modules\MediaIntelligence
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\MediaIntelligence;

/**
 * Class NsfwDetector
 *
 * Flags uploaded images that contain NSFW content.
 */
class NsfwDetector {

	/**
	 * Constructor — register async WordPress hooks.
	 *
	 * @param bool $register_hooks When false, skip hook registration (tests/manual calls).
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		// Schedule async check on upload so uploads are never blocked by upstream Vision API calls.
		add_action( 'add_attachment', [ $this, 'schedule_check' ], 15 );

		// WP-Cron single event handler.
		add_action( 'wpmind_check_nsfw_attachment', [ $this, 'check_attachment' ] );
	}

	/**
	 * Schedule async NSFW detection for an uploaded image attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function schedule_check( int $attachment_id ): void {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		if ( wp_next_scheduled( 'wpmind_check_nsfw_attachment', [ $attachment_id ] ) ) {
			return;
		}

		wp_schedule_single_event( time(), 'wpmind_check_nsfw_attachment', [ $attachment_id ] );
	}

	/**
	 * Check an uploaded attachment for NSFW content.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function check_attachment( int $attachment_id ): void {
		if ( ! function_exists( 'wpmind_vision' ) ) {
			return;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$image_input = $this->resolve_vision_image( $attachment_id );
		if ( ! $image_input ) {
			return;
		}

		$result = wpmind_vision(
			$image_input,
			'Is this image NSFW? Reply only "safe" or "nsfw".',
			[
				'max_tokens'  => 10,
				'temperature' => 0.1,
				'context'     => 'media_nsfw_check',
			]
		);

		if ( is_wp_error( $result ) ) {
			return;
		}

		$verdict = strtolower( trim( $result['content'] ?? '' ) );
		if ( str_contains( $verdict, 'nsfw' ) ) {
			update_post_meta( $attachment_id, '_wpmind_nsfw_flag', '1' );

			/**
			 * Fires when NSFW content is detected in an uploaded image.
			 *
			 * @param int $attachment_id The flagged attachment ID.
			 */
			do_action( 'wpmind_nsfw_detected', $attachment_id );
		}
	}

	/**
	 * Resolve image input for Vision API: prefer base64 data URI for local readable files.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Data URI or image URL, or null if unavailable.
	 */
	private function resolve_vision_image( int $attachment_id ): ?string {
		if ( class_exists( \WPMind\API\Services\VisionHelper::class ) ) {
			$data_uri = \WPMind\API\Services\VisionHelper::attachment_to_data_uri( $attachment_id );
			if ( is_string( $data_uri ) && '' !== $data_uri ) {
				return $data_uri;
			}
		}

		$url = wp_get_attachment_url( $attachment_id );
		return $url ?: null;
	}
}
