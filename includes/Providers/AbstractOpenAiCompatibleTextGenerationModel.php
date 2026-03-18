<?php
/**
 * OpenAI 兼容文本生成模型抽象基类
 *
 * @package WPMind
 * @since 1.3.0
 */

declare(strict_types=1);

namespace WPMind\Providers;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel as BaseModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * OpenAI 兼容文本生成模型抽象基类
 *
 * @since 1.3.0
 */
abstract class AbstractOpenAiCompatibleTextGenerationModel extends BaseModel {

	/**
	 * 获取 Provider 类名
	 *
	 * @return class-string<AbstractOpenAiCompatibleProvider>
	 */
	abstract protected static function providerClass(): string;

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		$providerClass = static::providerClass();
		return new Request(
			$method,
			$providerClass::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * 重写以强制 n=1，因为大多数国内 AI 服务不支持多候选
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		// 强制 n=1，忽略 candidateCount 配置
		// 大多数国内 AI 服务（DeepSeek、智谱等）只支持 n=1
		$params['n'] = 1;

		return $params;
	}

	/**
	 * {@inheritDoc}
	 *
	 * 重写以提取 completion_tokens_details.reasoning_tokens 作为 thought tokens。
	 * DeepSeek R1 等推理模型会在此字段返回推理 token 消耗。
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		$result = parent::parseResponseToGenerativeAiResult( $response );

		// 检查响应中是否包含 reasoning_tokens
		$responseData    = $response->getData();
		$reasoningTokens = $responseData['usage']['completion_tokens_details']['reasoning_tokens'] ?? null;

		if ( $reasoningTokens === null || ! is_int( $reasoningTokens ) ) {
			return $result;
		}

		// 用包含 thoughtTokens 的 TokenUsage 替换原始结果
		$originalUsage = $result->getTokenUsage();
		$tokenUsage    = new TokenUsage(
			$originalUsage->getPromptTokens(),
			$originalUsage->getCompletionTokens(),
			$originalUsage->getTotalTokens(),
			$reasoningTokens
		);

		return new GenerativeAiResult(
			$result->getId(),
			$result->getCandidates(),
			$tokenUsage,
			$this->providerMetadata(),
			$this->metadata(),
			$result->getAdditionalData()
		);
	}
}
