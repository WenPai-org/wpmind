<?php
/**
 * Model Mapper
 *
 * Resolves model names to provider/model pairs.
 *
 * @package WPMind\Modules\ApiGateway\Transform
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Transform;

/**
 * Class ModelMapper
 *
 * Static utility for mapping OpenAI-compatible model identifiers
 * to WPMind provider and model pairs.
 */
final class ModelMapper {

	/**
	 * Default model mapping table.
	 *
	 * @var array<string, array{provider: string, model: string}>
	 */
	private static array $default_map = [
		'gpt-4o'                     => [
			'provider' => 'openai',
			'model'    => 'gpt-4o',
		],
		'gpt-4o-mini'                => [
			'provider' => 'openai',
			'model'    => 'gpt-4o-mini',
		],
		'gpt-4-turbo'                => [
			'provider' => 'openai',
			'model'    => 'gpt-4-turbo',
		],
		'gpt-3.5-turbo'              => [
			'provider' => 'openai',
			'model'    => 'gpt-3.5-turbo',
		],
		'claude-3-5-sonnet-20241022' => [
			'provider' => 'anthropic',
			'model'    => 'claude-3-5-sonnet-20241022',
		],
		'claude-3-5-haiku-20241022'  => [
			'provider' => 'anthropic',
			'model'    => 'claude-3-5-haiku-20241022',
		],
		'claude-3-opus-20240229'     => [
			'provider' => 'anthropic',
			'model'    => 'claude-3-opus-20240229',
		],
		'gemini-2.0-flash-exp'       => [
			'provider' => 'google',
			'model'    => 'gemini-2.0-flash-exp',
		],
		'gemini-1.5-pro'             => [
			'provider' => 'google',
			'model'    => 'gemini-1.5-pro',
		],
		'deepseek-chat'              => [
			'provider' => 'deepseek',
			'model'    => 'deepseek-chat',
		],
		'deepseek-coder'             => [
			'provider' => 'deepseek',
			'model'    => 'deepseek-coder',
		],
		'deepseek-reasoner'          => [
			'provider' => 'deepseek',
			'model'    => 'deepseek-reasoner',
		],
		'qwen-turbo'                 => [
			'provider' => 'qwen',
			'model'    => 'qwen-turbo',
		],
		'qwen-plus'                  => [
			'provider' => 'qwen',
			'model'    => 'qwen-plus',
		],
		'qwen-max'                   => [
			'provider' => 'qwen',
			'model'    => 'qwen-max',
		],
		'moonshot-v1-8k'             => [
			'provider' => 'moonshot',
			'model'    => 'moonshot-v1-8k',
		],
		'glm-4'                      => [
			'provider' => 'zhipu',
			'model'    => 'glm-4',
		],
		'glm-4-flash'                => [
			'provider' => 'zhipu',
			'model'    => 'glm-4-flash',
		],
	];

	/**
	 * Resolve a model identifier to a provider/model pair.
	 *
	 * Checks user-configured aliases first, then the default map.
	 * The special value 'auto' delegates to IntelligentRouter.
	 *
	 * @param string $model Model identifier from the API request.
	 * @return array{provider: string, model: string}|null Resolved pair or null if unknown.
	 */
	public static function resolve( string $model ): ?array {
		if ( $model === 'auto' ) {
			return [
				'provider' => 'auto',
				'model'    => 'auto',
			];
		}

		$aliases = get_option( 'wpmind_gateway_model_aliases', [] );

		if ( is_array( $aliases ) && isset( $aliases[ $model ] ) ) {
			$alias = $aliases[ $model ];

			if ( is_array( $alias ) && isset( $alias['provider'], $alias['model'] ) ) {
				return [
					'provider' => (string) $alias['provider'],
					'model'    => (string) $alias['model'],
				];
			}
		}

		return self::$default_map[ $model ] ?? null;
	}

	/**
	 * Get all available model identifiers.
	 *
	 * Merges default models with user-configured aliases.
	 *
	 * @return string[] List of model IDs.
	 */
	public static function get_available_models(): array {
		$aliases = get_option( 'wpmind_gateway_model_aliases', [] );
		$models  = array_keys( self::$default_map );

		if ( is_array( $aliases ) ) {
			$models = array_unique( array_merge( $models, array_keys( $aliases ) ) );
		}

		$models[] = 'auto';

		sort( $models );

		return $models;
	}
}
