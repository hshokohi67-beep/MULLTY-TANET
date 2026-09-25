<?php

namespace App\Modules\Billing\Support;

use App\Modules\Analytics\Support\SalesRules;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Models\TenantUser;
use App\Support\Localization\JalaliDate;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/** Current usage of the limited features, counted live (small, indexed counts). */
final class Usage
{
    /** @return array{branches: int, staff: int, products: int, monthly_orders: int} */
    public static function current(): array
    {
        return [
            'branches' => self::count('branches'),
            'staff' => self::count('staff'),
            'products' => self::count('products'),
            'monthly_orders' => self::count('monthly_orders'),
        ];
    }

    public static function count(string $feature): int
    {
        return match ($feature) {
            'branches' => Branch::query()->count(),
            'staff' => TenantUser::query()->where('status', TenantUser::STATUS_ACTIVE)->count(),
            'products' => Product::query()->count(),
            'monthly_orders' => Order::query()->whereDate('business_date', '>=', self::monthStart())->whereNotIn('status', SalesRules::EXCLUDED)->count(),
            default => 0,
        };
    }

    /** First day of the current Jalali month, as a tenant-local date. */
    public static function monthStart(): string
    {
        $tz = app(TenantContext::class)->require()->timezone;
        $today = CarbonImmutable::now($tz);
        $j = JalaliDate::toJalali($today, $tz);

        return JalaliDate::toGregorian($j['year'], $j['month'], 1, $tz)->toDateString();
    }
}
