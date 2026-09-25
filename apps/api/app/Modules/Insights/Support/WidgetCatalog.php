<?php

namespace App\Modules\Insights\Support;

use App\Modules\Identity\Support\PermissionCatalog as P;
use Closure;

/**
 * Every dashboard widget: who may see it, the sizes it supports, and the default layouts per role.
 * The frontend renders them; the backend is the authority on what a user may place and fetch.
 */
final class WidgetCatalog
{
    public const SIZES = ['sm', 'md', 'lg'];

    /**
     * @return array<string, array{title: string, description: string, permission: string, sizes: list<string>, default_size: string}>
     */
    public static function all(): array
    {
        $w = fn (string $title, string $description, string $permission, array $sizes, string $default) => compact('title', 'description', 'permission', 'sizes') + ['default_size' => $default];

        return [
            'kpis' => $w('خلاصه‌ی فروش', 'فروش، تعداد سفارش، میانگین و لغوها با مقایسه', P::ORDERS_VIEW, ['lg'], 'lg'),
            'goal' => $w('هدف فروش', 'پیشرفت امروز و این ماه نسبت به هدف', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'sales_chart' => $w('نمودار فروش', 'ساعت‌به‌ساعت یا روزانه، در مقایسه با روز معمول', P::ORDERS_VIEW, ['md', 'lg'], 'md'),
            'live' => $w('همین حالا', 'سفارش‌های باز، دیرکرد، درخواست میزها و صف آشپزخانه', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'top_products' => $w('پرفروش‌ها', 'پرفروش‌ترین آیتم‌های منو', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'channel_mix' => $w('کانال‌های فروش', 'سهم میز، بیرون‌بر، ارسال، صندوق و تلفنی', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'heatmap' => $w('ساعت‌های شلوغ هفته', 'فروش هر روز هفته در هر ساعت، ۴ هفته‌ی اخیر', P::ORDERS_VIEW, ['md', 'lg'], 'lg'),
            'tables_now' => $w('وضعیت میزها', 'میزهای پر، مدت نشستن و فروش سالن به ازای هر صندلی', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'kitchen_speed' => $w('سرعت آشپزخانه', 'زمان آماده‌سازی و دیرکرد به تفکیک ایستگاه', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'cancellations' => $w('لغوها', 'سفارش‌های لغو و ردشده و دلیل‌هایشان', P::ORDERS_VIEW, ['sm', 'md'], 'sm'),
            'branches' => $w('مقایسه‌ی شعبه‌ها', 'فروش و سفارش هر شعبه', P::ORDERS_VIEW, ['md', 'lg'], 'md'),
            'payment_mix' => $w('روش‌های پرداخت', 'سهم نقدی، کارتخوان، آنلاین و کیف پول', P::PAYMENTS_VIEW, ['sm', 'md'], 'sm'),
            'payment_health' => $w('سلامت پرداخت آنلاین', 'درصد موفقیت درگاه و پرداخت‌های ناموفق', P::PAYMENTS_VIEW, ['sm', 'md'], 'sm'),
            'customers' => $w('مشتریان', 'عضو تازه، خریدار قدیمی و تولدهای این هفته', P::CUSTOMERS_VIEW, ['sm', 'md'], 'sm'),
            'at_risk' => $w('مشتریان در خطر رفتن', 'مشتریان ثابتی که مدتی نیامده‌اند', P::CUSTOMERS_VIEW, ['sm', 'md'], 'sm'),
            'club_liability' => $w('بدهی باشگاه', 'موجودی کیف پول و امتیاز مشتریان، کش‌بک داده‌شده', P::CUSTOMERS_VIEW, ['sm', 'md'], 'sm'),
            'discounts' => $w('عملکرد تخفیف‌ها', 'دفعات استفاده، مبلغ تخفیف و فروش هر تخفیف', P::DISCOUNTS_MANAGE, ['sm', 'md'], 'md'),
            'shift_notes' => $w('یادداشت شیفت', 'پیام شیفت قبل برای شیفت بعد', P::TENANT_VIEW, ['sm', 'md'], 'sm'),
            'profit' => $w('سود و زیان', 'فروش منهای بهای مواد، دستمزد و هزینه‌ها؛ درصد هزینه‌ی اصلی (پرایم کاست)', P::EXPENSES_MANAGE, ['md', 'lg'], 'md'),
            'labour' => $w('نیروی کار', 'چه کسانی سر کارند و هزینه‌ی دستمزد امروز نسبت به فروش', P::STAFF_MANAGE, ['sm', 'md'], 'sm'),
            'expenses' => $w('هزینه‌ها', 'هزینه‌های این بازه به تفکیک دسته', P::EXPENSES_MANAGE, ['sm', 'md'], 'sm'),
            'food_cost' => $w('بهای تمام‌شده و سود ناخالص', 'هزینه‌ی مواد اولیه‌ی فروش، درصد فود کاست و سودآورترین آیتم‌ها', P::INVENTORY_VIEW, ['md', 'lg'], 'md'),
            'stock_alerts' => $w('هشدار موجودی', 'مواد اولیه‌ی رو به اتمام یا منفی', P::INVENTORY_VIEW, ['sm', 'md'], 'sm'),
            'stories' => $w('استوری‌ها', 'استوری‌های در حال نمایش، بازدید و کلیک هر کدام', P::STOREFRONT_MANAGE, ['sm', 'md'], 'sm'),
        ];
    }

    /** @var array<string, list<string>> default widget keys per role */
    private const DEFAULTS = [
        'owner' => ['kpis', 'goal', 'profit', 'sales_chart', 'live', 'top_products', 'channel_mix', 'payment_mix', 'customers', 'heatmap', 'club_liability', 'at_risk', 'shift_notes'],
        'manager' => ['kpis', 'sales_chart', 'live', 'labour', 'top_products', 'channel_mix', 'kitchen_speed', 'tables_now', 'payment_mix', 'customers', 'cancellations', 'shift_notes'],
        'cashier' => ['live', 'kpis', 'tables_now', 'payment_mix', 'shift_notes'],
        'kitchen' => ['live', 'kitchen_speed', 'top_products', 'shift_notes'],
        'waiter' => ['live', 'tables_now', 'shift_notes'],
    ];

    /**
     * The default layout for someone with these role keys (the first role that has a default wins,
     * in order of seniority), limited to what they may see.
     *
     * @param  list<string>  $roleKeys
     * @param  Closure(string): bool  $can
     * @return list<array{key: string, size: string}>
     */
    public static function defaultFor(array $roleKeys, Closure $can): array
    {
        $role = collect(['owner', 'manager', 'cashier', 'kitchen', 'waiter'])->first(fn (string $r) => in_array($r, $roleKeys, true)) ?? 'waiter';

        return self::visible(array_map(fn (string $key) => ['key' => $key, 'size' => self::all()[$key]['default_size']], self::DEFAULTS[$role]), $can);
    }

    /**
     * Drops widgets the user may not see and fixes invalid sizes.
     *
     * @param  list<array{key: string, size: string}>  $widgets
     * @param  Closure(string): bool  $can
     * @return list<array{key: string, size: string}>
     */
    public static function visible(array $widgets, Closure $can): array
    {
        $all = self::all();
        $out = [];

        foreach ($widgets as $widget) {
            $def = $all[$widget['key']] ?? null;
            if ($def === null || ! $can($def['permission']) || isset($out[$widget['key']])) {
                continue;
            }

            $out[$widget['key']] = ['key' => $widget['key'], 'size' => in_array($widget['size'], $def['sizes'], true) ? $widget['size'] : $def['default_size']];
        }

        return array_values($out);
    }
}
