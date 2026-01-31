<?php
/**
 * Budget Checker - 预算检查器
 *
 * 检查当前费用是否超出预算限额
 *
 * @package WPMind
 * @since 1.7.0
 */

declare(strict_types=1);

namespace WPMind\Budget;

use WPMind\Usage\UsageTracker;

class BudgetChecker
{
    /**
     * 检查结果常量
     */
    public const STATUS_OK        = 'ok';        // 正常
    public const STATUS_WARNING   = 'warning';   // 接近限额
    public const STATUS_EXCEEDED  = 'exceeded';  // 已超限

    /**
     * 单例实例
     */
    private static ?BudgetChecker $instance = null;

    /**
     * 告警状态缓存（避免重复告警）
     */
    private const ALERT_TRANSIENT_KEY = 'wpmind_budget_alerts_sent';

    /**
     * 获取单例实例
     */
    public static function instance(): BudgetChecker
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有构造函数
     */
    private function __construct() {}

    /**
     * 检查全局预算状态
     *
     * @return array ['status' => string, 'details' => array]
     */
    public function checkGlobalBudget(): array
    {
        $manager = BudgetManager::instance();

        if (!$manager->isEnabled() || !$manager->hasAnyLimits()) {
            return ['status' => self::STATUS_OK, 'details' => []];
        }

        $global = $manager->getGlobalBudget();
        $threshold = $manager->getAlertThreshold();

        // 获取当前用量
        $today = UsageTracker::getTodayStats();
        $month = UsageTracker::getMonthStats();

        $details = [];
        $status = self::STATUS_OK;

        // 检查每日 USD 限额
        if ($global['daily_limit_usd'] > 0) {
            $result = $this->checkLimit(
                $today['cost_usd'] ?? 0,
                $global['daily_limit_usd'],
                $threshold,
                'daily_usd'
            );
            $details['daily_usd'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        // 检查每日 CNY 限额
        if ($global['daily_limit_cny'] > 0) {
            $result = $this->checkLimit(
                $today['cost_cny'] ?? 0,
                $global['daily_limit_cny'],
                $threshold,
                'daily_cny'
            );
            $details['daily_cny'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        // 检查每月 USD 限额
        if ($global['monthly_limit_usd'] > 0) {
            $result = $this->checkLimit(
                $month['cost_usd'] ?? 0,
                $global['monthly_limit_usd'],
                $threshold,
                'monthly_usd'
            );
            $details['monthly_usd'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        // 检查每月 CNY 限额
        if ($global['monthly_limit_cny'] > 0) {
            $result = $this->checkLimit(
                $month['cost_cny'] ?? 0,
                $global['monthly_limit_cny'],
                $threshold,
                'monthly_cny'
            );
            $details['monthly_cny'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        return ['status' => $status, 'details' => $details];
    }

    /**
     * 检查服务商预算状态
     *
     * @param string $provider 服务商 ID
     * @return array ['status' => string, 'details' => array]
     */
    public function checkProviderBudget(string $provider): array
    {
        $manager = BudgetManager::instance();

        if (!$manager->isEnabled()) {
            return ['status' => self::STATUS_OK, 'details' => []];
        }

        $providerBudget = $manager->getProviderBudget($provider);
        if (!$providerBudget) {
            return ['status' => self::STATUS_OK, 'details' => []];
        }

        $threshold = $manager->getAlertThreshold();

        // 获取服务商用量
        $stats = UsageTracker::getStats();
        $providerStats = $stats['providers'][$provider] ?? null;

        if (!$providerStats) {
            return ['status' => self::STATUS_OK, 'details' => []];
        }

        $details = [];
        $status = self::STATUS_OK;

        // 获取服务商货币类型
        $currency = UsageTracker::getCurrency($provider);
        $currentCost = $providerStats['total_cost'] ?? 0;

        // 检查每日限额
        if (!empty($providerBudget['daily_limit']) && $providerBudget['daily_limit'] > 0) {
            // 需要获取今日该服务商的费用（从日统计中提取）
            $todayCost = $this->getProviderTodayCost($provider);
            $result = $this->checkLimit(
                $todayCost,
                $providerBudget['daily_limit'],
                $threshold,
                "provider_{$provider}_daily"
            );
            $details['daily'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        // 检查每月限额
        if (!empty($providerBudget['monthly_limit']) && $providerBudget['monthly_limit'] > 0) {
            $monthCost = $this->getProviderMonthCost($provider);
            $result = $this->checkLimit(
                $monthCost,
                $providerBudget['monthly_limit'],
                $threshold,
                "provider_{$provider}_monthly"
            );
            $details['monthly'] = $result;
            $status = $this->mergeStatus($status, $result['status']);
        }

        return ['status' => $status, 'details' => $details, 'currency' => $currency];
    }

    /**
     * 检查是否允许发起请求
     *
     * @param string|null $provider 服务商 ID（可选）
     * @return array ['allowed' => bool, 'reason' => string|null, 'status' => array]
     */
    public function canMakeRequest(?string $provider = null): array
    {
        $manager = BudgetManager::instance();

        if (!$manager->isEnabled()) {
            return ['allowed' => true, 'reason' => null, 'status' => []];
        }

        $mode = $manager->getEnforcementMode();

        // 仅告警模式始终允许请求
        if ($mode === BudgetManager::MODE_ALERT) {
            return ['allowed' => true, 'reason' => null, 'status' => $this->checkGlobalBudget()];
        }

        // 检查全局预算
        $globalCheck = $this->checkGlobalBudget();
        if ($globalCheck['status'] === self::STATUS_EXCEEDED) {
            return [
                'allowed' => false,
                'reason'  => __('已超出全局预算限额', 'wpmind'),
                'status'  => $globalCheck,
            ];
        }

        // 检查服务商预算
        if ($provider) {
            $providerCheck = $this->checkProviderBudget($provider);
            if ($providerCheck['status'] === self::STATUS_EXCEEDED) {
                return [
                    'allowed' => false,
                    'reason'  => sprintf(__('已超出 %s 的预算限额', 'wpmind'), $provider),
                    'status'  => $providerCheck,
                ];
            }
        }

        return ['allowed' => true, 'reason' => null, 'status' => $globalCheck];
    }

    /**
     * 获取所有服务商的预算状态
     *
     * @return array
     */
    public function getAllProviderStatus(): array
    {
        $manager = BudgetManager::instance();
        $settings = $manager->getSettings();
        $result = [];

        foreach ($settings['providers'] as $provider => $limits) {
            $result[$provider] = $this->checkProviderBudget($provider);
        }

        return $result;
    }

    /**
     * 获取预算摘要（用于 UI 显示）
     *
     * @return array
     */
    public function getSummary(): array
    {
        $manager = BudgetManager::instance();

        if (!$manager->isEnabled()) {
            return [
                'enabled' => false,
                'global'  => null,
                'providers' => [],
            ];
        }

        $global = $this->checkGlobalBudget();
        $providers = $this->getAllProviderStatus();

        // 计算使用百分比
        $globalBudget = $manager->getGlobalBudget();
        $today = UsageTracker::getTodayStats();
        $month = UsageTracker::getMonthStats();

        $percentages = [];

        if ($globalBudget['daily_limit_usd'] > 0) {
            $percentages['daily_usd'] = min(100, round(
                (($today['cost_usd'] ?? 0) / $globalBudget['daily_limit_usd']) * 100,
                1
            ));
        }

        if ($globalBudget['daily_limit_cny'] > 0) {
            $percentages['daily_cny'] = min(100, round(
                (($today['cost_cny'] ?? 0) / $globalBudget['daily_limit_cny']) * 100,
                1
            ));
        }

        if ($globalBudget['monthly_limit_usd'] > 0) {
            $percentages['monthly_usd'] = min(100, round(
                (($month['cost_usd'] ?? 0) / $globalBudget['monthly_limit_usd']) * 100,
                1
            ));
        }

        if ($globalBudget['monthly_limit_cny'] > 0) {
            $percentages['monthly_cny'] = min(100, round(
                (($month['cost_cny'] ?? 0) / $globalBudget['monthly_limit_cny']) * 100,
                1
            ));
        }

        return [
            'enabled'     => true,
            'global'      => $global,
            'providers'   => $providers,
            'percentages' => $percentages,
            'threshold'   => $manager->getAlertThreshold(),
            'mode'        => $manager->getEnforcementMode(),
        ];
    }

    /**
     * 检查单个限额
     */
    private function checkLimit(float $current, float $limit, int $threshold, string $key): array
    {
        if ($limit <= 0) {
            return [
                'status'     => self::STATUS_OK,
                'current'    => $current,
                'limit'      => $limit,
                'percentage' => 0,
            ];
        }

        $percentage = ($current / $limit) * 100;

        if ($percentage >= 100) {
            $status = self::STATUS_EXCEEDED;
        } elseif ($percentage >= $threshold) {
            $status = self::STATUS_WARNING;
        } else {
            $status = self::STATUS_OK;
        }

        return [
            'status'     => $status,
            'current'    => $current,
            'limit'      => $limit,
            'percentage' => round($percentage, 1),
        ];
    }

    /**
     * 合并状态（取最严重的）
     */
    private function mergeStatus(string $current, string $new): string
    {
        $priority = [
            self::STATUS_OK      => 0,
            self::STATUS_WARNING => 1,
            self::STATUS_EXCEEDED => 2,
        ];

        return ($priority[$new] ?? 0) > ($priority[$current] ?? 0) ? $new : $current;
    }

    /**
     * 获取服务商今日费用
     *
     * 注意：当前 UsageTracker 没有按服务商分日统计，
     * 这里从历史记录中计算
     */
    private function getProviderTodayCost(string $provider): float
    {
        $history = UsageTracker::getHistory(1000);
        $today = wp_date('Y-m-d');
        $todayStart = strtotime($today);
        $cost = 0;

        foreach ($history as $record) {
            if (($record['provider'] ?? '') !== $provider) {
                continue;
            }
            if (($record['timestamp'] ?? 0) >= $todayStart) {
                $cost += $record['cost'] ?? 0;
            }
        }

        return $cost;
    }

    /**
     * 获取服务商本月费用
     */
    private function getProviderMonthCost(string $provider): float
    {
        $stats = UsageTracker::getStats();
        return $stats['providers'][$provider]['total_cost'] ?? 0;
    }

    /**
     * 检查是否需要发送告警
     */
    public function shouldSendAlert(string $alertKey): bool
    {
        $sent = get_transient(self::ALERT_TRANSIENT_KEY);
        if (!is_array($sent)) {
            $sent = [];
        }

        // 每天只发送一次同类告警
        $today = wp_date('Y-m-d');
        $key = "{$alertKey}_{$today}";

        return !isset($sent[$key]);
    }

    /**
     * 标记告警已发送
     */
    public function markAlertSent(string $alertKey): void
    {
        $sent = get_transient(self::ALERT_TRANSIENT_KEY);
        if (!is_array($sent)) {
            $sent = [];
        }

        $today = wp_date('Y-m-d');
        $key = "{$alertKey}_{$today}";
        $sent[$key] = time();

        // 清理过期的告警记录
        $yesterday = wp_date('Y-m-d', strtotime('-1 day'));
        foreach ($sent as $k => $v) {
            if (!str_contains($k, $today) && !str_contains($k, $yesterday)) {
                unset($sent[$k]);
            }
        }

        set_transient(self::ALERT_TRANSIENT_KEY, $sent, DAY_IN_SECONDS * 2);
    }

    /**
     * 获取状态的显示标签
     */
    public static function getStatusLabel(string $status): string
    {
        $labels = [
            self::STATUS_OK       => __('正常', 'wpmind'),
            self::STATUS_WARNING  => __('接近限额', 'wpmind'),
            self::STATUS_EXCEEDED => __('已超限', 'wpmind'),
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * 获取状态的 CSS 类
     */
    public static function getStatusClass(string $status): string
    {
        $classes = [
            self::STATUS_OK       => 'wpmind-budget-ok',
            self::STATUS_WARNING  => 'wpmind-budget-warning',
            self::STATUS_EXCEEDED => 'wpmind-budget-exceeded',
        ];
        return $classes[$status] ?? '';
    }
}
