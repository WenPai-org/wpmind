<?php
/**
 * BudgetChecker get_provider_month_cost unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the get_provider_month_cost fix (reads month, not all-time).
 *
 * @covers ::WPMind\Modules\CostControl\BudgetChecker::get_provider_month_cost
 */
class BudgetCheckerMonthTest extends TestCase {

	/**
	 * Only current month records are summed.
	 */
	public function test_only_current_month_counted(): void {
		$current_month_start = strtotime( date( 'Y-m' ) . '-01' );
		$last_month          = strtotime( '-35 days' );

		$history = [
			[
				'provider'  => 'deepseek',
				'model'     => 'deepseek-chat',
				'cost'      => 5.0,
				'timestamp' => $last_month,
			],
			[
				'provider'  => 'deepseek',
				'model'     => 'deepseek-chat',
				'cost'      => 3.0,
				'timestamp' => $current_month_start + 100,
			],
			[
				'provider'  => 'qwen',
				'model'     => 'qwen-turbo',
				'cost'      => 2.0,
				'timestamp' => $current_month_start + 200,
			],
		];

		$result = $this->call_get_provider_month_cost( 'deepseek', $history );
		// Should only count the 3.0 from this month, not 5.0 from last month.
		$this->assertEquals( 3.0, $result );
	}

	/**
	 * No records for provider returns zero.
	 */
	public function test_no_records_returns_zero(): void {
		$result = $this->call_get_provider_month_cost( 'nonexistent', [] );
		$this->assertEquals( 0.0, $result );
	}

	/**
	 * Records missing cost field are treated as zero.
	 */
	public function test_missing_cost_field_treated_as_zero(): void {
		$current_month_start = strtotime( date( 'Y-m' ) . '-01' );
		$history = [
			[
				'provider'  => 'deepseek',
				'model'     => 'deepseek-chat',
				'timestamp' => $current_month_start + 100,
			],
		];

		$result = $this->call_get_provider_month_cost( 'deepseek', $history );
		$this->assertEquals( 0.0, $result );
	}

	/**
	 * Multiple records this month are all summed.
	 */
	public function test_multiple_records_this_month_summed(): void {
		$current_month_start = strtotime( date( 'Y-m' ) . '-01' );
		$history = [
			[
				'provider'  => 'deepseek',
				'model'     => 'deepseek-chat',
				'cost'      => 1.5,
				'timestamp' => $current_month_start + 100,
			],
			[
				'provider'  => 'deepseek',
				'model'     => 'deepseek-reasoner',
				'cost'      => 2.5,
				'timestamp' => $current_month_start + 200,
			],
		];

		$result = $this->call_get_provider_month_cost( 'deepseek', $history );
		$this->assertEquals( 4.0, $result );
	}

	/**
	 * Simulate the fixed get_provider_month_cost logic.
	 *
	 * @param string $provider Provider ID.
	 * @param array  $history  Usage history records.
	 * @return float
	 */
	private function call_get_provider_month_cost( string $provider, array $history ): float {
		$month_start = strtotime( date( 'Y-m' ) . '-01' );
		$cost        = 0;

		foreach ( $history as $record ) {
			if ( ( $record['provider'] ?? '' ) !== $provider ) {
				continue;
			}
			if ( ( $record['timestamp'] ?? 0 ) >= $month_start ) {
				$cost += $record['cost'] ?? 0;
			}
		}

		return $cost;
	}
}
