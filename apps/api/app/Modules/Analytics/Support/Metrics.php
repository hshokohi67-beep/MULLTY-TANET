<?php

namespace App\Modules\Analytics\Support;

use App\Modules\Analytics\Actions\RollupDay;
use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Analytics\Models\HourlyMetric;
use App\Modules\Analytics\Models\MetricDirtyDay;
use App\Modules\Analytics\Models\ProductMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the aggregates for the current tenant. Before reading, dirty days inside the range are
 * rolled up synchronously (up to SYNC_LIMIT), so reports reflect every change up to this request;
 * anything beyond that is left to `analytics:rollup` and reported as stale.
 */
final class Metrics
{
    public const SYNC_LIMIT = 31;

    /** @return bool true when some days in the range are still waiting for the scheduler */
    public static function refresh(string $from, string $to): bool
    {
        $dirty = MetricDirtyDay::query()->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
            ->orderByDesc('business_date')->limit(self::SYNC_LIMIT + 1)->get()
            ->map(fn (MetricDirtyDay $d) => $d->business_date->toDateString());

        $rollup = app(RollupDay::class);
        foreach ($dirty->take(self::SYNC_LIMIT) as $day) {
            $rollup->handle($day);
        }

        return $dirty->count() > self::SYNC_LIMIT;
    }

    /** @return Collection<int, DailyMetric> */
    public static function daily(string $from, string $to, ?string $branchId): Collection
    {
        return self::range(DailyMetric::query(), $from, $to, $branchId)->orderBy('business_date')->get();
    }

    /** @return Collection<int, HourlyMetric> */
    public static function hourly(string $from, string $to, ?string $branchId): Collection
    {
        return self::range(HourlyMetric::query(), $from, $to, $branchId)->get();
    }

    /** @return Collection<int, ProductMetric> */
    public static function products(string $from, string $to, ?string $branchId): Collection
    {
        return self::range(ProductMetric::query(), $from, $to, $branchId)->get();
    }

    /**
     * @template TModel of DailyMetric|HourlyMetric|ProductMetric
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function range(Builder $query, string $from, string $to, ?string $branchId): Builder
    {
        return $query->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id));
    }
}
