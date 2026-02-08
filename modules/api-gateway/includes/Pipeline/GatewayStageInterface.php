<?php
/**
 * Gateway Stage Interface
 *
 * Contract for pipeline middleware stages.
 *
 * @package WPMind\Modules\ApiGateway\Pipeline
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Pipeline;

/**
 * Interface GatewayStageInterface
 *
 * Each pipeline stage receives the shared request context,
 * inspects or mutates it, and returns void.
 */
interface GatewayStageInterface {

	/**
	 * Process the gateway request context.
	 *
	 * @param GatewayRequestContext $context Shared request context.
	 */
	public function process( GatewayRequestContext $context ): void;
}
