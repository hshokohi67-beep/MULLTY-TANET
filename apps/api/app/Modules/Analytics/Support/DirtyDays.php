<?php

namespace App\Modules\Analytics\Support;

use App\Modules\Core\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Marks a tenant's business day for re-aggregation. Called from model events of the source
 * modules (orders, payments, costs, expenses, attendance, waste); cheap and idempotent.
 */
final class DirtyDays
{
    public static function mark(?string $tenantId, CarbonInterface|string|null $date): void
    {
        if ($tenantId === null || $date === null) {
            return;
        }
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        DB::table('metric_dirty_days')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'business_date' => $day,
            'created_at' => now(),
        ]);
    }

    /** The tenant-local calendar day of an instant (for data keyed by time rather than business date). */
    public static function markInstant(?string $tenantId, ?CarbonInterface $at): void
    {
        if ($tenantId === null || $at === null) {
            return;
        }
        $timezone = Tenant::query()->whereKey($tenantId)->value('timezone') ?? 'Asia/Tehran';

        self::mark($tenantId, $at->copy()->setTimezone($timezone));
    }
}
