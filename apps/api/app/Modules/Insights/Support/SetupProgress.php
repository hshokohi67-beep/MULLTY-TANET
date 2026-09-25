<?php

namespace App\Modules\Insights\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantBranding;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Storefront\Models\Story;

/**
 * The owner's setup checklist, computed from real data in one place. Essentials first; optional
 * steps can be skipped (remembered per tenant in `onboarding.skipped`).
 */
final class SetupProgress
{
    /** @return array{steps: list<array{key: string, title: string, hint: string, href: string, done: bool, essential: bool}>, done: int, total: int} */
    public static function get(): array
    {
        $branding = TenantBranding::query()->first();
        $skipped = self::skipped();

        $steps = [
            ['key' => 'menu', 'essential' => true, 'done' => Product::query()->exists(), 'href' => '/dashboard/menu',
                'title' => 'اولین آیتم‌های منو را اضافه کنید', 'hint' => 'با «افزودن سریع» فقط نام و قیمت کافی است.'],
            ['key' => 'logo', 'essential' => true, 'done' => (bool) $branding?->logo_path, 'href' => '/dashboard/settings#brand',
                'title' => 'لوگوی کافه را بارگذاری کنید', 'hint' => 'در منوی آنلاین، سربرگ و فاکتورها دیده می‌شود.'],
            ['key' => 'address', 'essential' => true, 'done' => Branch::query()->whereNotNull('address')->exists(), 'href' => '/dashboard/branches',
                'title' => 'آدرس شعبه را کامل کنید', 'hint' => 'برای محدوده‌ی ارسال و نقشه لازم است.'],
            ['key' => 'hours', 'essential' => true, 'done' => BranchOpeningHour::query()->exists(), 'href' => '/dashboard/branches',
                'title' => 'ساعات کاری را تعیین کنید', 'hint' => 'مشتری می‌بیند کافه باز است یا نه و پیش‌سفارش بر اساس آن است.'],
            ['key' => 'payment', 'essential' => false, 'done' => (bool) TenantSettings::get('payments.online.enabled') && TenantSettings::get('payments.zarinpal.merchant_id') !== null, 'href' => '/dashboard/settings#payments',
                'title' => 'پرداخت آنلاین را فعال کنید', 'hint' => 'مرچنت کد زرین‌پال را وارد کنید تا مشتری آنلاین بپردازد.'],
            ['key' => 'cover', 'essential' => false, 'done' => (bool) $branding?->cover_path, 'href' => '/dashboard/settings#brand',
                'title' => 'عکس کاور منوی آنلاین', 'hint' => 'یک عکس افقی از فضای کافه، بالای منو.'],
            ['key' => 'tables', 'essential' => false, 'done' => RestaurantTable::query()->whereHas('qrCodes')->exists(), 'href' => '/dashboard/tables',
                'title' => 'QR میزها را بسازید', 'hint' => 'مشتری سر میز منو را می‌بیند و سفارش می‌دهد.'],
            ['key' => 'kitchen', 'essential' => false, 'done' => KitchenStation::query()->exists(), 'href' => '/dashboard/kitchen',
                'title' => 'ایستگاه آشپزخانه را تعریف کنید', 'hint' => 'سفارش‌ها روی تبلت آشپزخانه و بار نمایش داده می‌شوند.'],
            ['key' => 'delivery', 'essential' => false, 'done' => DeliveryZone::query()->exists(), 'href' => '/dashboard/delivery',
                'title' => 'محدوده‌ی ارسال با پیک', 'hint' => 'اگر ارسال دارید، شعاع و هزینه‌ی ارسال را تعیین کنید.'],
            ['key' => 'team', 'essential' => false, 'done' => TenantUser::query()->count() > 1, 'href' => '/dashboard/team',
                'title' => 'همکاران را اضافه کنید', 'hint' => 'صندوق‌دار، آشپزخانه و سالن‌دار هرکدام دسترسی خودشان را دارند.'],
            ['key' => 'story', 'essential' => false, 'done' => Story::query()->exists(), 'href' => '/dashboard/stories',
                'title' => 'اولین استوری را بگذارید', 'hint' => 'محصول تازه یا تخفیف امروز را بالای منو نشان دهید.'],
        ];

        $steps = array_values(array_filter($steps, fn (array $s) => $s['essential'] || $s['done'] || ! in_array($s['key'], $skipped, true)));

        return [
            'steps' => array_map(fn (array $s) => [
                'key' => $s['key'], 'title' => $s['title'], 'hint' => $s['hint'], 'href' => $s['href'], 'done' => $s['done'], 'essential' => $s['essential'],
            ], $steps),
            'done' => count(array_filter($steps, fn (array $s) => $s['done'])),
            'total' => count($steps),
        ];
    }

    /** @return list<string> */
    public static function skipped(): array
    {
        return array_values(array_filter(explode(',', (string) TenantSettings::get('onboarding.skipped'))));
    }
}
