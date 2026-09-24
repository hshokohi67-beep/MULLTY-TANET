<?php

namespace App\Modules\Core\Support;

/**
 * The only tenant setting keys that exist. Unknown keys are rejected, so the
 * settings table can never become an untyped dumping ground.
 * Later modules register their keys here.
 */
final class TenantSettingsRegistry
{
    /**
     * @return array<string, array{type: 'string'|'bool'|'int', secret: bool, label: string, rules: list<string>, default: mixed}>
     */
    public static function definitions(): array
    {
        return [
            'contact.phone' => ['type' => 'string', 'secret' => false, 'label' => 'تلفن تماس', 'rules' => ['nullable', 'string', 'max:20'], 'default' => null],
            'contact.instagram' => ['type' => 'string', 'secret' => false, 'label' => 'اینستاگرام', 'rules' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'], 'default' => null],
            'orders.allow_preorder_when_closed' => ['type' => 'bool', 'secret' => false, 'label' => 'پذیرش پیش‌سفارش در ساعات تعطیلی', 'rules' => ['boolean'], 'default' => true],
            'preorder.lead_minutes' => ['type' => 'int', 'secret' => false, 'label' => 'حداقل فاصله تا زمان تحویل پیش‌سفارش (دقیقه)', 'rules' => ['integer', 'between:0,720'], 'default' => 30],
            'preorder.max_days' => ['type' => 'int', 'secret' => false, 'label' => 'پیش‌سفارش حداکثر چند روز جلوتر', 'rules' => ['integer', 'between:0,14'], 'default' => 3],
            'preorder.slot_minutes' => ['type' => 'int', 'secret' => false, 'label' => 'فاصله‌ی بازه‌های زمانی (دقیقه)', 'rules' => ['integer', 'in:10,15,20,30,60'], 'default' => 15],
            'preorder.slot_capacity' => ['type' => 'int', 'secret' => false, 'label' => 'ظرفیت هر بازه (۰ = نامحدود)', 'rules' => ['integer', 'between:0,500'], 'default' => 0],
            'preorder.release_minutes' => ['type' => 'int', 'secret' => false, 'label' => 'ارسال به آشپزخانه چند دقیقه قبل از زمان تحویل', 'rules' => ['integer', 'between:0,240'], 'default' => 20],
            'payments.online.enabled' => ['type' => 'bool', 'secret' => false, 'label' => 'پرداخت آنلاین', 'rules' => ['boolean'], 'default' => false],
            'payments.zarinpal.merchant_id' => ['type' => 'string', 'secret' => true, 'label' => 'مرچنت کد زرین‌پال', 'rules' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/'], 'default' => null],
            'loyalty.enabled' => ['type' => 'bool', 'secret' => false, 'label' => 'باشگاه مشتریان فعال است', 'rules' => ['boolean'], 'default' => false],
            'loyalty.points_per_100k' => ['type' => 'int', 'secret' => false, 'label' => 'امتیاز به ازای هر ۱۰ هزار تومان خرید', 'rules' => ['integer', 'min:0', 'max:1000'], 'default' => 1],
            'loyalty.point_value' => ['type' => 'int', 'secret' => false, 'label' => 'ارزش هر امتیاز (ریال)', 'rules' => ['integer', 'min:0', 'max:10000000'], 'default' => 1000],
            'loyalty.min_redeem_points' => ['type' => 'int', 'secret' => false, 'label' => 'حداقل امتیاز برای تبدیل', 'rules' => ['integer', 'min:1', 'max:1000000'], 'default' => 100],
            'loyalty.birthday_wallet_gift' => ['type' => 'int', 'secret' => false, 'label' => 'هدیه‌ی تولد به کیف پول (ریال)', 'rules' => ['integer', 'min:0', 'max:100000000'], 'default' => 0],
            'loyalty.birthday_points' => ['type' => 'int', 'secret' => false, 'label' => 'امتیاز هدیه‌ی تولد', 'rules' => ['integer', 'min:0', 'max:1000000'], 'default' => 0],
            'loyalty.referral_referrer_reward' => ['type' => 'int', 'secret' => false, 'label' => 'پاداش معرف (ریال)', 'rules' => ['integer', 'min:0', 'max:100000000'], 'default' => 0],
            'loyalty.referral_referee_reward' => ['type' => 'int', 'secret' => false, 'label' => 'پاداش دوست معرفی‌شده (ریال)', 'rules' => ['integer', 'min:0', 'max:100000000'], 'default' => 0],
            'wallet.payments_enabled' => ['type' => 'bool', 'secret' => false, 'label' => 'پرداخت با کیف پول', 'rules' => ['boolean'], 'default' => true],
            'kds.auto_complete_dine_in' => ['type' => 'bool', 'secret' => false, 'label' => 'تکمیل خودکار سفارش‌های سالن و میز پس از آماده شدن', 'rules' => ['boolean'], 'default' => true],
            'kds.auto_complete_takeaway' => ['type' => 'bool', 'secret' => false, 'label' => 'تکمیل خودکار سفارش‌های بیرون‌بر پس از آماده شدن', 'rules' => ['boolean'], 'default' => false],
            'goals.daily_sales' => ['type' => 'int', 'secret' => false, 'label' => 'هدف فروش روزانه (ریال)', 'rules' => ['integer', 'min:0', 'max:100000000000000'], 'default' => 0],
            'goals.monthly_sales' => ['type' => 'int', 'secret' => false, 'label' => 'هدف فروش ماهانه (ریال)', 'rules' => ['integer', 'min:0', 'max:100000000000000'], 'default' => 0],
            'reports.daily_sms' => ['type' => 'bool', 'secret' => false, 'label' => 'پیامک گزارش پایان روز برای مالک', 'rules' => ['boolean'], 'default' => false],
            'reports.daily_sms_hour' => ['type' => 'int', 'secret' => false, 'label' => 'ساعت ارسال گزارش پایان روز', 'rules' => ['integer', 'between:0,23'], 'default' => 23],
            'integrations.sms.kavenegar_api_key' => ['type' => 'string', 'secret' => true, 'label' => 'کلید API کاوه‌نگار', 'rules' => ['nullable', 'string', 'max:200'], 'default' => null],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function isSecret(string $key): bool
    {
        return self::definitions()[$key]['secret'] ?? false;
    }

    public static function cast(string $key, ?string $raw): mixed
    {
        if ($raw === null) {
            return self::definitions()[$key]['default'] ?? null;
        }

        return match (self::definitions()[$key]['type']) {
            'bool' => $raw === '1',
            'int' => (int) $raw,
            default => $raw,
        };
    }

    public static function serialize(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (self::definitions()[$key]['type']) {
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /** Masks a secret for display: only the last 4 characters remain visible. */
    public static function mask(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return str_repeat('•', 8).mb_substr($plain, -4);
    }
}
