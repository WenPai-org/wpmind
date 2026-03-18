<?php
/**
 * Routing Hooks - 路由钩子集成
 *
 * 将 IntelligentRouter 集成到 WordPress 过滤器系统
 *
 * @package WPMind
 * @since 3.2.0
 */

declare(strict_types=1);

namespace WPMind\Routing;

/**
 * 路由钩子类
 *
 * 负责将智能路由器连接到 wpmind_select_provider 过滤器
 */
class RoutingHooks {

	private static ?RoutingHooks $instance = null;

	/** @var bool 是否启用智能路由 */
	private bool $enabled = true;

	/**
	 * 获取单例实例
	 */
	public static function instance(): RoutingHooks {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_settings();
		$this->register_hooks();
	}

	/**
	 * 加载设置
	 */
	private function load_settings(): void {
		$settings      = get_option( 'wpmind_routing_settings', [] );
		$this->enabled = $settings['enabled'] ?? true;
	}

	/**
	 * 注册钩子
	 */
	private function register_hooks(): void {
		// 只有启用时才注册过滤器
		if ( $this->enabled ) {
			// 优先级 10，允许其他插件在之前或之后修改
			add_filter( 'wpmind_select_provider', [ $this, 'select_provider' ], 10, 2 );
		}

		// 注册设置变更钩子
		add_action( 'update_option_wpmind_routing_settings', [ $this, 'on_settings_update' ], 10, 2 );
	}

	/**
	 * 选择 Provider 过滤器回调
	 *
	 * @param string $provider 当前选择的 Provider
	 * @param string $context  请求上下文标识
	 * @return string 选择的 Provider ID
	 */
	public function select_provider( string $provider, string $context ): string {
		// 如果明确指定了 Provider（非 auto），尊重用户选择
		if ( $provider !== 'auto' && ! empty( $provider ) ) {
			// 但仍然检查该 Provider 是否可用
			$router         = IntelligentRouter::instance();
			$routingContext = $this->build_routing_context( $context, $provider );

			// 如果首选 Provider 可用，直接返回
			$selected = $router->route( $routingContext );
			if ( $selected === $provider ) {
				return $provider;
			}

			// 首选不可用时，记录日志并使用路由结果
			do_action( 'wpmind_routing_fallback', $provider, $selected, $context );
			return $selected ?? $provider;
		}

		// auto 模式：使用智能路由
		$router         = IntelligentRouter::instance();
		$routingContext = $this->build_routing_context( $context );

		$selected = $router->route( $routingContext );

		if ( $selected !== null ) {
			// 记录路由决策
			do_action( 'wpmind_routing_decision', $selected, $router->get_current_strategy(), $context );
			return $selected;
		}

		// 路由失败，返回默认 Provider
		return get_option( 'wpmind_default_provider', 'deepseek' );
	}

	/**
	 * 构建路由上下文
	 *
	 * @param string      $context           请求上下文标识
	 * @param string|null $preferredProvider 首选 Provider
	 * @return RoutingContext
	 */
	private function build_routing_context( string $context, ?string $preferredProvider = null ): RoutingContext {
		$routingContext = RoutingContext::create();

		// 设置首选 Provider
		if ( $preferredProvider !== null && $preferredProvider !== 'auto' ) {
			$routingContext->with_preferred_provider( $preferredProvider );
		}

		// 根据上下文设置模型类型
		$modelType = $this->infer_model_type( $context );
		if ( $modelType !== null ) {
			$routingContext->with_model_type( $modelType );
		}

		// 添加上下文元数据
		$routingContext->with_metadata( 'context', $context );
		$routingContext->with_metadata( 'timestamp', time() );

		return $routingContext;
	}

	/**
	 * 从上下文推断模型类型
	 *
	 * @param string $context 上下文标识
	 * @return string|null
	 */
	private function infer_model_type( string $context ): ?string {
		// 根据上下文关键词推断模型类型
		$contextLower = strtolower( $context );

		if ( str_contains( $contextLower, 'embed' ) || str_contains( $contextLower, 'vector' ) ) {
			return 'embedding';
		}

		if ( str_contains( $contextLower, 'image' ) || str_contains( $contextLower, 'vision' ) ) {
			return 'vision';
		}

		if ( str_contains( $contextLower, 'code' ) || str_contains( $contextLower, 'completion' ) ) {
			return 'completion';
		}

		// 默认为 chat
		return 'chat';
	}

	/**
	 * 设置更新回调
	 *
	 * @param mixed $old_value 旧值
	 * @param mixed $new_value 新值
	 */
	public function on_settings_update( $old_value, $new_value ): void {
		// 刷新路由器
		IntelligentRouter::instance()->refresh();

		// 更新启用状态
		$this->enabled = $new_value['enabled'] ?? true;
	}

	/**
	 * 检查智能路由是否启用
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * 启用智能路由
	 */
	public function enable(): void {
		$this->enabled       = true;
		$settings            = get_option( 'wpmind_routing_settings', [] );
		$settings['enabled'] = true;
		update_option( 'wpmind_routing_settings', $settings );

		// 重新注册过滤器
		if ( ! has_filter( 'wpmind_select_provider', [ $this, 'select_provider' ] ) ) {
			add_filter( 'wpmind_select_provider', [ $this, 'select_provider' ], 10, 2 );
		}
	}

	/**
	 * 禁用智能路由
	 */
	public function disable(): void {
		$this->enabled       = false;
		$settings            = get_option( 'wpmind_routing_settings', [] );
		$settings['enabled'] = false;
		update_option( 'wpmind_routing_settings', $settings );

		// 移除过滤器
		remove_filter( 'wpmind_select_provider', [ $this, 'select_provider' ], 10 );
	}
}
