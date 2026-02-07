<?php
/**
 * Service 基类
 *
 * 提供所有 Service 共享的基础设施方法
 *
 * @package WPMind
 * @subpackage API\Services
 * @since 3.7.0
 */

declare(strict_types=1);

namespace WPMind\API\Services;

use WP_Error;

/**
 * Service 基类
 *
 * @since 3.7.0
 */
abstract class AbstractService {

	/**
	 * 解析 Provider（'auto' -> 默认值，应用 filter）
	 *
	 * @param string $provider 原始 provider 值
	 * @param string $context  上下文
	 * @return string
	 */
	protected function resolve_provider(string $provider, string $context = ''): string {
		if ($provider === 'auto') {
			$provider = get_option('wpmind_default_provider', 'openai');
		}
		return apply_filters('wpmind_select_provider', $provider, $context);
	}

	/**
	 * 获取故障转移链
	 *
	 * @param string $provider 首选 provider
	 * @return array
	 */
	protected function get_failover_chain(string $provider): array {
		if (!class_exists('\\WPMind\\Failover\\FailoverManager')) {
			return [$provider];
		}

		$failover = \WPMind\Failover\FailoverManager::instance();
		return $failover->get_failover_chain($provider);
	}

	/**
	 * 获取端点配置
	 *
	 * @return array
	 */
	protected function get_endpoints(): array {
		if (!class_exists('\\WPMind\\WPMind')) {
			return [];
		}
		return \WPMind\WPMind::instance()->get_custom_endpoints();
	}

	/**
	 * 记录请求结果到 FailoverManager
	 *
	 * @param string $provider   服务商 ID
	 * @param bool   $success    是否成功
	 * @param int    $latency_ms 延迟毫秒
	 */
	protected function record_result(string $provider, bool $success, int $latency_ms = 0): void {
		if (class_exists('\\WPMind\\Failover\\FailoverManager')) {
			\WPMind\Failover\FailoverManager::instance()->record_result($provider, $success, $latency_ms);
		}
	}

	/**
	 * 通用 failover 执行模板
	 *
	 * 遍历 failover 链，对每个 provider 执行回调，处理成功/失败/记录。
	 * 适用于 embed/transcribe/speech 等简单 failover 场景。
	 *
	 * @param string   $type           请求类型（用于错误消息）
	 * @param string   $provider       首选 provider
	 * @param string   $context        上下文
	 * @param callable $execute_fn     执行函数 fn(string $provider, array $endpoint): array|WP_Error
	 * @param array    $supported_providers 支持的 provider 列表（空数组表示不过滤）
	 * @return array|WP_Error
	 */
	protected function execute_with_failover(
		string $type,
		string $provider,
		string $context,
		callable $execute_fn,
		array $supported_providers = []
	) {
		$failover_chain = $this->get_failover_chain($provider);

		// 过滤出支持的 provider
		if (!empty($supported_providers)) {
			$failover_chain = array_values(array_filter($failover_chain, function ($p) use ($supported_providers) {
				return in_array($p, $supported_providers, true);
			}));

			if (empty($failover_chain)) {
				return new WP_Error(
					"wpmind_{$type}_not_supported",
					sprintf(__('没有可用的 %s 服务商', 'wpmind'), $type)
				);
			}
		}

		$endpoints = $this->get_endpoints();
		$last_error = null;

		foreach ($failover_chain as $try_provider) {
			if (!isset($endpoints[$try_provider])) {
				$last_error = new WP_Error('wpmind_provider_not_found',
					sprintf(__('服务商 %s 未配置', 'wpmind'), $try_provider));
				continue;
			}

			$endpoint = $endpoints[$try_provider];
			$api_key = $endpoint['api_key'] ?? '';

			if (empty($api_key)) {
				$last_error = new WP_Error('wpmind_api_key_missing',
					sprintf(__('服务商 %s 未配置 API Key', 'wpmind'), $try_provider));
				continue;
			}

			$result = $execute_fn($try_provider, $endpoint);

			if (!is_wp_error($result)) {
				return $result;
			}

			$last_error = $result;
		}

		if ($last_error) {
			do_action('wpmind_error', $last_error, $type, []);
			return $last_error;
		}

		return new WP_Error("wpmind_{$type}_failed", sprintf(__('%s 请求失败', 'wpmind'), $type));
	}

	/**
	 * 获取当前模型
	 *
	 * @param string $provider 服务商
	 * @return string
	 */
	protected function get_current_model(string $provider): string {
		if (!class_exists('\\WPMind\\WPMind')) {
			return 'default';
		}

		$endpoints = $this->get_endpoints();

		if (isset($endpoints[$provider]['models']) && is_array($endpoints[$provider]['models'])) {
			return $endpoints[$provider]['models'][0] ?? 'default';
		}

		return 'default';
	}

	/**
	 * 获取默认模型
	 *
	 * @return string
	 */
	protected function get_default_model(): string {
		$provider = get_option('wpmind_default_provider', 'openai');
		return $this->get_current_model($provider);
	}

	/**
	 * 生成缓存键
	 *
	 * @param string $type     类型
	 * @param array  $args     参数
	 * @param string $provider 服务商
	 * @param string $model    模型
	 * @return string
	 */
	protected function generate_cache_key(string $type, array $args, string $provider = '', string $model = ''): string {
		$key = 'wpmind_' . $type . '_' . $provider . '_' . $model . '_' . md5(serialize($args));
		return apply_filters('wpmind_cache_key', $key, $type, $args);
	}
}
