<?php
/**
 * 端点配置 DTO（只读）
 *
 * 只表达用户的选择；name、默认 base_url 等元数据见 ProviderDefinitions。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider;

/**
 * 一家 Provider 的端点配置。
 *
 * @since 0.13.0
 */
final class Endpoint {

	/**
	 * 构造。
	 *
	 * @param string               $id                Provider ID.
	 * @param bool                 $enabled           是否启用.
	 * @param string               $api_key           API key 明文（只在内存里，不要输出）.
	 * @param string               $custom_base_url   用户填的自定义 Base URL，空串表示没填.
	 * @param string[]             $models            用户勾选/自填的模型；空表示用目录默认.
	 * @param string               $default_model     用户选的默认模型；空表示用目录推荐.
	 * @param array<string, mixed> $key_meta          非敏感的 key 元数据（fingerprint、set_at）.
	 * @param bool                 $needs_reconfigure 默认地址已变更，需要管理员重新配置.
	 */
	public function __construct(
		public readonly string $id,
		public readonly bool $enabled = false,
		public readonly string $api_key = '',
		public readonly string $custom_base_url = '',
		public readonly array $models = [],
		public readonly string $default_model = '',
		public readonly array $key_meta = [],
		public readonly bool $needs_reconfigure = false,
	) {}

	/**
	 * 从规范化后的 v2 条目构造。
	 *
	 * @param string               $id    Provider ID.
	 * @param array<string, mixed> $entry EndpointRepository::normalize_entry() 的结果.
	 * @return self
	 */
	public static function from_array( string $id, array $entry ): self {
		return new self(
			$id,
			(bool) ( $entry['enabled'] ?? false ),
			(string) ( $entry['api_key'] ?? '' ),
			(string) ( $entry['custom_base_url'] ?? '' ),
			array_values( (array) ( $entry['models'] ?? [] ) ),
			(string) ( $entry['default_model'] ?? '' ),
			(array) ( $entry['key_meta'] ?? [] ),
			! empty( $entry['needs_reconfigure'] ),
		);
	}

	/**
	 * 是否配置了 key。
	 *
	 * @return bool
	 */
	public function has_api_key(): bool {
		return '' !== $this->api_key;
	}

	/**
	 * 已启用并且有 key。
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return $this->enabled && $this->has_api_key();
	}

	/**
	 * 调试输出时隐藏 key 原文。
	 *
	 * @return array<string, mixed>
	 */
	public function __debugInfo(): array {
		return [
			'id'                => $this->id,
			'enabled'           => $this->enabled,
			'api_key'           => $this->has_api_key() ? Secrets::MASK : '',
			'custom_base_url'   => $this->custom_base_url,
			'models'            => $this->models,
			'default_model'     => $this->default_model,
			'key_meta'          => $this->key_meta,
			'needs_reconfigure' => $this->needs_reconfigure,
		];
	}
}
