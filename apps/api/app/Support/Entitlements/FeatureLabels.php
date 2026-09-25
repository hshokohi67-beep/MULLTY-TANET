<?php

namespace App\Support\Entitlements;

/** Persian names of the plan features, for messages (the catalogue itself lives in Billing). */
final class FeatureLabels
{
    public const LABELS = [
        'online_payments' => 'پرداخت آنلاین',
        'loyalty' => 'باشگاه مشتریان',
        'inventory' => 'انبار و خرید',
        'operations' => 'کارکنان و هزینه‌ها',
        'reports' => 'گزارش‌ها',
        'stories' => 'استوری',
        'custom_domain' => 'دامنه‌ی اختصاصی',
        'marketplace_featured' => 'ویترین ویژه در بازارگاه',
        'branches' => 'شعبه',
        'staff' => 'اعضای تیم',
        'products' => 'محصول',
        'monthly_orders' => 'سفارش ماهانه',
    ];

    public static function of(string $feature): string
    {
        return self::LABELS[$feature] ?? $feature;
    }
}
