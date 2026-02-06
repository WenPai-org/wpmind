<?php
/**
 * Cost Control Module
 *
 * AI service cost control module for WPMind.
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\CostControl;

use WPMind\Core\ModuleInterface;

// Load module classes.
require_once __DIR__ . '/includes/UsageTracker.php';
require_once __DIR__ . '/includes/BudgetManager.php';
require_once __DIR__ . '/includes/BudgetChecker.php';
require_once __DIR__ . '/includes/BudgetAlert.php';

/**
 * Class CostControlModule
 *
 * Main entry point for the cost control module.
 */
class CostControlModule implements ModuleInterface {

	/**
	 * Module components.
	 *
	 * @var array
	 */
	private array $components = [];

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'cost-control';
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Cost Control', 'wpmind' );
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'AI 服务费用控制 - 用量追踪、预算限额、告警通知', 'wpmind' );
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Check dependencies.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		// Cost control module has no external dependencies.
		return true;
	}

	/**
	 * Get settings tab slug.
	 *
	 * @return string|null
	 */
	public function get_settings_tab(): ?string {
		return 'cost-control';
	}

	/**
	 * Initialize the module.
	 */
	public function init(): void {
		// Initialize components.
		$this->init_components();

		// Register settings tab.
		add_filter( 'wpmind_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_wpmind_save_cost_control_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_wpmind_get_cost_control_status', array( $this, 'ajax_get_status' ) );
		add_action( 'wp_ajax_wpmind_clear_usage_stats', array( $this, 'ajax_clear_stats' ) );

		// Register usage recording hook (event-driven architecture).
		add_action( 'wpmind_usage_record', array( $this, 'record_usage' ), 10, 5 );

		/**
		 * Fires when Cost Control module is initialized.
		 *
		 * @param CostControlModule $this Module instance.
		 */
		do_action( 'wpmind_cost_control_init', $this );
	}

	/**
	 * Initialize module components.
	 */
	private function init_components(): void {
		// Usage Tracker (always active).
		$this->components['usage_tracker'] = UsageTracker::class;

		// Budget Manager.
		$this->components['budget_manager'] = BudgetManager::instance();

		// Budget Checker.
		$this->components['budget_checker'] = BudgetChecker::instance();

		// Budget Alert (initialize to register hooks).
		BudgetAlert::init();
		$this->components['budget_alert'] = BudgetAlert::instance();
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['cost-control'] = array(
			'title'    => __( 'Cost Control', 'wpmind' ),
			'icon'     => 'ri-money-cny-circle-line',
			'template' => WPMIND_PATH . 'modules/cost-control/templates/settings.php',
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * Record usage via event-driven hook.
	 *
	 * @param string $provider    Provider ID.
	 * @param string $model       Model name.
	 * @param int    $input_tokens  Input tokens.
	 * @param int    $output_tokens Output tokens.
	 * @param int    $latency_ms    Latency in milliseconds.
	 */
	public function record_usage( string $provider, string $model, int $input_tokens, int $output_tokens, int $latency_ms = 0 ): void {
		UsageTracker::record( $provider, $model, $input_tokens, $output_tokens, $latency_ms );

		// Check budget and send alerts.
		BudgetAlert::instance()->check_and_alert();

		/**
		 * Fires after usage has been recorded by the Cost Control module.
		 *
		 * This action is used to signal that usage recording has been handled,
		 * preventing duplicate recording by backward compatibility code.
		 *
		 * @since 1.0.0
		 *
		 * @param string $provider      Provider ID.
		 * @param string $model         Model name.
		 * @param int    $input_tokens  Input tokens.
		 * @param int    $output_tokens Output tokens.
		 * @param int    $latency_ms    Latency in milliseconds.
		 */
		do_action( 'wpmind_usage_recorded', $provider, $model, $input_tokens, $output_tokens, $latency_ms );
	}

	/**
	 * AJAX handler for saving settings.
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		// Parse JSON data.
		$json_input = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$input = json_decode( $json_input, true );

		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => __( '无效的数据格式', 'wpmind' ) ) );
		}

		// Build settings array.
		$settings = array();
		$settings['enabled'] = ! empty( $input['enabled'] );

		$settings['global'] = array(
			'daily_limit_usd'   => (float) ( $input['global']['daily_limit_usd'] ?? 0 ),
			'daily_limit_cny'   => (float) ( $input['global']['daily_limit_cny'] ?? 0 ),
			'monthly_limit_usd' => (float) ( $input['global']['monthly_limit_usd'] ?? 0 ),
			'monthly_limit_cny' => (float) ( $input['global']['monthly_limit_cny'] ?? 0 ),
			'alert_threshold'   => (int) ( $input['global']['alert_threshold'] ?? 80 ),
		);

		$settings['enforcement_mode'] = sanitize_text_field( $input['enforcement_mode'] ?? 'alert' );

		$settings['notifications'] = array(
			'admin_notice'  => ! empty( $input['notifications']['admin_notice'] ),
			'email_alert'   => ! empty( $input['notifications']['email_alert'] ),
			'email_address' => sanitize_email( $input['notifications']['email_address'] ?? '' ),
		);

		// Provider-specific settings.
		$settings['providers'] = array();
		if ( ! empty( $input['providers'] ) && is_array( $input['providers'] ) ) {
			foreach ( $input['providers'] as $provider => $limits ) {
				$provider = sanitize_key( $provider );
				$settings['providers'][ $provider ] = array(
					'daily_limit'   => (float) ( $limits['daily_limit'] ?? 0 ),
					'monthly_limit' => (float) ( $limits['monthly_limit'] ?? 0 ),
				);
			}
		}

		$manager = BudgetManager::instance();
		$result = $manager->save_settings( $settings );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( '设置已保存', 'wpmind' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( '保存失败', 'wpmind' ) ) );
		}
	}

	/**
	 * AJAX handler for getting status.
	 */
	public function ajax_get_status(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		$checker = BudgetChecker::instance();
		$summary = $checker->get_summary();

		$stats = UsageTracker::get_stats();
		$today = UsageTracker::get_today_stats();
		$month = UsageTracker::get_month_stats();

		wp_send_json_success( array(
			'budget'  => $summary,
			'stats'   => $stats,
			'today'   => $today,
			'month'   => $month,
		) );
	}

	/**
	 * AJAX handler for clearing stats.
	 */
	public function ajax_clear_stats(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'wpmind' ) ) );
		}

		UsageTracker::clearAll();
		wp_send_json_success( array( 'message' => __( '用量统计已清除', 'wpmind' ) ) );
	}

	/**
	 * Get a component instance.
	 *
	 * @param string $name Component name.
	 * @return object|null Component instance or null.
	 */
	public function get_component( string $name ): ?object {
		$component = $this->components[ $name ] ?? null;
		if ( is_string( $component ) && class_exists( $component ) ) {
			return null; // Static class, no instance.
		}
		return $component;
	}

	/**
	 * Get all components.
	 *
	 * @return array Components array.
	 */
	public function get_components(): array {
		return $this->components;
	}
}