<?php
/**
 * SSE Slot DTO
 *
 * Immutable data transfer object representing an acquired SSE concurrency slot.
 *
 * @package WPMind\Modules\ApiGateway\Stream
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Stream;

/**
 * Class SseSlot
 *
 * Read-only value object returned when a concurrency slot is
 * successfully acquired. Used to heartbeat and release the slot.
 */
final class SseSlot {

	/**
	 * The API key identifier that owns this slot.
	 *
	 * @var string
	 */
	public readonly string $key_id;

	/**
	 * The unique request ID for this SSE connection.
	 *
	 * @var string
	 */
	public readonly string $request_id;

	/**
	 * Composite key used for slot tracking in transients.
	 *
	 * @var string
	 */
	public readonly string $slot_key;

	/**
	 * Constructor.
	 *
	 * @param string $key_id     API key identifier.
	 * @param string $request_id Unique request ID.
	 * @param string $slot_key   Composite slot tracking key.
	 */
	public function __construct( string $key_id, string $request_id, string $slot_key ) {
		$this->key_id     = $key_id;
		$this->request_id = $request_id;
		$this->slot_key   = $slot_key;
	}
}
