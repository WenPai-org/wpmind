<?php
/**
 * 密钥指纹与脱敏工具
 *
 * 任何日志、WP_Error 消息/数据、诊断导出、do_action 参数在输出前都要先过 redact()。
 *
 * @package WPMind
 * @since endpoint schema v2
 */

declare(strict_types=1);

namespace WPMind\Provider;

/**
 * 密钥工具。
 *
 * @since endpoint schema v2
 */
final class Secrets {

	/**
	 * 脱敏后的占位符。
	 */
	public const MASK = '[REDACTED]';

	/**
	 * 已知 key 的最短长度；更短的值不当作 key 替换，避免误伤普通文本。
	 */
	private const MIN_KNOWN_KEY_LENGTH = 6;

	/**
	 * 计算不可逆的 key 指纹（HMAC-SHA256，取前 12 位十六进制）。
	 *
	 * @param string $key API key.
	 * @return string 空 key 返回空串。
	 */
	public static function fingerprint( string $key ): string {
		if ( '' === $key ) {
			return '';
		}
		return substr( hash_hmac( 'sha256', $key, self::salt() ), 0, 12 );
	}

	/**
	 * 把文本里的密钥替换成占位符。
	 *
	 * 覆盖：已知 key 原文、`sk-…`、`bce-v3/…`、URL 查询串里的 `key=`/`api_key=`/`token=` 等、`Bearer …`。
	 *
	 * @param string       $text       原文.
	 * @param array<mixed> $known_keys 已知的 key 原文（比如当前保存的各家 key）；非字符串会被忽略.
	 * @return string
	 */
	public static function redact( string $text, array $known_keys = [] ): string {
		if ( '' === $text ) {
			return '';
		}

		$known = [];
		foreach ( $known_keys as $key ) {
			if ( is_string( $key ) && strlen( $key ) >= self::MIN_KNOWN_KEY_LENGTH ) {
				$known[] = $key;
			}
		}
		// 先替换长的，避免一个 key 是另一个 key 的前缀时只替换一半.
		usort(
			$known,
			static function ( string $a, string $b ): int {
				return strlen( $b ) <=> strlen( $a );
			}
		);
		foreach ( $known as $key ) {
			$text = str_replace( $key, self::MASK, $text );
		}

		$patterns = [
			// Authorization: Bearer xxx.
			[ '/(\bBearer\s+)[A-Za-z0-9._~+\/=\-]+/i', '$1' . self::MASK ],
			// 百度千帆 bce-v3/ALTAK-xxx/yyy.
			[ '/\bbce-v3\/[A-Za-z0-9._~+\/=\-]+/', self::MASK ],
			// OpenAI 兼容 sk-xxx / sk-proj-xxx.
			[ '/\bsk-[A-Za-z0-9_\-]{8,}/', self::MASK ],
			// URL 查询串 ?key=xxx / &api_key=xxx / &access_token=xxx.
			[ '/([?&](?:key|api_key|apikey|api-key|access_token|token)=)[^&#\s"\'<>]+/i', '$1' . self::MASK ],
		];
		foreach ( $patterns as [ $pattern, $replacement ] ) {
			$replaced = preg_replace( $pattern, $replacement, $text );
			if ( is_string( $replaced ) ) {
				$text = $replaced;
			}
		}

		return $text;
	}

	/**
	 * HMAC 用的盐。
	 *
	 * @return string
	 */
	private static function salt(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' );
		}
		return 'wpmind-fingerprint';
	}
}
