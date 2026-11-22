<?php
/**
 * Optional online /models discovery (manual refresh only; D3).
 *
 * @package WPMind\Provider\Catalog
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Catalog;

use WPMind\Provider\EndpointRepository;
use WPMind\Provider\Secrets;

/**
 * Fetches OpenAI-compatible model ids from a provider base URL.
 *
 * The refresh() method is called lazily by the custom-endpoint auto-model
 * fallback (ChatService) and can also be triggered manually; results are
 * cached for 12h and validated by key/base_url fingerprint.
 *
 * @since 0.13.0
 */
final class RemoteModelLister {

	private const TRANSIENT_PREFIX = 'wpmind_models_remote_';
	private const TTL              = 43200;

	/**
	 * 模型 ID 中明显不是 chat/文本生成的模态标记（启发式黑名单）。
	 *
	 * /models 不返回能力标记，只能按命名排除：图像、音频、视频、embedding、
	 * rerank、OCR、安全审核等。保守起见只列明确模态词，宁可漏过滤也不错杀。
	 */
	private const NON_CHAT_PATTERN = '/embed|image|audio|tts|asr|whisper|realtime|voice|video|veo|sora|kling|jimeng|wan\d|rerank|ocr|moderation|cosyvoice|sensevoice|document|3d|mesh/i';

	/**
	 * Read cached remote model list.
	 *
	 * @param string $provider Provider id.
	 * @return array{ids: list<string>, fetched_at: int, base_url_hash: string, key_fp: string}|null
	 */
	public function cached( string $provider ): ?array {
		$raw = get_transient( self::TRANSIENT_PREFIX . $provider );
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * Invalidate cache when key or base URL changes.
	 *
	 * @param string $provider Provider id.
	 * @return void
	 */
	public function invalidate( string $provider ): void {
		delete_transient( self::TRANSIENT_PREFIX . $provider );
	}

	/**
	 * GET resolved base URL /models and cache ids.
	 *
	 * @param string      $provider Provider id.
	 * @param string      $api_key  API key (Bearer).
	 * @param string|null $base_url 覆盖解析后的 base URL（后台「拉取远程模型」可用未保存的表单值）.
	 * @param bool        $cache    是否写入 transient 缓存；非已保存地址的拉取应传 false.
	 * @return array{ok: bool, ids: list<string>, error?: string}
	 */
	public function refresh( string $provider, string $api_key, ?string $base_url = null, bool $cache = true ): array {
		$repo     = new EndpointRepository();
		$override = null !== $base_url ? untrailingslashit( trim( $base_url ) ) : '';
		$base_url = '' !== $override ? $override : $repo->resolved_base_url( $provider );
		if ( '' === $base_url ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => 'missing_base_url',
			];
		}

		$url  = trailingslashit( $base_url ) . 'models';
		$args = [
			'timeout'               => 5,
			'headers'               => [
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			],
			// Skip WPMind usage tracking for discovery.
			'_wpmind_skip_tracking' => true,
		];

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => $response->get_error_code(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return [
				'ok'    => false,
				'ids'   => [],
				'error' => 'http_' . $code,
			];
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ids  = [];
		if ( is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ) {
			foreach ( $body['data'] as $row ) {
				if ( is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
					$ids[] = $row['id'];
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );
		if ( $cache ) {
			$key_fp  = Secrets::fingerprint( $api_key );
			$payload = [
				'ids'           => $ids,
				'fetched_at'    => time(),
				'base_url_hash' => hash( 'sha256', $base_url ),
				'key_fp'        => $key_fp,
			];
			set_transient( self::TRANSIENT_PREFIX . $provider, $payload, self::TTL );
		}

		return [
			'ok'  => true,
			'ids' => $ids,
		];
	}

	/**
	 * Return cached ids only if key_fp and base_url still match.
	 *
	 * @param string $provider Provider id.
	 * @param string $api_key  API key used to validate fingerprint.
	 * @return list<string>
	 */
	public function ids_if_valid( string $provider, string $api_key ): array {
		$cached = $this->cached( $provider );
		if ( null === $cached ) {
			return [];
		}
		$repo     = new EndpointRepository();
		$base_url = $repo->resolved_base_url( $provider );
		$hash     = hash( 'sha256', $base_url );
		$fp       = Secrets::fingerprint( $api_key );
		if ( $cached['base_url_hash'] !== $hash || $cached['key_fp'] !== $fp ) {
			$this->invalidate( $provider );
			return [];
		}
		return $cached['ids'];
	}

	/**
	 * Filter a model id list down to likely chat/text-generation models.
	 *
	 * Heuristic only: /models carries no capability flags, so ids matching
	 * NON_CHAT_PATTERN (image/audio/video/embedding/rerank/...) are dropped.
	 * Order is preserved; callers pick the first surviving id.
	 *
	 * @param array<int, string> $ids      Raw model ids.
	 * @param string             $provider Provider id; when given, ids known to
	 *                                     the model catalog are filtered by their
	 *                                     declared capabilities ('chat' required)
	 *                                     before falling back to the name heuristic.
	 * @return list<string>
	 */
	public function chat_candidates( array $ids, string $provider = '' ): array {
		$catalog = '' !== $provider ? ModelCatalog::instance() : null;
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $catalog, $provider ): bool {
					if ( '' === $id ) {
						return false;
					}
					if ( null !== $catalog ) {
						$meta       = $catalog->model_meta( $provider, $id );
						$capability = is_array( $meta ) ? ( $meta['capabilities'] ?? null ) : null;
						if ( is_array( $capability ) && [] !== $capability ) {
							return in_array( 'chat', $capability, true );
						}
					}
					return 1 !== preg_match( self::NON_CHAT_PATTERN, $id );
				}
			)
		);
	}
}
