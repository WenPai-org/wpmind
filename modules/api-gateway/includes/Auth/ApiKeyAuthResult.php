<?php
/**
 * API Key Auth Result DTO
 *
 * Immutable data transfer object representing an authenticated API key.
 *
 * @package WPMind\Modules\ApiGateway\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Auth;

/**
 * Class ApiKeyAuthResult
 *
 * Read-only representation of an authenticated API key.
 */
class ApiKeyAuthResult {

	/**
	 * The 12-character key identifier.
	 *
	 * @var string
	 */
	public readonly string $key_id;

	/**
	 * WordPress user ID of the key owner, or null.
	 *
	 * @var int|null
	 */
	public readonly ?int $owner_user_id;

	/**
	 * Allowed provider IDs.
	 *
	 * @var array
	 */
	public readonly array $allowed_providers;

	/**
	 * Requests per minute limit.
	 *
	 * @var int
	 */
	public readonly int $rpm_limit;

	/**
	 * Tokens per minute limit.
	 *
	 * @var int
	 */
	public readonly int $tpm_limit;

	/**
	 * Concurrency limit.
	 *
	 * @var int
	 */
	public readonly int $concurrency_limit;

	/**
	 * Monthly budget in USD.
	 *
	 * @var float
	 */
	public readonly float $monthly_budget_usd;

	/**
	 * Construct from a database row array.
	 *
	 * @param array $row Associative array from the api_keys table.
	 */
	public function __construct( array $row ) {
		$this->key_id             = (string) $row['key_id'];
		$this->owner_user_id      = isset( $row['owner_user_id'] ) ? (int) $row['owner_user_id'] : null;
		$this->allowed_providers  = self::decode_json_array( $row['allowed_providers'] ?? null );
		$this->rpm_limit          = (int) ( $row['rpm_limit'] ?? 60 );
		$this->tpm_limit          = (int) ( $row['tpm_limit'] ?? 100000 );
		$this->concurrency_limit  = (int) ( $row['concurrency_limit'] ?? 2 );
		$this->monthly_budget_usd = (float) ( $row['monthly_budget_usd'] ?? 0.0 );
	}

	/**
	 * Get the key identifier.
	 *
	 * @return string
	 */
	public function get_key_id(): string {
		return $this->key_id;
	}

	/**
	 * Get the owner user ID.
	 *
	 * @return int|null
	 */
	public function get_owner_user_id(): ?int {
		return $this->owner_user_id;
	}

	/**
	 * Get allowed providers.
	 *
	 * @return array
	 */
	public function get_allowed_providers(): array {
		return $this->allowed_providers;
	}

	/**
	 * Get requests per minute limit.
	 *
	 * @return int
	 */
	public function get_rpm_limit(): int {
		return $this->rpm_limit;
	}

	/**
	 * Get tokens per minute limit.
	 *
	 * @return int
	 */
	public function get_tpm_limit(): int {
		return $this->tpm_limit;
	}

	/**
	 * Get concurrency limit.
	 *
	 * @return int
	 */
	public function get_concurrency_limit(): int {
		return $this->concurrency_limit;
	}

	/**
	 * Get monthly budget in USD.
	 *
	 * @return float
	 */
	public function get_monthly_budget_usd(): float {
		return $this->monthly_budget_usd;
	}

	/**
	 * Decode a JSON string to an array, returning empty array on failure.
	 *
	 * @param string|null $json JSON string or null.
	 * @return array Decoded array.
	 */
	private static function decode_json_array( ?string $json ): array {
		if ( $json === null || $json === '' ) {
			return [];
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}
