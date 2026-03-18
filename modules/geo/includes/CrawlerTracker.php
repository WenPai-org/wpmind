<?php
/**
 * AI Crawler Tracker
 *
 * Tracks AI crawler access to Markdown feeds for analytics.
 *
 * @package WPMind\Modules\Geo
 * @since 3.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class CrawlerTracker
 *
 * Identifies and tracks AI crawler access patterns.
 */
class CrawlerTracker {

	/**
	 * Known AI crawler user agents.
	 *
	 * @var array
	 */
	private const AI_CRAWLERS = array(
		'GPTBot'             => 'OpenAI',
		'ChatGPT-User'       => 'OpenAI',
		'Google-Extended'    => 'Google AI',
		'Googlebot'          => 'Google',
		'Bingbot'            => 'Microsoft',
		'ClaudeBot'          => 'Anthropic',
		'Claude-Web'         => 'Anthropic',
		'PerplexityBot'      => 'Perplexity',
		'Bytespider'         => 'ByteDance',
		'CCBot'              => 'Common Crawl',
		'anthropic-ai'       => 'Anthropic',
		'cohere-ai'          => 'Cohere',
		'Applebot'           => 'Apple',
		'FacebookBot'        => 'Meta',
		'Meta-ExternalAgent' => 'Meta',
		'Amazonbot'          => 'Amazon',
		'Baiduspider'        => '百度',
		'Sogou web spider'   => '搜狗',
		'360Spider'          => '360',
		'YisouSpider'        => '神马搜索',
		'DuckAssistBot'      => 'DuckDuckGo',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! get_option( 'wpmind_crawler_tracking', true ) ) {
			return;
		}

		// Track markdown feed access.
		add_action( 'wpmind_markdown_feed_accessed', array( $this, 'track_access' ) );

		// Hook into feed rendering.
		add_action( 'do_feed_markdown', array( $this, 'on_feed_access' ), 1 );
	}

	/**
	 * Called when markdown feed is accessed.
	 */
	public function on_feed_access(): void {
		$this->track_access();
	}

	/**
	 * Track crawler access.
	 */
	public function track_access(): void {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$crawler_info = $this->identify_crawler( $user_agent );

		if ( ! $crawler_info ) {
			return;
		}

		// Log the access.
		$this->log_access( $crawler_info, $user_agent );

		// Update statistics.
		$this->update_stats( $crawler_info );
	}

	/**
	 * Identify crawler from user agent.
	 *
	 * @param string $user_agent The user agent string.
	 * @return array|null Crawler info or null if not identified.
	 */
	public function identify_crawler( string $user_agent ): ?array {
		foreach ( self::AI_CRAWLERS as $pattern => $company ) {
			if ( false !== stripos( $user_agent, $pattern ) ) {
				return array(
					'pattern' => $pattern,
					'company' => $company,
					'is_ai'   => $this->is_ai_crawler( $pattern ),
				);
			}
		}

		return null;
	}

	/**
	 * Check if crawler is specifically an AI crawler.
	 *
	 * @param string $pattern The crawler pattern.
	 * @return bool True if AI crawler.
	 */
	private function is_ai_crawler( string $pattern ): bool {
		$ai_specific = array(
			'GPTBot',
			'ChatGPT-User',
			'Google-Extended',
			'ClaudeBot',
			'Claude-Web',
			'PerplexityBot',
			'anthropic-ai',
			'cohere-ai',
			'Bytespider',
			'DuckAssistBot',
		);

		return in_array( $pattern, $ai_specific, true );
	}

	/**
	 * Log crawler access.
	 *
	 * @param array  $crawler_info Crawler information.
	 * @param string $user_agent   Full user agent string.
	 */
	private function log_access( array $crawler_info, string $user_agent ): void {
		$log_entry = array(
			'timestamp'  => current_time( 'mysql' ),
			'crawler'    => $crawler_info['pattern'],
			'company'    => $crawler_info['company'],
			'is_ai'      => $crawler_info['is_ai'],
			'user_agent' => $user_agent,
			'url'        => isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '',
			'ip'         => $this->get_client_ip(),
		);

		// Get existing logs.
		$logs   = get_option( 'wpmind_crawler_logs', array() );
		$logs[] = $log_entry;

		// Keep only last 1000 entries.
		if ( count( $logs ) > 1000 ) {
			$logs = array_slice( $logs, -1000 );
		}

		update_option( 'wpmind_crawler_logs', $logs, false );
	}

	/**
	 * Update crawler statistics.
	 *
	 * @param array $crawler_info Crawler information.
	 */
	private function update_stats( array $crawler_info ): void {
		$stats = get_option( 'wpmind_crawler_stats', array() );

		$key = $crawler_info['pattern'];

		if ( ! isset( $stats[ $key ] ) ) {
			$stats[ $key ] = array(
				'company'    => $crawler_info['company'],
				'is_ai'      => $crawler_info['is_ai'],
				'total_hits' => 0,
				'first_seen' => current_time( 'mysql' ),
				'last_seen'  => current_time( 'mysql' ),
				'daily_hits' => array(),
			);
		}

		++$stats[ $key ]['total_hits'];
		$stats[ $key ]['last_seen'] = current_time( 'mysql' );

		// Track daily hits.
		$today = current_time( 'Y-m-d' );
		if ( ! isset( $stats[ $key ]['daily_hits'][ $today ] ) ) {
			$stats[ $key ]['daily_hits'][ $today ] = 0;
		}
		++$stats[ $key ]['daily_hits'][ $today ];

		// Keep only last 30 days.
		$stats[ $key ]['daily_hits'] = array_slice(
			$stats[ $key ]['daily_hits'],
			-30,
			30,
			true
		);

		update_option( 'wpmind_crawler_stats', $stats, false );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs.
				if ( false !== strpos( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Get crawler statistics.
	 *
	 * @return array Crawler statistics.
	 */
	public function get_stats(): array {
		return get_option( 'wpmind_crawler_stats', array() );
	}

	/**
	 * Get recent crawler logs.
	 *
	 * @param int $limit Number of logs to return.
	 * @return array Recent logs.
	 */
	public function get_recent_logs( int $limit = 100 ): array {
		$logs = get_option( 'wpmind_crawler_logs', array() );
		return array_slice( $logs, -$limit );
	}

	/**
	 * Get AI crawler summary.
	 *
	 * @return array Summary of AI crawler activity.
	 */
	public function get_ai_summary(): array {
		$stats   = $this->get_stats();
		$summary = array(
			'total_ai_hits'     => 0,
			'total_search_hits' => 0,
			'ai_crawlers'       => array(),
			'search_crawlers'   => array(),
		);

		foreach ( $stats as $crawler => $data ) {
			if ( $data['is_ai'] ) {
				$summary['total_ai_hits']          += $data['total_hits'];
				$summary['ai_crawlers'][ $crawler ] = $data['total_hits'];
			} else {
				$summary['total_search_hits']          += $data['total_hits'];
				$summary['search_crawlers'][ $crawler ] = $data['total_hits'];
			}
		}

		// Sort by hits.
		arsort( $summary['ai_crawlers'] );
		arsort( $summary['search_crawlers'] );

		return $summary;
	}
}
