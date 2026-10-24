<?php
/**
 * ErrorClass to Chinese message / recovery / credentials_url.
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Health;

use WPMind\Providers\ProviderRegistrar;

/**
 * Error catalog (PR6).
 */
final class ErrorCatalog {

	/**
	 * Official provider credential pages.
	 *
	 * @var array<string, string>
	 */
	private const OFFICIAL_CREDENTIALS = array(
		'openai'    => 'https://platform.openai.com/api-keys',
		'anthropic' => 'https://console.anthropic.com/settings/keys',
		'google'    => 'https://aistudio.google.com/apikey',
	);

	/**
	 * Lookup: message + recovery + credentials link.
	 *
	 * @return array{class:string, label:string, message:string, recovery:string, credentials_url:string}
	 */
	public static function entry( ErrorClass $class, string $provider = '' ): array {
		$row = self::rows()[ $class->value ] ?? null;
		if ( null === $row ) {
			$row = array(
				'label'    => __( '未知错误', 'wpmind' ),
				'message'  => __( '发生未知错误', 'wpmind' ),
				'recovery' => __( '请查看诊断导出或联系支持。', 'wpmind' ),
			);
		}

		return array(
			'class'           => $class->value,
			'label'           => $row['label'],
			'message'         => $row['message'],
			'recovery'        => $row['recovery'],
			'credentials_url' => self::credentials_url( $provider ),
		);
	}

	/**
	 * User-facing message.
	 */
	public static function message( ErrorClass $class, string $provider = '' ): string {
		return self::entry( $class, $provider )['message'];
	}

	/**
	 * Recovery suggestion.
	 */
	public static function recovery( ErrorClass $class, string $provider = '' ): string {
		return self::entry( $class, $provider )['recovery'];
	}

	/**
	 * Cover every ErrorClass (tests fail if a case is missing).
	 *
	 * @return array<string, array{label:string, message:string, recovery:string}>
	 */
	public static function rows(): array {
		return array(
			ErrorClass::AuthFailed->value       => array(
				'label'    => __( '认证失败', 'wpmind' ),
				'message'  => __( 'API Key 无效、过期或权限不足', 'wpmind' ),
				'recovery' => __( '请到服务商控制台核对或轮换 API Key，保存后点「测试连接」。', 'wpmind' ),
			),
			ErrorClass::QuotaExhausted->value   => array(
				'label'    => __( '额度不足', 'wpmind' ),
				'message'  => __( '账户余额或配额已用尽', 'wpmind' ),
				'recovery' => __( '请到服务商控制台充值或提升配额；冷却结束后会自动探测。', 'wpmind' ),
			),
			ErrorClass::RateLimited->value      => array(
				'label'    => __( '速率限制', 'wpmind' ),
				'message'  => __( '请求过于频繁，已被限流', 'wpmind' ),
				'recovery' => __( '请稍后再试，或降低并发；必要时升级账户套餐。', 'wpmind' ),
			),
			ErrorClass::Timeout->value          => array(
				'label'    => __( '超时', 'wpmind' ),
				'message'  => __( '请求超时', 'wpmind' ),
				'recovery' => __( '请检查网络与 Base URL，或在全局设置中增大超时时间。', 'wpmind' ),
			),
			ErrorClass::Network->value          => array(
				'label'    => __( '网络错误', 'wpmind' ),
				'message'  => __( '无法连接到服务器', 'wpmind' ),
				'recovery' => __( '请检查 DNS、防火墙与自定义 Base URL 是否可达。', 'wpmind' ),
			),
			ErrorClass::ServerError->value      => array(
				'label'    => __( '服务端错误', 'wpmind' ),
				'message'  => __( '上游服务暂时不可用', 'wpmind' ),
				'recovery' => __( '请稍后重试；若持续失败可换下一家或查看服务商状态页。', 'wpmind' ),
			),
			ErrorClass::ModelUnavailable->value => array(
				'label'    => __( '模型不可用', 'wpmind' ),
				'message'  => __( '指定的模型不存在或已下线', 'wpmind' ),
				'recovery' => __( '请改用目录中的推荐模型，或在高级设置中调整默认模型。', 'wpmind' ),
			),
			ErrorClass::BadRequest->value       => array(
				'label'    => __( '请求无效', 'wpmind' ),
				'message'  => __( '请求参数或内容不符合上游要求', 'wpmind' ),
				'recovery' => __( '请检查提示词长度、参数与模型能力；此类错误通常不会自动换家。', 'wpmind' ),
			),
		);
	}

	/**
	 * Provider credentials URL (empty when unknown).
	 */
	public static function credentials_url( string $provider ): string {
		$provider = sanitize_key( $provider );
		if ( '' === $provider ) {
			return '';
		}
		if ( isset( self::OFFICIAL_CREDENTIALS[ $provider ] ) ) {
			return self::OFFICIAL_CREDENTIALS[ $provider ];
		}
		if ( class_exists( ProviderRegistrar::class ) ) {
			$meta = ProviderRegistrar::getConnectorMeta();
			$url  = $meta[ $provider ]['credentials_url'] ?? '';
			return (string) $url;
		}
		return '';
	}

	/**
	 * Rough ErrorClass from HTTP status (for ErrorHandler catalog-first lookup).
	 */
	public static function class_from_http( int $http_code, ?string $raw_error = null ): ?ErrorClass {
		$lower = is_string( $raw_error ) ? strtolower( $raw_error ) : '';
		if ( 401 === $http_code || ( 403 === $http_code && ( str_contains( $lower, 'auth' ) || str_contains( $lower, 'api key' ) || str_contains( $lower, 'unauthorized' ) ) ) ) {
			return ErrorClass::AuthFailed;
		}
		if ( 402 === $http_code || str_contains( $lower, 'insufficient_quota' ) || str_contains( $lower, 'quota' ) || str_contains( $lower, 'balance' ) ) {
			return ErrorClass::QuotaExhausted;
		}
		if ( 429 === $http_code ) {
			return ErrorClass::RateLimited;
		}
		if ( 408 === $http_code || str_contains( $lower, 'timed out' ) || str_contains( $lower, 'timeout' ) ) {
			return ErrorClass::Timeout;
		}
		if ( in_array( $http_code, array( 500, 502, 503, 504, 529 ), true ) ) {
			return ErrorClass::ServerError;
		}
		if ( 404 === $http_code && ( str_contains( $lower, 'model' ) || str_contains( $lower, 'not found' ) ) ) {
			return ErrorClass::ModelUnavailable;
		}
		if ( in_array( $http_code, array( 400, 413, 422 ), true ) ) {
			return ErrorClass::BadRequest;
		}
		if ( $http_code >= 400 ) {
			return ErrorClass::ServerError;
		}
		return null;
	}
}
