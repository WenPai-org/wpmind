<?php
/**
 * Exact Cache Manager
 *
 * 为 AI 请求提供精确匹配缓存能力（请求哈希命中）。
 *
 * @package WPMind
 * @subpackage Cache
 * @since 4.0.0
 */

declare(strict_types=1);

namespace WPMind\Cache;

/**
 * Exact Cache 管理器
 *
 * @since 4.0.0
 */
final class ExactCache {

	private const OPTION_ENABLED = 'wpmind_exact_cache_enabled';
	private const OPTION_DEFAULT_TTL = 'wpmind_exact_cache_default_ttl';
	private const OPTION_MAX_ENTRIES = 'wpmind_exact_cache_max_entries';
	private const OPTION_INDEX = 'wpmind_exact_cache_index';
	private const OPTION_STATS = 'wpmind_exact_cache_stats';
	private const KEY_PREFIX = 'wpmind_ec_';
	private const DEFAULT_MAX_ENTRIES = 500;

	private static ?ExactCache $instance = null;

	/**
	 * 内存中累积的统计增量，shutdown 时批量写入。
	 *
	 * @var array{hits:int,misses:int,writes:int,last_hit_at:int,last_miss_at:int,last_write_at:int,last_key:string}
	 */
	private array $pending_stats = [];

	/**
	 * 内存中的索引快照（懒加载），shutdown 时批量写入。
	 *
	 * @var array<string,int>|null null 表示尚未加载
	 */
	private ?array $pending_index = null;

	/**
	 * 索引是否有变更需要写入。
	 *
	 * @var bool
	 */
	private bool $index_dirty = false;

	/**
	 * shutdown hook 是否已注册。
	 *
	 * @var bool
	 */
	private bool $shutdown_registered = false;

	/**
	 * 获取单例
	 *
	 * @return ExactCache
	 */
	public static function instance(): ExactCache {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * 禁止外部实例化
	 */
	private function __construct() {}

	/**
	 * 缓存是否启用
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$raw_value = get_option(self::OPTION_ENABLED, '1');
		$disabled_values = [false, 0, '0', 'false', 'no', 'off'];
		$enabled = !in_array($raw_value, $disabled_values, true);

		return (bool) apply_filters('wpmind_exact_cache_enabled', $enabled);
	}

	/**
	 * 获取最大缓存条目限制
	 *
	 * @return int
	 */
	public function get_max_entries(): int {
		$max_entries = (int) get_option(self::OPTION_MAX_ENTRIES, self::DEFAULT_MAX_ENTRIES);
		if ($max_entries <= 0) {
			$max_entries = self::DEFAULT_MAX_ENTRIES;
		}

		return (int) apply_filters('wpmind_exact_cache_max_entries', $max_entries);
	}

	/**
	 * 获取默认缓存 TTL
	 *
	 * @return int
	 */
	public function get_default_ttl(): int {
		$default_ttl = (int) get_option(self::OPTION_DEFAULT_TTL, 900);
		$default_ttl = max(0, min(86400, $default_ttl));

		return (int) apply_filters('wpmind_exact_cache_default_ttl', $default_ttl);
	}

	/**
	 * 生成精确缓存键
	 *
	 * @param string $type 请求类型
	 * @param array  $args 请求参数
	 * @param string $provider Provider
	 * @param string $model 模型
	 * @return string
	 */
	public function build_key(string $type, array $args, string $provider = '', string $model = ''): string {
		$key_data = [
			'v'        => 1,
			'type'     => $type,
			'provider' => $provider !== '' ? $provider : 'auto',
			'model'    => $model !== '' ? $model : 'auto',
			'scope'    => $this->build_scope(),
			'args'     => $this->normalize_for_hash($args),
		];

		$json = wp_json_encode($key_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json) || $json === '') {
			$json = serialize($key_data);
		}

		$key = self::KEY_PREFIX . substr(hash('sha256', $json), 0, 40);

		return (string) apply_filters('wpmind_exact_cache_key', $key, $key_data);
	}

	/**
	 * 读取缓存
	 *
	 * @param string $cache_key 缓存键
	 * @return mixed|null
	 */
	public function get(string $cache_key) {
		if (!$this->is_enabled()) {
			return null;
		}

		$cached = get_transient($cache_key);
		if ($cached === false || !is_array($cached) || !array_key_exists('payload', $cached)) {
			$this->buffer_stat('misses', $cache_key);
			if ($this->index_has($cache_key)) {
				$this->remove_from_index($cache_key);
			}
			do_action('wpmind_exact_cache_miss', $cache_key);

			return null;
		}

		$this->buffer_stat('hits', $cache_key);
		do_action('wpmind_exact_cache_hit', $cache_key, $cached['meta'] ?? []);

		return $cached['payload'];
	}

	/**
	 * 写入缓存
	 *
	 * @param string $cache_key 缓存键
	 * @param mixed  $value 缓存值
	 * @param int    $ttl TTL 秒数
	 * @param array  $meta 元数据
	 * @return bool
	 */
	public function set(string $cache_key, $value, int $ttl, array $meta = []): bool {
		if (!$this->is_enabled() || $ttl <= 0) {
			return false;
		}

		$stored_payload = [
			'payload'    => $value,
			'meta'       => $meta,
			'stored_at'  => time(),
			'expires_in' => $ttl,
		];

		$saved = set_transient($cache_key, $stored_payload, $ttl);
		if (!$saved) {
			return false;
		}

		$this->touch_index($cache_key);
		$this->enforce_max_entries();
		$this->buffer_stat('writes', $cache_key);
		do_action('wpmind_exact_cache_store', $cache_key, $meta, $ttl);

		return true;
	}

	/**
	 * 删除单个缓存
	 *
	 * @param string $cache_key 缓存键
	 * @return void
	 */
	public function delete(string $cache_key): void {
		delete_transient($cache_key);
		$this->remove_from_index($cache_key);
	}

	/**
	 * 清空所有精确缓存（按索引）
	 *
	 * @return void
	 */
	public function flush(): void {
		$index = $this->load_index();

		foreach (array_keys($index) as $cache_key) {
			delete_transient($cache_key);
		}

		$this->pending_index = [];
		$this->index_dirty = false;
		$this->pending_stats = [];

		delete_option(self::OPTION_INDEX);
		update_option(self::OPTION_STATS, $this->get_default_stats(), false);
	}

	/**
	 * 获取缓存统计
	 *
	 * @return array
	 */
	public function get_stats(): array {
		$stats = get_option(self::OPTION_STATS, []);
		$stats = wp_parse_args(is_array($stats) ? $stats : [], $this->get_default_stats());

		// 合并尚未写入的内存增量
		foreach (['hits', 'misses', 'writes'] as $metric) {
			if (isset($this->pending_stats[$metric])) {
				$stats[$metric] = (int) $stats[$metric] + (int) $this->pending_stats[$metric];
			}
		}

		$total_requests = (int) $stats['hits'] + (int) $stats['misses'];
		$hit_rate = $total_requests > 0
			? round(((int) $stats['hits'] / $total_requests) * 100, 2)
			: 0.0;

		$stats['enabled'] = $this->is_enabled();
		$stats['hit_rate'] = $hit_rate;
		$stats['entries'] = count($this->load_index());
		$stats['max_entries'] = $this->get_max_entries();

		return $stats;
	}

	/**
	 * 获取索引条目列表（供管理界面使用）
	 *
	 * @return array<int, array{key: string, last_access: int}>
	 */
	public function get_entries(): array {
		$index = $this->load_index();
		arsort($index, SORT_NUMERIC);
		$entries = [];
		foreach ($index as $key => $timestamp) {
			$entries[] = [
				'key'         => $key,
				'last_access' => (int) $timestamp,
			];
		}
		return $entries;
	}

	/**
	 * 构建缓存作用域（避免跨站点/跨角色污染）
	 *
	 * @return array
	 */
	private function build_scope(): array {
		$scope = [
			'blog_id' => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0,
			'locale'  => function_exists('get_locale') ? (string) get_locale() : '',
		];

		$scope_mode = (string) apply_filters('wpmind_exact_cache_scope_mode', 'role');
		if ($scope_mode === 'user') {
			$scope['user_id'] = get_current_user_id();
			return $scope;
		}

		if ($scope_mode === 'none') {
			$scope['segment'] = 'global';
			return $scope;
		}

		$user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
		$roles = [];
		if ($user && !empty($user->roles) && is_array($user->roles)) {
			$roles = array_values($user->roles);
			sort($roles);
		}

		$scope['roles'] = $roles;

		return $scope;
	}

	/**
	 * 归一化数组/对象，保证哈希稳定
	 *
	 * @param mixed $value 原始值
	 * @return mixed
	 */
	private function normalize_for_hash($value) {
		if (is_array($value)) {
			if ($this->is_assoc_array($value)) {
				ksort($value);
			}

			foreach ($value as $key => $child) {
				$value[$key] = $this->normalize_for_hash($child);
			}

			return $value;
		}

		if (is_object($value)) {
			return $this->normalize_for_hash(get_object_vars($value));
		}

		return $value;
	}

	/**
	 * 判断数组是否为关联数组
	 *
	 * @param array $array 数组
	 * @return bool
	 */
	private function is_assoc_array(array $array): bool {
		if ($array === []) {
			return false;
		}

		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * 懒加载索引到内存
	 *
	 * @return array<string,int>
	 */
	private function load_index(): array {
		if ($this->pending_index === null) {
			$index = get_option(self::OPTION_INDEX, []);
			$this->pending_index = is_array($index) ? $index : [];
		}

		return $this->pending_index;
	}

	/**
	 * 检查索引中是否存在指定键
	 *
	 * @param string $cache_key 缓存键
	 * @return bool
	 */
	private function index_has(string $cache_key): bool {
		$index = $this->load_index();
		return isset($index[$cache_key]);
	}

	/**
	 * 更新索引访问时间（内存操作，shutdown 时写入）
	 *
	 * @param string $cache_key 缓存键
	 * @return void
	 */
	private function touch_index(string $cache_key): void {
		$this->load_index();
		$this->pending_index[$cache_key] = time();
		$this->index_dirty = true;
		$this->register_shutdown();
	}

	/**
	 * 从索引中移除键（内存操作，shutdown 时写入）
	 *
	 * @param string $cache_key 缓存键
	 * @return void
	 */
	private function remove_from_index(string $cache_key): void {
		$this->load_index();
		if (!isset($this->pending_index[$cache_key])) {
			return;
		}

		unset($this->pending_index[$cache_key]);
		$this->index_dirty = true;
		$this->register_shutdown();
	}

	/**
	 * 强制执行容量上限（LRU 近似：最早写入优先淘汰）
	 *
	 * @return void
	 */
	private function enforce_max_entries(): void {
		$max_entries = $this->get_max_entries();
		$index = $this->load_index();
		$current_count = count($index);

		if ($current_count <= $max_entries) {
			return;
		}

		asort($index, SORT_NUMERIC);
		$remove_count = $current_count - $max_entries;
		$expired_keys = array_slice(array_keys($index), 0, $remove_count);

		foreach ($expired_keys as $cache_key) {
			delete_transient($cache_key);
			unset($index[$cache_key]);
		}

		$this->pending_index = $index;
		$this->index_dirty = true;
		$this->register_shutdown();
	}

	/**
	 * 累积统计增量到内存（shutdown 时批量写入）
	 *
	 * @param string      $metric 命中项（hits/misses/writes）
	 * @param string|null $cache_key 缓存键
	 * @return void
	 */
	private function buffer_stat(string $metric, ?string $cache_key = null): void {
		if (!isset($this->pending_stats[$metric])) {
			$this->pending_stats[$metric] = 0;
		}
		$this->pending_stats[$metric]++;

		$timestamp_fields = [
			'hits'   => 'last_hit_at',
			'misses' => 'last_miss_at',
			'writes' => 'last_write_at',
		];

		if (isset($timestamp_fields[$metric])) {
			$this->pending_stats[$timestamp_fields[$metric]] = time();
		}

		if ($cache_key !== null) {
			$this->pending_stats['last_key'] = $cache_key;
		}

		$this->register_shutdown();
	}

	/**
	 * 注册 shutdown hook（仅一次）
	 *
	 * @return void
	 */
	private function register_shutdown(): void {
		if ($this->shutdown_registered) {
			return;
		}

		add_action('shutdown', [$this, 'flush_pending']);
		$this->shutdown_registered = true;
	}

	/**
	 * 将内存中的统计和索引批量写入数据库。
	 *
	 * 由 shutdown hook 调用，每次请求最多写入 2 次 DB（stats + index）。
	 *
	 * @return void
	 */
	public function flush_pending(): void {
		// 写入统计增量
		if (!empty($this->pending_stats)) {
			$stats = get_option(self::OPTION_STATS, []);
			$stats = wp_parse_args(is_array($stats) ? $stats : [], $this->get_default_stats());

			foreach (['hits', 'misses', 'writes'] as $metric) {
				if (isset($this->pending_stats[$metric])) {
					$stats[$metric] = (int) $stats[$metric] + (int) $this->pending_stats[$metric];
				}
			}

			foreach (['last_hit_at', 'last_miss_at', 'last_write_at', 'last_key'] as $field) {
				if (isset($this->pending_stats[$field])) {
					$stats[$field] = $this->pending_stats[$field];
				}
			}

			update_option(self::OPTION_STATS, $stats, false);
			$this->pending_stats = [];
		}

		// 写入索引变更
		if ($this->index_dirty && $this->pending_index !== null) {
			update_option(self::OPTION_INDEX, $this->pending_index, false);
			$this->index_dirty = false;
		}
	}

	/**
	 * 默认统计结构
	 *
	 * @return array
	 */
	private function get_default_stats(): array {
		return [
			'hits'          => 0,
			'misses'        => 0,
			'writes'        => 0,
			'last_hit_at'   => 0,
			'last_miss_at'  => 0,
			'last_write_at' => 0,
			'last_key'      => '',
		];
	}
}
