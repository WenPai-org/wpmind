<?php
/**
 * Media AJAX Controller
 *
 * Handles AJAX requests for the Media Intelligence module:
 * settings save, bulk scan/process, stats, and single regeneration.
 *
 * @package WPMind\Modules\MediaIntelligence
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\MediaIntelligence;

/**
 * Class MediaAjaxController
 *
 * Provides AJAX handlers for media intelligence operations.
 */
final class MediaAjaxController {

	/**
	 * Register AJAX hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpmind_save_media_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_wpmind_media_bulk_scan', [ $this, 'ajax_bulk_scan' ] );
		add_action( 'wp_ajax_wpmind_media_bulk_process', [ $this, 'ajax_bulk_process' ] );
		add_action( 'wp_ajax_wpmind_media_get_stats', [ $this, 'ajax_get_stats' ] );
		add_action( 'wp_ajax_wpmind_media_regenerate', [ $this, 'ajax_regenerate' ] );
	}

	/**
	 * Verify request security.
	 *
	 * @param bool $require_post Whether to enforce POST method.
	 */
	private function verify_request( bool $require_post = false ): void {
		if ( $require_post && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json_error( [ 'message' => 'Method not allowed' ], 405 );
		}
		check_ajax_referer( 'wpmind_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}
	}

	/**
	 * Save media intelligence settings (POST only).
	 */
	public function ajax_save_settings(): void {
		$this->verify_request( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify_request() above.
		$raw = wp_unslash( $_POST );

		$to_bool = function ( string $key ) use ( $raw ): string {
			return ( (string) ( $raw[ $key ] ?? '0' ) ) === '1' ? '1' : '0';
		};

		$options = [
			'wpmind_media_auto_alt'     => $to_bool( 'auto_alt' ),
			'wpmind_media_auto_title'   => $to_bool( 'auto_title' ),
			'wpmind_media_nsfw_enabled' => $to_bool( 'nsfw_enabled' ),
		];

		// Language setting: whitelist.
		$language = sanitize_key( $raw['language'] ?? 'auto' );
		if ( ! in_array( $language, [ 'auto', 'zh', 'en' ], true ) ) {
			$language = 'auto';
		}
		$options['wpmind_media_language'] = $language;

		foreach ( $options as $key => $value ) {
			update_option( $key, $value, false );
		}

		wp_send_json_success( [ 'message' => __( '设置已保存', 'wpmind' ) ] );
	}

	/**
	 * Scan for images missing alt text (GET allowed, read-only).
	 */
	public function ajax_bulk_scan(): void {
		$this->verify_request( false );

		global $wpdb;

		// Count images without alt text.
		$total_images = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		$missing_alt = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%'
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} pm
			       WHERE pm.post_id = p.ID
			         AND pm.meta_key = '_wp_attachment_image_alt'
			         AND pm.meta_value != ''
			   )"
		);

		wp_send_json_success( [
			'total_images' => $total_images,
			'missing_alt'  => $missing_alt,
			'has_alt'      => $total_images - $missing_alt,
		] );
	}

	/**
	 * Process a batch of images missing alt text (POST only).
	 *
	 * Processes up to 5 images per request. Client should poll until done.
	 */
	public function ajax_bulk_process(): void {
		$this->verify_request( true );

		if ( ! function_exists( 'wpmind_vision' ) ) {
			wp_send_json_error( [ 'message' => 'Vision API not available' ] );
		}

		global $wpdb;

		$batch_size = 5;

		// Get batch of image IDs missing alt text, excluding previously failed ones.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- fixed pattern match, not user-supplied wildcards.
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%%'
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} pm
			       WHERE pm.post_id = p.ID
			         AND pm.meta_key = '_wp_attachment_image_alt'
			         AND pm.meta_value != ''
			   )
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} pm2
			       WHERE pm2.post_id = p.ID
			         AND pm2.meta_key = '_wpmind_alt_failed'
			   )
			 ORDER BY p.ID ASC
			 LIMIT %d",
			$batch_size
		) );

		if ( empty( $ids ) ) {
			wp_send_json_success( [
				'processed' => 0,
				'done'      => true,
			] );
		}

		$generator = new AltTextGenerator();
		$processed = 0;
		$errors    = 0;

		foreach ( $ids as $id ) {
			$int_id = (int) $id;
			$generator->process_single( $int_id );

			// Check if alt text was actually set.
			$alt = get_post_meta( $int_id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt ) ) {
				$processed++;
			} else {
				$errors++;
				// Mark as failed to prevent infinite re-processing.
				update_post_meta( $int_id, '_wpmind_alt_failed', '1' );
			}
		}

		wp_send_json_success( [
			'processed' => $processed,
			'errors'    => $errors,
			'done'      => count( $ids ) < $batch_size,
		] );
	}

	/**
	 * Get media intelligence statistics (GET allowed, read-only).
	 */
	public function ajax_get_stats(): void {
		$this->verify_request( false );

		$stats     = get_option( 'wpmind_media_stats', [] );
		$month_key = 'month_' . gmdate( 'Y_m' );

		wp_send_json_success( [
			'total_generated' => (int) ( $stats['generated'] ?? 0 ),
			'month_generated' => (int) ( $stats[ $month_key ] ?? 0 ),
		] );
	}

	/**
	 * Regenerate alt text for a single attachment (POST only).
	 */
	public function ajax_regenerate(): void {
		$this->verify_request( true );

		if ( ! function_exists( 'wpmind_vision' ) ) {
			wp_send_json_error( [ 'message' => 'Vision API not available' ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify_request() above.
		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid attachment' ] );
		}

		// Clear existing alt text so process_single will regenerate.
		delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );

		$generator = new AltTextGenerator();
		$generator->process_single( $attachment_id );

		$new_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( empty( $new_alt ) ) {
			wp_send_json_error( [ 'message' => __( '生成失败，请重试', 'wpmind' ) ] );
		}

		$post = get_post( $attachment_id );

		wp_send_json_success( [
			'alt_text' => $new_alt,
			'title'    => $post->post_title ?? '',
			'caption'  => $post->post_excerpt ?? '',
		] );
	}
}
