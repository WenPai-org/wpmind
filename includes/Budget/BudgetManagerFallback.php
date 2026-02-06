<?php
/**
 * Budget Manager Fallback - 预算管理器回退实现
 *
 * 当 Cost Control 模块未加载时使用此实现
 *
 * @package WPMind
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Budget;

/**
 * 回退实现：保持原有功能
 */
class BudgetManager
{
    private const OPTION_KEY = 'wpmind_budget_settings';

    public const MODE_ALERT    = 'alert';
    public const MODE_DISABLE  = 'disable';
    public const MODE_DOWNGRADE = 'downgrade';

    private static ?BudgetManager $instance = null;
    private ?array $settings = null;

    public static function instance(): BudgetManager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

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

    public function get_settings(): array
    {
        if (null === $this->settings) {
            $saved = get_option(self::OPTION_KEY, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            $this->settings = $this->recursiveMerge($saved, $this->get_defaults());
        }
        return $this->settings;
    }

    private function recursiveMerge(array $args, array $defaults): array
    {
        $result = $defaults;
        foreach ($args as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                if ($this->isAssociativeArray($defaults[$key])) {
                    $result[$key] = $this->recursiveMerge($value, $defaults[$key]);
                } else {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function save_settings(array $settings): bool
    {
        $sanitized = $this->sanitizeSettings($settings);
        $result = update_option(self::OPTION_KEY, $sanitized, false);
        $this->settings = null;
        return $result;
    }

    private function sanitizeSettings(array $input): array
    {
        $sanitized = [];
        $sanitized['enabled'] = !empty($input['enabled']);

        $sanitized['global'] = [
            'daily_limit_usd'   => $this->sanitizeAmount($input['global']['daily_limit_usd'] ?? 0),
            'daily_limit_cny'   => $this->sanitizeAmount($input['global']['daily_limit_cny'] ?? 0),
            'monthly_limit_usd' => $this->sanitizeAmount($input['global']['monthly_limit_usd'] ?? 0),
            'monthly_limit_cny' => $this->sanitizeAmount($input['global']['monthly_limit_cny'] ?? 0),
            'alert_threshold'   => $this->sanitizeThreshold($input['global']['alert_threshold'] ?? 80),
        ];

        $sanitized['enforcement_mode'] = $this->sanitizeMode($input['enforcement_mode'] ?? self::MODE_ALERT);

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

        $sanitized['notifications'] = [
            'admin_notice'  => !empty($input['notifications']['admin_notice']),
            'email_alert'   => !empty($input['notifications']['email_alert']),
            'email_address' => sanitize_email($input['notifications']['email_address'] ?? ''),
        ];

        return $sanitized;
    }

    private function sanitizeAmount($value): float
    {
        $amount = (float) $value;
        return max(0, round($amount, 2));
    }

    private function sanitizeThreshold($value): int
    {
        $threshold = (int) $value;
        return max(1, min(100, $threshold));
    }

    private function sanitizeMode(string $mode): string
    {
        $allowed = [self::MODE_ALERT, self::MODE_DISABLE, self::MODE_DOWNGRADE];
        return in_array($mode, $allowed, true) ? $mode : self::MODE_ALERT;
    }

    public function is_enabled(): bool
    {
        $settings = $this->get_settings();
        return !empty($settings['enabled']);
    }

    public function get_global_budget(): array
    {
        $settings = $this->get_settings();
        return $settings['global'] ?? $this->get_defaults()['global'];
    }

    public function get_provider_budget(string $provider): ?array
    {
        $settings = $this->get_settings();
        return $settings['providers'][$provider] ?? null;
    }

    public function get_notification_settings(): array
    {
        $settings = $this->get_settings();
        return $settings['notifications'] ?? $this->get_defaults()['notifications'];
    }

    public function get_enforcement_mode(): string
    {
        $settings = $this->get_settings();
        return $settings['enforcement_mode'] ?? self::MODE_ALERT;
    }

    public function get_alert_threshold(): int
    {
        $global = $this->get_global_budget();
        return $global['alert_threshold'] ?? 80;
    }

    public function has_any_limits(): bool
    {
        $global = $this->get_global_budget();

        if ($global['daily_limit_usd'] > 0 || $global['daily_limit_cny'] > 0) {
            return true;
        }
        if ($global['monthly_limit_usd'] > 0 || $global['monthly_limit_cny'] > 0) {
            return true;
        }

        $settings = $this->get_settings();
        return !empty($settings['providers']);
    }

    public static function get_mode_label(string $mode): string
    {
        $labels = [
            self::MODE_ALERT    => __('仅告警', 'wpmind'),
            self::MODE_DISABLE  => __('禁用服务', 'wpmind'),
            self::MODE_DOWNGRADE => __('降级模型', 'wpmind'),
        ];
        return $labels[$mode] ?? $mode;
    }

    public static function get_mode_options(): array
    {
        return [
            self::MODE_ALERT    => __('仅告警 - 超限时发送通知，不阻止请求', 'wpmind'),
            self::MODE_DISABLE  => __('禁用服务 - 超限时禁用该服务商', 'wpmind'),
            self::MODE_DOWNGRADE => __('降级模型 - 超限时自动切换到更便宜的模型', 'wpmind'),
        ];
    }
}
