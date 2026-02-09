<?php
/**
 * WPMind 公共 API 主类（Facade）
 *
 * 所有公共方法委托给对应的 Service 类，
 * Facade 层负责递归保护和单例管理。
 *
 * @package WPMind
 * @subpackage API
 * @since 2.5.0
 */

declare(strict_types=1);

namespace WPMind\API;

use WP_Error;
use WPMind\API\Services\ChatService;
use WPMind\API\Services\VisionHelper;
use WPMind\API\Services\TextProcessingService;
use WPMind\API\Services\StructuredOutputService;
use WPMind\API\Services\EmbeddingService;
use WPMind\API\Services\AudioService;
use WPMind\API\Services\ImageService;

/**
 * 公共 API 主类（Facade）
 *
 * @since 2.5.0
 */
class PublicAPI {

	/** @var PublicAPI|null */
	private static $instance = null;

	/** @var array 调用栈追踪（防止循环调用） */
	private static $call_stack = [];

	/** @var int 最大调用深度 */
	private static $max_call_depth = 3;

	/** @var ChatService */
	private ChatService $chat_service;

	/** @var StructuredOutputService */
	private StructuredOutputService $structured_service;

	/** @var TextProcessingService */
	private TextProcessingService $text_service;

	/** @var EmbeddingService */
	private EmbeddingService $embedding_service;

	/** @var AudioService */
	private AudioService $audio_service;

	/** @var ImageService */
	private ImageService $image_service;

	/**
	 * 获取单例实例
	 *
	 * @return PublicAPI
	 */
	public static function instance(): PublicAPI {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 构造函数
	 */
	private function __construct() {
		// 初始化 Service 实例（依赖注入）
		$this->chat_service       = new ChatService();
		$this->structured_service = new StructuredOutputService($this->chat_service);
		$this->text_service       = new TextProcessingService($this->chat_service, $this->structured_service);
		$this->embedding_service  = new EmbeddingService();
		$this->audio_service      = new AudioService();
		$this->image_service      = new ImageService();

		$this->register_hooks();
	}

	// ============================================
	// 递归保护
	// ============================================

	/**
	 * 检查是否存在循环调用
	 *
	 * @param string $method  方法名
	 * @param string $call_id 调用标识
	 * @return bool|WP_Error
	 */
	private function check_recursive_call(string $method, string $call_id) {
		$key = $method . ':' . $call_id;

		if (isset(self::$call_stack[$key])) {
			return ErrorHandler::recursive_call($method, $call_id);
		}

		$method_count = 0;
		foreach (self::$call_stack as $stack_key => $value) {
			if (strpos($stack_key, $method . ':') === 0) {
				$method_count++;
			}
		}

		if ($method_count >= self::$max_call_depth) {
			return ErrorHandler::call_depth_exceeded($method, $method_count, self::$max_call_depth);
		}

		return true;
	}

	private function begin_call(string $method, string $call_id): void {
		self::$call_stack[$method . ':' . $call_id] = microtime(true);
	}

	private function end_call(string $method, string $call_id): void {
		unset(self::$call_stack[$method . ':' . $call_id]);
	}

	private function generate_call_id($args): string {
		return md5(serialize($args));
	}

	// ============================================
	// Hook 注册
	// ============================================

	private function register_hooks(): void {
		add_filter('wpmind_chat_response', [$this->chat_service, 'filter_chat_response'], 10, 3);
	}

	// ============================================
	// 状态方法（保留在 Facade）
	// ============================================

	/**
	 * 检查 WPMind 是否可用
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		static $cached_result = null;

		if ($cached_result !== null) {
			return $cached_result;
		}

		if (!class_exists('\\WPMind\\WPMind')) {
			$cached_result = false;
			return false;
		}

		$wpmind = \WPMind\WPMind::instance();
		$endpoints = $wpmind->get_custom_endpoints();

		if (empty($endpoints)) {
			$cached_result = false;
			return false;
		}

		foreach ($endpoints as $endpoint) {
			if (!empty($endpoint['enabled']) && !empty($endpoint['api_key'])) {
				$cached_result = true;
				return true;
			}
		}

		$cached_result = false;
		return false;
	}

	/**
	 * 获取状态信息
	 *
	 * @return array
	 */
	public function get_status(): array {
		$endpoints = get_option('wpmind_custom_endpoints', []);
		$default_provider = get_option('wpmind_default_provider', '');

		$today_tokens = 0;
		$month_tokens = 0;

		if (class_exists('\\WPMind\\Modules\\CostControl\\UsageTracker')) {
			$today_stats = \WPMind\Modules\CostControl\UsageTracker::get_today_stats();
			$month_stats = \WPMind\Modules\CostControl\UsageTracker::get_month_stats();
			$today_tokens = $today_stats['total_tokens'] ?? 0;
			$month_tokens = $month_stats['total_tokens'] ?? 0;
		}

		return [
			'available' => $this->is_available(),
			'provider'  => $default_provider,
			'model'     => $this->chat_service->get_current_model_public($default_provider),
			'usage'     => [
				'today' => $today_tokens,
				'month' => $month_tokens,
				'limit' => 0,
			],
			'cache'     => $this->get_exact_cache_stats(),
		];
	}


	/**
	 * 获取精确缓存统计
	 *
	 * @return array
	 */
	public function get_exact_cache_stats(): array {
		if (!class_exists('\WPMind\Cache\ExactCache')) {
			return [
				'enabled'     => false,
				'hits'        => 0,
				'misses'      => 0,
				'writes'      => 0,
				'hit_rate'    => 0,
				'entries'     => 0,
				'max_entries' => 0,
			];
		}

		return \WPMind\Cache\ExactCache::instance()->get_stats();
	}

	/**
	 * 计算 Token 数量（估算）
	 *
	 * @since 2.6.0
	 * @param string|array $content 文本内容或消息数组
	 * @return int
	 */
	public function count_tokens($content): int {
		if (is_array($content)) {
			$text = '';
			foreach ($content as $msg) {
				if (isset($msg['content'])) {
					$text .= $msg['content'] . ' ';
				}
			}
			$content = $text;
		}

		$chinese_chars = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $content, $matches);
		$other_chars = mb_strlen($content) - $chinese_chars;

		$estimated_tokens = (int)($chinese_chars / 1.5 + $other_chars / 4);

		return max(1, $estimated_tokens);
	}

	// ============================================
	// 公共 API 委托（递归保护 + 转发到 Service）
	// ============================================

	/**
	 * AI 对话
	 *
	 * @param array|string $messages 消息或简单 Prompt
	 * @param array        $options  选项
	 * @return array|WP_Error
	 */
	public function chat($messages, array $options = []) {
		$call_id = $this->generate_call_id(['messages' => $messages, 'options' => $options]);

		$recursive_check = $this->check_recursive_call('chat', $call_id);
		if (is_wp_error($recursive_check)) {
			return $recursive_check;
		}

		$this->begin_call('chat', $call_id);

		try {
			return $this->chat_service->chat($messages, $options);
		} finally {
			$this->end_call('chat', $call_id);
		}
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
	public function translate(string $text, string $from = 'auto', string $to = 'en', array $options = []) {
		$call_id = $this->generate_call_id(['text' => $text, 'from' => $from, 'to' => $to, 'options' => $options]);

		$recursive_check = $this->check_recursive_call('translate', $call_id);
		if (is_wp_error($recursive_check)) {
			return $recursive_check;
		}

		$this->begin_call('translate', $call_id);

		try {
			return $this->text_service->translate($text, $from, $to, $options);
		} finally {
			$this->end_call('translate', $call_id);
		}
	}

	/**
	 * 生成图像
	 *
	 * @param string $prompt  图像描述
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function generate_image(string $prompt, array $options = []) {
		return $this->image_service->generate_image($prompt, $options);
	}

	/**
	 * AI 图像理解（Vision）
	 *
	 * @since 4.3.0
	 * @param string $image_url 图片 URL 或 base64 data URI
	 * @param string $prompt    提示词
	 * @param array  $options   选项
	 * @return array|WP_Error
	 */
	public function vision( string $image_url, string $prompt = '', array $options = [] ) {
		$defaults = [
			'system'      => '',
			'max_tokens'  => 300,
			'temperature' => 0.3,
			'provider'    => 'auto',
			'language'    => get_locale() === 'zh_CN' ? 'zh' : 'en',
		];
		$options = wp_parse_args( $options, $defaults );

		if ( 'auto' === $options['provider'] ) {
			$options['provider'] = VisionHelper::get_vision_provider();
			$options['model']    = VisionHelper::get_vision_model( $options['provider'] );
		}

		$messages = VisionHelper::build_vision_messages( $image_url, $prompt, $options['system'] );
		unset( $options['system'] );

		return $this->chat( $messages, $options );
	}

	/**
	 * 流式输出
	 *
	 * @since 2.6.0
	 * @param array|string $messages 消息
	 * @param callable     $callback 回调函数
	 * @param array        $options  选项
	 * @return bool|WP_Error
	 */
	public function stream($messages, callable $callback, array $options = []) {
		return $this->chat_service->stream($messages, $callback, $options);
	}

	/**
	 * 结构化输出（JSON Schema）
	 *
	 * @since 2.6.0
	 * @param array|string $messages 消息
	 * @param array        $schema   JSON Schema
	 * @param array        $options  选项
	 * @return array|WP_Error
	 */
	public function structured($messages, array $schema, array $options = []) {
		return $this->structured_service->structured($messages, $schema, $options);
	}

	/**
	 * 批量处理
	 *
	 * @since 2.6.0
	 * @param array  $items          要处理的项目数组
	 * @param string $prompt_template Prompt 模板
	 * @param array  $options         选项
	 * @return array|WP_Error
	 */
	public function batch(array $items, string $prompt_template, array $options = []) {
		return $this->structured_service->batch($items, $prompt_template, $options);
	}

	/**
	 * 文本嵌入向量
	 *
	 * @since 2.6.0
	 * @param string|array $texts   要嵌入的文本
	 * @param array        $options 选项
	 * @return array|WP_Error
	 */
	public function embed($texts, array $options = []) {
		return $this->embedding_service->embed($texts, $options);
	}

	/**
	 * 文本摘要
	 *
	 * @since 2.7.0
	 * @param string $text    要摘要的文本
	 * @param array  $options 选项
	 * @return string|WP_Error
	 */
	public function summarize(string $text, array $options = []) {
		return $this->text_service->summarize($text, $options);
	}

	/**
	 * 内容审核
	 *
	 * @since 2.7.0
	 * @param string $content 要审核的内容
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function moderate(string $content, array $options = []) {
		return $this->text_service->moderate($content, $options);
	}

	/**
	 * 音频转录
	 *
	 * @since 2.7.0
	 * @param string $audio_file 音频文件路径或 URL
	 * @param array  $options    选项
	 * @return array|WP_Error
	 */
	public function transcribe(string $audio_file, array $options = []) {
		return $this->audio_service->transcribe($audio_file, $options);
	}

	/**
	 * 文本转语音
	 *
	 * @since 2.7.0
	 * @param string $text    要转换的文本
	 * @param array  $options 选项
	 * @return array|WP_Error
	 */
	public function speech(string $text, array $options = []) {
		return $this->audio_service->speech($text, $options);
	}
}
