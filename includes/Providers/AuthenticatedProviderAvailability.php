<?php
/**
 * 带认证的 Provider 可用性检查
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;

/**
 * 带认证的 Provider 可用性检查
 *
 * 与 GenerateTextApiBasedProviderAvailability 不同，此类实现了
 * WithRequestAuthenticationInterface 和 WithHttpTransporterInterface，
 * 允许 ProviderRegistry 将 API Key 认证和 HTTP transporter 传递给它，
 * 然后再传递给模型实例。
 *
 * @since 1.3.0
 */
class AuthenticatedProviderAvailability implements
	ProviderAvailabilityInterface,
	WithRequestAuthenticationInterface,
	WithHttpTransporterInterface {

	use WithRequestAuthenticationTrait;
	use WithHttpTransporterTrait;

	/**
	 * @var ModelInterface&TextGenerationModelInterface 用于检查可用性的模型
	 */
	private ModelInterface $model;

	/**
	 * 构造函数
	 *
	 * @param ModelInterface $model 用于检查可用性的模型
	 * @throws \Exception 如果模型不实现 TextGenerationModelInterface
	 */
	public function __construct( ModelInterface $model ) {
		if ( ! ( $model instanceof TextGenerationModelInterface ) ) {
			throw new \Exception(
				'The model class to check provider availability must implement TextGenerationModelInterface.'
			);
		}
		$this->model = $model;
	}

	/**
	 * Model retained for registry wiring / future PR4 health probes.
	 *
	 * @return ModelInterface&TextGenerationModelInterface
	 */
	public function getModel(): ModelInterface {
		return $this->model;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.3.0
	 * @since 0.13.0 改为本地检查，不再发付费探测请求。
	 */
	public function isConfigured(): bool {
		// 只看本地是否已配置认证，不发任何网络请求。
		// （旧实现用 max_tokens=1 真打生成，打开连接器页/每次生成都会额外计费。）
		try {
			$auth = $this->getRequestAuthentication();
		} catch ( \Throwable $e ) {
			return false;
		}

		if ( \method_exists( $auth, 'getApiKey' ) ) {
			$key = (string) $auth->getApiKey();
			return '' !== \trim( $key );
		}

		if ( \method_exists( $auth, 'getToken' ) ) {
			$token = (string) $auth->getToken();
			return '' !== \trim( $token );
		}

		// 有认证对象即视为已配置（PR4 合入后再叠加 auth_failed 状态）。
		return true;
	}
}
