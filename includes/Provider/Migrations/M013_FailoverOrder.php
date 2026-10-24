<?php
/**
 * 从 routing manual_priority / 默认 provider 导入 wpmind_failover_order。
 *
 * @package WPMind
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Migrations;

use WPMind\Provider\ProviderDefinitions;

/**
 * Failover order migration.
 */
final class M013_FailoverOrder implements MigrationInterface {

	public const OPTION = 'wpmind_failover_order';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'M013_FailoverOrder';
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): int {
		return 1302;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		$existing = get_option( self::OPTION, null );
		if ( is_array( $existing ) && array() !== $existing ) {
			return false;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): array {
		if ( ! $this->is_needed() ) {
			return array(
				'result' => 'noop',
				'notes'  => array( 'failover_order already set' ),
			);
		}

		$settings = get_option( 'wpmind_routing_settings', array() );
		$manual   = array();
		if ( is_array( $settings ) ) {
			$manual = $settings['provider_priority'] ?? array();
			if ( ! is_array( $manual ) ) {
				$manual = array();
			}
		}

		$order = array_values( array_filter( $manual, 'is_string' ) );

		if ( array() === $order ) {
			$default = (string) get_option( 'wpmind_default_provider', '' );
			$defs    = array_keys( ProviderDefinitions::DEFINITIONS );
			$order   = $defs;
			if ( '' !== $default && in_array( $default, $order, true ) ) {
				$order = array_values( array_diff( $order, array( $default ) ) );
				array_unshift( $order, $default );
			}
		}

		update_option( self::OPTION, $order, false );

		return array(
			'result' => 'ok',
			'notes'  => array( 'imported ' . count( $order ) . ' providers' ),
		);
	}
}
