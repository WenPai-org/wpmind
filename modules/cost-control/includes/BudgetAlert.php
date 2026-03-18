<?php
/**
 * Budget Alert - 预算告警通知
 *
 * 处理预算告警的发送和显示
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\CostControl;

class BudgetAlert {

	/**
	 * 单例实例
	 */
	private static ?BudgetAlert $instance = null;

	/**
	 * 获取单例实例
	 */
	public static function instance(): BudgetAlert {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 私有构造函数
	 */
	private function __construct() {
		// 注册管理员通知钩子
		add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
	}

	/**
	 * 初始化告警系统
	 */
	public static function init(): void {
		self::instance();
	}

	/**
	 * 检查并发送告警
	 */
	public function check_and_alert(): void {
		$manager = BudgetManager::instance();

		if ( ! $manager->is_enabled() ) {
			return;
		}

		$checker     = BudgetChecker::instance();
		$globalCheck = $checker->check_global_budget();

		foreach ( ( $globalCheck['details'] ?? [] ) as $key => $detail ) {
			if ( ( $detail['status'] ?? '' ) === BudgetChecker::STATUS_WARNING ) {
				$this->send_warning_alert( $key, $detail );
			} elseif ( ( $detail['status'] ?? '' ) === BudgetChecker::STATUS_EXCEEDED ) {
				$this->send_exceeded_alert( $key, $detail );
			}
		}
	}

	/**
	 * 发送接近限额告警
	 */
	private function send_warning_alert( string $key, array $detail ): void {
		$checker = BudgetChecker::instance();

		if ( ! $checker->should_send_alert( "warning_{$key}" ) ) {
			return;
		}

		$manager       = BudgetManager::instance();
		$notifications = $manager->get_notification_settings();

		$message = $this->format_alert_message( $key, $detail, 'warning' );

		if ( $notifications['admin_notice'] ?? false ) {
			$this->store_admin_notice( $message, 'warning' );
		}

		if ( ( $notifications['email_alert'] ?? false ) && ! empty( $notifications['email_address'] ?? '' ) ) {
			$this->send_email_alert( $notifications['email_address'], $message, 'warning' );
		}

		$checker->mark_alert_sent( "warning_{$key}" );
	}

	/**
	 * 发送超限告警
	 */
	private function send_exceeded_alert( string $key, array $detail ): void {
		$checker = BudgetChecker::instance();

		if ( ! $checker->should_send_alert( "exceeded_{$key}" ) ) {
			return;
		}

		$manager       = BudgetManager::instance();
		$notifications = $manager->get_notification_settings();

		$message = $this->format_alert_message( $key, $detail, 'exceeded' );

		if ( $notifications['admin_notice'] ?? false ) {
			$this->store_admin_notice( $message, 'error' );
		}

		if ( ( $notifications['email_alert'] ?? false ) && ! empty( $notifications['email_address'] ?? '' ) ) {
			$this->send_email_alert( $notifications['email_address'], $message, 'exceeded' );
		}

		$checker->mark_alert_sent( "exceeded_{$key}" );
	}

	/**
	 * 格式化告警消息
	 */
	private function format_alert_message( string $key, array $detail, string $type ): string {
		$labels = [
			'daily_usd'   => __( '每日 USD 预算', 'wpmind' ),
			'daily_cny'   => __( '每日 CNY 预算', 'wpmind' ),
			'monthly_usd' => __( '每月 USD 预算', 'wpmind' ),
			'monthly_cny' => __( '每月 CNY 预算', 'wpmind' ),
		];

		$label      = $labels[ $key ] ?? $key;
		$current    = $this->format_cost( $detail['current'] ?? 0, $key );
		$limit      = $this->format_cost( $detail['limit'] ?? 0, $key );
		$percentage = $detail['percentage'] ?? 0;

		if ( $type === 'warning' ) {
			return sprintf(
				__( 'WPMind 预算告警：%1$s 已使用 %4$s%%（%2$s / %3$s）', 'wpmind' ),
				$label,
				$current,
				$limit,
				$percentage
			);
		} else {
			return sprintf(
				__( 'WPMind 预算超限：%1$s 已超出限额（%2$s / %3$s）', 'wpmind' ),
				$label,
				$current,
				$limit
			);
		}
	}

	/**
	 * 格式化费用
	 */
	private function format_cost( float $cost, string $key ): string {
		$currency = str_contains( $key, 'cny' ) ? 'CNY' : 'USD';
		return UsageTracker::format_cost( $cost, $currency );
	}

	/**
	 * 存储管理员通知
	 */
	private function store_admin_notice( string $message, string $type ): void {
		$notices = get_transient( 'wpmind_budget_notices' );
		if ( ! is_array( $notices ) ) {
			$notices = [];
		}

		$notices[] = [
			'message' => $message,
			'type'    => $type,
			'time'    => time(),
		];

		$notices = array_slice( $notices, -5 );

		set_transient( 'wpmind_budget_notices', $notices, HOUR_IN_SECONDS );
	}

	/**
	 * 显示管理员通知
	 */
	public function display_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_wpmind' ) {
			return;
		}

		$notices = get_transient( 'wpmind_budget_notices' );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$class = ( $notice['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-warning';
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr( $class ),
				esc_html( $notice['message'] ?? '' )
			);
		}

		delete_transient( 'wpmind_budget_notices' );
	}

	/**
	 * 发送邮件告警
	 */
	private function send_email_alert( string $email, string $message, string $type ): void {
		$subject = $type === 'exceeded'
			? __( '[WPMind] 预算超限告警', 'wpmind' )
			: __( '[WPMind] 预算接近限额告警', 'wpmind' );

		$body  = $message . "\n\n";
		$body .= sprintf(
			__( '站点：%s', 'wpmind' ),
			get_bloginfo( 'name' )
		) . "\n";
		$body .= sprintf(
			__( '时间：%s', 'wpmind' ),
			wp_date( 'Y-m-d H:i:s' )
		) . "\n\n";
		$body .= __( '请登录 WordPress 后台查看详情。', 'wpmind' );

		wp_mail( $email, $subject, $body );
	}

	/**
	 * 获取预算状态徽章 HTML
	 */
	public static function get_status_badge( string $status ): string {
		$class = BudgetChecker::get_status_class( $status );
		$label = BudgetChecker::get_status_label( $status );

		return sprintf(
			'<span class="wpmind-budget-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * 获取进度条 HTML
	 */
	public static function get_progress_bar( float $percentage, string $status ): string {
		$class = BudgetChecker::get_status_class( $status );
		$width = min( 100, max( 0, $percentage ) );

		return sprintf(
			'<div class="wpmind-budget-progress">
                <div class="wpmind-budget-progress-bar %s" style="width: %s%%"></div>
            </div>
            <span class="wpmind-budget-percentage">%s%%</span>',
			esc_attr( $class ),
			esc_attr( $width ),
			esc_html( number_format( $percentage, 1 ) )
		);
	}
}
