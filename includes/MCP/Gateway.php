<?php
/**
 * MCP Gateway bootstrap and Ability registration.
 *
 * @package WPMind\MCP
 * @since 4.0.0
 */

declare(strict_types=1);

namespace WPMind\MCP;

use WP_Error;

/**
 * Class Gateway
 */
final class Gateway {

    /**
     * Ability category slug.
     */
    private const ABILITY_CATEGORY = 'wpmind-ai-gateway';

    /**
     * Singleton instance.
     *
     * @var Gateway|null
     */
    private static ?Gateway $instance = null;

    /**
     * Whether gateway hooks are initialized.
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Registered ability names.
     *
     * @var array<string>
     */
    private array $ability_names = [];

    /**
     * Get singleton instance.
     *
     * @return Gateway
     */
    public static function instance(): Gateway {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Register MCP Gateway hooks.
     *
     * @return void
     */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }

        add_filter( 'mcp_adapter_default_server_config', [ $this, 'filter_server_config' ] );
        add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_categories' ] );
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        add_action( 'mcp_adapter_init', [ $this, 'register_server' ] );

        $this->initialized = true;
    }

    /**
     * Filter MCP server config.
     *
     * @param array $config Server config.
     * @return array
     */
    public function filter_server_config( array $config ): array {
        $config['name'] = 'wpmind-mcp';
        $config['version'] = WPMIND_VERSION;

        return $config;
    }

    /**
     * Register gateway ability category.
     *
     * @return void
     */
    public function register_ability_categories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::ABILITY_CATEGORY ) ) {
            return;
        }

        wp_register_ability_category(
            self::ABILITY_CATEGORY,
            [
                'label'       => __( 'WPMind AI Gateway', 'wpmind' ),
                'description' => __( 'AI routing, provider health, usage and budget control abilities.', 'wpmind' ),
            ]
        );
    }

    /**
     * Register WPMind abilities when Abilities API is available.
     *
     * @return void
     */
    public function register_abilities(): void {
        if ( ! $this->is_abilities_api_available() ) {
            return;
        }

        $definitions = $this->get_ability_definitions();
        $this->ability_names = [];

        foreach ( $definitions as $ability_name => $definition ) {
            if ( function_exists( 'wp_has_ability' ) && wp_has_ability( $ability_name ) ) {
                $this->ability_names[] = $ability_name;
                continue;
            }

            $ability = wp_register_ability( $ability_name, $definition );
            if ( null === $ability ) {
                do_action( 'wpmind_mcp_gateway_ability_registration_failed', $ability_name, $definition );
                continue;
            }

            $this->ability_names[] = $ability_name;
        }
    }

    /**
     * Register MCP server when adapter is initialized.
     *
     * @param mixed $adapter MCP adapter instance.
     * @return void
     */
    public function register_server( $adapter ): void {
        if ( ! $this->is_abilities_api_available() ) {
            return;
        }

        if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
            return;
        }

        if ( class_exists( '\WP_Abilities_Registry' ) ) {
            \WP_Abilities_Registry::get_instance();
        }

        if ( empty( $this->ability_names ) ) {
            $this->ability_names = $this->get_registered_ability_names();
        }

        if ( empty( $this->ability_names ) ) {
            do_action( 'wpmind_mcp_gateway_registration_failed', 'No abilities registered for gateway.' );
            return;
        }

        $transports = [];
        if ( class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
            $transports[] = \WP\MCP\Transport\HttpTransport::class;
        }

        try {
            $adapter->create_server(
                'wpmind-ai-gateway',
                'wpmind',
                'mcp',
                'WPMind AI Gateway',
                'Intelligent AI routing with multi-provider support',
                WPMIND_VERSION,
                $transports,
                null,
                $this->ability_names
            );

            do_action( 'wpmind_mcp_gateway_registered', $this->ability_names );
            return;
        } catch ( \ArgumentCountError $error ) {
            // Backward-compatible fallback for older adapter signatures.
            do_action( 'wpmind_mcp_gateway_adapter_fallback', $error->getMessage() );
        } catch ( \Throwable $error ) {
            do_action( 'wpmind_mcp_gateway_registration_failed', $error->getMessage() );
            return;
        }

        try {
            $adapter->create_server(
                'wpmind-ai-gateway',
                'wpmind',
                'mcp'
            );

            do_action( 'wpmind_mcp_gateway_registered', $this->ability_names );
        } catch ( \Throwable $error ) {
            do_action( 'wpmind_mcp_gateway_registration_failed', $error->getMessage() );
        }
    }

    /**
     * Ability: mind/chat
     *
     * @param mixed ...$args Callback args.
     * @return array
     */
    public function execute_chat_ability( ...$args ): array {
        $input = $this->normalize_input( $args[0] ?? [] );

        $messages = $input['messages'] ?? ( $input['prompt'] ?? '' );
        $options = isset( $input['options'] ) && is_array( $input['options'] ) ? $input['options'] : [];

        if ( '' === $messages || [] === $messages ) {
            return $this->error_result(
                'wpmind_mcp_chat_missing_prompt',
                __( 'Missing prompt/messages for mind/chat ability.', 'wpmind' )
            );
        }

        if ( ! function_exists( 'wpmind_chat' ) ) {
            return $this->error_result(
                'wpmind_mcp_api_unavailable',
                __( 'WPMind public API is not available.', 'wpmind' )
            );
        }

        $result = wpmind_chat( $messages, $options );

        if ( is_wp_error( $result ) ) {
            return $this->error_from_wp_error( $result );
        }

        return [
            'success' => true,
            'data'    => $result,
        ];
    }

    /**
     * Ability: mind/get-providers
     *
     * @param mixed ...$args Callback args.
     * @return array
     */
    public function execute_get_providers_ability( ...$args ): array {
        unset( $args );

        $providers = [];
        if ( function_exists( 'WPMind\\wpmind' ) ) {
            $endpoints = \WPMind\wpmind()->get_custom_endpoints();
            foreach ( $endpoints as $id => $endpoint ) {
                if ( empty( $endpoint['enabled'] ) || empty( $endpoint['api_key'] ) ) {
                    continue;
                }

                $providers[] = [
                    'id'          => $id,
                    'name'        => $endpoint['display_name'] ?? $endpoint['name'] ?? $id,
                    'base_url'    => $endpoint['custom_base_url'] ?? $endpoint['base_url'] ?? '',
                    'model_count' => is_array( $endpoint['models'] ?? null ) ? count( $endpoint['models'] ) : 0,
                    'is_official' => ! empty( $endpoint['is_official'] ),
                ];
            }
        }

        $router_status = class_exists( '\WPMind\\Routing\\IntelligentRouter' )
            ? \WPMind\Routing\IntelligentRouter::instance()->get_status_summary()
            : [];

        $failover_status = class_exists( '\WPMind\\Failover\\FailoverManager' )
            ? \WPMind\Failover\FailoverManager::instance()->get_status_summary()
            : [];

        return [
            'success' => true,
            'data'    => [
                'providers' => $providers,
                'routing'   => $router_status,
                'failover'  => $failover_status,
            ],
        ];
    }

    /**
     * Ability: mind/get-usage-stats
     *
     * @param mixed ...$args Callback args.
     * @return array
     */
    public function execute_get_usage_stats_ability( ...$args ): array {
        unset( $args );

        $data = [
            'today'  => [],
            'month'  => [],
            'total'  => [],
            'status' => [],
            'cache'  => [],
        ];

        if ( class_exists( '\WPMind\\Modules\\CostControl\\UsageTracker' ) ) {
            $data['today'] = \WPMind\Modules\CostControl\UsageTracker::get_today_stats();
            $data['month'] = \WPMind\Modules\CostControl\UsageTracker::get_month_stats();
            $data['total'] = \WPMind\Modules\CostControl\UsageTracker::get_stats();
        }

        if ( function_exists( 'wpmind_get_status' ) ) {
            $data['status'] = wpmind_get_status();
        }

        if ( function_exists( 'wpmind_get_cache_stats' ) ) {
            $data['cache'] = wpmind_get_cache_stats();
        }

        return [
            'success' => true,
            'data'    => $data,
        ];
    }

    /**
     * Ability: mind/get-budget-status
     *
     * @param mixed ...$args Callback args.
     * @return array
     */
    public function execute_get_budget_status_ability( ...$args ): array {
        unset( $args );

        if ( ! class_exists( '\WPMind\\Modules\\CostControl\\BudgetChecker' ) ) {
            return [
                'success' => true,
                'data'    => [
                    'enabled'   => false,
                    'global'    => null,
                    'providers' => [],
                ],
            ];
        }

        $summary = \WPMind\Modules\CostControl\BudgetChecker::instance()->get_summary();

        return [
            'success' => true,
            'data'    => $summary,
        ];
    }

    /**
     * Ability: mind/switch-strategy
     *
     * @param mixed ...$args Callback args.
     * @return array
     */
    public function execute_switch_strategy_ability( ...$args ): array {
        $input = $this->normalize_input( $args[0] ?? [] );
        $strategy = sanitize_key( (string) ( $input['strategy'] ?? '' ) );

        if ( '' === $strategy ) {
            return $this->error_result(
                'wpmind_mcp_switch_strategy_missing',
                __( 'Missing strategy for mind/switch-strategy ability.', 'wpmind' )
            );
        }

        if ( ! class_exists( '\WPMind\\Routing\\IntelligentRouter' ) ) {
            return $this->error_result(
                'wpmind_router_unavailable',
                __( 'Routing engine is unavailable.', 'wpmind' )
            );
        }

        $router = \WPMind\Routing\IntelligentRouter::instance();
        $switched = $router->set_strategy( $strategy );

        if ( ! $switched ) {
            return $this->error_result(
                'wpmind_invalid_strategy',
                __( 'Invalid routing strategy.', 'wpmind' ),
                [
                    'available' => array_keys( $router->get_available_strategies() ),
                ]
            );
        }

        return [
            'success' => true,
            'data'    => [
                'strategy'  => $router->get_current_strategy(),
                'available' => $router->get_available_strategies(),
            ],
        ];
    }

    /**
     * Permission callback for read-only MCP abilities.
     *
     * @param mixed ...$args Callback args.
     * @return bool
     */
    public function can_read_gateway_data( ...$args ): bool {
        unset( $args );

        return current_user_can( 'manage_options' );
    }

    /**
     * Permission callback for write MCP abilities.
     *
     * @param mixed ...$args Callback args.
     * @return bool
     */
    public function can_manage_gateway( ...$args ): bool {
        unset( $args );

        return current_user_can( 'manage_options' );
    }

    /**
     * Check if WordPress Abilities API is available.
     *
     * @return bool
     */
    private function is_abilities_api_available(): bool {
        return function_exists( 'wp_register_ability' );
    }

    /**
     * Get ability registration definitions.
     *
     * @return array<string,array>
     */
    private function get_ability_definitions(): array {
        $read_annotations = [
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ];

        $definitions = [
            'mind/chat' => [
                'label'               => __( 'WPMind Chat', 'wpmind' ),
                'description'         => __( 'Execute routed chat completions via WPMind.', 'wpmind' ),
                'category'            => self::ABILITY_CATEGORY,
                'input_schema'        => [
                    'type'       => 'object',
                    'properties' => [
                        'messages' => [
                            'description' => __( 'Prompt string or chat message array.', 'wpmind' ),
                        ],
                        'prompt'   => [
                            'type'        => 'string',
                            'description' => __( 'Shortcut prompt string.', 'wpmind' ),
                        ],
                        'options'  => [
                            'type'        => 'object',
                            'description' => __( 'Chat options forwarded to wpmind_chat().', 'wpmind' ),
                        ],
                    ],
                ],
                'output_schema'       => [
                    'type'        => 'object',
                    'description' => __( 'Chat execution result payload.', 'wpmind' ),
                ],
                'permission_callback' => [ $this, 'can_read_gateway_data' ],
                'execute_callback'    => [ $this, 'execute_chat_ability' ],
                'meta'                => [
                    'annotations' => $read_annotations,
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ],
            'mind/get-providers' => [
                'label'               => __( 'WPMind Get Providers', 'wpmind' ),
                'description'         => __( 'Get provider availability, routing and failover status.', 'wpmind' ),
                'category'            => self::ABILITY_CATEGORY,
                'input_schema'        => [
                    'type'       => 'object',
                    'properties' => [],
                ],
                'output_schema'       => [
                    'type'        => 'object',
                    'description' => __( 'Provider status payload.', 'wpmind' ),
                ],
                'permission_callback' => [ $this, 'can_read_gateway_data' ],
                'execute_callback'    => [ $this, 'execute_get_providers_ability' ],
                'meta'                => [
                    'annotations' => $read_annotations,
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ],
            'mind/get-usage-stats' => [
                'label'               => __( 'WPMind Get Usage Stats', 'wpmind' ),
                'description'         => __( 'Get usage, cost, and cache statistics.', 'wpmind' ),
                'category'            => self::ABILITY_CATEGORY,
                'input_schema'        => [
                    'type'       => 'object',
                    'properties' => [],
                ],
                'output_schema'       => [
                    'type'        => 'object',
                    'description' => __( 'Usage statistics payload.', 'wpmind' ),
                ],
                'permission_callback' => [ $this, 'can_read_gateway_data' ],
                'execute_callback'    => [ $this, 'execute_get_usage_stats_ability' ],
                'meta'                => [
                    'annotations' => $read_annotations,
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ],
            'mind/get-budget-status' => [
                'label'               => __( 'WPMind Get Budget Status', 'wpmind' ),
                'description'         => __( 'Get budget guardrail status and thresholds.', 'wpmind' ),
                'category'            => self::ABILITY_CATEGORY,
                'input_schema'        => [
                    'type'       => 'object',
                    'properties' => [],
                ],
                'output_schema'       => [
                    'type'        => 'object',
                    'description' => __( 'Budget summary payload.', 'wpmind' ),
                ],
                'permission_callback' => [ $this, 'can_read_gateway_data' ],
                'execute_callback'    => [ $this, 'execute_get_budget_status_ability' ],
                'meta'                => [
                    'annotations' => $read_annotations,
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ],
            'mind/switch-strategy' => [
                'label'               => __( 'WPMind Switch Strategy', 'wpmind' ),
                'description'         => __( 'Switch active routing strategy.', 'wpmind' ),
                'category'            => self::ABILITY_CATEGORY,
                'input_schema'        => [
                    'type'       => 'object',
                    'required'   => [ 'strategy' ],
                    'properties' => [
                        'strategy' => [
                            'type'        => 'string',
                            'description' => __( 'Routing strategy slug.', 'wpmind' ),
                        ],
                    ],
                ],
                'output_schema'       => [
                    'type'        => 'object',
                    'description' => __( 'Routing strategy switch result payload.', 'wpmind' ),
                ],
                'permission_callback' => [ $this, 'can_manage_gateway' ],
                'execute_callback'    => [ $this, 'execute_switch_strategy_ability' ],
                'meta'                => [
                    'annotations' => [
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => false,
                    ],
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ],
        ];

        /**
         * Filter MCP ability definitions.
         *
         * @since 4.0.0
         * @param array<string,array> $definitions Ability definitions.
         */
        return apply_filters( 'wpmind_mcp_gateway_ability_definitions', $definitions );
    }

    /**
     * Get ability names that are already registered.
     *
     * @return array<string>
     */
    private function get_registered_ability_names(): array {
        $names = array_keys( $this->get_ability_definitions() );

        if ( ! function_exists( 'wp_has_ability' ) ) {
            return [];
        }

        return array_values(
            array_filter(
                $names,
                static fn( string $name ): bool => wp_has_ability( $name )
            )
        );
    }

    /**
     * Normalize callback input payload.
     *
     * @param mixed $input Raw callback input.
     * @return array
     */
    private function normalize_input( $input ): array {
        if ( is_array( $input ) ) {
            return $input;
        }

        if ( is_object( $input ) ) {
            if ( method_exists( $input, 'get_params' ) ) {
                $params = $input->get_params();
                if ( is_array( $params ) ) {
                    return $params;
                }
            }

            if ( method_exists( $input, 'to_array' ) ) {
                $params = $input->to_array();
                if ( is_array( $params ) ) {
                    return $params;
                }
            }

            $vars = get_object_vars( $input );
            if ( is_array( $vars ) ) {
                return $vars;
            }
        }

        return [];
    }

    /**
     * Build a normalized error payload.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @param array  $data Extra data.
     * @return array
     */
    private function error_result( string $code, string $message, array $data = [] ): array {
        return [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
                'data'    => $data,
            ],
        ];
    }

    /**
     * Convert WP_Error to MCP payload.
     *
     * @param WP_Error $error WP error instance.
     * @return array
     */
    private function error_from_wp_error( WP_Error $error ): array {
        return $this->error_result(
            $error->get_error_code(),
            $error->get_error_message(),
            [
                'details' => $error->get_error_data(),
            ]
        );
    }
}
