<?php
/**
 * 文本 Provider 的静态元数据
 *
 * 只放代码里固定的元数据（名称、图标、默认 Base URL），不放用户选择。
 * 当前推荐模型由 Provider\Catalog\ModelCatalog（models.json）提供；
 * 这里只保存「历史上发过的默认列表」，用来识别 0.12 及以前强制写进 option 的默认副本。
 *
 * @package WPMind
 * @since endpoint schema v2
 */

declare(strict_types=1);

namespace WPMind\Provider;

/**
 * Provider 元数据定义。
 *
 * @since endpoint schema v2
 */
final class ProviderDefinitions {

	/**
	 * 每家 Provider 的元数据（顺序即后台显示顺序）。
	 *
	 * @var array<string, array{name:string, display_name:string, icon:string, base_url:string, is_official?:bool}>
	 */
	public const DEFINITIONS = [
		// WordPress 官方服务.
		'openai'      => [
			'name'         => 'OpenAI',
			'display_name' => 'ChatGPT',
			'icon'         => 'openai',
			'base_url'     => 'https://api.openai.com/v1',
			'is_official'  => true,
		],
		'anthropic'   => [
			'name'         => 'Anthropic',
			'display_name' => 'Claude',
			'icon'         => 'claude',
			'base_url'     => 'https://api.anthropic.com/v1',
			'is_official'  => true,
		],
		'google'      => [
			'name'         => 'Google AI',
			'display_name' => 'Gemini',
			'icon'         => 'gemini',
			'base_url'     => 'https://generativelanguage.googleapis.com/v1beta',
			'is_official'  => true,
		],

		// 国内 AI 服务.
		'deepseek'    => [
			'name'         => 'DeepSeek',
			'display_name' => 'DeepSeek',
			'icon'         => 'deepseek',
			'base_url'     => 'https://api.deepseek.com/v1',
		],
		'qwen'        => [
			'name'         => '通义千问',
			'display_name' => 'Qwen',
			'icon'         => 'qwen',
			'base_url'     => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
		],
		'zhipu'       => [
			'name'         => '智谱 AI',
			'display_name' => 'ChatGLM',
			'icon'         => 'zhipu',
			'base_url'     => 'https://open.bigmodel.cn/api/paas/v4',
		],
		'moonshot'    => [
			'name'         => 'Moonshot',
			'display_name' => 'Kimi',
			'icon'         => 'kimi',
			'base_url'     => 'https://api.moonshot.cn/v1',
		],
		'doubao'      => [
			'name'         => '豆包',
			'display_name' => 'Doubao',
			'icon'         => 'doubao',
			'base_url'     => 'https://ark.cn-beijing.volces.com/api/v3',
		],
		'siliconflow' => [
			'name'         => '硅基流动',
			'display_name' => 'SiliconFlow',
			'icon'         => 'siliconcloud',
			'base_url'     => 'https://api.siliconflow.cn/v1',
		],
		'baidu'       => [
			'name'         => '百度文心',
			'display_name' => 'ERNIE',
			'icon'         => 'wenxin',
			// D7：endpoint schema v2 起默认使用千帆 v2 的 OpenAI 兼容地址.
			'base_url'     => 'https://qianfan.baidubce.com/v2',
		],
		'minimax'     => [
			'name'         => 'MiniMax',
			'display_name' => 'MiniMax',
			'icon'         => 'minimax',
			'base_url'     => 'https://api.minimax.chat/v1',
		],
	];

	/**
	 * 默认地址已经换掉的 Provider：旧版本发过的默认 Base URL。
	 *
	 * 迁移时这些旧地址直接丢弃（它们只是默认值的副本，不是用户填的），
	 * 已配置 key 的站点会被标记 needs_reconfigure，并继续使用第一个旧地址，
	 * 直到管理员重新保存（填新 key 或自定义地址），不会自动切到新地址。
	 *
	 * 来源：git log -p -- wpmind.php（dcfa6f5/3f4450a 起到 7014e73 一直是这个值）。
	 *
	 * @var array<string, list<string>>
	 */
	public const LEGACY_BASE_URLS = [
		'baidu' => [
			'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop',
		],
	];

	/**
	 * 0.11.3 → 0.12.0 期间 get_default_endpoints() 的 models 列表。
	 *
	 * 核对方式：`git show v0.11.3|v0.11.4|v0.11.5|7014e73:wpmind.php`，以及
	 * `git log -p --all -- wpmind.php` 里所有 'models' => [...] 行。四个版本完全相同，
	 * 整个历史上每家只出现过这一份列表（adb6c49 只是重排缩进）。
	 *
	 * @var array<string, list<string>>
	 */
	private const MODELS_0_11_TO_0_12 = [
		'openai'      => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ],
		'anthropic'   => [ 'claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229' ],
		'google'      => [ 'gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash' ],
		'deepseek'    => [ 'deepseek-chat', 'deepseek-coder', 'deepseek-reasoner' ],
		'qwen'        => [ 'qwen-turbo', 'qwen-plus', 'qwen-max' ],
		'zhipu'       => [ 'glm-4', 'glm-4-flash', 'glm-4-plus' ],
		'moonshot'    => [ 'moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k' ],
		'doubao'      => [ 'doubao-pro-4k', 'doubao-pro-32k', 'doubao-pro-128k' ],
		'siliconflow' => [ 'deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct' ],
		'baidu'       => [ 'ernie-4.0-8k', 'ernie-3.5-8k', 'ernie-speed-8k' ],
		'minimax'     => [ 'abab6.5s-chat', 'abab6.5-chat', 'abab5.5-chat' ],
	];

	/**
	 * 历史默认模型列表，按发版版本号。
	 *
	 * 0.12 的 sanitize_endpoints() 保存时强制写入这里的值，所以库里的
	 * models 和其中任何一份完全相等，就说明用户没有自定义过。
	 *
	 * @var array<string, array<string, list<string>>>
	 */
	public const HISTORICAL_DEFAULT_MODELS = [
		'0.11.3' => self::MODELS_0_11_TO_0_12,
		'0.11.4' => self::MODELS_0_11_TO_0_12,
		'0.11.5' => self::MODELS_0_11_TO_0_12,
		'0.12.0' => self::MODELS_0_11_TO_0_12,
	];

	/**
	 * 所有已知 Provider ID。
	 *
	 * @return list<string>
	 */
	public static function ids(): array {
		return array_keys( self::DEFINITIONS );
	}

	/**
	 * 是否是已知 Provider。
	 *
	 * @param string $id Provider ID.
	 * @return bool
	 */
	public static function has( string $id ): bool {
		return isset( self::DEFINITIONS[ $id ] );
	}

	/**
	 * 取一家的元数据。
	 *
	 * @param string $id Provider ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		return self::DEFINITIONS[ $id ] ?? null;
	}

	/**
	 * 代码里的默认 Base URL。
	 *
	 * @param string $id Provider ID.
	 * @return string 未知 Provider 返回空串。
	 */
	public static function default_base_url( string $id ): string {
		return self::DEFINITIONS[ $id ]['base_url'] ?? '';
	}

	/**
	 * 这家历史上发过的旧默认 Base URL（不含当前默认值）。
	 *
	 * @param string $id Provider ID.
	 * @return list<string>
	 */
	public static function legacy_base_urls( string $id ): array {
		return self::LEGACY_BASE_URLS[ $id ] ?? [];
	}

	/**
	 * 这家历史上发过的全部默认模型列表（去重）。
	 *
	 * @param string $id Provider ID.
	 * @return list<list<string>>
	 */
	public static function historical_default_models( string $id ): array {
		$lists = [];
		foreach ( self::HISTORICAL_DEFAULT_MODELS as $per_provider ) {
			if ( isset( $per_provider[ $id ] ) && ! in_array( $per_provider[ $id ], $lists, true ) ) {
				$lists[] = $per_provider[ $id ];
			}
		}
		return $lists;
	}

	/**
	 * 判断 models 是否和某一份历史默认列表完全相等（顺序也要相同）。
	 *
	 * @param string   $id     Provider ID.
	 * @param string[] $models 已规范化的模型列表.
	 * @return bool
	 */
	public static function is_historical_default_models( string $id, array $models ): bool {
		if ( [] === $models ) {
			return false;
		}
		return in_array( $models, self::historical_default_models( $id ), true );
	}
}
