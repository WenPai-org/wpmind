<?php
/**
 * Budget Middleware
 *
 * Pipeline stage that enforces monthly budget limits per API key.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

/**
 * Class BudgetMiddleware
 *
 * Checks the current month's spend against the key's monthly budget.
 * Skips for management routes and already-errored contexts.
 */
final class BudgetMiddleware implements GatewayStageInterface {

	/**
	 * {@inheritDoc}
	 */
	public function process( GatewayRequestContext $context ): void {
		if ( $context->is_management_route() ) {
			return;
		}

		if ( $context->has_error() ) {
			return;
		}

		$auth_result = $context->auth_result();

		if ( $auth_result === null ) {
			return;
		}

		$budget = (float) $auth_result->monthly_budget_usd;

		if ( $budget <= 0.0 ) {
			return;
		}

		$spent = $this->get_current_month_spend( $auth_result->key_id );

		if ( $spent >= $budget ) {
			$context->set_error(
				new \WP_Error(
					'insufficient_quota',
					'Monthly budget exceeded.',
					[ 'status' => 429 ]
				)
			);
		}
	}

	/**
	 * Query the total spend for the current month from the usage table.
	 *
	 * @param string $key_id API key identifier.
	 * @return float Total cost in USD for the current month.
	 */
	private function get_current_month_spend( string $key_id ): float {
		global $wpdb;

		$table        = $wpdb->prefix . 'wpmind_api_key_usage';
		$window_month = gmdate( 'Y-m' );

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_cost_usd FROM {$table} WHERE key_id = %s AND window_month = %s LIMIT 1",
				$key_id,
				$window_month
			)
		);

		return $result !== null ? (float) $result : 0.0;
	}
}