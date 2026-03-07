<?php
/**
 * Auto-Meta AJAX Controller
 *
 * Handles AJAX requests for the Auto-Meta module:
 * settings save, manual generation, and statistics.
 *
 * @package WPMind\Modules\AutoMeta
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\AutoMeta;

/**
 * Class AutoMetaAjaxController
 *
 * Provides AJAX handlers for auto-meta operations.
 */
final class AutoMetaAjaxController {

	/**
	 * Register AJAX hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpmind_save_auto_meta_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_wpmind_auto_meta_generate', [ $this, 'ajax_manual_generate' ] );
		add_action( 'wp_ajax_wpmind_auto_meta_get_stats', [ $this, 'ajax_get_stats' ] );
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
	 * Save auto-meta settings (POST only).
	 */
	public function ajax_save_settings(): void {
		$this->verify_request( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify_request() above.
		$raw = wp_unslash( $_POST );

		$to_bool = function ( string $key ) use ( $raw ): string {
			return ( (string) ( $raw[ $key ] ?? '0' ) ) === '1' ? '1' : '0';
		};

		$options = [
			'wpmind_auto_meta_enabled'  => $to_bool( 'enabled' ),
			'wpmind_auto_meta_excerpt'  => $to_bool( 'auto_excerpt' ),
			'wpmind_auto_meta_tags'     => $to_bool( 'auto_tags' ),
			'wpmind_auto_meta_category' => $to_bool( 'auto_category' ),
			'wpmind_auto_meta_faq'      => $to_bool( 'auto_faq' ),
			'wpmind_auto_meta_seo_desc' => $to_bool( 'auto_seo_desc' ),
		];

		foreach ( $options as $key => $value ) {
			update_option( $key, $value, false );
		}

		// Post types (whitelist).
		$allowed_types = get_post_types( [ 'public' => true ], 'names' );
		$post_types    = [];
		$raw_types     = $raw['post_types'] ?? [];
		if ( is_array( $raw_types ) ) {
			foreach ( $raw_types as $type ) {
				$type = sanitize_key( $type );
				if ( isset( $allowed_types[ $type ] ) ) {
					$post_types[] = $type;
				}
			}
		}
		if ( empty( $post_types ) ) {
			$post_types = [ 'post' ];
		}
		update_option( 'wpmind_auto_meta_post_types', $post_types, false );

		wp_send_json_success( [ 'message' => __( '设置已保存', 'wpmind' ) ] );
	}

	/**
	 * Manually generate metadata for a single post (POST only).
	 */
	public function ajax_manual_generate(): void {
		$this->verify_request( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verify_request() above.
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( '无效的文章 ID', 'wpmind' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( '文章不存在', 'wpmind' ) ] );
		}

		if ( $post->post_status !== 'publish' ) {
			wp_send_json_error( [ 'message' => __( '仅支持已发布的文章，当前状态：', 'wpmind' ) . $post->post_status ] );
		}

		$generator = new MetaGenerator();
		$generator->process_single( $post_id );

		// Check if generation succeeded.
		$source = get_post_meta( $post_id, '_wpmind_auto_meta_source', true );
		if ( $source !== 'auto' ) {
			wp_send_json_error( [ 'message' => __( '生成失败，请检查 API 配置', 'wpmind' ) ] );
		}

		$post = get_post( $post_id );
		$faq  = get_post_meta( $post_id, '_wpmind_faq_schema', true );

		wp_send_json_success( [
			'message'         => __( '元数据生成成功', 'wpmind' ),
			'excerpt'         => $post->post_excerpt,
			'tags'            => wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ),
			'categories'      => wp_get_post_categories( $post_id, [ 'fields' => 'names' ] ),
			'faq'             => $faq ? json_decode( $faq, true ) : [],
			'seo_description' => get_post_meta( $post_id, '_wpmind_seo_description', true ),
			'generated_at'    => get_post_meta( $post_id, '_wpmind_auto_meta_generated_at', true ),
		] );
	}

	/**
	 * Get auto-meta statistics (GET allowed, read-only).
	 */
	public function ajax_get_stats(): void {
		$this->verify_request( false );

		$stats     = get_option( 'wpmind_auto_meta_stats', [] );
		$month_key = 'month_' . gmdate( 'Y_m' );

		wp_send_json_success( [
			'total_generated' => (int) ( $stats['generated'] ?? 0 ),
			'month_generated' => (int) ( $stats[ $month_key ] ?? 0 ),
		] );
	}
}