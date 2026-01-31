<?php
/**
 * Budget Alert - 预算告警通知
 *
 * 处理预算告警的发送和显示
 *
 * @package WPMind
 * @since 1.7.0
 */

declare(strict_types=1);

namespace WPMind\Budget;

use WPMind\Usage\UsageTracker;

class BudgetAlert
{
    /**
     * 单例实例
     */
    private static ?BudgetAlert $instance = null;

    /**
     * 获取单例实例
     */
    public static function instance(): BudgetAlert
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有构造函数
     */
    private function __construct()
    {
        // 注册管理员通知钩子
        add_action('admin_notices', [$this, 'displayAdminNotices']);
    }

    /**
     * 初始化告警系统
     */
    public static function init(): void
    {
        self::instance();
    }

    /**
     * 检查并发送告警
     *
     * 在每次 AI 请求后调用
     */
    public function checkAndAlert(): void
    {
        $manager = BudgetManager::instance();

        if (!$manager->isEnabled()) {
            return;
        }

        $checker = BudgetChecker::instance();
        $globalCheck = $checker->checkGlobalBudget();

        // 检查是否需要发送告警
        foreach ($globalCheck['details'] as $key => $detail) {
            if ($detail['status'] === BudgetChecker::STATUS_WARNING) {
                $this->sendWarningAlert($key, $detail);
            } elseif ($detail['status'] === BudgetChecker::STATUS_EXCEEDED) {
                $this->sendExceededAlert($key, $detail);
            }
        }
    }

    /**
     * 发送接近限额告警
     */
    private function sendWarningAlert(string $key, array $detail): void
    {
        $checker = BudgetChecker::instance();

        if (!$checker->shouldSendAlert("warning_{$key}")) {
            return;
        }

        $manager = BudgetManager::instance();
        $notifications = $manager->getNotificationSettings();

        $message = $this->formatAlertMessage($key, $detail, 'warning');

        // 管理员通知
        if ($notifications['admin_notice']) {
            $this->storeAdminNotice($message, 'warning');
        }

        // 邮件通知
        if ($notifications['email_alert'] && !empty($notifications['email_address'])) {
            $this->sendEmailAlert($notifications['email_address'], $message, 'warning');
        }

        $checker->markAlertSent("warning_{$key}");
    }

    /**
     * 发送超限告警
     */
    private function sendExceededAlert(string $key, array $detail): void
    {
        $checker = BudgetChecker::instance();

        if (!$checker->shouldSendAlert("exceeded_{$key}")) {
            return;
        }

        $manager = BudgetManager::instance();
        $notifications = $manager->getNotificationSettings();

        $message = $this->formatAlertMessage($key, $detail, 'exceeded');

        // 管理员通知
        if ($notifications['admin_notice']) {
            $this->storeAdminNotice($message, 'error');
        }

        // 邮件通知
        if ($notifications['email_alert'] && !empty($notifications['email_address'])) {
            $this->sendEmailAlert($notifications['email_address'], $message, 'exceeded');
        }

        $checker->markAlertSent("exceeded_{$key}");
    }

    /**
     * 格式化告警消息
     */
    private function formatAlertMessage(string $key, array $detail, string $type): string
    {
        $labels = [
            'daily_usd'   => __('每日 USD 预算', 'wpmind'),
            'daily_cny'   => __('每日 CNY 预算', 'wpmind'),
            'monthly_usd' => __('每月 USD 预算', 'wpmind'),
            'monthly_cny' => __('每月 CNY 预算', 'wpmind'),
        ];

        $label = $labels[$key] ?? $key;
        $current = $this->formatCost($detail['current'], $key);
        $limit = $this->formatCost($detail['limit'], $key);
        $percentage = $detail['percentage'];

        if ($type === 'warning') {
            return sprintf(
                /* translators: 1: budget type, 2: current cost, 3: limit, 4: percentage */
                __('WPMind 预算告警：%1$s 已使用 %4$s%%（%2$s / %3$s）', 'wpmind'),
                $label,
                $current,
                $limit,
                $percentage
            );
        } else {
            return sprintf(
                /* translators: 1: budget type, 2: current cost, 3: limit */
                __('WPMind 预算超限：%1$s 已超出限额（%2$s / %3$s）', 'wpmind'),
                $label,
                $current,
                $limit
            );
        }
    }

    /**
     * 格式化费用
     */
    private function formatCost(float $cost, string $key): string
    {
        $currency = str_contains($key, 'cny') ? 'CNY' : 'USD';
        return UsageTracker::formatCost($cost, $currency);
    }

    /**
     * 存储管理员通知
     */
    private function storeAdminNotice(string $message, string $type): void
    {
        $notices = get_transient('wpmind_budget_notices');
        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            'message' => $message,
            'type'    => $type,
            'time'    => time(),
        ];

        // 只保留最近 5 条通知
        $notices = array_slice($notices, -5);

        set_transient('wpmind_budget_notices', $notices, HOUR_IN_SECONDS);
    }

    /**
     * 显示管理员通知
     */
    public function displayAdminNotices(): void
    {
        // 只在 WPMind 设置页面显示
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_wpmind') {
            return;
        }

        $notices = get_transient('wpmind_budget_notices');
        if (!is_array($notices) || empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-warning';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($notice['message'])
            );
        }

        // 显示后清除通知
        delete_transient('wpmind_budget_notices');
    }

    /**
     * 发送邮件告警
     */
    private function sendEmailAlert(string $email, string $message, string $type): void
    {
        $subject = $type === 'exceeded'
            ? __('[WPMind] 预算超限告警', 'wpmind')
            : __('[WPMind] 预算接近限额告警', 'wpmind');

        $body = $message . "\n\n";
        $body .= sprintf(
            __('站点：%s', 'wpmind'),
            get_bloginfo('name')
        ) . "\n";
        $body .= sprintf(
            __('时间：%s', 'wpmind'),
            wp_date('Y-m-d H:i:s')
        ) . "\n\n";
        $body .= __('请登录 WordPress 后台查看详情。', 'wpmind');

        wp_mail($email, $subject, $body);
    }

    /**
     * 获取预算状态徽章 HTML
     */
    public static function getStatusBadge(string $status): string
    {
        $class = BudgetChecker::getStatusClass($status);
        $label = BudgetChecker::getStatusLabel($status);

        return sprintf(
            '<span class="wpmind-budget-badge %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * 获取进度条 HTML
     */
    public static function getProgressBar(float $percentage, string $status): string
    {
        $class = BudgetChecker::getStatusClass($status);
        $width = min(100, max(0, $percentage));

        return sprintf(
            '<div class="wpmind-budget-progress">
                <div class="wpmind-budget-progress-bar %s" style="width: %s%%"></div>
            </div>
            <span class="wpmind-budget-percentage">%s%%</span>',
            esc_attr($class),
            esc_attr($width),
            esc_html(number_format($percentage, 1))
        );
    }
}
