<?php
/**
 * 带认证的 Provider 可用性检查
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use Exception;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
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
    WithHttpTransporterInterface
{
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
     * @throws Exception 如果模型不实现 TextGenerationModelInterface
     */
    public function __construct(ModelInterface $model)
    {
        if (!($model instanceof TextGenerationModelInterface)) {
            throw new Exception(
                'The model class to check provider availability must implement TextGenerationModelInterface.'
            );
        }
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.3.0
     */
    public function isConfigured(): bool
    {
        // 将 HTTP transporter 传递给模型
        if ($this->model instanceof WithHttpTransporterInterface) {
            $this->model->setHttpTransporter($this->getHttpTransporter());
        }

        // 将认证传递给模型
        if ($this->model instanceof WithRequestAuthenticationInterface) {
            $this->model->setRequestAuthentication($this->getRequestAuthentication());
        }

        // 设置最小资源配置
        $modelConfig = ModelConfig::fromArray([
            ModelConfig::KEY_MAX_TOKENS => 1,
        ]);
        $this->model->setConfig($modelConfig);

        try {
            // 尝试生成文本以检查 Provider 是否可用
            $this->model->generateTextResult([
                new Message(
                    MessageRoleEnum::user(),
                    [new MessagePart('a')]
                ),
            ]);
            return true;
        } catch (Exception $e) {
            // 如果发生异常，Provider 不可用
            return false;
        }
    }
}
