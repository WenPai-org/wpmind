<?php
/**
 * Site Health integration for AI Client environment detection.
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Compat;

use WPMind\Failover\CircuitBreaker;
use WPMind\Failover\FailoverManager;
use WPMind\Provider\Health\ErrorCatalog;
use WPMind\Provider\Health\ErrorClass;

/**
 * Expose compatibility matrix results in Site Health (never includes keys).
 */
final class SiteHealth {

	/**
	 * Register Site Health hooks.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'register_tests' ) );
		add_filter( 'debug_information', array( self::class, 'debug_information' ) );
	}

	/**
	 * Register the direct Site Health test.
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed>
	 */
	public static function register_tests( array $tests ): array {
		$tests['direct']['wpmind_ai_client_env'] = array(
			'label' => __( 'WPMind AI Client environment', 'wpmind' ),
			'test'  => array( self::class, 'test_environment' ),
		);
		$tests['direct']['wpmind_provider_health'] = array(
			'label' => __( 'WPMind Provider', 'wpmind' ),
			'test'  => array( self::class, 'test_provider_health' ),
		);
		return $tests;
	}

	/**
	 * Run the AI Client environment Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_environment(): array {
		$env    = AiClientEnvironment::detect();
		$result = array(
			'label'       => __( 'WPMind AI Client environment', 'wpmind' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'WPMind',
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'wpmind_ai_client_env',
		);

		if ( 'none' === $env['sdk_source'] ) {
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'WordPress AI Client SDK was not detected. WPMind will use its native HTTP path only.', 'wpmind' ) . '</p>';
			return $result;
		}

		$source_label = 'core' === $env['sdk_source']
			? __( 'WordPress core', 'wpmind' )
			: __( 'plugin', 'wpmind' );

		$result['description'] = '<p>' . esc_html(
			sprintf(
				/* translators: 1: SDK source (core/plugin), 2: SDK version or unknown */
				__( 'AI Client SDK source: %1$s; version: %2$s.', 'wpmind' ),
				$source_label,
				$env['sdk_version'] ? $env['sdk_version'] : __( 'unknown', 'wpmind' )
			)
		) . '</p>';

		return $result;
	}

	/**
	 * Site Health: WPMind Provider 熔断 / 额度状态.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_provider_health(): array {
		$result = array(
			'label'       => __( 'WPMind Provider', 'wpmind' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => 'WPMind',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( '所有已跟踪的 Provider 状态正常。', 'wpmind' ) . '</p>',
			'actions'     => '',
			'test'        => 'wpmind_provider_health',
		);

		if ( ! class_exists( FailoverManager::class ) ) {
			return $result;
		}

		$issues = array();
		$fm     = FailoverManager::instance();
		foreach ( $fm->get_status_summary() as $id => $row ) {
			$state = (string) ( $row['state'] ?? '' );
			if ( ! in_array( $state, array( CircuitBreaker::STATE_NEEDS_ATTENTION, CircuitBreaker::STATE_QUOTA_EXHAUSTED ), true ) ) {
				continue;
			}
			$name        = (string) ( $row['name'] ?? $id );
			$class_value = (string) ( $row['reason'] ?? ( CircuitBreaker::STATE_QUOTA_EXHAUSTED === $state ? ErrorClass::QuotaExhausted->value : ErrorClass::AuthFailed->value ) );
			try {
				$ec = ErrorClass::from( $class_value );
			} catch ( \ValueError $e ) {
				$ec = CircuitBreaker::STATE_QUOTA_EXHAUSTED === $state ? ErrorClass::QuotaExhausted : ErrorClass::AuthFailed;
			}
			$entry     = ErrorCatalog::entry( $ec, (string) $id );
			$cred_html = '';
			if ( '' !== $entry['credentials_url'] ) {
				$cred_html = ' <a href="' . esc_url( $entry['credentials_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '获取密钥', 'wpmind' ) . '</a>';
			}
			$issues[] = sprintf(
				'<li><strong>%1$s</strong>：%2$s — %3$s%4$s</li>',
				esc_html( $name ),
				esc_html( $entry['message'] ),
				esc_html( $entry['recovery'] ),
				$cred_html
			);
		}

		if ( array() !== $issues ) {
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( '以下 Provider 需要处理（密钥无效或额度不足）：', 'wpmind' ) . '</p><ul>' . implode( '', $issues ) . '</ul>';
		}

		return $result;
	}

	/**
	 * Add AI Client fields to Site Health debug info.
	 *
	 * @param array<string, mixed> $info Debug info.
	 * @return array<string, mixed>
	 */
	public static function debug_information( array $info ): array {
		$env = AiClientEnvironment::detect();

		$info['wpmind-ai-client'] = array(
			'label'  => __( 'WPMind AI Client', 'wpmind' ),
			'fields' => array(
				'sdk_source'     => array(
					'label' => __( 'SDK source', 'wpmind' ),
					'value' => (string) $env['sdk_source'],
				),
				'sdk_version'    => array(
					'label' => __( 'SDK version', 'wpmind' ),
					'value' => $env['sdk_version'] ? (string) $env['sdk_version'] : __( 'n/a', 'wpmind' ),
				),
				'has_connectors' => array(
					'label' => __( 'Connectors API', 'wpmind' ),
					'value' => $env['has_connectors'] ? __( 'yes', 'wpmind' ) : __( 'no', 'wpmind' ),
				),
				'has_prompt_fn'  => array(
					'label' => __( 'wp_ai_client_prompt()', 'wpmind' ),
					'value' => $env['has_prompt_fn'] ? __( 'yes', 'wpmind' ) : __( 'no', 'wpmind' ),
				),
				'supports_ai'    => array(
					'label' => __( 'wp_supports_ai()', 'wpmind' ),
					'value' => $env['supports_ai'] ? __( 'yes', 'wpmind' ) : __( 'no', 'wpmind' ),
				),
			),
		);

		return $info;
	}
}
