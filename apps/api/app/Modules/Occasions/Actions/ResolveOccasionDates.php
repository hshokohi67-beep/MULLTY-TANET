<?php

namespace App\Modules\Occasions\Actions;

use App\Modules\Occasions\Models\Occasion;
use App\Modules\Occasions\Models\OccasionDate;
use App\Modules\Occasions\Support\OccasionDates;
use Carbon\CarbonImmutable;

/**
 * Keeps `occasion_dates` filled for last, this and next year of each occasion's own calendar.
 * Confirmed dates (checked by a platform admin) are never moved. Idempotent; runs daily.
 */
final class ResolveOccasionDates
{
    /** @return int occurrences created or moved */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $changed = 0;

        foreach (Occasion::query()->where('is_active', true)->where('is_one_off', false)->get() as $occasion) {
            $current = OccasionDates::currentYear($occasion->calendar, $today);
            foreach ([$current - 1, $current, $current + 1] as $year) {
                $range = OccasionDates::occurrence($occasion, $year);
                if ($range === null) {
                    continue;
                }
                [$start, $end] = $range;

                $existing = OccasionDate::query()->where('occasion_id', $occasion->id)->where('year', $year)->first();
                if ($existing?->confirmed) {
                    continue;
                }
                if ($existing === null) {
                    OccasionDate::query()->create(['occasion_id' => $occasion->id, 'year' => $year, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()]);
                    $changed++;
                } elseif ($existing->starts_on->toDateString() !== $start->toDateString() || $existing->ends_on->toDateString() !== $end->toDateString()) {
                    $existing->update(['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()]);
                    $changed++;
                }
            }
        }

        return $changed;
    }
}
