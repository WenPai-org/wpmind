<?php
/**
 * One-time cleanup of WPMind-written connectors_ai_* API key mirrors.
 *
 * Only deletes an option when its value is exactly equal to the matching
 * WPMind provider key. User-filled Core connector keys are left untouched.
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

/**
 * Remove same-value connector mirrors left by pre-0.13 dual-write.
 */
final class M013_ConnectorMirrorCleanup implements MigrationInterface {

	/**
	 * Domestic providers that WPMind previously mirrored into Connectors.
	 *
	 * @var list<string>
	 */
	public const PROVIDER_IDS = array(
		'deepseek',
		'qwen',
		'zhipu',
		'moonshot',
		'doubao',
		'siliconflow',
		'baidu',
		'minimax',
	);

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'M013_ConnectorMirrorCleanup';
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): int {
		return 1301;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		$legacy = get_option( 'wpmind_custom_endpoints', array() );
		if ( ! is_array( $legacy ) ) {
			$legacy = array();
		}

		foreach ( self::PROVIDER_IDS as $id ) {
			$option = 'connectors_ai_' . $id . '_api_key';
			$mirror = get_option( $option, null );
			if ( ! is_string( $mirror ) || '' === $mirror ) {
				continue;
			}
			$wpmind_key = self::wpmind_key( $legacy, $id );
			if ( '' !== $wpmind_key && $mirror === $wpmind_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): array {
		$legacy = get_option( 'wpmind_custom_endpoints', array() );
		if ( ! is_array( $legacy ) ) {
			$legacy = array();
		}

		$deleted = array();
		$kept    = array();

		foreach ( self::PROVIDER_IDS as $id ) {
			$option = 'connectors_ai_' . $id . '_api_key';
			$mirror = get_option( $option, null );
			if ( ! is_string( $mirror ) || '' === $mirror ) {
				continue;
			}

			$wpmind_key = self::wpmind_key( $legacy, $id );
			if ( '' !== $wpmind_key && $mirror === $wpmind_key ) {
				delete_option( $option );
				$deleted[] = $id;
				continue;
			}

			// Different value → user (or Core) owns it; leave alone.
			$kept[] = $id;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WPMind] connector mirror kept (value differs from WPMind key): ' . $id );
			}
		}

		$notes = array();
		if ( $deleted ) {
			$notes[] = 'deleted mirrors: ' . implode( ',', $deleted );
		}
		if ( $kept ) {
			$notes[] = 'kept distinct connector keys: ' . implode( ',', $kept );
		}
		if ( ! $notes ) {
			$notes[] = 'no connector mirrors matched';
		}

		return array(
			'result' => $deleted ? 'ok' : 'noop',
			'notes'  => $notes,
		);
	}

	/**
	 * Read the WPMind api_key for a provider from the legacy option.
	 *
	 * @param array<string, mixed> $legacy Legacy endpoints option.
	 * @param string               $id     Provider id.
	 * @return string
	 */
	private static function wpmind_key( array $legacy, string $id ): string {
		$entry = $legacy[ $id ] ?? null;
		if ( ! is_array( $entry ) ) {
			return '';
		}
		$key = $entry['api_key'] ?? '';
		return is_scalar( $key ) ? (string) $key : '';
	}
}
