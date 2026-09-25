<?php

namespace App\Modules\Operations\Support;

use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** A tenant-local date range from the request (?from=YYYY-MM-DD&to=YYYY-MM-DD) as UTC instants. */
final class LocalRange
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string} from, to (exclusive), from date, to date */
    public static function from(Request $request, int $defaultDays = 7): array
    {
        $tz = app(TenantContext::class)->require()->timezone;
        $v = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = isset($v['to']) ? CarbonImmutable::parse($v['to'], $tz) : CarbonImmutable::now($tz)->startOfDay();
        $from = isset($v['from']) ? CarbonImmutable::parse($v['from'], $tz) : $to->subDays($defaultDays - 1);

        // Cap the range so a report can't scan years of rows by accident.
        if ($from->diffInDays($to) > 370) {
            $from = $to->subDays(370);
        }

        return [$from->startOfDay()->utc(), $to->startOfDay()->addDay()->utc(), $from->toDateString(), $to->toDateString()];
    }
}
