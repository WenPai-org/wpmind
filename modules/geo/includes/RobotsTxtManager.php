<?php
/**
 * Robots.txt AI Crawler Manager
 *
 * Manages AI crawler access rules via WordPress robots_txt filter.
 *
 * @package WPMind\Modules\Geo
 * @since 3.10.0
 */

declare(strict_types=1);

namespace WPMind\Modules\Geo;

/**
 * Class RobotsTxtManager
 *
 * Injects AI crawler Allow/Disallow rules into robots.txt
 * without modifying the physical file.
 */
class RobotsTxtManager {

	/**
	 * Known AI crawlers with metadata.
	 *
	 * @var array<string, array{company: string, description: string}>
	 */
	public const AI_CRAWLERS = [
		'GPTBot'             => [
			'company'     => 'OpenAI',
			'description' => 'ChatGPT 训练和搜索',
		],
		'ChatGPT-User'       => [
			'company'     => 'OpenAI',
			'description' => 'ChatGPT 实时浏览',
		],
		'ClaudeBot'          => [
			'company'     => 'Anthropic',
			'description' => 'Claude 训练数据',
		],
		'Claude-Web'         => [
			'company'     => 'Anthropic',
			'description' => 'Claude 实时搜索',
		],
		'anthropic-ai'       => [
			'company'     => 'Anthropic',
			'description' => 'Anthropic 通用爬虫',
		],
		'Google-Extended'    => [
			'company'     => 'Google',
			'description' => 'Gemini AI 训练',
		],
		'PerplexityBot'      => [
			'company'     => 'Perplexity',
			'description' => 'Perplexity 搜索引擎',
		],
		'Bytespider'         => [
			'company'     => 'ByteDance',
			'description' => '字节跳动 AI 爬虫',
		],
		'CCBot'              => [
			'company'     => 'Common Crawl',
			'description' => '开放数据集爬虫',
		],
		'cohere-ai'          => [
			'company'     => 'Cohere',
			'description' => 'Cohere AI 训练',
		],
		'FacebookBot'        => [
			'company'     => 'Meta',
			'description' => 'Meta AI 爬虫',
		],
		'Meta-ExternalAgent' => [
			'company'     => 'Meta',
			'description' => 'Meta AI 训练',
		],
		'Applebot-Extended'  => [
			'company'     => 'Apple',
			'description' => 'Apple Intelligence 训练',
		],
		'Amazonbot'          => [
			'company'     => 'Amazon',
			'description' => 'Amazon AI 爬虫',
		],
		'Baiduspider'        => [
			'company'     => '百度',
			'description' => '百度搜索 / 文心一言',
		],
		'Sogou web spider'   => [
			'company'     => '搜狗',
			'description' => '搜狗搜索 / AI 搜索',
		],
		'360Spider'          => [
			'company'     => '360',
			'description' => '360 搜索 / AI 搜索',
		],
		'YisouSpider'        => [
			'company'     => '神马搜索',
			'description' => '阿里神马搜索',
		],
		'DuckAssistBot'      => [
			'company'     => 'DuckDuckGo',
			'description' => 'DuckDuckGo AI 助手',
		],
	];

	/**
	 * Rule values.
	 */
	public const RULE_ALLOW     = 'allow';
	public const RULE_DISALLOW  = 'disallow';
	public const RULE_UNMANAGED = '';

	/**
	 * Option key for stored rules.
	 */
	private const OPTION_KEY = 'wpmind_robots_ai_rules';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'robots_txt', [ $this, 'inject_rules' ], 100, 2 );
	}

	/**
	 * Inject AI crawler rules into robots.txt output.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string Modified robots.txt content.
	 */
	public function inject_rules( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return $output;
		}

		$lines = [ '', '# WPMind AI Crawler Rules' ];

		foreach ( $rules as $bot => $rule ) {
			if ( ! isset( self::AI_CRAWLERS[ $bot ] ) ) {
				continue;
			}

			if ( self::RULE_ALLOW === $rule ) {
				$lines[] = "User-agent: {$bot}";
				$lines[] = 'Allow: /';
				$lines[] = '';
			} elseif ( self::RULE_DISALLOW === $rule ) {
				$lines[] = "User-agent: {$bot}";
				$lines[] = 'Disallow: /';
				$lines[] = '';
			}
		}

		if ( count( $lines ) > 2 ) {
			$output .= implode( "\n", $lines ) . "\n";
		}

		return $output;
	}

	/**
	 * Get stored rules.
	 *
	 * @return array<string, string> Bot name => rule value.
	 */
	public function get_rules(): array {
		$rules = get_option( self::OPTION_KEY, [] );
		return is_array( $rules ) ? $rules : [];
	}

	/**
	 * Save rules.
	 *
	 * @param array<string, string> $rules Bot name => rule value.
	 */
	public function save_rules( array $rules ): void {
		$clean = [];
		foreach ( $rules as $bot => $rule ) {
			$bot = sanitize_text_field( $bot );
			if ( ! isset( self::AI_CRAWLERS[ $bot ] ) ) {
				continue;
			}
			if ( in_array( $rule, [ self::RULE_ALLOW, self::RULE_DISALLOW ], true ) ) {
				$clean[ $bot ] = $rule;
			}
			// RULE_UNMANAGED ('') means skip — don't store.
		}
		update_option( self::OPTION_KEY, $clean, false );
	}
}
