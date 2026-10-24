<?php
/**
 * Provider 主动健康检查。
 *
 * 默认「测试连接」= GET /models（免费）；深度测试才 max_tokens=1。
 * cron `wpmind_health_check` 默认关闭。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Health;

use WP_Error;
use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\EndpointRepository;
use WPMind\Provider\ProviderClient;

/**
 * Health checker.
 */
final class HealthChecker {

	public const CRON_HOOK = 'wpmind_health_check';
	public const OPTION_ENABLED = 'wpmind_health_check_enabled';

	/**
	 * 探测一家 provider。
	 *
	 * @param string               $provider_id Provider id.
	 * @param bool                 $deep        深度测试（会产生少量费用）.
	 * @param array<string,mixed>  $overrides   api_key / base_url 覆盖（AJAX 未保存场景）.
	 * @return array{ok:bool, latency_ms:int, message:string, deep:bool, error_class:?string}
	 */
	public function probe( string $provider_id, bool $deep = false, array $overrides = array() ): array {
		$client = new ProviderClient();

		if ( ! $deep ) {
			$result = $client->request(
				$provider_id,
				'GET',
				'models',
				array(
					'timeout'       => 15,
					'api_key'       => $overrides['api_key'] ?? null,
					'base_url'      => $overrides['base_url'] ?? null,
					'record_usage'  => false,
					'allow_empty_key' => false,
				)
			);

			if ( $result['ok'] ) {
				$this->on_probe_success( $provider_id, false );
				return array(
					'ok'          => true,
					'latency_ms'  => $result['latency_ms'],
					'message'     => __( '连接成功', 'wpmind' ),
					'deep'        => false,
					'error_class' => null,
				);
			}

			$class = $result['error_class'];
			$msg   = $result['wp_error'] instanceof WP_Error
				? $result['wp_error']->get_error_message()
				: __( '连接失败', 'wpmind' );
			return array(
				'ok'          => false,
				'latency_ms'  => $result['latency_ms'],
				'message'     => $msg,
				'deep'        => false,
				'error_class' => $class ? $class->value : null,
			);
		}

		$repo  = new EndpointRepository();
		$model = '';
		if ( class_exists( '\\WPMind\\Provider\\Catalog\\ModelCatalog' ) ) {
			$model = \WPMind\Provider\Catalog\ModelCatalog::instance()->default_model( $provider_id );
		}
		if ( '' === $model ) {
			$ep    = $repo->get( $provider_id );
			$model = '' !== $ep->default_model ? $ep->default_model : 'default';
		}

		$result = $client->request(
			$provider_id,
			'POST',
			'chat/completions',
			array(
				'timeout'  => 30,
				'api_key'  => $overrides['api_key'] ?? null,
				'base_url' => $overrides['base_url'] ?? null,
				'model'    => $model,
				'body'     => array(
					'model'      => $model,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => 'ping',
						),
					),
					'max_tokens' => 1,
				),
			)
		);

		if ( $result['ok'] ) {
			$this->on_probe_success( $provider_id, true );
			return array(
				'ok'          => true,
				'latency_ms'  => $result['latency_ms'],
				'message'     => __( '深度测试成功', 'wpmind' ),
				'deep'        => true,
				'error_class' => null,
			);
		}

		$class = $result['error_class'];
		$msg   = $result['wp_error'] instanceof WP_Error
			? $result['wp_error']->get_error_message()
			: __( '深度测试失败', 'wpmind' );
		return array(
			'ok'          => false,
			'latency_ms'  => $result['latency_ms'],
			'message'     => $msg,
			'deep'        => true,
			'error_class' => $class ? $class->value : null,
		);
	}

	/**
	 * /models 成功清 needs_attention；深度成功才清 quota_exhausted。
	 */
	private function on_probe_success( string $provider_id, bool $deep ): void {
		$fm = FailoverManager::instance();
		$cb = $fm->get_circuit_breaker( $provider_id );
		if ( ! $cb ) {
			$cb = new CircuitBreaker( $provider_id );
		}
		$state = $cb->get_state();
		if ( CircuitBreaker::STATE_NEEDS_ATTENTION === $state ) {
			$cb->reset();
			return;
		}
		if ( $deep && CircuitBreaker::STATE_QUOTA_EXHAUSTED === $state ) {
			$cb->reset();
			return;
		}
		if ( in_array( $state, array( CircuitBreaker::STATE_OPEN, CircuitBreaker::STATE_HALF_OPEN ), true ) ) {
			$cb->record_success();
		}
	}

	/**
	 * 注册 cron（默认不启用调度）。
	 */
	public static function register_cron(): void {
		add_action( self::CRON_HOOK, array( self::class, 'run_cron' ) );
		if ( ! (bool) get_option( self::OPTION_ENABLED, false ) ) {
			$ts = wp_next_scheduled( self::CRON_HOOK );
			if ( $ts ) {
				wp_unschedule_event( $ts, self::CRON_HOOK );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'fifteen_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * 自定义 15 分钟调度间隔。
	 *
	 * @param array<string,mixed> $schedules Schedules.
	 * @return array<string,mixed>
	 */
	public static function cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['fifteen_minutes'] ) ) {
			$schedules['fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( '每 15 分钟', 'wpmind' ),
			);
		}
		return $schedules;
	}

	/**
	 * Cron：只探测 open / half_open / quota_exhausted，且只用免费 /models。
	 */
	public static function run_cron(): void {
		if ( ! (bool) get_option( self::OPTION_ENABLED, false ) ) {
			return;
		}
		$fm      = FailoverManager::instance();
		$summary = $fm->get_status_summary();
		$checker = new self();
		foreach ( $summary as $id => $row ) {
			$state = (string) ( $row['state'] ?? '' );
			if ( ! in_array( $state, array( CircuitBreaker::STATE_OPEN, CircuitBreaker::STATE_HALF_OPEN, CircuitBreaker::STATE_QUOTA_EXHAUSTED ), true ) ) {
				continue;
			}
			$checker->probe( (string) $id, false );
		}
	}
}
