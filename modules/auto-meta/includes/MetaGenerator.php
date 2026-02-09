<?php
/**
 * Meta Generator
 *
 * Core logic for auto-generating post metadata via AI:
 * excerpt, tags, category, FAQ Schema, SEO description.
 *
 * @package WPMind\Modules\AutoMeta
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\AutoMeta;

use WP_Post;
use WP_Error;

/**
 * Class MetaGenerator
 *
 * Handles hook registration, trigger logic, content extraction,
 * AI generation, and metadata persistence.
 */
final class MetaGenerator {

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		// First publish trigger.
		add_action( 'transition_post_status', [ $this, 'on_publish' ], 20, 3 );

		// Content update trigger.
		add_action( 'post_updated', [ $this, 'on_update' ], 20, 3 );

		// WP-Cron async handler.
		add_action( 'wpmind_generate_auto_meta', [ $this, 'process_single' ] );

		// FAQ Schema injection into GEO module.
		add_filter( 'wpmind_article_schema', [ $this, 'inject_faq_schema' ], 15, 2 );
	}

	/**
	 * Handle first-time publish.
	 *
	 * @param string  $new  New post status.
	 * @param string  $old  Old post status.
	 * @param WP_Post $post Post object.
	 */
	public function on_publish( string $new, string $old, WP_Post $post ): void {
		if ( $new !== 'publish' ) {
			return;
		}
		if ( $old === 'publish' ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->get_supported_types(), true ) ) {
			return;
		}
		if ( wp_next_scheduled( 'wpmind_generate_auto_meta', [ $post->ID ] ) ) {
			return;
		}

		wp_schedule_single_event( time() + 30, 'wpmind_generate_auto_meta', [ $post->ID ] );
	}

	/**
	 * Handle content updates on published posts.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   Post after update.
	 * @param WP_Post $before  Post before update.
	 */
	public function on_update( int $post_id, WP_Post $after, WP_Post $before ): void {
		if ( $after->post_status !== 'publish' ) {
			return;
		}
		if ( ! in_array( $after->post_type, $this->get_supported_types(), true ) ) {
			return;
		}
		$old_hash = get_post_meta( $post_id, '_wpmind_auto_meta_hash', true );
		$new_hash = md5( $after->post_title . $after->post_content );
		if ( $old_hash === $new_hash ) {
			return;
		}
		// Only regenerate if metadata was auto-generated, or if initial generation failed (source empty).
		$source = get_post_meta( $post_id, '_wpmind_auto_meta_source', true );
		if ( $source !== 'auto' && $source !== '' ) {
			return;
		}
		if ( wp_next_scheduled( 'wpmind_generate_auto_meta', [ $post_id ] ) ) {
			return;
		}

		wp_schedule_single_event( time() + 30, 'wpmind_generate_auto_meta', [ $post_id ] );
	}

	/**
	 * Process a single post: generate and save all metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function process_single( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return;
		}

		// Preflight: skip AI call if no features are enabled.
		if ( ! $this->has_enabled_features() ) {
			return;
		}

		$lock_key = '_wpmind_auto_meta_lock';
		if ( get_post_meta( $post_id, $lock_key, true ) === '1' ) {
			return;
		}
		update_post_meta( $post_id, $lock_key, '1' );

		try {
			$content = $this->extract_content( $post );
			$result  = $this->generate_all_meta( $post, $content );
			if ( ! is_wp_error( $result ) ) {
				$this->save_meta( $post_id, $result );
			}
		} finally {
			delete_post_meta( $post_id, $lock_key );
		}
	}

	/**
	 * Extract clean text content from a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Plain text content.
	 */
	private function extract_content( WP_Post $post ): string {
		$content = $post->post_content;
		if ( has_blocks( $content ) ) {
			$content = do_blocks( $content );
		}
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = (string) preg_replace( '/\s+/', ' ', $content );
		if ( mb_strlen( $content ) > 3000 ) {
			$content = mb_substr( $content, 0, 3000 ) . '...';
		}
		return trim( $content );
	}

	/**
	 * Generate all metadata via a single structured API call.
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $content Extracted plain text.
	 * @return array|WP_Error Structured result or error.
	 */
	private function generate_all_meta( WP_Post $post, string $content ): array|WP_Error {
		$categories = get_categories( [ 'hide_empty' => false, 'fields' => 'names' ] );
		$cat_list   = implode( '、', array_slice( $categories, 0, 30 ) );

		$locale = get_locale();
		$lang   = str_starts_with( $locale, 'zh' ) ? '中文' : 'English';

		$prompt  = "分析以下文章，用{$lang}生成元数据。\n\n";
		$prompt .= "标题：{$post->post_title}\n\n";
		$prompt .= "内容：{$content}\n\n";
		$prompt .= "已有分类：{$cat_list}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "- excerpt: 100-150字摘要\n";
		$prompt .= "- seo_description: 120-160字符 SEO 描述\n";
		$prompt .= "- tags: 3-5个关键词标签\n";
		$prompt .= "- category: 从已有分类中选择最匹配的1个（必须是已有分类）\n";
		$prompt .= "- faq: 3个常见问题及简短回答\n";

		$schema = [
			'type'       => 'object',
			'required'   => [ 'excerpt', 'seo_description', 'tags', 'category', 'faq' ],
			'properties' => [
				'excerpt'         => [ 'type' => 'string' ],
				'seo_description' => [ 'type' => 'string' ],
				'tags'            => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'category'        => [ 'type' => 'string' ],
				'faq'             => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'question' => [ 'type' => 'string' ],
							'answer'   => [ 'type' => 'string' ],
						],
					],
				],
			],
		];

		/** @var string $prompt */
		$prompt = apply_filters( 'wpmind_auto_meta_prompt', $prompt, $post );

		return wpmind_structured( $prompt, $schema, [
			'context'     => 'auto_meta',
			'max_tokens'  => 800,
			'temperature' => 0.3,
		] );
	}

	/**
	 * Save generated metadata to the post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $result  Structured API result.
	 */
	private function save_meta( int $post_id, array $result ): void {
		$data = $result['data'];
		$post = get_post( $post_id );

		// 1. Excerpt (only when empty).
		if ( get_option( 'wpmind_auto_meta_excerpt', '1' ) === '1'
			&& empty( $post->post_excerpt )
			&& ! empty( $data['excerpt'] )
		) {
			remove_action( 'post_updated', [ $this, 'on_update' ], 20 );
			wp_update_post( [
				'ID'           => $post_id,
				'post_excerpt' => sanitize_text_field( $data['excerpt'] ),
			] );
			add_action( 'post_updated', [ $this, 'on_update' ], 20, 3 );
		}

		// 2. Tags (only when no tags exist).
		if ( get_option( 'wpmind_auto_meta_tags', '1' ) === '1'
			&& ! empty( $data['tags'] )
			&& is_object_in_taxonomy( $post->post_type, 'post_tag' )
		) {
			$existing = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
			if ( empty( $existing ) ) {
				$tags = array_map( 'sanitize_text_field', $data['tags'] );
				wp_set_post_tags( $post_id, $tags, true );
			}
		}

		// 3. Category (only when only default category).
		if ( get_option( 'wpmind_auto_meta_category', '0' ) === '1'
			&& ! empty( $data['category'] )
			&& is_object_in_taxonomy( $post->post_type, 'category' )
		) {
			$current_cats = wp_get_post_categories( $post_id );
			$default_cat  = (int) get_option( 'default_category' );
			if ( count( $current_cats ) === 1 && $current_cats[0] === $default_cat ) {
				$cat = get_cat_ID( sanitize_text_field( $data['category'] ) );
				if ( $cat > 0 ) {
					wp_set_post_categories( $post_id, [ $cat ] );
				}
			}
		}

		// 4. FAQ Schema.
		if ( get_option( 'wpmind_auto_meta_faq', '1' ) === '1' && ! empty( $data['faq'] ) ) {
			update_post_meta( $post_id, '_wpmind_faq_schema', wp_json_encode( $data['faq'] ) );
		}

		// 5. SEO description.
		if ( get_option( 'wpmind_auto_meta_seo_desc', '1' ) === '1' && ! empty( $data['seo_description'] ) ) {
			update_post_meta( $post_id, '_wpmind_seo_description', sanitize_text_field( $data['seo_description'] ) );
		}

		// 6. Tracking metadata.
		update_post_meta( $post_id, '_wpmind_auto_meta_source', 'auto' );
		update_post_meta( $post_id, '_wpmind_auto_meta_hash', md5( $post->post_title . $post->post_content ) );
		update_post_meta( $post_id, '_wpmind_auto_meta_generated_at', current_time( 'mysql' ) );

		// 7. Statistics.
		do_action( 'wpmind_auto_meta_generated', $post_id, $data );
		$this->increment_stat( 'generated' );
	}

	/**
	 * Inject FAQ Schema into GEO article schema.
	 *
	 * @param array   $schema Existing schema data.
	 * @param WP_Post $post   Post object.
	 * @return array Modified schema.
	 */
	public function inject_faq_schema( array $schema, WP_Post $post ): array {
		if ( get_option( 'wpmind_auto_meta_faq', '1' ) !== '1' ) {
			return $schema;
		}

		$faq_json = get_post_meta( $post->ID, '_wpmind_faq_schema', true );
		if ( empty( $faq_json ) ) {
			return $schema;
		}

		$faq_items = json_decode( $faq_json, true );
		if ( ! is_array( $faq_items ) || empty( $faq_items ) ) {
			return $schema;
		}

		$faq_entities = [];
		foreach ( $faq_items as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}
			$faq_entities[] = [
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $item['answer'],
				],
			];
		}

		if ( ! empty( $faq_entities ) ) {
			$schema['mainEntity'] = $faq_entities;
		}

		return $schema;
	}

	/**
	 * Check if at least one auto-meta feature is enabled.
	 *
	 * @return bool True if any feature toggle is on.
	 */
	private function has_enabled_features(): bool {
		return get_option( 'wpmind_auto_meta_excerpt', '1' ) === '1'
			|| get_option( 'wpmind_auto_meta_tags', '1' ) === '1'
			|| get_option( 'wpmind_auto_meta_category', '0' ) === '1'
			|| get_option( 'wpmind_auto_meta_faq', '1' ) === '1'
			|| get_option( 'wpmind_auto_meta_seo_desc', '1' ) === '1';
	}

	/**
	 * Get supported post types from settings.
	 *
	 * @return array<string> Post type slugs.
	 */
	private function get_supported_types(): array {
		$types = get_option( 'wpmind_auto_meta_post_types', [ 'post', 'page' ] );
		if ( ! is_array( $types ) ) {
			$types = [ 'post', 'page' ];
		}
		return $types;
	}

	/**
	 * Increment an auto-meta statistic counter.
	 *
	 * @param string $key Stat key (e.g. 'generated').
	 */
	private function increment_stat( string $key ): void {
		$stats = get_option( 'wpmind_auto_meta_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}
		$stats[ $key ]     = ( $stats[ $key ] ?? 0 ) + 1;
		$month_key         = 'month_' . gmdate( 'Y_m' );
		$stats[ $month_key ] = ( $stats[ $month_key ] ?? 0 ) + 1;
		update_option( 'wpmind_auto_meta_stats', $stats, false );
	}
}