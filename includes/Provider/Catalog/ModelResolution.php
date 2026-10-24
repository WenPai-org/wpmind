<?php
/**
 * Result of resolving a requested model against the catalog.
 *
 * @package WPMind\Provider\Catalog
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Catalog;

/**
 * Immutable resolution result.
 *
 * @since 0.13.0
 */
final class ModelResolution {

	/**
	 * Constructor.
	 *
	 * @param string      $model         Model id to send to the provider.
	 * @param string|null $remapped_from Original id when remapped; null if unchanged.
	 * @param string      $reason        empty|alias|retired_replaced|retired_default|unknown_passthrough|user_default.
	 */
	public function __construct(
		public readonly string $model,
		public readonly ?string $remapped_from = null,
		public readonly string $reason = ''
	) {
	}

	/**
	 * Whether the requested model was remapped.
	 */
	public function was_remapped(): bool {
		return null !== $this->remapped_from && $this->remapped_from !== $this->model;
	}
}
