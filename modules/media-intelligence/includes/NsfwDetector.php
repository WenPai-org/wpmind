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
	 * Constructor — register WordPress hooks.
	 */
	public function __construct() {
		// Check on upload (priority 15, before AltTextGenerator at 20).
		add_action( 'add_attachment', [ $this, 'check_attachment' ], 15 );
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

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return;
		}

		$result = wpmind_vision( $image_url, 'Is this image NSFW? Reply only "safe" or "nsfw".', [
			'max_tokens'  => 10,
			'temperature' => 0.1,
			'context'     => 'media_nsfw_check',
		] );

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
}
