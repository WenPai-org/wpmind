<?php
/**
 * Text Processing Service
 *
 * 处理翻译、摘要、内容审核
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Text Processing Service
 *
 * @since 3.7.0
 */
class TextProcessingService extends AbstractService {

	private ChatService $chat_service;
	private StructuredOutputService $structured_service;

	public function __construct( ChatService $chat_service, StructuredOutputService $structured_service ) {
		$this->chat_service       = $chat_service;
		$this->structured_service = $structured_service;
	}

	/**
	 * 翻译文本
	 *
	 * @param string $text    要翻译的文本
	 * @param string $from    源语言
	 * @param string $to      目标语言
	 * @param array  $options 选项
	 * @return string|WP_Error
	 */
	public function translate( string $text, string $from, string $to, array $options ) {
		$defaults = [
			'context'   => 'translation',
			'format'    => 'text',
			'hint'      => '',
			'cache_ttl' => 86400,
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		$args = compact( 'text', 'from', 'to', 'options' );
		$args = apply_filters( 'wpmind_translate_args', $args, $context );

		$default_provider = get_option( 'wpmind_default_provider', 'openai' );
		$default_model    = $this->get_current_model( $default_provider );

		$cache_key    = $this->generate_cache_key( 'translate', $args, $default_provider, $default_model );
		$cache_lookup = $this->get_cached_value( $cache_key, (int) $options['cache_ttl'] );
		if ( $cache_lookup['hit'] ) {
			return $cache_lookup['value'];
		}

		$prompt = $this->build_translate_prompt( $text, $from, $to, $options );

		do_action( 'wpmind_before_request', 'translate', $args, $context );

		$result = $this->chat_service->chat(
			$prompt,
			[
				'context'     => $context,
				'max_tokens'  => max( 500, strlen( $text ) * 2 ),
				'temperature' => 0.3,
				'cache_ttl'   => 0,
			]
		);

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_error', $result, 'translate', $args );
			return $result;
		}

		$translated = trim( $result['content'] );

		if ( $options['format'] === 'slug' ) {
			$translated = sanitize_title_with_dashes( $translated, '', 'save' );
		}

		$translated = apply_filters( 'wpmind_translate_response', $translated, $text, $from, $to );

		do_action( 'wpmind_after_request', 'translate', $translated, $args, $result['usage'] ?? [] );

		$this->set_cached_value(
			$cache_key,
			$translated,
			(int) $options['cache_ttl'],
			[
				'type'     => 'translate',
				'context'  => $context,
				'provider' => $default_provider,
				'model'    => $default_model,
			]
		);

		return $translated;
	}

	/**
	 * 文本摘要
	 *
	 * @since 2.7.0
	 * @param string $text    要摘要的文本
	 * @param array  $options 选项
	 * @return string|WP_Error
	 */
	public function summarize( string $text, array $options = [] ) {
		$defaults = [
			'context'    => 'summarize',
			'max_length' => 200,
			'style'      => 'paragraph',
			'language'   => 'auto',
			'cache_ttl'  => 3600,
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		$default_provider = get_option( 'wpmind_default_provider', 'openai' );
		$default_model    = $this->get_current_model( $default_provider );

		$cache_key    = $this->generate_cache_key( 'summarize', compact( 'text', 'options' ), $default_provider, $default_model );
		$cache_lookup = $this->get_cached_value( $cache_key, (int) $options['cache_ttl'] );
		if ( $cache_lookup['hit'] ) {
			return $cache_lookup['value'];
		}

		$style_prompts = [
			'paragraph' => '用一段简洁的文字总结以下内容',
			'bullet'    => '用要点列表总结以下内容的关键信息',
			'title'     => '为以下内容生成一个简洁的标题',
		];
		$style_prompt  = $style_prompts[ $options['style'] ] ?? $style_prompts['paragraph'];

		$length_hint = $options['style'] === 'title'
			? '（不超过 20 个字）'
			: "（不超过 {$options['max_length']} 个字）";

		$lang_hint = $options['language'] !== 'auto'
			? "，用{$options['language']}输出"
			: '';

		$prompt = "{$style_prompt}{$length_hint}{$lang_hint}：\n\n{$text}";

		do_action( 'wpmind_before_request', 'summarize', compact( 'text', 'options' ), $context );

		$result = $this->chat_service->chat(
			$prompt,
			[
				'context'     => $context,
				'max_tokens'  => max( 100, $options['max_length'] * 2 ),
				'temperature' => 0.3,
				'cache_ttl'   => 0,
			]
		);

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_error', $result, 'summarize', compact( 'text', 'options' ) );
			return $result;
		}

		$summary = trim( $result['content'] );

		do_action( 'wpmind_after_request', 'summarize', $summary, compact( 'text', 'options' ), $result['usage'] );

		$this->set_cached_value(
			$cache_key,
			$summary,
			(int) $options['cache_ttl'],
			[
				'type'     => 'summarize',
				'context'  => $context,
				'provider' => $default_provider,
				'model'    => $default_model,
			]
		);

		return $summary;
	}

	/**
	 * 内容审核
	 *
	 * @since 2.7.0
	 * @param string $content 要审核的内容
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function moderate( string $content, array $options = [] ) {
		$defaults = [
			'context'    => 'moderation',
			'categories' => [ 'spam', 'adult', 'violence', 'hate', 'illegal' ],
			'threshold'  => 0.7,
			'cache_ttl'  => 300,
		];
		$options  = wp_parse_args( $options, $defaults );

		$context = $options['context'];

		$default_provider = get_option( 'wpmind_default_provider', 'openai' );
		$default_model    = $this->get_current_model( $default_provider );

		$cache_key    = $this->generate_cache_key( 'moderate', compact( 'content', 'options' ), $default_provider, $default_model );
		$cache_lookup = $this->get_cached_value( $cache_key, (int) $options['cache_ttl'] );
		if ( $cache_lookup['hit'] ) {
			return $cache_lookup['value'];
		}

		$categories = implode( '、', $options['categories'] );

		$schema = [
			'type'       => 'object',
			'required'   => [ 'safe', 'categories' ],
			'properties' => [
				'safe'       => [ 'type' => 'boolean' ],
				'categories' => [
					'type'       => 'object',
					'properties' => array_combine(
						$options['categories'],
						array_fill(
							0,
							count( $options['categories'] ),
							[
								'type'       => 'object',
								'properties' => [
									'flagged' => [ 'type' => 'boolean' ],
									'score'   => [ 'type' => 'number' ],
									'reason'  => [ 'type' => 'string' ],
								],
							]
						)
					),
				],
				'summary'    => [ 'type' => 'string' ],
			],
		];

		$prompt = "请审核以下内容是否包含不当信息。检查类别：{$categories}。\n\n内容：\n{$content}";

		do_action( 'wpmind_before_request', 'moderate', compact( 'content', 'options' ), $context );

		$result = $this->structured_service->structured(
			$prompt,
			$schema,
			[
				'context'     => $context,
				'temperature' => 0.1,
				'retries'     => 2,
			]
		);

		if ( is_wp_error( $result ) ) {
			do_action( 'wpmind_error', $result, 'moderate', compact( 'content', 'options' ) );
			return $result;
		}

		$moderation = [
			'safe'       => $result['data']['safe'] ?? true,
			'categories' => $result['data']['categories'] ?? [],
			'summary'    => $result['data']['summary'] ?? '',
			'provider'   => $result['provider'],
			'model'      => $result['model'],
			'usage'      => $result['usage'],
		];

		do_action( 'wpmind_after_request', 'moderate', $moderation, compact( 'content', 'options' ), $result['usage'] );

		$this->set_cached_value(
			$cache_key,
			$moderation,
			(int) $options['cache_ttl'],
			[
				'type'     => 'moderate',
				'context'  => $context,
				'provider' => $default_provider,
				'model'    => $default_model,
			]
		);

		return $moderation;
	}

	/**
	 * 构建翻译 Prompt
	 *
	 * @param string $text    文本
	 * @param string $from    源语言
	 * @param string $to      目标语言
	 * @param array  $options 选项
	 * @return string
	 */
	private function build_translate_prompt( string $text, string $from, string $to, array $options ): string {
		$lang_names = [
			'zh'   => '中文',
			'en'   => '英文',
			'ja'   => '日文',
			'ko'   => '韩文',
			'fr'   => '法文',
			'de'   => '德文',
			'es'   => '西班牙文',
			'auto' => '自动检测',
		];

		$from_name = $lang_names[ $from ] ?? $from;
		$to_name   = $lang_names[ $to ] ?? $to;

		if ( $options['format'] === 'pinyin' ) {
			$prompt = "将以下中文文本转换为拼音，要求：
1. 按词语分隔，不是按字分隔（如 '你好世界' 应为 'nihao-shijie' 而非 'ni-hao-shi-jie'）
2. 词语之间用连字符 '-' 连接
3. 同一词语内的拼音不加分隔符
4. 全部小写，无声调
5. 保留英文和数字原样
6. 只返回拼音结果，不要其他解释

文本：{$text}";
			return $prompt;
		}

		$prompt = "将以下{$from_name}文本翻译成{$to_name}";

		if ( $options['format'] === 'slug' ) {
			$prompt .= '，输出结果应该适合作为 URL slug，使用小写英文和连字符';
		}

		if ( ! empty( $options['hint'] ) ) {
			$prompt .= "。提示：{$options['hint']}";
		}

		$prompt .= "。只返回翻译结果，不要其他解释：\n\n{$text}";

		return $prompt;
	}
}
