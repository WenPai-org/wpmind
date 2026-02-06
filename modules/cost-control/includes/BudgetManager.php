<?php
/**
 * Budget Manager - 预算管理器
 *
 * 管理 AI 服务的费用预算配置
 * 关键修复：使用 recursiveMerge() 替代 wp_parse_args() 处理嵌套数组
 *
 * @package WPMind\Modules\CostControl
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\CostControl;

class BudgetManager
{
    private const OPTION_KEY = 'wpmind_budget_settings';

    /**
     * 强制模式常量
     */
    public const MODE_ALERT    = 'alert';
    public const MODE_DISABLE  = 'disable';
    public const MODE_DOWNGRADE = 'downgrade';

    /**
     * 单例实例
     */
    private static ?BudgetManager $instance = null;

    /**
     * 预算配置缓存
     */
    private ?array $settings = null;

    /**
     * 获取单例实例
     */
    public static function instance(): BudgetManager
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
     * 获取默认预算配置
     */
    public function get_defaults(): array
    {
        return [
            'enabled' => false,
            'global' => [
                'daily_limit_usd'   => 0,
                'daily_limit_cny'   => 0,
                'monthly_limit_usd' => 0,
                'monthly_limit_cny' => 0,
                'alert_threshold'   => 80,
            ],
            'enforcement_mode' => self::MODE_ALERT,
            'providers' => [],
            'notifications' => [
                'admin_notice'  => true,
                'email_alert'   => false,
                'email_address' => '',
            ],
        ];
    }

    /**
     * 递归合并数组（关键修复）
     *
     * wp_parse_args() 无法正确处理嵌套数组，会导致嵌套数组被完全覆盖。
     * 此方法递归合并嵌套数组，确保默认值正确应用。
     *
     * @param array $args     用户提供的参数
     * @param array $defaults 默认值
     * @return array 合并后的数组
     */
    private function recursiveMerge(array $args, array $defaults): array
    {
        $result = $defaults;

        foreach ($args as $key => $value) {
            // 如果两边都是数组且默认值中存在该键，递归合并
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                // 检查是否是关联数组（非索引数组）
                if ($this->isAssociativeArray($defaults[$key])) {
                    $result[$key] = $this->recursiveMerge($value, $defaults[$key]);
                } else {
                    // 索引数组直接覆盖
                    $result[$key] = $value;
                }
            } else {
                // 非数组或默认值中不存在，直接覆盖
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 检查是否是关联数组
     *
     * @param array $arr 要检查的数组
     * @return bool 是否是关联数组
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * 获取预算配置
     */
    public function get_settings(): array
    {
        if (null === $this->settings) {
            $saved = get_option(self::OPTION_KEY, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            // 使用 recursiveMerge 替代 wp_parse_args
            $this->settings = $this->recursiveMerge($saved, $this->get_defaults());
        }
        return $this->settings;
    }

    /**
     * 保存预算配置
     */
    public function save_settings(array $settings): bool
    {
        $sanitized = $this->sanitizeSettings($settings);
        $result = update_option(self::OPTION_KEY, $sanitized, false);
        $this->settings = null; // 清除缓存
        return $result;
    }

    /**
     * 清理预算配置
     */
    private function sanitizeSettings(array $input): array
    {
        $defaults = $this->get_defaults();
        $sanitized = [];

        // 启用状态
        $sanitized['enabled'] = !empty($input['enabled']);

        // 全局设置
        $sanitized['global'] = [
            'daily_limit_usd'   => $this->sanitizeAmount($input['global']['daily_limit_usd'] ?? 0),
            'daily_limit_cny'   => $this->sanitizeAmount($input['global']['daily_limit_cny'] ?? 0),
            'monthly_limit_usd' => $this->sanitizeAmount($input['global']['monthly_limit_usd'] ?? 0),
            'monthly_limit_cny' => $this->sanitizeAmount($input['global']['monthly_limit_cny'] ?? 0),
            'alert_threshold'   => $this->sanitizeThreshold($input['global']['alert_threshold'] ?? 80),
        ];

        // 强制模式
        $sanitized['enforcement_mode'] = $this->sanitizeMode($input['enforcement_mode'] ?? self::MODE_ALERT);

        // 按服务商设置
        $sanitized['providers'] = [];
        if (!empty($input['providers']) && is_array($input['providers'])) {
            foreach ($input['providers'] as $provider => $limits) {
                $provider = sanitize_key($provider);
                if (!empty($limits['daily_limit']) || !empty($limits['monthly_limit'])) {
                    $sanitized['providers'][$provider] = [
                        'daily_limit'   => $this->sanitizeAmount($limits['daily_limit'] ?? 0),
                        'monthly_limit' => $this->sanitizeAmount($limits['monthly_limit'] ?? 0),
                    ];
                }
            }
        }

        // 通知设置
        $sanitized['notifications'] = [
            'admin_notice'  => !empty($input['notifications']['admin_notice']),
            'email_alert'   => !empty($input['notifications']['email_alert']),
            'email_address' => sanitize_email($input['notifications']['email_address'] ?? ''),
        ];

        return $sanitized;
    }

    /**
     * 清理金额
     */
    private function sanitizeAmount($value): float
    {
        $amount = (float) $value;
        return max(0, round($amount, 2));
    }

    /**
     * 清理阈值
     */
    private function sanitizeThreshold($value): int
    {
        $threshold = (int) $value;
        return max(1, min(100, $threshold));
    }

    /**
     * 清理强制模式
     */
    private function sanitizeMode(string $mode): string
    {
        $allowed = [self::MODE_ALERT, self::MODE_DISABLE, self::MODE_DOWNGRADE];
        return in_array($mode, $allowed, true) ? $mode : self::MODE_ALERT;
    }

    /**
     * 检查预算功能是否启用
     */
    public function is_enabled(): bool
    {
        $settings = $this->get_settings();
        return !empty($settings['enabled']);
    }

    /**
     * 获取全局预算设置
     */
    public function get_global_budget(): array
    {
        $settings = $this->get_settings();
        return $settings['global'] ?? $this->get_defaults()['global'];
    }

    /**
     * 获取服务商预算设置
     */
    public function get_provider_budget(string $provider): ?array
    {
        $settings = $this->get_settings();
        return $settings['providers'][$provider] ?? null;
    }

    /**
     * 获取通知设置
     */
    public function get_notification_settings(): array
    {
        $settings = $this->get_settings();
        return $settings['notifications'] ?? $this->get_defaults()['notifications'];
    }

    /**
     * 获取强制模式
     */
    public function get_enforcement_mode(): string
    {
        $settings = $this->get_settings();
        return $settings['enforcement_mode'] ?? self::MODE_ALERT;
    }

    /**
     * 获取告警阈值
     */
    public function get_alert_threshold(): int
    {
        $global = $this->get_global_budget();
        return $global['alert_threshold'] ?? 80;
    }

    /**
     * 检查是否有任何限额设置
     */
    public function has_any_limits(): bool
    {
        $global = $this->get_global_budget();

        if (($global['daily_limit_usd'] ?? 0) > 0 || ($global['daily_limit_cny'] ?? 0) > 0) {
            return true;
        }
        if (($global['monthly_limit_usd'] ?? 0) > 0 || ($global['monthly_limit_cny'] ?? 0) > 0) {
            return true;
        }

        $settings = $this->get_settings();
        return !empty($settings['providers']);
    }

    /**
     * 获取强制模式的显示名称
     */
    public static function get_mode_label(string $mode): string
    {
        $labels = [
            self::MODE_ALERT    => __('仅告警', 'wpmind'),
            self::MODE_DISABLE  => __('禁用服务', 'wpmind'),
            self::MODE_DOWNGRADE => __('降级模型', 'wpmind'),
        ];
        return $labels[$mode] ?? $mode;
    }

    /**
     * 获取所有强制模式选项
     */
    public static function get_mode_options(): array
    {
        return [
            self::MODE_ALERT    => __('仅告警 - 超限时发送通知，不阻止请求', 'wpmind'),
            self::MODE_DISABLE  => __('禁用服务 - 超限时禁用该服务商', 'wpmind'),
            self::MODE_DOWNGRADE => __('降级模型 - 超限时自动切换到更便宜的模型', 'wpmind'),
        ];
    }
}
