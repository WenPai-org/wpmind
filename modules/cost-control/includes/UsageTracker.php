<?php
/**
 * Usage Tracker - Token usage and cost tracking.
 *
 * Records per-provider, per-model, and per-context token usage and cost estimates.
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\CostControl;

use WPMind\Usage\Pricing;

/**
 * Tracks AI request token consumption, estimated cost, and caller attribution.
 */
class UsageTracker {

	private const OPTION_KEY  = 'wpmind_usage_stats';
	private const HISTORY_KEY = 'wpmind_usage_history';
	private const MAX_HISTORY = 1000;

	/**
	 * Legacy provider pricing table (per 1M tokens).
	 *
	 * @deprecated Use WPMind\Usage\Pricing::DATA instead.
	 */
	private const PRICING = [];

	/**
	 * Record token usage for a single AI request.
	 *
	 * @param string $provider      Provider ID.
	 * @param string $model         Model identifier.
	 * @param int    $input_tokens  Input token count.
	 * @param int    $output_tokens Output token count.
	 * @param int    $latency_ms    Request latency in milliseconds.
	 * @param string $context       Call context string (e.g. wpslug_seo_slug, api_gateway:sk_xxxx).
	 * @param string $payer         Payer identifier (byok or wenpai_cloud); derived when empty.
	 * @param int    $credits       Official cloud credits consumed.
	 */
	public static function record(
		string $provider,
		string $model,
		int $input_tokens,
		int $output_tokens,
		int $latency_ms = 0,
		string $context = '',
		string $payer = '',
		int $credits = 0
	): void {
		$input_tokens  = max( 0, $input_tokens );
		$output_tokens = max( 0, $output_tokens );
		$latency_ms    = max( 0, $latency_ms );
		$credits       = max( 0, $credits );
		$context       = trim( $context );

		if ( '' === $payer ) {
			$payer = 'wenpai_cloud' === $provider ? 'wenpai_cloud' : 'byok';
		}

		$caller_plugin = self::resolve_caller_plugin( $context );
		$cost          = self::calculate_cost( $provider, $model, $input_tokens, $output_tokens );

		self::update_stats(
			$provider,
			$model,
			$input_tokens,
			$output_tokens,
			$cost,
			$context,
			$caller_plugin,
			$payer,
			$credits
		);

		if ( $input_tokens > 0 || $output_tokens > 0 || $credits > 0 ) {
			self::add_to_history(
				$provider,
				$model,
				$input_tokens,
				$output_tokens,
				$cost,
				$latency_ms,
				$context,
				$caller_plugin,
				$payer,
				$credits
			);
		}
	}

	/**
	 * Resolve caller plugin identifier from a context string.
	 *
	 * @param string $context Call context string.
	 * @return string Normalized caller plugin slug.
	 */
	public static function resolve_caller_plugin( string $context ): string {
		$normalized = strtolower( trim( $context ) );
		if ( '' === $normalized ) {
			return 'wpmind';
		}

		$prefix_map = [
			'api_gateway:' => 'api-gateway',
			'api_gateway_' => 'api-gateway',
			'api-gateway:' => 'api-gateway',
			'api-gateway_' => 'api-gateway',
			'api_gateway'  => 'api-gateway',
			'api-gateway'  => 'api-gateway',
			'wpslug_'      => 'wpslug',
			'wpslug:'      => 'wpslug',
			'bisheng_'     => 'bisheng',
			'bisheng:'     => 'bisheng',
			'wpicp_'       => 'wpicp',
			'wpicp:'       => 'wpicp',
			'wpdocs_'      => 'wpdocs',
			'wpdocs:'      => 'wpdocs',
			'wpsss_'       => 'wpsss',
			'wpsss:'       => 'wpsss',
			'wpavatar_'    => 'wpavatar',
			'wpavatar:'    => 'wpavatar',
			'wpcomments_'  => 'wpcomments',
			'wpcomments:'  => 'wpcomments',
		];

		foreach ( $prefix_map as $prefix => $plugin ) {
			if ( str_starts_with( $normalized, $prefix ) ) {
				return $plugin;
			}
		}

		$exact_map = [
			'wpslug'     => 'wpslug',
			'bisheng'    => 'bisheng',
			'wpicp'      => 'wpicp',
			'wpdocs'     => 'wpdocs',
			'wpsss'      => 'wpsss',
			'wpavatar'   => 'wpavatar',
			'wpcomments' => 'wpcomments',
		];
		if ( isset( $exact_map[ $normalized ] ) ) {
			return $exact_map[ $normalized ];
		}

		$internal_prefixes = [
			'wpmind',
			'auto_meta',
			'media_',
			'geo_',
			'chat',
			'stream',
			'embed',
			'vision',
			'structured',
			'summarize',
			'translate',
			'moderate',
			'pinyin',
			'speech',
			'transcribe',
			'image',
		];
		foreach ( $internal_prefixes as $internal ) {
			if ( $normalized === $internal || str_starts_with( $normalized, $internal ) ) {
				return 'wpmind';
			}
		}

		if ( preg_match( '/^([a-z0-9-]+)[_:]/', $normalized, $matches ) ) {
			return sanitize_key( $matches[1] );
		}

		return 'wpmind';
	}

	/**
	 * Calculate request cost using Pricing (ModelCatalog-backed).
	 *
	 * @param string $provider      Provider ID.
	 * @param string $model         Model identifier.
	 * @param int    $input_tokens  Input token count.
	 * @param int    $output_tokens Output token count.
	 * @return float Cost in provider currency.
	 */
	public static function calculate_cost(
		string $provider,
		string $model,
		int $input_tokens,
		int $output_tokens
	): float {
		self::ensure_pricing_loaded();
		return Pricing::calculate_cost( $provider, $model, $input_tokens, $output_tokens );
	}

	/**
	 * Get the billing currency for a provider.
	 *
	 * @param string $provider Provider ID.
	 * @param string $model    Optional model identifier.
	 * @return string Currency code (USD or CNY).
	 */
	public static function get_currency( string $provider, string $model = '' ): string {
		self::ensure_pricing_loaded();
		if ( '' !== $model ) {
			return Pricing::resolve_currency( $provider, $model );
		}
		return Pricing::get_currency( $provider );
	}

	/**
	 * Update aggregated usage statistics with transient lock protection.
	 *
	 * @param string $provider      Provider ID.
	 * @param string $model         Model identifier.
	 * @param int    $input_tokens  Input token count.
	 * @param int    $output_tokens Output token count.
	 * @param float  $cost          Cost in provider currency.
	 * @param string $context       Call context string.
	 * @param string $caller_plugin Resolved caller plugin slug.
	 * @param string $payer         Payer identifier.
	 * @param int    $credits       Official cloud credits consumed.
	 */
	private static function update_stats(
		string $provider,
		string $model,
		int $input_tokens,
		int $output_tokens,
		float $cost,
		string $context = '',
		string $caller_plugin = 'wpmind',
		string $payer = 'byok',
		int $credits = 0
	): void {
		$lock_key    = 'wpmind_usage_stats_lock';
		$max_retries = 3;
		$retry_delay = 50000;
		$acquired    = false;

		for ( $i = 0; $i < $max_retries; $i++ ) {
			if ( false === get_transient( $lock_key ) ) {
				set_transient( $lock_key, true, 5 );
				$acquired = true;
				break;
			}
			usleep( $retry_delay );
		}

		try {
			$stats = get_option( self::OPTION_KEY, [] );

			if ( ! is_array( $stats ) ) {
				$stats = [];
			}

			$today    = wp_date( 'Y-m-d' );
			$month    = wp_date( 'Y-m' );
			$currency = self::get_currency( $provider, $model );

			if ( ! isset( $stats['providers'] ) || ! is_array( $stats['providers'] ) ) {
				$stats['providers'] = [];
			}
			if ( ! isset( $stats['providers'][ $provider ] ) ) {
				$stats['providers'][ $provider ] = [
					'total_input_tokens'  => 0,
					'total_output_tokens' => 0,
					'total_cost'          => 0,
					'request_count'       => 0,
					'models'              => [],
				];
			}

			if ( ! isset( $stats['daily'] ) || ! is_array( $stats['daily'] ) ) {
				$stats['daily'] = [];
			}
			if ( ! isset( $stats['daily'][ $today ] ) ) {
				$stats['daily'][ $today ] = [
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'cost_usd'      => 0,
					'cost_cny'      => 0,
					'requests'      => 0,
				];
			}

			if ( ! isset( $stats['monthly'] ) || ! is_array( $stats['monthly'] ) ) {
				$stats['monthly'] = [];
			}
			if ( ! isset( $stats['monthly'][ $month ] ) ) {
				$stats['monthly'][ $month ] = [
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'cost_usd'      => 0,
					'cost_cny'      => 0,
					'requests'      => 0,
				];
			}

			// Update provider stats.
			$stats['providers'][ $provider ]['total_input_tokens']  += $input_tokens;
			$stats['providers'][ $provider ]['total_output_tokens'] += $output_tokens;
			$stats['providers'][ $provider ]['total_cost']          += $cost;
			++$stats['providers'][ $provider ]['request_count'];

			// Update model stats.
			if ( ! isset( $stats['providers'][ $provider ]['models'][ $model ] ) ) {
				$stats['providers'][ $provider ]['models'][ $model ] = [
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'cost'          => 0,
					'requests'      => 0,
				];
			}
			$stats['providers'][ $provider ]['models'][ $model ]['input_tokens']  += $input_tokens;
			$stats['providers'][ $provider ]['models'][ $model ]['output_tokens'] += $output_tokens;
			$stats['providers'][ $provider ]['models'][ $model ]['cost']          += $cost;
			++$stats['providers'][ $provider ]['models'][ $model ]['requests'];

			// Update daily stats by currency.
			$stats['daily'][ $today ]['input_tokens']  += $input_tokens;
			$stats['daily'][ $today ]['output_tokens'] += $output_tokens;
			if ( 'CNY' === $currency ) {
				$stats['daily'][ $today ]['cost_cny'] = ( $stats['daily'][ $today ]['cost_cny'] ?? 0 ) + $cost;
			} else {
				$stats['daily'][ $today ]['cost_usd'] = ( $stats['daily'][ $today ]['cost_usd'] ?? 0 ) + $cost;
			}
			++$stats['daily'][ $today ]['requests'];

			// Update monthly stats by currency.
			$stats['monthly'][ $month ]['input_tokens']  += $input_tokens;
			$stats['monthly'][ $month ]['output_tokens'] += $output_tokens;
			if ( 'CNY' === $currency ) {
				$stats['monthly'][ $month ]['cost_cny'] = ( $stats['monthly'][ $month ]['cost_cny'] ?? 0 ) + $cost;
			} else {
				$stats['monthly'][ $month ]['cost_usd'] = ( $stats['monthly'][ $month ]['cost_usd'] ?? 0 ) + $cost;
			}
			++$stats['monthly'][ $month ]['requests'];

			// Update context attribution stats.
			if ( ! isset( $stats['contexts'] ) || ! is_array( $stats['contexts'] ) ) {
				$stats['contexts'] = [];
			}
			$context_key = '' !== $context ? $context : 'default';
			if ( ! isset( $stats['contexts'][ $context_key ] ) || ! is_array( $stats['contexts'][ $context_key ] ) ) {
				$stats['contexts'][ $context_key ] = [
					'context'       => $context_key,
					'caller_plugin' => $caller_plugin,
					'payer'         => $payer,
					'requests'      => 0,
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'cost_usd'      => 0.0,
					'cost_cny'      => 0.0,
					'credits'       => 0,
					'last_used'     => 0,
				];
			}
			$stats['contexts'][ $context_key ]['caller_plugin'] = $caller_plugin;
			$stats['contexts'][ $context_key ]['payer']         = $payer;
			$stats['contexts'][ $context_key ]['requests']      = (int) ( $stats['contexts'][ $context_key ]['requests'] ?? 0 ) + 1;
			$stats['contexts'][ $context_key ]['input_tokens']  = (int) ( $stats['contexts'][ $context_key ]['input_tokens'] ?? 0 ) + $input_tokens;
			$stats['contexts'][ $context_key ]['output_tokens'] = (int) ( $stats['contexts'][ $context_key ]['output_tokens'] ?? 0 ) + $output_tokens;
			$stats['contexts'][ $context_key ]['credits']       = (int) ( $stats['contexts'][ $context_key ]['credits'] ?? 0 ) + $credits;
			$stats['contexts'][ $context_key ]['last_used']     = time();
			if ( 'CNY' === $currency ) {
				$stats['contexts'][ $context_key ]['cost_cny'] = (float) ( $stats['contexts'][ $context_key ]['cost_cny'] ?? 0.0 ) + $cost;
			} else {
				$stats['contexts'][ $context_key ]['cost_usd'] = (float) ( $stats['contexts'][ $context_key ]['cost_usd'] ?? 0.0 ) + $cost;
			}

			// Update totals by currency.
			if ( ! isset( $stats['total'] ) || ! is_array( $stats['total'] ) ) {
				$stats['total'] = [
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'cost_usd'      => 0,
					'cost_cny'      => 0,
					'credits'       => 0,
					'requests'      => 0,
				];
			}
			$stats['total']['input_tokens']  = ( $stats['total']['input_tokens'] ?? 0 ) + $input_tokens;
			$stats['total']['output_tokens'] = ( $stats['total']['output_tokens'] ?? 0 ) + $output_tokens;
			$stats['total']['credits']       = (int) ( $stats['total']['credits'] ?? 0 ) + $credits;
			if ( 'CNY' === $currency ) {
				$stats['total']['cost_cny'] = ( $stats['total']['cost_cny'] ?? 0 ) + $cost;
			} else {
				$stats['total']['cost_usd'] = ( $stats['total']['cost_usd'] ?? 0 ) + $cost;
			}
			$stats['total']['requests'] = ( $stats['total']['requests'] ?? 0 ) + 1;

			$stats['last_updated'] = time();

			// Prune daily stats older than 30 days.
			$cutoff_date = wp_date( 'Y-m-d', strtotime( '-30 days' ) );
			foreach ( array_keys( $stats['daily'] ) as $date ) {
				if ( $date < $cutoff_date ) {
					unset( $stats['daily'][ $date ] );
				}
			}

			// Prune monthly stats older than 12 months.
			$cutoff_month = wp_date( 'Y-m', strtotime( '-12 months' ) );
			foreach ( array_keys( $stats['monthly'] ) as $m ) {
				if ( $m < $cutoff_month ) {
					unset( $stats['monthly'][ $m ] );
				}
			}

			update_option( self::OPTION_KEY, $stats, false );
			wp_cache_set( 'wpmind_usage_stats', $stats, '', 300 );
		} finally {
			if ( $acquired ) {
				delete_transient( $lock_key );
			}
		}
	}

	/**
	 * Append a record to usage history with transient lock protection.
	 *
	 * @param string $provider      Provider ID.
	 * @param string $model         Model identifier.
	 * @param int    $input_tokens  Input token count.
	 * @param int    $output_tokens Output token count.
	 * @param float  $cost          Cost in provider currency.
	 * @param int    $latency_ms    Request latency in milliseconds.
	 * @param string $context       Call context string.
	 * @param string $caller_plugin Resolved caller plugin slug.
	 * @param string $payer         Payer identifier.
	 * @param int    $credits       Official cloud credits consumed.
	 */
	private static function add_to_history(
		string $provider,
		string $model,
		int $input_tokens,
		int $output_tokens,
		float $cost,
		int $latency_ms,
		string $context = '',
		string $caller_plugin = 'wpmind',
		string $payer = 'byok',
		int $credits = 0
	): void {
		$lock_key    = 'wpmind_usage_history_lock';
		$max_retries = 3;
		$retry_delay = 50000;
		$acquired    = false;

		for ( $i = 0; $i < $max_retries; $i++ ) {
			if ( false === get_transient( $lock_key ) ) {
				set_transient( $lock_key, true, 5 );
				$acquired = true;
				break;
			}
			usleep( $retry_delay );
		}

		try {
			$history = get_option( self::HISTORY_KEY, [] );

			if ( ! is_array( $history ) ) {
				$history = [];
			}

			$history[] = [
				'provider'      => $provider,
				'model'         => $model,
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
				'cost'          => $cost,
				'latency_ms'    => $latency_ms,
				'context'       => $context,
				'caller_plugin' => $caller_plugin,
				'payer'         => $payer,
				'credits'       => $credits,
				'timestamp'     => time(),
			];

			if ( count( $history ) > self::MAX_HISTORY ) {
				$history = array_slice( $history, -self::MAX_HISTORY );
			}

			update_option( self::HISTORY_KEY, $history, false );
			wp_cache_set( 'wpmind_usage_history', $history, '', 300 );
		} finally {
			if ( $acquired ) {
				delete_transient( $lock_key );
			}
		}
	}

	/**
	 * Get aggregated usage statistics.
	 *
	 * @return array Aggregated usage statistics array.
	 */
	public static function get_stats(): array {
		$cache_key = 'wpmind_usage_stats';
		$stats     = wp_cache_get( $cache_key );

		if ( false === $stats ) {
			$stats = get_option( self::OPTION_KEY, [] );
			wp_cache_set( $cache_key, $stats, '', 300 );
		}

		if ( ! is_array( $stats ) ) {
			$stats = [];
		}

		return $stats ?: [
			'providers' => [],
			'daily'     => [],
			'monthly'   => [],
			'contexts'  => [],
			'total'     => [
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'cost_usd'      => 0,
				'cost_cny'      => 0,
				'credits'       => 0,
				'requests'      => 0,
			],
		];
	}

	/**
	 * Get per-context / caller plugin usage statistics sorted by request count descending.
	 *
	 * @return array<string, array<string, mixed>> Context usage breakdown.
	 */
	public static function get_context_stats(): array {
		$stats    = self::get_stats();
		$contexts = isset( $stats['contexts'] ) && is_array( $stats['contexts'] ) ? $stats['contexts'] : [];

		uasort(
			$contexts,
			static function ( $a, $b ): int {
				$req_a = is_array( $a ) ? (int) ( $a['requests'] ?? 0 ) : 0;
				$req_b = is_array( $b ) ? (int) ( $b['requests'] ?? 0 ) : 0;
				return $req_b <=> $req_a;
			}
		);

		return $contexts;
	}

	/**
	 * Get today's usage statistics.
	 *
	 * @return array Today's usage statistics.
	 */
	public static function get_today_stats(): array {
		$stats = self::get_stats();
		$today = wp_date( 'Y-m-d' );

		return $stats['daily'][ $today ] ?? [
			'input_tokens'  => 0,
			'output_tokens' => 0,
			'cost_usd'      => 0,
			'cost_cny'      => 0,
			'requests'      => 0,
		];
	}

	/**
	 * Get current month's usage statistics.
	 *
	 * @return array Current month's usage statistics.
	 */
	public static function get_month_stats(): array {
		$stats = self::get_stats();
		$month = wp_date( 'Y-m' );

		return $stats['monthly'][ $month ] ?? [
			'input_tokens'  => 0,
			'output_tokens' => 0,
			'cost_usd'      => 0,
			'cost_cny'      => 0,
			'requests'      => 0,
		];
	}

	/**
	 * Get current week's usage statistics (Monday to today).
	 *
	 * @return array Current week's usage statistics.
	 */
	public static function get_week_stats(): array {
		$stats = self::get_stats();
		$daily = $stats['daily'] ?? [];

		$week_start = wp_date( 'Y-m-d', strtotime( 'monday this week' ) );
		$today      = wp_date( 'Y-m-d' );

		$result = [
			'input_tokens'  => 0,
			'output_tokens' => 0,
			'cost_usd'      => 0,
			'cost_cny'      => 0,
			'requests'      => 0,
		];

		foreach ( $daily as $date => $day_stats ) {
			if ( $date >= $week_start && $date <= $today ) {
				$result['input_tokens']  += $day_stats['input_tokens'] ?? 0;
				$result['output_tokens'] += $day_stats['output_tokens'] ?? 0;
				$result['cost_usd']      += $day_stats['cost_usd'] ?? 0;
				$result['cost_cny']      += $day_stats['cost_cny'] ?? 0;
				$result['requests']      += $day_stats['requests'] ?? 0;
			}
		}

		return $result;
	}

	/**
	 * Get recent usage history records in reverse chronological order.
	 *
	 * @param int $limit Maximum number of records to return.
	 * @return array List of usage history entries.
	 */
	public static function get_history( int $limit = 50 ): array {
		$cache_key = 'wpmind_usage_history';
		$history   = wp_cache_get( $cache_key );

		if ( false === $history ) {
			$history = get_option( self::HISTORY_KEY, [] );
			wp_cache_set( $cache_key, $history, '', 300 );
		}

		if ( ! is_array( $history ) ) {
			$history = [];
		}

		return array_slice( array_reverse( $history ), 0, $limit );
	}

	/**
	 * Get legacy fallback pricing table.
	 *
	 * @return array Pricing data keyed by provider.
	 */
	public static function get_pricing(): array {
		self::ensure_pricing_loaded();
		return Pricing::DATA;
	}

	/**
	 * Clear all stored usage statistics and history.
	 */
	public static function clear_all(): void {
		delete_option( self::OPTION_KEY );
		delete_option( self::HISTORY_KEY );
		wp_cache_delete( 'wpmind_usage_stats' );
		wp_cache_delete( 'wpmind_usage_history' );
	}

	/**
	 * Format a token count into a compact human-readable string.
	 *
	 * @param int $tokens Token count.
	 * @return string Formatted token count.
	 */
	public static function format_tokens( int $tokens ): string {
		if ( $tokens >= 1_000_000 ) {
			return round( $tokens / 1_000_000, 2 ) . 'M';
		}
		if ( $tokens >= 1_000 ) {
			return round( $tokens / 1_000, 1 ) . 'K';
		}
		return (string) $tokens;
	}

	/**
	 * Format a monetary cost value with currency symbol.
	 *
	 * @param float  $cost     Monetary cost.
	 * @param string $currency Currency code (USD or CNY).
	 * @return string Formatted cost string.
	 */
	public static function format_cost( float $cost, string $currency = 'USD' ): string {
		$symbol = 'CNY' === $currency ? '¥' : '$';
		if ( $cost < 0.01 ) {
			return $symbol . number_format( $cost, 4 );
		}
		return $symbol . number_format( $cost, 2 );
	}

	/**
	 * Format combined USD and CNY costs for display.
	 *
	 * @param float $cost_usd Cost in USD.
	 * @param float $cost_cny Cost in CNY.
	 * @return string Formatted display string.
	 */
	public static function format_cost_by_currency( float $cost_usd, float $cost_cny ): string {
		$parts = [];
		if ( $cost_usd > 0 ) {
			$parts[] = self::format_cost( $cost_usd, 'USD' );
		}
		if ( $cost_cny > 0 ) {
			$parts[] = self::format_cost( $cost_cny, 'CNY' );
		}
		if ( empty( $parts ) ) {
			$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
			return 0 === strpos( $locale, 'zh_' ) ? '¥0.00' : '$0.00';
		}
		return implode( ' / ', $parts );
	}

	/**
	 * Get the display name for a provider ID.
	 *
	 * @param string $provider Provider ID.
	 * @return string Display name.
	 */
	public static function get_provider_display_name( string $provider ): string {
		$names = [
			'wenpai_cloud' => '文派心思官方算力',
			'openai'       => 'OpenAI',
			'anthropic'    => 'Anthropic',
			'google'       => 'Google AI',
			'deepseek'     => 'DeepSeek',
			'qwen'         => '千问',
			'zhipu'        => '智谱 AI',
			'moonshot'     => 'Moonshot',
			'doubao'       => '豆包',
			'siliconflow'  => '硅基流动',
			'baidu'        => '百度文心',
			'minimax'      => 'MiniMax',
		];
		return $names[ $provider ] ?? $provider;
	}

	/**
	 * Ensure the Pricing class file is loaded.
	 */
	private static function ensure_pricing_loaded(): void {
		if ( ! class_exists( Pricing::class ) ) {
			require_once WPMIND_PATH . 'includes/Usage/Pricing.php';
		}
	}
}
