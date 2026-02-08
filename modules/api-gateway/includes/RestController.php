<?php
/**
 * REST Controller
 *
 * Registers all REST API routes for the API Gateway and delegates
 * request handling to the middleware pipeline.
 *
 * @package WPMind\Modules\ApiGateway
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway;

use WPMind\Modules\ApiGateway\Pipeline\GatewayPipeline;
use WPMind\Modules\ApiGateway\Pipeline\AuthMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\BudgetMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\QuotaMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\RequestTransformMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\RouteMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\ResponseTransformMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\ErrorMiddleware;
use WPMind\Modules\ApiGateway\Pipeline\LogMiddleware;

/**
 * Class RestController
 *
 * Registers OpenAI-compatible REST endpoints under the mind/v1 namespace
 * and routes each request through the 8-stage gateway pipeline.
 */
final class RestController {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'mind/v1';

	/**
	 * Lazy-initialized gateway pipeline instance.
	 *
	 * @var GatewayPipeline|null
	 */
	private ?GatewayPipeline $pipeline = null;

	/**
	 * Register all REST API routes for the gateway.
	 */
	public function register_routes(): void {
		// POST /wp-json/mind/v1/chat/completions
		register_rest_route(
			self::NAMESPACE,
			'/chat/completions',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_chat_completions' ],
				'permission_callback' => '__return_true',
				'args'                => GatewayRequestSchema::chat_completions(),
			]
		);

		// POST /wp-json/mind/v1/embeddings
		register_rest_route(
			self::NAMESPACE,
			'/embeddings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_embeddings' ],
				'permission_callback' => '__return_true',
				'args'                => GatewayRequestSchema::embeddings(),
			]
		);

		// POST /wp-json/mind/v1/responses
		register_rest_route(
			self::NAMESPACE,
			'/responses',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_responses' ],
				'permission_callback' => '__return_true',
				'args'                => GatewayRequestSchema::responses(),
			]
		);

		// GET /wp-json/mind/v1/models
		register_rest_route(
			self::NAMESPACE,
			'/models',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_models' ],
				'permission_callback' => '__return_true',
				'args'                => GatewayRequestSchema::models(),
			]
		);

		// GET /wp-json/mind/v1/models/<model_id>
		register_rest_route(
			self::NAMESPACE,
			'/models/(?P<model_id>[a-zA-Z0-9_.-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_model_detail' ],
				'permission_callback' => '__return_true',
			]
		);

		// GET /wp-json/mind/v1/status
		register_rest_route(
			self::NAMESPACE,
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_status' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle POST /chat/completions requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_chat_completions( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'chat.completions', $request );
	}

	/**
	 * Handle POST /embeddings requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_embeddings( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'embeddings', $request );
	}

	/**
	 * Handle POST /responses requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_responses( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'responses', $request );
	}

	/**
	 * Handle GET /models requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_models( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'models', $request );
	}

	/**
	 * Handle GET /models/<model_id> requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_model_detail( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'models', $request );
	}

	/**
	 * Handle GET /status requests.
	 *
	 * Goes through the full pipeline so auth, error, and log stages apply.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_status( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->get_pipeline()->handle( 'status', $request );
	}

	/**
	 * Lazy-initialize and return the gateway pipeline.
	 *
	 * Creates the pipeline with all 8 middleware stages on first call.
	 *
	 * @return GatewayPipeline
	 */
	private function get_pipeline(): GatewayPipeline {
		if ( ! isset( $this->pipeline ) ) {
			$this->pipeline = new GatewayPipeline(
				new AuthMiddleware(),
				new BudgetMiddleware(),
				new QuotaMiddleware(),
				new RequestTransformMiddleware(),
				new RouteMiddleware(),
				new ResponseTransformMiddleware(),
				new ErrorMiddleware(),
				new LogMiddleware()
			);
		}

		return $this->pipeline;
	}
}
