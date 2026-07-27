<?php
/**
 * Pricing unit tests.
 *
 * @package WPMind
 */

declare(strict_types=1);

namespace WPMind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPMind\Usage\Pricing;

/**
 * @covers \WPMind\Usage\Pricing
 */
class PricingTest extends TestCase {

	/**
	 * Exact model name match returns correct pricing.
	 */
	public function test_exact_model_match(): void {
		$cost = Pricing::calculate_cost( 'deepseek', 'deepseek-chat', 1000, 1000 );
		// (1000/1M * 1.00) + (1000/1M * 2.00) = 0.001 + 0.002 = 0.003.
		$this->assertEquals( 0.003, $cost );
	}

	/**
	 * Model name with date suffix falls back to prefix match.
	 */
	public function test_prefix_match_claude_with_date(): void {
		$cost = Pricing::calculate_cost( 'anthropic', 'claude-3-5-sonnet-20241022', 1000000, 1000000 );
		// claude-3-5-sonnet pricing: input=3.00, output=15.00 per 1M.
		$this->assertEquals( 18.0, $cost );
	}

	/**
	 * Gemini model with -exp suffix falls back to prefix match.
	 */
	public function test_prefix_match_gemini_exp(): void {
		$cost = Pricing::calculate_cost( 'google', 'gemini-2.0-flash-exp', 1000000, 1000000 );
		// gemini-2.0-flash pricing: input=0.10, output=0.40 per 1M.
		$this->assertEquals( 0.5, $cost );
	}

	/**
	 * Unknown model falls back to default pricing.
	 */
	public function test_unknown_model_uses_default(): void {
		$cost = Pricing::calculate_cost( 'deepseek', 'deepseek-unknown-future-model', 1000000, 1000000 );
		// default for deepseek: input=1.00, output=2.00 per 1M.
		$this->assertEquals( 3.0, $cost );
	}

	/**
	 * Unknown provider returns zero cost.
	 */
	public function test_unknown_provider_returns_zero(): void {
		$cost = Pricing::calculate_cost( 'nonexistent', 'some-model', 1000000, 1000000 );
		$this->assertEquals( 0.0, $cost );
	}

	/**
	 * Zero tokens returns zero cost.
	 */
	public function test_zero_tokens_returns_zero(): void {
		$cost = Pricing::calculate_cost( 'deepseek', 'deepseek-chat', 0, 0 );
		$this->assertEquals( 0.0, $cost );
	}

	/**
	 * Baidu ERNIE with -8k suffix matches base name.
	 */
	public function test_prefix_match_ernie_with_suffix(): void {
		$cost = Pricing::calculate_cost( 'baidu', 'ernie-4.0-8k', 1000000, 1000000 );
		// ernie-4.0 pricing: input=30.00, output=60.00 per 1M.
		$this->assertEquals( 90.0, $cost );
	}

	/**
	 * Qwen models match exactly.
	 */
	public function test_qwen_exact_match(): void {
		$cost = Pricing::calculate_cost( 'qwen', 'qwen-max', 1000000, 1000000 );
		// qwen-max: input=20.00, output=60.00 per 1M.
		$this->assertEquals( 80.0, $cost );
	}

	/**
	 * MiniMax model matches exactly.
	 */
	public function test_minimax_exact_match(): void {
		$cost = Pricing::calculate_cost( 'minimax', 'abab6.5s-chat', 1000000, 1000000 );
		// abab6.5s-chat: input=1.00, output=1.00 per 1M.
		$this->assertEquals( 2.0, $cost );
	}

	/**
	 * Currency lookup returns correct values.
	 */
	public function test_get_currency(): void {
		$this->assertSame( 'CNY', Pricing::get_currency( 'deepseek' ) );
		$this->assertSame( 'USD', Pricing::get_currency( 'openai' ) );
		$this->assertSame( 'CNY', Pricing::get_currency( 'qwen' ) );
	}

	/**
	 * Pricing data returns array for known provider.
	 */
	public function test_get_returns_data(): void {
		$data = Pricing::get( 'deepseek' );
		$this->assertArrayHasKey( 'deepseek-chat', $data );
		$this->assertArrayHasKey( 'default', $data );
	}

	/**
	 * Pricing data returns empty for unknown provider.
	 */
	public function test_get_returns_empty_for_unknown(): void {
		$this->assertSame( [], Pricing::get( 'nonexistent' ) );
	}
}
