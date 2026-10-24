<?php
/**
 * Site Health integration for AI Client environment detection.
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Compat;

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
